#!/bin/sh
# Runs once, on the first initialisation of the Postgres data volume.
# The test suite runs against a real Postgres, so it needs its own database
# next to the development one.
set -e

psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<-EOSQL
    CREATE DATABASE ${POSTGRES_DB}_testing OWNER $POSTGRES_USER;
EOSQL
