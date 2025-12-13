# Initial User Setup

How to create the first user account for a fresh Pathary installation.

## Prerequisites

- Pathary is running (Docker or local)
- Database migrations have been executed
- You have access to either the web interface or CLI

## Method 1: Web UI (Recommended)

On a fresh install with no users, Pathary automatically redirects to the registration page.

### Steps

1. Open `http://localhost:8080/` in your browser
2. You'll be redirected to `/create-user`
3. Fill in the form:
   - **Email**: `admin@example.com`
   - **Username**: `admin` (letters and numbers only)
   - **Password**: minimum 8 characters
   - **Repeat Password**: same as above
4. Click **Create**

The first user is automatically granted admin privileges.

### Why this works

When no users exist:
- The `ServerHasNoUsers` middleware redirects all visitors to `/create-user`
- The `ServerHasUsers` middleware allows access to the registration form
- `CreateUserController` sets `isAdmin = true` for the first user

## Method 2: CLI Command

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
| `password` | Yes | Minimum 8 characters |
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
| `Password must contain at least 8 characters` | Password too short |
| `Name must only consist of numbers and letters` | Invalid characters in username |
| `Name already in use` | Username already taken |

## Method 3: Direct Database Insert (Last Resort)

Only use this if Methods 1 and 2 fail.

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

### "403 Forbidden" on /create-user

**Cause**: Users already exist in database, and `ENABLE_REGISTRATION=0` (default).

**Solutions**:
- Use CLI method instead
- Set `ENABLE_REGISTRATION=1` environment variable (then disable after)
- Use admin panel at `/settings/users` if you have an admin account

### "404 Not Found" on /create-user

**Cause**: URL rewriting not configured.

**Solution**: Ensure Apache `.htaccess` is present in `public/` directory and `mod_rewrite` is enabled.

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
- [ ] Admin settings accessible at `/settings/users` (if admin)

## User Management Commands

```bash
# List all users
php bin/console.php user:list

# Update user
php bin/console.php user:update <userId> --email="new@email.com"

# Delete user
php bin/console.php user:delete <userId>
```
