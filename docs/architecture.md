# Arsitektur — Smart Flood Warning System

> Ekspor dokumen ini ke PNG/PDF untuk deliverable spesifikasi §12 butir #3 (diagram arsitektur + diagram urutan).

Dokumen ini melengkapi [`README.md`](../README.md). Label teknis (nama service, path API, routing key RabbitMQ) sengaja tetap dalam bahasa Inggris karena itu nama resmi di kode dan container.

---

## Gambaran sistem (overview)

```mermaid
flowchart TB
  subgraph IoT["Lapisan IoT"]
    SIM[iot-simulator / Wokwi ESP32]
    MQTT[(Mosquitto MQTT :1883)]
    NR[Node-RED :1880]
  end

  subgraph Gateway["Lapisan Gateway"]
    GW[express-gateway :3000]
    OAUTH[oauth-server :3002]
  end

  subgraph Services["Layanan PHP (MVC)"]
    USER[php-user :8000]
    RIVER[php-river :8002]
    ANALYTICS[php-analytics :8003]
  end

  subgraph ML["Lapisan Machine Learning"]
    MLAPI[python-ml-service :5001]
    MLAIR[consumer air.new]
    MLBANJIR[consumer traffic.new]
  end

  subgraph Data["Data & Messaging"]
    MYSQL[(MySQL kelompok2 :3307)]
    RMQ[(RabbitMQ city.events)]
    REDIS[(Redis :6379)]
  end

  SIM --> MQTT
  MQTT --> NR
  NR --> GW
  GW --> OAUTH
  GW --> USER
  GW --> RIVER
  GW --> ANALYTICS
  GW --> MLAPI
  RIVER --> MYSQL
  USER --> MYSQL
  ANALYTICS --> MYSQL
  RIVER --> RMQ
  USER --> RMQ
  RMQ --> MLAIR
  RMQ --> MLBANJIR
  MLBANJIR --> RMQ
  RMQ --> ANALYTICS
  RMQ --> USER
  MLAPI --> MYSQL
  GW --> REDIS
```

**Keterangan:** Port yang ditampilkan adalah **port host** saat menjalankan `docker compose up` di mesin lokal. Lalu lintas eksternal masuk melalui **API Gateway (:3000)**.

---

## S1 — Ingesti data IoT

Skenario: sensor/simulator → MQTT → Node-RED → Gateway → php-river → RabbitMQ → konsumer ML.

```mermaid
sequenceDiagram
  participant S as Sensor / Simulator
  participant M as Mosquitto
  participant N as Node-RED
  participant G as API Gateway
  participant R as php-river
  participant Q as RabbitMQ
  participant L as python-ml-consumer

  S->>M: publish kelompok2/sensors/*
  M->>N: subscribe MQTT
  N->>G: POST /iot/traffic (OAuth client_credentials)
  G->>R: POST /api/traffic/readings
  R->>R: INSERT river_sensorReading
  R->>Q: publish traffic.new / air.new
  Q->>L: konsumsi & prediksi ML
```

**Verifikasi otomatis:** `bash iot/tests/s1-e2e.sh`

---

## S2 — Login warga & pengajuan laporan

Skenario: OAuth password grant → token → submit laporan → event RabbitMQ → notifikasi warga.

```mermaid
sequenceDiagram
  participant C as Aplikasi Warga
  participant G as API Gateway
  participant O as oauth-server
  participant U as php-user
  participant Q as RabbitMQ
  participant W as php-user-notification-consumer

  C->>G: POST /oauth/token (password grant)
  G->>O: teruskan request
  O-->>C: access_token
  C->>G: POST /api/reports + Bearer token
  G->>U: POST /api/laporan
  U->>Q: publish report.submitted
  Q->>W: konsumsi event
  W->>U: INSERT user_notifications
  C->>G: GET /api/notifications
```

**Verifikasi otomatis:** `bash express-gateway/tests/s2-report-e2e.sh`

---

## S6 — Alert anomali ke warga

Skenario: ML mendeteksi risiko banjir → publish `anomaly.alert` → peringatan analytics + notifikasi citizen (fan-out queue).

```mermaid
sequenceDiagram
  participant L as python-ml-consumer-banjir
  participant Q as RabbitMQ
  participant A as php-analytics-consumer
  participant W as php-user-notification-consumer
  participant C as Warga

  L->>Q: publish anomaly.alert
  Q->>A: antrian anomaly.alert
  Q->>W: antrian citizen.anomaly.alert
  A->>A: INSERT analytics_peringatan
  W->>W: INSERT user_notifications (semua warga)
  C->>C: GET /api/notifications
```

---

## Pemetaan domain (Tugas Besar → Proyek Banjir)

| Layanan spesifikasi | Folder / service | Tabel database | Catatan |
|---------------------|------------------|--------------|---------|
| Citizen Service | `php-user` | `user_*` | Warga, laporan, notifikasi, riwayat banjir |
| Traffic Service | `php-river` | `river_*` | Zona, sungai, node sensor, pembacaan |
| Environment Service | `php-analytics` | `analytics_*` | Peringatan waspada / bencana |
| OAuth Server | `oauth-server` | `auth_*` | Client & token OAuth |
| API Gateway | `express-gateway` | — | Routing, JWT, rate limit |
| Traffic Predictor (ML) | `python-ml-service` | — | Model: prediksi curah hujan |
| Air Quality Classifier (ML) | `python-ml-service` | — | Model: deteksi banjir berdasarkan cuaca |
| Anomaly Detector (ML) | `python-ml-service` | — | Model: Isolation Forest (`deteksi_anomali.pkl`) |

---

## Topologi RabbitMQ (ringkas)

Exchange: **`city.events`** (topic)

| Routing key | Antrian | Publisher | Consumer |
|-------------|---------|-----------|----------|
| `traffic.new` | `traffic.new` | php-river | python-ml-consumer-banjir |
| `air.new` | `air.new` | php-river | python-ml-consumer-air |
| `anomaly.alert` | `anomaly.alert` | ML consumer banjir | php-analytics-consumer |
| `anomaly.alert` | `citizen.anomaly.alert` | (fan-out) | php-user-notification-consumer |
| `report.submitted` | `report.submitted` | php-user | php-user-notification-consumer |

---

## Cara mengekspor ke PNG/PDF

1. Buka file ini di VS Code / Cursor dengan preview Mermaid, atau tempel diagram ke [mermaid.live](https://mermaid.live).
2. Ekspor setiap diagram sebagai PNG.
3. Susun di satu halaman (overview + minimal S1 dan S2) untuk lampiran laporan atau slide presentasi.

Alternatif: gunakan plugin Mermaid di draw.io / FigJam jika tim sudah punya template visual.
