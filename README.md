# forumify plugin skeleton

Generates a bare [forumify](https://forumify.net) plugin: registered with the platform,
with quality tooling, a test suite wired up to forumify's test kit, GitHub Actions
workflows and a README explaining where things go.

No example controllers, entities or templates — nothing to delete before you start.

## Usage

```bash
composer create-project majesticdev/commandnet-plugin my-plugin
```

The generator asks for your package name, namespace, license and whether you want tests
and CI, then rewrites the skeleton into your plugin and removes itself.

Add `--no-interaction` to take every default, deriving the package name from the target
directory. Useful in scripts:

```bash
composer create-project majesticdev/commandnet-plugin my-plugin --no-interaction
```

### Scripting the answers

Every question can be answered up front with an environment variable, which is how this
repository's own CI checks the generator:

```bash
SKELETON_PACKAGE=acme/todo-plugin \
SKELETON_NAME=Todo \
SKELETON_FEATURES=tests,ci \
composer create-project majesticdev/commandnet-plugin my-plugin --no-interaction
```

| Variable | Answers |
| --- | --- |
| `SKELETON_PACKAGE` | package name (`vendor/name`) |
| `SKELETON_NAMESPACE` | PHP namespace |
| `SKELETON_PLUGIN_CLASS` | plugin class name |
| `SKELETON_NAME` | display name |
| `SKELETON_DESCRIPTION` | description |
| `SKELETON_AUTHOR` | author |
| `SKELETON_HOMEPAGE` | homepage |
| `SKELETON_LICENSE` | license |
| `SKELETON_PLATFORM` | minimum forumify version |
| `SKELETON_FEATURES` | comma separated list of the features to include, everything else is left out |

Valid features: `tests`, `ci`.

## Requirements

- PHP 8.4 or newer
- Composer 2
- A MySQL 8.4 database, if you generate a test suite

## What you get

Always:

- `composer.json` with the plugin wiring (`type`, `extra.forumify-plugin-class`, autoload)
- the plugin class, plus service and route configuration
- `phpcs.xml` and `phpstan.neon` (level 8), runnable with `make quality`
- a `Makefile`, `.editorconfig`, `.gitignore` and a `migrations/` directory
- a README explaining where controllers, entities, templates and translations go

Optionally:

| Question | What it generates |
| --- | --- |
| Test suite | phpunit wired to `Forumify\Testing`, plus a test proving the plugin boots |
| GitHub Actions | Workflows running the tests and the quality checks |

## How this repository works

The skeleton is a working forumify plugin in its own right. Its test suite runs in CI
exactly as a generated plugin's would, which is what stops the templates from drifting
away from the platform.

Generation is plain string replacement of the skeleton's own identity:

| Placeholder | Becomes |
| --- | --- |
| `majesticdev/commandnet-plugin` | your package name |
| `MajesticDev\CommandNet` | your namespace |
| `CommandNetPlugin` | your plugin class |
| `Command Net` | your plugin's display name |
| `command-net` / `command_net` | slugged and snake_cased forms of the display name |

Optional parts are wrapped in markers that are stripped when you decline them, and kept
(without the markers) when you accept:

```makefile
# skeleton:if tests
tests:
	make setup-tests
	make run-tests
# skeleton:endif
```

`# skeleton:if !tests` inverts the condition. The comment character is ignored, so the
same markers work in PHP, YAML, Twig, Makefiles and Markdown.

The generator lives in [`skeleton/Installer.php`](skeleton/Installer.php) and runs from
composer's `post-create-project-cmd`. It deletes itself once it is done.

## Contributing

Test a change by generating a plugin from your working copy:

```bash
composer create-project majesticdev/commandnet-plugin /tmp/test-plugin \
  --repository='{"type":"path","url":"/path/to/command-net","options":{"symlink":false}}' \
  --stability=dev
cd /tmp/test-plugin && make quality && make tests
```
