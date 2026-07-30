---
paths:
  - "src/Migrations/**"
  - "db/**"
  - "config/db_constants*"
  - "config/bootstrap.php"
---

# Database

- **MariaDB** database named `aoo4`
- Initialized via `db/init_noupdates.sql` on container startup
- Connection config: `config/db_constants.php` (must be created from `.exemple` file)
- Use PHPMyAdmin at `http://localhost:8081` for database inspection

## UTF-8 Encoding with MySQL

**CRITICAL**: When updating database records with UTF-8 content (French accents, special characters), always use the `--default-character-set=utf8mb4` flag to prevent encoding corruption.

```bash
# ❌ WRONG - Will corrupt UTF-8 characters
mysql -h mariadb-aoo4 -u root -ppasswordRoot database_name -e "UPDATE table SET text = 'déplacer';"
# Result: "dÃ©placer" (corrupted)

# ✅ CORRECT - Preserves UTF-8 encoding
mysql -h mariadb-aoo4 -u root -ppasswordRoot database_name --default-character-set=utf8mb4 -e "UPDATE table SET text = 'déplacer';"
# Result: "déplacer" (correct)
```

Without the flag the client assumes latin1 and the text is double-encoded (UTF-8 → latin1 →
UTF-8), which hits every user-facing string: tutorial steps, descriptions, error messages.
Verify with a `SELECT` after the `UPDATE`; PHPMyAdmin handles the encoding on its own.
