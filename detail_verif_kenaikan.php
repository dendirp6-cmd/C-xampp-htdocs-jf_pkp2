<?php
// =========================================================================================
// FILE: detail_verif_kenaikan.php - Halaman Penilaian Persyaratan Uji Kompetensi (KENAIKAN)
// PERBAIKAN: Penambahan Syarat Umum, Redirect Kasubdit, & Relayout UI Kesimpulan
// =========================================================================================
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'auth_guard.php'; 
require_once 'koneksi.php'; 

// --- PENGGUNAAN DATA SESSION ---
$user_email_sesi = $_SESSION['user_email_sesi'] ?? $_SESSION['email'] ?? $_SESSION['Email'] ?? '';
$user_nama_sesi  = $_SESSION['user_nama_sesi'] ?? $_SESSION['nama'] ?? 'Pengguna JF';
$user_id_sesi    = $_SESSION['user_id_sesi'] ?? 0;
$user_role_sesi  = strtoupper($_SESSION['user_role_sesi'] ?? '');

// Identifikasi Role
$is_pengusul    = (strpos($user_role_sesi, 'PENGUSUL') !== false);
$is_ppsdm       = (strpos($user_role_sesi, 'PPSDM') !== false);
$is_verifikator = (strpos($user_role_sesi, 'VERIFIKATOR') !== false);
$is_evaluator   = (strpos($user_role_sesi, 'EVALUATOR') !== false);
$is_kasubdit    = (strpos($user_role_sesi, 'KASUBDIT') !== false);
$is_admin       = (strpos($user_role_sesi, 'ADMIN') !== false);

// --- KONSTANTA & PENGATURAN ---
$NAMA_KOLOM_ID_PENGAJUAN = "id"; 
$NAMA_TABEL_PENGAJUAN = "pengajuan_ujikom"; 
$BASE_URL_DOKUMEN = 'uploads/kenaikan/'; 

$page = 'ujikom'; 
$sub_page = 'daftar_verif_kenaikan'; 
$page_title = 'Verifikasi Peserta Kenaikan Jabatan';

$pengajuan_id = $_GET['id'] ?? null; 
$pegawai = null;
$success_redirect_trigger = false;

if (!$pengajuan_id) {
    die("ID Pengajuan tidak valid atau tidak ditemukan.");
}

// -----------------------------------------------------------------------------------------
// DEFINISI 3 PERSYARATAN UMUM KENAIKAN
// -----------------------------------------------------------------------------------------
$SYARAT_UMUM_CONFIG = [
    1 => ['db_status' => 'syarat_1_status', 'label' => 'Usia pegawai saat pengusulan tidak melebihi usia pensiun'],
    2 => ['db_status' => 'syarat_2_status', 'label' => 'Akumulasi Angka Kredit (PAK) mencukupi / memenuhi syarat minimum untuk jenjang tujuan'],
    3 => ['db_status' => 'syarat_3_status', 'label' => 'Kesesuaian usulan jenjang kenaikan jabatan yang dituju (berdasarkan formasi/peta jabatan)']
];

// -----------------------------------------------------------------------------------------
// DEFINISI 5 DOKUMEN WAJIB KENAIKAN
// -----------------------------------------------------------------------------------------
$DOKUMEN_CONFIG = [
    1 => ['db_file' => 'file_sk_pangkat',      'db_status' => 'd6_sk_pangkat_status',        'label' => 'SK Kenaikan Pangkat Terakhir'],
    2 => ['db_file' => 'file_sk_jabatan',      'db_status' => 'd7_sk_jabatan_status',        'label' => 'SK Jabatan Fungsional Terakhir'],
    3 => ['db_file' => 'file_pak',             'db_status' => 'd9_ijazah_transkrip_status',  'label' => 'Penetapan Angka Kredit (PAK) Terakhir'],
    4 => ['db_file' => 'file_skp',             'db_status' => 'd8_nilai_skp_status',         'label' => 'Penilaian Kinerja (SKP) 2 Tahun Terakhir'],
    5 => ['db_file' => 'file_surat_usulan_uji', 'db_status' => 'd3_usulan_ujikom_status',    'label' => 'Surat Rekomendasi / Usulan dari Instansi']
];

