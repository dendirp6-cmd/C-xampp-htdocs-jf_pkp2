<?php
// FILE: catatan_update.php
// DESKRIPSI: Halaman Log Update Sistem - Khusus Super Admin & Admin

// =========================================================
// 1. PENGATURAN AWAL & AUTH GUARD
// =========================================================
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Memuat file pengaman sesi (pastikan file ini ada)
require_once 'auth_guard.php';
// Memuat koneksi database
require_once 'koneksi.php';

// Fetch user role dari session untuk validasi hak akses halaman
$user_role = $_SESSION['user_role_sesi'] ?? 'Guest'; 
$is_super_admin = ($user_role === 'user_super_admin'); 
$is_admin = ($user_role === 'user_admin'); 

// Validasi Hak Akses: Hanya Super Admin dan Admin yang boleh membuka halaman ini
$can_access_log_update = ($is_super_admin || $is_admin);
if (!$can_access_log_update) {
    // Jika tidak punya akses, tendang ke dashboard asli atau tampilkan error
    header("Location: index_asli.php");
    exit();
}

// --- VARIABEL NAVIGASI & HIGHLIGHT SIDEBAR ---
$page = 'log_update';
$sub_page = '';
$page_title = 'Log Update Sistem';

// Ambil status append URL dari parameter sidebar jika ada
$hide_perpindahan = isset($_GET['hide_perpindahan']) && $_GET['hide_perpindahan'] == '1';
$hide_kenaikan = isset($_GET['hide_kenaikan']) && $_GET['hide_kenaikan'] == '1';
$append_url = '';
if ($hide_perpindahan) {
    $append_url = '?hide_perpindahan=1';
} elseif ($hide_kenaikan) {
    $append_url = '?hide_kenaikan=1';
}

// =========================================================
// 2. QUERY MENGAMBIL DATA LOG UPDATE
// =========================================================
$log_updates = [];
$error_db_msg = '';

// Nama tabel penampung log update sistem Anda
$nama_tabel_log = "log_update"; 

// Proteksi: Cek apakah tabel database-nya ada
$check_table = mysqli_query($conn, "SHOW TABLES LIKE '{$nama_tabel_log}'");

