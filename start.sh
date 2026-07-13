#!/bin/bash

set -e

PROXY_HOST="${MEETING_PROXY_HOST:-meeting-server}"
PROXY_PORT="${MEETING_PROXY_PORT:-8765}"

if [ -f /etc/apache2/sites-available/000-default.conf.template ]; then
	sed \
		-e "s/__MEETING_PROXY_HOST__/${PROXY_HOST}/g" \
		-e "s/__MEETING_PROXY_PORT__/${PROXY_PORT}/g" \
		/etc/apache2/sites-available/000-default.conf.template \
		> /etc/apache2/sites-available/000-default.conf
fi

# Start Postfix
service postfix start

# Start Apache
apache2-foreground