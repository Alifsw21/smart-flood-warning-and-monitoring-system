import random
from datetime import datetime, timedelta

OUTPUT_FILE = "seed.sql"

ZONA = ['Jakarta Timur', 'Jakarta Selatan', 'Bogor', 'Depok', 'Tangerang']
SUNGAI = ['Ciliwung', 'Pesanggrahan', 'Cisadane', 'Cikeas', 'Sunter']
POSISI = ['hulu', 'hilir']
NAMA_DEPAN = ['Budi', 'Siti', 'Agus', 'Ayu', 'Rudi', 'Dina', 'Eko', 'Rina', 'Hadi', 'Maya']
NAMA_BELAKANG = ['Santoso', 'Wijaya', 'Pratama', 'Sari', 'Kusuma', 'Putra', 'Lestari', 'Hidayat']
LAPORAN_TEKS = [
    "Data ketinggian air di aplikasi terlambat update dibandingkan kondisi sungai aslinya.",
    "Prediksi peringatan dini banjir semalam sangat akurat, warga sempat mengevakuasi barang.",
    "Notifikasi status Waspada muncul di aplikasi, tapi sungai masih surut. Mohon kalibrasi sensor.",
    "Sensor cuaca di jembatan sepertinya rusak, hujan deras tapi di aplikasi terbaca curah hujan 0 mm.",
    "Dashboard sangat membantu, tapi sensor suhu sepertinya terlalu tinggi (tidak realistis).",
    "Sistem AI memprediksi status Normal, namun air selokan sudah mulai meluap ke jalan.",
    "Akurasi kelembapan tanah dan udara sudah sangat pas dengan kondisi cuaca hari ini.",
    "Notifikasi di aplikasi sangat cepat masuk sesaat setelah hujan deras turun. Terima kasih!"
]

def generate():
    with open(OUTPUT_FILE, "w", encoding="utf-8") as f:
        f.write("USE kelompok2;\n\n")
        f.write("-- =====================================\n")
        f.write("-- DUMMY DATA GENERATE FOR FLOOD WARNING\n")
        f.write("-- =====================================\n")

        f.write("-- 1. Data Zona\n")
        for i in range(1, 6):
            f.write(f"INSERT INTO river_zones (id, nama_kota) VALUES ({i}, '{ZONA[i-1]}');\n")
        f.write("\n")

        f.write("-- 2. Data Sungai\n")
        for i in range(1, 6):
            f.write(f"INSERT INTO river_sungai (id, zoneId, lokasiSungai) VALUES ({i}, {i}, 'Sungai {SUNGAI[i-1]}');\n")
        f.write("\n")

        f.write("-- 3. Data Sensor Node\n")
        id_node = 1
        for id_sungai in range(1, 6):
            for pos in POSISI:
                f.write(f"INSERT INTO river_sensorNode (id, idSungai, idStation, namaNode, posisi, elevasi) "
                        f"VALUES ({id_node}, {id_sungai}, {100+id_node}, 'Node {pos.capitalize()} {SUNGAI[id_sungai-1]}', '{pos}', {round(random.uniform(2.0, 15.0), 1)});\n")
                id_node += 1
        f.write("\n")

        f.write("-- 4. Data Pengguna\n")
        for i in range(1, 51):
            nama_depan = random.choice(NAMA_DEPAN).lower()
            nama_belakang = random.choice(NAMA_BELAKANG).lower()
            username = f"{nama_depan}{nama_belakang}{i}"
            email = f"{username}@kelompok2.com"
            password_dummy = "$2y$10$dummyhashpassword12345678"
            role = 'admin' if i == 1 else 'user'
            waktu_acak = datetime.now() - timedelta(days=random.randint(0, 30), hours=random.randint(0, 23))
            f.write(f"INSERT INTO user_user (id, username, email, password, role, waktuDibuat) VALUES ({i}, '{username}', '{email}', '{password_dummy}', '{role}', '{waktu_acak.strftime('%Y-%m-%d %H:%M:%S')}');\n")
        f.write("\n")

        f.write("-- 5. Data Laporan\n")
        for i in range(1, 21):
            id_pengguna = random.randint(2, 50)
            deskripsi = random.choice(LAPORAN_TEKS)
            waktu_acak = datetime.now() - timedelta(days=random.randint(0, 30), hours=random.randint(0, 23))
            f.write(f"INSERT INTO user_laporan (id, idPengguna, deskripsiLaporan, waktuDibuat) "
                    f"VALUES ({i}, {id_pengguna}, '{deskripsi}', '{waktu_acak.strftime('%Y-%m-%d %H:%M:%S')}');\n")
        f.write("\n")

        f.write("-- 6. Data Sensor Readings\n")
        sekarang = datetime.now()
        for i in range(1, 201):
            id_node_rand = random.randint(1, 10)
            tinggi_air = round(random.uniform(0.5, 6.4), 2)
            kelembapan_tanah = round(random.uniform(5.0, 39.7), 1)
            curah_hujan = round(random.uniform(0.1, 52.7), 1)
            suhu = round(random.uniform(23.0, 36.5), 1)
            kelembapan_udara = round(random.uniform(50.0, 100.0), 1)
            kecepatan_angin = round(random.uniform(0.0, 20.0), 1)
            arah = round(random.uniform(0.0, 360.0), 1)

            waktu_rekam = sekarang - timedelta(hours=(200-i))

            f.write(f"INSERT INTO river_sensorReading (id, idNode, tinggiAir, kelembapanTanah, curahHujan, suhuRataRata, kelembapanUdara, kecepatanAngin, arahAngin, recorded_at) "
                    f"VALUES ({i}, {id_node_rand}, {tinggi_air}, {kelembapan_tanah}, {curah_hujan}, {suhu}, {kelembapan_udara}, {kecepatan_angin}, {arah}, '{waktu_rekam.strftime('%Y-%m-%d %H:%M:%S')}');\n")
        f.write("\n")

    print(f"File '{OUTPUT_FILE}' berhasil di-generate")

if __name__ == "__main__": 
    generate()