// --- 1. PROSES SIMPAN SUBMIT VERIFIKASI (Hanya jika bukan PPSDM) ---
if (!$is_ppsdm && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_verifikasi'])) {
    $has_perbaikan = false;
    $update_parts = [];
    $types = "";
    $params = [];

    // Loop 3 Status Persyaratan Umum
    foreach ($SYARAT_UMUM_CONFIG as $idx => $cfg) {
        $post_status = $_POST[$cfg['db_status']] ?? 'Belum Diperiksa';
        if ($post_status === 'Tidak Sesuai') {
            $has_perbaikan = true;
        }
        $update_parts[] = "{$cfg['db_status']} = ?";
        $types .= "s"; 
        $params[] = $post_status;
    }

    // Loop 5 Status Dokumen Fisik
    foreach ($DOKUMEN_CONFIG as $idx => $cfg) {
        $post_status = $_POST[$cfg['db_status']] ?? 'Belum Diperiksa';
        if ($post_status === 'Tidak Sesuai') {
            $has_perbaikan = true;
        }
        $update_parts[] = "{$cfg['db_status']} = ?";
        $types .= "s"; 
        $params[] = $post_status;
    }

    // Penentuan Status Pengajuan Baru
    if ($has_perbaikan) {
        $status_baru = 'Perlu Perbaikan';
        $progres_baru = 50; 
    } else {
        $status_baru = 'Disetujui Verifikator';
        $progres_baru = 100;
    }

    $catatan_umum = trim($_POST['catatan_evaluator'] ?? '');

    $update_parts[] = "status_pengajuan = ?";
    $update_parts[] = "progres_kelengkapan = ?";
    $update_parts[] = "catatan_evaluator = ?";
    $update_parts[] = "is_read_notif = 0"; 
    
    $types .= "sis"; 
    $params[] = $status_baru;
    $params[] = (int)$progres_baru;
    $params[] = $catatan_umum;

    // Gabungkan query string UPDATE
    $sql_update = "UPDATE {$NAMA_TABEL_PENGAJUAN} SET " . implode(", ", $update_parts) . " WHERE {$NAMA_KOLOM_ID_PENGAJUAN} = ?";
    
    $types .= "i";
    $params[] = $pengajuan_id;

    if ($stmt = $conn->prepare($sql_update)) {
        $stmt->bind_param($types, ...$params);
        if ($stmt->execute()) {
            $success_redirect_trigger = true;
        } else {
            die("<div style='color:#721c24; padding:25px; background-color:#f8d7da; border:2px solid #f5c6cb; border-radius:5px; margin:20px; font-family:sans-serif;'>
                    <h3 style='margin-top:0;'>❌ Gagal Menyimpan Status ke Database!</h3>
                    <p>MySQL Error: <b style='color:#111;'> " . htmlspecialchars($stmt->error) . "</b></p>
                    <hr>
                    <p>Harap pastikan kolom persyaratan (seperti syarat_1_status, dst) sudah ada di database.</p>
                    <a href='detail_verif_kenaikan.php?id=" . urlencode($pengajuan_id) . "' style='display:inline-block; padding:8px 15px; background:#721c24; color:#fff; text-decoration:none; border-radius:4px; margin-top:10px;'>Kembali Coba Lagi</a>
                 </div>");
        }
        $stmt->close();
    }
}

// --- 2. FETCH DATA UTAMA PESERTA ---
$sql_select = "SELECT * FROM {$NAMA_TABEL_PENGAJUAN} WHERE {$NAMA_KOLOM_ID_PENGAJUAN} = ? LIMIT 1";
if ($stmt = $conn->prepare($sql_select)) {
    $stmt->bind_param("i", $pengajuan_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $pegawai = $result->fetch_assoc();
    $stmt->close();
}

require_once 'template/header.php';
require_once 'template/navbar.php';
require_once 'template/sidebar.php';
?>

<style>
    .card-profile-header { background: linear-gradient(135deg, #1e3c72, #2a5298); color: white; border-radius: 10px; }
    .status-badge { padding: 6px 14px; border-radius: 20px; font-weight: bold; font-size: 0.9rem; display: inline-block; }
    .table-verif th { vertical-align: middle !important; text-align: center; }
    .table-verif td { vertical-align: middle !important; }
    .select-verif { font-weight: bold; border-radius: 6px; padding: 4px 8px; transition: all 0.2s; }
    .preview-btn { transition: all 0.2s; }
    .preview-btn:hover { transform: scale(1.05); }
    .box-log { background-color: #f8f9fa; border-left: 4px solid #17a2b8; padding: 12px; border-radius: 4px; max-height: 200px; overflow-y: auto; }
</style>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="font-weight-bold text-white"><i class="fas fa-user-check mr-2 text-info"></i><?= $page_title; ?></h1>
                </div>
                <div class="col-sm-6 text-right">
                    <a href="index_verifikator.php" class="btn btn-default border shadow-sm"><i class="fas fa-arrow-left mr-1"></i> Kembali ke List</a>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <?php if ($pegawai): ?>
                
                <?php if ($is_ppsdm): ?>
                <div class="alert alert-info shadow-sm mb-4">
                    <i class="fas fa-info-circle mr-2"></i><strong>Mode Tonton (View-Only):</strong> Anda login sebagai akun PPSDM. Menu validasi dan tombol simpan dinonaktifkan.
                </div>
                <?php endif; ?>

                <div class="card card-profile-header shadow mb-4">
                    <div class="card-body p-4">
                        <div class="row align-items-center">
                            <div class="col-md-2 text-center mb-3 mb-md-0">
                                <div class="bg-white text-dark d-inline-block rounded-circle p-3 shadow-lg" style="width: 90px; height: 90px;">
                                    <i class="fas fa-id-card fa-3x mt-1 text-primary"></i>
                                </div>
                            </div>
                            <div class="col-md-5">
                                <h3 class="font-weight-bold mb-1"><?= htmlspecialchars($pegawai['nama'] ?? '-'); ?></h3>
                                <p class="mb-2 lead" style="font-size: 1.1rem; opacity: 0.9;"><i class="fas fa-barcode mr-1"></i> NIP: <code><?= htmlspecialchars($pegawai['nip'] ?? '-'); ?></code></p>
                                <p class="mb-0 small"><i class="fas fa-calendar-alt mr-1"></i> Tanggal Masuk Berkas: <?= isset($pegawai['tanggal_pengajuan']) ? date('d F Y (H:i)', strtotime($pegawai['tanggal_pengajuan'])) : '-'; ?> WIB</p>
                            </div>
                            <div class="col-md-5 text-md-right mt-3 mt-md-0">
                                <!-- KOTAK KESIMPULAN KELENGKAPAN -->
                                <div class="bg-white text-dark p-2 rounded shadow-sm d-inline-block text-left mr-2 mb-2 mb-md-0" style="vertical-align: top; border-left: 5px solid #17a2b8;">
                                    <small class="text-muted d-block font-weight-bold text-uppercase">Kesimpulan Kelengkapan:</small>
                                    <div class="mt-1">
                                        <span id="summary-result" class="status-badge bg-secondary shadow-sm text-white" style="font-size: 0.85rem; padding: 4px 10px;">MENGHITUNG...</span>
                                    </div>
                                </div>

                                <!-- KOTAK STATUS SAAT INI -->
                                <div class="bg-white text-dark p-2 rounded shadow-sm d-inline-block text-left" style="vertical-align: top; min-width: 180px; border-left: 5px solid #ffc107;">
                                    <small class="text-muted d-block font-weight-bold text-uppercase">Status Berkas Saat Ini:</small>
                                    <span class="text-dark font-weight-bold d-block mt-1" style="font-size: 1rem;">
                                        <i class="fas fa-info-circle mr-1 text-warning"></i> <?= strtoupper(htmlspecialchars($pegawai['status_pengajuan'] ?? 'BELUM DIKETAHUI')); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <form action="" method="POST" id="form-verifikasi-ujikom">
                    
                    <!-- ========================================================= -->
                    <!-- BAGIAN 1: PENILAIAN PERSYARATAN UMUM -->
                    <!-- ========================================================= -->
                    <div class="card card-primary card-outline shadow mb-4">
                        <div class="card-header bg-light d-flex align-items-center justify-content-between">
                            <h3 class="card-title font-weight-bold m-0 text-primary"><i class="fas fa-clipboard-list mr-2"></i>Bagian I: Penilaian Persyaratan Umum</h3>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped table-hover m-0 table-verif">
                                    <thead class="bg-light text-dark">
                                        <tr>
                                            <th width="5%">No.</th>
                                            <th width="65%">Deskripsi Persyaratan Umum</th>
                                            <th width="30%">Status Validasi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $no_urut_syarat = 1;
                                        foreach ($SYARAT_UMUM_CONFIG as $idx => $cfg): 
                                            $status_syarat = $_POST[$cfg['db_status']] ?? $pegawai[$cfg['db_status']] ?? 'Belum Diperiksa';
                                            if (empty($status_syarat)) { $status_syarat = 'Belum Diperiksa'; }
                                        ?>
                                            <tr>
                                                <td class="text-center font-weight-bold"><?= $no_urut_syarat++; ?>.</td>
                                                <td class="font-weight-bold text-secondary"><?= $cfg['label']; ?></td>
                                                <td class="text-center">
                                                    <select name="<?= $cfg['db_status']; ?>" class="form-control select-verif text-center mx-auto shadow-sm" style="max-width: 220px;" <?php echo $is_ppsdm ? 'disabled' : ''; ?>>
                                                        <option value="Belum Diperiksa" <?= $status_syarat == 'Belum Diperiksa' ? 'selected' : ''; ?>>➖ Belum Diperiksa</option>
                                                        <option value="Sesuai" <?= $status_syarat == 'Sesuai' ? 'selected' : ''; ?>>✅ Sesuai</option>
                                                        <option value="Tidak Sesuai" <?= $status_syarat == 'Tidak Sesuai' ? 'selected' : ''; ?>>❌ Tidak Sesuai</option>
                                                    </select>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- ========================================================= -->
                    <!-- BAGIAN 2: PENILAIAN DOKUMEN FISIK -->
                    <!-- ========================================================= -->
                    <div class="card card-indigo card-outline shadow mb-4">
                        <div class="card-header bg-light">
                            <h3 class="card-title font-weight-bold m-0 text-indigo"><i class="fas fa-folder-open mr-2"></i>Bagian II: Validitas Berkas Fisik Kenaikan</h3>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped table-hover m-0 table-verif">
                                    <thead class="bg-light text-dark">
                                        <tr>
                                            <th width="5%">No.</th>
                                            <th width="45%">Nama Berkas Dokumen Persyaratan</th>
                                            <th width="20%">Lihat Berkas</th>
                                            <th width="30%">Status Validasi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $no_urut_dok = 1;
                                        foreach ($DOKUMEN_CONFIG as $idx => $cfg): 
                                            $nama_file = $pegawai[$cfg['db_file']] ?? '';
                                            
                                            $status_file = $_POST[$cfg['db_status']] ?? $pegawai[$cfg['db_status']] ?? 'Belum Diperiksa';
                                            if (empty($status_file)) { $status_file = 'Belum Diperiksa'; }
                                            
                                            $path_file_lengkap = $BASE_URL_DOKUMEN . $nama_file;
                                        ?>
                                            <tr>
                                                <td class="text-center font-weight-bold"><?= $no_urut_dok++; ?>.</td>
                                                <td class="font-weight-bold text-secondary"><?= $cfg['label']; ?></td>
                                                <td class="text-center">
                                                    <?php if (!empty($nama_file)): ?>
                                                        <a href="<?= htmlspecialchars($path_file_lengkap); ?>" target="_blank" class="btn btn-sm btn-primary btn-block preview-btn shadow-sm" style="max-width: 150px; margin: 0 auto;">
                                                            <i class="fas fa-file-pdf mr-1"></i> Buka File
                                                        </a>
                                                    <?php else: ?>
                                                        <button type="button" class="btn btn-sm btn-danger btn-block disabled" style="max-width: 150px; margin: 0 auto;" disabled>
                                                            <i class="fas fa-exclamation-triangle mr-1"></i> Kosong
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <select name="<?= $cfg['db_status']; ?>" class="form-control select-verif text-center mx-auto shadow-sm" style="max-width: 220px;" <?php echo $is_ppsdm ? 'disabled' : ''; ?>>
                                                        <option value="Belum Diperiksa" <?= $status_file == 'Belum Diperiksa' ? 'selected' : ''; ?>>➖ Belum Diperiksa</option>
                                                        <option value="Sesuai" <?= $status_file == 'Sesuai' ? 'selected' : ''; ?>>✅ Sesuai</option>
                                                        <option value="Tidak Sesuai" <?= $status_file == 'Tidak Sesuai' ? 'selected' : ''; ?>>❌ Tidak Sesuai</option>
                                                    </select>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- ========================================================= -->
                    <!-- CATATAN & TOMBOL SUBMIT -->
                    <!-- ========================================================= -->
                    <div class="card card-dark card-outline shadow mb-5">
                        <div class="card-header bg-light">
                            <h3 class="card-title font-weight-bold text-dark"><i class="fas fa-comment-dots mr-2 text-indigo"></i>Catatan Tambahan Umum & Log Riwayat</h3>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-7">
                                    <div class="form-group">
                                        <label class="font-weight-bold text-secondary"><i class="fas fa-pen-alt mr-1"></i> Input Komentar Resmi Verifikator (Akan tampil pada akun Peserta):</label>
                                        <textarea name="catatan_evaluator" class="form-control shadow-sm" rows="5" placeholder="Tulis alasan rincian berkas/persyaratan jika status 'Tidak Sesuai' agar peserta bisa memperbaiki datanya..." <?php echo $is_ppsdm ? 'readonly' : ''; ?>><?= htmlspecialchars($_POST['catatan_evaluator'] ?? $pegawai['catatan_evaluator'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                                <div class="col-md-5">
                                    <label class="font-weight-bold text-muted"><i class="fas fa-history mr-1"></i> Log Catatan Sebelumnya / Riwayat Pengusul:</label>
                                    <div class="box-log shadow-sm">
                                        <?php if (!empty($pegawai['catatan_evaluator'])): ?>
                                            <div class="small mb-2"><strong>Catatan Verifikator Aktif:</strong></div>
                                            <p class="small text-danger font-italic bg-white p-2 border rounded">"<?= nl2br(htmlspecialchars($pegawai['catatan_evaluator'])); ?>"</p>
                                        <?php else: ?>
                                            <p class="text-muted small text-center my-4">Tidak ada riwayat catatan tertulis sebelumnya.</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <hr class="my-4">
                            
                            <div class="row justify-content-center">
                                <div class="col-md-6">
                                    <button type="submit" name="submit_verifikasi" class="btn btn-success btn-lg btn-block shadow font-weight-bold btn-submit-final" <?php echo $is_ppsdm ? 'disabled' : ''; ?>>
                                        <i class="fas fa-cloud-upload-alt mr-2"></i> Selesaikan Verifikasi & Simpan Status
                                    </button>
                                    <?php if ($is_ppsdm): ?>
                                        <small class="text-muted d-block text-center mt-2 font-italic">Tombol simpan dinonaktifkan untuk akun PPSDM.</small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>

            <?php else: ?>
                <div class="card card-danger card-outline shadow">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-database fa-4x text-danger mb-3"></i>
                        <h4 class="font-weight-bold">Data Pengajuan Tidak Ditemukan</h4>
                        <p class="text-muted">ID pengajuan tidak valid atau records sudah dihapus dari sistem internal database.</p>
                        <a href="index_kasubdit.php" class="btn btn-warning mt-2"><i class="fas fa-arrow-left mr-1"></i> Kembali ke Halaman Utama</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<?php require_once 'template/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selects = document.querySelectorAll('.select-verif');
    const res = document.getElementById('summary-result');
    
    function updateColors() {
        let ok = 0;
        let adaTidakSesuai = false;

        selects.forEach(s => {
            if(s.value === 'Sesuai') {
                s.style.backgroundColor = '#d1fae5'; 
                s.style.color = '#065f46'; 
                s.style.borderColor = '#10b981';
                ok++;
            } else if(s.value === 'Tidak Sesuai') {
                s.style.backgroundColor = '#fee2e2'; 
                s.style.color = '#991b1b'; 
                s.style.borderColor = '#ef4444';
                adaTidakSesuai = true;
            } else {
                s.style.backgroundColor = '#fff'; 
                s.style.color = '#000'; 
                s.style.borderColor = '#ccc';
            }
        });

        if (res) {
            if (adaTidakSesuai) {
                res.textContent = 'PERLU PERBAIKAN'; 
                res.className = 'status-badge bg-danger shadow-sm text-white';
            } else if (ok === selects.length) { 
                res.textContent = 'LENGKAP (MEMENUHI SYARAT)'; 
                res.className = 'status-badge bg-success shadow-sm text-white'; 
            } else { 
                res.textContent = 'BELUM SELESAI DIPERIKSA'; 
                res.className = 'status-badge bg-warning shadow-sm text-dark'; 
            }
        }
    }

    selects.forEach(s => s.addEventListener('change', updateColors));
    updateColors(); 

    <?php if ($success_redirect_trigger): ?>
        alert('🎉 Sukses! Hasil pemeriksaan persyaratan dan dokumen Kenaikan Jabatan berhasil disimpan.');
        window.location.href = 'index_verifikator.php';
    <?php endif; ?>
});
</script>