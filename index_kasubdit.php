<?php
/**
 * ==================================================================================
 * FILE: index_kasubdit.php
 * DESKRIPSI: Panel Monitoring & Disposisi Kasubdit + Sinkronisasi Filter Wizard Direktur
 * ==================================================================================
 */

// Aktifkan reporting sementara untuk debugging aman
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'auth_guard.php'; 
require_once 'koneksi.php';    

if (!isset($conn) || !$conn) {
    die("Fatal Error: Koneksi database tidak tersedia.");
}

// 1. IDENTITAS HALAMAN
$page_title = "Panel Monitoring Kasubdit";

// 2. AMBIL DATA USER
$session_user_id = $_SESSION['user_id_sesi'] ?? 0;
$user_nama = "Kasubdit"; 
$sql_user = "SELECT nama FROM users WHERE id = ?"; 
if ($stmt_user = $conn->prepare($sql_user)) {
    $stmt_user->bind_param("i", $session_user_id);
    $stmt_user->execute();
    $res_user = $stmt_user->get_result();
    if ($user_data = $res_user->fetch_assoc()) { 
        $user_nama = $user_data['nama']; 
    }
    $stmt_user->close();
}

// 3. AMBIL DAFTAR VERIFIKATOR
$sql_verifikator = "SELECT id, nama FROM users WHERE role = 'user_verifikator'";
$res_verif = $conn->query($sql_verifikator);
$list_verifikator = ($res_verif) ? $res_verif->fetch_all(MYSQLI_ASSOC) : [];

// 4. AMBIL DAFTAR GELOMBANG
$sql_gelombang = "SELECT id, gelombang, bln_gelombang FROM tb_gelombang ORDER BY id DESC";
$res_gel = $conn->query($sql_gelombang);
$list_gelombang = ($res_gel) ? $res_gel->fetch_all(MYSQLI_ASSOC) : [];

// 5. QUERY DATA PENGAJUAN
$sql = "SELECT p.*, 
               u.email AS user_email, 
               v.nama AS nama_verifikator, 
               g.gelombang AS nama_gelombang, 
               g.bln_gelombang
        FROM pengajuan_ujikom p
        LEFT JOIN users u ON p.nip COLLATE utf8mb4_general_ci = u.nip_user COLLATE utf8mb4_general_ci
        LEFT JOIN users v ON p.verifikator_id = v.id
        LEFT JOIN tb_gelombang g ON p.gelombang = g.id
        ORDER BY p.tanggal_pengajuan DESC";

$result = $conn->query($sql);
if (!$result) {
    die("Query Error: " . $conn->error);
}
$data_pengusul = $result->fetch_all(MYSQLI_ASSOC);

// ==================================================================================
// LOGIKA EXPORT EXCEL
// ==================================================================================
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    $gelombang_filter = $_GET['gelombang_export'] ?? 'all';
    
    $f_jenis = trim($_GET['f_jenis'] ?? '');
    $f_gelombang = trim($_GET['f_gelombang'] ?? '');
    $f_status = trim($_GET['f_status'] ?? '');
    $f_verifikator = trim($_GET['f_verifikator'] ?? '');

    if ($gelombang_filter != 'all') {
        $nama_gel_title = "Gelombang Tidak Diketahui";
        foreach ($list_gelombang as $g) {
            if ($g['id'] == $gelombang_filter) {
                $nama_gel_title = $g['gelombang'] . (!empty($g['bln_gelombang']) ? " - " . $g['bln_gelombang'] : "");
                break;
            }
        }
    } else {
        $title_parts = [];
        if (!empty($f_jenis)) $title_parts[] = "Jenis: " . $f_jenis;
        if (!empty($f_gelombang)) $title_parts[] = "Gelombang: " . $f_gelombang;
        if (!empty($f_status)) $title_parts[] = "Status: " . $f_status;
        if (!empty($f_verifikator)) $title_parts[] = "Verif: " . $f_verifikator;

        $nama_gel_title = !empty($title_parts) ? implode(" | ", $title_parts) : "Semua Data (Keseluruhan)";
    }

    $filename = "Data_Peserta_" . preg_replace('/[^A-Za-z0-9]/', '_', $nama_gel_title) . "_" . date('Ymd_His') . ".xls";

    header("Content-Type: application/vnd-ms-excel");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    echo '<table border="1">';
    echo '<tr><th colspan="9" style="font-size: 12pt; font-weight: bold; text-align: center; padding: 10px;">Data Peserta Ujikom - ' . htmlspecialchars($nama_gel_title) . '</th></tr>';
    echo '<tr style="font-weight: bold; text-align: center; background-color: #f2f2f2;">
            <th>No</th>
            <th>Nama</th>
            <th>NIP</th>
            <th>Email</th>
            <th>No HP/WA</th>
            <th>Pangkat/Golongan</th>
            <th>Jabatan Saat Ini</th>
            <th>JF PKP yang Dituju</th>
            <th>Unit Kerja</th>
          </tr>';
    
    $no_ex = 1;
    foreach ($data_pengusul as $p) {
        if ($gelombang_filter != 'all' && $p['gelombang'] != $gelombang_filter) {
            continue;
        }

        if ($gelombang_filter == 'all') {
            $p_jenis = $p['jenis_pengajuan'] ?? '';
            $p_gel = !empty($p['nama_gelombang']) ? $p['nama_gelombang'] : ($p['gelombang'] ?? '');
            $p_status = $p['status_pengajuan'] ?? '';
            $p_verif = $p['nama_verifikator'] ?? '';
            $has_verif = !empty($p['verifikator_id']);

            if (!empty($f_jenis) && $p_jenis !== $f_jenis) continue;
            
            if (!empty($f_gelombang)) {
                if ($f_gelombang === 'BELUM MASUK GELOMBANG') {
                    if (!empty($p['gelombang'])) continue;
                } else {
                    if ($p_gel !== $f_gelombang && (empty($p['nama_gelombang']) || strpos($p['nama_gelombang'], $f_gelombang) === false)) continue;
                }
            }

            if (!empty($f_status) && $p_status !== $f_status) continue;
            if (!empty($f_verifikator)) {
                if ($f_verifikator === 'BELUM DISPOSISI' && $has_verif) continue;
                if ($f_verifikator !== 'BELUM DISPOSISI' && $p_verif !== $f_verifikator) continue;
            }
        }

        $email_val = !empty($p['user_email']) ? $p['user_email'] : (!empty($p['email']) ? $p['email'] : '-');
        $hp_val = !empty($p['hp']) ? $p['hp'] : (!empty($p['no_hp']) ? $p['no_hp'] : '-');

        echo '<tr>';
        echo '<td style="text-align:center;">' . $no_ex++ . '</td>';
        echo '<td>' . htmlspecialchars($p['nama'] ?? '-') . '</td>';
        echo '<td style="mso-number-format:\'\@\';">' . htmlspecialchars($p['nip'] ?? '-') . '</td>';
        echo '<td>' . htmlspecialchars($email_val) . '</td>';
        echo '<td style="mso-number-format:\'\@\';">' . htmlspecialchars($hp_val) . '</td>';
        echo '<td>' . htmlspecialchars($p['pangkat'] ?? '-') . '</td>';
        echo '<td>' . htmlspecialchars($p['jabatan_saat_ini'] ?? '-') . '</td>';
        echo '<td>' . htmlspecialchars($p['jf_pkp_tujuan'] ?? '-') . '</td>';
        echo '<td>' . htmlspecialchars($p['unit_saat_ini'] ?? $p['unit_kerja'] ?? '-') . '</td>';
        echo '</tr>';
    }
    echo '</table>';
    exit; 
}

