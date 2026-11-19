<?php
/**
 * File: proses_absen.php
 * Endpoint JSON untuk memproses transaksi absen masuk dan absen pulang siswa PKL.
 */

// Set header response selalu JSON
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/koneksi.php';

// Fungsi bantuan untuk mengirim response JSON dan mengakhiri eksekusi
function kirimResponse(bool $sukses, string $pesan): void {
    echo json_encode([
        'sukses' => $sukses,
        'pesan'  => $pesan
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 1. Validasi Keamanan: Pastikan siswa telah login
if (!isset($_SESSION['siswa_id']) || empty($_SESSION['siswa_id'])) {
    kirimResponse(false, 'Sesi login telah berakhir. Silakan muat ulang dan login kembali.');
}

// 2. Validasi HTTP Method: Hanya menerima metode POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    kirimResponse(false, 'Metode permintaan tidak valid.');
}

// 3. Ambil dan validasi parameter input
$aksi = trim($_POST['aksi'] ?? '');
$latRaw = trim($_POST['lat'] ?? '0');
$lngRaw = trim($_POST['lng'] ?? '0');

if ($aksi !== 'masuk' && $aksi !== 'pulang') {
    kirimResponse(false, 'Aksi presensi tidak dikenal (hanya masuk atau pulang).');
}

// Validasi koordinat GPS
if (!is_numeric($latRaw) || !is_numeric($lngRaw)) {
    kirimResponse(false, 'Koordinat lokasi tidak valid.');
}

$latSiswa = (float)$latRaw;
$lngSiswa = (float)$lngRaw;

if ($latSiswa < -90.0 || $latSiswa > 90.0 || $lngSiswa < -180.0 || $lngSiswa > 180.0) {
    kirimResponse(false, 'Nilai koordinat berada di luar jangkauan geografis.');
}

$siswaId = (int)$_SESSION['siswa_id'];

// Fungsi hitung jarak Haversine (radius bumi 6.371.000 meter)
function hitungJarakHaversine(float $lat1, float $lon1, float $lat2, float $lon2): int {
    $earthRadius = 6371000;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);

    $a = sin($dLat / 2) * sin($dLat / 2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon / 2) * sin($dLon / 2);

    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return (int)round($earthRadius * $c);
}

try {
    // 4. Ambil titik koordinat & radius lokasi PKL milik siswa
    $stmtLokasi = $koneksi->prepare(
        'SELECT l.nama_tempat, l.latitude, l.longitude, l.radius 
         FROM siswa s 
         JOIN lokasi_pkl l ON s.lokasi_id = l.id 
         WHERE s.id = ? 
         LIMIT 1'
    );
    $stmtLokasi->bind_param('i', $siswaId);
    $stmtLokasi->execute();
    $lokasi = $stmtLokasi->get_result()->fetch_assoc();
    $stmtLokasi->close();

    if (!$lokasi) {
        kirimResponse(false, 'Data penempatan lokasi PKL siswa tidak ditemukan.');
    }

    $latLokasi = (float)$lokasi['latitude'];
    $lngLokasi = (float)$lokasi['longitude'];
    $radiusMaks = (int)$lokasi['radius'];

    // Hitung jarak (tetap dihitung dan koordinat tetap disimpan tanpa memblokir presensi)
    $jarakMeter = hitungJarakHaversine($latSiswa, $lngSiswa, $latLokasi, $lngLokasi);

    // Waktu server (bukan waktu gawai/client)
    $tanggalHariIni = date('Y-m-d');
    $jamSekarang    = date('H:i:s');

    // 5. Proses Absen Masuk
    if ($aksi === 'masuk') {
        // Tentukan status kehadiran otomatis berdasarkan jam server
        $status = ($jamSekarang <= JAM_MASUK) ? 'Hadir' : 'Terlambat';

        // Cegah absen ganda dengan INSERT IGNORE memanfaatkan UNIQUE KEY (siswa_id, tanggal)
        $stmtMasuk = $koneksi->prepare(
            'INSERT IGNORE INTO presensi 
             (siswa_id, tanggal, jam_masuk, status, lat_masuk, lng_masuk) 
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmtMasuk->bind_param('isssdd', $siswaId, $tanggalHariIni, $jamSekarang, $status, $latSiswa, $lngSiswa);
        $stmtMasuk->execute();
        $berhasil = ($stmtMasuk->affected_rows > 0);
        $stmtMasuk->close();

        if ($berhasil) {
            $pesanStatus = ($status === 'Hadir') ? 'Tepat Waktu' : 'Terlambat';
            kirimResponse(
                true, 
                "Berhasil absen masuk pada $jamSekarang WIB (Status: $pesanStatus)."
            );
        } else {
            kirimResponse(false, 'Anda sudah melakukan absen masuk hari ini.');
        }
    }

    // 6. Proses Absen Pulang
    if ($aksi === 'pulang') {
        // Validasi aturan waktu pulang: hanya boleh setelah jam 15:00
        if ($jamSekarang < JAM_PULANG) {
            $jamBuka = substr(JAM_PULANG, 0, 5);
            kirimResponse(false, "Belum waktunya absen pulang. Absen pulang baru dibuka mulai pukul $jamBuka WIB.");
        }

        // Periksa apakah siswa sudah melakukan absen masuk hari ini
        $stmtCek = $koneksi->prepare(
            'SELECT jam_masuk, jam_pulang FROM presensi WHERE siswa_id = ? AND tanggal = ? LIMIT 1'
        );
        $stmtCek->bind_param('is', $siswaId, $tanggalHariIni);
        $stmtCek->execute();
        $presensiHariIni = $stmtCek->get_result()->fetch_assoc();
        $stmtCek->close();

        if (!$presensiHariIni || empty($presensiHariIni['jam_masuk'])) {
            kirimResponse(false, 'Anda belum melakukan absen masuk hari ini. Tidak dapat absen pulang.');
        }

        if (!empty($presensiHariIni['jam_pulang'])) {
            kirimResponse(false, 'Anda sudah melakukan absen pulang hari ini.');
        }

        // Catat jam dan koordinat pulang
        $stmtPulang = $koneksi->prepare(
            'UPDATE presensi 
             SET jam_pulang = ?, lat_pulang = ?, lng_pulang = ? 
             WHERE siswa_id = ? AND tanggal = ? AND jam_pulang IS NULL'
        );
        $stmtPulang->bind_param('sddis', $jamSekarang, $latSiswa, $lngSiswa, $siswaId, $tanggalHariIni);
        $stmtPulang->execute();
        $berhasil = ($stmtPulang->affected_rows > 0);
        $stmtPulang->close();

        if ($berhasil) {
            kirimResponse(
                true, 
                "Berhasil absen pulang pada $jamSekarang WIB. Hati-hati di jalan!"
            );
        } else {
            kirimResponse(false, 'Gagal mencatat absen pulang. Silakan coba kembali.');
        }
    }
} catch (mysqli_sql_exception $e) {
    error_log('Database Absen Query Error: ' . $e->getMessage());
    kirimResponse(false, 'Terjadi kendala pada sistem database saat memproses presensi.');
}
