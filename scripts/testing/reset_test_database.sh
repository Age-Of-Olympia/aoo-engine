#!/bin/bash

# Reset test database to clean state
# This ensures each test run starts fresh
# Clones structure from main database to ensure schema compatibility

DB_NAME="aoo4_test"

echo "🔄 Resetting test database: $DB_NAME"

# Drop database
echo "📦 Dropping database..."
mysql -h mariadb-aoo4 -u root -ppasswordRoot -e "DROP DATABASE IF EXISTS $DB_NAME;"

# Clear player JSON cache to prevent stale data
echo "🗑️  Clearing player cache..."
rm -rf /var/www/html/datas/private/players/*.json 2>/dev/null || true

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
echo "  - TestAdmin (ID: 100, password: test, admin: yes)"
echo "  - TestFreshPlayer (ID: 101, password: testpass)"
echo "  - TestTutorialStarted (ID: 102, password: testpass)"
echo "  - TestTutorialCompleted (ID: 103, password: testpass)"
echo "  - TestTutorialSkipped (ID: 104, password: testpass)"
