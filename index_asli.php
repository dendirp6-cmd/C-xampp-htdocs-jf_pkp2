<?php
// 1. Inisialisasi Session & Proteksi Halaman
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'koneksi.php'; 

// Pastikan user login dan memiliki role yang sesuai
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// --- LOGIKA AMBIL DATA ---

// Ambil data users untuk kolom kiri (Daftar Pengguna)
$data_users_admin = [];
$res_users = mysqli_query($conn, "SELECT id, nama, email, instansi, role, status FROM users ORDER BY id DESC");
if($res_users) {
    while($row = mysqli_fetch_assoc($res_users)) {
        $data_users_admin[] = $row;
    }
}

// Ambil data terbaru dari tb_admin_update untuk kolom kanan (Update Konten)
$query_data = mysqli_query($conn, "SELECT * FROM tb_admin_update WHERE id = 1 LIMIT 1");
$data_konten = mysqli_fetch_assoc($query_data) ?: [];

// Ambil data gelombang aktif (Limit 5 untuk tampilan dashboard)
$data_gelombang = [];
$sql_gelombang = "SELECT g.*, 
                  (SELECT COUNT(*) FROM pengajuan_ujikom p WHERE p.gelombang = g.id AND p.status_pengajuan = 'Terjadwal') as total_terjadwal,
                  (SELECT COUNT(*) FROM pengajuan_ujikom p WHERE p.gelombang = g.id AND p.status_pengajuan = 'Lulus') as total_lulus,
                  (SELECT COUNT(*) FROM pengajuan_ujikom p WHERE p.gelombang = g.id AND p.status_pengajuan = 'Tidak Lulus') as total_tidak_lulus
                  FROM tb_gelombang g 
                  ORDER BY g.id DESC LIMIT 5";
$res_g = mysqli_query($conn, $sql_gelombang);
if($res_g) {
    while($row = mysqli_fetch_assoc($res_g)) { $data_gelombang[] = $row; }
}

// Ambil data pengajuan untuk tabel bawah (Perpindahan Jabatan)
$data_perpindahan = [];
$res_p = mysqli_query($conn, "SELECT * FROM pengajuan_ujikom WHERE jenis_pengajuan LIKE '%Perpindahan%' ORDER BY id DESC LIMIT 5");
if($res_p) {
    while($row = mysqli_fetch_assoc($res_p)) { $data_perpindahan[] = $row; }
}

// Ambil data pengajuan untuk tabel bawah (Rekomendasi Formasi)
$data_rekom = [];
$res_r = mysqli_query($conn, "SELECT * FROM rekomendasi_formasi ORDER BY id DESC LIMIT 5");
if($res_r) {
    while($row = mysqli_fetch_assoc($res_r)) { $data_rekom[] = $row; }
}

// Fungsi Pewarnaan Status
function getStatusBadge($status) {
    $status = strtoupper($status);
    switch ($status) {
        case 'ACTIVE': case 'DISETUJUI VERIFIKATOR': return 'bg-success';
        case 'PENDING': case 'PROSES PSDM': return 'bg-warning text-dark';
        case 'PERLU PERBAIKAN': case 'REJECTED': return 'bg-danger';
        case 'LULUS': return 'bg-primary';
        default: return 'bg-secondary';
    }
}

