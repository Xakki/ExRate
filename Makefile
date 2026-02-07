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
php-composer := docker run --rm -v ${PWD}/app:/app -w /app --env-file .env_dist ${TTY} composer:2

##@ Help
help:  ## Display this help
	@awk 'BEGIN {FS = ":.*##"; printf "\nUsage:\n  make \033[36m<target>\033[0m\n"} /^[a-zA-Z_-]+:.*?##/ { printf "  \033[36m%-25s\033[0m %s\n", $$1, $$2 } /^##@/ { printf "\n\033[1m%s\033[0m\n", substr($$0, 5) } ' $(MAKEFILE_LIST)

init: composer-install up migrate ## Fast run project

##@ Docker

up: ## Docker up
	$(dc) up -d

up-force: ## Docker UP (Force recreate/Update/Pull)
	$(dc) up -d --force-recreate --remove-orphans --pull=always $(name)

down: ## Docker down
	$(dc) down

build: ## Build
	$(dc) build

ps: ## PS
	$(dc) ps

logs-follow: ## Docker logs follow
	$(dc) logs --tail=20 --follow $(name)

logs: ## Docker logs last 200
	$(dc) logs --tail=200 $(name)

php-console: ## PHP interactive shell
	$(dc) exec php sh

php-restart: ## PHP restart
	$(dc) restart php

##@ Composer

composer-bash: ## Composer bash
	$(php-composer) bash

composer-install: ## Install dependencies
	$(php-composer) composer i --ignore-platform-reqs $(name)

composer-update: ## Update dependencies
	$(php-composer) composer u --ignore-platform-reqs $(name)

##@ Tools

console: ## Symfony console
	$(dc) exec php bin/console $(cmd)

migrate: ## Run migrations
	@make console cmd="doctrine:migrations:migrate --no-interaction" --no-print-directory

load-rates: ## Load rates from 180 last days
	@make console cmd="app:fetch-history --days=180" --no-print-directory

queue-run: ## manual Run Queue
	@make console cmd="messenger:consume async -vv" --no-print-directory

clear-file-cache: ## Clear file cache
	$(dc) exec php rm -rf /app/var/cache

db-reset: ## Reset database and redis
	$(dc) exec cache keydb-cli FLUSHALL
	@make console cmd="doctrine:database:drop --force --if-exists" --no-print-directory
	@make console cmd="doctrine:database:create" --no-print-directory
	@make migrate --no-print-directory

generate-secret:
	php -r "print bin2hex(random_bytes(26));"

##@ Tests

test: ## Unit tests
	$(dc) exec php composer test:unit

phpstan: ## Static analysis
	$(dc) exec php composer test:phpstan

cs-fix: ## Codestyle fixer
	$(dc) exec php composer test:cs-fix

cs-check: ## Codestyle check
	$(dc) exec php composer test:cs-check


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
