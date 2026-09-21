# Command Net

A [forumify](https://forumify.net) plugin for personnel and unit management, built to be compared
against [forumify-milhq-plugin](https://github.com/forumify/forumify-milhq-plugin): ranks (in
promotion tracks), positions, specialties, equipment, assignments, awards, qualifications and
operations with RSVP, attendance and after-action reports, plus enlistment, discharge, forms,
courses, documents, promotions, Report In and AWOL detection. Everything a soldier does feeds a
single append-only service-record timeline on their personnel file, rather than each module keeping
its own history.

Built for a specific MILSIM community's Forumify install; not a general-purpose skeleton.

## Requirements

- PHP 8.4 or newer
- A Forumify 1.3.x install
- MySQL (for the migrations in `migrations/`)
- The Symfony scheduler running, for the daily Report In check (it does nothing until enabled)

Optional, picked up automatically when installed:

- The Forumify **calendar** plugin: operations are mirrored onto a community calendar.
- The Discord plugin (`MajesticDev\Discord`): slash commands, and mapping the forumify roles this
  plugin grants to Discord roles. See Integrations below.

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
| `SoldierProfile` | 1:1 with the Forumify `User`, kept separate so the plugin can be removed cleanly. Rank, specialty, service number, callsign, status (active, LOA, AWOL, discharged, retired), enlistment/discharge dates. |
| `Unit` | Self-referencing tree (parent/children), sortable, optional commander and forumify role, optional vehicles. |
| `Roster` | A named, sortable set of units shown as a tab on the roster page. |
| `Rank`, `RankGroup` | Ranks are a sortable ladder with promotion requirements and an optional forumify role; a group is a promotion track (Enlisted, Officer, ...). |
| `Position`, `Specialty`, `Award`, `Qualification` | Sortable catalogs managed in the admin panel. A position lists the weapons its holder may use; a specialty can carry a forumify role. |
| `Equipment` | A primary weapon, secondary weapon or vehicle. |
| `Document` | A rich-text template with `{placeholders}` that a service record can carry. |
| `Assignment` | A soldier's posting to a unit (and optionally a position) over a date range. One assignment can be flagged primary. |
| `SoldierAward`, `SoldierQualification` | Join entities recording who issued what, and when. |
| `Operation` | An OPORD with a start/end time, optional unit, and status. Owns its RSVPs and AARs. |
| `OperationRSVP` | One per soldier per operation (`status`, and separately, `attended`, since intent and reality aren't the same field). |
| `OperationAAR` | After-action report. Many per operation by design — larger ops often get separate reports from each element lead rather than one summary. |
| `ReportIn` | One entry each time a soldier reports in. |
| `EnlistmentApplication` | A request to join; accepting it creates or restores the personnel file. |
| `FormDefinition`, `FormSubmission` | A staff-built form and a member's filled-in copy of it (answers stored as text next to the question). |
| `Course`, `CourseClass`, `CourseClassStudent` | A course (with prerequisites and the qualifications a pass grants), a scheduled class of it, and a soldier enrolled in a class with their result. |
| `ServiceRecord` | The unified timeline entry. Assignments, awards, qualifications, AARs, promotions, course passes, AWOL changes and discharges each write one; deleting the entry that created one removes it too where that applies. |

## Frontend routes

| Route | Path | What it does |
| --- | --- | --- |
| `command_net_roster` | `/roster` | Active roster: one list, or a tab per roster when rosters are defined (`?roster=<id>`). |
| `command_net_units` | `/units` | Org chart. |
| `command_net_qualifications` | `/qualifications` | Public qualifications board. |
| `command_net_attendance` | `/attendance` | Your attendance record, or everyone's with the right permission. |
| `command_net_promotions` | `/promotions` | Promotion eligibility, with a Promote button for managers. |
| `command_net_promotions_promote` | `/promotions/{id}/promote` (POST) | Promote an eligible soldier. |
| `command_net_report_in` | `/roster/report-in` (POST) | Report in. |
| `command_net_report_in_delete` | `/roster/report-in/{id}/delete` (POST) | Remove a report in entry. |
| `command_net_roster_profile` | `/roster/{username}` | A soldier's personnel file: awards, qualifications, assignment history, loadout, service record timeline. |
| `command_net_roster_award` | `/roster/{username}/award` | Issue an award (optionally with a document). |
| `command_net_roster_award_delete` | `/roster/{username}/award/{id}/delete` | Remove an award and its service record entry. |
| `command_net_roster_qualification` | `/roster/{username}/qualification` | Issue a qualification (optionally with a document). |
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
| `command_net_enlist` | `/enlist` | Apply to join, and see the status of your last application. |
| `command_net_forms` | `/forms` | Open forms, and your own submissions with their status. |
| `command_net_form_fill` | `/forms/{id}` | Fill in a form. |
| `command_net_squad_xml` / `_squad_dtd` / `_squad_logo` | `/squad.xml`, `/squad.dtd`, `/logo.paa` | The Arma squad export (404 until enabled). |
| `command_net_courses` | `/courses` | Upcoming classes and all courses. |
| `command_net_course_class` | `/courses/class/{id}` | A class: its students, enrol/withdraw, and (for managers) recording results. |
| `command_net_course_class_enroll` / `_withdraw` / `_results` | `/courses/class/{id}/enroll`, `/withdraw`, `/results` (POST) | Enrol, withdraw, and record every student's result. |

## Admin

Every catalog has a standard Forumify CRUD screen under **Admin → Command Net**: Personnel, Units,
Ranks, Rank Groups, Rosters, Positions, Specialties, Equipment, Documents, Forms, Courses, Course
Classes, Awards, Qualifications and Operations. Form Submissions and Enlistment are review queues:
opening an entry is the review screen. Enlistment Settings, AWOL Settings, Report In Settings and Squad XML are
single settings pages, and Personnel rows have a Discharge action. There's no separate admin screen
for awards issued, qualifications earned, assignments, or service records — those are managed from
the frontend personnel file instead, since they only make sense in the context of one soldier.

## Permissions

Checked as `command-net.<area>.<action>` (the prefix is slugged from the plugin's display
name, "Command Net" — note the hyphen, unlike the underscored route/translation names).

| Permission | Grants |
| --- | --- |
| `command-net.roster.view` | View the roster and personnel files. |
| `command-net.admin.rosters.view` / `.manage` | View / edit the roster tabs. |
| `command-net.admin.personnel.view` / `.manage` | View / edit personnel profiles, assignments, service records. |
| `command-net.admin.personnel.discharge` | Discharge or retire a soldier. |
| `command-net.admin.enlistment.view` / `.manage` | View / review enlistment applications, and edit Enlistment Settings. |
| `command-net.admin.specialties.view` / `.manage` | View / edit the specialty catalog. |
| `command-net.admin.equipment.view` / `.manage` | View / edit the equipment catalog. |
| `command-net.admin.documents.view` / `.manage` | View / edit the document templates. |
| `command-net.admin.forms.view` / `.manage` | View forms and submissions / edit forms and review submissions. |
| `command-net.forms.submit` | See and fill in open forms at `/forms`. |
| `command-net.admin.courses.view` / `.manage` | View / edit courses and classes, and record class results. |
| `command-net.courses.enroll` | See courses and enrol in classes at `/courses`. |
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
| `command-net.admin.squadxml.manage` | Edit Squad XML settings. |

`admin.attendance` is also
declared in `CommandNetPlugin::getPermissions()`, reserved for features that don't exist yet
(see below) — granting it today has no effect.

## Rosters

By default `/roster` is one list of every active soldier. Under Admin → Command Net → Rosters
(`command-net.admin.rosters.view` / `.manage`) staff can define named **rosters**, each made of
some units and arranged in the order they should appear, such as Combat units and Support. Once at
least one exists the roster page shows a tab per roster (the first is selected, or pick one with
`?roster=`), listing the roster's units in their order with the soldiers whose current primary
assignment is that unit, senior first. A soldier in none of a roster's units is not on it, and
child units are not folded into their parent. Delete every roster to go back to the single list.

## Courses

Staff define **courses** under Admin → Command Net → Courses (`command-net.admin.courses.view` /
`.manage`): a description, an optional minimum rank, prerequisite courses, and the qualifications a
pass grants. They schedule **classes** of a course under Course Classes: start and end, an optional
number of places and an instructor.

Members with `command-net.courses.enroll` see `/courses`, open a class and enrol or withdraw until
it starts. Enrolling checks that they are enlisted, the class has a free place, they meet the minimum
rank and have passed every prerequisite. Once a class has started, someone with
`command-net.admin.courses.manage` records a result for every student on the class page (passed,
failed, no-show or excused) in one go. That is final: each pass writes a course record and grants the
course qualifications the student does not already hold, and everyone is notified. To correct a
mistake, remove the entries it wrote from the personnel file.

Not included yet (MILHQ has): several instructors per class with roles, a signup window, awards as a
course reward, a course image, calendar sync, and per-student service record text.

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

## AWOL detection

Turn it on under **Admin → Command Net → AWOL Settings** (`command-net.admin.awol.manage`), with a
number of consecutive missed operations and an optional forumify **AWOL role**. An active soldier who
misses that many in a row is flagged AWOL, and the flag clears when they next attend one. Only
operations where attendance was taken count, only those of the soldier's current unit (or with no
unit), and only ones since they last became Active, so leave and transfers do not count against them.
An AWOL set by an admin is never cleared automatically. Every change writes an AWOL service record
and notifies the soldier. Failing to report in flags AWOL too, and is cleared by reporting in.

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
  instead of leaving an orphan behind. Entries with no single source (a promotion, a
  discharge, a course pass) leave both null.
- **Attendance is separate from RSVP.** A soldier's RSVP status is their stated intent;
  `attended` is a separate field an operations manager sets afterward, so a no-show who
  RSVP'd "attending" doesn't get a combat record, and someone who shows up unannounced can
  still get credit.

## Known gaps

The earliest features have run against a live install. Everything added since the audit (rank
groups, enlistment, discharge, specialties, equipment, documents, forms, courses, rosters, and the
fixes) has been through CI and the application tests below, but not a live install with real data;
`docs/merge-and-test-plan.md` says what has been tested and what to check on a staging copy before
relying on it.

- **Tests are thinner than the plugin.** CI runs PHPUnit, phpcs and PHPStan. The unit tests mock
  the repositories. Two application tests boot the plugin in a real Forumify install on MySQL
  (`tests/Application`): one loads every page as an administrator, the other drives the main
  submit-and-review flows (enlistment, promotion, RSVP and attendance, transfers, discharge,
  forms, courses) and checks the database. Permissions for ordinary members, notifications, the
  scheduled Report In task and the Discord integration are not covered.
- **Not in this plugin yet, although MILHQ has it:** configurable statuses, a point-and-click form
  builder, several instructors per course class, calendar sync for classes, and the Discord
  `/award`, `/qualification` and `/rank` commands. The section for each feature above lists what its
  first version leaves out. MILHQ's PERSCOM migration tool is deliberately left out; this install
  does not migrate from PERSCOM.
- **Discord replies** do not show a soldier's specialty or loadout, and `Unit`'s Discord server id
  is stored but unused.
- **`src/Discord` isn't analysed by PHPStan** in CI, because it depends on the private
  `MajesticDev\Discord` plugin.

## Squad XML

Arma reads a unit's squad page from three files at the site root. Turn them on under **Admin →
Command Net → Squad XML** (`command-net.admin.squadxml.manage`): a squad tag, name, title, web address
and email (each falls back to the community title, the site address or `N/A`), and an optional `.paa`
logo. `/squad.xml` then lists every enlisted soldier who has a Steam ID: the Steam ID as the member
id, their callsign (or display name) as the nick, their display name, and their unit as the remark,
senior first. `/squad.dtd` and `/logo.paa` are served alongside it. Names with symbols such as `&` are
escaped. The files are public and off until enabled, since they publish members' Steam IDs; the XML
is cached for 15 minutes, and saving the settings clears the cache.

## Integrations

- **Calendar plugin:** when installed, an operation can be linked to a calendar and is mirrored as a
  calendar event (removed if the operation is cancelled).
- **Discord plugin:** the forumify roles this plugin grants (unit, rank, specialty, AWOL) can be mapped
  to Discord roles in that plugin's own settings, which keeps Discord in step without any Discord code
  here. It also adds three slash commands: `/command-net-soldier`, `/command-net-unit` and
  `/command-net-promotion`. `Unit` has a Discord server id field, but nothing reads it yet.

## Works well with

- [`forumify-id-card-plugin`](https://github.com/MajesticDevBox/forumify-id-card-plugin) —
  if installed, a soldier's personnel file gets a button to view or create their MILSIM ID
  card, and the ID card plugin can use this plugin as a personnel source without ever
  naming it anywhere public.
- [`command-net-theme`](https://github.com/Spearhead-Gaming/command-net-theme) — the
  frontend theme this plugin is designed to be used with; its homepage reads this plugin's
  `Operation` repository and online-count Twig function directly.
