#!/bin/bash

# Reset test database to clean state
# This ensures each test run starts fresh
# Clones structure from main database to ensure schema compatibility

DB_NAME="aoo4_test"

echo "🔄 Resetting test database: $DB_NAME"

# Drop database
echo "📦 Dropping database..."
mysql -h mariadb-aoo4 -u root -ppasswordRoot -e "DROP DATABASE IF EXISTS $DB_NAME;"

# Run the complete initialization script
echo "🏗️  Initializing from main database structure..."
bash /var/www/html/db/init_test_from_dump.sh

echo ""
echo "✅ Test database reset complete!"
echo ""
echo "📊 Database info:"
TABLE_COUNT=$(mysql -h mariadb-aoo4 -u root -ppasswordRoot -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '$DB_NAME';")
echo "  - Tables: $TABLE_COUNT"
echo ""
echo "👥 Test characters available:"
echo "  - TestPlayerActive (ID: 100, password: test)"
echo "  - TestPlayerInactive (ID: 101, password: test)"
echo "  - TestTutorialStarted (ID: 102, password: test)"
echo "  - TestAdmin (ID: 103, password: test, admin: yes)"
