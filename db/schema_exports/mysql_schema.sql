
/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_letterboxd_diary` (
  `diary_id` varchar(255) NOT NULL,
  `letterboxd_id` varchar(4) NOT NULL,
  PRIMARY KEY (`diary_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_tmdb_languages` (
  `iso_639_1` char(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `english_name` varchar(256) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`iso_639_1`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_trakt_user_movie_rating` (
  `trakt_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `rating` tinyint unsigned DEFAULT NULL,
  `rated_at` timestamp NOT NULL,
  PRIMARY KEY (`trakt_id`),
  KEY `cache_trakt_user_movie_rating_fk_user_id` (`user_id`),
  CONSTRAINT `cache_trakt_user_movie_rating_fk_user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_trakt_user_movie_watched` (
  `trakt_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `last_updated_at` datetime NOT NULL,
  UNIQUE KEY `uniqueTraktId` (`trakt_id`),
  KEY `cache_trakt_user_movie_watched_fk_user_id` (`user_id`),
  CONSTRAINT `cache_trakt_user_movie_watched_fk_user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `company` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(256) COLLATE utf8mb4_unicode_ci NOT NULL,
  `origin_country` char(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tmdb_id` int unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tmdb_id` (`tmdb_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `country` (
  `iso_3166_1` char(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `english_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`iso_3166_1`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_send_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `recipient_email` varchar(255) COLLATE utf8mb3_unicode_ci NOT NULL,
  `email_type` varchar(50) COLLATE utf8mb3_unicode_ci NOT NULL COMMENT 'Type: welcome, password_reset, etc.',
  `sender_user_id` int DEFAULT NULL COMMENT 'Admin who triggered the email (for welcome emails)',
  `sent_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `success` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Whether email was sent successfully',
  `error_message` text COLLATE utf8mb3_unicode_ci COMMENT 'Error message if send failed',
  PRIMARY KEY (`id`),
  KEY `idx_email_rate_limit` (`recipient_email`,`sent_at`),
  KEY `idx_email_type_time` (`email_type`,`sent_at`),
  KEY `idx_sender_time` (`sender_user_id`,`sent_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `genre` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(256) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tmdb_id` int unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `tmdb_id` (`tmdb_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_queue` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `job_type` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `job_status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int unsigned DEFAULT NULL,
  `parameters` text COLLATE utf8mb4_unicode_ci,
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `index_job_status` (`job_status`),
  KEY `index_job_type` (`job_type`),
  CONSTRAINT `job_queue_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `location` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned DEFAULT NULL,
  `name` text NOT NULL,
  `is_cinema` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `location_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `movie` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(256) COLLATE utf8mb4_unicode_ci NOT NULL,
  `trakt_id` int unsigned DEFAULT NULL,
  `imdb_id` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tmdb_id` int unsigned NOT NULL,
  `letterboxd_id` char(4) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `poster_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tagline` text COLLATE utf8mb4_unicode_ci,
  `overview` text COLLATE utf8mb4_unicode_ci,
  `original_language` varchar(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `runtime` smallint DEFAULT NULL,
  `release_date` date DEFAULT NULL,
  `tmdb_vote_average` decimal(3,1) DEFAULT NULL,
  `tmdb_vote_count` int unsigned DEFAULT NULL,
  `tmdb_poster_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tmdb_backdrop_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `imdb_rating_average` double(3,1) DEFAULT NULL,
  `imdb_rating_vote_count` int unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `updated_at_tmdb` datetime DEFAULT NULL,
  `updated_at_imdb` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tmdb_id` (`tmdb_id`),
  UNIQUE KEY `trakt_id` (`trakt_id`),
  UNIQUE KEY `imdb_id` (`imdb_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `movie_cast` (
  `person_id` int unsigned NOT NULL,
  `movie_id` int unsigned NOT NULL,
  `character_name` text COLLATE utf8mb4_unicode_ci,
  `position` smallint unsigned DEFAULT NULL,
  UNIQUE KEY `movie_id` (`movie_id`,`position`),
  KEY `person_id` (`person_id`),
  CONSTRAINT `movie_cast_ibfk_1` FOREIGN KEY (`person_id`) REFERENCES `person` (`id`),
  CONSTRAINT `movie_cast_ibfk_2` FOREIGN KEY (`movie_id`) REFERENCES `movie` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `movie_crew` (
  `person_id` int unsigned NOT NULL,
  `movie_id` int unsigned NOT NULL,
  `job` varchar(256) COLLATE utf8mb4_unicode_ci NOT NULL,
  `department` varchar(256) COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` smallint unsigned DEFAULT NULL,
  UNIQUE KEY `movie_id` (`movie_id`,`position`),
  KEY `person_id` (`person_id`),
  CONSTRAINT `movie_crew_ibfk_1` FOREIGN KEY (`person_id`) REFERENCES `person` (`id`),
  CONSTRAINT `movie_crew_ibfk_2` FOREIGN KEY (`movie_id`) REFERENCES `movie` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `movie_genre` (
  `genre_id` int unsigned NOT NULL,
  `movie_id` int unsigned NOT NULL,
  `position` smallint unsigned DEFAULT NULL,
  UNIQUE KEY `genre_id` (`genre_id`,`movie_id`),
  UNIQUE KEY `movie_id` (`movie_id`,`position`),
  CONSTRAINT `movie_genre_ibfk_1` FOREIGN KEY (`genre_id`) REFERENCES `genre` (`id`),
  CONSTRAINT `movie_genre_ibfk_2` FOREIGN KEY (`movie_id`) REFERENCES `movie` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `movie_production_company` (
  `company_id` int unsigned NOT NULL,
  `movie_id` int unsigned NOT NULL,
  `position` smallint unsigned DEFAULT NULL,
  UNIQUE KEY `company_id` (`company_id`,`movie_id`),
  UNIQUE KEY `movie_id` (`movie_id`,`position`),
  CONSTRAINT `movie_production_company_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `company` (`id`) ON DELETE CASCADE,
  CONSTRAINT `movie_production_company_ibfk_2` FOREIGN KEY (`movie_id`) REFERENCES `movie` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `movie_production_countries` (
  `movie_id` int unsigned NOT NULL,
  `iso_3166_1` char(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` tinyint NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`movie_id`,`iso_3166_1`),
  KEY `iso_3166_1` (`iso_3166_1`),
  CONSTRAINT `movie_production_countries_ibfk_1` FOREIGN KEY (`movie_id`) REFERENCES `movie` (`id`) ON DELETE CASCADE,
  CONSTRAINT `movie_production_countries_ibfk_2` FOREIGN KEY (`iso_3166_1`) REFERENCES `country` (`iso_3166_1`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `movie_user_rating` (
  `movie_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `rating` tinyint DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL,
  `rating_popcorn` tinyint unsigned DEFAULT NULL,
  `comment` text COLLATE utf8mb4_unicode_ci,
  `watched_year` smallint unsigned DEFAULT NULL,
  `watched_month` tinyint unsigned DEFAULT NULL,
  `watched_day` tinyint unsigned DEFAULT NULL,
  `location_id` int unsigned DEFAULT NULL,
  PRIMARY KEY (`movie_id`,`user_id`),
  KEY `user_id` (`user_id`),
  KEY `fk_movie_user_rating_location_id` (`location_id`),
  CONSTRAINT `fk_movie_user_rating_location_id` FOREIGN KEY (`location_id`) REFERENCES `location` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `movie_user_rating_ibfk_1` FOREIGN KEY (`movie_id`) REFERENCES `movie` (`id`) ON DELETE CASCADE,
  CONSTRAINT `movie_user_rating_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `movie_user_watch_dates` (
  `movie_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `watched_at` date DEFAULT NULL,
  `plays` smallint DEFAULT '1',
  `comment` text COLLATE utf8mb4_unicode_ci,
  `position` smallint NOT NULL DEFAULT '1',
  `location_id` int unsigned DEFAULT NULL,
  UNIQUE KEY `unique_watched_dates` (`movie_id`,`user_id`,`watched_at`),
  KEY `movie_history_fk_user_id` (`user_id`),
  KEY `fk_movie_user_watch_dates_location_id` (`location_id`),
  CONSTRAINT `fk_movie_user_watch_dates_location_id` FOREIGN KEY (`location_id`) REFERENCES `location` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `movie_history_fk_user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE,
  CONSTRAINT `movie_user_watch_dates_ibfk_1` FOREIGN KEY (`movie_id`) REFERENCES `movie` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `oauth_admin_banner_ack` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL COMMENT 'Admin user who acknowledged',
  `alert_level_acked` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Alert level at time of acknowledgement',
  `acked_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When banner was acknowledged',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_user_alert` (`user_id`,`alert_level_acked`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_acked_at` (`acked_at`),
  CONSTRAINT `fk_oauth_banner_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Admin acknowledgements of OAuth monitoring banners';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `oauth_email_config` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `provider` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'OAuth provider: gmail or microsoft',
  `client_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Public OAuth client identifier',
  `client_secret_encrypted` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Encrypted client secret (AES-256-CBC + base64)',
  `client_secret_iv` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Initialization vector for client_secret decryption',
  `tenant_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Microsoft only: Azure AD tenant ID',
  `refresh_token_encrypted` text COLLATE utf8mb4_unicode_ci COMMENT 'Encrypted refresh token (AES-256-CBC + base64)',
  `refresh_token_iv` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Initialization vector for refresh_token decryption',
  `from_address` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Email address to send from (must match OAuth account)',
  `scopes` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'OAuth scopes granted (space-separated)',
  `token_status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'not_connected' COMMENT 'Status: not_connected, active, expired, error',
  `token_error` text COLLATE utf8mb4_unicode_ci COMMENT 'Last error message from OAuth provider',
  `client_secret_expires_at` datetime DEFAULT NULL COMMENT 'When client secret expires (Microsoft only)',
  `connected_at` datetime DEFAULT NULL COMMENT 'When OAuth connection was established',
  `last_token_refresh_at` datetime DEFAULT NULL COMMENT 'Last time access token was refreshed',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_failure_at` datetime DEFAULT NULL COMMENT 'Timestamp of last token refresh failure',
  `last_error_code` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Last OAuth error code (sanitized)',
  `reauth_required` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Whether re-authorization is required',
  `alert_level` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ok' COMMENT 'Alert level: ok, warn, critical, expired',
  `next_notification_at` datetime DEFAULT NULL COMMENT 'Next scheduled notification time',
  PRIMARY KEY (`id`),
  KEY `idx_provider` (`provider`),
  KEY `idx_token_status` (`token_status`),
  KEY `idx_alert_level` (`alert_level`),
  KEY `idx_next_notification_at` (`next_notification_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='OAuth 2.0 email configuration for Gmail and Microsoft 365';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_setup_attempts` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `token_hash` varchar(64) COLLATE utf8mb3_unicode_ci NOT NULL COMMENT 'SHA-256 hash of invitation token',
  `attempted_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `success` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Whether password was successfully set',
  `ip_address` varchar(45) COLLATE utf8mb3_unicode_ci DEFAULT NULL COMMENT 'IP address of the attempt (IPv4 or IPv6)',
  PRIMARY KEY (`id`),
  KEY `idx_token_time` (`token_hash`,`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `person` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(256) COLLATE utf8mb4_unicode_ci NOT NULL,
  `gender` enum('0','1','2','3') COLLATE utf8mb4_unicode_ci NOT NULL,
  `known_for_department` varchar(256) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `poster_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `biography` text COLLATE utf8mb4_unicode_ci,
  `place_of_birth` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `death_date` date DEFAULT NULL,
  `tmdb_id` int unsigned DEFAULT NULL,
  `imdb_id` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tmdb_poster_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at_tmdb` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tmdb_id` (`tmdb_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `phinxlog` (
  `version` bigint NOT NULL,
  `migration_name` varchar(100) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `start_time` timestamp NULL DEFAULT NULL,
  `end_time` timestamp NULL DEFAULT NULL,
  `breakpoint` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `server_setting` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  UNIQUE KEY `key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `server_setting_metadata` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `updated_at` datetime NOT NULL,
  `updated_by_user_id` int unsigned DEFAULT NULL,
  PRIMARY KEY (`key`),
  KEY `updated_by_user_id` (`updated_by_user_id`),
  CONSTRAINT `server_setting_metadata_ibfk_1` FOREIGN KEY (`updated_by_user_id`) REFERENCES `user` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(256) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `totp_uri` char(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_admin` tinyint(1) DEFAULT '0',
  `dashboard_visible_rows` text COLLATE utf8mb4_unicode_ci,
  `dashboard_extended_rows` text COLLATE utf8mb4_unicode_ci,
  `dashboard_order_rows` text COLLATE utf8mb4_unicode_ci,
  `jellyfin_access_token` char(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `jellyfin_user_id` char(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `jellyfin_server_url` char(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `jellyfin_sync_enabled` tinyint(1) DEFAULT '0',
  `jellyfin_webhook_uuid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `emby_webhook_uuid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kodi_webhook_uuid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `jellyfin_scrobble_views` tinyint NOT NULL DEFAULT '1',
  `emby_scrobble_views` tinyint NOT NULL DEFAULT '1',
  `kodi_scrobble_views` tinyint NOT NULL DEFAULT '1',
  `privacy_level` tinyint unsigned DEFAULT '1',
  `date_format_id` tinyint unsigned DEFAULT '0',
  `plex_webhook_uuid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trakt_user_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trakt_client_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `plex_client_id` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `plex_client_temporary_code` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `plex_access_token` char(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `plex_account_id` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `plex_server_url` char(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `plex_scrobble_views` tinyint NOT NULL DEFAULT '1',
  `plex_scrobble_ratings` tinyint NOT NULL DEFAULT '0',
  `radarr_feed_uuid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `watchlist_automatic_removal_enabled` tinyint NOT NULL DEFAULT '1',
  `country` char(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `display_character_names` tinyint(1) DEFAULT '1',
  `locations_enabled` tinyint(1) DEFAULT '1',
  `display_tmdb_rating` tinyint NOT NULL DEFAULT '1',
  `display_imdb_rating` tinyint NOT NULL DEFAULT '1',
  `mastodon_enabled` tinyint NOT NULL DEFAULT '0',
  `mastodon_username` text COLLATE utf8mb4_unicode_ci,
  `mastodon_access_token` text COLLATE utf8mb4_unicode_ci,
  `mastodon_post_automatic` tinyint NOT NULL DEFAULT '1',
  `mastodon_post_visibility` enum('public','private','unlisted','direct') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'public',
  `core_account_changes_disabled` tinyint unsigned DEFAULT '0',
  `created_at` timestamp NOT NULL,
  `profile_image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_api_token` (
  `user_id` int unsigned NOT NULL,
  `token` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`token`),
  UNIQUE KEY `user_id` (`user_id`),
  CONSTRAINT `user_api_token_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_auth_token` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token_hash` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expiration_date` datetime NOT NULL,
  `created_at` timestamp NOT NULL,
  `last_used_at` datetime DEFAULT NULL,
  `device_name` varchar(256) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `user_auth_token_fk_user_id` (`user_id`),
  KEY `idx_token_hash` (`token_hash`),
  CONSTRAINT `user_auth_token_fk_user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_invitation` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `token_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token_hash`),
  UNIQUE KEY `token_hash` (`token_hash`),
  KEY `token_2` (`token_hash`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `user_invitation_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_jellyfin_cache` (
  `pathary_user_id` int unsigned NOT NULL AUTO_INCREMENT,
  `jellyfin_item_id` varchar(256) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tmdb_id` int unsigned NOT NULL,
  `watched` tinyint(1) NOT NULL,
  `last_watch_date` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`pathary_user_id`,`jellyfin_item_id`),
  CONSTRAINT `user_jellyfin_cache_ibfk_1` FOREIGN KEY (`pathary_user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_person_settings` (
  `user_id` int unsigned NOT NULL,
  `person_id` int unsigned NOT NULL,
  `is_hidden_in_top_lists` tinyint(1) DEFAULT '0',
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`user_id`,`person_id`),
  KEY `person_id` (`person_id`),
  CONSTRAINT `user_person_settings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_person_settings_ibfk_2` FOREIGN KEY (`person_id`) REFERENCES `person` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_recovery_codes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `code_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_recovery_codes_user_id` (`user_id`),
  CONSTRAINT `user_recovery_codes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_security_audit_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `event_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `metadata` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_security_audit_log_user_id` (`user_id`),
  KEY `idx_user_security_audit_log_event_type` (`event_type`),
  KEY `idx_user_security_audit_log_created_at` (`created_at`),
  CONSTRAINT `user_security_audit_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_trusted_devices` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `token_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `device_name` varchar(256) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL,
  `last_used_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token_hash` (`token_hash`),
  UNIQUE KEY `idx_user_trusted_devices_token_hash` (`token_hash`),
  KEY `idx_user_trusted_devices_user_id` (`user_id`),
  KEY `idx_user_trusted_devices_expires_at` (`expires_at`),
  CONSTRAINT `user_trusted_devices_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `watchlist` (
  `movie_id` int unsigned DEFAULT NULL,
  `user_id` int unsigned DEFAULT NULL,
  `added_at` timestamp NOT NULL,
  UNIQUE KEY `movie_id` (`movie_id`,`user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `watchlist_ibfk_1` FOREIGN KEY (`movie_id`) REFERENCES `movie` (`id`) ON DELETE CASCADE,
  CONSTRAINT `watchlist_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

