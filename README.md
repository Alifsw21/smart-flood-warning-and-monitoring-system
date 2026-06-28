# Smart Flood Warning & Monitoring System

Sistem peringatan dini banjir berbasis **microservice** untuk mata kuliah *Pembangunan Perangkat Lunak Orientasi Berbasis Service* (Tugas Besar Orientasi Service).

**Kelompok:** kelompok2  
**Domain:** adaptasi Smart City → monitoring banjir & sensor sungai  
**Database:** satu skema MySQL `kelompok2` (prefix tabel per layanan, bukan multi-database)

**Dosen pengampu:** Muhammad Panji Muslim, S.Pd., M.Kom

**Anggota kelompok:**

| Nama | NIM |
|------|-----|
| Alif Satriya Wicaksono | 2410511002 |
| Khairul Insan | 2410511018 |
| Praffi Ramadhani | 2410511028 |
| Rakha Aqilatha Farid | 2410511030 |
| Aliifah Sava Fikrana | 2410511038 |
| Yusuf Abdulhakiem | 2410511048 |

---

## Daftar isi

1. [Ringkasan arsitektur](#1-ringkasan-arsitektur)
2. [Persyaratan](#2-persyaratan)
3. [Panduan cepat — setup < 15 menit](#3-panduan-cepat--setup--15-menit)
4. [Struktur repositori](#4-struktur-repositori)
5. [Peta layanan & port](#5-peta-layanan--port)
6. [Kredensial demo](#6-kredensial-demo)
7. [Variabel lingkungan](#7-variabel-lingkungan)
8. [Database](#8-database)
9. [Autentikasi OAuth 2.0 & JWT](#9-autentikasi-oauth-20--jwt)
10. [API Gateway & endpoint utama](#10-api-gateway--endpoint-utama)
11. [Message broker (RabbitMQ)](#11-message-broker-rabbitmq)
12. [Lapisan IoT (MQTT + Node-RED)](#12-lapisan-iot-mqtt--node-red)
13. [Layanan Machine Learning](#13-layanan-machine-learning)
14. [Monitoring (Prometheus + Grafana)](#14-monitoring-prometheus--grafana)
15. [Kubernetes](#15-kubernetes)
16. [Skenario demo end-to-end (S1–S6)](#16-skenario-demo-end-to-end-s1s6)
17. [Pengujian otomatis](#17-pengujian-otomatis)
18. [Postman](#18-postman)
19. [Pemecahan masalah](#19-pemecahan-masalah)
20. [Checklist deliverables](#20-checklist-deliverables)

---

## 1. Ringkasan arsitektur

Alur data utama:

```
Sensor IoT / Simulator
  → Mosquitto MQTT
  → Node-RED (transformasi)
  → API Gateway (Express)
  → PHP Service (simpan MySQL + publish RabbitMQ)
  → Python ML (konsumsi & prediksi)
  → Dashboard / notifikasi warga
```

**Pemetaan domain Tugas Besar → proyek ini:**


| Spesifikasi (Smart City) | Layanan kami                | Folder              |
| ------------------------ | --------------------------- | ------------------- |
| Citizen Service          | Warga, laporan, notifikasi  | `php-user`          |
| Traffic Service          | Data sungai & sensor        | `php-river`         |
| Environment Service      | Peringatan banjir           | `php-analytics`     |
| OAuth Server             | Token JWT / OAuth 2.0       | `oauth-server`      |
| API Gateway              | Routing, rate limit, health | `express-gateway`   |
| Python ML                | 3 model + deteksi anomali   | `python-ml-service` |
| IoT Layer                | MQTT, Node-RED, simulator   | `iot/`              |


---

## 2. Persyaratan


| Alat           | Versi minimum | Keterangan                               |
| -------------- | ------------- | ---------------------------------------- |
| Docker         | 24+           | Wajib untuk menjalankan stack lengkap    |
| Docker Compose | v2            | Perintah `docker compose`                |
| Git            | —             | Clone repositori                         |
| RAM            | 8 GB          | Disarankan untuk 15+ container sekaligus |
| Node.js        | 18+           | Opsional — uji skrip gateway             |
| Python         | 3.11+         | Opsional — training model lokal          |
| kubectl        | —             | Opsional — deploy Kubernetes             |


**Port host yang dipakai (server lab kelompok 2):** `3530`, `3532`, `5012`, `5674`, `8150`, `8151`, `8154`, `1890`, `1886`, `3352`, `6382`, `9092`, `3014`, `8082`, `15674`.

---

## 3. Panduan cepat — setup < 15 menit

Ikuti langkah berikut **berurutan**. Estimasi total: **10–15 menit** (tergantung kecepatan unduh image Docker).

### Langkah 1 — Clone & masuk folder (1 menit)

```bash
git clone https://github.com/Alifsw21/smart-flood-warning-and-monitoring-system.git
cd smart-flood-warning-and-monitoring-system
```

### Langkah 2 — Salin konfigurasi lingkungan (1 menit)

```bash
cp .env.example .env
```

File `.env` sudah berisi nilai dev default. **Jangan commit file `.env`** ke Git.

### Langkah 3 — Jalankan seluruh stack (5–10 menit)

```bash
make compose-up
# setara dengan: docker compose up -d --build
```

Untuk pengembangan dengan bind-mount source PHP/ML (hot reload):

```bash
make compose-dev
# setara dengan: docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d --build
```

Build pertama kali akan:

- Mengunduh image dasar (MySQL, RabbitMQ, dll.)
- Melatih model ML di dalam image `python-ml-service`
- Menginisialisasi database dari `database/schema.sql` + `database/seed.sql`

### Langkah 4 — Tunggu layanan sehat (2 menit)

```bash
docker compose ps
```

Pastikan status `running` / `healthy` untuk minimal: `mysql`, `rabbitmq`, `oauth-server`, `express-gateway`, `php-user`, `php-river`, `php-analytics`, `python-ml-service`.

Cek health gateway:

```bash
curl -s http://localhost:3530/health | python3 -m json.tool
```

Respons sukses: `"status": "success"` dan semua upstream `"status": "up"`.

### Langkah 5 — Dapatkan token OAuth (1 menit)

```bash
curl -s -X POST http://localhost:3530/oauth/token \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "grant_type=password" \
  -d "username=hadiputra2" \
  -d "password=kelompok2dev" \
  -d "client_id=citizen-app" \
  -d "client_secret=CitizenSecretDev123"
```

Salin nilai `access_token` dari respons JSON.

### Langkah 6 — Uji API terproteksi (1 menit)

```bash
export TOKEN="<access_token_dari_langkah_5>"

curl -s http://localhost:3530/api/notifications \
  -H "Authorization: Bearer $TOKEN" | python3 -m json.tool

curl -s http://localhost:3530/api/environment/alerts \
  -H "Authorization: Bearer $TOKEN" | python3 -m json.tool
```

### Langkah 7 — (Opsional) Uji skenario otomatis (2 menit)

```bash
./express-gateway/tests/smoke.sh
./express-gateway/tests/s2-report-e2e.sh
```

Jika semua langkah di atas berhasil, setup selesai dalam target **< 15 menit**.

---

## 4. Struktur repositori

```
smart-flood-warning-and-monitoring-system/
├── express-gateway/       # API Gateway (Per. 7, 10)
├── oauth-server/          # OAuth 2.0 + JWT (Per. 5, 6)
├── php-user/              # Citizen service — warga & laporan (Per. 3, 4)
├── php-river/             # Traffic/river — sensor sungai (Per. 3, 4)
├── php-analytics/         # Environment — peringatan banjir (Per. 3, 4)
├── python-ml-service/     # FastAPI + ML + RabbitMQ consumer (Per. 9, 11)
├── iot/                   # Mosquitto, Node-RED, simulator, firmware Wokwi (§4.6)
├── database/              # schema.sql, seed.sql, generateSeed.py
├── k8s/                   # Manifest Kubernetes (Per. 13)
├── monitoring/            # Prometheus + Grafana
├── postman/               # Koleksi API
├── docker-compose.yml     # Orkestrasi lokal (Per. 12)
├── docker-compose.dev.yml # Override development
├── Makefile               # Shortcut perintah umum
└── .env.example           # Template variabel lingkungan
```

---

## 5. Peta layanan & port


| Layanan               | Container                 | Port host               | Fungsi                                        |
| --------------------- | ------------------------- | ----------------------- | --------------------------------------------- |
| **express-gateway**   | `smartcity-gateway`       | **3530**                | Pintu masuk tunggal API                       |
| **oauth-server**      | `smartcity-oauth`         | **3532**                | `/oauth/token`, introspect, revoke            |
| **php-user**          | `smartcity-php-user`      | **8150**                | Warga, laporan, notifikasi, riwayat banjir    |
| **php-river**         | `smartcity-php-river`     | **8151**                | Zona, sungai, node sensor, pembacaan          |
| **php-analytics**     | `smartcity-php-analytics` | **8154**                | Peringatan (`analytics_peringatan`)           |
| **python-ml-service** | `smartcity-python-ml`     | **5012**                | Prediksi ML (FastAPI)                         |
| **MySQL**             | `smartcity-mysql`         | **3352**                | Database `kelompok2`                          |
| **RabbitMQ**          | `smartcity-rabbitmq`      | **5674** / UI **15674** | Message broker                                |
| **Redis**             | `smartcity-redis`         | **6382**                | Rate limiting gateway                         |
| **Mosquitto**         | `smartcity-mosquitto`     | **1886**                | Broker MQTT (host); internal `mosquitto:1883` |
| **Node-RED**          | `smartcity-node-red`      | **1890**                | Bridge MQTT → REST                            |
| **iot-simulator**     | `smartcity-iot-simulator` | —                       | Publish sensor periodik                       |
| **Prometheus**        | `smartcity-prometheus`    | **9092**                | Metrik                                        |
| **Grafana**           | `smartcity-grafana`       | **3014**                | Dashboard                                     |
| **cAdvisor**          | `smartcity-cadvisor`      | **8082**                | Metrik container                              |


> Akses API eksternal selalu melalui **Gateway :3530**, kecuali OAuth langsung (:3532) atau UI monitoring.

**Worker (tanpa port publik):**


| Worker                           | Fungsi                                                          |
| -------------------------------- | --------------------------------------------------------------- |
| `php-user-notification-consumer` | `report.submitted` + `citizen.anomaly.alert` → notifikasi warga |
| `php-analytics-consumer`         | `anomaly.alert` → tabel peringatan                              |
| `python-ml-consumer-air`         | Konsumsi `air.new`                                              |
| `python-ml-consumer-banjir`      | Konsumsi `traffic.new` → publish alert                          |


---

## 6. Kredensial demo

### Pengguna aplikasi (OAuth password grant)


| Field    | Nilai          |
| -------- | -------------- |
| Username | `hadiputra2`   |
| Password | `kelompok2dev` |
| Role     | `user` (warga) |


Akun admin contoh: `dinasari1` (role `admin`) — **password OAuth belum di-set di seed** (hanya `hadiputra2` yang di-`UPDATE` ke `kelompok2dev`). Untuk demo login OAuth, gunakan `**hadiputra2`** / `kelompok2dev`.

### OAuth clients (terdaftar di `database/seed.sql`)


| Client ID     | Client Secret         | Grant types                 |
| ------------- | --------------------- | --------------------------- |
| `gateway`     | `GatewaySecretDev123` | `client_credentials`        |
| `citizen-app` | `CitizenSecretDev123` | `password`, `refresh_token` |


### Layanan lain


| Layanan     | User        | Password                    |
| ----------- | ----------- | --------------------------- |
| RabbitMQ UI | `smartcity` | `RabbitSecret`              |
| Grafana     | `admin`     | `admin`                     |
| MySQL root  | `root`      | `RootSecret` (lihat `.env`) |


---

## 7. Variabel lingkungan

Salin `.env.example` → `.env`. Variabel penting:


| Variabel                              | Default                                | Keterangan                                                            |
| ------------------------------------- | -------------------------------------- | --------------------------------------------------------------------- |
| `MYSQL_ROOT_PASSWORD`                 | `RootSecret`                           | Password root MySQL                                                   |
| `MYSQL_DATABASE`                      | `kelompok2`                            | Nama database                                                         |
| `JWT_SECRET`                          | `dev-jwt-secret-change-me`             | Verifikasi JWT di gateway                                             |
| `OAUTH_CLIENT_ID`                     | `gateway`                              | Client gateway untuk introspect                                       |
| `OAUTH_CLIENT_SECRET`                 | `GatewaySecretDev123`                  | **Wajib diisi** agar gateway bisa introspect token                    |
| `RABBITMQ_USER` / `RABBITMQ_PASSWORD` | `smartcity` / `RabbitSecret`           | Koneksi RabbitMQ                                                      |
| `MQTT_TOPIC_PREFIX`                   | `kelompok2/sensors`                    | Prefix topik MQTT kelompok 2                                          |
| `OAUTH_LOGIN_URL`                     | `http://localhost:3532/api/auth/login` | OAuth dari host (sesuai port host OAuth di `docker-compose.yml`)      |
| `IOT_MQTT_BROKER` / `IOT_MQTT_PORT`   | `mosquitto` / `1883`                   | Broker MQTT untuk Node-RED (ganti ke `broker.hivemq.com` untuk Wokwi) |


Setiap sub-layanan juga punya `.env.example` sendiri untuk pengembangan di luar Docker.

---

## 8. Database

### Prinsip (Best Practice §8)

- **Satu database** `kelompok2` untuk seluruh microservice
- **Prefix tabel** per layanan: `user_`*, `river_*`, `analytics_*`, `auth_*`
- **User MySQL terpisah** per layanan dengan `GRANT` terbatas (lihat `database/schema.sql`)

### Inisialisasi otomatis (install baru)

Saat volume MySQL masih kosong, Docker menjalankan:

1. `database/schema.sql` — DDL + user/grant
2. `database/seed.sql` — data dummy (5 zona, 50 warga, 200 pembacaan sensor, 20 laporan, dll.)

### Upgrade database lama

Jika volume MySQL sudah ada dari versi sebelumnya:

```bash
make migrate
# menjalankan database/migrate-spec-gap.sql
```

### Regenerasi seed

```bash
make seed   # menjalankan database/generateSeed.py
```

### Tabel utama


| Prefix       | Contoh tabel                                                             | Layanan       |
| ------------ | ------------------------------------------------------------------------ | ------------- |
| `user_`      | `user_user`, `user_laporan`, `user_notifications`, `user_riwayatBanjir`  | php-user      |
| `river_`     | `river_zones`, `river_sungai`, `river_sensorNode`, `river_sensorReading` | php-river     |
| `analytics_` | `analytics_peringatan`                                                   | php-analytics |
| `auth_`      | `auth_oauthClient`, `auth_oauthToken`                                    | oauth-server  |


---

## 9. Autentikasi OAuth 2.0 & JWT

**oauth-server** (host **:3532**, internal container `:3531`) mengimplementasikan:


| Grant type           | Endpoint            | Kegunaan                       |
| -------------------- | ------------------- | ------------------------------ |
| `password`           | `POST /oauth/token` | Login warga                    |
| `client_credentials` | `POST /oauth/token` | Komunikasi antar layanan / IoT |
| `refresh_token`      | `POST /oauth/token` | Perpanjang sesi                |


Endpoint tambahan: `POST /oauth/introspect`, `POST /oauth/revoke`.

Endpoint kenyamanan (juga via gateway `:3530`):


| Method | Path                | Body                          | Keterangan                             |
| ------ | ------------------- | ----------------------------- | -------------------------------------- |
| POST   | `/api/auth/login`   | `username`, `password` (JSON) | Wrapper password grant (`citizen-app`) |
| GET    | `/api/auth/profile` | — (Bearer)                    | Profil user dari token                 |


**express-gateway** memverifikasi setiap request terproteksi via introspect OAuth (atau JWT lokal), meneruskan header `x-user-id` dan `x-user-role` ke PHP service.

### Contoh: login warga

```bash
curl -s -X POST http://localhost:3530/oauth/token \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "grant_type=password" \
  -d "username=hadiputra2" \
  -d "password=kelompok2dev" \
  -d "client_id=citizen-app" \
  -d "client_secret=CitizenSecretDev123"
```

### Contoh: refresh token

```bash
curl -s -X POST http://localhost:3530/oauth/token \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "grant_type=refresh_token" \
  -d "client_id=citizen-app" \
  -d "client_secret=CitizenSecretDev123" \
  -d "refresh_token=<refresh_token>"
```

---

## 10. API Gateway & endpoint utama

Base URL: `http://localhost:3530`

### Rate limiting


| Limiter        | Batas                  | Catatan                                   |
| -------------- | ---------------------- | ----------------------------------------- |
| Global         | 100 request / 15 menit | Tidak berlaku untuk `/health`, `/metrics` |
| Terautentikasi | 500 request / jam      | Per token Bearer atau IP                  |
| Backend        | Redis `:6382`          | Fallback in-memory jika Redis down        |


Respons `429`: `"Too many requests"`.

### Publik (tanpa Bearer token)


| Method | Path                | Deskripsi                                            |
| ------ | ------------------- | ---------------------------------------------------- |
| GET    | `/health`           | Status agregat semua upstream                        |
| GET    | `/metrics`          | Metrik Prometheus                                    |
| POST   | `/oauth/token`      | Issue token                                          |
| POST   | `/oauth/introspect` | Validasi token (butuh `client_id` + `client_secret`) |
| POST   | `/oauth/revoke`     | Cabut token (butuh `client_id` + `client_secret`)    |
| POST   | `/api/auth/login`   | Login JSON → token (wrapper password grant)          |


### Terproteksi (Bearer token wajib)


| Method                | Path                            | Layanan       | Deskripsi                                |
| --------------------- | ------------------------------- | ------------- | ---------------------------------------- |
| GET                   | `/api/auth/profile`             | oauth-server  | Profil user                              |
| GET/POST/PATCH/DELETE | `/api/citizens/*`               | php-user      | Data warga (alias `/api/users`)          |
| GET/POST/PATCH/DELETE | `/api/reports/*`                | php-user      | Laporan warga (alias `/api/laporan`)     |
| PATCH                 | `/api/reports/:id/status`       | php-user      | Update status laporan (admin)            |
| GET                   | `/api/notifications`            | php-user      | Notifikasi warga                         |
| GET                   | `/api/flood-history`            | php-user      | Riwayat banjir                           |
| GET/POST/PUT/DELETE   | `/api/river/*`                  | php-river     | Zona, sungai, sensor, pembacaan          |
| GET/POST              | `/api/traffic/*`                | php-river     | Pembacaan & status banjir (alias)        |
| GET/POST              | `/api/environment/*`            | php-river     | Pembacaan lingkungan                     |
| GET                   | `/api/environment/alerts`       | php-analytics | Peringatan aktif (waspada/bencana)       |
| GET/DELETE            | `/api/analytics/*`              | php-analytics | Riwayat peringatan + filter              |
| POST                  | `/predict/banjir`               | python-ml     | Prediksi banjir (gabungan 2 model)       |
| POST                  | `/predict/curah-hujan`          | python-ml     | Prediksi curah hujan                     |
| POST                  | `/predict/traffic`              | python-ml     | Alias → `/predict/banjir`                |
| POST                  | `/predict/air-quality`          | python-ml     | Alias → `/predict/curah-hujan`           |
| GET                   | `/predict/realtime/banjir`      | python-ml     | Snapshot prediksi banjir (Redis)         |
| GET                   | `/predict/realtime/curah-hujan` | python-ml     | Snapshot curah hujan (Redis)             |
| POST                  | `/detect/anomaly`               | python-ml     | Deteksi anomali sensor                   |
| POST                  | `/predict/batch`                | python-ml     | Prediksi batch                           |
| GET                   | `/model/feature-importance`     | python-ml     | Bobot fitur model                        |
| GET                   | `/api/sensor`                   | python-ml     | Data sensor terakhir (Redis)             |
| POST                  | `/iot/traffic`                  | php-river     | Ingest IoT → `/api/traffic/readings`     |
| POST                  | `/iot/air`                      | php-river     | Ingest IoT → `/api/environment/readings` |


> Skema lengkap ML: `http://localhost:5012/docs` (Swagger FastAPI). Koleksi Postman: folder **Citizen**, **River CRUD**, **Analytics**, **ML**, **IoT**.

### Body request — endpoint demo (S1–S3)

Gunakan `Content-Type: application/json` kecuali OAuth form-urlencoded.

#### `POST /oauth/token` (form-urlencoded)

**Password grant (login warga):**


| Field           | Wajib | Keterangan             |
| --------------- | ----- | ---------------------- |
| `grant_type`    | ya    | `password`             |
| `username`      | ya    | contoh: `hadiputra2`   |
| `password`      | ya    | contoh: `kelompok2dev` |
| `client_id`     | ya    | `citizen-app`          |
| `client_secret` | ya    | `CitizenSecretDev123`  |


**Client credentials (IoT / antar layanan):**


| Field           | Wajib | Keterangan            |
| --------------- | ----- | --------------------- |
| `grant_type`    | ya    | `client_credentials`  |
| `client_id`     | ya    | `gateway`             |
| `client_secret` | ya    | `GatewaySecretDev123` |


**Refresh token:**


| Field           | Wajib | Keterangan            |
| --------------- | ----- | --------------------- |
| `grant_type`    | ya    | `refresh_token`       |
| `refresh_token` | ya    | dari respons login    |
| `client_id`     | ya    | `citizen-app`         |
| `client_secret` | ya    | `CitizenSecretDev123` |


#### `POST /api/auth/login` (JSON)

```json
{ "username": "hadiputra2", "password": "kelompok2dev" }
```


| Field      | Wajib | Keterangan     |
| ---------- | ----- | -------------- |
| `username` | ya    | username warga |
| `password` | ya    | password warga |


#### `POST /api/reports` (S2)

```json
{ "deskripsiLaporan": "Genangan air setinggi 30 cm di Jl. Merdeka, perlu penanganan." }
```


| Field              | Wajib | Keterangan                  |
| ------------------ | ----- | --------------------------- |
| `deskripsiLaporan` | ya    | string, minimal 10 karakter |


#### `PATCH /api/reports/:id/status` (admin)

```json
{ "status": "resolved" }
```


| Field    | Wajib | Nilai valid                                      |
| -------- | ----- | ------------------------------------------------ |
| `status` | ya    | `pending`, `in_progress`, `resolved`, `rejected` |


#### `POST /iot/traffic` (S1 — sama dengan pembacaan sensor gabungan)

```json
{
  "idNode": 1,
  "tinggiAir": 2.5,
  "kelembapanTanah": 60,
  "curahHujan": 12,
  "suhuRataRata": 28,
  "kelembapanUdara": 77,
  "kecepatanAngin": 10,
  "arahAngin": 180
}
```


| Field             | Wajib | Tipe   | Keterangan                  |
| ----------------- | ----- | ------ | --------------------------- |
| `idNode`          | ya    | number | ID node sensor (lihat seed) |
| `tinggiAir`       | ya    | number | tinggi air (m)              |
| `kelembapanTanah` | ya    | number | 0–100                       |
| `curahHujan`      | ya    | number | mm                          |
| `suhuRataRata`    | ya    | number | °C                          |
| `kelembapanUdara` | ya    | number | %                           |
| `kecepatanAngin`  | ya    | number | m/s                         |
| `arahAngin`       | tidak | number | derajat 0–360 (opsional)    |


#### `POST /iot/air`

Body sama dengan `/iot/traffic` (field di atas).

#### `POST /predict/banjir` atau `/predict/traffic` (S3)

```json
{
  "idSungai": 1,
  "curahHujan": 12,
  "tinggiAir": 2.5,
  "kelembapanTanah": 60,
  "suhuMin": 25,
  "suhuMax": 32,
  "suhuRataRata": 28,
  "kelembapanUdara": 77,
  "sunShine": 5,
  "kecepatanAngin": 10,
  "arahAngin": 180,
  "kecepatanRataRataAngin": 8
}
```


| Field                                                               | Wajib | Tipe        | Keterangan           |
| ------------------------------------------------------------------- | ----- | ----------- | -------------------- |
| `idSungai`                                                          | ya    | int ≥ 1     | ID sungai            |
| `curahHujan`                                                        | tidak | float ≥ 0   | default `0`          |
| `tinggiAir`                                                         | tidak | float ≥ 0   | default `0`          |
| `kelembapanTanah`                                                   | tidak | float 0–100 | default `0`          |
| `suhuMin`, `suhuMax`, `suhuRataRata`                                | tidak | float       | default 25 / 32 / 28 |
| `kelembapanUdara`                                                   | tidak | float 0–100 | default `77`         |
| `sunShine`, `kecepatanAngin`, `arahAngin`, `kecepatanRataRataAngin` | tidak | float       | lihat contoh         |


Hasil: `NORMAL`, `WASPADA`, atau `BENCANA` (trigger S6 jika waspada/bencana).

#### `POST /predict/curah-hujan` atau `/predict/air-quality`

```json
{
  "idNode": 1,
  "tinggiAir": 1.2,
  "kelembapanTanah": 55,
  "suhuMin": 24,
  "suhuMax": 31,
  "suhuRataRata": 27,
  "kelembapanUdara": 70,
  "sunShine": 6,
  "kecepatanAngin": 9,
  "arahAngin": 90,
  "kecepatanRataRataAngin": 7
}
```


| Field               | Wajib | Tipe    | Keterangan                   |
| ------------------- | ----- | ------- | ---------------------------- |
| `idNode`            | ya    | int ≥ 1 | ID node                      |
| Field cuaca lainnya | tidak | float   | sama seperti prediksi banjir |


#### `POST /detect/anomaly`

```json
{
  "sensor_value": 9.5,
  "timestamp_hour": 14,
  "rolling_mean_1h": 2.1,
  "z_score": 3.8
}
```


| Field             | Wajib | Tipe     | Keterangan      |
| ----------------- | ----- | -------- | --------------- |
| `sensor_value`    | ya    | float    | nilai sensor    |
| `timestamp_hour`  | ya    | int 0–23 | jam pembacaan   |
| `rolling_mean_1h` | ya    | float    | rata-rata 1 jam |
| `z_score`         | ya    | float    | z-score         |


### Format respons JSON standar

```json
{
  "status": "success",
  "code": 200,
  "data": { },
  "message": "Keterangan singkat",
  "timestamp": "2025-01-01T00:00:00.000Z",
  "service": "nama-service"
}
```

---

## 11. Message broker (RabbitMQ)

Exchange utama: `**city.events**` (topic)


| Routing key        | Queue                   | Publisher                 | Consumer                       |
| ------------------ | ----------------------- | ------------------------- | ------------------------------ |
| `traffic.new`      | `traffic.new`           | php-river                 | python-ml-consumer-banjir      |
| `air.new`          | `air.new`               | php-river                 | python-ml-consumer-air         |
| `anomaly.alert`    | `anomaly.alert`         | python-ml-consumer-banjir | php-analytics-consumer         |
| `anomaly.alert`    | `citizen.anomaly.alert` | (fan-out)                 | php-user-notification-consumer |
| `report.submitted` | `report.submitted`      | php-user                  | php-user-notification-consumer |


UI manajemen: [http://localhost:15674](http://localhost:15674) (`smartcity` / `RabbitSecret`)

---

## 12. Lapisan IoT (MQTT + Node-RED)

Detail teknis: `[iot/README.md](iot/README.md)`

### Topik MQTT (kelompok 2)


| Topik                      | Isi                                                          |
| -------------------------- | ------------------------------------------------------------ |
| `kelompok2/sensors/sungai` | `idNode`, `tinggiAir`, `kelembapanTanah`                     |
| `kelompok2/sensors/cuaca`  | `idNode`, `curahHujan`, `suhuRataRata`, `kelembapanUdara`, … |


### Alur S1 (ringkas)

1. `iot-simulator` (atau Wokwi ESP32) publish ke Mosquitto
2. Node-RED subscribe → gabung payload sungai + cuaca → OAuth `client_credentials`
3. `POST http://express-gateway:3530/iot/traffic`
4. php-river simpan ke MySQL → publish RabbitMQ
5. python-ml-consumer memproses & prediksi

**Jalur alternatif:** `python-ml-service` juga bisa subscribe MQTT langsung (`MQTT_HOST=mosquitto`) dan publish ke RabbitMQ tanpa melalui gateway — berguna untuk debugging, tetapi **alur demo S1** memakai Node-RED → Gateway → php-river.

### Uji S1

```bash
bash iot/tests/s1-e2e.sh
```

### Wokwi vs Docker lokal


| Mode           | Broker MQTT                                                    |
| -------------- | -------------------------------------------------------------- |
| Docker Compose | `mosquitto` (host `localhost:1886`, internal `mosquitto:1883`) |
| Wokwi online   | `broker.hivemq.com` (atur di firmware)                         |


---

## 13. Layanan Machine Learning

Folder: `python-ml-service/` — FastAPI + scikit-learn

### Model


| Model file                                  | Fungsi                | Algoritma        | Endpoint                                                   |
| ------------------------------------------- | --------------------- | ---------------- | ---------------------------------------------------------- |
| `deteksi_banjir_berdasarkan_waterLevel.pkl` | Cabang ketinggian air | Random Forest    | `POST /predict/banjir` (alias `/predict/traffic`)          |
| `deteksi_banjir_berdasarkan_cuaca.pkl`      | Cabang cuaca          | Random Forest    | Digabung di `POST /predict/banjir`                         |
| `prediksi_curah_hujan.pkl`                  | Estimasi curah hujan  | Random Forest    | `POST /predict/curah-hujan` (alias `/predict/air-quality`) |
| `deteksi_anomali.pkl`                       | Anomali sensor        | Isolation Forest | `POST /detect/anomaly`                                     |


Model dilatih otomatis saat `docker build` (lihat `python-ml-service/Dockerfile`). Host Docker: port **5012**; lokal tanpa Docker: **8000**.

### Training lokal (tanpa Docker)

```bash
cd python-ml-service
python3 -m venv venv && source venv/bin/activate
pip install -r requirements.txt
python trains/train_model_water_level.py
python trains/train_model_cuaca.py
python trains/train_model_curah_hujan.py
python trains/train_model_anomaly.py
uvicorn main:app --host 0.0.0.0 --port 8000
```

Notebook EDA: `python-ml-service/notebooks/EDA1.ipynb`, `EDA2.ipynb`

---

## 14. Monitoring (Prometheus + Grafana)


| URL                                            | Keterangan                  |
| ---------------------------------------------- | --------------------------- |
| [http://localhost:9092](http://localhost:9092) | Prometheus                  |
| [http://localhost:3014](http://localhost:3014) | Grafana (`admin` / `admin`) |


Dashboard: *Smart City Platform Monitoring* — request rate, error rate, latency, CPU, memory container.

Konfigurasi scrape: `monitoring/prometheus.yml`

---

## 15. Kubernetes

Prasyarat: `kubectl`, kluster dengan StorageClass, metrics-server (HPA), opsional nginx Ingress.

```bash
# 1. Build image lokal
./k8s/build-images.sh

# 2. Deploy namespace + workload + DB init
./k8s/deploy.sh

# 3. Cek pod
kubectl get pods -n smartcity -w
```

Atau langsung:

```bash
kubectl apply -k k8s/
```

Tambahkan ke `/etc/hosts`:

```
127.0.0.1 smartcity.local
```

Akses gateway via Ingress: [http://smartcity.local](http://smartcity.local)

Manifest mencakup: namespace, ConfigMap, Secret, MySQL StatefulSet, RabbitMQ, Redis, OAuth, 3 PHP service, Python ML + HPA, gateway (2 replika), worker RabbitMQ, Mosquitto, Ingress.

---

## 16. Skenario demo end-to-end (S1–S6)


| No     | Skenario                                              | Cara verifikasi cepat                                           |
| ------ | ----------------------------------------------------- | --------------------------------------------------------------- |
| **S1** | IoT → MQTT → Node-RED → Gateway → PHP → RabbitMQ → ML | `bash iot/tests/s1-e2e.sh`                                      |
| **S2** | Login OAuth → submit laporan → notifikasi RabbitMQ    | `bash express-gateway/tests/s2-report-e2e.sh`                   |
| **S3** | Prediksi ML via gateway + rate limit                  | `bash express-gateway/tests/s3-ml-e2e.sh`                       |
| **S4** | Docker Compose full stack                             | `make compose-up` + `curl localhost:3530/health`                |
| **S5** | Kubernetes deploy + Ingress + HPA                     | `./k8s/deploy.sh` + `kubectl get hpa -n smartcity`              |
| **S6** | Anomali → RabbitMQ → notifikasi warga                 | Trigger prediksi waspada/bencana → cek `GET /api/notifications` |


**Minimal 5 dari 6 skenario** harus bisa didemonstrasikan live saat presentasi (ketentuan Tugas Besar).

---

## 17. Pengujian otomatis

```bash
# Suite utama (oauth + php unit/http + gateway E2E + monitoring)
make test-all
```

`make test-all` menjalankan 9 skrip berikut (beberapa butuh stack Docker untuk E2E):


| Skrip                                    | Cakupan                                                                                          |
| ---------------------------------------- | ------------------------------------------------------------------------------------------------ |
| `oauth-server/tests/http.sh`             | OAuth grants, introspect, revoke                                                                 |
| `php-user/tests/run.sh`                  | Unit + HTTP php-user                                                                             |
| `php-river/tests/run.sh`                 | Unit + HTTP + integrasi php-river                                                                |
| `php-analytics/tests/run.sh`             | Unit + HTTP + smoke alert                                                                        |
| `python-ml-service/tests/http.sh`        | Endpoint ML (`ML_URL`, default `http://localhost:5012`)                                          |
| `express-gateway/tests/smoke.sh`         | Routing, auth, ML proxy                                                                          |
| `express-gateway/tests/s2-report-e2e.sh` | Skenario S2                                                                                      |
| `express-gateway/tests/s3-ml-e2e.sh`     | Skenario S3                                                                                      |
| `monitoring/tests/smoke.sh`              | Prometheus/Grafana (`PROMETHEUS_URL=http://localhost:9092`, `GRAFANA_URL=http://localhost:3014`) |


**Skenario E2E terpisah** (jalankan manual setelah `make compose-up`):


| Skrip                                   | Cakupan                                  |
| --------------------------------------- | ---------------------------------------- |
| `express-gateway/tests/rabbitmq-e2e.sh` | Alur RabbitMQ                            |
| `iot/tests/s1-e2e.sh`                   | Skenario S1 (`MQTT_PORT=1886` dari host) |


---

## 18. Postman

1. Import `postman/smart-flood-warning.postman_collection.json`
2. Pastikan variabel `baseUrl` = `http://localhost:3530`
3. Request terproteksi **otomatis** mengambil token via pre-request script (password grant + refresh fallback)
4. Folder **IoT** memakai `client_credentials` (`gateway` / `GatewaySecretDev123`)
5. Request admin otomatis memakai `{{adminAccessToken}}` (`dinasari1`) — lihat nama request berakhiran `(admin)`

Koleksi mencakup semua endpoint gateway per layanan:

| Folder | Cakupan |
|--------|---------|
| **Auth** | OAuth, login, profile, health, metrics |
| **Citizen (php-user)** | CRUD warga, laporan, notifikasi, riwayat banjir |
| **Traffic & Environment (php-river)** | Status real-time, history, ingest pembacaan |
| **River CRUD (php-river)** | Zona, sungai, sensor node |
| **Analytics (php-analytics)** | Peringatan banjir, alert aktif |
| **ML (python-ml-service)** | Prediksi, deteksi anomali, model |
| **IoT (Node-RED ingress)** | Ingest sensor via `client_credentials` |

Contoh body ada di setiap request POST/PUT/PATCH. Skema ML lengkap: `http://localhost:5012/docs`.

---

## 19. Deploy di server kelompok

Sesuai spesifikasi server lab:

```bash
ssh -p 8989 mahasiswa@103.147.92.135
cd ~/Kelompok2A_SmartFloodDetection   # atau folder kelompok Anda

git clone https://github.com/Alifsw21/smart-flood-warning-and-monitoring-system.git .  # jika belum ada
git pull origin main
cp .env.example .env
nano .env                        # pastikan OAUTH_CLIENT_SECRET=GatewaySecretDev123

docker compose down
docker rm -f $(docker ps -aq --filter name=smartcity) 2>/dev/null
docker compose up -d --build
docker compose ps
curl http://localhost:3530/health
```

**Port alokasi kelompok (server lab):** 3530 (Gateway), 3532 (OAuth), 8150/8151/8154 (PHP), 5012 (ML), 3352 (MySQL), 5674/15674 (RabbitMQ), 6382 (Redis), 1886 (MQTT), 1890 (Node-RED), 9092 (Prometheus), 3014 (Grafana), 8082 (cAdvisor).

**Aturan keamanan server:**

- Jangan commit `.env` atau kredensial ke Git
- Jangan mematikan container/pod kelompok lain
- Hubungi dosen jika ada konflik port

Deploy Kubernetes di server (jika `kubectl` tersedia):

```bash
./k8s/build-images.sh && ./k8s/deploy.sh
```

---

## 20. Pemecahan masalah

### `curl /health` mengembalikan 503

```bash
docker compose logs express-gateway
docker compose logs oauth-server
docker compose logs php-user
```

Pastikan `OAUTH_CLIENT_SECRET=GatewaySecretDev123` di `.env`.

### OAuth login gagal (`invalid_grant`)

- Pastikan database sudah ter-seed (`hadiputra2` / `kelompok2dev`)
- Gunakan `client_secret=CitizenSecretDev123` (bukan nilai lain)
- Reset volume DB: `make compose-down` lalu `make compose-up` (**hapus semua data**)

### S2 gagal — notifikasi tidak muncul

```bash
docker compose logs php-user-notification-consumer
docker compose ps | grep consumer
```

Pastikan container `smartcity-php-user-notification-consumer` running.

### Port sudah dipakai

Ubah mapping port di `docker-compose.yml` atau hentikan proses yang bentrok.

### Model ML tidak ditemukan

Jalankan ulang build image:

```bash
docker compose build python-ml-service
docker compose up -d python-ml-service
```

### MySQL connection refused dari host

Gunakan port **3352** (bukan 3306) saat koneksi dari luar container.

---

## 21. Checklist deliverables


| No  | Item                    | Lokasi                                                       | Status              |
| --- | ----------------------- | ------------------------------------------------------------ | ------------------- |
| 1   | Source code             | Repo GitHub                                                  | ✅                   |
| 2   | README setup < 15 menit | `README.md` (dokumen ini)                                    | ✅                   |
| 3   | Diagram arsitektur      | submit eksternal (PNG/PDF)                                   | 🔲                  |
| 4   | `schema.sql`            | `database/schema.sql`                                        | ✅                   |
| 5   | `seed.sql`              | `database/seed.sql`                                          | ✅                   |
| 6   | Postman collection      | `postman/`                                                   | ✅                   |
| 7   | Laporan ML / EDA        | `python-ml-service/notebooks/ML_Report.ipynb` (+ ekspor PDF) | ✅                   |
| 8   | `docker-compose.yml`    | root                                                         | ✅                   |
| 9   | Manifest K8s            | `k8s/`                                                       | ✅                   |
| 10  | Video demo (≤15 menit)  | —                                                            | 🔲 submit eksternal |


---

## Perintah Makefile ringkas

```bash
make help           # daftar perintah
make compose-up     # jalankan stack
make compose-down   # stop + hapus volume
make compose-dev    # stack + override dev
make test-all       # semua uji otomatis
make seed           # regenerasi seed.sql
make migrate        # migrasi DB lama
make build-images   # image untuk Kubernetes
```

