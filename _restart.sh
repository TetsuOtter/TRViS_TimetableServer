#!/bin/sh

cd $(dirname $0)

if [ $# -eq 0 ]
  then
    echo "No arguments supplied" 1>&2
    exit 1
fi

SERVICE_NAME=$1

docker compose rm -fs $SERVICE_NAME

docker compose build $SERVICE_NAME

docker compose up -d $SERVICE_NAME
