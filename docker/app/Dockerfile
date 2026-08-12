FROM php:8.4-fpm-alpine

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
    linux-headers \
    bash \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo \
        pdo_pgsql \
        pgsql \
        bcmath \
        intl \
        opcache \
        gd

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# The base image ships no php.ini, so PHP falls back to compiled-in defaults
# (upload_max_filesize=2M, post_max_size=8M). The spec allows 8 MB PNG uploads,
# and post_max_size must clear the whole multipart envelope, not just the file.
# The three limits stack so every oversized request stays handleable in PHP:
#   8M  < size <= 12M  PHP accepts the body, sets UPLOAD_ERR_INI_SIZE, and
#                      Laravel validation returns a readable 422
#  12M  < size <= 25M  Laravel's ValidatePostSize middleware throws
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
