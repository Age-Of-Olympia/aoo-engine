.PHONY: all phpstan test test-ci coverage testf setup-ci-env coverage-report migration-status migration-check new-sql stale-branches release-check cypress-tutorial-ci

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

test:
	mkdir -p tmp/coverage
	$(PHPUNIT)

testf:
	$(PHPUNIT) --filter $(word 2,$(MAKECMDGOALS))
%:
	@:

test-ci:
	mkdir -p tmp/coverage
	composer install --no-progress --no-interaction
	./vendor/bin/phpunit -c phpunit.xml --log-junit phpunit-report.xml --coverage-text --colors=never

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

