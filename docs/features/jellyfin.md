!!! warning "Integration Under Development"
    This integration feature is currently being worked on and may not be fully functional. Please check back for updates.

## Webhook (Scrobbler)

### Description

Automatically add new [Jellyin](https://jellyfin.org/) movie plays to Pathary.

!!! Info

    Requires the [webhook plugin](https://github.com/jellyfin/jellyfin-plugin-webhook) to be installed and active in Jellyfin.

### Instruction

- Generate a webhook url in Pathary for your user on the Jellyfin integration settings page (`/settings/integrations/jellyfin`)
- Add the generated url in the Jellyfin webhook plugin as a `Generic Destination` and activate only:
    - Notification Type => "Playback Stop"
    - User Filter => Choose your user
    - Item Type => "Movies" + "Send All Properties (ignores template)"

!!! tip

    Keep your webhook url private to prevent abuse.

## Authentication

Some features require access to protected personal Jellyfin data.
You can authenticate Pathary against Jellyfin on the Jellyfin integration settings page (`/settings/integrations/jellyfin`).

!!! Info

    Requires the server configuration [JELLYFIN_DEVICE_ID](/configuration/#third-party-integrations) to be set.

During the authentication process a Jellyfin access token is generated and stored in Pathary.
This token will be used in all further Jellyfin API requests.
When an authentication is removed from Pathary, the token will be deleted in Pathary and the Jellyfin server.

## Sync

General notes:

- Movies are matched via tmdb id. Movies without a tmdb id in Jellyfin are ignored.
- Movies will be updated in all Jellyfin libraries.
- Backup your Jellyfin database regularly in case something goes wrong!

### Automatic sync

You can keep your Jellyfin libraries automatically up to date with your latest Pathary watch history changes.

!!! Info

    Jellyfin [authentication](#authentication) is required.  

If the automatic sync is enabled (e.g. on `/settings/integrations/jellyfin`) new watch dates added to Pathary are automatically pushed as plays to Jellyfin.
If a movie has its last watch date removed in Pathary it is set to unwatched in Jellyfin.

### Export

#### Description

You can export your Pathary watch dates as plays to Jellyfin.

!!! Info

    Jellyfin [authentication](#authentication) is required.  

Pathary will compare its movie watch dates against the Jellyfin movie plays.
Movies not marked as watched in Jellyfin but with watch dates in Pathary are marked as watched and get the latest watch date set as the last play date.
Movies already marked as watched are updated with the latest watch date as the last play date if the dates are not the same.

#### CLI command

```shell
php bin/console.php jellyfin:export <userId>
```

### Import

#### Description

You can import your Jellyfin plays as Pathary watch dates.

!!! Info

    Jellyfin [authentication](#authentication) is required.  

Pathary will compare the Jellyfin movie plays against its movie watch dates.
Movies with multiple plays in Jellyfin are handled as one watch date, using the date of the latest play.
Watch dates are added to Pathary if they are missing, existing watch dates are not changed. 

#### CLI command

```shell
php bin/console.php jellyfin:import <userId>
```

