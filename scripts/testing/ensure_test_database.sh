#!/bin/bash

# Ensures test database exists and is ready
# Can be run at any time without affecting existing data

DB_NAME="aoo4_test"

echo "🔍 Checking if test database exists..."

# Check if database exists
DB_EXISTS=$(mysql -h mariadb-aoo4 -u root -ppasswordRoot -e "SHOW DATABASES LIKE '$DB_NAME';" | grep "$DB_NAME")

if [ -z "$DB_EXISTS" ]; then
    echo "📦 Creating test database: $DB_NAME"
    mysql -h mariadb-aoo4 -u root -ppasswordRoot -e "CREATE DATABASE $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    mysql -h mariadb-aoo4 -u root -ppasswordRoot -e "GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO 'run'@'%'; FLUSH PRIVILEGES;"
    echo "✅ Test database created successfully!"
else
    echo "✅ Test database already exists"
fi