$page = 'dashboard';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard Admin | Instansi Pembina JF</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap4.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        .content-wrapper { background-color: #f4f6f9; }
        .card-header { font-weight: bold; }
        .table-sm td, .table-sm th { font-size: 0.85rem; vertical-align: middle; }
        .bg-purple { background-color: #6f42c1 !important; color: white; }
        .bg-teal { background-color: #20c997 !important; color: white; }
        .form-label-sm { font-size: 0.75rem; font-weight: bold; margin-bottom: 2px; display: block; }

        /* --- ENHANCED SWITCH UI --- */
        .custom-switch-container {
            padding: 15px;
            border-radius: 12px;
            border: 2px solid #eaecf0;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            background: #ffffff;
            cursor: pointer;
        }

        .custom-switch-container.active {
            border-color: #1cc88a;
            background: linear-gradient(to right, #ffffff, #f0fff4);
            box-shadow: 0 5px 15px rgba(28, 200, 138, 0.1);
        }

        .custom-switch-container.inactive {
            border-color: #858796;
            background: linear-gradient(to right, #ffffff, #f8f9fc);
        }

        .status-indicator-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }

        .active .status-indicator-icon {
            background: rgba(28, 200, 138, 0.1);
            color: #1cc88a;
        }

        .inactive .status-indicator-icon {
            background: #f1f2f4;
            color: #858796;
        }

        .status-text-main {
            font-weight: 800;
            font-size: 1rem;
            margin-bottom: 0;
            line-height: 1.2;
        }

        .form-switch .custom-switch-input {
            width: 2.5rem !important;
            height: 1.25rem !important;
            margin-left: 0 !important;
            float: none !important;
            cursor: pointer;
            position: relative !important;
        }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

    <?php include 'template/navbar.php'; ?>
    <?php include 'template/sidebar.php'; ?> 

    <div class="content-wrapper">
        <section class="content-header">
            <div class="container-fluid">
                <h1 class="font-weight-bold text-white">Dashboard Sistem</h1>
            </div>
        </section>

        <section class="content">
            <div class="container-fluid">
                
                <div class="row">
                    <div class="col-lg-6 mb-4">
                        <div class="card card-primary card-outline shadow-sm h-100">
                            <div class="card-header border-0">
                                <h3 class="card-title"><i class="fas fa-users-cog mr-2 text-primary"></i> Daftar Pengguna (Administrasi)</h3>
                            </div>
                            <div class="card-body p-2">
                                <table id="tabelUsers" class="table table-sm table-hover w-100">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Nama / Email</th>
                                            <th>Role</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($data_users_admin as $u): ?>
                                        <tr>
                                            <td><?= $u['id']; ?></td>
                                            <td>
                                                <strong><?= htmlspecialchars($u['nama']); ?></strong><br>
                                                <small class="text-muted"><?= htmlspecialchars($u['email']); ?></small>
                                            </td>
                                            <td><span class="badge badge-light border"><?= $u['role']; ?></span></td>
                                            <td><span class="badge <?= getStatusBadge($u['status']); ?>"><?= $u['status']; ?></span></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6 mb-4">
                        <div class="card card-info card-outline shadow-sm h-100">
                            <div class="card-header border-0">
                                <h3 class="card-title"><i class="fas fa-edit mr-2 text-info"></i> Update Konten Portal</h3>
                            </div>
                            <div class="card-body">
                                <form action="proses_update.php" method="POST" enctype="multipart/form-data">
                                    <div class="mb-3">
                                        <label class="form-label-sm">JUDUL PENGUMUMAN UTAMA</label>
                                        <input type="text" name="judul" class="form-control form-control-sm" value="<?= htmlspecialchars($data_konten['judul_pengumuman'] ?? '') ?>" required>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label-sm">STATUS PENDAFTARAN</label>
                                        <div id="switchBox" class="custom-switch-container d-flex align-items-center <?= ($data_konten['status_form_pj'] ?? 0) == 1 ? 'active' : 'inactive' ?>" onclick="toggleSwitch()">
                                            <div class="status-indicator-icon mr-3">
                                                <i id="statusIcon" class="fas <?= ($data_konten['status_form_pj'] ?? 0) == 1 ? 'fa-lock-open' : 'fa-lock' ?>"></i>
                                            </div>

                                            <div class="form-check form-switch m-0 p-0 mr-3 d-flex align-items-center">
                                                <div class="custom-control custom-switch">
                                                    <input type="checkbox" class="custom-control-input" name="status_pj" value="1" id="switchPJ" 
                                                        <?= ($data_konten['status_form_pj'] ?? 0) == 1 ? 'checked' : '' ?> 
                                                        onchange="updateSwitchUI(this)" 
                                                        onclick="event.stopPropagation();">
                                                    <label class="custom-control-label" for="switchPJ" onclick="event.stopPropagation();"></label>
                                                </div>
                                            </div>
                                            
                                            <div class="flex-grow-1">
                                                <p class="text-muted mb-0" style="font-size: 0.65rem; font-weight: 800; letter-spacing: 0.5px;">UBAH STATUS</p>
                                                <h5 id="statusLabelText" class="status-text-main fw-bold mb-0 <?= ($data_konten['status_form_pj'] ?? 0) == 1 ? 'text-success' : 'text-secondary' ?>">
                                                    <?= ($data_konten['status_form_pj'] ?? 0) == 1 ? 'DIBUKA' : 'DITUTUP' ?>
                                                </h5>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-6 mb-3">
                                            <label class="form-label-sm">Kenaikan Jabatan(Pembukaan)</label>
                                            <input type="text" name="kj_daftar" class="form-control form-control-sm" value="<?= htmlspecialchars($data_konten['kj_tgl_daftar'] ?? '') ?>">
                                        </div>
                                        <div class="col-6 mb-3">
                                            <label class="form-label-sm">Kenaikan Jabatan(Sampai)</label>
                                            <input type="text" name="kj_ujian" class="form-control form-control-sm" value="<?= htmlspecialchars($data_konten['kj_tgl_ujian'] ?? '') ?>">
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-6 mb-3">
                                            <label class="form-label-sm">Perpindahan Jabatan (Pembukaan)</label>
                                            <input type="text" name="pj_daftar" class="form-control form-control-sm" value="<?= htmlspecialchars($data_konten['pj_tgl_daftar'] ?? '') ?>">
                                        </div>
                                        <div class="col-6 mb-3">
                                            <label class="form-label-sm">Perpindahan Jabatan (Sampai)</label>
                                            <input type="text" name="pj_ujian" class="form-control form-control-sm" value="<?= htmlspecialchars($data_konten['pj_tgl_ujian'] ?? '') ?>">
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label-sm">UPLOAD SURAT (PDF)</label>
                                        <input type="file" name="surat_file[]" class="form-control form-control-sm" accept=".pdf" multiple>
                                    </div>
                                    <button type="submit" name="update_all" class="btn btn-info btn-sm btn-block shadow-sm">
                                        <i class="fas fa-save mr-2"></i> SIMPAN PERUBAHAN KONTEN
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-2">
                    <div class="col-12 mb-4">
                        <div class="card shadow-sm">
                            <div class="card-header bg-dark d-flex justify-content-between align-items-center">
                                <h3 class="card-title text-white"><i class="fas fa-layer-group mr-2"></i> Daftar Gelombang Aktif</h3>
                                <a href="admin_update.php" class="ml-auto text-white small">Kelola Gelombang</a>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-sm table-hover m-0">
                                    <thead class="bg-light">
                                        <tr>
                                            <th>Gelombang</th>
                                            <th>Bulan</th>
                                            <th>Jenis Pengajuan</th>
                                            <th>Surat Pengumuman</th>
                                            <th class="text-center">Statistik Peserta</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if(empty($data_gelombang)): ?>
                                            <tr><td colspan="5" class="text-center p-3 text-muted">Belum ada data gelombang.</td></tr>
                                        <?php else: ?>
                                            <?php foreach($data_gelombang as $g): ?>
                                            <tr>
                                                <td class="font-weight-bold text-dark"><?= htmlspecialchars($g['gelombang'] ?? '-') ?></td>
                                                <td><i class="far fa-calendar-alt mr-1 text-muted"></i> <?= htmlspecialchars($g['bln_gelombang'] ?? '-') ?></td>
                                                <td>
                                                    <span class="badge badge-light border">
                                                        <i class="fas fa-tag mr-1 text-primary"></i> <?= htmlspecialchars($g['jenis_pengajuan'] ?? '-') ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if(!empty($g['surat_gelombang'])): ?>
                                                        <a href="uploads/pengumuman/<?= $g['surat_gelombang'] ?>" target="_blank" class="text-primary text-decoration-none small">
                                                            <i class="fas fa-file-pdf mr-1 text-danger"></i> Lihat File
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-muted small">File Kosong</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge bg-warning text-dark border px-2 py-1 mr-1" title="Menunggu Proses">
                                                        <?= number_format($g['total_terjadwal'] ?? 0) ?> Proses
                                                    </span>
                                                    <span class="badge bg-success border px-2 py-1 mr-1" title="Lulus">
                                                        <?= number_format($g['total_lulus'] ?? 0) ?> Lulus
                                                    </span>
                                                    <span class="badge bg-danger border px-2 py-1" title="Tidak Lulus">
                                                        <?= number_format($g['total_tidak_lulus'] ?? 0) ?> T. Lulus
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-2">
                    <div class="col-12 mb-4">
                        <div class="card shadow-sm">
                            <div class="card-header bg-purple d-flex justify-content-between align-items-center">
                                <h3 class="card-title"><i class="fas fa-exchange-alt mr-2"></i> Data Pengajuan Khusus Perpindahan Jabatan</h3>
                                <a href="list_perpindahan.php" class="ml-auto text-white small">Lihat Semua</a>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-sm table-hover m-0">
                                    <thead class="bg-light">
                                        <tr>
                                            <th>#</th><th>NIP</th><th>Nama</th><th>Jenis Pengajuan</th><th>Tgl Pengajuan</th><th>Status</th><th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if(empty($data_perpindahan)): ?>
                                            <tr><td colspan="7" class="text-center p-3 text-muted">Tidak ada data.</td></tr>
                                        <?php else: ?>
                                            <?php $n=1; foreach($data_perpindahan as $p): ?>
                                            <tr>
                                                <td><?= $n++ ?></td>
                                                <td><?= $p['nip'] ?></td>
                                                <td><?= $p['nama'] ?></td>
                                                <td><?= $p['jenis_pengajuan'] ?></td>
                                                <td><?= date('d-m-Y', strtotime($p['tanggal_pengajuan'])) ?></td>
                                                <td><span class="badge <?= getStatusBadge($p['status_pengajuan']) ?>"><?= $p['status_pengajuan'] ?></span></td>
                                                <td><button class="btn btn-xs btn-info"><i class="fas fa-search"></i></button></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-2">
                    <div class="col-12">
                        <div class="card shadow-sm">
                            <div class="card-header bg-teal d-flex justify-content-between align-items-center">
                                <h3 class="card-title text-white"><i class="fas fa-file-signature mr-2"></i> Data Pengajuan Rekomendasi Formasi</h3>
                                <a href="#" class="ml-auto text-white small">Lihat Semua</a>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-sm table-hover m-0">
                                    <thead class="bg-light">
                                        <tr>
                                            <th>#</th><th>Tgl Pengajuan</th><th>Nama Pengusul</th><th>NIP</th><th>Instansi</th><th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if(empty($data_rekom)): ?>
                                            <tr><td colspan="6" class="text-center p-3 text-muted">Tidak ada data terbaru.</td></tr>
                                        <?php else: ?>
                                            <?php $n=1; foreach($data_rekom as $r): ?>
                                            <tr>
                                                <td><?= $n++ ?></td>
                                                <td><?= date('d-m-Y', strtotime($r['tanggal_pengajuan'])) ?></td>
                                                <td><?= $r['nama_pengusul'] ?></td>
                                                <td><?= $r['nip'] ?></td>
                                                <td><?= $r['instansi'] ?></td>
                                                <td><span class="badge <?= getStatusBadge($r['status']) ?>"><?= $r['status'] ?></span></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </section>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap4.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>

<script>
    $(document).ready(function() {
        $('#tabelUsers').DataTable({
            "paging": true,
            "lengthChange": false,
            "searching": true,
            "ordering": true,
            "info": false,
            "autoWidth": false,
            "responsive": true,
            "pageLength": 5
        });
    });

    // FUNGSI ANIMASI UI SWITCH
    function toggleSwitch() {
        const checkbox = document.getElementById('switchPJ');
        checkbox.checked = !checkbox.checked;
        updateSwitchUI(checkbox);
    }

    function updateSwitchUI(el) {
        const box = document.getElementById('switchBox');
        const labelText = document.getElementById('statusLabelText');
        const icon = document.getElementById('statusIcon');
        
        if(el.checked) {
            box.classList.remove('inactive');
            box.classList.add('active');
            labelText.innerText = 'DIBUKA';
            labelText.classList.remove('text-secondary');
            labelText.classList.add('text-success');
            icon.classList.remove('fa-lock');
            icon.classList.add('fa-lock-open');
        } else {
            box.classList.remove('active');
            box.classList.add('inactive');
            labelText.innerText = 'DITUTUP';
            labelText.classList.remove('text-success');
            labelText.classList.add('text-secondary');
            icon.classList.remove('fa-lock-open');
            icon.classList.add('fa-lock');
        }
    }
</script>

<?php if (isset($_GET['status']) && $_GET['status'] == 'success'): ?>
<script>
    Swal.fire({ title: 'Berhasil!', text: 'Konten portal diperbarui.', icon: 'success' });
</script>
<?php endif; ?>

</body>
</html>