# Renaming from Movary to Pathary - Summary

**Date**: 2026-01-12
**Status**: ✅ Ready to execute

---

## Why the Rename?

Pathary is a fork of the Movary project. However, many internal references still use "movary" instead of "pathary". Since this is a new project with no production installations, now is the perfect time to rename everything for consistency.

---

## What Will Be Renamed

### 1. MySQL Database
- **Old**: `movary`
- **New**: `pathary`
- **Location**: MySQL container
- **Impact**: Database name in MySQL server

### 2. SQLite Database Files
- **Old**: `storage/movary.sqlite`
- **New**: `storage/pathary.sqlite`
- **Location**: Local filesystem
- **Impact**: SQLite database file path

- **Old**: `db/movary.db` (empty file, 0 bytes)
- **New**: `db/pathary.db`
- **Location**: Local filesystem
- **Impact**: Empty database file (can be deleted)

### 3. Code Constants
- **File**: `src/Factory.php`
- **Old**: `private const string DEFAULT_DATABASE_SQLITE = 'storage/movary.sqlite';`
- **New**: `private const string DEFAULT_DATABASE_SQLITE = 'storage/pathary.sqlite';`
- **Impact**: Default SQLite database path
- **Status**: ✅ Already updated

### 4. Migration Files
- **Action**: Consolidate 97 MySQL + 55 SQLite migrations → 1 migration each
- **Impact**: Fresh start with clean migration history
- **Old migrations**: Archived to `db/migrations_archive/`

---

## What Will NOT Be Changed

### Namespace
- **Stays**: `namespace Movary;`
- **Reason**: Changing PHP namespaces across the entire codebase is high-risk
- **Impact**: None - internal implementation detail
- **Recommendation**: Change gradually in future versions

### Docker Container Names
- **Stays**: `pathary-mysql`, `pathary-app` (already named pathary)
- **Status**: ✅ Already correct

### Environment Variables
- **No changes needed**: Database name configured via `DATABASE_MYSQL_NAME`
- **Default**: Will automatically use new database name

---

## How to Execute the Rename

### Automated (Recommended)

Run the consolidation script:

```bash
./scripts/consolidate-migrations.sh
```

This script will:
1. ✅ Archive old migrations (97 MySQL + 55 SQLite → `db/migrations_archive/`)
2. ✅ Export current schema from `movary` database
3. ✅ Create new `pathary` database
4. ✅ Copy all data from `movary` → `pathary`
5. ✅ Rename SQLite files: `movary.sqlite` → `pathary.sqlite`
6. ✅ Create consolidated initial migration
7. ✅ Update schema exports to reference `pathary`

### Manual (If Needed)

If you prefer to do it manually:

1. **Rename MySQL database**:
   ```bash
   docker compose exec mysql mysql -uroot -proot -e "
   CREATE DATABASE pathary CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
   "
   docker compose exec mysql mysqldump -uroot -proot movary | \
   docker compose exec -T mysql mysql -uroot -proot pathary
   ```

2. **Rename SQLite files**:
   ```bash
   mv storage/movary.sqlite storage/pathary.sqlite
   mv db/movary.db db/pathary.db  # This file is empty anyway
   ```

3. **Update code** (already done):
   - ✅ `src/Factory.php` - DEFAULT_DATABASE_SQLITE constant

---

## Testing After Rename

### Verify MySQL Database

```bash
# Check pathary database exists
docker compose exec mysql mysql -uroot -proot -e "SHOW DATABASES;" | grep pathary

# Check tables exist
docker compose exec mysql mysql -uroot -proot pathary -e "SHOW TABLES;"

# Should show 36 tables:
# location, user, movie, movie_user_rating, etc.
```

### Verify SQLite Database

```bash
# Check file exists
ls -lh storage/pathary.sqlite

# Check tables (if using SQLite)
sqlite3 storage/pathary.sqlite ".tables"
```

### Verify Application

```bash
# Restart containers
docker compose restart app

# Check app connects to correct database
docker compose logs app | grep -i "database\|pathary"

# Test login at http://localhost/
```

---

## Rollback (If Needed)

If something goes wrong:

```bash
# Restore from movary database (still exists)
docker compose exec mysql mysql -uroot -proot -e "DROP DATABASE pathary;"
docker compose exec mysql mysqldump -uroot -proot movary | \
docker compose exec -T mysql mysql -uroot -proot pathary

# Or revert code changes
git checkout src/Factory.php
```

---

## File Changes Summary

| File | Change | Status |
|------|--------|--------|
| `src/Factory.php` | `movary.sqlite` → `pathary.sqlite` | ✅ Updated |
| `storage/movary.sqlite` | Rename to `pathary.sqlite` | ⏳ Pending script |
| `db/movary.db` | Rename to `pathary.db` | ⏳ Pending script |
| MySQL database | `movary` → `pathary` | ⏳ Pending script |
| Migrations | Consolidate & archive | ⏳ Pending script |

---

## Benefits of This Rename

1. ✅ **Consistency**: Everything named "Pathary" instead of mix of Movary/Pathary
2. ✅ **Clean Start**: Fresh migration history (1 file vs 97)
3. ✅ **Professionalism**: No references to forked project name
4. ✅ **Clarity**: Clear project identity
5. ✅ **Maintainability**: Easier to understand codebase

---

## Next Steps

1. Run `./scripts/consolidate-migrations.sh`
2. Test the application at http://localhost/
3. Verify database connection works
4. Commit changes:
   ```bash
   git add .
   git commit -m "Rename database from movary to pathary and consolidate migrations"
   ```
5. Tag as `v1.0.0-alpha`:
   ```bash
   git tag -a v1.0.0-alpha -m "First consolidated release with pathary branding"
   ```

---

## Questions?

- **Will this break existing installations?** No, because there are no existing installations yet.
- **Can I still access the old movary database?** Yes, it's preserved and can be dropped manually later.
- **Do I need to update .env files?** No, environment variables handle this automatically.
- **What about Docker volumes?** No changes needed, data is copied not moved.

---

**Status**: Ready to execute! Run `./scripts/consolidate-migrations.sh` when ready.
