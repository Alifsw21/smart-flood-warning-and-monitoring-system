# IoT Layer (MQTT + Node-RED + Simulator)

Spesifikasi: **Tugas Besar §4.6** · skenario uji **S1** (MQTT → Node-RED → Gateway → php-river → RabbitMQ → ML).

## Komponen

| File / folder | Fungsi |
|---------------|--------|
| `mosquitto.conf` | Broker MQTT (port 1883) |
| `passwd.dev` | Kredensial dev (`iot_device` / `iot_secret`) — jangan commit `passwd` produksi |
| `node-red-data/flows.json` | Flow S1: subscribe sungai+cuaca, merge, OAuth, POST `/iot/traffic` |
| `simulator.py` | Simulator Python (tanpa hardware) |
| `Sensor Air/`, `Sensor Cuaca/` | Firmware Wokwi/ESP32 (alternatif simulator) |
| `tests/s1-e2e.sh` | Uji E2E otomatis S1 |

## Topik MQTT (kelompok 2)

| Topik | Payload (ringkas) |
|-------|-------------------|
| `kelompok2/sensors/sungai` | `idNode`, `tinggiAir`, `kelembapanTanah` |
| `kelompok2/sensors/cuaca` | `idNode`, `curahHujan`, `suhuRataRata`, `kelembapanUdara`, `kecepatanAngin`, … |

Node-RED menggabungkan keduanya lalu POST ke `http://express-gateway:3000/iot/traffic` dengan token OAuth `client_credentials`.

## Menjalankan dengan Docker Compose

```bash
# Dari root repo
cp .env.example .env   # pastikan OAUTH_CLIENT_SECRET=GatewaySecretDev123

docker compose up -d mosquitto oauth-server express-gateway php-river node-red \
  rabbitmq redis python-ml-consumer-banjir mysql

# Tunggu ~30 detik, lalu uji S1
bash iot/tests/s1-e2e.sh

# Simulator Python (background)
MQTT_HOST=localhost python3 iot/simulator.py
```

Atau one-shot publish untuk debug:

```bash
mosquitto_pub -h localhost -t kelompok2/sensors/sungai \
  -m '{"idNode":1,"tinggiAir":2.5,"kelembapanTanah":50}'
mosquitto_pub -h localhost -t kelompok2/sensors/cuaca \
  -m '{"idNode":2,"curahHujan":10,"suhuRataRata":28,"kelembapanUdara":70,"kecepatanAngin":8}'
```

## Wokwi vs Compose

| Mode | Broker MQTT | Kapan dipakai |
|------|-------------|---------------|
| **Wokwi** (dev) | `broker.hivemq.com` publik | Prototype firmware di `Sensor Air/`, `Sensor Cuaca/` |
| **Compose / server** | `mosquitto:1883` (service `mosquitto`) | S1, demo, course server |

Untuk deploy: set `IS_DEPLOYED = true` di firmware dan isi `secrets.h` (MQTT host, user, pass). Di compose, broker = hostname `mosquitto`.

## OAuth & secrets

| Variabel | Default dev | Dipakai oleh |
|----------|-------------|--------------|
| `OAUTH_CLIENT_ID` | `gateway` | Node-RED OAuth bootstrap |
| `OAUTH_CLIENT_SECRET` | `GatewaySecretDev123` | Node-RED + Gateway introspect |
| `JWT_SECRET` | lihat `.env.example` | Gateway smoke / JWT lokal |

Node-RED meminta token di startup (`POST oauth-server:3002/oauth/token`) dan menyimpan di `global.authToken`.

## Antrian saat Gateway down (§4.6C)

Jika POST `/iot/traffic` gagal (gateway mati, 5xx, atau token belum ada), payload disimpan di `global.iotPendingQueue`. Inject **Drain IoT queue** setiap 15 detik mencoba mengirim ulang.

Uji manual:

```bash
docker compose stop express-gateway
python3 iot/simulator.py   # data masuk antrian Node-RED
docker compose start express-gateway
# dalam ±15–30 detik antrian terkirim
```

## Tes

```bash
php iot/tests/unit.php              # logika sensor (tanpa compose)
RUN_S1_E2E=1 bash iot/tests/run.sh  # unit + S1 E2E (compose harus hidup)
bash iot/tests/s1-e2e.sh            # hanya S1
```

## Node-RED UI

Editor: http://localhost:1880 — flow **IoT S1 Pipeline**.

## Troubleshooting: "MQTT tidak dapat data IoT"

### Penyebab paling sering: broker salah

| Sumber data | Broker yang dipakai | Kelihatan di compose? |
|-------------|---------------------|------------------------|
| **Wokwi** (`IS_DEPLOYED=false`) | `broker.hivemq.com` (internet) | **Tidak** — bukan `mosquitto` lokal |
| **`iot/simulator.py`** dengan `MQTT_HOST=localhost` | Mosquitto Docker port 1883 | **Ya** |
| **Node-RED / python-ml** di compose | `mosquitto:1883` (internal) | **Ya** |

Firmware Wokwi **tidak** mengirim ke Mosquitto lokal. Untuk uji S1 di Docker, jalankan simulator Python:

```bash
docker compose up -d mosquitto node-red express-gateway oauth-server php-river mysql
MQTT_HOST=localhost python3 iot/simulator.py
```

### Cek cepat (3 langkah)

```bash
# 1. Broker hidup?
docker compose ps mosquitto

# 2. MQTT bisa kirim/terima?
bash iot/tests/mqtt-smoke.sh

# 3. Pipeline S1 penuh?
bash iot/tests/s1-e2e.sh
```

### Node-RED butuh DUA topik

Flow merge menunggu **sungai** dan **cuaca** berpasangan. Hanya satu topik → tidak ada POST ke Gateway (status node: "waiting pair").

### Wokwi ingin ke Mosquitto lokal?

Opsi A — pakai simulator Python (disarankan untuk compose).

Opsi B — firmware deploy: `IS_DEPLOYED=true`, `secrets.h` dengan host IP mesin (`localhost` tidak bisa dari Wokwi; pakai IP LAN atau server kursus).

Opsi C — tetap HiveMQ untuk Wokwi saja; itu terpisah dari stack Docker compose.

### Monitor topik manual

```bash
# butuh mosquitto-clients (brew install mosquitto) atau pakai mqtt-smoke.sh
mosquitto_sub -h localhost -t 'kelompok2/sensors/#' -v
```

Di terminal lain: `MQTT_HOST=localhost python3 iot/simulator.py`
