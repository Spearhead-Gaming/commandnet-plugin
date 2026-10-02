# Changelog

## Unreleased

## v1.1.5

**Upgrading:** run the database migrations (they add the `unit_position`, `squad` and `squad_position` tables and `assignment.squad_id`), and give the new `command-net.units.manage_own` permission to the roles your unit commanders hold. Forumify grants a new permission to no role.

### Added

- Squads: a squad or team within a unit, nesting so a team can sit inside a squad. Units and squads list the positions they are expected to hold, and an assignment can optionally name a squad (#51)
- Import a unit and squad structure from a pasted outline, with `@Team` and `@Squad` shortcuts for the standard positions. Admins use **Admin → Command Net → Import ORBAT** (with a downloadable template); commanders import under their own unit. It only builds structure and never creates assignments (#51)
- Unit self-service: a commander holding the new `command-net.units.manage_own` permission can manage their own units, child units and squads from the site at **My Units**, without admin-panel access (#51, #52)
- A new admin **Overview** page: a Needs attention panel (pending enlistment applications, AWOL soldiers, units with no commander), personnel and unit graphs, and active and AWOL tiles (#51)
- Attendance review for leadership: a filterable roster, a per-soldier history and attendance corrections for community leadership (`admin.attendance.*`) and unit commanders (their unit tree, never their own record), plus a read-only history for members' own attendance. The blanket units admin grant does not give attendance review (#58)
- Attach a document when promoting or demoting, from the promotions page or the admin personnel form, and print any record's document from a new print page (#57)
- `/command-net-soldier` in Discord now shows the soldier's specialty and loadout (#56)
- `/command-net-unit` in Discord now shows the unit's vehicles
- The admin Units list indents each unit under its parent (#51)

### Fixed

- Creating a squad now lands on the new squad instead of the parent unit; unit authorisation compares the commander by id rather than object identity; deleting a squad checks its own assignments (#52)

### Internal

- PHPStan now analyses `src/Discord` against stubs of the private Discord plugin (#54), and 37 new unit tests cover the Discord commands, the user resolver and the Report In task (#55)
- Application tests cover unit self-service, the ORBAT import and the Overview page (#53)
- A release workflow copies this changelog to the docs repository as a pull request (#59, #60)
- Corrected the README's known gaps and documented the new features

## v1.1.3

- Let a patrol be permanently deleted, and clean up the records it earned (#47)
- Let a patrol be linked to a deployment from the web form and the Discord command (#46)
- Add a setting to hide the rank system (#45)
- Hide promotion and demotion records from the Service Record tab when ranks are off (#48)
- Require map and intel images on a patrol's AAR, following the community template (#49)

## v1.1.2

- Grant the built-in user role's command-net permissions to every account (#44)

## 1.1.1

- Version bump only; no functional changes recorded since v1.1.0.

## v1.1.0

- (no release notes recorded for this tag)

## v1.0.0

- Initial release.
