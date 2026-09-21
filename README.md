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
| `command-net.admin.personnel.discharge` | Discharge or retire a soldier. |
| `command-net.admin.specialties.view` / `.manage` | View / edit the specialty catalog. |
| `command-net.admin.equipment.view` / `.manage` | View / edit the equipment catalog. |
| `command-net.admin.documents.view` / `.manage` | View / edit the document templates. |
| `command-net.admin.forms.view` / `.manage` | View forms and submissions / edit forms and review submissions. |
| `command-net.forms.submit` | See and fill in open forms at `/forms`. |
| `command-net.admin.units.view` / `.manage` | View / edit units. Also gates Positions — a position isn't useful outside the context of a unit's org chart, so it doesn't get its own permission branch. |
| `command-net.admin.ranks.view` / `.manage` | View / edit the rank ladder. |
| `command-net.admin.awards.view` / `.manage` | View / edit the award catalog and issue/remove awards. |
| `command-net.admin.qualifications.view` / `.manage` | View / edit the qualification catalog and issue/remove qualifications. |
| `command-net.admin.operations.view` / `.manage` | View / edit operations, mark attendance, remove any AAR. |
| `command-net.operations.view` | View the operations list and detail pages. |
| `command-net.operations.rsvp` | RSVP to an operation. |
| `command-net.operations.submit_aar` | Submit an after-action report. |
| `command-net.qualifications.view` | View the public qualifications board. |
| `command-net.attendance.view_own` / `.view_all` | View your own attendance record on `/attendance` / everyone's. |
| `command-net.promotions.view` | View promotion eligibility on `/promotions`. |
| `command-net.reportin.submit` | Use the Report In button. |
| `command-net.admin.reportin.view` / `.manage` | See each soldier's last report in / remove report ins and edit Report In Settings. |
| `command-net.admin.awol.manage` | Edit AWOL Settings. |

`admin.attendance`, `admin.courses` and `courses.enroll` are also
declared in `CommandNetPlugin::getPermissions()`, reserved for features that don't exist yet
(see below) — granting them today has no effect.

## Forms

Staff build forms under Admin → Command Net → Forms (`command-net.admin.forms.view` / `.manage`), such
as a leave request or a transfer request. Fields are written as text, one per line, and checked when
the form is saved:

    type | Label | required | options

Types are `text`, `textarea`, `number`, `boolean`, `date` and `select` (only select takes options,
comma separated); the third part is `required` or `optional`. For example
`select | Branch | required | Army, Navy, Air Force`. Members with `command-net.forms.submit` see open
forms at `/forms` and can follow the status of what they submitted. Staff review submissions under
Admin → Command Net → Form Submissions: accept or decline with an optional note, and the submitter is
notified. Answers are saved as text next to the question, so editing a form later never changes what
was already submitted.

Not included yet (MILHQ has): a point-and-click field editor, help text per field, custom statuses,
supervisor routing and using a form for enlistment.

## Documents

A **document** is a reusable rich-text template with `{placeholders}`, such as an award citation or a
promotion order (Admin → Command Net → Documents, `command-net.admin.documents.view` / `.manage`).
When issuing an award, issuing a qualification or creating an assignment from a personnel file, you
can pick one; it is shown under that entry in the service record, filled in for the soldier and
record. The placeholders (`{user_name}`, `{user_rank}`, `{record_title}`, ...) are listed in the
document editor. Values are HTML-escaped, and a placeholder that is not recognised is left as
written.

Not included yet: documents can not be attached to promotions or to entries created another way,
and there is no print or download view.

## Equipment

**Equipment** is a catalog of weapons and vehicles (Admin → Command Net → Equipment,
`command-net.admin.equipment.view` / `.manage`). Each is a primary weapon, a secondary weapon or a
vehicle. Positions list the primary and secondary weapons their holder may use, and units list the
vehicles they have. A soldier's personnel file shows a **Loadout** card worked out from the position
and unit of their primary assignment; nothing is stored per soldier, so changing a position or unit
changes it for everyone who holds it.

Not included yet: the Discord soldier and unit replies do not show equipment, and there is no
Squad XML export like MILHQ's.

## Specialties

