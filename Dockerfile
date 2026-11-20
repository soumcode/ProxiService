# Build the PHP dependencies on 8.4+, required by the locked Symfony 8 packages.
FROM php:8.4-cli-bookworm AS composer-deps

# Install PHP extension build dependencies and the extensions Laravel uses.
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        $PHPIZE_DEPS \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libonig-dev \
        libpng-dev \
        libwebp-dev \
        libxml2-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        exif \
        gd \
        intl \
        mbstring \
        opcache \
        pcntl \
        pdo_mysql \
        xml \
        zip \
    && rm -rf /var/lib/apt/lists/*

# Use the official Composer executable.
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Set the application directory for dependency installation.
WORKDIR /var/www/html

# Copy the application source without host secrets or installed dependencies.
COPY . .

# Install only production dependencies and create an optimized autoloader.
RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --no-progress \
    --optimize-autoloader

# Build frontend assets after PHP is available for the Wayfinder Vite plugin.
FROM composer-deps AS frontend

# Reuse the official Node.js 22 runtime and npm CLI in this build-only stage.
COPY --from=node:22-bookworm /usr/local/bin/node /usr/local/bin/node
COPY --from=node:22-bookworm /usr/local/lib/node_modules/npm /usr/local/lib/node_modules/npm

# Expose npm commands while keeping Node.js out of the production image.
RUN ln -sf /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -sf /usr/local/lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx

# Install exactly the locked frontend dependencies and compile the Vite bundle.
RUN npm ci && npm run build

# Create the lightweight runtime image with the same PHP version, FPM, and Nginx.
FROM php:8.4-fpm-bookworm AS app

# Install runtime libraries, Nginx, Supervisor, and the health-check client.
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        libfreetype6 \
        libicu72 \
        libjpeg62-turbo \
        libonig5 \
        libpng16-16 \
        libwebp7 \
        libxml2 \
        libzip4 \
        nginx \
        supervisor \
    && rm -rf /var/lib/apt/lists/*

# Copy the PHP extensions compiled in the matching PHP 8.4 CLI build stage.
COPY --from=composer-deps /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=composer-deps /usr/local/etc/php/conf.d/docker-php-ext-*.ini /usr/local/etc/php/conf.d/

# Configure production PHP behavior and expose container environment variables to FPM.
COPY docker/php/production.ini /usr/local/etc/php/conf.d/zz-production.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-app.conf

# Remove the default Nginx site and install the Laravel public-document-root config.
RUN rm -f /etc/nginx/sites-enabled/default
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf

# Configure Supervisor to keep Nginx and PHP-FPM in the foreground.
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Copy application code and Composer dependencies with runtime-readable ownership.
WORKDIR /var/www/html
COPY --from=composer-deps --chown=www-data:www-data /var/www/html/ /var/www/html/

# Include only the compiled Vite assets from the Node build stage.
COPY --from=frontend --chown=www-data:www-data /var/www/html/public/build/ /var/www/html/public/build/

# Install the startup script and mark it executable.
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod 0755 /usr/local/bin/entrypoint.sh \
    && mkdir -p /var/www/html/storage/app/private \
        /var/www/html/storage/app/public \
        /var/www/html/storage/framework/cache/data \
        /var/www/html/storage/framework/sessions \
        /var/www/html/storage/framework/views \
        /var/www/html/storage/logs \
        /var/www/html/bootstrap/cache \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Publish only the HTTP port inside the Docker network.
EXPOSE 80

# Check Laravel's built-in health endpoint from inside the container.
HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD curl --fail --silent --header "X-Forwarded-Proto: https" http://127.0.0.1/up || exit 1

# Run initialization and then replace the shell with the selected service command.
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisor/supervisord.conf"]


# ============================================================
# Dockerfile pour ProxiService (Laravel + Vite/Wayfinder)
# ============================================================
FROM php:8.3-cli

# --- Outils système de base ---
RUN apt-get update && apt-get install -y git unzip curl \
    && rm -rf /var/lib/apt/lists/*

# --- Extensions PHP courantes pour Laravel (MySQL, Postgres, etc.) ---
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_mysql pdo_pgsql zip bcmath intl

# --- Composer (gestionnaire de paquets PHP) ---
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# --- Node.js (nécessaire pour Vite) ---
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app
COPY . .

# --- Dépendances PHP ---
RUN composer install --no-dev --optimize-autoloader --no-interaction

# --- Build du frontend ---
# Un .env temporaire est requis pour que "php artisan" démarre pendant le build.
# Il est supprimé ensuite : les vraies variables viennent de Render.
RUN cp .env.example .env && php artisan key:generate \
    && npm ci \
    && npm run build \
    && rm -rf node_modules .env

# --- Permissions Laravel ---
RUN chmod -R 775 storage bootstrap/cache

# --- Démarrage : migrations puis serveur ---
# Render fournit la variable $PORT automatiquement
EXPOSE 8000
CMD php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=${PORT:-8000}