# Two stages on purpose. `base` is what everyone runs; `test` adds the browser
# toolchain and is built only when the browser suite has to run. Folding the two
# together took the image from 209 MB to 1.4 GB, paid by every developer and
# every CI job to support one 30-second test.
#
#   docker compose build                     -> base, 209 MB
#   docker compose build --build-arg ...      (see docker-compose.yml)
#   docker build --target test .              -> test, with Chromium
FROM php:8.4-fpm-alpine AS base

RUN apk add --no-cache \
    git \
    unzip \
    libpq-dev \
    postgresql-client \
    icu-dev \
    oniguruma-dev \
    libxml2-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    linux-headers \
    bash \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    # ext-sockets backs pestphp/pest-plugin-browser: the plugin talks to the
    # Playwright server over a websocket. ext-pcntl comes with it — the plugin
    # sends the server a SIGTERM on teardown, and without pcntl the constant
    # does not exist and every browser run ends in a fatal error. Both are a few
    # hundred kilobytes, so they stay in base and only the browser moves out.
    && docker-php-ext-install \
        pdo \
        pdo_pgsql \
        pgsql \
        bcmath \
        intl \
        opcache \
        gd \
        sockets \
        pcntl \
        zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# The base image ships no php.ini, so PHP falls back to compiled-in defaults
# (upload_max_filesize=2M, post_max_size=8M). The spec allows 10 MB PNG uploads,
# and post_max_size must clear the whole multipart envelope, not just the file.
# The three limits stack so every oversized request stays handleable in PHP:
#  10M  < size <= 14M  PHP accepts the body, sets UPLOAD_ERR_INI_SIZE, and
#                      Laravel validation returns a readable 422
#  14M  < size <= 25M  Laravel's ValidatePostSize middleware throws
#                      PostTooLargeException as a 413, before routing and
#                      before CSRF verification
#        size >  25M   nginx rejects it with a bare 413
# Keeping nginx well above post_max_size is deliberate: it hands the widest
# possible band to Laravel, where the response can still be shaped.
RUN mv "${PHP_INI_DIR}/php.ini-production" "${PHP_INI_DIR}/php.ini"
COPY docker/app/php.ini "${PHP_INI_DIR}/conf.d/99-quiz.ini"

WORKDIR /var/www

ARG UID=1000
ARG GID=1000
RUN addgroup -g ${GID} -S app && adduser -u ${UID} -S app -G app \
    && mkdir -p /var/www/storage /var/www/bootstrap/cache \
    && chown -R app:app /var/www

USER app

EXPOSE 9000
CMD ["php-fpm"]

# The browser stage. Pest 4's browser plugin serves the application from inside
# this container and drives it through a Playwright server it starts itself, so
# node and a browser have to live here rather than on the host. Playwright's own
# Chromium download is a glibc build and will not run on musl; Alpine's package
# will, and tests/Pest.php links it into Playwright's registry.
FROM base AS test

USER root

RUN apk add --no-cache \
    nodejs \
    chromium \
    nss \
    freetype \
    harfbuzz \
    ttf-freefont

USER app
