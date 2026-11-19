<?php
/**
 * File: admin/index.php
 * Dashboard Administrator: Statistik utama, ringkasan presensi seluruh siswa,
 * dan navigasi master data.
 */

require_once __DIR__ . '/../koneksi.php';

// Proteksi akses khusus Administrator
wajibLoginAdmin();

$adminNama = $_SESSION['user_nama'] ?? 'Administrator';
$tanggalHariIni = date('Y-m-d');

// 1. Hitung Statistik Master Data Siswa
$totSiswa = (int)$koneksi->query('SELECT COUNT(*) FROM siswa')->fetch_row()[0];

// 2. Hitung Statistik Presensi Hari Ini
$stmtPresensiHariIni = $koneksi->prepare(
    "SELECT 
        SUM(CASE WHEN status = 'Hadir' THEN 1 ELSE 0 END) AS hadir,
        SUM(CASE WHEN status = 'Terlambat' THEN 1 ELSE 0 END) AS terlambat,
        COUNT(id) AS total_absen
     FROM presensi 
     WHERE tanggal = ?"
);
$stmtPresensiHariIni->bind_param('s', $tanggalHariIni);
$stmtPresensiHariIni->execute();
$statPresensi = $stmtPresensiHariIni->get_result()->fetch_assoc();
$stmtPresensiHariIni->close();

$jmlHadir     = (int)($statPresensi['hadir'] ?? 0);
$jmlTerlambat = (int)($statPresensi['terlambat'] ?? 0);
$jmlTotal     = (int)($statPresensi['total_absen'] ?? 0);
$jmlBelum     = max(0, $totSiswa - $jmlTotal);

// 3. Ambil Monitoring Presensi Hari Ini Seluruh Siswa
$queryMonitoring = "SELECT s.id, s.nis, s.nama,
                           p.jam_masuk, p.jam_pulang, p.status
                    FROM siswa s
                    LEFT JOIN presensi p ON s.id = p.siswa_id AND p.tanggal = ?
                    ORDER BY s.id ASC";
$stmtMon = $koneksi->prepare($queryMonitoring);
$stmtMon->bind_param('s', $tanggalHariIni);
$stmtMon->execute();
$listMonitoring = $stmtMon->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtMon->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Administrator - Presensi Siswa PKL</title>
    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f1f5f9;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            color: #1e293b;
        }
        .navbar-admin {
            background: #0f172a;
        }
        .stat-card {
            border: none;
            border-radius: 12px;
            background: #ffffff;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
            transition: transform 0.15s ease;
        }
        .nav-link.active-custom {
            background: rgba(255, 255, 255, 0.12);
            border-radius: 8px;
        }
    </style>
</head>
<body>

<!-- Navbar Admin -->
<nav class="navbar navbar-expand-lg navbar-dark navbar-admin shadow-sm mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold d-flex align-items-center" href="index.php">
            <i class="bi bi-shield-check me-2 fs-4 text-primary"></i>
            <span>Admin Presensi PKL</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="adminNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link active active-custom" href="index.php"><i class="bi bi-speedometer2 me-1"></i> Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="siswa.php"><i class="bi bi-people me-1"></i> Data Siswa</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="rekap.php"><i class="bi bi-file-earmark-bar-graph me-1"></i> Rekap Presensi</a>
                </li>
            </ul>
            <div class="d-flex align-items-center text-white">
                <span class="me-3 small text-white-50"><i class="bi bi-person-circle me-1"></i> <?= e($adminNama) ?></span>
                <a href="../logout.php" class="btn btn-outline-danger btn-sm rounded-pill px-3" onclick="return confirm('Apakah Anda yakin ingin logout?');">
                    <i class="bi bi-box-arrow-right me-1"></i> Keluar
                </a>
            </div>
        </div>
    </div>
</nav>

