# FForms — local development via wp-env (see .wp-env.json)
# Usage: make <target>   |   make help

# http://localhost:8890
WP_ENV = npx wp-env
PORT   = 8890
SITE   = http://localhost:$(PORT)

.DEFAULT_GOAL := help
.PHONY: help install start stop restart destroy reset update xdebug logs tail cli bash wp status

help: ## show this help
	@grep -E '^[a-z-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-10s\033[0m %s\n", $$1, $$2}'

install: ## install node deps (@wordpress/env)
	npm install

start: ## start the environment (http://localhost:8890, admin/password)
	$(WP_ENV) start

stop: ## stop the environment
	$(WP_ENV) stop

restart: stop start ## stop then start

update: ## restart pulling the latest WordPress core
	$(WP_ENV) start --update

reset: ## wipe the database and reinstall WordPress
	$(WP_ENV) reset all && $(WP_ENV) start

destroy: ## remove containers and volumes
	$(WP_ENV) destroy

xdebug: ## start with Xdebug enabled
	$(WP_ENV) start --xdebug

logs: ## tail PHP and Docker logs
	$(WP_ENV) logs development

tail: ## follow wp-content/debug.log inside the container
	$(WP_ENV) run cli sh -c 'touch /var/www/html/wp-content/debug.log && tail -f /var/www/html/wp-content/debug.log'

cli: ## run WP-CLI, e.g. make cli CMD="plugin list"
	$(WP_ENV) run cli wp $(CMD)

bash: ## open a shell in the WordPress container
	$(WP_ENV) run cli bash

wp: cli ## alias for cli

status: ## report WP/PHP versions, plugin and block state
	@$(WP_ENV) run cli wp eval 'printf( "wp=%s php=%s plugin=%s block=%s permalinks=%s\n", get_bloginfo( "version" ), PHP_VERSION, is_plugin_active( "_fforms/fforms.php" ) ? "active" : "inactive", WP_Block_Type_Registry::get_instance()->is_registered( "fforms/form" ) ? "registered" : "missing", get_option( "permalink_structure" ) ?: "plain" );' --skip-themes 2>/dev/null | grep -E '^wp='
	@printf 'rest=%s home=%s\n' \
	  "$$(curl -s -o /dev/null -w '%{http_code}' $(SITE)/wp-json/fforms/v1)" \
	  "$$(curl -s -o /dev/null -w '%{http_code}' $(SITE)/)"
