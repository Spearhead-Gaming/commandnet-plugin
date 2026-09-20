# Command Net

A [forumify](https://forumify.net) plugin for personnel and unit management: ranks,
positions, assignments, awards, qualifications, and operations with RSVP, attendance, and
after-action reports. Everything a soldier does feeds a single append-only service-record
timeline on their personnel file, rather than each module keeping its own history.

Built for a specific MILSIM community's Forumify install; not a general-purpose skeleton.

## Requirements

- PHP 8.4 or newer
- A Forumify 1.3.x install
- MySQL (for the migration in `migrations/`)

## Install

```bash
composer require majesticdev/commandnet-plugin
```

Then, from the Forumify install:

```bash
bin/console forumify:plugins:refresh
bin/console doctrine:migrations:migrate
```

## Entities

| Entity | Notes |
| --- | --- |
| `SoldierProfile` | 1:1 with the Forumify `User`, kept separate so the plugin can be removed cleanly. Rank, service number, callsign, status, enlistment/discharge dates. |
| `Unit` | Self-referencing tree (parent/children), sortable, optional commander. |
| `Rank`, `Position`, `Award`, `Qualification` | Flat, sortable catalogs managed in the admin panel. |
| `Assignment` | A soldier's posting to a unit (and optionally a position) over a date range. One assignment can be flagged primary. |
| `SoldierAward`, `SoldierQualification` | Join entities recording who issued what, and when. |
| `Operation` | An OPORD with a start/end time, optional unit, and status. Owns its RSVPs and AARs. |
| `OperationRSVP` | One per soldier per operation (`status`, and separately, `attended`, since intent and reality aren't the same field). |
| `OperationAAR` | After-action report. Many per operation by design — larger ops often get separate reports from each element lead rather than one summary. |
| `ServiceRecord` | The unified timeline entry. Assignments, awards, qualifications, and AARs each write one on success; deleting the entry that created one removes it too. |

## Frontend routes

| Route | Path | What it does |
| --- | --- | --- |
| `command_net_roster` | `/roster` | Active roster listing. |
| `command_net_roster_profile` | `/roster/{username}` | A soldier's personnel file: awards, qualifications, assignment history, service record timeline. |
| `command_net_roster_award` | `/roster/{username}/award` | Issue an award. |
| `command_net_roster_award_delete` | `/roster/{username}/award/{id}/delete` | Remove an award and its service record entry. |
| `command_net_roster_qualification` | `/roster/{username}/qualification` | Issue a qualification. |
| `command_net_roster_qualification_delete` | `/roster/{username}/qualification/{id}/delete` | Remove a qualification and its service record entry. |
| `command_net_roster_assignment` | `/roster/{username}/assignment` | Create an assignment (closes the soldier's current primary posting if the new one is primary). |
| `command_net_roster_assignment_delete` | `/roster/{username}/assignment/{id}/delete` | Remove an assignment and its service record entry. |
| `command_net_roster_service_record_delete` | `/roster/{username}/service-record/{id}/delete` | Remove a manual or otherwise-orphaned timeline entry directly. |
| `command_net_operations` | `/operations` | Upcoming/past operations. |
| `command_net_operation_detail` | `/operations/{id}` | OPORD, roster with attendance, RSVP controls, AARs. |
| `command_net_operation_rsvp` | `/operations/{id}/rsvp` (POST) | Set or change your own RSVP. |
| `command_net_operation_attendance` | `/operations/{id}/attendance` (POST) | Mark a soldier attended/absent; creates the RSVP row if they never responded. |
| `command_net_operation_aar` | `/operations/{id}/aar` | Submit an after-action report; combat service records are kept at one per soldier marked attended, however many AARs exist. |
| `command_net_operation_aar_delete` | `/operations/{id}/aar/{aarId}/delete` (POST) | Remove a report (its submitter or an operations manager only) and every service record it wrote. |

## Admin

Personnel, Units, Ranks, Positions, Awards, Qualifications, and Operations each get a
standard Forumify CRUD screen under **Admin → Command Net**. There's no separate admin
screen for awards issued, qualifications earned, assignments, or service records — those
are managed from the frontend personnel file instead, since they only make sense in the
context of one soldier.

## Permissions

Checked as `command-net.<area>.<action>` (the prefix is slugged from the plugin's display
name, "Command Net" — note the hyphen, unlike the underscored route/translation names).

| Permission | Grants |
| --- | --- |
| `command-net.roster.view` | View the roster and personnel files. |
| `command-net.admin.personnel.view` / `.manage` | View / edit personnel profiles, assignments, service records. |
| `command-net.admin.units.view` / `.manage` | View / edit units. Also gates Positions — a position isn't useful outside the context of a unit's org chart, so it doesn't get its own permission branch. |
| `command-net.admin.ranks.view` / `.manage` | View / edit the rank ladder. |
| `command-net.admin.awards.view` / `.manage` | View / edit the award catalog and issue/remove awards. |
| `command-net.admin.qualifications.view` / `.manage` | View / edit the qualification catalog and issue/remove qualifications. |
| `command-net.admin.operations.view` / `.manage` | View / edit operations, mark attendance, remove any AAR. |
| `command-net.operations.view` | View the operations list and detail pages. |
| `command-net.operations.rsvp` | RSVP to an operation. |
| `command-net.operations.submit_aar` | Submit an after-action report. |

`attendance.*`, `forms.*`, `courses.*`, and `reportin.*` are also declared in
`CommandNetPlugin::getPermissions()`, reserved for features that don't exist yet (see
below) — granting them today has no effect.

## Report In

Soldiers with `command-net.reportin.submit` get a **Report In** button on the roster. Turn on
enforcement under **Admin → Command Net → Report In Settings**: a daily scheduled task
(`command-net:report-in:run-checks`, 08:00) flags an active soldier AWOL once they go past
the configured number of days without reporting in, and optionally warns them as the deadline
approaches. Reporting in again restores Active. It reuses the AWOL role from AWOL Settings and
writes the same audit record and notification. A soldier with no report in on file gets a
baseline entry rather than being failed, so enabling this doesn't flag everyone at once. An AWOL
set by an admin or by missed operations is never cleared by reporting in.

## Design notes

- **Corrections happen by deletion, not editing.** Awards, qualifications, assignments,
  service records, and AARs can all be removed but never edited in place — the same
  "records are facts, not form fields" rule the MILHQ plugin uses for its own history.
  Fix a mistake by removing the wrong entry and creating the right one.
- **Service records are linked back to what created them.** `ServiceRecord` carries a
  nullable `sourceType`/`sourceId` pair set when an award, qualification, assignment, or
  AAR writes one, so deleting the source also removes the timeline entry it generated
  instead of leaving an orphan behind. Manually-added entries (once that exists — see
  below) leave both null.
- **Attendance is separate from RSVP.** A soldier's RSVP status is their stated intent;
  `attended` is a separate field an operations manager sets afterward, so a no-show who
  RSVP'd "attending" doesn't get a combat record, and someone who shows up unannounced can
  still get credit.

## Known gaps

This plugin is running against a live install, but a few things are worth knowing before
you rely on them:

- **No test suite.** Everything here is verified by having booted the plugin against a
  real install, not by an automated suite.
- **Rank changes don't write a service record.** Editing a soldier's rank in the admin
  form updates the field silently, in either direction.
- **No cycle guard on the unit tree.** The parent-unit picker excludes the unit itself but
  not its descendants.
- **OperationRSVP has no "withdraw."** A soldier can change their RSVP any time but can't
  clear it back to no response.

## Works well with

- [`forumify-id-card-plugin`](https://github.com/MajesticDevBox/forumify-id-card-plugin) —
  if installed, a soldier's personnel file gets a button to view or create their MILSIM ID
  card, and the ID card plugin can use this plugin as a personnel source without ever
  naming it anywhere public.
- [`command-net-theme`](https://github.com/Spearhead-Gaming/command-net-theme) — the
  frontend theme this plugin is designed to be used with; its homepage reads this plugin's
  `Operation` repository and online-count Twig function directly.
