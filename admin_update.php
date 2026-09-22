<?php
// 1. Inisialisasi Session & Proteksi Halaman
session_start();

// Pastikan file koneksi.php ada
require_once 'koneksi.php'; 

// Cek Login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Cek Role (Hanya admin & super_admin)
$allowed_roles = ['user_admin', 'user_super_admin'];
if (!isset($_SESSION['user_role_sesi']) || !in_array($_SESSION['user_role_sesi'], $allowed_roles)) {
    header("Location: index.php?error=unauthorized");
    exit;
}

// =========================================================================
// LOGIKA PROSES IMPORT DATA AUTOMATIC BYPASS (LANGSUNG DARI GOOGLE FORM CSV)
// =========================================================================
if (isset($_POST['btn_import_csv'])) {
    if ($_FILES['csv_file']['size'] > 0) {
        $file_tmp = $_FILES['csv_file']['tmp_name'];
        $file_handle = fopen($file_tmp, "r");
        
        // 1. Ambil baris pertama sebagai Header untuk pemetaan dinamis
        $header = fgetcsv($file_handle, 1000, ",");
        
        // Bersihkan spasi atau karakter aneh pada header jika ada
        $header = array_map('trim', $header);

        // Cari tahu posisi indeks kolom berdasarkan nama kolom dari Google Form Anda
        $idx_nip      = array_search('NIP', $header);
        $idx_nama     = array_search('Nama Lengkap dan Gelar', $header);
        $idx_email    = array_search('Email', $header);
        $idx_instansi = array_search('Instansi', $header);

        // Validasi: Pastikan kolom utama Google Form ditemukan di dalam file CSV
        if ($idx_nip === false || $idx_nama === false || $idx_email === false || $idx_instansi === false) {
            echo "<script>
                    alert('Error: Struktur file CSV tidak cocok dengan Google Form Pendaftaran JFPKP! Harap pastikan kolom NIP, Nama Lengkap dan Gelar, Email, dan Instansi tersedia.');
                    window.location.href = 'admin_update.php';
                  </script>";
            fclose($file_handle);
            exit;
        }

        // 2. Ambil Informasi Gelombang Terakhir Secara Otomatis dari Database
        $query_gel_aktif = mysqli_query($conn, "SELECT id, gelombang FROM tb_gelombang ORDER BY id DESC LIMIT 1");
        if (mysqli_num_rows($query_gel_aktif) > 0) {
            $row_gel_aktif = mysqli_fetch_assoc($query_gel_aktif);
            $id_gelombang  = $row_gel_aktif['id'];
            $gelombang     = $row_gel_aktif['gelombang'];
        } else {
            // Jika belum ada gelombang sama sekali di database, buat fallback default
            $id_gelombang  = 1;
            $gelombang     = "Gelombang I";
        }

        $jumlah_sukses = 0;
        
        // 3. Looping membaca baris data pengusul
        while (($row = fgetcsv($file_handle, 1000, ",")) !== FALSE) {
            // Ambil data berdasarkan indeks yang sudah dipetakan di atas
            $nip               = mysqli_real_escape_string($conn, trim($row[$idx_nip] ?? ''));
            $nama              = mysqli_real_escape_string($conn, trim($row[$idx_nama] ?? ''));
            $email             = mysqli_real_escape_string($conn, trim($row[$idx_email] ?? ''));
            $instansi          = mysqli_real_escape_string($conn, trim($row[$idx_instansi] ?? ''));
            
            // Injeksi otomatis parameter internal sistem
            $status_pengajuan  = 'Terjadwal'; 

            // Skip jika data kritikal seperti NIP atau Email ternyata kosong pada baris tersebut
            if (empty($nip) || empty($email)) {
                continue;
            }

            // A. Cek atau Buat Akun Pengusul di tabel users jika belum terdaftar
            $cek_user = mysqli_query($conn, "SELECT id FROM users WHERE email = '$email' OR nip_user = '$nip'");
            if (mysqli_num_rows($cek_user) == 0) {
                $password_default = password_hash("jfpkp12345", PASSWORD_BCRYPT);
                $query_user = "INSERT INTO users (nama, email, password_hash, instansi, role, status, nip_user, is_verified, created_at) 
                               VALUES ('$nama', '$email', '$password_default', '$instansi', 'pengusul', 'aktif', '$nip', 1, NOW())";
                mysqli_query($conn, $query_user);
            }

            // B. Masukkan data pengajuan langsung terikat ke Gelombang Aktif terakhir saat ini
            $query_ujikom = "INSERT INTO pengajuan_ujikom (
                                nip, nama, email, instansi, gelombang, id_gelombang, status_pengajuan, tanggal_pengajuan, updated_at
                             ) VALUES (
                                '$nip', '$nama', '$email', '$instansi', '$gelombang', '$id_gelombang', '$status_pengajuan', NOW(), NOW()
                             )";
            
            if (mysqli_query($conn, $query_ujikom)) {
                $jumlah_sukses++;
            }
        }
        
        fclose($file_handle);
        echo "<script>
                alert('Bypass Berhasil! Sukses mengimpor $jumlah_sukses data pengusul langsung ke $gelombang tanpa edit manual.');
                window.location.href = 'admin_update.php';
              </script>";
        exit;
    }
}
// =========================================================================

