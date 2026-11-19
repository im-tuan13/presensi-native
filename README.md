# Aplikasi Presensi Karyawan Berbasis GPS Geofencing (PHP Native)

Aplikasi web presensi (kehadiran) karyawan berbasis GPS Geofencing tanpa framework (Pure PHP 8.0+ & MySQLi Prepared Statements), dirancang *mobile-first* menggunakan Bootstrap 5.3 CDN dan Vanilla JavaScript.

---

## Struktur File & Direktori

```text
presensi/
├── database.sql        → Skema tabel, relasi foreign key, dan data awal (seed)
├── koneksi.php         → Koneksi database, strict mode, timezone Asia/Jakarta, session aman
├── fungsi.php          → Konstanta jam kerja, rumus Haversine, wajibLogin(), CSRF, e()
├── login.php           → Halaman login (password_verify bcrypt, session_regenerate_id)
├── logout.php          → Mengakhiri sesi dan membersihkan session cookie
├── index.php           → Dashboard mobile karyawan, jam real-time, geofence, tombol presensi
├── proses_absen.php    → Endpoint API JSON (validasi GPS server-side, CSRF, simpan presensi)
├── README.md           → Dokumentasi instalasi dan panduan pengujian sensor
└── admin/              → Modul Administrator
    ├── index.php       → Monitoring presensi hari ini & statistik
    ├── karyawan.php    → CRUD data karyawan & penetapan kantor
    ├── lokasi.php      → Pengaturan titik koordinat kantor & radius meter
    ├── rekap.php       → Rekapitulasi presensi bulanan dengan filter
    └── ekspor_csv.php  → Ekspor data rekapitulasi ke format CSV/Excel
```

---

## ⚙️ Langkah Instalasi di XAMPP / Laragon

1. **Letakkan Folder Proyek:**
   Pastikan folder berada di: `d:\xampp\htdocs\presensi` (atau direktori root web server Anda).

2. **Aktifkan Server:**
   Buka XAMPP Control Panel, lalu jalankan modul **Apache** dan **MySQL**.

3. **Import Database:**
   - Buka browser dan akses `http://localhost/phpmyadmin`
   - Buat database baru bernama `db_presensi` (atau biarkan query membuat otomatis)
   - Pilih tab **Import**, lalu pilih file `database.sql` dan klik **Go / Kirim**
   - *Alternatif CLI:* Jalankan `mysql -u root < database.sql`

4. **Buka Aplikasi di Browser:**
   - Karyawan / Halaman Utama: `http://localhost/presensi/`
   - Login otomatis mengarahkan ke dashboard karyawan atau admin sesuai peran akun.

---

## 🔑 Akun Demo / Uji Coba

| Peran (Role) | NIP | Username | Kata Sandi |
|---|---|---|---|
| **Karyawan 1** | `199501152020121001` | `budi` | `password` |
| **Karyawan 2** | `199607202021012002` | `siti` | `password` |
| **Administrator** | `199003122015031003` | `admin` | `password` |

---

## ⏱️ Aturan Bisnis & Geofencing

1. **Jadwal Jam Masuk:**
   - Batas akhir tepat waktu: `06:30:59 WIB`.
   - Absen masuk sebelum atau sama dengan batas waktu dihitung **Hadir**.
   - Absen masuk setelah batas waktu dihitung **Terlambat**.
2. **Jadwal Jam Pulang:**
   - Default jam pulang: `15:00:00 WIB`.
   - Tersedia opsi `PULANG_HARUS_SETELAH_JAM` di `fungsi.php` (`false` secara default untuk kemudahan testing).
3. **Geofencing Dua Lapis (Frontend & Backend):**
   - **Frontend (UX):** Menghitung jarak via rumus Haversine JavaScript secara real-time melalui `navigator.geolocation.watchPosition` dengan opsi `enableHighAccuracy: true`. Tombol terkunci (disabled) jika di luar radius kantor.
   - **Backend (Otoritas):** `proses_absen.php` **tidak mempercayai** klaim frontend. Backend menghitung ulang jarak menggunakan rumus Haversine PHP dan data koordinat kantor dari database milik user. Jika jarak melebihi batas radius, server menolak dengan status **HTTP 403 Forbidden**.
4. **Keamanan & Anti Race Condition:**
   - Seluruh query menggunakan **Prepared Statements**.
   - Validasi token **CSRF** pada setiap interaksi penting.
   - Cookie sesi aman (`HttpOnly`, `SameSite=Lax`, `Secure` pada HTTPS).
   - Penolakan duplikasi presensi hari yang sama via **UNIQUE KEY (user_id, tanggal)** dan kondisi `WHERE jam_pulang IS NULL`.

---

## 🧪 Cara Menguji Lokasi GPS dengan Chrome DevTools

Jika Anda sedang menguji di komputer desktop/laptop yang berada di luar lokasi kantor, Anda dapat mensimulasikan koordinat lokasi secara instan menggunakan fitur **Sensors** bawaan browser Chrome/Edge:

1. Buka browser Chrome/Edge dan kunjungi `http://localhost/presensi/`.
2. Login menggunakan akun karyawan (contoh: `budi` / `password`).
3. Buka **Developer Tools** dengan menekan tombol `F12` atau `Ctrl + Shift + I`.
4. Klik icon **titik tiga (&vellip;)** di pojok kanan atas jendela DevTools &rarr; **More tools** &rarr; **Sensors**.
5. Pada panel **Sensors** di bagian bawah, cari opsi **Location**:
   - Pilih opsi **Other...** pada dropdown.
   - **Skenario DI DALAM RADIUS KANTOR (Tombol Aktif):**
     - Masukkan koordinat Monumen Nasional (titik kantor default di database):
       - Latitude: `-6.17539240`
       - Longitude: `106.82715380`
     - Perhatikan alert di layar seketika berubah menjadi **hijau (Di dalam area kantor)** dan tombol **Absen Masuk** otomatis aktif!
   - **Skenario DI LUAR RADIUS KANTOR (Tombol Terkunci):**
     - Ubah koordinat menjadi lokasi lain (contoh: Bundaran HI):
       - Latitude: `-6.19500000`
       - Longitude: `106.82300000`
     - Alert di layar seketika berubah menjadi **merah (Di luar area kantor - Jarak ~2.2 km)** dan tombol presensi terkunci rapat! 