#!/usr/bin/env bash
# Runs INSIDE the spawned tmux window. Launches interactive claude with an
# autonomous prompt. Window stays open after claude exits.
#
# Universal: derives PROJECT_DIR / PROJECT_NAME / CLAUDE_PROJECT_PATH from its
# own location (script lives at <repo>/.claude/scripts/run-claude-task-inner.sh).
set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "$SCRIPT_DIR/../.." && pwd)"
PROJECT_NAME="$(basename "$PROJECT_DIR")"
# Claude Code stores session JSONL under ~/.claude/projects/<encoded-path>/,
# where <encoded-path> = absolute project dir with every '/' replaced by '-'.
CLAUDE_PROJECT_PATH="$(printf '%s' "$PROJECT_DIR" | tr '/' '-')"

TASK_FILE="$1"
TASK_NAME="$(basename "$TASK_FILE" .md)"
REPO="$PROJECT_DIR"
TS="$(date +%Y%m%d_%H%M%S)"
# Logs MUST live OUTSIDE the repo. The autonomous prompt previously ran
# `git stash push -u`, which stashed the open --debug-file mid-write and
# crashed claude with ENOENT (appendFileSync to a vanished file).
LOG_DIR="${HOME:-/home/coder}/.local/state/claude-auto-runs/${PROJECT_NAME}"
DEBUG_LOG="$LOG_DIR/${TS}_${TASK_NAME}.debug.log"
META_LOG="$LOG_DIR/${TS}_${TASK_NAME}.meta.log"
# Predetermined session ID so user can `claude --resume <id>` to inspect history.
SESSION_ID="$(cat /proc/sys/kernel/random/uuid)"

mkdir -p "$LOG_DIR"
cd "$REPO"

