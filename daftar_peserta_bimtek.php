<?php
/**
 * ==================================================================================
 * FILE: daftar_peserta_bimtek.php
 * DESKRIPSI: Halaman Rekapitulasi & Daftar Peserta Bimtek Solo
 * FITUR:
 *  - Memanggil koneksi.php & auth_guard.php bawaan sistem
 *  - Template konsisten dengan AdminLTE (Header, Navbar, Sidebar, Footer)
 *  - Kartu Statistik Ringkasan Peserta & Sertifikat
 *  - Filter Interaktif (Metode Kehadiran & Status Sertifikat)
 *  - DataTables dengan fitur Pencarian, Pengurutan, & Pagination
 * ==================================================================================
 */

// Reporting error untuk debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. CALL GUARD & KONEKSI DATABASE BAWAAN
require_once 'auth_guard.php'; 
require_once 'koneksi.php';    

if (!isset($conn) || !$conn) {
    die("Fatal Error: Koneksi database tidak tersedia.");
}

$page_title = "Daftar Peserta Bimtek";

// ==================================================================================
// 2. HITUNG RINGKASAN / STATISTIK PESERTA
// ==================================================================================
$sql_stat = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN UPPER(TRIM(sertif)) = 'YA' THEN 1 ELSE 0 END) as total_sertif_ya,
    SUM(CASE WHEN UPPER(TRIM(sertif)) != 'YA' OR sertif IS NULL THEN 1 ELSE 0 END) as total_sertif_tidak,
    SUM(CASE WHEN LOWER(metode_kehadiran) LIKE '%online%' THEN 1 ELSE 0 END) as total_online,
    SUM(CASE WHEN LOWER(metode_kehadiran) LIKE '%offline%' OR LOWER(metode_kehadiran) LIKE '%tatap%' THEN 1 ELSE 0 END) as total_offline
FROM tb_bimtek_peserta";

$res_stat = $conn->query($sql_stat);
$stat = ($res_stat) ? $res_stat->fetch_assoc() : [
    'total' => 0, 'total_sertif_ya' => 0, 'total_sertif_tidak' => 0, 'total_online' => 0, 'total_offline' => 0
];

// ==================================================================================
// 3. AMBIL DATA PESERTA DARI DATABASE
// ==================================================================================
$sql = "SELECT * FROM tb_bimtek_peserta ORDER BY no_urut ASC, id ASC";
$result = $conn->query($sql);

if (!$result) {
    die("Query Error: " . $conn->error);
}

$peserta_list = $result->fetch_all(MYSQLI_ASSOC);

// Include Template AdminLTE
require_once 'template/header.php'; 
require_once 'template/navbar.php'; 
require_once 'template/sidebar.php'; 
?>

