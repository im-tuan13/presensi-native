<?php
/**
 * File: admin/rekap.php
 * Rekapitulasi Presensi Seluruh Siswa PKL: Filter tanggal & status serta cetak laporan.
 */

require_once __DIR__ . '/../koneksi.php';
wajibLoginAdmin();

$tglMulai     = $_GET['tgl_mulai'] ?? date('Y-m-01');
$tglSelesai   = $_GET['tgl_selesai'] ?? date('Y-m-d');
$filterStatus = trim($_GET['status'] ?? '');

// Buat query dinamis berdasarkan filter
$query = "SELECT p.id, p.tanggal, p.jam_masuk, p.jam_pulang, p.status,
                 s.nis, s.nama AS nama_siswa
          FROM presensi p
          JOIN siswa s ON p.siswa_id = s.id
          WHERE p.tanggal BETWEEN ? AND ?";
$params = [$tglMulai, $tglSelesai];
$types  = 'ss';

if ($filterStatus === 'Hadir' || $filterStatus === 'Terlambat') {
    $query .= " AND p.status = ?";
    $params[] = $filterStatus;
    $types   .= 's';
}

$query .= " ORDER BY p.tanggal DESC, p.jam_masuk DESC";

$stmt = $koneksi->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$rekapList = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Hitung statistik hasil filter
$totalRekap = count($rekapList);
$totalHadir = 0;
$totalTelat = 0;
foreach ($rekapList as $row) {
    if ($row['status'] === 'Hadir') $totalHadir++;
    if ($row['status'] === 'Terlambat') $totalTelat++;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rekapitulasi Presensi - Administrator</title>
    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background-color: #f1f5f9; font-family: system-ui, sans-serif; }
        .navbar-admin { background: #0f172a; }
        @media print {
            .no-print { display: none !important; }
            body { background: #ffffff !important; }
            .card { border: none !important; box-shadow: none !important; }
            .table-responsive { overflow: visible !important; }
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark navbar-admin shadow-sm mb-4 no-print">
    <div class="container">
        <a class="navbar-brand fw-bold" href="index.php"><i class="bi bi-shield-check me-2 text-primary"></i>Admin Presensi PKL</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="adminNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link" href="index.php"><i class="bi bi-speedometer2 me-1"></i> Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="siswa.php"><i class="bi bi-people me-1"></i> Data Siswa</a></li>
                <li class="nav-item"><a class="nav-link active fw-bold" href="rekap.php"><i class="bi bi-file-earmark-bar-graph me-1"></i> Rekap Presensi</a></li>
            </ul>
            <a href="../logout.php" class="btn btn-outline-danger btn-sm rounded-pill px-3" onclick="return confirm('Apakah Anda yakin ingin logout?');">Keluar</a>
        </div>
    </div>
</nav>

<div class="container mb-5">
    
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0">Laporan Rekapitulasi Presensi</h4>
            <p class="text-muted small mb-0">Arsip data kehadiran seluruh siswa PKL.</p>
        </div>
        <div class="no-print">
            <button onclick="window.print()" class="btn btn-dark btn-sm rounded-3 shadow-sm">
                <i class="bi bi-printer me-1"></i> Cetak Dokumen
            </button>
        </div>
    </div>

    <!-- Filter Box -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 no-print">
        <div class="card-body p-3">
            <form method="GET" action="rekap.php" class="row g-2 align-items-end">
                <div class="col-6 col-md-4">
                    <label class="form-label small fw-semibold text-muted">Tanggal Mulai</label>
                    <input type="date" name="tgl_mulai" class="form-control form-control-sm" value="<?= e($tglMulai) ?>">
                </div>
                <div class="col-6 col-md-4">
                    <label class="form-label small fw-semibold text-muted">Tanggal Selesai</label>
                    <input type="date" name="tgl_selesai" class="form-control form-control-sm" value="<?= e($tglSelesai) ?>">
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label small fw-semibold text-muted">Status Kehadiran</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">-- Semua Status --</option>
                        <option value="Hadir" <?= $filterStatus === 'Hadir' ? 'selected' : '' ?>>Hadir (Tepat Waktu)</option>
                        <option value="Terlambat" <?= $filterStatus === 'Terlambat' ? 'selected' : '' ?>>Terlambat</option>
                    </select>
                </div>
                <div class="col-12 mt-2">
                    <button type="submit" class="btn btn-primary btn-sm px-4 fw-semibold">
                        <i class="bi bi-funnel me-1"></i> Terapkan Filter
                    </button>
                    <a href="rekap.php" class="btn btn-light btn-sm ms-1 border">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Ringkasan Filter -->
    <div class="row g-2 mb-3">
        <div class="col-4">
            <div class="bg-white border rounded-3 p-2 text-center shadow-sm">
                <div class="text-muted small" style="font-size: 0.75rem;">Total Presensi</div>
                <div class="fw-bold fs-5 text-dark"><?= $totalRekap ?></div>
            </div>
        </div>
        <div class="col-4">
            <div class="bg-white border rounded-3 p-2 text-center shadow-sm">
                <div class="text-muted small" style="font-size: 0.75rem;">Tepat Waktu</div>
                <div class="fw-bold fs-5 text-success"><?= $totalHadir ?></div>
            </div>
        </div>
        <div class="col-4">
            <div class="bg-white border rounded-3 p-2 text-center shadow-sm">
                <div class="text-muted small" style="font-size: 0.75rem;">Terlambat</div>
                <div class="fw-bold fs-5 text-warning"><?= $totalTelat ?></div>
            </div>
        </div>
    </div>

    <!-- Tabel Rekapitulasi -->
    <div class="card border-0 shadow-sm rounded-4">
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle mb-0 text-center" style="font-size: 0.88rem;">
                <thead class="table-light">
                    <tr>
                        <th style="width: 40px;">No</th>
                        <th>Tanggal</th>
                        <th class="text-start">Nama Siswa (NIS)</th>
                        <th>Jam Masuk</th>
                        <th>Jam Pulang</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rekapList)): ?>
                        <tr><td colspan="6" class="text-muted py-4 small">Tidak ada rekaman presensi pada filter ini.</td></tr>
                    <?php else: ?>
                        <?php $no = 1; foreach ($rekapList as $r): ?>
                            <tr>
                                <td class="small text-muted"><?= $no++ ?></td>
                                <td class="text-nowrap fw-semibold"><?= e(date('d/m/Y', strtotime($r['tanggal']))) ?></td>
                                <td class="text-start">
                                    <div class="fw-bold text-dark"><?= e($r['nama_siswa']) ?></div>
                                    <div class="text-muted small">NIS: <?= e($r['nis']) ?></div>
                                </td>
                                <td><?= !empty($r['jam_masuk']) ? e(substr($r['jam_masuk'], 0, 5)) . ' WIB' : '-' ?></td>
                                <td><?= !empty($r['jam_pulang']) ? e(substr($r['jam_pulang'], 0, 5)) . ' WIB' : '-' ?></td>
                                <td>
                                    <?php if ($r['status'] === 'Hadir'): ?>
                                        <span class="badge bg-success">Hadir (Tepat Waktu)</span>
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

<!-- Bootstrap 5 JS Bundle CDN -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
