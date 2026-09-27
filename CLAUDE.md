# commandnet-plugin

A [Forumify](https://forumify.net) plugin (`majesticdev/commandnet-plugin`) implementing
personnel and unit management for **Spearhead Gaming**, a MILSIM Arma 3 community — ranks,
units, positions, specialties, equipment, assignments, awards, qualifications, operations
(RSVP/attendance/AARs), enlistment, discharge, forms, courses, documents, promotions, Report
In, and AWOL detection. See [README.md](README.md) for the full entity and route tables.

Built for this one community's Forumify install, not a general-purpose skeleton — don't add
abstractions for configurability nobody asked for.

## The ecosystem

This is the foundation plugin. Siblings, all under `G:\Github Repos`:

- **commandnet-s3-plugin** — Operations-staff tooling (briefings, SOPs, missions, Zeus assets)
  that hangs off this plugin's `Operation` and `Unit`/`Equipment`/`Position` entities. Requires
  this plugin.
- **commandnet-discord-plugin** — bridges this plugin's Roles/Users to Discord across multiple
  guilds (one per unit) over HTTP. Optional — this plugin's `loadExtension` only imports its
  Discord command config when that plugin is present (`class_exists` guard), and the same
  pattern applies to the Calendar plugin.
- **commandnet-discord-bot** — the Node.js/discord.js process the Discord plugin talks to.
- **command-net-theme** — the Forumify theme; reads this plugin's `Operation` repository for
  the homepage operation-status panel via `command_net_online_count()` and similar helpers.
- **forumify-id-card-plugin** — issues fictional MILSIM ID cards; can pull organization data
  from this plugin's soldier/unit records via `CommandNetCardProvider`.

## Community model (important context)

- **Operations**: Wed/Sat 2000 EST, staff-run, no AAR required today.
- **Patrols**: member-led events between weekend ops, AAR required — not yet modeled as a
  distinct `OperationType`; a spec for adding this exists but isn't built (check for an open
  `feat/patrol*` branch/worktree before assuming it doesn't exist).
- Ranks are used by this community (unlike some others using this codebase) — a settings
  toggle to hide the rank system may be added; check for a `feat/rank-toggle` branch/worktree
  before assuming ranks are hard-required everywhere.
- `Rank` is already nullable on `SoldierProfile` — a soldier without a rank is already valid
  data, independent of any UI toggle.

## Local dev

Composer **path repository** development, not a packaged install. The actual Forumify app
lives at `~/dev/forumify` in WSL (Ubuntu), whose `composer.json` has a `path` repository
entry pointing at this directory's absolute WSL path (`/mnt/g/Github Repos/commandnet-plugin`)
— i.e. this Windows checkout *is* the source Composer symlinks into `vendor/majesticdev/`.
Editing files here is editing the live plugin.

Two ways to preview the app:
1. **Native**: `.claude/launch.json` in `commandnet-s3-plugin` used to spawn
   `wsl.exe ... php -S` directly — check current state, this may have moved to option 2.
2. **Docker dev stack**: `~/dev/forumify-dev-docker/docker-compose.yml` (WSL-native path, not
   under this repo) — MySQL + PHP containers bind-mounting `~/dev/forumify` and
   `/mnt/g/Github Repos` at identical paths so the path repos resolve unchanged. Runs with
   `APP_DEBUG=0` for speed (see Gotchas) — after editing PHP here, the *running app* needs
   `docker exec <forumify-container> php bin/console cache:clear` to see it; Twig template
   edits show up live without that.

After a fresh `composer require`/path-repo change from the app root:
```bash
bin/console forumify:plugins:refresh
bin/console doctrine:migrations:migrate
```

## Commands

```bash
make quality       # phpcs (strict) + phpstan
make quality-fix    # phpcbf autofix
```
Tests live under `tests/` with their own `composer.json`/`phpunit.xml.dist` (a self-contained
Forumify test kernel, not run against the dev app). `forumify:plugins:test-setup` (a Forumify
core console command) is the fast way to rebuild a plugin's test DB and activate it under test.

## Gotchas learned the hard way

- **Symfony dev-mode cache freshness checking is very slow over the Docker dev stack.**
  `composer.json`'s path repos mean this plugin's source is reached through a Windows-drive
  bind mount from inside WSL2, where `stat()` is far slower than native filesystem. With
  `APP_DEBUG=1` (the `dev` env default), Symfony re-validates its container/route cache by
  stat-ing every tracked resource file on *every request* — this alone was a 10x+ slowdown
  (4-5s vs 0.3s per page). The dev Docker stack runs with `APP_DEBUG=0` to avoid it; remember
  to `cache:clear` after PHP/config changes there.
- **Doctrine `Forum`/`ACL` (Forumify core, not this plugin) default-deny**: a new `Forum`
  entity with no `ACL` rows is invisible to everyone, including logged-in users — the voter
  returns `false` when no ACL row exists for a role. Relevant if this plugin ever creates
  Forumify forums/content programmatically.
- **Gedmo slug fields have no setter** (`Forum::slug`, `Topic::slug` in Forumify core) — the
  slug is derived from `title` on persist; you can't set it directly, only shape the title so
  it slugifies to what you want.
- Git-Bash-launched `wsl.exe "..."` commands: **double-quoted strings expand `$VAR` in the
  outer Git Bash shell before WSL ever sees them** — use single quotes for the inner command
  when you need WSL's own `$HOME` etc., not the Windows host's.
