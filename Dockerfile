# syntax=docker/dockerfile:1

# =============================================================================
# Stage 1: Build dependencies with Composer
# =============================================================================
FROM php:8.4-cli-alpine AS builder

# Install build dependencies for PHP extensions
RUN apk add --no-cache \
    git \
    unzip \
    icu-dev \
    libzip-dev \
    freetype-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    curl-dev \
    oniguruma-dev

# Install PHP extensions needed for Composer and the app
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        intl \
        gd \
        zip \
        opcache \
        mbstring \
        curl

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /build

# Copy composer files first for better layer caching
COPY composer.json composer.lock ./

# Install production dependencies only
RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --optimize-autoloader \
    --no-scripts

# Copy the rest of the application
COPY . .

# Run post-install scripts if needed
RUN composer dump-autoload --optimize --no-dev

# =============================================================================
# Stage 2: Production image with Apache
# =============================================================================
FROM php:8.4-apache AS production

# Set build arguments and labels
ARG BUILD_DATE
ARG VCS_REF
ARG VERSION=latest

LABEL org.opencontainers.image.title="Movary" \
      org.opencontainers.image.description="Self-hosted movie tracking web application" \
      org.opencontainers.image.url="https://github.com/leepeuker/movary" \
      org.opencontainers.image.source="https://github.com/leepeuker/movary" \
      org.opencontainers.image.version="${VERSION}" \
      org.opencontainers.image.created="${BUILD_DATE}" \
      org.opencontainers.image.revision="${VCS_REF}"

# Install runtime dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    libicu72 \
    libzip4 \
    libfreetype6 \
    libjpeg62-turbo \
    libpng16-16 \
    libcurl4 \
    libonig5 \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
    libicu-dev \
    libzip-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libcurl4-openssl-dev \
    libonig-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        intl \
        gd \
        zip \
        opcache \
        mbstring \
        curl \
    && apt-get purge -y --auto-remove \
        libicu-dev \
        libzip-dev \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libcurl4-openssl-dev \
        libonig-dev \
    && rm -rf /var/lib/apt/lists/*

# Configure Apache
ENV APACHE_DOCUMENT_ROOT=/app/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Enable Apache modules
RUN a2enmod rewrite headers

# Configure Apache for .htaccess and security
RUN { \
    echo '<Directory /app/public>'; \
    echo '    Options -Indexes +FollowSymLinks'; \
    echo '    AllowOverride All'; \
    echo '    Require all granted'; \
    echo '</Directory>'; \
} > /etc/apache2/conf-available/movary.conf \
    && a2enconf movary

# Configure PHP for production
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Configure OPcache for production
RUN { \
    echo 'opcache.enable=1'; \
    echo 'opcache.memory_consumption=128'; \
    echo 'opcache.interned_strings_buffer=8'; \
    echo 'opcache.max_accelerated_files=10000'; \
    echo 'opcache.revalidate_freq=0'; \
    echo 'opcache.validate_timestamps=0'; \
    echo 'opcache.save_comments=1'; \
    echo 'opcache.fast_shutdown=1'; \
} > /usr/local/etc/php/conf.d/opcache-recommended.ini

# Configure PHP settings
RUN { \
    echo 'upload_max_filesize=20M'; \
    echo 'post_max_size=25M'; \
    echo 'memory_limit=256M'; \
    echo 'max_execution_time=120'; \
    echo 'expose_php=Off'; \
} > /usr/local/etc/php/conf.d/movary.ini

# Set working directory
WORKDIR /app

# Copy application from builder stage
COPY --from=builder /build /app

# Create writable directories and set permissions
RUN mkdir -p /app/storage/logs /app/storage/app/public /app/storage/profile-images \
    && chown -R www-data:www-data /app/storage \
    && chmod -R 775 /app/storage

# Copy entrypoint script
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Expose port 80
EXPOSE 80

# Health check
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD curl -f http://localhost/ -o /dev/null -s -w "%{http_code}" | grep -q "200\|302" || exit 1

# Set entrypoint
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["apache2-foreground"]
