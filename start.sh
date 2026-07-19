#!/bin/bash

set -e

PROXY_PORT="${MEETING_PROXY_PORT:-8765}"

if [ -n "${MEETING_PROXY_HOST:-}" ]; then
	PROXY_HOST="${MEETING_PROXY_HOST}"
else
	# Pick a sensible default upstream based on where the meeting service is resolvable.
	for candidate in meeting-server unilis-meeting-media unilis-meeting host.docker.internal 127.0.0.1; do
		if [ "$candidate" = "127.0.0.1" ] || getent hosts "$candidate" >/dev/null 2>&1; then
			PROXY_HOST="$candidate"
			break
		fi
	done
	PROXY_HOST="${PROXY_HOST:-127.0.0.1}"
fi

echo "[unilis] Meeting proxy upstream: ${PROXY_HOST}:${PROXY_PORT}"

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