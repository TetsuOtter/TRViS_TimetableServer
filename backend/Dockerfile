FROM php:8.2-apache

ENV APACHE_DOCUMENT_ROOT='/var/www/html/public'
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf

RUN a2enmod rewrite

# 依存パッケージのビルド/実行に必要なシステムライブラリと PHP 拡張。
# 必要拡張の正準ソースは .github/workflows/backend-tests.yml の
#   extensions: mbstring, intl, pdo_mysql, bcmath
# （pdo_mysql は従来からインストール済み。CI とランタイムでパリティを取る）
RUN apt-get update \
  && apt-get install -y --no-install-recommends \
    git \
    unzip \
    libonig-dev \
    libicu-dev \
  && docker-php-ext-install pdo_mysql mbstring intl bcmath \
  && apt-get clean \
  && rm -rf /var/lib/apt/lists/*

# Composer 本体は公式イメージからバイナリだけ取り込む
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# 依存解決はソース全体ではなく lock ファイルだけで行い、レイヤキャッシュを効かせる。
# アプリ本体 (lib/ src/) は compose の bind mount で供給されるため、ここでは
# 非最適化の PSR-4 オートローダで十分（実行時にマウント済みソースを解決する）。
# --no-dev: この php コンテナは API 配信専用。テスト系ツールは CI / ホストで実行。
COPY composer.json composer.lock ./
RUN composer install \
  --no-interaction \
  --no-progress \
  --prefer-dist \
  --no-dev \
  --no-scripts

EXPOSE 80
