#!/usr/bin/env node
/**
 * Patch Node-RED flows.json mqtt-broker config from IOT_MQTT_BROKER / IOT_MQTT_PORT.
 * Runs at container start so compose .env controls the broker (mosquitto vs hivemq).
 */
'use strict';

const fs = require('fs');

const flowsPath = process.env.FLOWS_PATH || '/data/flows.json';
const broker = process.env.IOT_MQTT_BROKER || 'mosquitto';
const port = String(process.env.IOT_MQTT_PORT || '1883');

if (!fs.existsSync(flowsPath)) {
  console.warn(`[node-red-patch-mqtt] skip: ${flowsPath} not found`);
  process.exit(0);
}

const flows = JSON.parse(fs.readFileSync(flowsPath, 'utf8'));
let patched = false;

for (const node of flows) {
  if (node.type === 'mqtt-broker' && node.name === 'iot-mqtt-broker') {
    node.broker = broker;
    node.port = port;
    patched = true;
    break;
  }
}

if (!patched) {
  console.warn('[node-red-patch-mqtt] iot-mqtt-broker config node not found');
  process.exit(0);
}

fs.writeFileSync(flowsPath, JSON.stringify(flows, null, 4));
console.log(`[node-red-patch-mqtt] broker=${broker} port=${port}`);
