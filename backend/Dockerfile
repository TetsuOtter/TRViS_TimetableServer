# --- 依存解決ステージ -------------------------------------------------------
# compose は ./backend を read-only bind し /var/www/html に被せるが、
# /var/www/html/vendor だけは名前付きボリューム(webmon-php-vendor)で覆う。
# Docker は空の名前付きボリュームを初回マウント時にイメージ側の同パス内容で
# 初期化するため、イメージに vendor/ を焼き込んでおく必要がある。
# src/ はランタイムの bind が供給するので、ここでは composer.* だけで十分。
# PSR-4 オートローダは vendor/ からの相対で src/ を解決する（authoritative
# classmap を作ると src/ 不在で不完全になるため --optimize しない）。
# ext-pdo_mysql 等のプラットフォーム要件は最終ステージで担保されるため
# このステージでは無視する。
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
	--no-dev \
	--no-scripts \
	--no-interaction \
	--prefer-dist \
	--ignore-platform-reqs

# --- 実行ステージ -----------------------------------------------------------
FROM php:8.4-apache

ENV APACHE_DOCUMENT_ROOT='/var/www/html/public'
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf

RUN a2enmod rewrite

RUN docker-php-ext-install pdo_mysql

# C1 backstop: bound raw request body / memory below the app-level
# BodySizeLimitMiddleware (2 MiB). post_max_size sits just above the app cap so
# the middleware returns a clean 413 for normal oversize, while PHP hard-stops
# clients that omit/falsify Content-Length before the worker can OOM.
RUN { \
		echo 'post_max_size = 4M'; \
		echo 'upload_max_filesize = 4M'; \
		echo 'memory_limit = 128M'; \
	} > "$PHP_INI_DIR/conf.d/zz-trvis-limits.ini"

# H4: pre-create the rate-limiter cache dir owned by www-data so the first
# request under www-data does not race to create it (sys_get_temp_dir() is /tmp).
RUN mkdir -p /tmp/trvis-ratelimiter && chown -R www-data:www-data /tmp/trvis-ratelimiter

WORKDIR /var/www/html

# 焼き込んだ vendor/ が webmon-php-vendor ボリュームの初期内容になる。
# composer.json/lock 変更後は `docker compose down -v` でボリュームを作り直すこと。
COPY --from=vendor /app/vendor /var/www/html/vendor

EXPOSE 80
