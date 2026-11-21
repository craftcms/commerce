# Craft Commerce agent guidance

## Translations

Whenever translatable strings in the `commerce` category are added, modified, or removed, always update the English source translations in `src/translations/en/commerce.php` accordingly.

Commerce may freely use existing `app`-category translations from Craft CMS's `src/translations/en/app.php`, available here at `vendor/craftcms/cms/src/translations/en/app.php`. Do not modify `app`-category translations or their catalog.

## Commit messages

For commits touching only genuinely inconsequential, nonfunctional files, put `[ci skip]` at the end of the extended commit message, in the commit body rather than the subject. Judge eligibility by the actual effect of the full commit, not filenames alone. Code, dependencies, executable configuration, workflows, and behavior-changing generated artifacts are not eligible.

Skip markers can leave required checks pending and do not establish green CI. Never use them to evade required checks, required merged-result verification, or merge queues. If a skip marker would prevent satisfying those requirements, stop and ask how to proceed instead of skipping CI.

## Workspace skills

The Craft workspace skills below cover processes shared with Craft CMS and the other plugin repos. Follow them, along with the details for this repo.

### Releasing

Follow the `releasing` skill.

- Release from the branch the changes landed on: `5.x`, or `4.x` for 4.x releases.
- Commerce doesn't store its version in code. The Finish commit only changes `CHANGELOG.md`, and Composer and Craftnet take the version from the tag.
- New minor versions are developed on a next-minor branch, such as `5.8`, with its own `CHANGELOG-WIP.md`. `6.x` is the next major version.
- After a release, merge `5.x` into the next-minor branch and into `6.x`.
- Unreleased security fixes are on `5.x-internal` in craftcms/commerce-internal.

### Changelog entries

Follow the `changelog-entries` skill. Link Commerce issues and PRs in `craftcms/commerce`. Security fixes link to the craftcms/cms security policy.

### Merging pull requests

When explicitly instructed to merge a PR, follow the `merging-pull-requests` skill, with one stricter rule for this repo: a push of the final merge commit may use the credential's bypass permission only for the PR-only rule. Never bypass required status checks, even ones that inherited CI covers.

### Copilot reviews

When waiting for, watching for, or checking a Copilot review, follow the `watching-copilot-reviews` skill.