if (mysqli_num_rows($check_table) > 0) {
    // Ambil log dari yang terbaru (DESC)
    $sql_log = "SELECT * FROM {$nama_tabel_log} ORDER BY tanggal DESC, id DESC";
    $result_log = mysqli_query($conn, $sql_log);
    
    if ($result_log) {
        while ($row = mysqli_fetch_assoc($result_log)) {
            $log_updates[] = $row;
        }
        mysqli_free_result($result_log);
    } else {
        $error_db_msg = "Gagal mengambil data log: " . mysqli_error($conn);
    }
} else {
    // FALLBACK DATA: Jika tabel belum dibuat di database, sistem memakai data dummy agar tidak langsung error crash
    $log_updates = [
        [
            'id' => 1,
            'versi' => 'v2.1.0',
            'judul' => 'Pemutakhiran Alur End-to-End Recruitment & Evaluator Log',
            'deskripsi' => 'Pembaruan komponen Log Aktivitas Berkas menjadi Keterangan Tugas dan Fungsi Evaluator pada modul JFPKP.',
            'kategori' => 'Fitur Baru',
            'tanggal' => date('Y-m-d H:i:s'),
            'developer' => 'System Developer'
        ],
        [
            'id' => 2,
            'versi' => 'v2.0.1',
            'judul' => 'Perbaikan Validasi Filter Dan Tabel Safe Mode',
            'deskripsi' => 'Optimalisasi query pencarian database instansi induk, eselon I, eselon II, dan penanganan error nama kolom tak dikenal.',
            'kategori' => 'Perbaikan',
            'tanggal' => date('Y-m-d H:i:s', strtotime('-2 days')),
            'developer' => 'System Developer'
        ]
    ];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $page_title; ?> | Instansi Pembina JF</title>

    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.25/css/dataTables.bootstrap4.min.css">

    <style>
        .brand-link { background-color: #111827; }
        .badge-fitur { background-color: #28a745; color: white; }
        .badge-perbaikan { background-color: #ffc107; color: #1f2d3d; }
        .badge-keamanan { background-color: #dc3545; color: white; }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

    <?php
    include 'template/navbar.php';
    include 'template/sidebar.php';
    ?>

    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0"><i class="fas fa-history"></i> <?php echo $page_title; ?></h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="index_asli.php">Home</a></li>
                            <li class="breadcrumb-item active">Log Update</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <section class="content">
            <div class="container-fluid">

                <?php if (!empty($error_db_msg)): ?>
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <strong>Informasi:</strong> <?php echo $error_db_msg; ?>. Menggunakan penyimpanan lokal sementara.
                        <button type="button" class="close" data-alert="dismiss" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-12">
                        <div class="card card-tabs card-primary card-outline">
                            <div class="card-header p-0 pt-1 border-bottom-0">
                                <ul class="nav nav-tabs" id="logTab" role="tablist">
                                    <li class="nav-item">
                                        <a class="nav-link active" id="timeline-tab" data-toggle="pill" href="#timeline-view" role="tab" aria-controls="timeline-view" aria-selected="true">
                                            <i class="fas fa-stream"></i> Mode Timeline
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" id="table-tab" data-toggle="pill" href="#table-view" role="tab" aria-controls="table-view" aria-selected="false">
                                            <i class="fas fa-table"></i> Mode Tabel Data
                                        </a>
                                    </li>
                                </ul>
                            </div>
                            
                            <div class="card-body">
                                <div class="tab-content" id="logTabContent">
                                    
                                    <div class="tab-pane fade show active" id="timeline-view" role="tabpanel" aria-labelledby="timeline-tab">
                                        <div class="timeline">
                                            <?php 
                                            $current_date_group = '';
                                            foreach ($log_updates as $log): 
                                                $log_date = date('d M Y', strtotime($log['tanggal']));
                                                if ($current_date_group != $log_date) {
                                                    $current_date_group = $log_date;
                                                    echo '<div class="time-label"><span class="bg-blue">' . htmlspecialchars($current_date_group) . '</span></div>';
                                                }
                                                
                                                // Pemilihan warna ikon berdasarkan kategori update
                                                $icon_class = "fa-code bg-green";
                                                $badge_color = "badge-success";
                                                $kategori = strtolower($log['kategori'] ?? 'fitur baru');
                                                
                                                if (strpos($kategori, 'perbaikan') !== false || strpos($kategori, 'bug') !== false) {
                                                    $icon_class = "fa-tools bg-warning";
                                                    $badge_color = "badge-warning";
                                                } elseif (strpos($kategori, 'aman') !== false || strpos($kategori, 'security') !== false) {
                                                    $icon_class = "fa-shield-alt bg-danger";
                                                    $badge_color = "badge-danger";
                                                }
                                            ?>
                                                <div>
                                                    <i class="fas <?php echo $icon_class; ?>"></i>
                                                    <div class="timeline-item">
                                                        <span class="time"><i class="fas fa-clock"></i> <?php echo date('H:i', strtotime($log['tanggal'])); ?> WIB</span>
                                                        <h3 class="timeline-header">
                                                            <span class="badge badge-primary mr-1"><?php echo htmlspecialchars($log['versi'] ?? 'v1.0'); ?></span> 
                                                            <strong><?php echo htmlspecialchars($log['judul']); ?></strong>
                                                            <span class="badge <?php echo $badge_color; ?> ml-1"><?php echo htmlspecialchars($log['kategori']); ?></span>
                                                        </h3>
                                                        <div class="timeline-body">
                                                            <?php echo nl2br(htmlspecialchars($log['deskripsi'])); ?>
                                                        </div>
                                                        <div class="timeline-footer p-2 text-muted text-right" style="font-size: 11px;">
                                                            <i class="fas fa-user-edit"></i> Diperbarui oleh: <?php echo htmlspecialchars($log['developer'] ?? 'Admin'); ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                            <div>
                                                <i class="fas fa-clock bg-gray"></i>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="tab-pane fade" id="table-view" role="tabpanel" aria-labelledby="table-tab">
                                        <div class="table-responsive">
                                            <table id="tabelLogUpdate" class="table table-bordered table-striped" style="width:100%">
                                                <thead>
                                                    <tr>
                                                        <th style="width: 5%">No.</th>
                                                        <th style="width: 10%">Tanggal</th>
                                                        <th style="width: 10%">Versi</th>
                                                        <th style="width: 15%">Kategori</th>
                                                        <th>Judul Pembaruan</th>
                                                        <th>Deskripsi Singkat</th>
                                                        <th style="width: 12%">Oleh</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php $no = 1; foreach ($log_updates as $log): ?>
                                                        <tr>
                                                            <td><?php echo $no++; ?></td>
                                                            <td><?php echo date('d-m-Y H:i', strtotime($log['tanggal'])); ?></td>
                                                            <td><span class="badge badge-dark"><?php echo htmlspecialchars($log['versi'] ?? '-'); ?></span></td>
                                                            <td><?php echo htmlspecialchars($log['kategori'] ?? '-'); ?></td>
                                                            <td><strong><?php echo htmlspecialchars($log['judul'] ?? '-'); ?></strong></td>
                                                            <td><?php echo htmlspecialchars($log['deskripsi'] ?? '-'); ?></td>
                                                            <td><?php echo htmlspecialchars($log['developer'] ?? 'Admin'); ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </section>
    </div>

    <footer class="main-footer">
        <div class="float-right d-none d-sm-inline">
            Aplikasi Management JF PKP
        </div>
        <strong>© <?php echo date('Y'); ?> Instansi Pembina Jabatan Fungsional Penata Kelola Perumahan</strong>.
    </footer>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.10.25/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.10.25/js/dataTables.bootstrap4.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>

<script>
$(function () {
    // Inisialisasi DataTables untuk Mode Tabel Data
    $("#tabelLogUpdate").DataTable({
        "responsive": true,
        "autoWidth": false,
        "order": [[1, "desc"]], // Urutkan berdasarkan tanggal terbaru
        "pageLength": 10,
        "language": {
            "url": "https://cdn.datatables.net/plug-ins/1.10.25/i18n/Indonesian.json"
        }
    });
});
</script>

<?php
// Tutup koneksi database di akhir halaman
if (isset($conn) && $conn) {
    mysqli_close($conn);
}
ob_end_flush();
?>