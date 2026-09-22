<?php
// 1. Inisialisasi Session & Proteksi Halaman
session_start();

// Koneksi Database
require_once 'koneksi.php'; 

// Cek Login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Cek Role & Hak Akses
$allowed_roles = ['user_admin', 'user_super_admin', 'user_verifikator', 'user_kasubdit'];
if (!isset($_SESSION['user_role_sesi']) || !in_array($_SESSION['user_role_sesi'], $allowed_roles)) {
    header("Location: index.php?error=unauthorized");
    exit;
}

$user_role = $_SESSION['user_role_sesi'];

// Folder Penyimpanan File LPJ
$upload_dir = 'uploads/lpj/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// =========================================================================
// DATA DUMMY / QUERY DATA REALISASI
// =========================================================================
$data_realisasi = [
    [
        'id'                => 1,
        'kode'              => 'BMK-2026-001',
        'nama_kegiatan'     => 'Bimtek Jabatan Fungsional Angkatan I',
        'tanggal'           => '15 - 18 Februari 2026',
        'target_peserta'    => 50,
        'realisasi_peserta' => 48,
        'pagu_anggaran'     => 150000000,
        'realisasi_anggaran'=> 142500000,
        'status'            => 'Selesai',
        'dokumen_lpj'       => 'lpj_angkatan_1.pdf'
    ],
    [
        'id'                => 2,
        'kode'              => 'BMK-2026-002',
        'nama_kegiatan'     => 'Bimtek Penilaian Kinerja & Portofolio',
        'tanggal'           => '10 - 12 Mei 2026',
        'target_peserta'    => 40,
        'realisasi_peserta' => 40,
        'pagu_anggaran'     => 120000000,
        'realisasi_anggaran'=> 118000000,
        'status'            => 'Selesai',
        'dokumen_lpj'       => 'lpj_angkatan_2.pdf'
    ],
    [
        'id'                => 3,
        'kode'              => 'BMK-2026-003',
        'nama_kegiatan'     => 'Bimtek Penyusunan Karya Tulis Ilmiah',
        'tanggal'           => '20 - 22 Agustus 2026',
        'target_peserta'    => 60,
        'realisasi_peserta' => 35,
        'pagu_anggaran'     => 180000000,
        'realisasi_anggaran'=> 95000000,
        'status'            => 'Berjalan',
        'dokumen_lpj'       => ''
    ]
];

// METRICS REKAP
$total_pagu      = array_sum(array_column($data_realisasi, 'pagu_anggaran'));
$total_realisasi = array_sum(array_column($data_realisasi, 'realisasi_anggaran'));
$total_kegiatan  = count($data_realisasi);
$persen_total    = ($total_pagu > 0) ? round(($total_realisasi / $total_pagu) * 100, 1) : 0;

// Load Header (Sesuai materi_bimtek.php)
include 'template/header.php'; 
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">

<style>
    .header-banner {
        background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
        border-radius: 16px;
        color: white;
        padding: 30px;
        margin-bottom: 30px;
        box-shadow: 0 10px 20px rgba(78, 115, 223, 0.2);
    }
    
    .card-stat {
        border: none;
        border-radius: 15px;
        transition: all 0.3s ease;
        background: #ffffff;
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    }

    .icon-box-stat {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
    }
</style>

