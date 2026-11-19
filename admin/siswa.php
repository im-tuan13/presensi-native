<?php
/**
 * File: admin/siswa.php
 * Kelola Master Data Siswa PKL (CRUD): NIS, Nama, dan Password.
 */

require_once __DIR__ . '/../koneksi.php';
wajibLoginAdmin();

$pesanSukses = '';
$pesanError  = '';

// Proses Simpan (Tambah / Edit) Siswa PKL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_siswa'])) {
    $id       = (int)($_POST['id'] ?? 0);
    $nis      = trim($_POST['nis'] ?? '');
    $nama     = trim($_POST['nama'] ?? '');
    $password = trim($_POST['password'] ?? '');

    // Default lokasi_id = 1 (otomatis terhubung tanpa perlu konfigurasi lokasi manual)
    $lokasiId = 1;

    if ($nis === '' || $nama === '') {
        $pesanError = 'NIS dan Nama Siswa wajib diisi.';
    } else {
        try {
            if ($id > 0) {
                // Mode Edit Siswa
                $stmtCek = $koneksi->prepare('SELECT id FROM siswa WHERE nis = ? AND id != ? LIMIT 1');
                $stmtCek->bind_param('si', $nis, $id);
                $stmtCek->execute();
                $adaNis = $stmtCek->get_result()->fetch_assoc();
                $stmtCek->close();

                if ($adaNis) {
                    $pesanError = 'NIS sudah terdaftar untuk siswa lain.';
                } else {
                    if ($password !== '') {
                        $hash = password_hash($password, PASSWORD_BCRYPT);
                        $stmt = $koneksi->prepare(
                            'UPDATE siswa SET nis = ?, nama = ?, password = ? WHERE id = ?'
                        );
                        $stmt->bind_param('sssi', $nis, $nama, $hash, $id);
                    } else {
                        $stmt = $koneksi->prepare(
                            'UPDATE siswa SET nis = ?, nama = ? WHERE id = ?'
                        );
                        $stmt->bind_param('ssi', $nis, $nama, $id);
                    }
                    $stmt->execute();
                    $stmt->close();
                    $pesanSukses = 'Data siswa PKL berhasil diperbarui.';
                }
            } else {
                // Mode Tambah Siswa Baru
                if ($password === '') {
                    $pesanError = 'Password login siswa wajib diisi.';
                } else {
                    $stmtCek = $koneksi->prepare('SELECT id FROM siswa WHERE nis = ? LIMIT 1');
                    $stmtCek->bind_param('s', $nis);
                    $stmtCek->execute();
                    $adaNis = $stmtCek->get_result()->fetch_assoc();
                    $stmtCek->close();

                    if ($adaNis) {
                        $pesanError = 'NIS sudah terdaftar sebelumnya.';
                    } else {
                        $hash = password_hash($password, PASSWORD_BCRYPT);
                        $stmt = $koneksi->prepare(
                            'INSERT INTO siswa (nis, nama, password, lokasi_id) VALUES (?, ?, ?, ?)'
                        );
                        $stmt->bind_param('sssi', $nis, $nama, $hash, $lokasiId);
                        $stmt->execute();
                        $stmt->close();
                        $pesanSukses = 'Siswa PKL baru berhasil ditambahkan.';
                    }
                }
            }
        } catch (mysqli_sql_exception $e) {
            error_log('Error Simpan Siswa: ' . $e->getMessage());
            $pesanError = 'Terjadi kesalahan sistem saat menyimpan data siswa.';
        }
    }
}

// Proses Hapus Siswa
if (isset($_GET['hapus'])) {
    $hapusId = (int)$_GET['hapus'];
    try {
        $stmtDel = $koneksi->prepare('DELETE FROM siswa WHERE id = ?');
        $stmtDel->bind_param('i', $hapusId);
        $stmtDel->execute();
        $stmtDel->close();
        $pesanSukses = 'Siswa PKL beserta seluruh riwayat presensinya berhasil dihapus.';
    } catch (mysqli_sql_exception $e) {
        error_log('Error Hapus Siswa: ' . $e->getMessage());
        $pesanError = 'Gagal menghapus siswa.';
    }
}

