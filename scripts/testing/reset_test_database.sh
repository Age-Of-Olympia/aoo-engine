#!/bin/bash

# Reset test database to clean state
# This ensures each test run starts fresh
# Clones structure from main database to ensure schema compatibility
#
# Environment overrides (defaults match the devcontainer):
#   DB_HOST    — MariaDB host           (default: mariadb-aoo4)
#   DB_USER    — MariaDB user           (default: root)
#   DB_PASS    — MariaDB password       (default: passwordRoot)
#   DB_NAME    — Test DB name           (default: aoo4_test)
# Script-relative paths replace the previous /var/www/html hardcodes so
# this script also works when the project is mounted at a different
# location (e.g. /builds/.../aoo-engine in GitLab CI).

DB_HOST="${DB_HOST:-mariadb-aoo4}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-passwordRoot}"
DB_NAME="${DB_NAME:-aoo4_test}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"

echo "🔄 Resetting test database: $DB_NAME"

# Drop database
echo "📦 Dropping database..."
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" -e "DROP DATABASE IF EXISTS $DB_NAME;"

# Clear player JSON cache to prevent stale data
echo "🗑️  Clearing player cache..."
rm -rf "$PROJECT_ROOT/datas/private/players/"*.json 2>/dev/null || true

# Run the complete initialization script
echo "🏗️  Initializing from main database structure..."
DB_HOST="$DB_HOST" DB_USER="$DB_USER" DB_PASS="$DB_PASS" \
    bash "$PROJECT_ROOT/db/init_test_from_dump.sh"

echo ""
echo "✅ Test database reset complete!"
echo ""
echo "📊 Database info:"
TABLE_COUNT=$(mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '$DB_NAME';")
echo "  - Tables: $TABLE_COUNT"
echo ""
echo "👥 Test characters available:"
echo "  - TestAdmin (ID: 100, password: test, admin: yes)"
echo "  - TestFreshPlayer (ID: 101, password: testpass)"
echo "  - TestTutorialStarted (ID: 102, password: testpass)"
echo "  - TestTutorialCompleted (ID: 103, password: testpass)"
echo "  - TestTutorialSkipped (ID: 104, password: testpass)"
