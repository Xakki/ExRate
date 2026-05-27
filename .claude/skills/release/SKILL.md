---
name: release
description: Use when the user asks to cut a release of ExRate ("release X.Y", "релиз", "сделай тег", "prepare release"). Runs the full Definition-of-Done QA, updates `CHANGELOG.md` and `app/composer.json` version, creates a single release commit on the current branch, and tags it. Does NOT push without explicit confirmation. Pass the target version as the first argument; otherwise the skill asks.
when_to_use: Cutting a new release version of ExRate. Triggers — "release", "релиз", "tag X.Y", "publish version".
argument-hint: "[version, e.g. 0.4]"
allowed-tools: Read, Edit, Bash(make *), Bash(git *)
model: inherit
---

# Release ExRate

Follows the project history (see `git log --oneline`): tags `Release 0.1`, `Release 0.2`, `Release 0.3`. Versions are 2-component (`MAJOR.MINOR`) so far.

## Inputs

- **version** — `MAJOR.MINOR` (e.g. `0.4`). If omitted, ASK before any work — never guess.

## Pre-flight

1. `git status` — working tree must be clean.
2. `git branch --show-current` — releases happen on `main` (confirm with user if not).
3. Read the last `## ...` section of `CHANGELOG.md` and `app/composer.json:version` to confirm we're bumping forward, not sideways.

## Steps

1. **Definition of Done (mandatory, in order)**:
   - `make cs-fix`
   - `make cs-check`
   - `make phpstan`
   - `make test-unit`
   - `make test-functional`

   On any failure — STOP, surface the error, do not commit.

2. **CHANGELOG.md** — prepend a new top section:
   ```
   # Release version X.Y

   - <bullet 1>
   - <bullet 2>
   ```
   Bullets come from `git log --oneline <prev-tag>..HEAD` summarised semantically (group: Providers / API / Architecture / Caching / Tests / Misc). Match the tone of existing sections (see Release 0.3).

3. **app/composer.json** — bump `"version": "X.Y"`.

4. **Commit** — single commit on current branch with message `Release X.Y` (matches existing tag-commit convention). Stage only: `CHANGELOG.md`, `app/composer.json`. Use HEREDOC:
   ```
   git commit -m "$(cat <<'EOF'
   Release X.Y

   <one-line summary of release>
   EOF
   )"
   ```

5. **Tag** — `git tag -a "Release X.Y" -m "Release X.Y"` (annotated tag, matches existing naming).

6. **Session log** — append to `.ai/logs/YYYY-MM-DD.md`.

7. **Stop here.** Do NOT `git push` or `git push --tags` without the user explicitly saying "push" / "outgoing". Print the next-step hint:
   ```
   Done locally. To publish:
     git push origin main
     git push origin "Release X.Y"
   ```

## Rules

- Never bypass QA — if any of `cs-check`, `phpstan`, `test-unit`, `test-functional` fail, fix or report, never `--no-verify`.
- Never amend an existing release commit; if something is wrong, create a new commit (or back out via the user).
- Do not run `make test-integration` for a release unless the user asks.
- Do not bump beyond what the user asked for (no auto-major bumps).
