<?php
/**
 * File: index.php
 * Halaman utama presensi siswa PKL:
 * - Login 2 Role: Siswa PKL (NIS) & Administrator (Username)
 * - Dashboard Siswa (Mobile-first dengan Presensi GPS)
 */

require_once __DIR__ . '/koneksi.php';

// ----------------------------------------------------------
// 1. PROSES LOGOUT (Diproses paling awal agar tidak stuck)
// ----------------------------------------------------------
if (isset($_GET['aksi']) && $_GET['aksi'] === 'logout') {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
    header('Location: index.php');
    exit;
}

// ----------------------------------------------------------
// 2. CEK REDIRECTION ROLE
// ----------------------------------------------------------
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    header('Location: admin/index.php');
    exit;
}

// ----------------------------------------------------------
// 3. PROSES LOGIN
// ----------------------------------------------------------
$errorLogin = '';
$activeTab  = 'siswa';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // A. Login Siswa (NIS & Password)
    if (isset($_POST['login_siswa'])) {
        $activeTab = 'siswa';
        $nis       = trim($_POST['nis'] ?? '');
        $password  = trim($_POST['password'] ?? '');

        if ($nis === '' || $password === '') {
            $errorLogin = 'NIS dan Password wajib diisi.';
        } else {
            try {
                $stmt = $koneksi->prepare('SELECT id, nis, nama, password FROM siswa WHERE nis = ? LIMIT 1');
                $stmt->bind_param('s', $nis);
                $stmt->execute();
                $siswa = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($siswa && password_verify($password, $siswa['password'])) {
                    session_regenerate_id(true);
                    $_SESSION['role']       = 'siswa';
                    $_SESSION['siswa_id']   = (int)$siswa['id'];
                    $_SESSION['siswa_nis']  = $siswa['nis'];
                    $_SESSION['siswa_nama'] = $siswa['nama'];

                    header('Location: index.php');
                    exit;
                } else {
                    $errorLogin = 'NIS atau Password siswa salah.';
                }
            } catch (mysqli_sql_exception $e) {
                error_log('Login Siswa Error: ' . $e->getMessage());
                $errorLogin = 'Terjadi kendala pada database server.';
            }
        }
    }

    // B. Login Administrator (Username & Password)
    if (isset($_POST['login_admin'])) {
        $activeTab = 'admin';
        $username  = trim($_POST['username'] ?? '');
        $password  = trim($_POST['password'] ?? '');

        if ($username === '' || $password === '') {
            $errorLogin = 'Username dan Password admin wajib diisi.';
        } else {
            try {
                $stmt = $koneksi->prepare('SELECT id, nama, username, password, role FROM users WHERE username = ? AND role = "admin" LIMIT 1');
                $stmt->bind_param('s', $username);
                $stmt->execute();
                $user = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($user && password_verify($password, $user['password'])) {
                    session_regenerate_id(true);
                    $_SESSION['role']      = 'admin';
                    $_SESSION['user_id']   = (int)$user['id'];
                    $_SESSION['user_nama'] = $user['nama'];

                    header('Location: admin/index.php');
                    exit;
                } else {
                    $errorLogin = 'Username atau Password admin salah.';
                }
            } catch (mysqli_sql_exception $e) {
                error_log('Login Admin Error: ' . $e->getMessage());
                $errorLogin = 'Terjadi kendala pada database server.';
            }
        }
    }
}

// Cek apakah yang aktif adalah siswa
$isSiswa = isset($_SESSION['role']) && $_SESSION['role'] === 'siswa' && !empty($_SESSION['siswa_id']);

// Jika login sebagai Siswa, persiapkan data dashboard siswa
$siswaData       = null;
$presensiHariIni = null;
$riwayatList     = [];
$sudahMasuk      = false;
$sudahPulang     = false;

