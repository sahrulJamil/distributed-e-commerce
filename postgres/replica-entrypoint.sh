#!/bin/bash
set -e

if [ ! -s "$PGDATA/PG_VERSION" ]; then
    echo "Mengambil initial data dari product primary..."

    export PGPASSWORD=replica_password

    until gosu postgres pg_basebackup \
        -h postgres-product-primary \
        -p 5432 \
        -U replicator \
        -D "$PGDATA" \
        -Fp \
        -Xs \
        -P \
        -R; do

        echo "Primary belum siap, mencoba lagi dalam 3 detik..."
        sleep 3
    done
fi

exec /usr/local/bin/docker-entrypoint.sh postgres -c hot_standby=on