<style>
    .content-wrapper { background-color: #f4f7f6; }
    .main-card { border: none; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); }
    .filter-card { background: #fff; border-radius: 10px; margin-bottom: 15px; border-left: 5px solid #2c7873; }
    
    .stat-card-prio { border-radius: 12px; padding: 15px; color: white; transition: transform 0.2s; }
    .stat-card-prio:hover { transform: translateY(-3px); }
    
    .badge-ya { background-color: #28a745; color: white; font-weight: bold; padding: 5px 10px; border-radius: 12px; }
    .badge-tidak { background-color: #dc3545; color: white; font-weight: bold; padding: 5px 10px; border-radius: 12px; }
    .badge-metode { background-color: #17a2b8; color: white; font-weight: 600; padding: 4px 8px; border-radius: 6px; }
</style>

<div class="content-wrapper">
    <!-- CONTENT HEADER -->
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-3 mt-3 align-items-center">
                <div class="col-sm-6">
                    <!-- Judul Halaman dengan Warna Putih Sesuai Permintaan -->
                    <h1 class="font-weight-bold" style="color: #ffffff !important;">
                        <i class="fas fa-users text-primary mr-2"></i> <?= htmlspecialchars($page_title); ?>
                    </h1>
                </div>
                <div class="col-sm-6 text-right">
                    <a href="index_kasubdit.php" class="btn btn-secondary elevation-1 font-weight-bold">
                        <i class="fas fa-arrow-left mr-1"></i> Kembali ke Dashboard
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- MAIN CONTENT -->
    <section class="content">
        <div class="container-fluid">

            <!-- KARTU STATISTIK RINGKASAN -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stat-card-prio bg-primary shadow-sm">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="font-weight-bold mb-0"><?= number_format($stat['total']); ?></h3>
                                <span>Total Peserta Terdaftar</span>
                            </div>
                            <i class="fas fa-user-friends fa-2x opacity-5"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card-prio bg-info shadow-sm">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="font-weight-bold mb-0"><?= number_format($stat['total_online']); ?></h3>
                                <span>Metode Online</span>
                            </div>
                            <i class="fas fa-laptop-house fa-2x opacity-5"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card-prio bg-success shadow-sm">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="font-weight-bold mb-0"><?= number_format($stat['total_sertif_ya']); ?></h3>
                                <span>Berhak Sertifikat (YA)</span>
                            </div>
                            <i class="fas fa-certificate fa-2x opacity-5"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card-prio bg-danger shadow-sm">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="font-weight-bold mb-0"><?= number_format($stat['total_sertif_tidak']); ?></h3>
                                <span>Tidak Sertifikat (TIDAK)</span>
                            </div>
                            <i class="fas fa-times-circle fa-2x opacity-5"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CARD FILTER -->
            <div class="card filter-card elevation-1">
                <div class="card-body py-3">
                    <div class="row align-items-center">
                        <div class="col-md-1 text-muted font-weight-bold">FILTER:</div>
                        <div class="col-md-3">
                            <select id="filterMetode" class="form-control form-control-sm border-info">
                                <option value="">-- Semua Metode Kehadiran --</option>
                                <option value="Online">Online</option>
                                <option value="Offline">Offline / Tatap Muka</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select id="filterSertif" class="form-control form-control-sm border-success">
                                <option value="">-- Semua Status Sertifikat --</option>
                                <option value="YA">Dapat Sertifikat (YA)</option>
                                <option value="TIDAK">Tidak Dapat (TIDAK)</option>
                            </select>
                        </div>
                        <div class="col-md-5 text-right">
                            <button class="btn btn-sm btn-outline-secondary font-weight-bold" onclick="resetFilter()">
                                <i class="fas fa-undo mr-1"></i> Reset Filter
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TABEL UTAMA -->
            <div class="card main-card">
                <div class="card-header bg-white py-3">
                    <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap: 15px;">
                        <h3 class="card-title font-weight-bold mb-0 text-dark">
                            <i class="fas fa-table mr-2 text-primary"></i> Data Rekapitulasi Peserta Bimtek
                        </h3>
                        <div class="d-flex align-items-center" style="gap: 10px;">
                            <label class="mb-0 font-weight-bold text-muted" style="white-space: nowrap;">Tampilkan Baris:</label>
                            <select id="limitSelect" class="form-control form-control-sm border-primary" style="width: 100px;">
                                <option value="10">10 Data</option>
                                <option value="25">25 Data</option>
                                <option value="50">50 Data</option>
                                <option value="100">100 Data</option>
                                <option value="all" selected>Semua (<?= count($peserta_list); ?>)</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="tablePeserta" class="table table-bordered table-hover align-middle" style="font-size: 13px;">
                            <thead class="bg-dark text-white text-center">
                                <tr>
                                    <th width="40">No</th>
                                    <th>Nama Peserta / NIP</th>
                                    <th>No Suket</th>
                                    <th>Jabatan</th>
                                    <th>Instansi / Unit Kerja</th>
                                    <th>Metode</th>
                                    <th width="50">Absen 23</th>
                                    <th width="50">Absen 24</th>
                                    <th width="50">Pre</th>
                                    <th width="50">Post</th>
                                    <th width="50">Total</th>
                                    <th width="70">Sertif</th>
                                    <th>Keterangan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $no_fallback = 1;
                                foreach ($peserta_list as $row): 
                                    $no_urut = !empty($row['no_urut']) ? $row['no_urut'] : $no_fallback;
                                    $is_sertif_ya = (strtoupper(trim($row['sertif'] ?? '')) === 'YA');
                                ?>
                                <tr>
                                    <td class="text-center font-weight-bold"><?= $no_urut; ?></td>
                                    <td>
                                        <strong class="text-dark"><?= htmlspecialchars($row['nama_peserta'] ?? '-'); ?></strong>
                                        <br><small class="text-muted"><i class="far fa-id-card mr-1"></i><?= htmlspecialchars($row['nip'] ?? '-'); ?></small>
                                    </td>
                                    <td>
                                        <?php if (!empty($row['no_suket'])): ?>
                                            <span class="badge badge-light border text-primary font-weight-bold"><?= htmlspecialchars($row['no_suket']); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted font-italic">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><small class="font-weight-bold text-secondary"><?= htmlspecialchars($row['jabatan'] ?? '-'); ?></small></td>
                                    <td>
                                        <strong class="d-block text-dark small"><?= htmlspecialchars($row['instansi'] ?? '-'); ?></strong>
                                        <small class="text-muted"><?= htmlspecialchars($row['unit_kerja'] ?? '-'); ?></small>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge-metode"><i class="fas fa-video mr-1"></i><?= htmlspecialchars($row['metode_kehadiran'] ?? '-'); ?></span>
                                    </td>
                                    
                                    <!-- Absen 23 Juli -->
                                    <td class="text-center">
                                        <?php if (trim((string)$row['absen_23_juli']) === '1'): ?>
                                            <span class="badge badge-success"><i class="fas fa-check"></i></span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary"><i class="fas fa-times"></i></span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Absen 24 Juli -->
                                    <td class="text-center">
                                        <?php if (trim((string)$row['absen_24_juli']) === '1'): ?>
                                            <span class="badge badge-success"><i class="fas fa-check"></i></span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary"><i class="fas fa-times"></i></span>
                                        <?php endif; ?>
                                    </td>

                                    <td class="text-center font-weight-bold text-secondary"><?= htmlspecialchars($row['nilai_pretest'] ?? '0'); ?></td>
                                    <td class="text-center font-weight-bold text-secondary"><?= htmlspecialchars($row['nilai_posttest'] ?? '0'); ?></td>
                                    <td class="text-center font-weight-bold text-primary" style="font-size: 15px;"><?= htmlspecialchars($row['total_nilai'] ?? '0'); ?></td>
                                    
                                    <!-- Sertifikat -->
                                    <td class="text-center">
                                        <?php if ($is_sertif_ya): ?>
                                            <span class="badge-ya"><i class="fas fa-check-circle mr-1"></i> YA</span>
                                        <?php else: ?>
                                            <span class="badge-tidak"><i class="fas fa-times-circle mr-1"></i> TIDAK</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Keterangan -->
                                    <td>
                                        <small class="text-dark"><?= htmlspecialchars($row['keterangan'] ?? '-'); ?></small>
                                    </td>
                                </tr>
                                <?php 
                                    $no_fallback++;
                                endforeach; 
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </section>
</div>

<?php require_once 'template/footer.php'; ?>

<!-- DATATABLES INIT & SCRIPT -->
<script>
$(document).ready(function() {
    var table = $('#tablePeserta').DataTable({
        "responsive": true,
        "autoWidth": false,
        "ordering": true,
        "language": {
            "search": "Cari Cepat:",
            "lengthMenu": "Tampilkan _MENU_ baris",
            "info": "Menampilkan _START_ sampai _END_ dari _TOTAL_ peserta",
            "paginate": {
                "first": "Awal",
                "last": "Akhir",
                "next": "Lanjut",
                "previous": "Kembali"
            }
        }
    });

    // Filter Metode Kehadiran (Kolom Indeks ke-5 setelah penambahan kolom No Suket)
    $('#filterMetode').on('change', function() {
        table.column(5).search(this.value).draw();
    });

    // Filter Sertifikat (Kolom Indeks ke-11)
    $('#filterSertif').on('change', function() {
        table.column(11).search(this.value).draw();
    });

    // Reset Filter
    window.resetFilter = function() {
        $('#filterMetode, #filterSertif').val('');
        table.column(5).search('').column(11).search('').draw();
    };

    // Limit Tampilan Jumlah Baris
    $('#limitSelect').on('change', function() {
        var val = $(this).val();
        if (val === 'all') {
            table.page.len(-1).draw();
        } else {
            table.page.len(parseInt(val)).draw();
        }
    });
});
</script>