#!/usr/bin/env bash
# PostToolUse hook for Edit|Write|MultiEdit: auto-run `make cs-fix` after PHP edits.
# Silent: never blocks (always exit 0), skips if php container is not up.

set -u
input=$(cat)

file=$(printf '%s' "$input" | jq -r '.tool_input.file_path // ""' 2>/dev/null || printf '%s' "")

case "$file" in
  */app/src/*.php|*/app/tests/*.php|*/app/config/*.php|*/app/migrations/*.php) ;;
  *) exit 0 ;;
esac

cd "${CLAUDE_PROJECT_DIR:-$(pwd)}" 2>/dev/null || exit 0

# Skip silently if docker compose isn't usable or php container is down.
if ! command -v docker >/dev/null 2>&1; then
  exit 0
fi
if ! docker compose ps -q php 2>/dev/null | grep -q .; then
  exit 0
fi

make cs-fix >/dev/null 2>&1 || true
exit 0