<div class="container mb-5">

    <!-- Header Tanggal -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0">Panel Administrator</h4>
            <p class="text-muted small mb-0"><i class="bi bi-calendar3 me-1"></i> <?= formatTanggalIndo($tanggalHariIni) ?></p>
        </div>
        <div>
            <a href="rekap.php" class="btn btn-primary btn-sm rounded-3 shadow-sm">
                <i class="bi bi-printer me-1"></i> Rekapitulasi Presensi
            </a>
        </div>
    </div>

    <!-- 3 Kartu Statistik Ringkasan -->
    <div class="row g-3 mb-4">
        <div class="col-md-4 col-sm-6">
            <div class="card stat-card p-3 border-start border-primary border-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold">Total Siswa PKL</div>
                        <h3 class="fw-bold mb-0 text-dark mt-1"><?= $totSiswa ?></h3>
                    </div>
                    <i class="bi bi-people fs-1 text-primary opacity-25"></i>
                </div>
                <div class="mt-2 small text-muted">
                    <a href="siswa.php" class="text-decoration-none text-primary">Kelola Data Siswa &rarr;</a>
                </div>
            </div>
        </div>

        <div class="col-md-4 col-sm-6">
            <div class="card stat-card p-3 border-start border-success border-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold">Presensi Masuk Hari Ini</div>
                        <h3 class="fw-bold mb-0 text-success mt-1"><?= $jmlTotal ?> <span class="fs-6 fw-normal text-muted">/ <?= $totSiswa ?> Siswa</span></h3>
                    </div>
                    <i class="bi bi-check2-circle fs-1 text-success opacity-25"></i>
                </div>
                <div class="mt-2 small text-muted">
                    <span class="badge bg-success bg-opacity-10 text-success me-1"><?= $jmlHadir ?> Tepat</span>
                    <span class="badge bg-warning bg-opacity-10 text-warning"><?= $jmlTerlambat ?> Terlambat</span>
                </div>
            </div>
        </div>

        <div class="col-md-4 col-sm-12">
            <div class="card stat-card p-3 border-start border-danger border-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold">Belum Absen Hari Ini</div>
                        <h3 class="fw-bold mb-0 text-danger mt-1"><?= $jmlBelum ?> <span class="fs-6 fw-normal text-muted">Siswa</span></h3>
                    </div>
                    <i class="bi bi-clock-history fs-1 text-danger opacity-25"></i>
                </div>
                <div class="mt-2 small text-muted">
                    <span class="badge bg-danger bg-opacity-10 text-danger">Belum Hadir</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabel Monitoring Presensi Hari Ini Seluruh Siswa -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h6 class="fw-bold mb-0 text-dark">
                <i class="bi bi-broadcast me-1 text-danger"></i> Monitoring Presensi Siswa Hari Ini (<?= date('d/m/Y') ?>)
            </h6>
            <span class="badge bg-light text-dark border"><?= count($listMonitoring) ?> Siswa Terdaftar</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small">
                    <tr>
                        <th class="ps-3" style="width: 50px;">No</th>
                        <th>NIS & Nama Siswa</th>
                        <th class="text-center">Jam Masuk</th>
                        <th class="text-center">Jam Pulang</th>
                        <th class="text-center">Status Kehadiran</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($listMonitoring)): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4 small">Belum ada siswa yang terdaftar.</td>
                        </tr>
                    <?php else: ?>
                        <?php $no = 1; foreach ($listMonitoring as $m): ?>
                            <tr>
                                <td class="ps-3 small text-muted"><?= $no++ ?></td>
                                <td>
                                    <div class="fw-bold text-dark"><?= e($m['nama']) ?></div>
                                    <div class="text-muted small">NIS: <?= e($m['nis']) ?></div>
                                </td>
                                <td class="text-center">
                                    <?= !empty($m['jam_masuk']) ? e(substr($m['jam_masuk'], 0, 5)) . ' WIB' : '<span class="text-muted">-</span>' ?>
                                </td>
                                <td class="text-center">
                                    <?= !empty($m['jam_pulang']) ? e(substr($m['jam_pulang'], 0, 5)) . ' WIB' : '<span class="text-muted">-</span>' ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($m['status'] === 'Hadir'): ?>
                                        <span class="badge bg-success">Hadir (Tepat Waktu)</span>
                                    <?php elseif ($m['status'] === 'Terlambat'): ?>
                                        <span class="badge bg-warning text-dark">Terlambat</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Belum Absen</span>
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

<!-- Bootstrap 5 JS Bundle CDN -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
