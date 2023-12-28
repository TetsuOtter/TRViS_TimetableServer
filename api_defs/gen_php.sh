#!/bin/sh

cd $(dirname $0)/../

openapi-generator generate \
    -i api_defs/openapi.bundle.yml \
    -c api_defs/php-laravel.config.yaml \
    -g php-laravel \
    -o backend \
