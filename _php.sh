#!/bin/sh

cd $(dirname $0)/backend

php -S localhost:8888 -t public
