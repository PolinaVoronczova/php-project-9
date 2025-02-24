install:
	composer install
lint:
	composer exec --verbose phpcs -- --standard=PSR12 src public
PORT ?= 8000
start:
	PHP_CLI_SERVER_WORKERS=5 php -S 0.0.0.0:$(PORT) -t public
create tables:
	psql -a -d $DATABASE_URL -f database.sql