// Ambil daftar seluruh siswa
$querySiswa = "SELECT id, nis, nama FROM siswa ORDER BY id ASC";
$siswaList = $koneksi->query($querySiswa)->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Siswa PKL - Administrator</title>
    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background-color: #f1f5f9; font-family: system-ui, sans-serif; }
        .navbar-admin { background: #0f172a; }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark navbar-admin shadow-sm mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold" href="index.php"><i class="bi bi-shield-check me-2 text-primary"></i>Admin Presensi PKL</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="adminNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link" href="index.php"><i class="bi bi-speedometer2 me-1"></i> Dashboard</a></li>
                <li class="nav-item"><a class="nav-link active fw-bold" href="siswa.php"><i class="bi bi-people me-1"></i> Data Siswa</a></li>
                <li class="nav-item"><a class="nav-link" href="rekap.php"><i class="bi bi-file-earmark-bar-graph me-1"></i> Rekap Presensi</a></li>
            </ul>
            <a href="../logout.php" class="btn btn-outline-danger btn-sm rounded-pill px-3" onclick="return confirm('Apakah Anda yakin ingin logout?');">Keluar</a>
        </div>
    </div>
</nav>

<div class="container mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0">Master Data Siswa PKL</h4>
            <p class="text-muted small mb-0">Kelola akun dan NIS siswa peserta PKL.</p>
        </div>
        <button class="btn btn-primary btn-sm rounded-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalSiswa" onclick="bukaModalTambah()">
            <i class="bi bi-person-plus me-1"></i> Tambah Siswa
        </button>
    </div>

    <?php if (!empty($pesanSukses)): ?>
        <div class="alert alert-success alert-dismissible fade show small py-2" role="alert">
            <i class="bi bi-check-circle-fill me-1"></i> <?= e($pesanSukses) ?>
            <button type="button" class="btn-close p-2" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($pesanError)): ?>
        <div class="alert alert-danger alert-dismissible fade show small py-2" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-1"></i> <?= e($pesanError) ?>
            <button type="button" class="btn-close p-2" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small">
                    <tr>
                        <th class="ps-3" style="width: 50px;">No</th>
                        <th>NIS</th>
                        <th>Nama Lengkap Siswa</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($siswaList)): ?>
                        <tr><td colspan="4" class="text-center text-muted py-4 small">Belum ada data siswa PKL.</td></tr>
                    <?php else: ?>
                        <?php $no = 1; foreach ($siswaList as $s): ?>
                            <tr>
                                <td class="ps-3 small text-muted"><?= $no++ ?></td>
                                <td><code class="fw-bold fs-6"><?= e($s['nis']) ?></code></td>
                                <td><div class="fw-bold text-dark"><?= e($s['nama']) ?></div></td>
                                <td class="text-center">
                                    <button class="btn btn-outline-primary btn-sm me-1 rounded-3" 
                                            onclick='bukaModalEdit(<?= json_encode($s) ?>)'>
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                    <a href="siswa.php?hapus=<?= (int)$s['id'] ?>" 
                                       class="btn btn-outline-danger btn-sm rounded-3" 
                                       onclick="return confirm('Hapus siswa ini beserta seluruh data presensinya?');">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Form Tambah/Edit Siswa -->
<div class="modal fade" id="modalSiswa" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-bottom py-3">
                <h6 class="modal-title fw-bold" id="modalJudul">Tambah Siswa PKL</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="siswa.php" autocomplete="off">
                <input type="hidden" name="id" id="siswa_id" value="0">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nomor Induk Siswa (NIS)</label>
                        <input type="text" name="nis" id="nis" class="form-control" placeholder="Contoh: 12345" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nama Lengkap Siswa</label>
                        <input type="text" name="nama" id="nama" class="form-control" placeholder="Nama lengkap siswa" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Password Akun Siswa</label>
                        <input type="password" name="password" id="password" class="form-control" placeholder="Masukkan password">
                        <small class="text-muted" id="infoPass" style="font-size: 0.75rem;">Wajib diisi untuk siswa baru.</small>
                    </div>
                </div>
                <div class="modal-footer border-top py-2">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="simpan_siswa" class="btn btn-primary btn-sm px-4 fw-semibold">Simpan Siswa</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function bukaModalTambah() {
        document.getElementById('modalJudul').textContent = 'Tambah Siswa PKL Baru';
        document.getElementById('siswa_id').value = '0';
        document.getElementById('nis').value = '';
        document.getElementById('nama').value = '';
        document.getElementById('password').value = '';
        document.getElementById('password').required = true;
        document.getElementById('infoPass').textContent = 'Wajib diisi untuk siswa baru.';
    }

    function bukaModalEdit(data) {
        document.getElementById('modalJudul').textContent = 'Edit Siswa PKL';
        document.getElementById('siswa_id').value = data.id;
        document.getElementById('nis').value = data.nis;
        document.getElementById('nama').value = data.nama;
        document.getElementById('password').value = '';
        document.getElementById('password').required = false;
        document.getElementById('infoPass').textContent = 'Kosongkan jika password tidak ingin diubah.';

        const modal = new bootstrap.Modal(document.getElementById('modalSiswa'));
        modal.show();
    }
</script>
</body>
</html>
