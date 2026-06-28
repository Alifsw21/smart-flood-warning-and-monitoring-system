#include <WiFi.h>
#include <ArduinoJson.h>
#include <PubSubClient.h>
#include "secrets.h"

const char* ssid = "Wokwi-GUEST";
const bool IS_DEPLOYED = false;

const char* mqtt_server = IS_DEPLOYED ? SECRET_MQTT_SERVER_PUB : "broker.hivemq.com";
const int mqtt_port = 1883;
const char* mqtt_user = SECRET_MQTT_USER_1;
const char* mqtt_pass = SECRET_MQTT_PASS_1;
const char* DEVICE_ID = "Sensor-banjir1";

unsigned long lastMsg = 0;
unsigned long lastReconnectAttempt = 0;

#define TRIG_PIN 21
#define ECHO_PIN 19
#define POT_PIN 34

WiFiClient espClient;
PubSubClient client(espClient);

void publishSensorData() {
  if (!client.connected()) return;

  digitalWrite(TRIG_PIN, LOW);
  delayMicroseconds(2);
  digitalWrite(TRIG_PIN, HIGH);
  delayMicroseconds(10);
  digitalWrite(TRIG_PIN, LOW);

  long duration = pulseIn(ECHO_PIN, HIGH);
  float distance_m = (duration * 0.034/2) / 100.0;

  float waterLevel_m = 5.0 - distance_m;
  if (waterLevel_m < 0) {
    waterLevel_m = 0;
  }

  int raw_pot = analogRead(POT_PIN);
  float soilMoisture_pct = (raw_pot / 4095.0) * 100.0;

  StaticJsonDocument<300> doc;
  doc["idNode"] = 1;
  doc["tinggiAir"] = waterLevel_m;
  doc["kelembapanTanah"] = soilMoisture_pct;

  char payload[300];
  serializeJson(doc, payload);

  if (client.publish("kelompok2/sensors/sungai", payload, true)) {
    Serial.println("Data terkirim: " + String(payload));
  } else {
    Serial.println("Gagal kirim data");
  }
}

void reconnect() {
  unsigned long now = millis();

  if (now - lastReconnectAttempt > 5000) {
    lastReconnectAttempt = now;
    Serial.print("Mencoba koneksi MQTT...");

    bool success = IS_DEPLOYED
                   ? client.connect(DEVICE_ID, mqtt_user, mqtt_pass)
                   : client.connect(DEVICE_ID);

    if (success) {
      Serial.println("TERHUBUNG!");
      lastReconnectAttempt = 0;
    } else {
      Serial.print("gagal, reconnect=");
      Serial.print(client.state());
      Serial.println(" coba lagi dalam 5 detik");
    }
  }
}

void setup() {
  Serial.begin(115200);

  pinMode(TRIG_PIN, OUTPUT);
  pinMode(ECHO_PIN, INPUT);
  pinMode(POT_PIN, INPUT);

  WiFi.begin(ssid, "");
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }
  Serial.println(("\nWiFi Terhubung"));

  client.setServer(mqtt_server, mqtt_port);
}

void loop() {
  if (!client.connected()) {
    reconnect();
  } else {
    client.loop();
  }

  unsigned long now = millis();
  if (now - lastMsg > 5000) {
    lastMsg = now;
    publishSensorData();
  }
}
