#!/bin/bash

# Switch to Test Database
# This script modifies config/db_constants.php to use aoo4_test database

CONFIG_FILE="/var/www/html/config/db_constants.php"

echo "==========================================="
echo "Switching to TEST database (aoo4_test)"
echo "==========================================="

# Backup current config if not already backed up
if [ ! -f "${CONFIG_FILE}.backup" ]; then
    echo "Creating backup: ${CONFIG_FILE}.backup"
    cp "$CONFIG_FILE" "${CONFIG_FILE}.backup"
fi

# Replace any database name with 'aoo4_test'
sed -i "s/'db'=>\"[^\"]*\"/'db'=>\"aoo4_test\"/g" "$CONFIG_FILE"
sed -i "s/'dbname'=>\"[^\"]*\"/'dbname'=>\"aoo4_test\"/g" "$CONFIG_FILE"

echo "✅ Database switched to: aoo4_test"
echo ""
echo "Current database configuration:"
grep -E "(db|dbname)" "$CONFIG_FILE" | head -2
echo ""
echo "To switch back to production database, run:"
echo "  ./scripts/testing/switch_to_prod_db.sh"
echo ""
