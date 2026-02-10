SHELL = /bin/bash
### https://makefiletutorial.com/

include .env_dist
export

-include .env
export

.PHONY: up down build install migrate console test analyze cs-fix

TTY ?= $(shell if [ -t 0 ]; then echo "-it"; else echo ""; fi)
PWD := $(shell pwd)

dc:= docker compose
composer:= $(dc) exec php composer
composer-lite := docker run --rm -v ${PWD}/app:/app -w /app --env-file .env_dist ${TTY} composer/composer:latest

##@ Help
help:  ## Display this help
	@awk 'BEGIN {FS = ":.*##"; printf "\nUsage:\n  make \033[36m<target>\033[0m\n"} /^[a-zA-Z_-]+:.*?##/ { printf "  \033[36m%-25s\033[0m %s\n", $$1, $$2 } /^##@/ { printf "\n\033[1m%s\033[0m\n", substr($$0, 5) } ' $(MAKEFILE_LIST)

init: composer-install up migrate ## Fast run project

##@ Docker

up: ## Docker up
	$(dc) up -d

up-force: ## Docker UP (Force recreate/Update/Pull) (With parameter `name`)
	$(dc) up -d --force-recreate --remove-orphans --pull=always $(name)

down: ## Docker down
	$(dc) down

build: ## Docker Build
	$(dc) build

ps: ## Docker container list
	$(dc) ps

logs-follow: ## Docker logs follow (interactive) (With parameter `name`)
	$(dc) logs --tail=20 --follow $(name)

logs: ## Docker logs last 200 (With parameter `name`)
	$(dc) logs --tail=200 $(name)

php-console: ## PHP shell (interactive)
	$(dc) exec php sh

php-restart: ## PHP container restart
	$(dc) restart php

##@ Composer

composer-bash: ## Composer bash (interactive)
	$(composer-lite) bash

composer-i: ## Install dependencies
	$(composer-lite) composer i --ignore-platform-reqs $(name)

composer-require: ## Require dependency (With parameter `name`)
	$(composer-lite) composer require --ignore-platform-reqs $(name)

composer-u: ## Update dependencies
	$(composer-lite) composer u --ignore-platform-reqs $(name)

composer-dump: ## dump-autoload
	$(composer-lite) composer dump-autoload --ignore-platform-reqs

##@ Tools

console: ## Symfony command console (interactive) (With parameter `cmd`)
	$(dc) exec php bin/console $(cmd)

migrate: ## Run migrations
	$(composer) migrate
	$(composer) test:migrate

load-rates: ## Load rates from 180 last days
	@make console cmd="app:fetch-history --days=180 --provider=$(provider)" --no-print-directory

queue-run: ## manual Run Queue
	@make console cmd="messenger:consume async -vv" --no-print-directory

queue-stats: ## show Queue stats
	@make console cmd="messenger:stats --format=json" --no-print-directory

queue-failed-stats: ## show failed Queue stats
	@make console cmd="messenger:failed:show --stats" --no-print-directory

queue-failed-retry: ## retry failed Queue
	@make console cmd="messenger:failed:retry -vv" --no-print-directory

warmup-providers-cache: ## Warmup providers cache
	@make console cmd="app:warmup-providers-cache" --no-print-directory

sync-provider-currencies: ## Sync provider currencies
	@make console cmd="app:sync-provider-currencies" --no-print-directory

clear-file-var: ## Clear file in var/
	$(dc) exec php rm -rf /app/var/cache
	$(dc) exec php rm -rf /app/var/log

db-reset: ## Refresh database and redis
	$(dc) exec cache keydb-cli FLUSHALL
	@make console cmd="doctrine:schema:drop --full-database --force" --no-print-directory
	@make console cmd="doctrine:schema:drop --full-database --force -e test" --no-print-directory
	@make migrate --no-print-directory

generate-secret: ## get secret for APP_SECRET
	php -r "print bin2hex(random_bytes(26));"

##@ Tests

test: ## All checks and tests (except Integration tests)
	$(composer) test:cs-fix
	$(composer) test:cs-check
	$(composer) test:phpstan
	$(composer) test:unit
	$(composer) test:functional

test-integration: ## Integration tests (Full system test). Filter by provider: `make test-integration provider=cbr`
	$(dc) exec -e TEST_PROVIDER=$(provider) php composer test:integration $(name)

test-functional: ## Functional tests
	$(composer) test:functional $(name)

test-unit: ## Unit tests (With parameter `name`)
	$(composer) test:unit $(name)

phpstan: ## Static analysis
	$(composer) test:phpstan

cs-fix: ## Codestyle fixer
	$(composer) test:cs-fix

cs-check: ## Codestyle check
	$(composer) test:cs-check


##@ Supervisor
supervisor-update: ## update
	$(dc) exec php supervisorctl update all

supervisor-restart: ## restart
	$(dc) exec php supervisorctl restart all

supervisor-start: ## start
	$(dc) exec php supervisorctl start all

supervisor-status: ## status
	$(dc) exec php supervisorctl status

supervisor-stop: ## stop
	$(dc) exec php supervisorctl stop all
