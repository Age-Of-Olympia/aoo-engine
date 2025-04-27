.PHONY: phpstan test test-ci coverage

all: phpstan test coverage

phpstan:
	./vendor/bin/phpstan analyse -c phpstan.neon --memory-limit 1G

test:
	mkdir -p tmp/coverage
	XDEBUG_MODE=coverage ./vendor/bin/phpunit --testdox

test-ci:
	mkdir -p tmp/coverage
	./vendor/bin/phpunit -c phpunit.xml --coverage-text --colors=never

coverage:
	mkdir -p tmp/coverage
	XDEBUG_MODE=coverage ./vendor/bin/phpunit --coverage-html tmp/coverage --testdox
