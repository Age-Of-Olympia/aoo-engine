.PHONY: all phpstan test test-ci coverage testf setup-ci-env

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
	./vendor/bin/phpunit -c phpunit.xml --coverage-text --colors=never

phpstan-ci:
	composer install --no-progress --no-interaction
	$(MAKE) phpstan

coverage:
	mkdir -p tmp/coverage
	XDEBUG_MODE=coverage ./vendor/bin/phpunit --coverage-html tmp/coverage --testdox

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


selenium-install:
	cd selenium_tests && npm install

e2e:
	cd selenium_tests && npm run e2e

e2e-report:
	cd selenium_tests && npm run e2e:report

e2e-ci:
	$(MAKE) selenium-install e2e-report

