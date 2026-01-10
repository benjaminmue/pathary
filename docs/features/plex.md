!!! warning "Integration Under Development"
    This integration feature is currently being worked on and may not be fully functional. Please check back for updates.

## Webhook (Scrobbler)

### Description

Automatically add new [Plex](https://www.plex.tv/) movie plays and ratings to Pathary.

!!! Info

    To use the required webhooks feature in Plex an active [Plex Pass](https://www.plex.tv/plex-pass/) subscription is neceessary.

### Instruction
- Generate a webhook url in Pathary for your user on the Plex integration settings page (`/settings/integrations/plex`)
- Add the generated url as a [webhook to your Plex server](https://support.plex.tv/articles/115002267687-webhooks/) to start scrobbling

You can select what you want to scrobble (movie plays and/or ratings) via the "Scrobble Options" checkboxes on the settings page.

!!! tip

    Keep your webhook url private to prevent abuse.

## Authentication

Some features require access to protected personal Plex data.
You can authenticate Pathary against Plex on the Plex integration settings page (`/settings/integrations/plex`).

!!! Info

    Requires the server configuration [PLEX_IDENTIFIER](/configuration/#third-party-integrations) to be set.

During the authentication process a Plex access token is generated and stored in Pathary. 
This token will be used in all further Plex API requests.
When an authentication is removed from Pathary, the token will be deleted only in Pathary.

!!! Info

    Removing the authentication only deletes the token stored in Pathary itself. The token still exists in Plex.
    To invalidate the access token in Plex, go to your Plex settings at: Account -> Authorized devices -> Click on the red cross for the entry "Pathary"

## Watchlist import

### Description

Import missing movies from your Plex Watchlist to your Pathary Watchlist.
Missing movies imported to the Pathary Watchlist are put at the beginning of the list in the same order as they are in Plex.

!!! Info

    Plex [authentication](#authentication) is required.

### Instruction

#### Web UI
You can schedule import jobs and see the status/history of past jobs on the Plex integration settings page (`/settings/integrations/plex`).

#### Command
You can directly trigger an import via CLI

```shell
php bin/console.php plex:watchlist:import --userId=<id>
```

!!! tip

    You could create a cronjob to regularly import your watchlist to keep up to date automatically. 
