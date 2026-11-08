# Encryption Key

Pathary encrypts the OAuth client secret, the OAuth refresh token and a stored
SMTP password before writing them to the database. This page covers where the
key comes from, how to move it out of the database and how to rotate it.

## Format

`ENCRYPTION_KEY` must be 32 random bytes, encoded as base64 or hex. Both
commands produce a valid key:

```bash
openssl rand -base64 32
openssl rand -hex 32
```

Since this release new values are encrypted with AES-256-GCM and stored with a
`v2:` prefix. GCM authenticates the data, so a modified ciphertext is rejected
instead of decrypting to garbage. Values written by earlier releases
(AES-256-CBC without a MAC) are still read and are replaced by the new format
the next time they are saved, e.g. when OAuth is reconnected or the SMTP
password is saved again.

## Key source

| Source | When | Status |
|---|---|---|
| `ENCRYPTION_KEY` environment variable | Set on the container | Recommended |
| `server_setting.encryptionKey` row | Created by Admin → Email Settings → OAuth → "Generate Encryption Key" when no environment key exists | Legacy fallback, logs a warning on use |

A key stored in the database sits next to the data it protects: anybody with a
database dump can decrypt the secrets. "Generate Encryption Key" refuses to run
once any key exists, because replacing the key would make every stored secret
unreadable.

`php bin/console.php selftest` reports which source is in use.

## Move a database key to ENCRYPTION_KEY

This keeps all encrypted data valid; the key itself does not change.

1. Read the stored key (prints the key, do this on the server only):
   ```bash
   docker exec -it <mysql-container> mysql -u <user> -p <database> \
     -e "SELECT value FROM server_setting WHERE \`key\` = 'encryptionKey';"
   ```
2. Put the value into `ENCRYPTION_KEY` of the Pathary container (Unraid: edit
   the container template, add the variable) and restart the container.
3. Run `docker exec -u www-data <container> php bin/console.php selftest` and
   check that the key source is `environment` and the round-trip passes.
4. Test sending an email (Admin → Email Settings → Test Email).
5. Delete the database copy:
   ```sql
   DELETE FROM server_setting WHERE `key` = 'encryptionKey';
   ```

## Rotate the key

There is no re-encryption tool: secrets encrypted with the old key cannot be
read with the new one, so they are entered again. Rotate after a suspected
leak of the key or a database dump, when a person with access leaves, and
otherwise every one to two years.

1. Write down the OAuth settings (provider, client ID, tenant ID) and have the
   OAuth client secret and, if used, the SMTP password at hand.
2. Generate a new key: `openssl rand -base64 32`.
3. Replace `ENCRYPTION_KEY` on the container and restart it. If a key is also
   stored in the database, delete it (`DELETE FROM server_setting WHERE
   \`key\` = 'encryptionKey';`), otherwise it is no longer used anyway.
4. Admin → Email Settings → OAuth: enter the client secret again, save, then
   "Connect" to obtain a new refresh token.
5. If basic SMTP authentication is used, enter the SMTP password again.
6. Run `selftest` and send a test email.
7. After a suspected leak, also rotate the OAuth client secret at the provider
   (Google Cloud / Microsoft Entra) and the database password: the old key can
   still decrypt old backups.
