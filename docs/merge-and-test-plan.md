# Merge and test plan for the audit stack (#4 to #18)

Fifteen pull requests are stacked: each targets the branch of the one below it, and #4 targets
`master`. All were CI-green when this was written (unit tests, phpcs, PHPStan), but none has run on
a real install. This is the order to merge them in and what to check between merges.

Tick the boxes as you go. The 12 migrations are numbered in stack order, so merging bottom-up runs
them in a safe order.

## Ground rules

- [ ] Use a **staging install with a copy of production data**, on the same PHP and Forumify versions.
- [ ] **Back up the database** before every tier, and note the current commit.
- [ ] Merge **bottom-up, one tier at a time**, and test between tiers.
- [ ] Before merging each PR: retarget its base to `master`, mark it ready, confirm CI is green.
- [ ] Use **merge commits, not squash**. Squashing a parent rewrites the commits its children
      contain and makes the next PR conflict.
- [ ] After each tier run `bin/console doctrine:migrations:migrate`, then `bin/console cache:clear`.
- [ ] Ignore the **Kilo Code Review** check. It failed on a rate limit, not on the code.
- [ ] If a tier fails: restore the backup, revert that merge commit, and fix on the PR. Every
      migration has a `down()`, but the new tables lose their data if it is used.

## Tier 1: #4 and #5 (fixes, Report In, promotion action, rank roles, CI)

Migrations `20260920140000`, `150000`, `160000`, `170000`.

- [ ] Read the `20260920140000` backfill SQL against real data first (it retypes old AWOL records and
      flags soldiers who are currently AWOL from detection).
- [ ] Migrations run without errors and `doctrine:schema:validate` reports no drift.
- [ ] Attendance: mark a soldier who never RSVP'd as absent and another as attended.
- [ ] File two after-action reports for one operation: each attendee has **one** combat record.
- [ ] Set a soldier to AWOL by hand, then mark them attended: they **stay** AWOL.
- [ ] Return a soldier from LOA to Active: absences from before do not count toward AWOL.
- [ ] AWOL flag and clear entries show as type "AWOL" on the personnel file.
- [ ] Turn on Report In under Report In Settings and run `bin/console command-net:report-in:run-checks`:
      baseline entries are written and **nobody is flagged** on the first run.
- [ ] The scheduler is running in this environment (Report In is a scheduled task).
- [ ] Promote an eligible soldier from `/promotions`: rank changes, a promotion record is written,
      the rank role is granted, the soldier is notified.
- [ ] The admin **Rank** form opens and saves (a parse error in it was caught by CI).
- [ ] CI runs on a pull request and passes.

## Tier 2: #6 to #9 (rank groups, query fixes, README)

Migration `20260920180000`.

- [ ] With no rank groups, the promotion ladder behaves exactly as before.
- [ ] Create groups (for example Enlisted and Officer) and assign ranks: the top rank of a group has
      no next rank on `/promotions`.
- [ ] Load `/promotions`, `/roster` and `/attendance` with query logging on. The query count stays flat
      as the roster grows.

## Tier 3: #10 to #12 (enlistment, discharge, assignment role sync)

Migration `20260920190000`.

- [ ] Enlistment: turn it on, set a starting rank and unit, apply as a test user, accept. The profile,
      rank, unit posting, records and notification are all there.
- [ ] Decline a second application: the applicant is notified and no profile is created.
- [ ] Discharge a soldier who has a unit role and a rank role: they leave the roster, both roles are
      removed, a discharge record is written, and they can no longer RSVP or report in.
- [ ] Re-enlist that soldier: their history is kept and they are active again.
- [ ] **Assignment role sync:** transfer a soldier between two units that each have a role. The role
      moves. (This fix was found by reading code and has not been observed failing.)
- [ ] Delete an assignment: the unit role follows.

## Tier 4: #13 to #15 (specialties, equipment, documents)

Migrations `20260920200000`, `210000`, `220000`.

- [ ] Specialties: set one that carries a role, change it, then discharge: the role follows each time.
- [ ] The specialty shows on the personnel file and on the roster row.
- [ ] Equipment: create weapons and a vehicle, attach them to a position and a unit, assign a soldier:
      the Loadout card shows them.
- [ ] Documents: create one using several placeholders, issue an award with it, check the rendering on
      the personnel file. A name containing HTML is shown escaped.

## Tier 5: #16 to #18 (forms, courses, rosters)

Migrations `20260920230000`, `240000`, `250000`.

- [ ] Forms: build one with a field of each type, submit it, review it, check the notification.
- [ ] Edit the form afterwards: the earlier submission still shows its original questions.
- [ ] Courses: create two courses (one a prerequisite of the other, with a qualification attached) and
      schedule a class.
- [ ] Enrolment is refused for a rank below the minimum and for a missing prerequisite.
- [ ] After the start time, record results for every student: passes get a course record and the
      qualification, everyone is notified, and results cannot be recorded twice.
- [ ] Rosters: with none defined `/roster` looks exactly as before.
- [ ] Create two rosters over different units and reorder them: tabs, unit order and counts are right.

## After the last merge

- [ ] Grant the new permissions to the right roles:
  - `command-net.admin.personnel.discharge`
  - `command-net.admin.enlistment`, `.specialties`, `.equipment`, `.documents`, `.forms`,
    `.courses` and `.rosters`, each with `.view` and `.manage`
  - `command-net.forms.submit`
  - `command-net.courses.enroll`
- [ ] Decide whether to open Enlistment and turn on Report In enforcement (both are off until enabled).
- [ ] Existing soldiers do not get rank or specialty roles until their rank or specialty next changes.
      Decide whether to change each once to trigger it.
- [ ] Make the CI jobs required checks in branch protection.
- [ ] Delete the merged branches.

## Open questions

- #4 is large: it bundles the attendance, AWOL, Report In and promotion work, the CI workflow and the
  Discord commit `88492f1`. Merge it whole after a careful read, or split it first?
- Test every tier separately, or merge #4 to #9 and test, then #10 to #18 in a second pass?
