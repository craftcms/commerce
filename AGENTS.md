# AGENTS.md

Guidance for AI coding agents working in this repository.

# Craft Commerce

Craft Commerce 6 is a plugin for Craft CMS 6, which runs on Laravel. This repository is a package that boots through Orchestra Testbench and its workbench, not a conventional Laravel application.

Craft CMS is installed as `craftcms/cms` in `vendor/`. Reuse its classes and helpers rather than writing Commerce equivalents, and follow its conventions where this file doesn't cover something. The cms repository has its own `AGENTS.md`.

New work is Laravel-first. The Yii2 code exists only so plugins and templates that still call Yii-era Commerce APIs keep working.

## Layout

- `src/` (`CraftCms\Commerce\`): the Laravel codebase, grouped by domain (`Order`, `Product`, `Payment`, `Shipping`, `Tax`, …).
- `src-yii2/` (`craft\commerce\`): the Yii2 compatibility layer. Most classes are deprecated `class_alias()` stubs that point to their `src/` replacements. Don't add new features here.
- `resources/js/`: the Inertia and Vue control panel pages and modules, built with Vite.
- `resources/views/`: Twig views.
- `legacy/`: the webpack build for the remaining Yii2 asset bundles in `src-yii2/web/assets/`.
- `database/migrations/`: migrations.
- `example-templates/`: the front-end example templates.
- `workbench/`: the Testbench workbench app used for local development and tests.
- `tests/`: Pest tests in the `Unit`, `Feature` and `Arch` suites.

## Commands

- PHP:
  - `composer tests` runs every Pest suite. Use `./vendor/bin/pest <path> --filter=<filter>` for focused runs.
  - `composer fix-cs` fixes code style, and `composer check-cs` checks it.
  - `composer phpstan` runs static analysis.
  - `vendor/bin/rector process --dry-run` checks the Rector rules.
- Tests use in-memory SQLite by default. To run them against MySQL or Postgres, copy `tests/.env.example.mysql` or `tests/.env.example.pgsql` to `tests/.env`.
- JavaScript: run tooling through pnpm.
  - `pnpm build` builds the Vite assets, and `pnpm build:legacy` builds the legacy webpack bundles.
  - `pnpm typecheck` runs `vue-tsc`.
  - `pnpm fix-prettier` formats files, and `pnpm check-prettier` checks them.
- Pass `--no-interaction` to Artisan and Testbench commands.
- To check that a change works without writing a test file, use `vendor/bin/pest --agent='<code>'` and load the `pest-plugin-agent` skill first. Browser checks (`visit()`) need `pestphp/pest-plugin-browser`, which isn't installed; ask before installing it, since it pulls in Playwright and browser binaries.

## PHP

- Add `declare(strict_types=1)` to PHP files.
- Classes are non-final by default. Use `readonly` when it fits.
- Never call `json_encode()` or `json_decode()` directly; use `CraftCms\Cms\Support\Json`.
- Use `CraftCms\Cms\Support\Arr`, `Str`, `File` and `Env` instead of `Illuminate\Support\Arr`, `Illuminate\Support\Str`, `Illuminate\Support\Facades\File` and `Illuminate\Support\Env`. Each Craft helper extends its Laravel equivalent.

## Translations

- Translate control panel strings with `t()` and the `commerce` category, e.g. `t('Customer', category: 'commerce')`.
- Don't build translated text by concatenation. Use one key with named placeholders, e.g. `t('Recent Orders – {orderStatus}', ['orderStatus' => $status], category: 'commerce')`.
- Add new keys only to `lang/en/commerce.php`, keeping it sorted. translation-sync generates the other languages, so don't edit them by hand.

## Changelog

Use the Craft workspace `changelog-entries` skill for `CHANGELOG.md` and `CHANGELOG-WIP.md`. `CHANGELOG-WIP.md` holds the Commerce 6.0 release notes for people upgrading from Commerce 5.

## Pull requests

- Run the CI checks before creating a pull request: `composer check-cs`, `vendor/bin/rector process --dry-run`, `composer phpstan`, and `composer tests`.
- Prefix the title with the target version branch, such as `[6.x]`.
- Use the `pr` skill to write the pull request body.

## CI skip markers

Decide this for every commit before you make it, including commits in a
worktree or on another branch. Once a commit is pushed, the marker can't be
added without rewriting history.

For commits touching only genuinely inconsequential, nonfunctional files, put
`[ci skip]` on its own line at the end of the extended commit message (the
commit body), before trailers such as `Co-Authored-By`. Judge eligibility by
the actual effect, not filenames alone. Code, dependencies, executable
configuration, workflows, and behavior-changing generated artifacts are not
eligible.

Commits that are usually eligible:

- Release notes only: `CHANGELOG.md` and `CHANGELOG-WIP.md`.
- Documentation and code comments only.
- Agent instructions and skills only: `AGENTS.md`, `.agents/` and `.claude/`,
  including scripts that only agents run.

Only add it when no other unpushed commits on the branch have significant
changes. GitHub skips CI for a whole push when its head commit carries the
marker, so it would also skip the commits beneath it.

Skip markers can leave required checks pending and do not establish green CI.
Never use them to evade required checks, merged-result verification, or merge
queues. The `merging-pull-requests` skill's safeguards still apply.

## Skills

Skills live in `.agents/skills/`; `.claude/skills` links to the same folder. Load the matching skill before starting the task it covers:

- `pr`: pull request bodies. It loads `show-me` and `feature-screenshots`.
- `test-audit`: writing, changing or reviewing tests.
- `pest-plugin-agent`: one-off checks that a change works.
- `resolve-merge-conflicts`: merge conflicts, including merging 5.x into 6.x.
- `laravel-best-practices`: Laravel PHP code.
- `inertia-vue-development` and `vue-best-practices`: control panel pages and Vue components.
- `merging-pull-requests`: merging a PR when explicitly asked to.
- `watching-copilot-reviews`: checking for or waiting on a Copilot review.