// Ambil data konten utama (id=1) dari tabel admin_update
$query_data = mysqli_query($conn, "SELECT * FROM tb_admin_update WHERE id = 1 LIMIT 1");
$data = mysqli_fetch_assoc($query_data) ?: [];

// --- Hitung jumlah 'Terjadwal', 'Lulus', dan 'Tidak Lulus' dari pengajuan_ujikom per gelombang ---
$sql_gelombang = "SELECT g.*, 
                  (SELECT COUNT(*) FROM pengajuan_ujikom p WHERE p.gelombang = g.id AND p.status_pengajuan = 'Terjadwal') as total_terjadwal,
                  (SELECT COUNT(*) FROM pengajuan_ujikom p WHERE p.gelombang = g.id AND p.status_pengajuan = 'Lulus') as total_lulus,
                  (SELECT COUNT(*) FROM pengajuan_ujikom p WHERE p.gelombang = g.id AND p.status_pengajuan = 'Tidak Lulus') as total_tidak_lulus
                  FROM tb_gelombang g 
                  ORDER BY g.id DESC";
$query_gelombang = mysqli_query($conn, $sql_gelombang);

// 2. Load Header
include 'template/header.php'; 
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">

<style>
    :root {
        --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        --section-gelombang: #4e73df;
        --section-pengumuman: #6f42c1;
        --section-jadwal: #36b9cc;
        --section-berita: #1cc88a;
    }

    .static-section-header {
        width: 100%;
        text-align: left;
        padding: 18px 25px;
        border: none;
        border-radius: 15px 15px 0 0;
        background: #fdfdfd;
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-left: 5px solid var(--section-gelombang);
        margin-bottom: 0;
    }

    .btn-toggle-section {
        width: 100%;
        text-align: left;
        padding: 18px 25px;
        border: none;
        border-radius: 15px;
        background: #fff;
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        display: flex;
        justify-content: space-between;
        align-items: center;
        transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        margin-bottom: 20px;
        border-left: 5px solid #ddd;
    }

    .btn-toggle-section:hover {
        background: #fff;
        transform: translateY(-3px);
        box-shadow: 0 8px 15px rgba(0,0,0,0.1);
    }

    .section-gelombang { border-left-color: var(--section-gelombang); }
    .section-pengumuman { border-left-color: var(--section-pengumuman); }
    .section-jadwal { border-left-color: var(--section-jadwal); }
    .section-berita { border-left-color: var(--section-berita); }

    .btn-toggle-section i.arrow-icon {
        transition: transform 0.3s ease;
        background: #f8f9fa;
        padding: 8px;
        border-radius: 50%;
    }

    .btn-toggle-section[aria-expanded="true"] {
        background: #fdfdfd;
        border-bottom-left-radius: 0;
        border-bottom-right-radius: 0;
        margin-bottom: 0;
    }

    .btn-toggle-section[aria-expanded="true"] i.arrow-icon {
        transform: rotate(180deg);
        background: #eee;
    }

    .collapse-content, .static-content {
        border-radius: 0 0 15px 15px;
        overflow: hidden;
        margin-bottom: 25px;
        box-shadow: 0 10px 20px rgba(0,0,0,0.05);
    }

    .card-custom {
        border: none;
        border-radius: 0;
    }

    .table thead th {
        background-color: #f8f9fc;
        text-transform: uppercase;
        font-size: 0.8rem;
        letter-spacing: 0.05em;
        color: #5a5c69;
    }

    .btn-manage-text {
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        padding: 4px 10px;
    }

    /* --- ENHANCED SWITCH UI --- */
    .custom-switch-container {
        padding: 20px;
        border-radius: 16px;
        border: 2px solid #eaecf0;
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        position: relative;
        background: #ffffff;
    }

    .custom-switch-container.active {
        border-color: #1cc88a;
        background: linear-gradient(to right, #ffffff, #f0fff4);
        box-shadow: 0 10px 25px rgba(28, 200, 138, 0.15);
    }

    .custom-switch-container.inactive {
        border-color: #858796;
        background: linear-gradient(to right, #ffffff, #f8f9fc);
    }

    .status-indicator-icon {
        width: 45px;
        height: 45px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
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
        font-size: 1.1rem;
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

<div class="d-flex" id="wrapper" style="min-height: 100vh;">
    <aside id="sidebar-container" style="width: 250px; flex-shrink: 0; background-color: #2c3e50;">
        <?php include 'template/sidebar.php'; ?>
    </aside>

    <main id="page-content-wrapper" class="flex-grow-1" style="background-color: #f8f9fc; min-width: 0;">
        <div class="container-fluid p-4">
            
            <div class="d-flex justify-content-between align-items-center mb-4 animate__animated animate__fadeIn">
                <div>
                    <h3 class="fw-bold text-dark mb-0">Update Konten Portal</h3>
                    <p class="text-muted small">Kelola informasi publikasi dan gelombang ujikom</p>
                </div>
                <span class="badge rounded-pill bg-white text-primary px-3 py-2 shadow-sm border">
                    <i class="fas fa-user-shield me-1"></i> Role: <?php echo htmlspecialchars($_SESSION['user_role_sesi'] ?? 'Guest'); ?>
                </span>
            </div>

            <div class="static-section-header section-gelombang">
                <span class="fw-bold text-dark"><i class="fas fa-layer-group text-primary me-2"></i> Pengaturan Gelombang Pelaksanaan</span>
            </div>
            <div class="static-content mb-4" id="contentGelombang">
                <div class="card card-custom shadow-sm">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                            <h5 class="fw-bold mb-0 text-primary">Daftar Gelombang Aktif</h5>
                            
                            <div>
                                <button type="button" class="btn btn-primary fw-bold shadow-sm text-nowrap" onclick="tambahGelombangBaru()">
                                    <i class="fas fa-plus-circle me-2"></i> Tambah Gelombang
                                </button>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th class="py-3">Gelombang</th>
                                        <th class="py-3">Bulan</th>
                                        <th class="py-3">Surat Pengumuman</th>
                                        <th class="py-3 text-center">Statistik Peserta</th> 
                                        <th class="py-3 text-center">Opsi Kelola</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(mysqli_num_rows($query_gelombang) > 0): ?>
                                        <?php while($row = mysqli_fetch_assoc($query_gelombang)): ?>
                                        <tr>
                                            <td class="fw-bold text-dark"><?= htmlspecialchars($row['gelombang'] ?? '-') ?></td>
                                            <td><i class="far fa-calendar-alt me-1 text-muted"></i> <?= htmlspecialchars($row['bln_gelombang'] ?? '-') ?></td>

                                            <td>
                                                <?php if(!empty($row['surat_gelombang'])): ?>
                                                    <a href="uploads/pengumuman/<?= $row['surat_gelombang'] ?>" target="_blank" class="btn btn-sm btn-light border text-primary rounded-pill px-3 shadow-sm">
                                                        <i class="fas fa-external-link-alt me-1"></i> PDF Surat
                                                    </a>
                                                <?php else: ?>
                                                    <span class="badge bg-light text-muted border border-dashed py-2 px-3">File Kosong</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <div class="d-flex flex-column align-items-center gap-1">
                                                    <span class="badge bg-primary-subtle text-primary rounded-pill px-3 border border-primary w-100" style="max-width: 130px;">
                                                        <?= number_format($row['total_terjadwal'] ?? 0) ?> Menunggu Proses
                                                    </span>
                                                    <span class="badge bg-success-subtle text-success rounded-pill px-3 border border-success w-100" style="max-width: 130px;">
                                                        <?= number_format($row['total_lulus'] ?? 0) ?> Lulus
                                                    </span>
                                                    <span class="badge bg-danger-subtle text-danger rounded-pill px-3 border border-danger w-100" style="max-width: 130px;">
                                                        <?= number_format($row['total_tidak_lulus'] ?? 0) ?> Tidak Lulus
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <div class="d-flex justify-content-center gap-1">
                                                    <a href="list_peserta_admin.php?id_gelombang=<?= $row['id'] ?>" class="btn btn-sm btn-outline-primary btn-manage-text" title="Lihat Peserta">
                                                        <i class="fas fa-eye me-1"></i> LIHAT
                                                    </a>
                                                    <a href="list_peserta_admin.php?id_gelombang=<?= $row['id'] ?>&trigger_upload=1" class="btn btn-sm btn-success text-white btn-manage-text" title="Upload Surat Hasil Ujikom">
                                                        <i class="fas fa-file-upload me-1"></i> UPLOAD SURAT
                                                    </a>
                                                    <button type="button" class="btn btn-sm btn-outline-warning btn-manage-text" onclick='editGelombang(<?= json_encode($row) ?>)' title="Edit Gelombang">
                                                        <i class="fas fa-edit me-1"></i> EDIT
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-danger btn-manage-text" onclick="hapusGelombang(<?= $row['id'] ?>)" title="Hapus Gelombang">
                                                        <i class="fas fa-trash me-1"></i> HAPUS
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-4 text-muted">
                                                <i class="fas fa-info-circle mb-2 fa-2x opacity-50"></i><br>
                                                Belum ada data gelombang.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- ========================================================================= -->
                        <!-- FITUR BARU: PANEL AUTOMATIC BYPASS IMPORT CSV DIRECT GOOGLE FORM -->
                        <!-- ========================================================================= -->
                        <div class="card border-0 bg-light rounded-3 mt-4 shadow-sm">
                            <div class="card-body p-4">
                                <h6 class="fw-bold text-dark mb-3"><i class="fas fa-file-csv text-success me-2"></i> Import Mentah File Google Form (.CSV)</h6>
                                <div class="alert alert-success border-0 small text-dark mb-3" style="background-color: #eafaf1; border-left: 4px solid #2ecc71 !important;">
                                    <strong>Sistem Otomatis Aktif:</strong> Anda bisa langsung mengunggah file hasil ekspor CSV asli Google Form tanpa perlu mengedit urutan kolom di Excel lagi.
                                    Sistem akan otomatis mendeteksi kolom <code class="text-primary fw-bold">NIP</code>, <code class="text-primary fw-bold">Nama Lengkap dan Gelar</code>, <code class="text-primary fw-bold">Email</code>, serta <code class="text-primary fw-bold">Instansi</code>, lalu langsung memasukkannya ke daftar <strong>Gelombang Aktif Terakhir</strong> dengan status pendaftaran <strong>Terjadwal</strong>.
                                </div>
                                <form action="" method="POST" enctype="multipart/form-data" class="row g-3 align-items-center">
                                    <div class="col-md-8">
                                        <input type="file" name="csv_file" class="form-control bg-white" accept=".csv" required>
                                    </div>
                                    <div class="col-md-4">
                                        <button type="submit" name="btn_import_csv" class="btn btn-success fw-bold w-100 shadow-sm">
                                            <i class="fas fa-bolt me-2"></i> Jalankan Auto-Bypass
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <!-- ========================================================================= -->

                    </div>
                </div>
            </div>

            <div class="container-fluid py-4">
                <main>
                    <div class="container-light">
                        <form id="formUpdateUtama" action="proses_update.php" method="POST" enctype="multipart/form-data">
                            <div class="row">
                                <div class="col-lg-6 mb-2">
                                    <button class="btn-toggle-section section-pengumuman" type="button" data-bs-toggle="collapse" data-bs-target="#collapsePengumuman">
                                        <span class="fw-bold text-dark"><i class="fas fa-bullhorn text-purple me-2" style="color:#6f42c1"></i> Update Pengumuman Utama</span>
                                        <i class="fas fa-chevron-down arrow-icon text-muted"></i>
                                    </button>
                                    <div class="collapse collapse-content mb-4" id="collapsePengumuman">
                                        <div class="card card-custom shadow-sm border-0">
                                            <div class="card-body p-4">
                                                <div class="mb-3">
                                                    <label class="form-label small fw-bold text-muted uppercase">Judul Pengumuman Berjalan</label>
                                                    <input type="text" name="judul" class="form-control form-control-lg bg-light border-0" value="<?= htmlspecialchars($data['judul_pengumuman'] ?? '') ?>" placeholder="Masukkan headline pengumuman..." required>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-lg-6 mb-2">
                                    <button class="btn-toggle-section section-jadwal" type="button" data-bs-toggle="collapse" data-bs-target="#collapseJadwal">
                                        <span class="fw-bold text-dark"><i class="fas fa-calendar-check text-info me-2"></i> Update Periode Uji Kompetensi</span>
                                        <i class="fas fa-chevron-down arrow-icon text-muted"></i>
                                    </button>
                                    <div class="collapse collapse-content mb-4" id="collapseJadwal">
                                        <div class="card card-custom shadow-sm border-0">
                                            <div class="card-body p-4">
                                                
                                               <div id="switchBox" class="custom-switch-container d-flex align-items-center mb-4 <?= ($data['status_form_pj'] ?? 0) == 1 ? 'active' : 'inactive' ?>" onclick="toggleSwitch()">
                
                                                    <div class="status-indicator-icon me-3">
                                                        <i id="statusIcon" class="fas <?= ($data['status_form_pj'] ?? 0) == 1 ? 'fa-lock-open' : 'fa-lock' ?>"></i>
                                                    </div>

                                                    <div class="form-check form-switch m-0 p-0 me-3 d-flex align-items-center">
                                                        <input class="form-check-input custom-switch-input" type="checkbox" name="status_pj" value="1" id="switchPJ" 
                                                            <?= ($data['status_form_pj'] ?? 0) == 1 ? 'checked' : '' ?> 
                                                            onchange="updateSwitchUI(this)" 
                                                            onclick="event.stopPropagation();">
                                                    </div>
                                                    
                                                    <div class="flex-grow-1">
                                                        <p class="text-muted mb-0" style="font-size: 0.65rem; font-weight: 800; letter-spacing: 0.5px;">STATUS PENDAFTARAN</p>
                                                        <h5 id="statusLabelText" class="status-text-main fw-bold mb-0 <?= ($data['status_form_pj'] ?? 0) == 1 ? 'text-success' : 'text-secondary' ?>" style="font-size: 1rem;">
                                                            <?= ($data['status_form_pj'] ?? 0) == 1 ? 'DIBUKA' : 'DITUTUP' ?>
                                                        </h5>
                                                    </div>
                                                </div>

                                                <div class="mb-3 p-3 bg-light rounded-3">
                                                    <label class="form-label small fw-bold text-info"><i class="fas fa-level-up-alt me-1"></i> Kenaikan Jenjang (Ganjil)</label>
                                                    <div class="row g-2">
                                                        <div class="col-6"><input type="text" name="kj_daftar" class="form-control border-0" placeholder="Tgl Daftar" value="<?= htmlspecialchars($data['kj_tgl_daftar'] ?? '') ?>"></div>
                                                        <div class="col-6"><input type="text" name="kj_ujian" class="form-control border-0" placeholder="Tgl Ujian" value="<?= htmlspecialchars($data['kj_tgl_ujian'] ?? '') ?>"></div>
                                                    </div>
                                                </div>
                                                <div class="mb-3 p-3 bg-light rounded-3">
                                                    <label class="form-label small fw-bold text-info"><i class="fas fa-exchange-alt me-1"></i> Perpindahan Jabatan</label>
                                                    <div class="row g-2">
                                                        <div class="col-6"><input type="text" name="pj_daftar" class="form-control border-0" placeholder="Tgl Daftar" value="<?= htmlspecialchars($data['pj_tgl_daftar'] ?? '') ?>"></div>
                                                        <div class="col-6"><input type="text" name="pj_ujian" class="form-control border-0" placeholder="Tgl Ujian" value="<?= htmlspecialchars($data['pj_tgl_ujian'] ?? '') ?>"></div>
                                                    </div>
                                                </div>

                                                <div class="mb-0 p-3 bg-light rounded-3 border border-info border-opacity-10">
                                                    <label class="form-label small fw-bold text-primary"><i class="fas fa-file-pdf me-1"></i> Upload Surat Pengumuman</label>
                                                    <input type="file" name="file_pengumuman" class="form-control border-0 bg-white shadow-sm" accept=".pdf,.doc,.docx">
                                                    <?php if(!empty($data['file_pengumuman'])): ?>
                                                        <div class="mt-2">
                                                            <small class="text-muted">File saat ini: <a href="uploads/pengumuman/<?= $data['file_pengumuman'] ?>" target="_blank" class="text-decoration-none"><i class="fas fa-external-link-alt ms-1"></i> <?= $data['file_pengumuman'] ?></a></small>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div class="form-text mt-1" style="font-size: 0.7rem;">Format: PDF/DOCX (Maks 2MB)</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <button class="btn-toggle-section section-berita" type="button" data-bs-toggle="collapse" data-bs-target="#collapseBerita">
                                <span class="fw-bold text-dark"><i class="fas fa-newspaper text-success me-2"></i>Update Etalase Berita & Informasi</span>
                                <i class="fas fa-chevron-down arrow-icon text-muted"></i>
                            </button>
                            <div class="collapse collapse-content mb-4" id="collapseBerita">
                                <div class="d-flex justify-content-end mb-3 px-1">
                                    <button type="button" class="btn btn-sm btn-success fw-bold rounded-pill shadow-sm px-4" onclick="tambahBerita()">
                                        <i class="fas fa-plus-circle me-1"></i> Tambah Baris Berita
                                    </button>
                                </div>

                                <div id="container-berita">
                                    <div class="card border-0 shadow-sm rounded-3 mb-3 item-berita">
                                        <div class="card-body p-4">
                                            <div class="row">
                                                <div class="col-md-3 mb-3 text-center border-end">
                                                    <div class="bg-light p-3 rounded-3 mb-2" style="min-height: 100px; display: flex; align-items: center; justify-content: center;">
                                                        <i class="fas fa-image fa-3x text-muted opacity-25"></i>
                                                    </div>
                                                    <label class="form-label small fw-bold text-muted">Cover Berita</label>
                                                    <input type="file" name="cover[]" class="form-control form-control-sm border-0 bg-light" accept="image/*">
                                                </div>
                                                <div class="col-md-9">
                                                    <div class="row">
                                                        <div class="col-md-4 mb-3">
                                                            <label class="form-label small fw-bold text-muted">Kategori Tag</label>
                                                            <select name="kategori[]" class="form-select bg-light border-0 fw-bold text-success">
                                                                <?php 
                                                                $options = ['PENGUMUMAN', 'SOSIALISASI', 'KEBIJAKAN'];
                                                                foreach($options as $opt) {
                                                                    $selected = (($data['berita_kategori'] ?? '') == $opt) ? 'selected' : '';
                                                                    echo "<option value='$opt' $selected>$opt</option>";
                                                                }
                                                                ?>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-8 mb-3">
                                                            <label class="form-label small fw-bold text-muted">Headline Berita</label>
                                                            <input type="text" name="judul_berita[]" class="form-control bg-light border-0 fw-bold" value="<?= htmlspecialchars($data['berita_judul'] ?? '') ?>">
                                                        </div>
                                                        <div class="col-12">
                                                            <label class="form-label small fw-bold text-muted">Isi Ringkasan Berita</label>
                                                            <textarea name="ringkasan[]" class="form-control bg-light border-0" rows="3" placeholder="Tulis ringkasan singkat informasi..."><?= htmlspecialchars($data['berita_isi'] ?? '') ?></textarea>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card border-0 shadow-sm rounded-4 mb-5 overflow-hidden">
                                <div class="card-body p-4 text-center bg-white border-top border-4 border-primary">
                                    <input type="hidden" name="update_all" value="1">
                                    <button type="submit" class="btn btn-primary px-5 py-3 fw-bold shadow-lg rounded-pill">
                                        <i class="fas fa-cloud-upload-alt me-2"></i> PUBLIKASIKAN PERUBAHAN PORTAL
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </main>
            </div>

            <!-- MODAL TAMBAH/EDIT GELOMBANG -->
            <div class="modal fade" id="modalGelombang" tabindex="-1" data-bs-backdrop="static">
                <div class="modal-dialog modal-dialog-centered">
                    <form action="proses_update.php" method="POST" enctype="multipart/form-data">
                        <div class="modal-content border-0 shadow-lg rounded-4">
                            <div class="modal-header bg-dark text-white p-4">
                                <h5 class="modal-title fw-bold" id="modalGelombangLabel">Data Gelombang</h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body p-4">
                                <input type="hidden" name="gelombang_id" id="gelombang">
                                <input type="hidden" name="aksi_gelombang" id="aksi_gelombang" value="tambah">
                                <div class="mb-3">
                                    <label class="form-label fw-bold small text-muted">NAMA GELOMBANG</label>
                                    <input type="text" name="gelombang" id="input_gelombang" class="form-control form-control-lg bg-light border-0" placeholder="Contoh: Gelombang I" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold small text-muted">BULAN & TAHUN</label>
                                    <input type="text" name="bln_gelombang" id="input_bulan" class="form-control form-control-lg bg-light border-0" placeholder="Contoh: Maret 2026" required>
                                </div>

                                <div class="mb-0">
                                    <label class="form-label fw-bold small text-muted">DOKUMEN PENGUMUMAN (PDF)</label>
                                    <input type="file" name="surat_gelombang" class="form-control border-0 bg-light" accept=".pdf">
                                    <div id="file_aktif_info" class="mt-2"></div>
                                </div>
                            </div>
                            <div class="modal-footer border-0 p-4 pt-0">
                                <button type="submit" class="btn btn-primary btn-lg fw-bold w-100 rounded-3">
                                    <i class="fas fa-save me-2"></i> Simpan Data Gelombang
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
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

function toggleSwitch() {
    const checkbox = document.getElementById('switchPJ');
    checkbox.checked = !checkbox.checked;
    updateSwitchUI(checkbox);
}

function tambahGelombangBaru() {
    document.getElementById('modalGelombangLabel').innerText = 'Tambah Gelombang Baru';
    document.getElementById('aksi_gelombang').value = 'tambah';
    document.getElementById('gelombang').value = '';
    document.getElementById('input_gelombang').value = '';
    document.getElementById('input_bulan').value = '';
    document.getElementById('file_aktif_info').innerHTML = '';
    new bootstrap.Modal(document.getElementById('modalGelombang')).show();
}

function editGelombang(data) {
    document.getElementById('modalGelombangLabel').innerText = 'Edit Data Gelombang';
    document.getElementById('aksi_gelombang').value = 'edit';
    document.getElementById('gelombang').value = data.id;
    document.getElementById('input_gelombang').value = data.gelombang;
    document.getElementById('input_bulan').value = data.bln_gelombang;
    document.getElementById('file_aktif_info').innerHTML = data.surat_gelombang ? `<div class="alert alert-info py-2 small"><i class="fas fa-file-pdf me-2"></i>File: ${data.surat_gelombang}</div>` : '';
    new bootstrap.Modal(document.getElementById('modalGelombang')).show();
}

function hapusGelombang(id) {
    Swal.fire({
        title: 'Hapus Gelombang?',
        text: "Peserta yang terhubung mungkin akan terdampak!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e74a3b',
        cancelButtonColor: '#858796',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `proses_update.php?delete_gelombang=${id}`;
        }
    });
}

function tambahBerita() {
    const container = document.getElementById('container-berita');
    const div = document.createElement('div');
    div.className = 'card border-0 shadow-sm rounded-3 mb-3 item-berita animate__animated animate__fadeInUp';
    div.innerHTML = `
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold text-success mb-0"><i class="fas fa-plus-circle me-2"></i> Berita Tambahan</h6>
                <button type="button" class="btn btn-sm btn-outline-danger border-0" onclick="this.closest('.item-berita').remove()">
                    <i class="fas fa-trash-alt"></i> Hapus
                </button>
            </div>
            <div class="row">
                <div class="col-md-3 mb-3 text-center border-end">
                    <div class="bg-light p-3 rounded-3 mb-2" style="min-height: 100px; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-image fa-3x text-muted opacity-25"></i>
                    </div>
                    <input type="file" name="cover[]" class="form-control form-control-sm border-0 bg-light" accept="image/*">
                </div>
                <div class="col-md-9">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label small fw-bold text-muted">Kategori Tag</label>
                            <select name="kategori[]" class="form-select bg-light border-0">
                                <option value="PENGUMUMAN">PENGUMUMAN</option>
                                <option value="SOSIALISASI">SOSIALISASI</option>
                                <option value="KEBIJAKAN">KEBIJAKAN</option>
                            </select>
                        </div>
                        <div class="col-md-8 mb-3">
                            <label class="form-label small fw-bold text-muted">Headline Berita</label>
                            <input type="text" name="judul_berita[]" class="form-control bg-light border-0" placeholder="Judul berita baru..." required>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold text-muted">Isi Ringkasan Berita</label>
                            <textarea name="ringkasan[]" class="form-control bg-light border-0" rows="3" placeholder="Detail ringkasan informasi..."></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    container.appendChild(div);
}
</script>

<?php if (isset($_GET['status']) && $_GET['status'] == 'success'): ?>
<script>
    Swal.fire({
        title: 'Pembaruan Berhasil!',
        text: 'Konten portal Anda telah diperbarui secara sistem.',
        icon: 'success',
        confirmButtonColor: '#4e73df'
    }).then(() => { window.location.href = 'admin_update.php'; });
</script>
<?php endif; ?>

<?php include 'template/footer.php'; ?>