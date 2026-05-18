#!/bin/sh

cd $(dirname $0)/../

openapi-generator generate \
	-i api_defs/openapi.bundle.yml \
	-c api_defs/ts-fetch.config.yaml \
	-g typescript-fetch \
	-o frontend/packages/trvis-api \