if ($isSiswa) {
    $siswaId = (int)$_SESSION['siswa_id'];
    $tanggalHariIni = date('Y-m-d');

    try {
        // Ambil data siswa & lokasi penempatan PKL
        $stmtSiswa = $koneksi->prepare(
            'SELECT s.id, s.nis, s.nama, l.nama_tempat, l.latitude, l.longitude, l.radius 
             FROM siswa s 
             JOIN lokasi_pkl l ON s.lokasi_id = l.id 
             WHERE s.id = ? LIMIT 1'
        );
        $stmtSiswa->bind_param('i', $siswaId);
        $stmtSiswa->execute();
        $siswaData = $stmtSiswa->get_result()->fetch_assoc();
        $stmtSiswa->close();

        // Ambil data presensi hari ini
        $stmtPresensi = $koneksi->prepare(
            'SELECT jam_masuk, jam_pulang, status 
             FROM presensi 
             WHERE siswa_id = ? AND tanggal = ? LIMIT 1'
        );
        $stmtPresensi->bind_param('is', $siswaId, $tanggalHariIni);
        $stmtPresensi->execute();
        $presensiHariIni = $stmtPresensi->get_result()->fetch_assoc();
        $stmtPresensi->close();

        $sudahMasuk  = !empty($presensiHariIni['jam_masuk']);
        $sudahPulang = !empty($presensiHariIni['jam_pulang']);

        // Ambil riwayat 10 presensi terakhir
        $stmtRiwayat = $koneksi->prepare(
            'SELECT tanggal, jam_masuk, jam_pulang, status 
             FROM presensi 
             WHERE siswa_id = ? 
             ORDER BY tanggal DESC 
             LIMIT 10'
        );
        $stmtRiwayat->bind_param('i', $siswaId);
        $stmtRiwayat->execute();
        $riwayatList = $stmtRiwayat->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtRiwayat->close();
    } catch (mysqli_sql_exception $e) {
        error_log('Database Dashboard Query Error: ' . $e->getMessage());
        die('Terjadi kendala saat memuat data presensi.');
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= $isSiswa ? 'Dashboard Siswa - Presensi PKL' : 'Login Presensi Siswa PKL' ?></title>
    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f1f5f9;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            color: #334155;
            min-height: 100vh;
        }
        .app-container {
            max-width: 520px;
            margin: 0 auto;
            min-height: 100vh;
            background: #ffffff;
            box-shadow: 0 0 25px rgba(0, 0, 0, 0.06);
            display: flex;
            flex-direction: column;
        }
        .header-section {
            background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
            color: #ffffff;
            padding: 20px 20px 24px;
            border-bottom-left-radius: 20px;
            border-bottom-right-radius: 20px;
        }
        .clock-display {
            font-family: "Courier New", Courier, monospace;
            font-size: 2.2rem;
            font-weight: 700;
            letter-spacing: 2px;
            color: #0f172a;
        }
        .btn-absen {
            border-radius: 14px;
            font-size: 1.05rem;
            padding: 14px;
            transition: transform 0.1s ease, box-shadow 0.2s ease;
        }
        .btn-absen:active:not(:disabled) {
            transform: scale(0.98);
        }
        .card-custom {
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            background-color: #ffffff;
        }
        .table-custom th, .table-custom td {
            vertical-align: middle;
            font-size: 0.85rem;
        }
        .nav-pills .nav-link {
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.9rem;
            color: #64748b;
        }
        .nav-pills .nav-link.active {
            background-color: #0d6efd;
            color: #ffffff;
        }
    </style>
</head>
<body>

<div class="app-container">

<?php if (!$isSiswa): ?>
    <!-- ====================================================== -->
    <!-- 1. HALAMAN LOGIN 2 ROLE: SISWA & ADMIN                 -->
    <!-- ====================================================== -->
    <div class="p-4 my-auto">
        <div class="text-center mb-4">
            <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 72px; height: 72px;">
                <i class="bi bi-geo-alt-fill fs-1"></i>
            </div>
            <h3 class="fw-bold text-dark mb-1">Presensi Siswa PKL</h3>
            <p class="text-muted small">Praktek Kerja Lapangan Berbasis GPS</p>
        </div>

        <?php if (!empty($errorLogin)): ?>
            <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center py-2 mb-3" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                <div class="small"><?= e($errorLogin) ?></div>
                <button type="button" class="btn-close ms-auto p-2" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Nav Tabs Role (Siswa / Admin) -->
        <ul class="nav nav-pills nav-fill mb-3 bg-light p-1 rounded-3" id="loginTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $activeTab === 'siswa' ? 'active' : '' ?>" id="tab-siswa-btn" data-bs-toggle="pill" data-bs-target="#tab-siswa" type="button" role="tab">
                    <i class="bi bi-person-badge me-1"></i> Siswa PKL
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $activeTab === 'admin' ? 'active' : '' ?>" id="tab-admin-btn" data-bs-toggle="pill" data-bs-target="#tab-admin" type="button" role="tab">
                    <i class="bi bi-shield-lock me-1"></i> Administrator
                </button>
            </li>
        </ul>

        <div class="tab-content" id="loginTabContent">
            <!-- TAB 1: LOGIN SISWA -->
            <div class="tab-pane fade <?= $activeTab === 'siswa' ? 'show active' : '' ?>" id="tab-siswa" role="tabpanel">
                <div class="card card-custom p-4 shadow-sm mb-3">
                    <form method="POST" action="index.php" autocomplete="off">
                        <div class="mb-3">
                            <label for="nis" class="form-label small fw-semibold text-secondary">Nomor Induk Siswa (NIS)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-person"></i></span>
                                <input type="text" class="form-control border-start-0 ps-0" id="nis" name="nis" placeholder="Contoh: 12345" required autofocus>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="password_siswa" class="form-label small fw-semibold text-secondary">Password</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-lock"></i></span>
                                <input type="password" class="form-control border-start-0 ps-0" id="password_siswa" name="password" placeholder="Masukkan password" required>
                            </div>
                        </div>

                        <button type="submit" name="login_siswa" class="btn btn-primary w-100 py-2 fw-semibold rounded-3 shadow-sm">
                            <i class="bi bi-box-arrow-in-right me-1"></i> Masuk sebagai Siswa
                        </button>
                    </form>
                </div>
            </div>

            <!-- TAB 2: LOGIN ADMINISTRATOR -->
            <div class="tab-pane fade <?= $activeTab === 'admin' ? 'show active' : '' ?>" id="tab-admin" role="tabpanel">
                <div class="card card-custom p-4 shadow-sm mb-3">
                    <form method="POST" action="index.php" autocomplete="off">
                        <div class="mb-3">
                            <label for="username" class="form-label small fw-semibold text-secondary">Username Admin</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-person-check"></i></span>
                                <input type="text" class="form-control border-start-0 ps-0" id="username" name="username" placeholder="admin" required>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="password_admin" class="form-label small fw-semibold text-secondary">Password Admin</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-key"></i></span>
                                <input type="password" class="form-control border-start-0 ps-0" id="password_admin" name="password" placeholder="Masukkan password" required>
                            </div>
                        </div>

                        <button type="submit" name="login_admin" class="btn btn-dark w-100 py-2 fw-semibold rounded-3 shadow-sm">
                            <i class="bi bi-box-arrow-in-right me-1"></i> Masuk sebagai Admin
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="text-center text-muted small mt-2">
            <span class="badge bg-light text-secondary border d-block py-2">
                <strong>Akun Uji Coba (Semua Password: password123):</strong><br>
                Siswa: NIS <code>12345</code> s/d <code>12354</code> | Admin: User <code>admin</code>
            </span>
        </div>
    </div>

<?php else: ?>
    <!-- ====================================================== -->
    <!-- 2. DASHBOARD SISWA (SETELAH LOGIN)                     -->
    <!-- ====================================================== -->

    <!-- Header & Profil Siswa -->
    <div class="header-section shadow-sm">
        <div class="d-flex justify-content-between align-items-start mb-3">
            <div>
                <span class="badge bg-white text-primary mb-1 fw-bold">Siswa PKL</span>
                <h5 class="fw-bold mb-0 text-white"><?= e($siswaData['nama']) ?></h5>
                <small class="opacity-75">NIS: <?= e($siswaData['nis']) ?></small>
            </div>
            <a href="logout.php" class="btn btn-sm btn-outline-light rounded-pill px-3" onclick="return confirm('Apakah Anda yakin ingin keluar?');">
                <i class="bi bi-box-arrow-right me-1"></i> Keluar
            </a>
        </div>

        <!-- Info Lokasi PKL & Radius -->
        <div class="bg-white bg-opacity-10 rounded-3 p-2 px-3 small border border-white border-opacity-25">
            <div class="d-flex align-items-center">
                <i class="bi bi-building me-2 fs-5"></i>
                <div class="text-truncate">
                    <div class="fw-semibold text-truncate"><?= e($siswaData['nama_tempat']) ?></div>
                    <div class="opacity-75" style="font-size: 0.8rem;">
                        <i class="bi bi-crosshair me-1"></i>Radius Maksimal: <strong><?= e($siswaData['radius']) ?> meter</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Konten Dashboard Siswa -->
    <div class="p-3 flex-grow-1">

        <!-- Card Jam Digital & Tanggal Hari Ini -->
        <div class="card card-custom text-center p-3 mb-3 shadow-sm">
            <div class="text-muted small fw-semibold mb-1">
                <i class="bi bi-calendar3 me-1 text-primary"></i> <?= formatTanggalIndo(date('Y-m-d')) ?>
            </div>
            <div class="clock-display" id="jamDigital">--:--:--</div>
            <div class="text-muted small" style="font-size: 0.75rem;">Waktu Indonesia Barat (WIB)</div>
        </div>

        <!-- Area Alert Status Hasil Absen (Dinamis via JS) -->
        <div id="alertPresensi" class="alert d-none mb-3 shadow-sm py-2 px-3 small" role="alert">
            <div class="d-flex align-items-center">
                <span id="alertIcon" class="me-2 fs-5"></span>
                <span id="alertPesan"></span>
            </div>
        </div>

        <!-- Dua Tombol Besar: Absen Masuk & Absen Pulang -->
        <div class="row g-2 mb-3">
            <div class="col-6">
                <button type="button" 
                        id="btnMasuk" 
                        class="btn btn-success btn-absen w-100 fw-bold shadow-sm" 
                        onclick="jalankanPresensi('masuk')"
                        <?= $sudahMasuk ? 'disabled' : '' ?>>
                    <i class="bi bi-box-arrow-in-down-right d-block fs-3 mb-1"></i>
                    Absen Masuk
                </button>
            </div>
            <div class="col-6">
                <button type="button" 
                        id="btnPulang" 
                        class="btn btn-danger btn-absen w-100 fw-bold shadow-sm" 
                        onclick="jalankanPresensi('pulang')"
                        <?= (!$sudahMasuk || $sudahPulang) ? 'disabled' : '' ?>>
                    <i class="bi bi-box-arrow-up-right d-block fs-3 mb-1"></i>
                    Absen Pulang
                </button>
            </div>
        </div>

        <!-- Ringkasan Status Presensi Hari Ini -->
        <div class="card card-custom p-3 mb-3 shadow-sm">
            <h6 class="fw-bold mb-3 small text-secondary text-uppercase tracking-wider">
                <i class="bi bi-clock-history me-1 text-primary"></i> Presensi Hari Ini
            </h6>
            <div class="row text-center g-2">
                <div class="col-4 border-end">
                    <div class="text-muted" style="font-size: 0.75rem;">Jam Masuk</div>
                    <div class="fw-bold text-dark mt-1" style="font-size: 0.95rem;">
                        <?= $sudahMasuk ? e(substr($presensiHariIni['jam_masuk'], 0, 5)) : '-' ?>
                    </div>
                </div>
                <div class="col-4 border-end">
                    <div class="text-muted" style="font-size: 0.75rem;">Status</div>
                    <div class="mt-1">
                        <?php if ($sudahMasuk): ?>
                            <?php if ($presensiHariIni['status'] === 'Hadir'): ?>
                                <span class="badge bg-success">Hadir</span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark">Terlambat</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="badge bg-secondary">Belum Absen</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-4">
                    <div class="text-muted" style="font-size: 0.75rem;">Jam Pulang</div>
                    <div class="fw-bold text-dark mt-1" style="font-size: 0.95rem;">
                        <?= $sudahPulang ? e(substr($presensiHariIni['jam_pulang'], 0, 5)) : '-' ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabel Riwayat 10 Presensi Terakhir -->
        <div class="card card-custom p-3 shadow-sm mb-3">
            <h6 class="fw-bold mb-3 small text-secondary text-uppercase tracking-wider">
                <i class="bi bi-journal-text me-1 text-primary"></i> Riwayat 10 Presensi Terakhir
            </h6>
            <div class="table-responsive">
                <table class="table table-hover table-custom mb-0 text-center">
                    <thead class="table-light">
                        <tr>
                            <th class="text-start">Tanggal</th>
                            <th>Masuk</th>
                            <th>Pulang</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($riwayatList)): ?>
                            <tr>
                                <td colspan="4" class="text-muted py-3 small">Belum ada riwayat presensi.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($riwayatList as $item): ?>
                                <tr>
                                    <td class="text-start text-nowrap fw-semibold">
                                        <?= e(date('d/m/Y', strtotime($item['tanggal']))) ?>
                                    </td>
                                    <td><?= !empty($item['jam_masuk']) ? e(substr($item['jam_masuk'], 0, 5)) : '-' ?></td>
                                    <td><?= !empty($item['jam_pulang']) ? e(substr($item['jam_pulang'], 0, 5)) : '-' ?></td>
                                    <td>
                                        <?php if ($item['status'] === 'Hadir'): ?>
                                            <span class="badge bg-success">Hadir</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark">Terlambat</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- Script Jam Digital & Presensi Frontend -->
    <script>
        function updateJam() {
            const sekarang = new Date();
            const jam = String(sekarang.getHours()).padStart(2, '0');
            const menit = String(sekarang.getMinutes()).padStart(2, '0');
            const detik = String(sekarang.getSeconds()).padStart(2, '0');
            document.getElementById('jamDigital').textContent = `${jam}:${menit}:${detik}`;
        }
        setInterval(updateJam, 1000);
        updateJam();

        function tampilkanAlert(tipe, pesan, iconClass) {
            const alertBox = document.getElementById('alertPresensi');
            const alertPesan = document.getElementById('alertPesan');
            const alertIcon = document.getElementById('alertIcon');

            alertBox.className = `alert alert-${tipe} shadow-sm py-2 px-3 small d-block mb-3`;
            alertPesan.textContent = pesan;
            alertIcon.className = `me-2 fs-5 ${iconClass}`;
        }

        function setStatusTombol(disabled) {
            const btnMasuk = document.getElementById('btnMasuk');
            const btnPulang = document.getElementById('btnPulang');
            if (btnMasuk && !<?= $sudahMasuk ? 'true' : 'false' ?>) {
                btnMasuk.disabled = disabled;
            }
            if (btnPulang && <?= ($sudahMasuk && !$sudahPulang) ? 'true' : 'false' ?>) {
                btnPulang.disabled = disabled;
            }
        }

        function jalankanPresensi(aksi) {
            setStatusTombol(true);
            tampilkanAlert('info', 'Sedang memproses presensi... Mohon tunggu sebentar.', 'spinner-border spinner-border-sm text-info');

            function kirimAbsen(lat, lng) {
                const formData = new URLSearchParams();
                formData.append('aksi', aksi);
                formData.append('lat', lat);
                formData.append('lng', lng);

                fetch('proses_absen.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: formData.toString()
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Terjadi kendala respon HTTP: ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.sukses) {
                        tampilkanAlert('success', data.pesan, 'bi bi-check-circle-fill text-success');
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    } else {
                        tampilkanAlert('danger', data.pesan, 'bi bi-exclamation-triangle-fill text-danger');
                        setStatusTombol(false);
                    }
                })
                .catch(err => {
                    console.error(err);
                    tampilkanAlert('danger', 'Gagal berkomunikasi dengan server. Silakan coba kembali.', 'bi bi-x-circle-fill text-danger');
                    setStatusTombol(false);
                });
            }

            // Ambil koordinat tanpa memaksakan akurasi satelit (cepat dan tidak timeout/error)
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    function(posisi) {
                        kirimAbsen(posisi.coords.latitude, posisi.coords.longitude);
                    },
                    function(error) {
                        // Fallback koordinat jika GPS diblokir browser laptop/HP
                        kirimAbsen(<?= (float)($siswaData['latitude'] ?? -6.1753924) ?>, <?= (float)($siswaData['longitude'] ?? 106.8271538) ?>);
                    },
                    { enableHighAccuracy: false, timeout: 5000, maximumAge: 60000 }
                );
            } else {
                kirimAbsen(<?= (float)($siswaData['latitude'] ?? -6.1753924) ?>, <?= (float)($siswaData['longitude'] ?? 106.8271538) ?>);
            }
        }
    </script>
<?php endif; ?>

</div>

<!-- Bootstrap 5 JS Bundle CDN -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