A **specialty** is a soldier's trade (Combat Medic, Radio Operator, ...): one per soldier, set on
their profile in Admin → Command Net → Personnel, and shown on their personnel file and the roster.
Unlike a position it follows them between units. Manage them under Admin → Command Net →
Specialties (`command-net.admin.specialties.view` / `.manage`). A specialty can carry a forumify
**Role**: a soldier holds the role of their specialty, and it is removed if it changes or they are
discharged (map it to a Discord role in the Discord plugin to keep Discord in step).

Changes to a soldier's specialty are not written to their service record, and it is not shown in the
Discord `/command-net-soldier` reply yet.

## Enlistment

Turn it on under **Admin → Command Net → Enlistment Settings**. Signed-in members with a verified,
non-banned account can then apply at `/enlist` (add it to the menu with the Command Net menu item):
callsign, Steam ID, why they want to join, experience and availability. Staff review applications
under **Admin → Command Net → Enlistment** (`command-net.admin.enlistment.view` / `.manage`) and
accept or decline with an optional note.

Accepting creates the personnel file (or restores a discharged or retired one), sets the enlistment
date, gives the configured starting rank if they have none, posts them to the configured starting
unit, writes the enlistment and assignment records, and notifies the applicant. Declining just
notifies. A declined applicant can apply again; someone with a pending application, or who is
already enlisted, can't.

Unlike MILHQ, the application is a fixed form rather than one built in a form builder, and it
doesn't open a forum topic for recruiters; both come with a forms feature, which doesn't exist yet.

## Discharge

**Discharge** appears on each row of Admin → Command Net → Personnel and on the personnel edit
screen, for people with `command-net.admin.personnel.discharge`. Pick General, Honorable,
Dishonorable or Retirement (retirement sets the status to Retired, the others to Discharged), an
effective date and an optional reason. It ends the soldier's open assignments, removes their unit,
rank and AWOL roles, and writes a discharge service record. The soldier leaves the roster, and can
no longer RSVP or report in.

Unlike MILHQ it keeps everything: rank, awards, qualifications and the full service record stay
on the personnel file rather than being cleared, and it doesn't offer a final rank or new posting
as part of the discharge. Enlistment can bring a discharged soldier back, keeping their history.

## Promotions

`/promotions` (permission `command-net.promotions.view`) lists active soldiers against the
requirements of the next rank up — minimum time in the previous rank and required
qualifications, both set on the target rank. Anyone with `command-net.admin.personnel.manage`
also gets a **Promote** button on eligible rows; it re-checks the requirements, changes the
rank, writes the promotion record and notifies the soldier. Skipping the requirements is a
rank edit on the admin personnel form.

Ranks can be put in a **rank group** (Admin → Command Net → Rank Groups), such as Enlisted or
Officer. Promotion only moves up within a group, so the top of one track isn't offered the bottom
of the next; ranks with no group share one ladder.

A rank can be given a forumify **Role** in the admin. A soldier holds the role of their current
rank and loses every other rank's role on any rank change, from either the Promote button or the
admin form; map those roles to Discord roles in the Discord plugin to keep Discord in step.

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

- **Tests cover the services, not the whole plugin.** CI runs PHPUnit, phpcs and PHPStan, but
  the unit tests mock the repositories, so controllers, forms, templates, migrations, DQL
  queries and the scheduled Report In task are only verified by booting the plugin against a
  real install.
- **Features MILHQ has that this plugin doesn't yet:** an enlistment flow, forms and
  submissions, courses, a discharge flow, specialties, equipment and documents.
- **`src/Discord` isn't analysed by PHPStan** in CI, because it depends on the private
  `MajesticDev\Discord` plugin.

## Works well with

- [`forumify-id-card-plugin`](https://github.com/MajesticDevBox/forumify-id-card-plugin) —
  if installed, a soldier's personnel file gets a button to view or create their MILSIM ID
  card, and the ID card plugin can use this plugin as a personnel source without ever
  naming it anywhere public.
- [`command-net-theme`](https://github.com/Spearhead-Gaming/command-net-theme) — the
  frontend theme this plugin is designed to be used with; its homepage reads this plugin's
  `Operation` repository and online-count Twig function directly.
