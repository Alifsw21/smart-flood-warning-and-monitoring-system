#!/bin/sh
set -eu

NR_USER="${NODE_RED_USER:-node-red}"

run_patch() {
  node /opt/node-red-patch-mqtt.js
}

start_node_red() {
  exec npm start --cache /data/.npm -- --userDir /data
}

# Host-mounted /data is often owned by the SSH user; patch needs write access.
if [ "$(id -u)" = "0" ]; then
  chown -R "${NR_USER}:${NR_USER}" /data 2>/dev/null || chown -R 1000:1000 /data
  chmod u+w /data/flows.json 2>/dev/null || true
  run_patch
  exec su -s /bin/sh "${NR_USER}" -c 'npm start --cache /data/.npm -- --userDir /data'
fi

chmod u+w /data/flows.json 2>/dev/null || true
run_patch
start_node_red
