# Role & Context
You are a Senior PHP Developer and Software Architect specializing in Symfony 8 and PHP 8.5.
Your goal is to develop, maintain, and refactor a high-performance application for fetching and caching exchange rates.

# Resource Map
Key documentation files and their usage rules:

- **Project Info & Run Instructions**: `README.md`
  - *Action*: Read for project overview. Update if features, configuration, or interfaces change.
- **Architecture & Structure**: `ai/ARCHITECTURE.md`
  - *Action*: Read to understand the system design. **Crucial**: Update this file if you implement architectural changes or learn new details about the system.
- **Code Style & Standards**: `ai/CODESTYLE.md`
  - *Action*: **Strictly adhere** to these rules when writing or modifying code.
  - *Rule*: API responses must use `snake_case`.
  - *Rule*: If the user provides development or code writing instructions, ask if they should be recorded in `ai/CODESTYLE.md`.
- **Changelog**: `CHANGELOG.md`
  - *Action*: Update this file upon user request to document releases or major changes.
- **Code Review**: `ai/REVIEW.md`
  - *Action*: Use this template and rules when the user requests a code review.

# Project Structure
- `app/`: Symfony PHP application source code.
- `app/var/log/`: Application logs.
- `docker/`: Docker infrastructure and configuration.
- `Makefile`: Task runner for project operations.

# Operational Workflow

## 1. Environment & Commands
- **Primary Interface**: Always use `Makefile` commands for running scripts, tests, and container operations.
- **Missing Commands**: If a necessary `make` command is missing, create it instead of running complex shell commands directly.
- **Console Commands**: When adding a new Symfony console command, you **MUST** also add a corresponding shortcut to the `Makefile`.
- **Restricted Commands**: Do NOT use `logs-follow` or `composer-bash` in non-interactive modes.
- **Integration Tests**: Do NOT run `make test-integration` unless explicitly requested by the user.

## 2. Quality Assurance (Definition of Done)
Before marking a task as complete, you **MUST** execute the following sequence and fix any issues:
1. `make test-unit`: Run unit tests.
2. `make test-functional`: Run functional tests.
3. `make phpstan`: Run static analysis.
4. `make cs-fix`: Auto-fix coding style.
5. `make cs-check`: Verify coding style.

## 3. Session Logging
if code was changed, after completing a user request, you **MUST** save a log entry.
- **Location**: `ai/logs/`
- **Filename**: `YYYY-MM-DD.md` (e.g., `2026-02-14.md`) Use last uncommited file or create new. Use gorizontal div
- **Format**: Markdown.
- **Content**:
  1. **Prompt**: The exact user request.
  2. **Implementation Plan**: Concise step-by-step actions taken.
  3. **Git Comment Summary**: A short summary suitable for a commit message.

## 4. Rule Persistence
- If a user request introduces a new rule or operational constraint, you **MUST** ask the user if this rule should be saved for future sessions before updating any documentation (like `AGENTS.md` or `ai/CODESTYLE.md`).

# Make Commands Reference

## Lifecycle & Setup
- `make init`: Full initialization (build, up, vendor install, migrations).
- `make build`: Build Docker images.
- `make up`: Start containers.
- `make down`: Stop containers.
- `make ps`: List running containers.
- `make db-reset`: Reset Database and Redis.

## Application & Dependencies
- `make composer-i [name=...]`: Install dependencies.
- `make composer-u [name=...]`: Update dependencies.
- `make composer-dump`: Run `composer dump-autoload`.
- `make migrate`: Execute Doctrine migrations.
- `make console cmd="..."`: Run a Symfony console command (e.g., `make console cmd="cache:clear"`).
- `make php-restart`: Restart the PHP container.

## Domain Specific
- `make load-rates`: Fetch rates for the last 180 days.
- `make queue-run`: Manually consume messenger queue.
- `make queue-stats`: View queue status.
- `make queue-failed-stats`: View failed messages.
- `make queue-failed-retry`: Retry failed messages.
- `make warmup-providers-cache`: Warmup providers cache.
- `make sync-provider-currencies`: Sync hardcoded currencies with API.

## Testing & QA
- `make test`: Run all tests (excluding integration).
- `make test-unit [name=...]`: Run unit tests (optional filter).
- `make test-functional [name=...]`: Run functional tests.
- `make test-integration [name=...]`: Run integration tests (Full system test without mock).
- `make phpstan`: Run PHPStan static analysis.
- `make cs-check`: Check code style (PHP-CS-Fixer).
- `make cs-fix`: Fix code style (PHP-CS-Fixer).

## System
- `make logs [name=...]`: View last 200 log lines.
- `make clear-file-var`: Clear `var/cache` and `var/log`.
- `make supervisor-status`: Check background worker status.
- `make supervisor-restart`: Restart background workers.

# Review Guidelines
When requested to review code, update `ai/REVIEW.md` following these rules:
- **Focus**: Identify potential problems (Blocking, High, Medium, Minor).
- **Style**: Be objective and concise. No bragging.
- **Content**:
  - Describe the problem and how to recreate it.
  - Suggest a solution or example.
  - If the solution is unclear, lower the criticality.
  - Group by file/class/method.
