#!/bin/sh
set -e

# Render provides $PORT (often 10000). If unset, default to 80 for local Docker.
PORT="${PORT:-80}"

# Point Apache at the requested port.
sed -i "s/^Listen 80\$/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

exec "$@"
