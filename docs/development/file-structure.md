### Backend

Most of the backend files are in the `/src` directory. The main `index.php` is in `/public`.

Within the `/src` directory, various other directories are located.

- `/src/Api` - Contains usage of external APIs (such as Trakt, Plex, Jellyfin, etc.)
- `/src/Command` - Contains Pathary CLI commands  (which can be executed via `php /bin/console.php`)
- `/src/HttpController` - Contains files processing HTTP requests. Controllers and middleware are located here, for both the HTTP requests directed at the API and just the usual
  web interface.
- `/src/Service` - Contains core Pathary components, such as the routing system, the processing of external API data and some other stuff. Note: The `/src/api` directory is mainly
  meant for *connecting* with the external APIs and sending HTTP requests, whereas the external API stuff in `/src/Service` mainly handles how to process the data from those
  external APIs.
- `/src/Util` - Contains basic utility files to keep consistency throughout the project
- `/src/Domain` - Contains the core Pathary domain implementation, e.g. what is a movie in Pathary (required Pathary and TMDB IDs) or a watch date (required user and Pathary IDs) and
  how to create or delete those

### Templates

The frontend of Pathary is mainly regular HTML5, but it also uses the [twig framework](https://twig.symfony.com/) from Symfony, to use dynamic templates. This allows for
cleaner code when injecting PHP code in HTML. So instead of using `<?php echo $variable ?>`, you can use `{{ $variable }}` and the twig engine will automatically translate the
clean code into the PHP code. See their documentation for more information.

### Frontend assets

The CSS, Javascript and the images are located in `/public`.
