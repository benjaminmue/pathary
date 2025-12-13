## Introduction

It is recommended to host Pathary with the [official Docker image](https://github.com/benjaminkomen/pathary/pkgs/container/pathary) from GitHub Container Registry.

!!! warning

    After the **initial installation** and every update containing database changes the database migrations must be executed:

    `php bin/console.php database:migration:migrate`

    Missing database migrations can cause critical errors!

!!! info

    The docker images automatically runs the missing database migrations on start up.
    To stop this behavior set the environment variable `DATABASE_DISABLE_AUTO_MIGRATION=1`

## Image tags

- `latest` Default image. Latest stable version and **recommended** for the average user
- `main` Latest build from main branch
- `vX.Y.Z` There is a tag for every individual version
- `sha-XXXXXXX` Specific commit builds

## Storage permissions

The `/app/storage` directory is used to store all created files (e.g. logs and images).
It should be persisted outside the container and Pathary needs read/write access to it.

The easiest way to do this are managed docker volumes (used in the examples below).

!!! info

    If you bind a local mount, make sure the directory exists before you start the container
    and that it has the necessary permissions/ownership.

## Docker secrets

Docker secrets can be used for all environment variables, just append `_FILE` to the environment variable name.
Secrets are used as a fallback for not existing environment variables.
Make sure to not set the environment variable without the `_FILE` suffix if you want to use a secret.

For more info on Docker secrets, read the [official Docker documentation](https://docs.docker.com/engine/swarm/secrets/).

## Examples

All examples include the environment variable `TMDB_API_KEY` (get a key [here](https://www.themoviedb.org/settings/api)).
It is not strictly required to be set here but recommend.
Many features of the application will not work correctly without it.

### With SQLite

This is the easiest setup and especially recommend for beginners

```shell
$ docker volume create pathary-storage
$ docker run --rm -d \
  --name pathary \
  -p 80:80 \
  -e TMDB_API_KEY="<tmdb_key>" \
  -e DATABASE_MODE="sqlite" \
  -v pathary-storage:/app/storage \
  ghcr.io/benjaminkomen/pathary:latest
```

### With MySQL

```shell
$ docker volume create pathary-storage
$ docker run --rm -d \
  --name pathary \
  -p 80:80 \
  -e TMDB_API_KEY="<tmdb_key>" \
  -e DATABASE_MODE="mysql" \
  -e DATABASE_MYSQL_HOST="<host>" \
  -e DATABASE_MYSQL_NAME="<db_name>" \
  -e DATABASE_MYSQL_USER="<db_user>" \
  -e DATABASE_MYSQL_PASSWORD="<db_password>" \
  -v pathary-storage:/app/storage \
  ghcr.io/benjaminkomen/pathary:latest
```

### docker-compose.yml with MySQL

```yaml
services:
  pathary:
    image: ghcr.io/benjaminkomen/pathary:latest
    container_name: pathary
    ports:
      - "80:80"
    environment:
      TMDB_API_KEY: "<tmdb_key>"
      DATABASE_MODE: "mysql"
      DATABASE_MYSQL_HOST: "mysql"
      DATABASE_MYSQL_NAME: "pathary"
      DATABASE_MYSQL_USER: "pathary_user"
      DATABASE_MYSQL_PASSWORD: "pathary_password"
    volumes:
      - pathary-storage:/app/storage

  mysql:
    image: mysql:8.0
    environment:
      MYSQL_DATABASE: "pathary"
      MYSQL_USER: "pathary_user"
      MYSQL_PASSWORD: "pathary_password"
      MYSQL_ROOT_PASSWORD: "<mysql_root_password>"
    volumes:
      - pathary-db:/var/lib/mysql

volumes:
  pathary-db:
  pathary-storage:
```

### docker-compose.yml with MySQL and secrets

```yaml
services:
  pathary:
    image: ghcr.io/benjaminkomen/pathary:latest
    container_name: pathary
    ports:
      - "80:80"
    environment:
      TMDB_API_KEY_FILE: /run/secrets/tmdb_key
      DATABASE_MODE: "mysql"
      DATABASE_MYSQL_HOST: "mysql"
      DATABASE_MYSQL_NAME: "pathary"
      DATABASE_MYSQL_USER: "pathary_user"
      DATABASE_MYSQL_PASSWORD_FILE: /run/secrets/mysql_password
    volumes:
      - pathary-storage:/app/storage
    secrets:
      - tmdb_key
      - mysql_password

  mysql:
    image: mysql:8.0
    environment:
      MYSQL_DATABASE: "pathary"
      MYSQL_USER: "pathary_user"
      MYSQL_PASSWORD_FILE: /run/secrets/mysql_password
      MYSQL_ROOT_PASSWORD_FILE: /run/secrets/mysql_root_password
    volumes:
      - pathary-db:/var/lib/mysql
    secrets:
      - mysql_root_password
      - mysql_password

secrets:
  mysql_root_password:
    file: /path/to/docker/secret/mysql_root_password
  mysql_password:
    file: /path/to/docker/secret/mysql_password
  tmdb_key:
    file: /path/to/docker/secret/tmdb_key

volumes:
  pathary-db:
  pathary-storage:
```
