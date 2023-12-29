#!/bin/sh

cd $(dirname $0)/../

openapi-generator generate \
    -i api_defs/openapi.bundle.yml \
    -c api_defs/php-slim4.config.yaml \
    -g php-slim4 \
    -o backend \
