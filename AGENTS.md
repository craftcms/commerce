# Craft Commerce agent guidance

## Commit messages

For commits touching only genuinely inconsequential, nonfunctional files, put `[ci skip]` at the end of the extended commit message, in the commit body rather than the subject. Judge eligibility by the actual effect of the full commit, not filenames alone. Code, dependencies, executable configuration, workflows, and behavior-changing generated artifacts are not eligible.

Skip markers can leave required checks pending and do not establish green CI. Never use them to evade required checks, required merged-result verification, or merge queues. If a skip marker would prevent satisfying those requirements, stop and ask how to proceed instead of skipping CI.

## Merging pull requests

When explicitly instructed to merge a PR targeting `5.x` or `5.8`, resolve conflicts limited to inconsequential, nonfunctional files such as `CHANGELOG.md` in the final merge commit on the PR's actual target branch. Do not merge the target into the PR or rebase and push the PR just to resolve those conflicts and rerun PR CI.

- Fetch the latest target and PR heads. Confirm that the exact PR head being merged has passed all required CI and has the required approvals. Recheck these if the PR head changes.
- Use a clean worktree based on the latest remote target, then run `git merge --no-ff --no-commit <verified-pr-head-sha>`. Resolve only inconsequential, nonfunctional conflicts, preserving both sides' intended content. Ask for confirmation before resolving substantive, code, dependency, or generated-artifact conflicts.
- Inspect the full staged merge diff and validate the resolutions. For documentation-only resolutions, check content, formatting, conflict markers, and `git diff --cached --check`. These lightweight checks do not replace required tests or substantive merged-result verification. Run all required tests and checks for the merged result.
- Inspect applicable branch rules before pushing. When explicitly authorized to merge a PR whose conflicts are genuinely nonfunctional only, a normal push of the final target-based merge commit may use the existing credential's bypass permission solely for the PR-only rule, after verifying that the exact PR head has green required CI and satisfies required approvals. Never bypass failed or pending required checks, required fresh merged-result checks, merge queues, or any other protections. If compliance is unclear or another protection prevents this path, stop and ask how to proceed.
- Commit the inspected merge and use an ordinary, non-force push to the actual target branch. If the target advances, fetch again and recreate the merge against the latest target, repeating inspection and required validation. Never force-push or update the PR branch just to land these resolutions.
