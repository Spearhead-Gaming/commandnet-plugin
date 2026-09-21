# Rollout and test plan for the audit work

Everything in the audit stack (#4 to #22) is merged into `master`, along with a follow-up fix (#23)
and the application tests (#24). This file now tracks what has been tested and what still needs a
run on a real install before it is relied on in production.

Each item says how far it has been checked:

- **App test**: exercised end to end by `tests/Application` (real controllers, forms, CSRF buttons
  and a MySQL database), which runs in CI.
- **Migration test**: the migration's real SQL is run against rows shaped like the data the old code
  wrote, in a scratch MySQL database (`tests/Migration`), which runs in CI.
- **Unit test**: the logic is covered by a unit test with mocked repositories, but nothing drives it
  through the real application.
- **Not covered**: nobody has checked it yet.

The boxes stay unticked until someone has checked the item on staging with a copy of real data.

## What has been verified

On a throwaway install (fresh MySQL 8.4, Forumify 1.3.2), and now in CI on every pull request:

- All 13 migrations, `20260920140000` to `20260920260000`, run cleanly and the plugin activates.
- The AWOL backfill (`20260920140000`) and its correction (`20260920260000`) were run against
  soldiers and records shaped like the old code wrote them: flagged and still AWOL, set AWOL by an
  admin, flagged then returned then set AWOL by an admin, flagged twice, and unrelated records that
  must not be retyped. The first version of the backfill marked the third kind as auto-flagged, so
  they would have been returned to Active the next time they attended; the correction fixes that.
- The container and Twig lint pass, and all 106 `command_net` routes register.
- About 80 pages load as an administrator without an error: every frontend page, every admin list,
  create and edit screen, the discharge page, the settings pages and `/squad.xml`.
- Enlistment, promotion, RSVP, attendance, AWOL detection, report in (button, command and
  enforcement), transfers, specialty changes, discharge and re-enlist, forms and courses were driven
  through the real forms and buttons, and the database was checked afterwards.
- The 118 unit tests, phpcs and PHPStan pass.

A query-count test loads each page that lists soldiers (roster and roster tabs, units, promotions,
attendance, an operation, qualifications, the admin Personnel list) with 3 and then 15 soldiers and
fails if the number of queries grows.

A separate permissions test checks every frontend and admin endpoint as a member with no
permissions, as one holding everything except the required permission, and as one holding only it.
The admin panel is behind Forumify's administrator role, so staff need that role plus the specific
command-net permission; the CRUD edit screens refuse by redirecting to the list, not with a 403.

The flow test also checks that each action creates the notification it should, for the right person:
enlistment accepted and declined, promotion, form review, course results, the AWOL flag and return
to Active, and the Report In warning. It checks that the notification exists with the expected
title and that the platform's notification type shows a title, text and link on the site from it,
not that it was delivered.

A page content test checks what pages say and do: the specialty, Loadout card and record types on a
personnel file, a member's name containing HTML inside a rendered document, the roster with and
without rosters (tabs, which soldiers each lists, their order), the squad file validated against the
DTD the plugin serves, the Rank form saving, what a discharged soldier is offered and refused, and
that the Report In command is in the scheduler.

What that run could not cover: your real production data (the backfill is tested only on rows shaped
like the old data), the scheduler
worker actually running, delivery of notifications, the Discord plugin, and a real Arma client
reading `/squad.xml`.

A member views test loads the personnel file, roster, promotions, an operation, a course class, the
attendance page and the admin personnel record as members holding the permission to open the page
plus exactly one more, and checks that member sees the control that permission unlocks (buttons,
forms, report details) and none of the others.

## Found while testing

- The assignment form returned a 500 on a blank start date and did not prefill it. Fixed in #23.
- The admin Personnel list ran a query per soldier for the user, rank and assignments: 17 queries with
  3 soldiers, 40 with 15. Fixed in #27. The frontend pages were already flat.
- The RSVP buttons on an operation page were shown to anyone with a personnel file, although the RSVP
  action needs `command-net.operations.rsvp`, so a member without it saw buttons that refused. They
  now need the permission too. Fixed in #30.
- A discharged soldier was still offered the RSVP buttons and the Report In button (they have a
  profile and the permission), and was refused when they used them. Both now need the soldier to be
  enlisted. Fixed in #31.
- `doctrine:schema:validate` reports drift for calendar-plugin tables and for Forumify's own
  `UserNotificationSettings` mapping. Neither comes from this plugin.

## Before rolling out

- [ ] Follow `docs/staging-rollout.md`: the commands, the before and after AWOL checks, and how to
      roll back.
- [ ] Use a **staging install with a copy of production data**, on the same PHP and Forumify versions.
- [ ] **Back up the database** first, and note the current commit.
- [ ] Run `bin/console doctrine:migrations:migrate`, then `bin/console cache:clear`.
- [ ] If it goes wrong: restore the backup and revert to the previous commit. Every migration has a
      `down()`, but the new tables lose their data if it is used.

## Fixes, Report In, promotions and rank roles (migrations `20260920140000` to `170000`)

- [ ] Check the `20260920140000` backfill and its correction `20260920260000` against real data first:
      they retype old AWOL records and mark soldiers who are AWOL from detection. **Migration test**
      on rows shaped like the old data. Your real rows can still differ, so before rolling out run
      `SELECT type, title, COUNT(*) FROM service_record WHERE title IN ('Flagged AWOL', 'Returned to
      Active') GROUP BY type, title` and `SELECT COUNT(*) FROM soldier_profile WHERE status = 'awol'`,
      and compare with what the migration reports afterwards. **Not covered** on real data.
- [ ] Migrations run without errors. **App test** (CI runs them on every pull request).
- [ ] `doctrine:schema:validate` reports no drift from this plugin. Checked once on the throwaway
      install, not in CI.
- [ ] Mark a soldier who never RSVP'd as absent and another as attended. **App test**
- [ ] File two after-action reports for one operation: each attendee has **one** combat record.
      **Unit test**
- [ ] Set a soldier to AWOL by hand, then mark them attended: they **stay** AWOL. **Unit test**
- [ ] Return a soldier from LOA to Active: absences from before do not count toward AWOL. **Unit test**
- [ ] AWOL flag and clear entries show as type "AWOL" on the personnel file. Missing an operation
      flags the soldier, writes the record, grants the AWOL role and sends the notification, and
      attending clears all of it, and the file lists an AWOL record with the type "AWOL": **App test**
- [ ] Turn on Report In under Report In Settings and run `bin/console command-net:report-in:run-checks`:
      baseline entries are written and **nobody is flagged** on the first run. **App test**: the
      command runs, 25 days later a soldier is warned, 40 days later flagged AWOL, and reporting in
      clears it, each with its notification. The Report In button is an **App test** too.
- [ ] The scheduler is running in this environment (Report In is a scheduled task). The task is
      registered in the scheduler: **App test**. The scheduler worker running on your server is
      **not covered**.
- [ ] Promote an eligible soldier from `/promotions`: rank changes, a promotion record is written and
      the rank role moves, the soldier gets a notification. **App test**
- [ ] The admin **Rank** form opens and saves. **App test**
- [x] CI runs on a pull request and passes.

## Rank groups and query fixes (migration `20260920180000`)

- [ ] With no rank groups, the promotion ladder behaves exactly as before. **Unit test**
- [ ] Create groups (for example Enlisted and Officer) and assign ranks: the top rank of a group has
      no next rank on `/promotions`. **Unit test**. Promoting from the lower rank of a two-rank group
      is an **App test**.
- [ ] Load `/promotions`, `/roster` and `/attendance` with query logging on. The query count stays flat
      as the roster grows. **App test**: the same count at 3 and at 15 soldiers, for these and for
      `/units`, `/operations/{id}`, `/qualifications` and the admin Personnel list. It found an N+1 in
      the Personnel list (fixed). Real data can still surprise it, so glance at a query log on staging.

## Enlistment, discharge and assignment role sync (migration `20260920190000`)

- [ ] Enlistment: turn it on, set a starting rank and unit, apply as a test user, accept. The profile,
      rank, unit posting, unit role, record and the applicant's notification are all there. **App test**
- [ ] Decline a second application: no profile is created and the applicant is notified. **App test**
- [ ] Discharge a soldier who has a unit role, a rank role and a specialty role: all roles are removed
      and a discharge record is written. **App test**
- [ ] A discharged soldier can no longer RSVP or report in: the buttons are not offered, posting anyway
      is refused with the enlisted message, and nothing is saved. **App test**
- [ ] Re-enlist that soldier: the same profile is restored, history is kept and they are active again.
      **App test**
- [ ] **Assignment role sync:** transfer a soldier between two units that each have a role. The role
      moves. **App test**
- [ ] Delete an assignment: the unit role follows. **App test**

## Specialties, equipment and documents (migrations `20260920200000` to `220000`)

- [ ] Specialties: set one that carries a role, change it, then discharge: the role follows each time.
      **App test** (set, clear and discharge)
- [ ] The specialty shows on the personnel file and on the roster row. **App test**
- [ ] Equipment: create weapons and a vehicle, attach them to a position and a unit, assign a soldier:
      the Loadout card shows them. **App test** (weapons from the position, vehicles from the unit, no
      card when there is nothing to show)
- [ ] Documents: create one using several placeholders, issue an award with it, check the rendering on
      the personnel file. **App test** (a record carrying a document renders on the file)
- [ ] A name containing HTML is shown escaped in a rendered document. **App test**

## Forms, courses and rosters (migrations `20260920230000` to `250000`)

- [ ] Forms: build one with a field of each type, submit it, review it, and the submitter is notified.
      **App test**
- [ ] Edit the form afterwards: the earlier submission still shows its original questions. **App test**
- [ ] Courses: create two courses (one a prerequisite of the other, with a qualification attached) and
      schedule a class. **App test** (seeded)
- [ ] Enrolment is refused for a rank below the minimum and for a missing prerequisite. **App test**
      (no enrol button is offered), **Unit test** for the exact reasons.
- [ ] After the start time, record results for every student: passes get a course record and the
      qualification, the student is notified, and results cannot be recorded twice. **App test**
- [ ] Rosters: with none defined `/roster` has no tabs and lists everyone, as before. **App test**
- [ ] Create two rosters over different units and reorder them: the tabs follow the order, each lists
      only its own units, and changing the order changes the tabs. **App test** (the order is changed
      by editing the position, not by the drag-and-drop in the admin table, which is **not covered**)

## After rollout

- [ ] Grant the new permissions to the right roles. Enforcement is an **App test**: every endpoint
      is refused to a member with none, refused to one holding every permission except the required
      one, and open to one holding only that permission. Which role should get which permission is
      still your call. What each of those members sees on the main pages is also an **App test**. The
      org chart, qualifications board, courses list and forms list have no permission-dependent
      buttons or details in their templates, so there is nothing further to check there beyond who may
      open them.
  - `command-net.admin.personnel.discharge`
  - `command-net.admin.enlistment`, `.specialties`, `.equipment`, `.documents`, `.forms`,
    `.courses` and `.rosters`, each with `.view` and `.manage`
  - `command-net.forms.submit`
  - `command-net.courses.enroll`
- [ ] Decide whether to open Enlistment and turn on Report In enforcement (both are off until enabled).
- [ ] Existing soldiers do not get rank or specialty roles until their rank or specialty next changes.
      Decide whether to change each once to trigger it.
- [ ] Make the CI jobs required checks in branch protection.
- [x] Delete the merged branches.
- [ ] Have someone with a real Arma client check that `/squad.xml` loads. The file is valid against the
      DTD the plugin serves: **App test**. A real client reading it is **not covered**.
