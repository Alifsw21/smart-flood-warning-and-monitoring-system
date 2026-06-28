#!/bin/sh
set -eu

node /opt/node-red-patch-mqtt.js

exec npm start --cache /data/.npm -- --userDir /data
