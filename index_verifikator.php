<?php
/**
 * FILE: index_verifikator.php
 * DESKRIPSI: Daftar Pengajuan Uji Kompetensi (Perpindahan & Kenaikan Jabatan)
 * TAMPILAN: AdminLTE 3 Standard dengan Modern Rekap Box & Custom Status Colors
 * TERAKHIR DIPERBARUI: Maret 2026
 */

// ==================================================================================
// 1. PENGATURAN AWAL: SESSION & AUTORISASI
// ==================================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ----------------------------------------------------------------------------------
// 2. PANGGIL FILE PENDUKUNG KRITIS & INISIALISASI
// ----------------------------------------------------------------------------------
require_once 'auth_guard.php'; 
require_once 'koneksi.php';    

if (!isset($conn) || $conn === false) {
    die("Koneksi database gagal.");
}

$user_id_sesi    = $_SESSION['user_id_sesi']    ?? 0; 
$user_nip_sesi   = $_SESSION['user_nip_sesi']   ?? '';
$user_nama_sesi  = $_SESSION['user_nama_sesi']  ?? 'Pengguna JF'; 
$user_role_sesi  = $_SESSION['user_role_sesi']  ?? 'User'; 

$page        = 'ujikom'; 
$sub_page    = 'list_semua_pengajuan'; 
$page_title  = 'Daftar Semua Tugas Pengajuan Uji Kompetensi'; 

$NAMA_TABEL_PENGAJUAN = "pengajuan_ujikom"; 
$data_pengajuan       = []; 
$is_error             = false;

// --- Inisialisasi Counter Rekap ---
$count_lengkap = 0; 
$count_proses  = 0; 
$count_kosong  = 0;
$rekap_status  = []; 

// ----------------------------------------------------------------------------------
// 3. BLOK LOGIKA & FETCH DATA UTAMA (DILONGGARKAN UNTUK SEMUA JENIS PENGAJUAN)
// ----------------------------------------------------------------------------------
$allowed_statuses = [
    'Menunggu Verifikasi', 'Verifikasi Dokumen', 'Disetujui Verifikator', 
    'Proses Direktur', 'Proses PPSDM', 'Terjadwal', 'Cadangan', 'Perlu Perbaikan'
];

$filter_status = $_GET['status'] ?? 'Semua Status';

// PERBAIKAN: Menghapus filter TRIM(jenis_pengajuan) LIKE 'Perpindahan%'
$query_parts = ["verifikator_id = ?"];
$params = [$user_id_sesi];
$types = "i";

if ($filter_status !== 'Semua Status' && in_array($filter_status, $allowed_statuses)) {
    $query_parts[] = "status_pengajuan = ?";
    $params[] = $filter_status;
    $types .= "s";
}

$where_clause = implode(" AND ", $query_parts);

try {
    // Mengambil kolom jenis_pengajuan agar bisa ditampilkan di tabel
    $sql = "SELECT id, nip, nama, jenis_pengajuan, tanggal_pengajuan, status_pengajuan, progres_kelengkapan 
            FROM {$NAMA_TABEL_PENGAJUAN} WHERE {$where_clause} ORDER BY tanggal_pengajuan DESC"; 
    
    if ($stmt = $conn->prepare($sql)) {
        if (!empty($params)) { $stmt->bind_param($types, ...$params); }
        $stmt->execute();
        $result = $stmt->get_result();
        $data_pengajuan = $result->fetch_all(MYSQLI_ASSOC);
        $total_data_ditemukan = count($data_pengajuan);
        $stmt->close();

        // Hitung Rekapitulasi
        foreach ($data_pengajuan as $row) {
            $prog = (int)$row['progres_kelengkapan'];
            if ($prog >= 100) { $count_lengkap++; }
            elseif ($prog > 0) { $count_proses++; }
            else { $count_kosong++; }

            $st_name = $row['status_pengajuan'];
            if (!empty($st_name)) {
                $rekap_status[$st_name] = ($rekap_status[$st_name] ?? 0) + 1;
            }
        }
        ksort($rekap_status);
    }
} catch (Exception $e) {
    $is_error = true;
    $error_message = "❌ Error Database! " . htmlspecialchars($e->getMessage());
}

require_once 'template/header.php'; 
require_once 'template/navbar.php'; 
require_once 'template/sidebar.php'; 
?>

