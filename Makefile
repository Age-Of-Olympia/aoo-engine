.PHONY: all phpstan test test-ci coverage testf

PHPUNIT = XDEBUG_MODE=coverage ./vendor/bin/phpunit --testdox

all: phpstan test coverage

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
