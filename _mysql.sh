#!/bin/sh

. ./mysql/.env

DOCKER_EXEC_FLAGS=""
# if stdin is a terminal, then also make it interactive
# ref: https://hydrocul.github.io/wiki/blog/2016/1012-is-pipe-or-terminal.html
if [ -t 0 ]; then
  DOCKER_EXEC_FLAGS+="-it"
fi

docker exec $DOCKER_EXEC_FLAGS webmon-db mysql -u$MYSQL_USER -p$MYSQL_PASSWORD -D$MYSQL_DATABASE
