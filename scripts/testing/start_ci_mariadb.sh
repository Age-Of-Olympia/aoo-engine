#!/bin/bash
#
# CI-only: start MariaDB inside the job container, on loopback.
#
# A GitLab service is a separate container joined through the Docker
# network; on a shared runner the suite's ~15 000 queries paid that hop at
# ~17 ms each — 65x the loopback latency, and the whole cost of the test
# job (#288). Same credentials, database and relaxed-durability flags as
# the retired mariadb:11 service: the base is throwaway, it owes nothing
# to a power cut.
set -e

mkdir -p /run/mysqld /var/log/mysql
chown -R mysql:mysql /run/mysqld /var/lib/mysql /var/log/mysql

(mariadbd \
    --user=mysql \
    --bind-address=127.0.0.1 \
    --skip-log-bin \
    --sync-binlog=0 \
    --innodb-flush-log-at-trx-commit=0 \
    --innodb-doublewrite=0 \
    --performance-schema=OFF \
    > /var/log/mysql/ci.log 2>&1 &)

for i in $(seq 1 60); do
    if mariadb -u root -e "SELECT 1" >/dev/null 2>&1; then
        break
    fi
    sleep 1
done

if ! mariadb -u root -e "SELECT 1" >/dev/null 2>&1; then
    echo "MariaDB did not come up:" >&2
    tail -50 /var/log/mysql/ci.log >&2
    exit 1
fi

# The jobs connect over 127.0.0.1 with a password; a fresh install only
# knows root through the unix socket. Password auth first, database last.
mariadb -u root <<'SQL'
ALTER USER 'root'@'localhost' IDENTIFIED VIA mysql_native_password USING PASSWORD('passwordRoot');
CREATE USER IF NOT EXISTS 'root'@'127.0.0.1' IDENTIFIED VIA mysql_native_password USING PASSWORD('passwordRoot');
GRANT ALL PRIVILEGES ON *.* TO 'root'@'127.0.0.1' WITH GRANT OPTION;
CREATE DATABASE IF NOT EXISTS aoo4 CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
FLUSH PRIVILEGES;
SQL

echo "MariaDB up on 127.0.0.1 (in-container)."
