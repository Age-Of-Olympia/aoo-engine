#!/bin/bash

# Switch to Production Database
# This script modifies config/db_constants.php to use aoo4 database

CONFIG_FILE="/var/www/html/config/db_constants.php"

echo "==========================================="
echo "Switching to PRODUCTION database (aoo4)"
echo "==========================================="

# If backup exists, restore from it
if [ -f "${CONFIG_FILE}.backup" ]; then
    echo "Restoring from backup: ${CONFIG_FILE}.backup"
    cp "${CONFIG_FILE}.backup" "$CONFIG_FILE"
    echo "✅ Database restored to: aoo4 (from backup)"
else
    # No backup, use production database name (aoo_prod_20251127)
    sed -i "s/'db'=>\"[^\"]*\"/'db'=>\"aoo_prod_20251127\"/g" "$CONFIG_FILE"
    sed -i "s/'dbname'=>\"[^\"]*\"/'dbname'=>\"aoo_prod_20251127\"/g" "$CONFIG_FILE"
    echo "✅ Database switched to: aoo_prod_20251127"
fi

echo ""
echo "Current database configuration:"
grep -E "(db|dbname)" "$CONFIG_FILE" | head -2
echo ""
