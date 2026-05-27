#!/usr/bin/env bash
# PreToolUse hook for Bash: block destructive commands.
# Stdin: JSON with { tool_name, tool_input: { command, description } }.
# Exit 2 = blocking (stderr → Claude). Exit 0 = allow.

set -u
input=$(cat)

cmd=$(printf '%s' "$input" | jq -r '.tool_input.command // ""' 2>/dev/null || printf '%s' "$input")

# rm -rf and friends (any order of -r/-f, capital R, --recursive --force)
if printf '%s' "$cmd" | grep -qE '(^|[[:space:];&|`])rm[[:space:]]+([^|&;]*(-[a-zA-Z]*r[a-zA-Z]*f|-[a-zA-Z]*f[a-zA-Z]*r|-r[[:space:]]+-f|-f[[:space:]]+-r|--recursive[[:space:]]+--force|--force[[:space:]]+--recursive|-R[a-zA-Z]*f|-f[a-zA-Z]*R))'; then
  echo "Blocked: 'rm -rf'-style command detected. Ask the user before running destructive removals." >&2
  exit 2
fi

# git push --force / -f (also push-with-lease isn't blocked — that's intentional, it's safer)
if printf '%s' "$cmd" | grep -qE 'git[[:space:]]+push([[:space:]]+[^|&;]*)?[[:space:]](-f|--force)([[:space:]]|$)'; then
  echo "Blocked: 'git push --force' detected. Confirm with the user; prefer --force-with-lease if a force push is truly needed." >&2
  exit 2
fi

exit 0
