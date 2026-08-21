.PHONY: all phpstan test test-db test-ci test-ci-coverage coverage testf setup-ci-env coverage-report migration-status migration-check new-sql stale-branches release-check cypress-tutorial-ci cypress-endpoint-ci

PHPUNIT = XDEBUG_MODE=coverage ./vendor/bin/phpunit --testdox

all: phpstan test coverage

setup-ci-env:
	mkdir -p datas img config tmp
	cp -r datas_standalone/* datas/ 2>/dev/null || echo "No datas_standalone found"
	cp -r img_standalone/* img/ 2>/dev/null || echo "No img_standalone found"
	cp config/db_constants.php.exemple config/db_constants.php 2>/dev/null || echo "Config already exists"
	cp .env.dist .env 2>/dev/null || echo ".env already exists"

phpstan:
	./vendor/bin/phpstan analyse -c phpstan.neon --memory-limit 1G

# La base jetable de la suite se reconstruit depuis le schéma VIVANT, donc
# après chaque migration. Le client MariaDB n'existe pas dans le devcontainer :
# la commande se lance depuis l'hôte, et tests/bootstrap.php refuse de tourner
# sur une base en retard plutôt que de laisser croire à un bug de code.
test-db:
	docker exec -i -e DB_HOST=127.0.0.1 aoo-engine-mariadb-aoo4-1 \
	  bash -s < scripts/testing/reset_phpunit_database.sh

test:
	mkdir -p tmp/coverage
	$(PHPUNIT)

testf:
	$(PHPUNIT) --filter $(word 2,$(MAKECMDGOALS))
%:
	@:

# Suite sans couverture : c'est le retour rapide des pipelines de branche.
# --no-coverage est nécessaire, pas décoratif : phpunit.xml déclare des
# rapports de couverture, et sans pilote PHPUnit émet un avertissement —
# que failOnWarning transforme en job rouge.
# Mesurer la couverture multiplie le temps de calcul par ~7 (2,3 s de CPU
# contre 16,3 s sur cette suite), pour un chiffre qui n'intéresse que les
# branches d'intégration.
# --display-skipped : le rapport junit que GitLab conserve perd le motif des
# sauts ; seul le log du job peut dire POURQUOI un test s'est sauté.
test-ci:
	mkdir -p tmp/coverage
	./vendor/bin/phpunit -c phpunit.xml --log-junit phpunit-report.xml --colors=never --no-coverage --display-skipped

# Couverture par pcov plutôt que Xdebug, et activée à la demande :
# l'extension est chargée avec pcov.enabled=0 pour ne rien coûter au reste
# du job (migrations, PHPStan).
test-ci-coverage:
	mkdir -p tmp/coverage
	php -d pcov.enabled=1 -d pcov.directory=src -d memory_limit=512M ./vendor/bin/phpunit -c phpunit.xml \
		--log-junit phpunit-report.xml --coverage-text --colors=never --display-skipped

phpstan-ci:
	composer install --no-progress --no-interaction
	$(MAKE) phpstan

coverage:
	mkdir -p tmp/coverage
	XDEBUG_MODE=coverage ./vendor/bin/phpunit --coverage-html tmp/coverage --testdox

coverage-report:
	bash scripts/tools/test-coverage-report.sh

migration-status:
	bash scripts/tools/migration-helper.sh status

migration-check:
	bash scripts/tools/migration-helper.sh check

new-sql:
	bash scripts/tools/migration-helper.sh new-sql $(word 2,$(MAKECMDGOALS))

stale-branches:
	bash scripts/tools/stale-branches.sh

release-check:
	bash scripts/tools/release-checklist.sh

cypress-tutorial-ci:
	bash scripts/testing/reset_test_database.sh
	CYPRESS_CONTAINER=true xvfb-run --auto-servernum npx cypress run \
		--spec "cypress/e2e/tutorial-production-ready.cy.js,cypress/e2e/tutorial-resume-persistence.cy.js" \
		--browser electron \
		--reporter junit \
		--reporter-options "mochaFile=cypress-report.xml,toConsole=true"

# L'endpoint action.php, exercé par HTTP. Ne dépend d'aucune fixture de
# tutoriel : c'est ce qui lui permet de tourner sur les merge requests, là
# où le job tutoriel reste en attente.
cypress-endpoint-ci:
	CYPRESS_CONTAINER=true xvfb-run --auto-servernum npx cypress run \
		--spec "cypress/e2e/action-endpoint.cy.js,cypress/e2e/gesture-endpoints.cy.js" \
		--browser electron \
		--reporter junit \
		--reporter-options "mochaFile=cypress-endpoint-report.xml,toConsole=true"

sqlmap-login:
	python3 gitlab-ci/sqlmap-dev/sqlmap.py -u "http://localhost:80/login.php" \
		--data="name=test&psw=test" \
		-p name,psw \
		--dbms=mysql \
		--risk=3 \
		--level=1 \
		--batch \
		--output-dir=tmp/security

sqlmap-register:
	python3 gitlab-ci/sqlmap-dev/sqlmap.py -u "http://localhost:80/register.php" \
		--data="name=test&race=test&psw1=test&psw2=test&mail=test@test.fr" \
		-p name,race,psw1,psw2,mail \
		--dbms=mysql \
		--risk=3 \
		--level=1 \
		--batch \
		--output-dir=tmp/security

