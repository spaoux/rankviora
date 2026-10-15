#!/bin/sh
set -eu
cd "$(dirname "$0")/.."
if [ ! -f .env ]; then
    umask 077
    printf 'DB_PASSWORD=%s\n' "$(od -An -N32 -tx1 /dev/urandom | tr -d ' \n')" > .env
    echo 'Local database secret generated.'
else
    echo 'Existing environment preserved.'
fi