<!-- Structure Wrapper Persis materi_bimtek.php -->
<div class="d-flex" id="wrapper" style="min-height: 100vh;">
    <!-- Sidebar -->
    <aside id="sidebar-container" style="width: 250px; flex-shrink: 0; background-color: #2c3e50;">
        <?php include 'template/sidebar.php'; ?>
    </aside>

    <!-- Main Content -->
    <main id="page-content-wrapper" class="flex-grow-1" style="background-color: #f8f9fc; min-width: 0;">
        <div class="container-fluid p-4">
            
            <!-- Banner Header (Gaya materi_bimtek.php) -->
            <div class="header-banner d-flex justify-content-between align-items-center flex-wrap gap-3 animate__animated animate__fadeIn">
                <div>
                    <h2 class="fw-bold mb-1"><i class="fas fa-chart-line me-2"></i> Progres Realisasi PJF</h2>
                    <p class="mb-0 opacity-75">Pantauan capaian target peserta, anggaran, dan berkas LPJ kegiatan PJF</p>
                </div>
                <div>
                    <button type="button" class="btn btn-light text-primary fw-bold px-4 py-2 rounded-pill shadow-sm" onclick="tambahRealisasi()">
                        <i class="fas fa-plus-circle me-2"></i> Tambah Data Realisasi
                    </button>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="row g-3 mb-4">
                <div class="col-xl-3 col-md-6">
                    <div class="card card-stat p-3">
                        <div class="d-flex align-items-center">
                            <div class="icon-box-stat bg-primary bg-opacity-10 text-primary me-3">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <div>
                                <small class="text-muted fw-bold d-block">TOTAL KEGIATAN</small>
                                <h4 class="fw-bold mb-0"><?= $total_kegiatan ?> <small class="fs-6 text-muted">Kegiatan</small></h4>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card card-stat p-3">
                        <div class="d-flex align-items-center">
                            <div class="icon-box-stat bg-info bg-opacity-10 text-info me-3">
                                <i class="fas fa-wallet"></i>
                            </div>
                            <div>
                                <small class="text-muted fw-bold d-block">PAGU ANGGARAN</small>
                                <h5 class="fw-bold mb-0 text-dark">Rp <?= number_format($total_pagu, 0, ',', '.') ?></h5>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card card-stat p-3">
                        <div class="d-flex align-items-center">
                            <div class="icon-box-stat bg-success bg-opacity-10 text-success me-3">
                                <i class="fas fa-hand-holding-usd"></i>
                            </div>
                            <div>
                                <small class="text-muted fw-bold d-block">REALISASI ANGGARAN</small>
                                <h5 class="fw-bold mb-0 text-success">Rp <?= number_format($total_realisasi, 0, ',', '.') ?></h5>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card card-stat p-3">
                        <div class="d-flex align-items-center">
                            <div class="icon-box-stat bg-warning bg-opacity-10 text-warning me-3">
                                <i class="fas fa-percentage"></i>
                            </div>
                            <div>
                                <small class="text-muted fw-bold d-block">CAPAIAN SERAPAN</small>
                                <h4 class="fw-bold mb-0 text-warning"><?= $persen_total ?>%</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Table Card -->
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 50px;">No</th>
                                    <th>Kegiatan Bimtek</th>
                                    <th>Jadwal</th>
                                    <th>Peserta</th>
                                    <th>Anggaran (Pagu / Realisasi)</th>
                                    <th style="width: 15%;">Capaian</th>
                                    <th>Status</th>
                                    <th class="text-center" style="width: 100px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $no = 1; foreach ($data_realisasi as $row): 
                                    $persen = ($row['pagu_anggaran'] > 0) ? round(($row['realisasi_anggaran'] / $row['pagu_anggaran']) * 100, 1) : 0;
                                ?>
                                <tr>
                                    <td class="fw-bold"><?= $no++; ?></td>
                                    <td>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($row['nama_kegiatan']); ?></div>
                                        <small class="text-muted"><i class="fas fa-barcode me-1"></i><?= $row['kode']; ?></small>
                                    </td>
                                    <td><small class="text-muted"><i class="far fa-calendar-alt me-1"></i><?= $row['tanggal']; ?></small></td>
                                    <td><small><i class="fas fa-users me-1 text-primary"></i><?= $row['realisasi_peserta']; ?> / <?= $row['target_peserta']; ?> Orang</small></td>
                                    <td>
                                        <small class="d-block text-muted">Pagu: Rp <?= number_format($row['pagu_anggaran'], 0, ',', '.'); ?></small>
                                        <strong class="text-success">Real: Rp <?= number_format($row['realisasi_anggaran'], 0, ',', '.'); ?></strong>
                                    </td>
                                    <td>
                                        <div class="d-flex justify-content-between small fw-bold mb-1">
                                            <span><?= $persen ?>%</span>
                                        </div>
                                        <div class="progress" style="height: 6px;">
                                            <div class="progress-bar bg-success" role="progressbar" style="width: <?= $persen ?>%"></div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($row['status'] == 'Selesai'): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3">Selesai</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-3">Berjalan</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <button class="btn btn-sm btn-outline-info rounded-2" onclick='detailRealisasi(<?= json_encode($row) ?>)' title="Detail">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </main>
</div>

<!-- MODAL DETAIL (Menggunakan Bootstrap 5 persis seperti materi_bimtek.php) -->
<div class="modal fade" id="modalDetailRealisasi" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-info text-white p-4">
                <h5 class="modal-title fw-bold"><i class="fas fa-info-circle me-2"></i> Detail Realisasi Bimtek</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="detail_content">
                <!-- Content diinjeksi via JavaScript -->
            </div>
            <div class="modal-footer border-0 p-3 pt-0">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
function detailRealisasi(data) {
    const formattedPagu = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(data.pagu_anggaran);
    const formattedReal = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(data.realisasi_anggaran);
    
    let html = `
        <table class="table table-borderless mb-0">
            <tr><th width="40%" class="text-muted">Kode Kegiatan</th><td>: <strong>${data.kode}</strong></td></tr>
            <tr><th class="text-muted">Nama Kegiatan</th><td>: ${data.nama_kegiatan}</td></tr>
            <tr><th class="text-muted">Jadwal</th><td>: ${data.tanggal}</td></tr>
            <tr><th class="text-muted">Peserta</th><td>: ${data.realisasi_peserta} / ${data.target_peserta} Orang</td></tr>
            <tr><th class="text-muted">Pagu Anggaran</th><td>: ${formattedPagu}</td></tr>
            <tr><th class="text-muted">Realisasi Anggaran</th><td>: <strong class="text-success">${formattedReal}</strong></td></tr>
            <tr><th class="text-muted">Status</th><td>: <span class="badge bg-primary">${data.status}</span></td></tr>
        </table>
    `;
    
    document.getElementById('detail_content').innerHTML = html;
    new bootstrap.Modal(document.getElementById('modalDetailRealisasi')).show();
}

function tambahRealisasi() {
    Swal.fire({
        icon: 'info',
        title: 'Tambah Realisasi',
        text: 'Fitur form tambah data dapat disesuaikan dengan kebutuhan database Anda.',
        confirmButtonColor: '#4e73df'
    });
}
</script>

<?php include 'template/footer.php'; ?>