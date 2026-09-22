#!/bin/bash
# Runs once, on first initialisation of the MySQL volume.
# Creates the database used by the MySQL concurrency test suite
# (backend/phpunit.concurrency.xml) — kept separate from the dev database.
set -e

mysql -uroot -p"$MYSQL_ROOT_PASSWORD" <<SQL
CREATE DATABASE IF NOT EXISTS mios_testing;
GRANT ALL PRIVILEGES ON mios_testing.* TO '$MYSQL_USER'@'%';
FLUSH PRIVILEGES;
SQL