// --- HITUNG STATISTIK AWAL ---
$count_dendi = 0; $count_brian = 0; $count_tata = 0; $count_yogi = 0; $count_belum = 0; $rekap_status = [];
foreach ($data_pengusul as $row) {
    if (empty($row['verifikator_id'])) { 
        $count_belum++; 
    } else {
        $nama_v = $row['nama_verifikator'] ?? '';
        if (stripos($nama_v, 'Dendi') !== false) { $count_dendi++; } 
        elseif (stripos($nama_v, 'Brian') !== false) { $count_brian++; }
        elseif (stripos($nama_v, 'Tata') !== false) { $count_tata++; }
        elseif (stripos($nama_v, 'Yogi') !== false) { $count_yogi++; }
    }
    $st = $row['status_pengajuan'] ?? 'Unknown';
    if (!isset($rekap_status[$st])) { $rekap_status[$st] = 0; }
    $rekap_status[$st]++;
}

$status_terpilih = [
    'Menunggu Disposisi'    => ['color' => '#6610f2', 'icon' => 'fa-inbox'],
    'Menunggu Verifikasi'   => ['color' => '#ffc107', 'icon' => 'fa-clock'],
    'Proses Verifikasi'     => ['color' => '#fd7e14', 'icon' => 'fa-spinner'],
    'Verifikasi Dokumen'    => ['color' => '#17a2b8', 'icon' => 'fa-file-signature'],
    'Perlu Perbaikan'       => ['color' => '#dc3545', 'icon' => 'fa-exclamation-triangle'],
    'Disetujui Verifikator' => ['color' => '#28a745', 'icon' => 'fa-check-circle'],
    'Proses Direktur'       => ['color' => '#20c997', 'icon' => 'fa-paper-plane'],
    'Disetujui Direktur'    => ['color' => '#0056b3', 'icon' => 'fa-award'],
    'Tidak Memenuhi Syarat' => ['color' => '#6c757d', 'icon' => 'fa-times-circle']
];

require_once 'template/header.php'; 
require_once 'template/navbar.php'; 
require_once 'template/sidebar.php'; 
?>