PROMPT=$(cat <<PROMPT_EOF
Выполни задачу из файла \`${TASK_FILE}\` полностью автономно (без вопросов пользователю — это запуск по таймеру).

Семантика kanban-стадий и переходов задана в \`.claude/skills/kanban/SKILL.md\`. Маршрутизация сабагентов — в разделе \`Сабагенты и делегирование\` корневого CLAUDE.md.

Алгоритм:

1. **Refuse on dirty.** \`git status --porcelain\`. Если есть ЛЮБЫЕ модификации (M/A/D/R/??) — НЕ стэшь, не очищай. Распечатай ровно:
   \`AUTO-RUN-RESULT: skip: ${TASK_NAME}: working tree dirty, manual intervention required\`
   и заверши работу немедленно.

2. **Sanity.** Убедись, что \`${TASK_FILE}\` существует И находится в \`.claude/kanban/todo/\`. Если нет (уже двигалась, удалена, в другом стейдже) — распечатай:
   \`AUTO-RUN-RESULT: skip: ${TASK_NAME}: not in todo/\`
   и заверши работу.

3. **todo → progress** (старт имплементации). Отдельным коммитом:
   \`git mv ${TASK_FILE} .claude/kanban/progress/\$(basename ${TASK_FILE})\`
   \`git commit -m "task: start ${TASK_NAME} (todo→progress)"\`
   Дальше работай с новым путём \`.claude/kanban/progress/\$(basename ${TASK_FILE})\`.

4. **Имплементация.** Прочитай карточку. Делегируй имплементационному сабагенту по правилам CLAUDE.md (\`python-backend\`, \`go-client\`, \`browser-extension\`, \`frontend-spa\`, \`infra-devops\`). Если задача требует анализа безопасности — также \`security-auditor\` (read-only).
   Каждое значимое подзадание — обновление секции **Execution Log** в файле карточки + git-коммит по соглашениям проекта (scope: \`api|goclient|ext|infra|db|docs\`). Сообщения — короткие, в стиле последних коммитов.

5. **qa-check.** Запусти skill **qa-check** (lint + test по затронутым модулям).
   - Если красный — НЕ переноси карточку. Оставь её в \`progress/\`. Зафиксируй проблему в Execution Log + коммит \`task: ${TASK_NAME} qa-check failed\`. Перейди к шагу 9 с \`AUTO-RUN-RESULT: fail\`.

6. **progress → test** (передача тестеру). Отдельным коммитом:
   \`git mv .claude/kanban/progress/\$(basename ${TASK_FILE}) .claude/kanban/test/\$(basename ${TASK_FILE})\`
   \`git commit -m "task: review ${TASK_NAME} (progress→test)"\`

7. **Проверка тестером.** Делегируй \`test-engineer\`:
   - сверить реализацию с разделом **Acceptance Criteria** карточки;
   - запустить релевантные тесты (юнит / интеграционные / e2e — что применимо);
   - если есть e2e-сценарии — прогнать только те, что покрывают эту карточку (не весь suite, если он тяжёлый).
   В Execution Log — что именно проверял + результат.

8. **Финализация (test → ready).**
   - **Если test-engineer всё подтвердил:** отдельным коммитом
     \`git mv .claude/kanban/test/\$(basename ${TASK_FILE}) .claude/kanban/ready/\$(basename ${TASK_FILE})\`
     \`git commit -m "task: ready ${TASK_NAME} (test→ready)"\`
     Перейди к шагу 9 с \`AUTO-RUN-RESULT: ok\`.
   - **Если test-engineer нашёл проблемы:** карточка остаётся в \`test/\`. В Execution Log — конкретные находки. Сделай коммит \`task: ${TASK_NAME} review found issues\`. Перейди к шагу 9 с \`AUTO-RUN-RESULT: fail\`.

9. **Завершение.**
   - НЕ пушить, НЕ создавать PR, НЕ мержить — все изменения остаются локально на текущей ветке.
   - Распечатай ровно одну итоговую строку:
     \`AUTO-RUN-RESULT: <ok|fail|skip>: ${TASK_NAME}: <короткая причина>\`

Жёсткие запреты:
- Никакого \`git stash\`, \`git checkout -- .\`, \`git reset --hard\`, \`git clean\`.
- Никаких пропусков стадий (todo→test, progress→ready и т. п.).
- Один \`git mv\` = один отдельный коммит. Контент-правки и переезды карточки не смешивать.
- \`--no-verify\` не использовать.
- НИКОГДА не двигать карточку в \`.claude/kanban/done/\`. Финальная стадия автономного запуска — \`ready/\`; перенос \`ready→done\` делает пользователь вручную.
PROMPT_EOF
)

{
    echo "================================================================"
    echo "=== AUTO RUN START $(date)"
    echo "=== TASK   : $TASK_NAME"
    echo "=== FILE   : $TASK_FILE"
    echo "=== HEAD   : $(git rev-parse --short HEAD 2>/dev/null) on $(git rev-parse --abbrev-ref HEAD 2>/dev/null)"
    echo "=== SESSION: $SESSION_ID"
    echo "=== RESUME : claude --resume $SESSION_ID"
    echo "=== VIEW   : $SCRIPT_DIR/view-task-history.sh $SESSION_ID"
    echo "=== DEBUG  : $DEBUG_LOG"
    echo "================================================================"
} | tee -a "$META_LOG"

START_EPOCH=$(date +%s)
claude --dangerously-skip-permissions \
    --session-id "$SESSION_ID" \
    --debug-file "$DEBUG_LOG" \
    "$PROMPT"
EXIT=$?
WALL_SEC=$(( $(date +%s) - START_EPOCH ))

# Detect run result by grepping the JSONL for the final AUTO-RUN-RESULT marker.
# Agent often wraps the marker in backticks (`AUTO-RUN-RESULT: ok: ...`); accept
# any preceding char, anchor on canonical "AUTO-RUN-RESULT: <verdict>:" shape.
RESULT="unknown"
JSONL="${HOME:-/home/coder}/.claude/projects/${CLAUDE_PROJECT_PATH}/${SESSION_ID}.jsonl"
if [ -f "$JSONL" ]; then
    LAST_RESULT=$(grep -hoE 'AUTO-RUN-RESULT: (ok|fail|skip):' "$JSONL" | tail -1 | sed -E 's/.*: ([a-z]+):/\1/')
    [ -n "$LAST_RESULT" ] && RESULT="$LAST_RESULT"
fi

{
    echo
    echo "================================================================"
    echo "=== AUTO RUN END $(date), claude exit=$EXIT, result=$RESULT, wall=${WALL_SEC}s"
    echo "================================================================"
} | tee -a "$META_LOG"

# Token/cost summary → stdout + appended to task file.
echo
echo "================================================================"
echo "=== USAGE SUMMARY"
echo "================================================================"
"$SCRIPT_DIR/summarize-task-usage.sh" "$SESSION_ID" "$TASK_FILE" "$WALL_SEC" "$EXIT" "$RESULT" 2>&1 | tee -a "$META_LOG"

# If the appended task file is the only dirty change, commit it so the
# refuse-on-dirty contract holds for the next at-job.
cd "$REPO"
DIRTY=$(git status --porcelain)
if [ -n "$DIRTY" ]; then
    # commit only if EVERY dirty path is a kanban task .md (auto-stats append)
    SAFE=true
    while IFS= read -r line; do
        path="${line:3}"
        case "$path" in
            .claude/kanban/*/*.md) ;;
            *) SAFE=false; break ;;
        esac
    done <<< "$DIRTY"
    if [ "$SAFE" = true ]; then
        git add -A .claude/kanban/
        git commit -m "chore(auto-run): append usage stats for $TASK_NAME" \
                   -m "session: $SESSION_ID, result: $RESULT, wall: ${WALL_SEC}s, exit: $EXIT" \
            >> "$META_LOG" 2>&1 \
            && echo "[inner] committed usage-stats append" \
            || echo "[inner] WARN: could not commit usage-stats append (see meta.log)"
    else
        echo "[inner] WARN: working tree has non-kanban dirt — leaving as-is for manual review"
        git status --short
    fi
fi

# === Chain: enqueue next todo/ card at +3min on ok ===
# Stops on fail/skip/unknown, empty todo/, dirty tree, or inactive atd.
{
    echo
    echo "================================================================"
    echo "=== CHAIN"
    echo "================================================================"
    if [ "$RESULT" != "ok" ]; then
        echo "[chain] result=$RESULT — chain stops"
    elif [ -n "$(git status --porcelain)" ]; then
        echo "[chain] working tree dirty — skipping enqueue"
        git status --short
    elif ! systemctl is-active --quiet atd; then
        echo "[chain] WARN: atd inactive — skipping enqueue"
    else
        NEXT_CARD=$(ls "$REPO/.claude/kanban/todo/" 2>/dev/null | grep -E '\.md$' | sort | head -1)
        if [ -z "$NEXT_CARD" ]; then
            echo "[chain] todo/ empty — chain ends"
        else
            NEXT_PATH="$REPO/.claude/kanban/todo/$NEXT_CARD"
            NEXT_AT=$(date -d '+3 minutes' +%Y%m%d%H%M)
            AT_OUT=$(echo "$SCRIPT_DIR/run-claude-task.sh $NEXT_PATH" | at -t "$NEXT_AT" 2>&1)
            AT_RC=$?
            if [ $AT_RC -eq 0 ]; then
                echo "[chain] enqueued next: $NEXT_CARD at $NEXT_AT"
                echo "$AT_OUT"
            else
                echo "[chain] WARN: at failed (rc=$AT_RC) for $NEXT_CARD"
                echo "$AT_OUT"
            fi
        fi
    fi
} | tee -a "$META_LOG"

echo
echo ">>> Window kept open. Press Enter to close."
read -r _
