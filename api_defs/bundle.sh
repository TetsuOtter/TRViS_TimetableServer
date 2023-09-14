#!/bin/sh

cd $(dirname $0)

redocly bundle api_root.yaml -o openapi.bundle.yml
