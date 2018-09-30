#!/usr/bin/env bash

if ! /usr/bin/sudo -n true 2>/dev/null; then
  /usr/bin/osascript -e 'do shell script "mkdir -p /var/db/sudo/$USER; touch /var/db/sudo/$USER" with administrator privileges' >/dev/null || exit 1
fi

/usr/bin/sudo $@
