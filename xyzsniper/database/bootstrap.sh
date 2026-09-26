#!/bin/sh
set -eu

schema_file=${XYZSNIPER_SCHEMA:-/Users/fahra/.local/share/xyzsniper/schema.sql}
docker_bin=${DOCKER_BIN:-/usr/local/bin/docker}
db_container=${XYZSNIPER_DB_CONTAINER:-shopdash-mariadb}
db_image=${XYZSNIPER_DB_IMAGE:-mariadb:10.4}
db_volume=${XYZSNIPER_DB_VOLUME:-xyzsniper_mariadb_data}
db_port=${XYZSNIPER_DB_PORT:-3308}

if [ ! -x "$docker_bin" ]; then
  echo "Docker tidak ditemukan di $docker_bin" >&2
  exit 1
fi

if ! "$docker_bin" inspect "$db_container" >/dev/null 2>&1; then
  "$docker_bin" volume create "$db_volume" >/dev/null
  "$docker_bin" run -d \
    --name "$db_container" \
    --restart unless-stopped \
    -e MARIADB_ALLOW_EMPTY_ROOT_PASSWORD=1 \
    -p "127.0.0.1:${db_port}:3306" \
    -v "${db_volume}:/var/lib/mysql" \
    "$db_image" >/dev/null
fi

container_state=$("$docker_bin" inspect -f '{{.State.Status}}' "$db_container")
if [ "$container_state" != "running" ]; then
  "$docker_bin" start "$db_container" >/dev/null
fi

ready=0
for attempt in $(seq 1 30); do
  if "$docker_bin" exec "$db_container" mariadb-admin ping -uroot --silent >/dev/null 2>&1; then
    ready=1
    break
  fi
  sleep 1
done

if [ "$ready" -ne 1 ]; then
  echo "MariaDB container $db_container belum siap." >&2
  exit 1
fi

"$docker_bin" exec -i "$db_container" mariadb -uroot < "$schema_file"