<style>
    :root { --banner-kasubdit: linear-gradient(135deg, #155263 0%, #2c7873 100%); }
    
    .main-card { border: none; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); }
    .dashboard-banner { background: var(--banner-kasubdit); color: white; border-radius: 12px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
    .status-badge { padding: 6px 12px; border-radius: 50px; font-size: 11px; font-weight: bold; }
    .stat-box { background: rgba(255, 255, 255, 0.15); border-radius: 10px; padding: 10px 15px; text-align: center; min-width: 115px; border: 1px solid rgba(255, 255, 255, 0.2); }
    .stat-box h3 { margin-bottom: 0; font-weight: 800; font-size: 24px; }
    
    .rekap-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 20px; }
    .rekap-card { border-radius: 12px; padding: 15px 18px; border-left: 5px solid #6c757d; box-shadow: 0 4px 12px rgba(0,0,0,0.04); transition: transform 0.2s ease, box-shadow 0.2s ease; display: flex; align-items: center; justify-content: space-between; }
    .rekap-card:hover { transform: translateY(-3px); box-shadow: 0 6px 15px rgba(0,0,0,0.08); }
    .rekap-card .val { font-size: 24px; font-weight: 800; line-height: 1; margin-bottom: 4px; }
    .rekap-card .lbl { font-size: 11px; color: #495057; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; }
    .rekap-card .icon-box { width: 42px; height: 42px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px; opacity: 0.15; }

    .badge-verif-none { background-color: #dc3545 !important; color: white !important; font-weight: 700 !important; padding: 4px 8px; border-radius: 4px; font-size: 10px; }
    .badge-gelombang-none { background-color: #6c757d !important; color: white !important; font-weight: 700 !important; padding: 4px 8px; border-radius: 4px; font-size: 10px; }
    .btn-detail { background-color: #17a2b8; color: white; font-weight: bold; border-radius: 8px; border: none; transition: 0.3s; }
    .btn-direktur-main { background-color: #6610f2; color: white; font-weight: bold; border-radius: 8px; border: none; transition: 0.3s; padding: 8px 15px; }
    .btn-direktur-main:hover { background-color: #520dc2; color: white; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(102, 16, 242, 0.4); }
    .btn-olah-prioritas { background-color: #e83e8c; color: white; font-weight: bold; border-radius: 8px; border: none; transition: 0.3s; padding: 8px 15px; }
    .btn-olah-prioritas:hover { background-color: #d63384; color: white; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(232, 62, 140, 0.4); }
    .bulk-action-area { display: none; margin-bottom: 15px; animation: fadeIn 0.3s; }
    .filter-card { border-radius: 10px; margin-bottom: 15px; border-left: 5px solid #2c7873; }
    .step-wizard { display: none; } .step-wizard.active { display: block; }
    #wizard_data_body tr:hover { background-color: #f1f1f1; cursor: pointer; }
    .action-container { display: flex; gap: 4px; justify-content: center; flex-wrap: nowrap; align-items: center; }

    .btn-secret-bg {
        background: transparent;
        border: none;
        color: rgba(255, 255, 255, 0.2); 
        font-size: 12px;
        padding: 4px 8px;
        border-radius: 4px;
        transition: all 0.3s ease;
        text-decoration: none;
    }
    .btn-secret-bg:hover {
        color: #ffffff;
        background: rgba(255, 255, 255, 0.1);
    }

    .dt-header-controls {
        position: sticky;
        top: 0;
        z-index: 5;
        background: #ffffff;
        padding-top: 5px;
        padding-bottom: 10px;
        border-bottom: 1px solid #e9ecef;
    }
    .dataTables_scrollHead {
        position: sticky !important;
        top: 0;
        z-index: 4;
        background-color: #ffffff;
    }
</style>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-3 mt-3 align-items-center">
                <div class="col-sm-6">
                    <h1 class="font-weight-bold text-white text-shadow" style="text-shadow: 0 2px 4px rgba(0,0,0,0.6);">
                        <i class="fas fa-desktop text-success mr-2"></i> Monitoring & Disposisi
                    </h1>
                </div>
                <div class="col-sm-6 text-sm-right mt-2 mt-sm-0">
                    <button type="button" class="btn-secret-bg" data-toggle="modal" data-target="#modalUploadBg" title="Pengaturan Latar Belakang">
                        <i class="fas fa-cog"></i>
                    </button>
                </div>
            </div>

            <?php if (isset($_SESSION['success_msg'])): ?>
                <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                    <i class="fas fa-check-circle mr-2"></i> <?= $_SESSION['success_msg']; unset($_SESSION['success_msg']); ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
            <?php endif; ?>
            <?php if (isset($upload_error)): ?>
                <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                    <i class="fas fa-exclamation-triangle mr-2"></i> <?= $upload_error; ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
            <?php endif; ?>

            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="dashboard-banner">
                        <div class="row align-items-center">
                            <div class="col-md-3">
                                <h2 class="font-weight-bold mb-1">Kelola Disposisi</h2>
                                <p class="mb-0 opacity-8">Pantau progres usulan secara real-time</p>
                            </div>
                            <div class="col-md-9">
                                <div class="d-flex flex-wrap justify-content-end" style="gap: 10px;">
                                    <div class="stat-box"><h3><?= count($data_pengusul); ?></h3><small>Total Usulan</small></div>
                                    <div class="stat-box" style="border-bottom: 4px solid #007bff;"><h3><?= $count_dendi; ?></h3><small>Verif Dendi</small></div>
                                    <div class="stat-box" style="border-bottom: 4px solid #28a745;"><h3><?= $count_brian; ?></h3><small>Verif Brian</small></div>
                                    <div class="stat-box" style="border-bottom: 4px solid #17a2b8;"><h3><?= $count_tata; ?></h3><small>Verif Tata</small></div>
                                    <div class="stat-box" style="border-bottom: 4px solid #ffc107;"><h3><?= $count_yogi; ?></h3><small>Verif Yogi</small></div>
                                    <div class="stat-box" style="border-bottom: 4px solid #dc3545; background: rgba(220, 53, 69, 0.2);"><h3><?= $count_belum; ?></h3><small>Belum Disposisi</small></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <h5 class="font-weight-bold mb-3 text-white text-shadow" style="text-shadow: 0 2px 4px rgba(0,0,0,0.6);">
                <i class="fas fa-chart-pie mr-2"></i> Rekapitulasi Per Status
            </h5>
            
            <div class="rekap-grid">
                <?php foreach($status_terpilih as $st_name => $meta): 
                    $st_count = $rekap_status[$st_name] ?? 0; ?>
                    <div class="rekap-card" style="border-left-color: <?= $meta['color']; ?>;">
                        <div>
                            <div class="val rekap-val" data-status="<?= htmlspecialchars($st_name); ?>" style="color: <?= $meta['color']; ?>;"><?= $st_count; ?></div>
                            <div class="lbl"><?= htmlspecialchars($st_name); ?></div>
                        </div>
                        <div class="icon-box" style="background-color: <?= $meta['color']; ?>; color: <?= $meta['color']; ?>;">
                            <i class="fas <?= $meta['icon']; ?>"></i>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="card filter-card elevation-1">
                <div class="card-body py-3">
                    <div class="row align-items-center">
                        <div class="col-md-1 text-dark font-weight-bold">FILTER:</div>
                        <div class="col-md-2">
                            <select id="filterJenis" class="form-control form-control-sm border-info">
                                <option value="">-- Semua Jenis --</option>
                                <option value="Perpindahan Jabatan">Perpindahan Jabatan</option>
                                <option value="Kenaikan Jabatan">Kenaikan Jabatan</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select id="filterGelombang" class="form-control form-control-sm border-secondary">
                                <option value="">-- Semua Gelombang --</option>
                                <option value="BELUM MASUK GELOMBANG">Belum Masuk Gelombang</option>
                                <?php foreach($list_gelombang as $g): 
                                    $label_gel = htmlspecialchars($g['gelombang']) . (!empty($g['bln_gelombang']) ? " - " . htmlspecialchars($g['bln_gelombang']) : "");
                                ?>
                                    <option value="<?= htmlspecialchars($g['gelombang']); ?>"><?= $label_gel; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select id="filterStatus" class="form-control form-control-sm border-success">
                                <option value="">-- Semua Status --</option>
                                <?php foreach(array_keys($status_terpilih) as $st_opt): ?>
                                    <option value="<?= $st_opt; ?>"><?= $st_opt; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select id="filterVerifikator" class="form-control form-control-sm border-primary">
                                <option value="">-- Semua Verifikator --</option>
                                <option value="BELUM DISPOSISI">BELUM DISPOSISI</option>
                                <?php foreach($list_verifikator as $v): ?>
                                    <option value="<?= htmlspecialchars($v['nama']); ?>"><?= htmlspecialchars($v['nama']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 text-right"><button class="btn btn-sm btn-outline-secondary" onclick="resetFilter()"><i class="fas fa-undo"></i> Reset</button></div>
                    </div>
                </div>
            </div>

            <div class="card main-card">
                <div class="card-header bg-white py-3">
                    <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap: 10px;">
                        <h3 class="card-title font-weight-bold mb-0"><i class="fas fa-list mr-2"></i> Daftar Pengusul</h3>
                        <div class="d-flex flex-wrap" style="gap: 8px;">
                            <div id="bulkActionArea" class="bulk-action-area mr-2">
                                <button type="button" class="btn btn-primary font-weight-bold elevation-2 mr-2" id="btnBulkDisposisi"><i class="fas fa-users-cog mr-1"></i> Disposisi Massal (<span class="countCheck">0</span>)</button>
                                <button type="button" class="btn btn-info font-weight-bold elevation-2" id="btnBulkGelombang"><i class="fas fa-layer-group mr-1"></i> Set Gelombang Massal (<span class="countCheck">0</span>)</button>
                            </div>
                            
                            <a href="olah_prioritas.php" class="btn btn-olah-prioritas elevation-2">
                                <i class="fas fa-sort-amount-down mr-1"></i> Olah Prioritas
                            </a>

                            <button type="button" class="btn btn-success font-weight-bold elevation-2" data-toggle="modal" data-target="#modalExportExcel">
                                <i class="fas fa-file-excel mr-1"></i> Export Excel
                            </button>

                            <button type="button" class="btn btn-direktur-main elevation-2" data-toggle="modal" data-target="#modalKirimDirektur">
                                <i class="fas fa-paper-plane mr-1"></i> Kirim ke Direktur
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <table id="tableKasubdit" class="table table-hover text-nowrap" style="width:100%">
                        <thead>
                            <tr>
                                <th width="30"><input type="checkbox" id="checkAll"></th>
                                <th>No</th>
                                <th>Pengusul</th>
                                <th>Email</th>
                                <th>No Telpon</th>
                                <th>Jenis</th>
                                <th>Gelombang</th> 
                                <th>Verifikator</th>
                                <th class="text-center">Status</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no=1; foreach ($data_pengusul as $row): 
                                $status = $row['status_pengajuan'] ?? '-';
                                $ket_status = $row['keterangan_status'] ?? '';
                                
                                if ($status == 'Lulus Administrasi') { $badge_class = 'bg-primary'; }
                                elseif ($status == 'Disetujui Verifikator' || $status == 'Lulus') { $badge_class = 'bg-success'; }
                                elseif ($status == 'Proses Direktur') { $badge_class = 'bg-teal'; }
                                elseif ($status == 'Perlu Perbaikan' || $status == 'Tidak Lulus') { $badge_class = 'bg-danger'; }
                                elseif ($status == 'Menunggu Verifikasi') { $badge_class = 'bg-warning'; }
                                elseif ($status == 'Proses Verifikasi') { $badge_class = 'bg-orange text-white'; }
                                elseif ($status == 'Verifikasi Dokumen') { $badge_class = 'bg-info'; }
                                elseif ($status == 'Menunggu Disposisi') { $badge_class = 'bg-indigo'; }
                                elseif ($status == 'Tidak Memenuhi Syarat') { $badge_class = 'bg-dark'; }
                                else { $badge_class = 'bg-secondary'; }
                                
                                $disp_gel = !empty($row['nama_gelombang']) ? $row['nama_gelombang'] : ($row['gelombang'] ?? '');
                                $disp_bln = !empty($row['bln_gelombang']) ? " - " . $row['bln_gelombang'] : "";
                                $tampil_gelombang = $disp_gel . $disp_bln;

                                if (($row['jenis_pengajuan'] ?? '') == 'Perpindahan Jabatan') {
                                    $url_detail = "detail_verif_perpindahan.php?id=" . $row['id'];
                                } else {
                                    $url_detail = "detail_verif_kenaikan.php?id=" . $row['id'];
                                }

                                $user_email = !empty($row['user_email']) ? $row['user_email'] : (!empty($row['email']) ? $row['email'] : '-');
                                $no_telp = !empty($row['hp']) ? $row['hp'] : (!empty($row['no_hp']) ? $row['no_hp'] : '-');
                            ?>
                            <tr>
                                <td><input type="checkbox" class="check-child" value="<?= $row['id']; ?>"></td>
                                <td><?= $no++; ?></td>
                                <td><strong><?= htmlspecialchars($row['nama'] ?? '-'); ?></strong><br><small class="text-muted"><?= htmlspecialchars($row['nip'] ?? '-'); ?></small></td>
                                <td><small class="text-dark"><i class="fas fa-envelope text-secondary mr-1"></i> <?= htmlspecialchars($user_email); ?></small></td>
                                <td><small class="text-dark"><i class="fas fa-phone text-secondary mr-1"></i> <?= htmlspecialchars($no_telp); ?></small></td>
                                <td><small class="jenis-teks"><?= htmlspecialchars($row['jenis_pengajuan'] ?? '-'); ?></small></td>
                                <td> 
                                    <?php if(!empty($disp_gel)): ?>
                                        <span class="badge badge-outline-secondary" style="border: 1px solid #ddd; color: #555;"><i class="fas fa-tag mr-1"></i> <?= htmlspecialchars($tampil_gelombang); ?></span>
                                    <?php else: ?>
                                        <span class="badge-gelombang-none">BELUM MASUK GELOMBANG</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= !empty($row['verifikator_id']) ? '<span class="badge badge-info p-1">'.htmlspecialchars($row['nama_verifikator'] ?? '').'</span>' : '<span class="badge-verif-none">BELUM DISPOSISI</span>'; ?></td>
                                <td class="text-center">
                                    <span class="badge <?= $badge_class; ?> status-badge"><?= htmlspecialchars($status); ?></span>
                                    <?php if (!empty($ket_status)): ?>
                                        <br><small class="text-danger font-italic font-weight-bold">(<?= htmlspecialchars($ket_status); ?>)</small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="action-container">
                                        <a href="<?= $url_detail; ?>" class="btn btn-sm btn-detail px-2" title="Lihat Detail"><i class="fas fa-eye"></i> Detail</a>
                                        <button class="btn btn-sm btn-warning font-weight-bold btn-buka-disposisi px-2" data-id="<?= $row['id']; ?>" data-nama="<?= htmlspecialchars($row['nama'] ?? ''); ?>" data-verif="<?= $row['verifikator_id'] ?? ''; ?>" data-toggle="modal" data-target="#modalDisposisi" title="Set Verifikator"><i class="fas fa-share-square"></i> Disposisi</button>
                                        <button class="btn btn-sm btn-info font-weight-bold btn-buka-gelombang px-2" data-id="<?= $row['id']; ?>" data-gel="<?= $row['gelombang'] ?? ''; ?>" title="Set Gelombang"><i class="fas fa-layer-group"></i> Gelombang</button>
                                        
                                        <!-- Tombol Edit Status Khusus / Batalkan Proses -->
                                        <button class="btn btn-sm btn-secondary font-weight-bold btn-edit-status-khusus px-2" data-id="<?= $row['id']; ?>" data-nama="<?= htmlspecialchars($row['nama'] ?? ''); ?>" data-jenis="<?= htmlspecialchars($row['jenis_pengajuan'] ?? ''); ?>" data-status="<?= htmlspecialchars($status); ?>" data-ket="<?= htmlspecialchars($ket_status); ?>" title="Edit Status / Pembatalan"><i class="fas fa-user-slash"></i> Edit Status</button>

                                        <?php if ($status === 'Proses Direktur'): ?>
                                            <button class="btn btn-sm btn-danger font-weight-bold btn-batal-direktur px-2" data-id="<?= $row['id']; ?>" data-nama="<?= htmlspecialchars($row['nama'] ?? ''); ?>" title="Batalkan Pengiriman ke Direktur">
                                                <i class="fas fa-undo-alt"></i> Batal Kirim
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- MODAL EDIT STATUS KHUSUS / TMS -->
<div class="modal fade" id="modalStatusKhusus" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius: 12px;">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title font-weight-bold"><i class="fas fa-user-slash mr-2"></i> Ubah Status Pengusul / Pembatalan</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form id="formStatusKhusus">
                <input type="hidden" name="id_pengajuan" id="status_khusus_id">
                <div class="modal-body">
                    <p class="mb-1">Pengusul: <strong id="status_khusus_nama" class="text-primary"></strong></p>
                    <p class="small text-muted mb-3">Jenis Pengajuan: <span id="status_khusus_jenis" class="badge badge-light border"></span></p>

                    <div class="form-group">
                        <label class="font-weight-bold">Status Baru:</label>
                        <select name="status_baru" id="status_khusus_select" class="form-control" required>
                            <option value="Tidak Memenuhi Syarat">Tidak Memenuhi Syarat (TMS) / Dibatalkan</option>
                            <option value="Perlu Perbaikan">Perlu Perbaikan</option>
                            <option value="Menunggu Disposisi">Kembalikan ke Menunggu Disposisi</option>
                        </select>
                    </div>

                    <div class="form-group mt-3">
                        <label class="font-weight-bold">Alasan / Keterangan Pembatalan:</label>
                        <select name="keterangan_status" id="status_khusus_alasan" class="form-control" required>
                            <option value="">-- Pilih Alasan --</option>
                            <option value="Mengundurkan diri">1. Mengundurkan diri</option>
                            <option value="Sudah mengikuti ujikom">2. Sudah mengikuti ujikom</option>
                            <option value="Hukuman disiplin">3. Hukuman disiplin</option>
                            <option value="Lewat batas umur">4. Lewat batas umur</option>
                            <option value="Tidak hadir saat ujikom">5. Tidak hadir saat ujikom</option>
                            <option value="JF bukan PKP" id="opt_jf_bukan_pkp">6. JF bukan PKP (Khusus Kenaikan)</option>
                            <option value="Lainnya">Lainnya...</option>
                        </select>
                    </div>

                    <div class="form-group d-none" id="group_alasan_lainnya">
                        <label class="font-weight-bold">Detail Alasan Lainnya:</label>
                        <input type="text" id="alasan_lainnya_text" class="form-control" placeholder="Tuliskan alasan spesifik...">
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger font-weight-bold" id="btnSimpanStatusKhusus">
                        <i class="fas fa-save mr-1"></i> Simpan Status ke Database
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL UPLOAD BACKGROUND KUSTOM -->
<div class="modal fade" id="modalUploadBg" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius: 12px;">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title font-weight-bold"><i class="fas fa-image mr-2"></i> Unggah Background Baru</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form action="proses_upload_bg.php" method="POST" enctype="multipart/form-data">
                <div class="modal-body py-4">
                    <p class="text-muted">Pilih gambar (format: JPG, PNG, JPEG) untuk dijadikan latar belakang global seluruh halaman sistem.</p>
                    <div class="form-group mt-3">
                        <div class="custom-file">
                            <input type="file" name="custom_bg" class="custom-file-input" id="customFileBg" accept="image/*" required>
                            <label class="custom-file-label" for="customFileBg">Pilih file gambar...</label>
                        </div>
                    </div>
                    <small class="form-text text-muted mt-2">Gambar yang diunggah akan otomatis mengubah tampilan background di seluruh halaman.</small>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary shadow-sm" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-info font-weight-bold px-4 shadow">
                        <i class="fas fa-upload mr-1"></i> Unggah & Terapkan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL EXPORT EXCEL -->
<div class="modal fade" id="modalExportExcel" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius: 12px;">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title font-weight-bold"><i class="fas fa-file-excel mr-2"></i> Export Data ke Excel</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form action="index_kasubdit.php" method="GET" id="formExportExcel">
                <input type="hidden" name="export" value="excel">
                <input type="hidden" name="f_jenis" id="exp_f_jenis" value="">
                <input type="hidden" name="f_gelombang" id="exp_f_gelombang" value="">
                <input type="hidden" name="f_status" id="exp_f_status" value="">
                <input type="hidden" name="f_verifikator" id="exp_f_verifikator" value="">
                
                <div class="modal-body py-4">
                    <p>Pilih cakupan data yang ingin Anda ekspor:</p>
                    <div class="form-group mt-3">
                        <label class="font-weight-bold text-muted">Filter Gelombang:</label>
                        <select name="gelombang_export" class="form-control" required>
                            <option value="all">-- Semua Data (Keseluruhan / Sesuai Filter Layar) --</option>
                            <option value="" disabled>-----------------------------------</option>
                            <?php foreach($list_gelombang as $g): 
                                $exp_label = htmlspecialchars($g['gelombang']) . (!empty($g['bln_gelombang']) ? " - " . htmlspecialchars($g['bln_gelombang']) : "");
                            ?>
                                <option value="<?= $g['id']; ?>"><?= $exp_label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary shadow-sm" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success font-weight-bold px-4 shadow">
                        <i class="fas fa-download mr-1"></i> Unduh Excel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Kirim Direktur -->
<div class="modal fade" id="modalKirimDirektur" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content" style="border-radius: 12px; overflow: hidden;">
            <div class="modal-header bg-indigo text-white">
                <h5 class="modal-title font-weight-bold"><i class="fas fa-paper-plane mr-2"></i> Kirim ke Direktur</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            
            <div id="step1" class="step-wizard active">
                <div class="modal-body py-4">
                    <div class="alert alert-info border-0 shadow-sm"><i class="fas fa-info-circle mr-2"></i> <strong>Langkah 1:</strong> Pilih gelombang tujuan pengiriman.</div>
                    <div class="form-group mt-4">
                        <label class="font-weight-bold">Tujuan Gelombang:</label>
                        <select id="wizard_gelombang" class="form-control form-control-lg border-primary">
                            <option value="">-- Pilih Gelombang --</option>
                            <?php foreach($list_gelombang as $g): 
                                $wiz_gel_label = htmlspecialchars($g['gelombang']) . (!empty($g['bln_gelombang']) ? " - " . htmlspecialchars($g['bln_gelombang']) : "");
                            ?>
                                <option value="<?= $g['id']; ?>" data-nama="<?= $wiz_gel_label; ?>"><?= $wiz_gel_label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-primary px-4 font-weight-bold shadow" id="btnNextStep">Selanjutnya <i class="fas fa-arrow-right ml-1"></i></button>
                </div>
            </div>

            <div id="step2" class="step-wizard">
                <div class="modal-body py-4">
                    <div class="alert alert-warning border-0 shadow-sm d-flex justify-content-between align-items-center mb-3">
                        <div><i class="fas fa-user-check mr-2"></i> <strong>Langkah 2:</strong> Pilih pengusul untuk <strong><span id="text_gel_selected"></span></strong>.</div>
                    </div>

                    <div class="row mb-3 bg-light p-2 rounded mx-0 align-items-center">
                        <div class="col-md-6">
                            <small class="font-weight-bold text-muted">Filter Jenis Pengajuan di Popup:</small>
                            <select id="wizard_filter_jenis" class="form-control form-control-sm mt-1">
                                <option value="">-- Tampilkan Semua Jenis (Sesuai Filter Utama) --</option>
                                <option value="Kenaikan Jabatan">Kenaikan Jabatan</option>
                                <option value="Perpindahan Jabatan">Perpindahan Jabatan</option>
                            </select>
                        </div>
                        <div class="col-md-6 text-right pt-3">
                            <small class="text-muted">Total ditampilkan: <span id="wizard_count_badge" class="font-weight-bold badge badge-info">0</span> data</small>
                        </div>
                    </div>

                    <div id="container_list_direktur" class="mt-2">
                        <div class="table-responsive" style="max-height: 320px; border: 1px solid #dee2e6; border-radius: 8px;">
                            <table class="table table-sm table-hover mb-0">
                                <thead class="bg-dark text-white" style="position: sticky; top: 0; z-index: 2;">
                                    <tr>
                                        <th width="50" class="text-center"><input type="checkbox" id="checkAllWizard"></th>
                                        <th>Nama Pengusul / NIP</th>
                                        <th>Jenis Pengajuan</th>
                                        <th class="text-center">Status Saat Ini</th>
                                    </tr>
                                </thead>
                                <tbody id="wizard_data_body"></tbody>
                            </table>
                        </div>
                    </div>
                    <div id="wizard_empty" class="text-center py-5 d-none">
                        <i class="fas fa-user-slash fa-4x text-muted mb-3"></i>
                        <p class="text-muted font-italic">Tidak ditemukan pengusul dengan status "Disetujui Verifikator" yang sesuai filter.</p>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary shadow-sm" id="btnPrevStep"><i class="fas fa-arrow-left mr-1"></i> Kembali</button>
                    <button type="button" class="btn btn-success font-weight-bold px-4 shadow" id="btnFinalKirimDirektur">Kirim ke Direktur <i class="fas fa-check-circle ml-1"></i></button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Disposisi -->
<div class="modal fade" id="modalDisposisi" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius: 12px;">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title font-weight-bold" id="modalTitle">Tugaskan Verifikator</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form id="formDisposisi">
                <input type="hidden" name="id_pengajuan" id="disp_id_pengajuan">
                <div class="modal-body">
                    <p id="label_pilih">Pilih Verifikator untuk pengusul:</p>
                    <h5 id="disp_nama_pengusul" class="font-weight-bold text-primary mb-3"></h5>
                    <div class="form-group">
                        <label>Nama Verifikator:</label>
                        <select name="id_verifikator" id="disp_select_verif" class="form-control">
                            <option value="">-- Biarkan Kosong (Belum Disposisi) --</option>
                            <?php foreach($list_verifikator as $v): ?>
                                <option value="<?= $v['id']; ?>"><?= htmlspecialchars($v['nama']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success font-weight-bold" id="btnSimpanDisp">Kirim Tugas</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Gelombang -->
<div class="modal fade" id="modalGelombang" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius: 12px;">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title font-weight-bold"><i class="fas fa-layer-group mr-2"></i> Set Gelombang Pengusul</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form id="formGelombangMasal">
                <input type="hidden" name="id_pengajuan" id="gel_id_pengajuan">
                <div class="modal-body">
                    <p>Tentukan gelombang untuk <span id="count_gel_text" class="font-weight-bold text-danger">1</span> pengusul:</p>
                    <div class="form-group">
                        <label class="font-weight-bold">Pilih Gelombang:</label>
                        <select name="id_gelombang" id="select_gelombang_masal" class="form-control">
                            <option value="">-- Belum Dipilih / Kosongkan Gelombang --</option>
                            <?php foreach($list_gelombang as $g): 
                                $bulk_label = htmlspecialchars($g['gelombang']) . (!empty($g['bln_gelombang']) ? " - " . htmlspecialchars($g['bln_gelombang']) : "");
                            ?>
                                <option value="<?= $g['id']; ?>"><?= $bulk_label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-info font-weight-bold" id="btnSimpanGel">
                        <i class="fas fa-save mr-1"></i> Update Gelombang
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'template/footer.php'; ?>

<script>
$(document).ready(function() {
    $('#customFileBg').on('change', function() {
        var fileName = $(this).val().split('\\').pop();
        $(this).next('.custom-file-label').html(fileName);
    });

    var table = $('#tableKasubdit').DataTable({ 
        "scrollX": true,
        "scrollY": "55vh",           
        "scrollCollapse": true,
        "paging": true,
        "autoWidth": false,
        "language": { "search": "Cari Cepat:" },
        "dom": '<"dt-header-controls"<"row align-items-center"<"col-md-4"l><"col-md-4 text-center"p><"col-md-4"f>>>rt<"row mt-2 align-items-center"<"col-md-6"i><"col-md-6 d-flex justify-content-end"p>>'
    });

    // ----------------------------------------------------------------------
    // FILTER CUSTOM DATA TABLES (MENGATASI GELOMBANG, VERIFIKATOR, STATUS)
    // ----------------------------------------------------------------------
    $.fn.dataTable.ext.search.push(
        function(settings, data, dataIndex) {
            if (settings.nTable.id !== 'tableKasubdit') {
                return true;
            }

            var fJenis       = $('#filterJenis').val();
            var fGelombang   = $('#filterGelombang').val();
            var fStatus      = $('#filterStatus').val();
            var fVerifikator = $('#filterVerifikator').val();

            var cJenis       = $('<div>' + data[5] + '</div>').text().trim();
            var cGelombang   = $('<div>' + data[6] + '</div>').text().trim();
            var cVerifikator = $('<div>' + data[7] + '</div>').text().trim();
            var cStatus      = $('<div>' + data[8] + '</div>').text().trim();

            if (fJenis !== '' && cJenis !== fJenis) {
                return false;
            }

            if (fGelombang !== '') {
                if (fGelombang === 'BELUM MASUK GELOMBANG') {
                    if (cGelombang !== 'BELUM MASUK GELOMBANG') return false;
                } else {
                    if (cGelombang.indexOf(fGelombang) === -1) return false;
                }
            }

            if (fStatus !== '' && cStatus.indexOf(fStatus) === -1) {
                return false;
            }

            if (fVerifikator !== '') {
                if (fVerifikator === 'BELUM DISPOSISI') {
                    if (cVerifikator !== 'BELUM DISPOSISI') return false;
                } else {
                    if (cVerifikator !== fVerifikator) return false;
                }
            }

            return true;
        }
    );

    function updateRekapStatus() {
        var counts = {};
        $('.rekap-val').each(function() {
            var stName = $(this).data('status');
            counts[stName] = 0;
        });

        table.rows({ filter: 'applied' }).data().each(function(row) {
            var statusHtml = row[8];
            var tempDiv = document.createElement('div');
            tempDiv.innerHTML = statusHtml;
            var statusText = tempDiv.textContent || tempDiv.innerText || '';
            statusText = statusText.trim();

            for (var key in counts) {
                if (statusText.indexOf(key) !== -1) {
                    counts[key]++;
                }
            }
        });

        $('.rekap-val').each(function() {
            var stName = $(this).data('status');
            $(this).text(counts[stName] || 0);
        });
    }

    $('#filterJenis, #filterGelombang, #filterStatus, #filterVerifikator').on('change', function() {
        table.draw();
        updateRekapStatus();
    });
    
    window.resetFilter = function() {
        $('#filterJenis, #filterGelombang, #filterStatus, #filterVerifikator').val('');
        table.draw();
        updateRekapStatus();
    };

    // LOGIKA MODAL STATUS KHUSUS / PEMBATALAN PROCESS
    $('#tableKasubdit').on('click', '.btn-edit-status-khusus', function() {
        var id = $(this).data('id');
        var nama = $(this).data('nama');
        var jenis = $(this).data('jenis');
        var ket = $(this).data('ket') || '';

        $('#status_khusus_id').val(id);
        $('#status_khusus_nama').text(nama);
        $('#status_khusus_jenis').text(jenis);
        $('#status_khusus_alasan').val(ket);

        // Pengkondisian Khusus Opsi JF Bukan PKP (Hanya untuk Kenaikan Jabatan)
        if (jenis === 'Kenaikan Jabatan') {
            $('#opt_jf_bukan_pkp').show();
        } else {
            $('#opt_jf_bukan_pkp').hide();
        }

        $('#group_alasan_lainnya').addClass('d-none');
        $('#modalStatusKhusus').modal('show');
    });

    $('#status_khusus_alasan').on('change', function() {
        if ($(this).val() === 'Lainnya') {
            $('#group_alasan_lainnya').removeClass('d-none');
            $('#alasan_lainnya_text').prop('required', true);
        } else {
            $('#group_alasan_lainnya').addClass('d-none');
            $('#alasan_lainnya_text').prop('required', false);
        }
    });

    $('#formStatusKhusus').on('submit', function(e) {
        e.preventDefault();
        var btn = $('#btnSimpanStatusKhusus');
        var originalHtml = btn.html();

        var selectedAlasan = $('#status_khusus_alasan').val();
        if (selectedAlasan === 'Lainnya') {
            var customAlasan = $('#alasan_lainnya_text').val().trim();
            if (customAlasan === '') {
                Swal.fire('Peringatan', 'Mohon isi detail alasan pembatalan.', 'warning');
                return;
            }
            $('#status_khusus_alasan').append('<option value="' + customAlasan + '" selected>' + customAlasan + '</option>');
        }

        btn.attr('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Menyimpan...');

        $.ajax({
            url: 'proses_update_status_khusus.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil!',
                        text: res.message,
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Gagal!', res.message, 'error');
                    btn.attr('disabled', false).html(originalHtml);
                }
            },
            error: function() {
                Swal.fire('Error!', 'Terjadi kesalahan sistem saat memperbarui status.', 'error');
                btn.attr('disabled', false).html(originalHtml);
            }
        });
    });

    $('#modalExportExcel').on('show.bs.modal', function() {
        $('#exp_f_jenis').val($('#filterJenis').val());
        $('#exp_f_gelombang').val($('#filterGelombang').val());
        $('#exp_f_status').val($('#filterStatus').val());
        $('#exp_f_verifikator').val($('#filterVerifikator').val());
    });

    $('#checkAll').on('click', function() { $('.check-child').prop('checked', this.checked); toggleBulkButton(); });
    $('#tableKasubdit').on('change', '.check-child', function() { toggleBulkButton(); });
    function toggleBulkButton() {
        var count = $('.check-child:checked').length;
        if (count > 0) { $('#bulkActionArea').show(); $('.countCheck').text(count); } 
        else { $('#bulkActionArea').hide(); }
    }

    let globalWizardData = [];

    $('#btnNextStep').on('click', function() {
        const gelId = $('#wizard_gelombang').val();
        const gelNama = $('#wizard_gelombang option:selected').data('nama');
        
        if(!gelId) { Swal.fire('Perhatian', 'Silakan pilih gelombang terlebih dahulu', 'warning'); return; }

        $('#text_gel_selected').text(gelNama);
        
        const activeJenisFilter = $('#filterJenis').val();
        $('#wizard_filter_jenis').val(activeJenisFilter);

        $('#wizard_data_body').html('<tr><td colspan="4" class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x"></i><br>Sedang memuat data...</td></tr>');
        $('#step1').removeClass('active');
        $('#step2').addClass('active');

        $.ajax({
            url: 'get_pengusul.php',
            type: 'GET',
            data: { gel_id: gelId },
            dataType: 'json',
            success: function(res) {
                if(res.status === 'success' && res.data.length > 0) {
                    globalWizardData = res.data;
                    renderWizardTable();
                } else {
                    globalWizardData = [];
                    renderWizardTable();
                }
            },
            error: function(xhr) {
                console.error("Error Fetch:", xhr.responseText);
                $('#wizard_data_body').html('<tr><td colspan="4" class="text-center text-danger">Gagal mengambil data.</td></tr>');
            }
        });
    });

    function renderWizardTable() {
        const jenisFilter = $('#wizard_filter_jenis').val();
        let filteredData = globalWizardData;

        if (jenisFilter) {
            filteredData = globalWizardData.filter(row => row.jenis_pengajuan === jenisFilter);
        }

        let html = '';
        if (filteredData.length > 0) {
            filteredData.forEach(row => {
                html += `<tr data-jenis="${row.jenis_pengajuan}">
                    <td class="text-center"><input type="checkbox" class="check-wizard" value="${row.id}"></td>
                    <td><div class="font-weight-bold text-dark">${row.nama}</div><small class="text-muted">${row.nip}</small></td>
                    <td><span class="badge badge-light border">${row.jenis_pengajuan || '-'}</span></td>
                    <td class="text-center"><span class="badge badge-success px-3 py-2 shadow-sm">${row.status_pengajuan}</span></td>
                </tr>`;
            });
            $('#container_list_direktur').show();
            $('#wizard_empty').addClass('d-none');
        } else {
            $('#container_list_direktur').hide();
            $('#wizard_empty').removeClass('d-none');
        }

        $('#wizard_data_body').html(html);
        $('#wizard_count_badge').text(filteredData.length);
        $('#checkAllWizard').prop('checked', false);
    }

    $('#wizard_filter_jenis').on('change', function() {
        renderWizardTable();
    });

    $('#btnPrevStep').on('click', function() {
        $('#step2').removeClass('active');
        $('#step1').addClass('active');
    });

    $('#checkAllWizard').on('click', function() {
        $('.check-wizard:visible').prop('checked', this.checked);
    });

    $('#wizard_data_body').on('click', 'tr', function(e) {
        if (e.target.type !== 'checkbox') {
            const cb = $(this).find('input.check-wizard');
            cb.prop('checked', !cb.prop('checked'));
        }
    });

    $('#btnFinalKirimDirektur').on('click', function() {
        const selectedIds = [];
        $('.check-wizard:checked').each(function() { selectedIds.push($(this).val()); });
        const gelId = $('#wizard_gelombang').val();
        const gelNama = $('#wizard_gelombang option:selected').data('nama');

        if(selectedIds.length === 0) { Swal.fire('Peringatan', 'Pilih minimal satu pengusul.', 'warning'); return; }

        Swal.fire({
            title: 'Konfirmasi',
            text: 'Kirim ' + selectedIds.length + ' data ke Direktur untuk ' + gelNama + '?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#6610f2',
            confirmButtonText: 'Ya, Kirim!'
        }).then((result) => {
            if (result.isConfirmed) {
                const btn = $(this);
                const originalText = btn.html();
                
                btn.html('<i class="fas fa-spinner fa-spin"></i> Memproses...').attr('disabled', true);
                
                $.ajax({
                    url: 'proses_ke_direktur.php',
                    type: 'POST',
                    data: { 
                        id_pengajuan: selectedIds.join(','), 
                        status_baru: 'Proses Direktur',
                        gelombang: gelId 
                    },
                    dataType: 'json',
                    success: function(res) {
                        if(res.status === 'success') { 
                            Swal.fire('Berhasil', res.message, 'success')
                            .then(() => { location.reload(); });
                        } else { 
                            Swal.fire('Gagal', res.message, 'error');
                            btn.html(originalText).attr('disabled', false); 
                        }
                    },
                    error: function(xhr) {
                        Swal.fire('Error Sistem', 'Terjadi kesalahan pada server.', 'error');
                        btn.html(originalText).attr('disabled', false);
                    }
                });
            }
        });
    });

    $('#tableKasubdit').on('click', '.btn-batal-direktur', function() {
        const id = $(this).data('id');
        const nama = $(this).data('nama');

        Swal.fire({
            title: 'Batalkan Pengiriman?',
            text: 'Pengusul "' + nama + '" akan dikembalikan statusnya ke "Disetujui Verifikator".',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, Batalkan!',
            cancelButtonText: 'Tutup'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'proses_batal_direktur.php',
                    type: 'POST',
                    data: { id_pengajuan: id },
                    dataType: 'json',
                    success: function(res) {
                        if (res.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Berhasil!',
                                text: res.message,
                                timer: 1500,
                                showConfirmButton: false
                            }).then(() => { location.reload(); });
                        } else {
                            Swal.fire('Gagal!', res.message, 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error!', 'Terjadi kesalahan sistem saat membatalkan.', 'error');
                    }
                });
            }
        });
    });

    $('#tableKasubdit').on('click', '.btn-buka-disposisi', function() {
        $('#disp_id_pengajuan').val($(this).data('id'));
        $('#disp_nama_pengusul').text($(this).data('nama'));
        $('#disp_select_verif').val($(this).data('verif'));
    });

    $('#tableKasubdit').on('click', '.btn-buka-gelombang', function() {
        const id = $(this).data('id');
        const gel = $(this).data('gel') || ''; 
        
        $('#gel_id_pengajuan').val(id);
        $('#count_gel_text').text('1');
        $('#select_gelombang_masal').val(gel); 
        $('#modalGelombang').modal('show');
    });

    $('#formDisposisi').on('submit', function(e) {
        e.preventDefault();
        const btn = $('#btnSimpanDisp');
        btn.attr('disabled', true).text('Loading...');
        $.ajax({
            url: 'proses_disposisi.php', type: 'POST', data: $(this).serialize(), dataType: 'json',
            success: function(res) { 
                if(res.status === 'success') { location.reload(); } 
                else { Swal.fire('Gagal', res.message, 'error'); btn.attr('disabled', false).text('Kirim Tugas'); } 
            },
            error: function() { Swal.fire('Error', 'Gagal memproses disposisi.', 'error'); btn.attr('disabled', false).text('Kirim Tugas'); }
        });
    });

    $('#btnBulkDisposisi').on('click', function() {
        var ids = []; $('.check-child:checked').each(function() { ids.push($(this).val()); });
        $('#disp_id_pengajuan').val(ids.join(','));
        $('#disp_nama_pengusul').text(ids.length + ' Pengusul Terpilih');
        $('#modalDisposisi').modal('show');
    });

    $('#btnBulkGelombang').on('click', function() {
        var ids = []; $('.check-child:checked').each(function() { ids.push($(this).val()); });
        $('#gel_id_pengajuan').val(ids.join(','));
        $('#count_gel_text').text(ids.length);
        $('#select_gelombang_masal').val(''); 
        $('#modalGelombang').modal('show');
    });

    $('#formGelombangMasal').on('submit', function(e) {
        e.preventDefault();
        const btn = $('#btnSimpanGel');
        btn.attr('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Menyimpan...');

        $.ajax({
            url: 'proses_gelombang_masal.php', 
            type: 'POST', 
            data: $(this).serialize(), 
            dataType: 'json',
            success: function(res) { 
                if(res.status === 'success') { 
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil',
                        text: res.message || 'Gelombang berhasil diperbarui!',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        location.reload(); 
                    });
                } else { 
                    Swal.fire('Gagal', res.message || 'Gagal update gelombang.', 'error'); 
                    btn.attr('disabled', false).html('<i class="fas fa-save mr-1"></i> Update Gelombang'); 
                } 
            },
            error: function(xhr, status, error) { 
                console.error("AJAX Error:", xhr.responseText);
                Swal.fire('Error Sistem', 'Terjadi kesalahan sistem saat memperbarui gelombang.', 'error'); 
                btn.attr('disabled', false).html('<i class="fas fa-save mr-1"></i> Update Gelombang'); 
            }
        });
    });
});
</script>