# Craft Commerce agent guidance

## Translations

Whenever translatable strings in the `commerce` category are added, modified, or removed, always update the English source translations in `src/translations/en/commerce.php` accordingly.

Commerce may freely use existing `app`-category translations from Craft CMS's `src/translations/en/app.php`, available here at `vendor/craftcms/cms/src/translations/en/app.php`. Do not modify `app`-category translations or their catalog.

## Commit messages

For commits touching only genuinely inconsequential, nonfunctional files, put `[ci skip]` at the end of the extended commit message, in the commit body rather than the subject. Judge eligibility by the actual effect of the full commit, not filenames alone. Code, dependencies, executable configuration, workflows, and behavior-changing generated artifacts are not eligible.

Skip markers can leave required checks pending and do not establish green CI. Never use them to evade required checks, required merged-result verification, or merge queues. If a skip marker would prevent satisfying those requirements, stop and ask how to proceed instead of skipping CI.

## Merging pull requests

When explicitly instructed to merge a PR targeting `5.x` or `5.8`, resolve conflicts limited to inconsequential, nonfunctional files such as `CHANGELOG.md` or generated build artifacts in the final merge commit on the PR's actual target branch. Do not merge the target into the PR or rebase and push the PR just to resolve those conflicts and rerun PR CI.

- Fetch the latest target and PR heads. Confirm that the current PR head has passed all required CI, unless the inheritance exception below applies. Required approvals must remain satisfied for the current PR state. Recheck CI eligibility and approvals if the head changes.
- Earlier successful required CI may satisfy agent pre-merge verification only if the tested commit is an ancestor of the current PR head, every subsequent PR commit ends its body with `[ci skip]`, and inspection of every subsequent commit plus the complete cumulative diff from the tested commit to the current head confirms genuinely nonfunctional-only changes. Behavior-changing build artifacts are not eligible for this exception. Report both the tested SHA and current-head SHA; never claim the current head itself passed when relying on inherited CI. This exception cannot override GitHub head-check requirements, required fresh merged-result checks, merge queues, or other protections. Stop and ask how to proceed if compliance is unclear or prevented.
- Use a clean worktree based on the latest remote target, then run `git merge --no-ff --no-commit <verified-pr-head-sha>`. Resolve inconsequential, nonfunctional conflicts by preserving both sides' intended content. Resolve generated build artifact conflicts separately by running the appropriate documented build script on the merged sources; regenerate outputs, never hand-merge generated content. Ask for confirmation before resolving substantive source or dependency conflicts, or generated build artifact conflicts that cannot be resolved by this regeneration.
- Inspect the full staged merge diff and validate the resolutions. For documentation-only resolutions, check content, formatting, conflict markers, and `git diff --cached --check`. For regenerated build artifacts, inspect the output and run applicable verification and tests. Documentation-only checks do not replace required tests or substantive merged-result verification. Run all required tests and checks for the merged result.
- Inspect applicable branch rules before pushing. When explicitly authorized to merge a PR whose conflicts are limited to inconsequential, nonfunctional files or generated build artifacts resolved by the documented regeneration above, a normal push of the final target-based merge commit may use the existing credential's bypass permission solely for the PR-only rule, after satisfying the CI verification and current-state approval requirements above. Never bypass failed or pending required checks, required fresh merged-result checks, merge queues, or any other protections. If compliance is unclear or another protection prevents this path, stop and ask how to proceed.
- Commit the inspected merge and use an ordinary, non-force push to the actual target branch. If the target advances, fetch again and recreate the merge against the latest target, repeating inspection and required validation. Never force-push or update the PR branch just to land these resolutions.