<style>
    /* MODERNISED REKAP BOX STYLE */
    .rekap-card-main {
        background: linear-gradient(135deg, #0f2027, #203a43, #2c5364);
        border: none; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    }
    .rekap-card-side {
        background: linear-gradient(135deg, #141e30, #243b55);
        border: none; border-radius: 15px;
    }
    .modern-status-item {
        background: rgba(255, 255, 255, 0.07);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 12px; padding: 12px 15px; margin-bottom: 10px;
        display: flex; align-items: center; transition: all 0.2s;
    }
    .modern-status-item:hover {
        transform: translateY(-3px); background: rgba(255, 255, 255, 0.12);
    }
    .status-icon-box {
        width: 38px; height: 38px; border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        margin-right: 12px; font-size: 1.1rem;
    }
    .status-label { color: rgba(255,255,255,0.9); font-weight: 500; font-size: 0.88rem; flex-grow: 1; margin-bottom: 0; }
    .status-badge-count { background: rgba(255, 255, 255, 0.2); color: #fff; padding: 2px 10px; border-radius: 20px; font-weight: 700; }
    
    .scroll-rekap-modern { max-height: 280px; overflow-y: auto; padding: 10px; }
    .scroll-rekap-modern::-webkit-scrollbar { width: 5px; }
    .scroll-rekap-modern::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); border-radius: 10px; }

    /* Custom Status Colors for Badge */
    .bg-pink { background-color: #e83e8c !important; color: white !important; }
    .bg-stabilo { background-color: #7df9ff !important; color: #333 !important; } 
    .bg-orange { background-color: #fd7e14 !important; color: white !important; }
</style>

<div class="content-wrapper"> 
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-white"><?php echo $page_title; ?></h1>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            
            <?php if ($is_error): ?>
                <div class="alert alert-danger"><?= $error_message; ?></div>
            <?php endif; ?>
            
            <div class="row">
                <div class="col-md-8">
                    <div class="card h-100 rekap-card-main">
                        <div class="card-header border-0 bg-transparent">
                            <h3 class="card-title text-white font-weight-bold">
                                <i class="fas fa-chart-pie mr-2 text-warning"></i> Rekapitulasi Status Pengajuan
                            </h3>
                        </div>
                        <div class="card-body pt-0">
                            <div class="row scroll-rekap-modern">
                                <?php if (empty($rekap_status)): ?>
                                    <div class="col-12 text-center py-4">
                                        <p class="text-white-50 small">Tidak ada data status untuk ditampilkan.</p>
                                    </div>
                                <?php else: 
                                    $chunks = array_chunk($rekap_status, ceil(count($rekap_status) / 2), true);
                                    foreach ($chunks as $chunk): ?>
                                        <div class="col-md-6">
                                            <?php foreach ($chunk as $status_label => $jumlah): 
                                                $icon = "fa-tag"; $icon_color = "text-info";
                                                if(stripos($status_label, 'Perbaikan') !== false) { $icon = "fa-exclamation-circle"; $icon_color = "text-danger"; }
                                                elseif(stripos($status_label, 'Disetujui') !== false || stripos($status_label, 'Terjadwal') !== false) { $icon = "fa-check-circle"; $icon_color = "text-success"; }
                                                elseif(stripos($status_label, 'Proses') !== false || stripos($status_label, 'Verifikasi') !== false) { $icon = "fa-spinner fa-spin"; $icon_color = "text-warning"; }
                                            ?>
                                                <div class="modern-status-item">
                                                    <div class="status-icon-box bg-dark">
                                                        <i class="fas <?= $icon; ?> <?= $icon_color; ?>"></i>
                                                    </div>
                                                    <p class="status-label"><?= $status_label; ?></p>
                                                    <span class="status-badge-count"><?= $jumlah; ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endforeach; 
                                endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card h-100 rekap-card-side">
                        <div class="card-header border-0 bg-transparent">
                            <h3 class="card-title font-weight-bold text-white">
                                <i class="fas fa-tasks mr-2 text-info"></i> Rekap Kelengkapan
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="modern-status-item">
                                <div class="status-icon-box" style="background: rgba(0, 255, 204, 0.1);">
                                    <i class="fas fa-check-double" style="color: #00ffcc;"></i>
                                </div>
                                <p class="status-label">Lengkap (100%)</p>
                                <span class="status-badge-count"><?= $count_lengkap; ?></span>
                            </div>
                            <div class="modern-status-item">
                                <div class="status-icon-box" style="background: rgba(255, 204, 0, 0.1);">
                                    <i class="fas fa-hourglass-half" style="color: #ffcc00;"></i>
                                </div>
                                <p class="status-label">Proses Verifikasi (< 100%)</p>
                                <span class="status-badge-count"><?= $count_proses; ?></span>
                            </div>
                            <div class="modern-status-item">
                                <div class="status-icon-box" style="background: rgba(255, 102, 102, 0.1);">
                                    <i class="fas fa-folder-open" style="color: #ff6666;"></i>
                                </div>
                                <p class="status-label">Belum Diverifikasi (0%)</p>
                                <span class="status-badge-count"><?= $count_kosong; ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mt-4">
                <div class="col-12">
                    <div class="card card-indigo card-outline shadow">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-list mr-1"></i> Tugas Verifikasi Saya (Disposisi Kasubdit)
                                <span class="badge badge-primary ml-2">(<?= $total_data_ditemukan ?? 0; ?> Data)</span>
                            </h3>
                        </div>
                        
                        <div class="card-body">
                            <div class="mb-4 bg-light p-3" style="border-radius: 8px; border: 1px solid #ddd;">
                                <form method="GET" action="" class="form-inline">
                                    <div class="form-group mr-3">
                                        <label class="mr-2 font-weight-bold">Filter Status:</label>
                                        <select name="status" class="form-control form-control-sm select2" style="min-width: 200px;">
                                            <option value="Semua Status">Semua Status</option>
                                            <?php foreach ($allowed_statuses as $st): ?>
                                                <option value="<?= $st; ?>" <?= ($st == $filter_status) ? 'selected' : ''; ?>><?= $st; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <button class="btn btn-primary btn-sm shadow-sm" type="submit"><i class="fas fa-filter mr-1"></i> Terapkan</button>
                                    <a href="index_verifikator.php" class="btn btn-default btn-sm ml-2 border shadow-sm"><i class="fas fa-sync"></i> Reset</a>
                                </form>
                            </div>

                            <div class="table-responsive"> 
                                <table id="tabelUjikomSemua" class="table table-bordered table-striped table-hover">
                                    <thead class="bg-indigo text-white">
                                        <tr>
                                            <th width="5%">No.</th>
                                            <th>NIP</th>
                                            <th>Nama Pegawai</th>
                                            <th>Jenis Pengajuan</th> <th width="12%">Kelengkapan</th>
                                            <th>Tgl Pengajuan</th>
                                            <th>Status Saat Ini</th>
                                            <th width="10%">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $no = 1; 
                                        foreach ($data_pengajuan as $data): 
                                            $status = $data['status_pengajuan'];
                                            $jenis_pengajuan = $data['jenis_pengajuan'];
                                            $badge_class = 'bg-secondary';

                                            // CUSTOM STATUS COLORS MAPPING
                                            switch ($status) {
                                                case 'Menunggu Verifikasi': $badge_class = 'bg-warning text-dark'; break;
                                                case 'Verifikasi Dokumen':  $badge_class = 'bg-info'; break;
                                                case 'Disetujui Verifikator':$badge_class = 'bg-success'; break;
                                                case 'Proses Direktur':     $badge_class = 'bg-navy'; break;
                                                case 'Proses PPSDM':        $badge_class = 'bg-pink'; break;
                                                case 'Terjadwal':           $badge_class = 'bg-stabilo'; break;
                                                case 'Cadangan':            $badge_class = 'bg-orange'; break;
                                                case 'Perlu Perbaikan':     $badge_class = 'bg-danger'; break;
                                            }

                                            // PERBAIKAN: Menentukan URL Periksa secara dinamis berdasarkan Jenis Pengajuan
                                            $url_periksa = 'detail_verif_perpindahan.php'; // default
                                            $badge_jenis = 'badge-info';

                                            if (stripos($jenis_pengajuan, 'Kenaikan') !== false) {
                                                $url_periksa = 'detail_verif_kenaikan.php'; // Diarahkan ke modul verif kenaikan
                                                $badge_jenis = 'bg-teal';
                                            }
                                        ?>
                                            <tr>
                                                <td class="text-center font-weight-bold"><?= $no++; ?></td>
                                                <td><code><?= htmlspecialchars($data['nip']); ?></code></td>
                                                <td><strong><?= htmlspecialchars($data['nama']); ?></strong></td>
                                                <td>
                                                    <span class="badge <?= $badge_jenis; ?> p-1-5"><?= htmlspecialchars($jenis_pengajuan); ?></span>
                                                </td>
                                                <td>
                                                    <?php $progres = (int)$data['progres_kelengkapan']; 
                                                          $p_class = ($progres >= 100) ? 'bg-success' : (($progres >= 50) ? 'bg-warning' : 'bg-danger'); ?>
                                                    <div class="progress progress-xs mb-1">
                                                        <div class="progress-bar <?= $p_class; ?>" style="width: <?= $progres; ?>%"></div>
                                                    </div>
                                                    <small class="badge <?= $p_class; ?>"><?= $progres; ?>%</small>
                                                </td>
                                                <td><?= date('d/m/Y', strtotime($data['tanggal_pengajuan'])); ?></td>
                                                <td class="text-center">
                                                    <span class="badge <?= $badge_class; ?> p-2 shadow-sm" style="min-width: 130px; border-radius: 5px;">
                                                        <?= strtoupper(htmlspecialchars($status)); ?>
                                                    </span>
                                                </td>
                                                <td class="text-center">
                                                    <a href="<?= $url_periksa; ?>?id=<?= $data['id']; ?>" class="btn btn-info btn-xs shadow-sm">
                                                        <i class="fas fa-search mr-1"></i> Periksa
                                                    </a>
                                                </td>
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
    </section>
</div>

<?php require_once 'template/footer.php'; ?>

<script>
    $(function () {
      $("#tabelUjikomSemua").DataTable({
        "responsive": true, 
        "autoWidth": false, 
        "order": [[5, "desc"]], // Sort by Tanggal Pengajuan (kolom ke-6 sekarang)
        "language": { 
            "search": "Pencarian Cepat:", 
            "lengthMenu": "Tampilkan _MENU_ data",
            "zeroRecords": "Data pengajuan tidak ditemukan",
            "info": "Menampilkan _START_ sampai _END_ dari _TOTAL_ pengajuan"
        },
        "pageLength": 10
      });
    });
</script>