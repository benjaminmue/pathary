# Initial User Setup

How to create the first user account for a fresh Pathary installation.

> **Note:** A web-based `/init` setup wizard is planned for first-time installation. See [GitHub Issue #45](https://github.com/benjaminmue/pathary/issues/45). Currently, use the CLI method below for initial user creation.

## Prerequisites

- Pathary is running (Docker or local)
- Database migrations have been executed
- You have CLI access to the container or server

## Method 1: CLI Command (Recommended)

Use the `user:create` command for headless or scripted setups.

### Docker

```bash
docker exec -it pathary php bin/console.php user:create \
  "admin@example.com" \
  "YourSecurePassword123" \
  "admin" \
  true
```

### Local / Docker Compose

```bash
# If using docker-compose
docker compose exec app php bin/console.php user:create \
  "admin@example.com" \
  "YourSecurePassword123" \
  "admin" \
  true

# If running locally
php bin/console.php user:create \
  "admin@example.com" \
  "YourSecurePassword123" \
  "admin" \
  true
```

### Command Arguments

| Argument | Required | Description |
|----------|----------|-------------|
| `email` | Yes | Valid email address |
| `password` | Yes | Minimum 10 characters |
| `name` | Yes | Username (letters and numbers only, e.g. `admin123`) |
| `isAdmin` | No | `true` or `false` (default: `false`) |

### Example Output

```
User created.
```

### Error Messages

| Error | Cause |
|-------|-------|
| `Email already in use` | Email exists in database |
| `Password must contain at least 10 characters` | Password too short |
| `Name must only consist of numbers and letters` | Invalid characters in username |
| `Name already in use` | Username already taken |

## Method 2: Direct Database Insert (Last Resort)

Only use this if the CLI method fails.

### Generate Password Hash

```bash
php -r "echo password_hash('YourSecurePassword123', PASSWORD_DEFAULT) . PHP_EOL;"
```

Output example:
```
$2y$10$abcdefghijklmnopqrstuv.wxyzABCDEFGHIJKLMNOPQRS
```

### Insert User

```sql
INSERT INTO user (email, password, name, is_admin, created_at)
VALUES (
  'admin@example.com',
  '$2y$10$YOUR_GENERATED_HASH_HERE',
  'admin',
  1,
  datetime('now')
);
```

For MySQL, use `NOW()` instead of `datetime('now')`.

### Required Columns

| Column | Type | Description |
|--------|------|-------------|
| `email` | string | Unique email address |
| `password` | string | bcrypt hash from `password_hash()` |
| `name` | string | Unique username (alphanumeric) |
| `is_admin` | int | `1` for admin, `0` for regular user |
| `created_at` | datetime | Account creation timestamp |

## Troubleshooting

### Cannot create first user via web interface

**Cause**: The `/init` setup wizard is not yet implemented (see [GitHub Issue #45](https://github.com/benjaminmue/pathary/issues/45)).

**Solution**: Use the CLI method (`user:create` command) to create the first user.

### Database connection errors

**Cause**: Database not configured or migrations not run.

**Solutions**:
```bash
# Check database mode
echo $DATABASE_MODE

# Run migrations (Docker)
docker exec -it pathary php bin/console.php database:migration:migrate

# Run migrations (local)
php bin/console.php database:migration:migrate
```

### "Could not create user" with no details

**Cause**: Generic error, check logs.

**Solution**:
```bash
# View logs (Docker)
docker logs pathary

# View logs (local)
cat storage/logs/*.log
```

### Cookie/session issues after login

**Cause**: Browser blocking cookies or `APPLICATION_URL` mismatch.

**Solutions**:
- Ensure `APPLICATION_URL` matches how you access the app
- Clear browser cookies for the domain
- Check browser console for cookie warnings

## Verification Checklist

After creating your first user:

- [ ] Login works at `/login`
- [ ] Dashboard loads after login
- [ ] Can search for movies
- [ ] Can add a movie to library
- [ ] Can rate a movie (1-7 popcorns)
- [ ] Admin settings accessible at `/admin/users` (if admin)

## User Management Commands

```bash
# List all users
php bin/console.php user:list

# Update user
php bin/console.php user:update <userId> --email="new@email.com"

# Delete user
php bin/console.php user:delete <userId>
```
