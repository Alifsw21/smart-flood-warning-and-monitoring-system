#include <WiFi.h>
#include <PubSubClient.h>
#include <DHT.h>
#include <ArduinoJson.h>

const char* ssid = "Wokwi-GUEST";
const char* mqtt_server = "broker.hivemq.com";
const int mqtt_port = 1883;
const char* DEVICE_ID = "Sensor-banjir-cuaca2";

#define POTENTIO_PIN 34 

DHT dht(4, DHT22);
WiFiClient espClient;
PubSubClient client(espClient);

unsigned long lastMsg = 0;

void publishSensorData() {
  float t_avg = dht.readTemperature();
  float rh_avg = dht.readHumidity();

  int raw_pot = analogRead(POTENTIO_PIN);
  float rainfall = (raw_pot / 4095.0) * 50.0; 

  if (isnan(t_avg) || isnan(rh_avg)) {
    Serial.println("Gagal membaca DHT22");
    return;
  }

  float tn = t_avg - 2.0;
  float tx = t_avg + 3.0;
  float ss = 5.0 + (random(-10, 11) / 10.0);
  float ff_x = 5.0 + (random(-10, 11) / 10.0);
  float ddd_x = 193.5 + (random(-200, 201) / 10.0);
  float ff_avg = 2.3 + (random(-10, 11) / 10.0);

  StaticJsonDocument<1024> doc; 
  doc["idNode"] = 2;
  doc["curahHujan"] = rainfall;
  doc["suhuMin"] = tn;
  doc["suhuMax"] = tx;
  doc["suhuRataRata"] = t_avg;
  doc["kelembapanUdara"] = rh_avg;
  doc["sunShine"] = ss;
  doc["kecepatanAngin"] = ff_x;
  doc["arahAngin"] = ddd_x;
  doc["kecepatanRataRataAngin"] = ff_avg;

  char payload[1024];
  serializeJson(doc, payload);

  client.publish("kelompok2/sensors/cuaca", payload, true);
  Serial.println("Node 2 Mengirim: " + String(payload));
}

void reconnect() {
  while (!client.connected()) {
    Serial.print("Mencoba koneksi MQTT...");
    if (client.connect(DEVICE_ID)) {
      Serial.println("TERHUBUNG!");
    } else {
      Serial.print("gagal, reconnect=");
      Serial.print(client.state());
      Serial.println(" coba lagi dalam 5 detik");
      delay(5000);
    }
  }
}

void setup() {
  Serial.begin(115200);
  
  pinMode(POTENTIO_PIN, INPUT);
  dht.begin();

  WiFi.begin(ssid, "");
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }
  Serial.println("\nWiFi Terhubung!");

  client.setServer(mqtt_server, mqtt_port);
}

void loop() {
  if (!client.connected()) {
    reconnect();
  }
  client.loop();

  unsigned long now = millis();
  if (now - lastMsg > 5000) {
    lastMsg = now;
    publishSensorData();
  }
}