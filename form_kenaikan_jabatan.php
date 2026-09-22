<?php
// form_kenaikan_jabatan.php - Formulir Pendaftaran Uji Kompetensi (Multi-Step)

// --- ASUMSI FILE PENDUKUNG ---
require_once 'auth_guard.php'; // Ganti jika Anda tidak pakai
require_once 'koneksi.php';    // Koneksi database
require_once 'role_definitions.php'; // FILE DITAMBAHKAN SESUAI PERMINTAAN

// Cek koneksi untuk mencegah error fatal
if (!isset($conn) || !$conn) {
    die("Fatal Error: Koneksi database tidak tersedia. Mohon cek koneksi.php");
}

// --- LOGIKA ENDPOINT AJAX UNTUK DATA WILAYAH ---
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    // Fetch Daftar Provinsi
    if ($_GET['action'] === 'get_provinsi') {
        $sql = "SELECT DISTINCT provinsi FROM data_wilayah ORDER BY provinsi ASC";
        $result = $conn->query($sql);
        $provinsi_list = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $provinsi_list[] = $row['provinsi'];
            }
        }
        echo json_encode($provinsi_list);
        exit();
    }
    
    // Fetch Daftar Kabupaten/Kota berdasarkan Provinsi
    if ($_GET['action'] === 'get_kabupaten' && isset($_GET['provinsi'])) {
        $prov = $conn->real_escape_string($_GET['provinsi']);
        $sql = "SELECT id, jenis, nama_wilayah FROM data_wilayah WHERE provinsi = '$prov' ORDER BY jenis ASC, nama_wilayah ASC";
        $result = $conn->query($sql);
        $kabupaten_list = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $kabupaten_list[] = [
                    'id' => $row['id'],
                    'nama' => $row['jenis'] . ' ' . $row['nama_wilayah']
                ];
            }
        }
        echo json_encode($kabupaten_list);
        exit();
    }
}

// --- Kontrol Akses Logika ---
if (!defined('ROLE_ADMIN')) define('ROLE_ADMIN', 'admin'); 
if (!defined('ROLE_USER_ADMIN')) define('ROLE_USER_ADMIN', 'user_admin'); 

if (!function_exists('check_role')) {
    function check_role(array $allowed_roles) {
        global $user_role_sesi; 
        return in_array($user_role_sesi, $allowed_roles);
    }
}
// Tambahkan role yang diizinkan untuk mengisi form di sini
$allowed_submit_roles = [ROLE_ADMIN, ROLE_USER_ADMIN, 'user_biasa', 'user_pengusul'];

// Pastikan session_start() sudah berjalan di auth_guard.php
$user_role_sesi = $_SESSION['user_role'] ?? 'user_biasa'; 
$user_nip_sesi = $_SESSION['user_nip'] ?? $_SESSION['nip'] ?? ''; 
$user_nama_sesi = $_SESSION['user_name'] ?? $_SESSION['nama'] ?? ''; 
$user_email_sesi = $_SESSION['user_email'] ?? $_SESSION['email'] ?? '';

// Variabel ini TRUE jika role saat ini TIDAK diizinkan untuk SUBMIT
$is_form_disabled = !check_role($allowed_submit_roles); 

// Siapkan atribut disabled/readonly untuk HTML umum
$disabled_attr = $is_form_disabled ? 'disabled' : '';
$readonly_attr = $is_form_disabled ? 'readonly' : '';

// --- LOGIKA KHUSUS NIP ---
if ($user_role_sesi == ROLE_ADMIN) {
    $nip_control_attr = ''; 
    $nip_title_attr = 'title="Anda adalah Admin: NIP dapat diubah untuk keperluan pengujian aplikasi."';
    $is_nip_locked = false;
} else {
    $nip_control_attr = 'readonly';
    $nip_title_attr = 'title="NIP tidak dapat diubah karena diambil dari data akun Anda."';
    $is_nip_locked = true;
}

// --- VARIABEL UNTUK HIGHLIGHT MENU SIDEBAR ---
$page = 'ujikom'; 
$sub_page = 'form_kenaikan';      
$page_title = 'Pengajuan Uji Kompetensi | Instansi Pembina JF'; 

// Path relatif direktori upload
$TARGET_DIR = "uploads/kenaikan/"; 
$NAMA_TABEL_UJIKOM = "pengajuan_ujikom"; 
$MAX_FILE_SIZE = 5000000; // 5 MB

$success_message = '';
$error_message = '';

// Array map disamakan dengan input file di HTML Langkah 3
$file_map = [
    'file_usulan_ukom' => 'file_surat_usulan_uji',
    'file_sk_jafung'   => 'file_sk_jabatan', 
    'file_pangkat'     => 'file_sk_pangkat',
    'file_pak'         => 'file_pak', 
    'file_skp'         => 'file_skp',
];

// Deskripsi file untuk notifikasi jika terjadi error upload
$file_descriptions = [
    'file_usulan_ukom' => 'Surat Usulan Uji Kompetensi',
    'file_sk_jafung'   => 'Salinan SK Jabatan Fungsional',
    'file_pangkat'     => 'Salinan SK Pangkat/Golongan Terakhir',
    'file_pak'         => 'Salinan Penetapan Angka Kredit (PAK) Terakhir',
    'file_skp'         => 'Salinan SKP 2 Tahun Terakhir',
];

// >>> LOGIKA PEMROSESAN FORM SUBMISSION <<<
if (!$is_form_disabled && $_SERVER["REQUEST_METHOD"] == "POST") {
    
    // 1. Sanitize input data
    $nama = $conn->real_escape_string($_POST['nama'] ?? '');
    $nip_from_form = $_POST['nip'] ?? $user_nip_sesi;
    $nip = $conn->real_escape_string($nip_from_form); 
    
    $jabatan = $conn->real_escape_string($_POST['jabatan'] ?? '');
    $pangkat = $conn->real_escape_string($_POST['pangkat'] ?? '');
    $tmt = $conn->real_escape_string($_POST['tmt'] ?? '');
    $jenjang = $conn->real_escape_string($_POST['jenjang'] ?? '');
    $prodi = $conn->real_escape_string($_POST['prodi'] ?? ''); 
    $email = $conn->real_escape_string($_POST['email'] ?? '');
    $hp = $conn->real_escape_string($_POST['hp'] ?? '');
    $instansi = $conn->real_escape_string($_POST['instansi'] ?? '');
    
    $provinsi_pemda = $conn->real_escape_string($_POST['provinsi_pemda'] ?? '');
    $kabupaten_pemda = $conn->real_escape_string($_POST['kabupaten_pemda'] ?? '');
    
    $unit_organisasi = $conn->real_escape_string($_POST['unit_organisasi'] ?? '');
    $unit_saat_ini = $conn->real_escape_string($_POST['unit_saat_ini'] ?? '');
    $unit_sebelumnya = $conn->real_escape_string($_POST['unit_sebelumnya'] ?? '');
    $jenis_pengajuan = 'Kenaikan Jabatan'; 

    if (empty($nip)) {
         $error_message .= "❌ NIP wajib diisi.<br>";
         $upload_ok = false;
    } else {
         $upload_ok = true;
    }

    // --- CEK DUPLIKASI NIP DILAKUKAN SEBELUM PROSES UPLOAD ---
    if ($upload_ok) {
        $stmt_check = $conn->prepare("SELECT id FROM {$NAMA_TABEL_UJIKOM} WHERE nip = ? AND jenis_pengajuan = ?");
        $stmt_check->bind_param("ss", $nip, $jenis_pengajuan);
        $stmt_check->execute();
        $res_check = $stmt_check->get_result();

        if ($res_check->num_rows > 0) {
            $error_message .= "❌ **Gagal Mendaftar:** NIP <strong>{$nip}</strong> sudah terdaftar dalam usulan {$jenis_pengajuan}!<br>";
            $upload_ok = false;
        }
        $stmt_check->close();
    }
    
    $file_paths = [];

    // 2. Proses Upload File
    if ($upload_ok && !is_dir($TARGET_DIR)) {
        if (!mkdir($TARGET_DIR, 0755, true)) {
            $error_message .= "Gagal membuat folder upload ({$TARGET_DIR}). Periksa izin folder server Anda.<br>";
            $upload_ok = false;
        }
    }

    if ($upload_ok) {
        foreach ($file_map as $form_field => $db_column) {
            if (isset($_FILES[$form_field]) && $_FILES[$form_field]['error'] == 0) {
                
                $file_temp = $_FILES[$form_field]['tmp_name'];
                $file_name_original = basename($_FILES[$form_field]['name']);
                $file_ext = strtolower(pathinfo($file_name_original, PATHINFO_EXTENSION));
                
                $prefix = str_replace(['file_', '_'], ['','-'], $form_field); 
                $clean_nama = preg_replace('/[^A-Za-z0-9]/', '', str_replace(' ', '-', $nama)); 
                $new_file_name = "{$prefix}_{$nip}_{$clean_nama}_" . time() . ".{$file_ext}";
                $target_file = $TARGET_DIR . $new_file_name;

                if ($file_ext != "pdf") {
                    $error_message .= "File **{$file_descriptions[$form_field]}** harus berformat PDF.<br>";
                    $upload_ok = false;
                    break;
                }
                if ($_FILES[$form_field]['size'] > $MAX_FILE_SIZE) { 
                    $error_message .= "File **{$file_descriptions[$form_field]}** terlalu besar. Maksimal 5MB.<br>";
                    $upload_ok = false;
                    break;
                }
                if (move_uploaded_file($file_temp, $target_file)) {
                    $file_paths[$db_column] = $conn->real_escape_string($new_file_name); 
                } else {
                    $error_message .= "Gagal mengunggah file **{$file_descriptions[$form_field]}** ke folder server.<br>";
                    $upload_ok = false;
                    break;
                }
            } else if (isset($_FILES[$form_field]) && $_FILES[$form_field]['error'] == 4) {
                 $error_message .= "File **{$file_descriptions[$form_field]}** wajib diunggah.<br>";
                 $upload_ok = false;
                 break;
            } else {
                $error_message .= "Terjadi error saat upload file **{$file_descriptions[$form_field]}**.<br>";
                $upload_ok = false;
                break;
            }
        }
    }
    
    // 3. Masukkan data ke database jika semua file tervalidasi dengan benar
    if ($upload_ok && empty($error_message) && count($file_paths) == count($file_map)) {
        
        $columns = "nip, nama, email, hp, jabatan, pangkat, tmt_pangkat, jenjang_pendidikan, program_studi, instansi, unit_organisasi, unit_saat_ini, unit_sebelumnya, jenis_pengajuan, tanggal_pengajuan, status_pengajuan";
        
        // Pengolahan string unit organisasi untuk Pemerintah Daerah
        if ($instansi == 'Pemerintah Daerah') {
            $unit_organisasi_value = 'Pemda Prov. ' . $provinsi_pemda;
            if (!empty($kabupaten_pemda)) {
                $unit_organisasi_value .= ' - ' . $kabupaten_pemda;
            }
        } else {
            $unit_organisasi_value = $unit_organisasi;
        }

        $values = "'$nip', '$nama', '$email', '$hp', '$jabatan', '$pangkat', '$tmt', '$jenjang', '$prodi', '$instansi', '$unit_organisasi_value', '$unit_saat_ini', '$unit_sebelumnya', '$jenis_pengajuan', NOW(), 'Menunggu Verifikasi'"; 

        foreach ($file_paths as $column => $filename) {
            $columns .= ", " . $column;
            $values .= ", '" . $filename . "'";
        }
        
        $sql = "INSERT INTO {$NAMA_TABEL_UJIKOM} ($columns) VALUES ($values)";
        
        if ($conn->query($sql) === TRUE) {
            header("Location: form_kenaikan_jabatan.php?status=success");
            exit(); 
        } else {
            $error_message = "❌ Gagal menyimpan data ke database: " . $conn->error;
            // Rollback file jika simpan database gagal
            foreach ($file_paths as $path) { 
                $full_path = $TARGET_DIR . $path; 
                if (file_exists($full_path)) unlink($full_path); 
            }
        }
    } else {
        // Rollback file jika validasi di tengah jalan gagal
        foreach ($file_paths as $path) { 
            $full_path = $TARGET_DIR . $path; 
            if (file_exists($full_path)) unlink($full_path); 
        }
    }
}

if (isset($_GET['status']) && $_GET['status'] == 'success') {
    $success_message = "✅ Pendaftaran Uji Kompetensi **berhasil dikirim**! Silakan tunggu proses verifikasi dokumen.";
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

    <style>
        .step-header { background-color: #f8f9fa; border-bottom: 2px solid #ced4da; padding: 15px 20px; font-size: 1.1rem; font-weight: 600; margin-bottom: 15px; color: #495057; }
        .step-box { display: none; animation: fadeIn 0.5s; }
        .step-box.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .form-control, .input-text { border: 1px solid #ced4da !important; border-radius: .25rem; padding: .375rem .75rem; width: 100%; }
        .form-group label { font-weight: 600; }
        .message {padding: 15px; margin-bottom: 20px; border-radius: 8px; font-weight: bold;}
        .success {background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb;}
        .error {background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;}
        .warning {background-color: #fff3cd; color: #856404; border: 1px solid #ffeeba;}
        .upload-grid { width:100%; border-collapse: collapse; margin-top:12px; }
        .upload-grid thead th { background-color: #007bff; color: white; padding: 10px; }
        .upload-grid tr:nth-child(even) { background-color: #f8f9fa; }
        .upload-grid td { padding:12px; vertical-align:middle; border-bottom:1px solid #dee2e6; }
        .upload-label { font-weight:600; color:#343a40; width:35%; }
        .upload-hint { color:#28a745; font-size:13px; margin-left:8px; display: block; margin-top: 5px; }
        .progress-indicator { display: flex; margin-bottom: 30px; justify-content: space-around;}
        .step-dot { width: 30px; height: 30px; border-radius: 50%; background-color: #ced4da; color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; transition: background-color 0.3s, transform 0.3s; cursor: pointer; position: relative;}
        .step-dot.active { background-color: #007bff; transform: scale(1.1); }
        .step-dot.done { background-color: #28a745; }
        .step-dot:after { content: attr(data-label); position: absolute; top: 100%; margin-top: 10px; font-size: 12px; color: #6c757d; font-weight: normal; width: 100px; text-align: center; }
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
                        <h1 class="m-0"><i class="fas fa-file-upload"></i> Pendaftaran Uji Kompetensi</h1>
                    </div>
                </div>
            </div>
        </div>
        <section class="content">
            <div class="container-fluid">

                <?php if ($success_message): ?>
                <div class="message success">
                    <?php echo $success_message; ?>
                    <p class="mt-2"><a href="index.php" class="btn btn-sm btn-success">Kembali ke Dashboard</a></p>
                </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                <div class="message error"><?php echo $error_message; ?></div>
                <?php endif; ?>
                
                <?php if ($is_form_disabled): ?>
                <div class="message warning">
                    <i class="fas fa-exclamation-triangle"></i> **PERINGATAN AKSES DIBATASI!**
                    <p>Peran Anda saat ini (**<?php echo htmlspecialchars(strtoupper($user_role_sesi)); ?>**) hanya diizinkan untuk melihat tampilan formulir ini.</p>
                </div>
                <?php endif; ?>

                <?php if (empty($success_message)): ?>
                <div class="card card-primary card-outline">
                    <div class="card-body">
                        
                        <div class="progress-indicator">
                            <div class="step-dot active" data-step="1" data-label="Data Pribadi">1</div>
                            <div class="step-dot" data-step="2" data-label="Data Instansi">2</div>
                            <div class="step-dot" data-step="3" data-label="Unggah Dokumen">3</div>
                        </div>

                        <form id="ujiform" method="POST" enctype="multipart/form-data" <?php echo $is_form_disabled ? 'onsubmit="return false;"' : 'novalidate'; ?>>
                            
                            <div class="step-box active" id="step1">
                                <div class="step-header"><i class="fas fa-user-circle"></i> Langkah 1: Biodata Peserta</div>
                                <div class="row">
                                    <div class="col-md-6 form-group">
                                        <label>Nama Lengkap dan Gelar <span class="text-danger">*</span></label>
                                        <input type="text" name="nama" class="form-control" required value="<?php echo htmlspecialchars($_POST['nama'] ?? $user_nama_sesi); ?>" <?php echo $readonly_attr; ?>>
                                    </div>
                                    <div class="col-md-6 form-group">
                                        <label>NIP <span class="text-danger">*</span></label>
                                        <input type="text" name="nip" class="form-control" required value="<?php echo htmlspecialchars($_POST['nip'] ?? $user_nip_sesi); ?>" <?php echo $nip_control_attr; ?> <?php echo $nip_title_attr; ?> >
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 form-group">
                                        <label>Jabatan Saat Ini <span class="text-danger">*</span></label>
                                        <input type="text" name="jabatan" class="form-control" required value="<?php echo htmlspecialchars($_POST['jabatan'] ?? ''); ?>" <?php echo $readonly_attr; ?>>
                                    </div>
                                    <div class="col-md-6 form-group">
                                        <label>Pangkat/Golongan <span class="text-danger">*</span></label>
                                        <input type="text" name="pangkat" class="form-control" required value="<?php echo htmlspecialchars($_POST['pangkat'] ?? ''); ?>" <?php echo $readonly_attr; ?>>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 form-group">
                                        <label>TMT Pangkat/Golongan Terakhir <span class="text-danger">*</span></label>
                                        <input type="date" name="tmt" class="form-control" required value="<?php echo htmlspecialchars($_POST['tmt'] ?? ''); ?>" <?php echo $readonly_attr; ?>>
                                    </div>
                                    <div class="col-md-6 form-group">
                                        <label>Jenjang Pendidikan Terakhir <span class="text-danger">*</span></label>
                                        <select name="jenjang" class="form-control" required <?php echo $disabled_attr; ?>>
                                            <option value="">-- Pilih Jenjang --</option>
                                            <?php 
                                            $jenjang_options = ['S1 / D4', 'S2', 'S3', 'Lainnya'];
                                            $selected_jenjang = $_POST['jenjang'] ?? '';
                                            foreach($jenjang_options as $opt): ?>
                                                <option value="<?php echo $opt; ?>" <?php echo ($selected_jenjang == $opt) ? 'selected' : ''; ?>><?php echo $opt; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 form-group">
                                        <label>Program Studi <span class="text-danger">*</span></label>
                                        <input type="text" name="prodi" class="form-control" required value="<?php echo htmlspecialchars($_POST['prodi'] ?? ''); ?>" <?php echo $readonly_attr; ?>>
                                    </div>
                                    <div class="col-md-6 form-group">
                                        <label>Email <span class="text-danger">*</span></label>
                                        <input type="email" name="email" class="form-control" required value="<?php echo htmlspecialchars($_POST['email'] ?? $user_email_sesi); ?>" <?php echo $readonly_attr; ?>>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 form-group">
                                        <label>No. Handphone (WA) <span class="text-danger">*</span></label>
                                        <input type="text" name="hp" class="form-control" required value="<?php echo htmlspecialchars($_POST['hp'] ?? ''); ?>" <?php echo $readonly_attr; ?>>
                                    </div>
                                </div>
                                <div class="actions text-right">
                                    <button type="button" class="btn btn-primary next-step" data-next="2" <?php echo $disabled_attr; ?>>Lanjut <i class="fas fa-arrow-right"></i></button>
                                </div>
                            </div>
                            
                            <div class="step-box" id="step2">
                                <div class="step-header"><i class="fas fa-building"></i> Langkah 2: Data Instansi</div>
                                <div class="row">
                                    <div class="col-md-6 form-group">
                                        <label>Instansi <span class="text-danger">*</span></label>
                                        <select name="instansi" id="instansi" class="form-control" required <?php echo $disabled_attr; ?>>
                                            <option value="">-- Pilih Instansi --</option>
                                            <option value="Kementerian PKP" <?php echo (($_POST['instansi'] ?? '') == 'Kementerian PKP') ? 'selected' : ''; ?>>Kementerian PKP</option>
                                            <option value="Kementerian PU" <?php echo (($_POST['instansi'] ?? '') == 'Kementerian PU') ? 'selected' : ''; ?>>Kementerian PU</option>
                                            <option value="Pemerintah Daerah" <?php echo (($_POST['instansi'] ?? '') == 'Pemerintah Daerah') ? 'selected' : ''; ?>>Pemerintah Daerah</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 form-group" id="container_unit_organisasi">
                                        <label>Unit Organisasi Eselon I/Utama (K/L) <span class="text-danger required-star">*</span></label>
                                        <input type="text" name="unit_organisasi" id="unit_organisasi" class="form-control" placeholder="Diisi Jika Instansi Pusat..." value="<?php echo htmlspecialchars($_POST['unit_organisasi'] ?? ''); ?>" <?php echo $readonly_attr; ?>>
                                    </div>
                                </div>

                                <!-- ELEMENT BARU: CONTAINER PILIHAN REGIONAL PEMERINTAH DAERAH -->
                                <div class="row" id="container_pemda_wilayah" style="display: none;">
                                    <div class="col-md-6 form-group">
                                        <label>Provinsi <span class="text-danger">*</span></label>
                                        <select name="provinsi_pemda" id="provinsi_pemda" class="form-control" <?php echo $disabled_attr; ?>>
                                            <option value="">-- Pilih Provinsi --</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 form-group">
                                        <label>Kabupaten / Kota <span class="text-danger">*</span></label>
                                        <select name="kabupaten_pemda" id="kabupaten_pemda" class="form-control" <?php echo $disabled_attr; ?>>
                                            <option value="">-- Pilih Kabupaten/Kota --</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 form-group">
                                        <label>Unit Kerja Saat Ini <span class="text-danger">*</span></label>
                                        <input type="text" name="unit_saat_ini" class="form-control" required value="<?php echo htmlspecialchars($_POST['unit_saat_ini'] ?? ''); ?>" <?php echo $readonly_attr; ?>>
                                    </div>
                                    <div class="col-md-6 form-group">
                                        <label>Unit Kerja Sebelumnya (Opsional)</label>
                                        <input type="text" name="unit_sebelumnya" class="form-control" value="<?php echo htmlspecialchars($_POST['unit_sebelumnya'] ?? ''); ?>" <?php echo $readonly_attr; ?>>
                                    </div>
                                </div>

                                <div class="actions">
                                    <button type="button" class="btn btn-secondary prev-step" data-prev="1"><i class="fas fa-arrow-left"></i> Sebelumnya</button>
                                    <button type="button" class="btn btn-primary next-step float-right" data-next="3" <?php echo $disabled_attr; ?>>Lanjut <i class="fas fa-arrow-right"></i></button>
                                </div>
                            </div>

                            <div class="step-box" id="step3">
                                <div class="step-header"><i class="fas fa-cloud-upload-alt"></i> Langkah 3: Unggah Dokumen Persyaratan (Format PDF Maks. 5MB)</div>
                                <p class="text-danger">Pastikan semua file sudah diunggah. Nama file yang disarankan adalah sebagai panduan.</p>
                                <table class="upload-grid">
                                    <thead>
                                        <tr>
                                            <th style="width:5%;">No</th>
                                            <th style="width:35%;">Nama Dokumen</th>
                                            <th>File Upload (.pdf)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php
                                    $upload_fields_list = [
                                        ['file_usulan_ukom', 'Surat Usulan Uji Kompetensi', 'UjiKom_NIP_Nama'],
                                        ['file_sk_jafung', 'Salinan SK Jabatan Fungsional', 'SKjafung_NIP_Nama'],
                                        ['file_pangkat', 'Salinan SK Jabatan Terakhir', 'SKPangkat_NIP_Nama'],
                                        ['file_pak', 'Salinan Penetapan Angka Kredit (PAK) Terakhir', 'PAK_NIP_Nama'],
                                        ['file_skp', 'Salinan SKP 2 Tahun Terakhir', 'SKP2Tahun_NIP_Nama'],
                                    ];
                                    $no = 1;
                                    foreach ($upload_fields_list as $field): ?>
                                        <tr>
                                            <td><?php echo $no++; ?></td>
                                            <td class="upload-label"><?php echo $field[1]; ?> <span class="text-danger">*</span></td>
                                            <td>
                                                <input type="file" name="<?php echo $field[0]; ?>" accept=".pdf" required <?php echo $disabled_attr; ?>>
                                                <span class="upload-hint">Nama file: <?php echo $field[2]; ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>

                                <div class="actions">
                                    <button type="button" class="btn btn-secondary prev-step" data-prev="2"><i class="fas fa-arrow-left"></i> Sebelumnya</button>
                                    <button type="submit" id="btnSubmitForm" class="btn btn-success float-right" <?php echo $disabled_attr; ?>><i class="fas fa-check-circle"></i> Selesaikan & Kirim Pendaftaran</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <?php include 'template/footer.php'; ?> 
</div>

<script src="js/jquery.min.js"></script>
<script src="js/bootstrap.bundle.min.js"></script>
<script src="js/adminlte.min.js"></script>
<script>
$(document).ready(function() {
    const IS_FORM_DISABLED = <?php echo $is_form_disabled ? 'true' : 'false'; ?>;
    let currentStep = 1;

    function updateStepDisplay(step) {
        $('.step-box').removeClass('active').hide();
        $('#step' + step).addClass('active').show();
        
        $('.step-dot').removeClass('active').removeClass('done');
        $('.step-dot').each(function() {
            const dotStep = parseInt($(this).data('step'));
            if (dotStep < step) {
                $(this).addClass('done');
            } else if (dotStep === step) {
                $(this).addClass('active');
            }
        });
        currentStep = step;
        window.scrollTo(0, 0);
    }

    function validateStep(step) {
        let isValid = true;
        const currentBox = $('#step' + step);
        
        currentBox.find('input[required], select[required]').each(function() {
            const $input = $(this);
            if ($input.prop('disabled') || $input.prop('readonly')) {
                $input.removeClass('is-invalid');
                return true; 
            }
            if (!$input.val() || $input.val().trim() === '') {
                $input.addClass('is-invalid');
                isValid = false;
            } else {
                $input.removeClass('is-invalid');
            }
        });
        return isValid;
    }

    $('.next-step').on('click', function() {
        if (!IS_FORM_DISABLED && validateStep(currentStep)) {
            const nextStep = parseInt($(this).data('next'));
            if (nextStep) updateStepDisplay(nextStep);
        } else if (IS_FORM_DISABLED) {
            const nextStep = parseInt($(this).data('next'));
            if (nextStep) updateStepDisplay(nextStep);
        } else {
            alert('Mohon isi semua data yang wajib diisi pada langkah ini.');
        }
    });

    $('.prev-step').on('click', function() {
        const prevStep = parseInt($(this).data('prev'));
        if (prevStep) updateStepDisplay(prevStep);
    });

    // --- LOGIKA FETCH & DROPDOWN PEMERINTAH DAERAH ---
    function loadProvinsi() {
        $.getJSON('form_kenaikan_jabatan.php?action=get_provinsi', function(data) {
            let options = '<option value="">-- Pilih Provinsi --</option>';
            $.each(data, function(index, val) {
                options += `<option value="${val}">${val}</option>`;
            });
            $('#provinsi_pemda').html(options);
        });
    }

    loadProvinsi();

    $('#provinsi_pemda').on('change', function() {
        const provSelected = $(this).val();
        let options = '<option value="">-- Pilih Kabupaten/Kota --</option>';
        if (provSelected) {
            $.getJSON('form_kenaikan_jabatan.php?action=get_kabupaten&provinsi=' + encodeURIComponent(provSelected), function(data) {
                $.each(data, function(index, item) {
                    options += `<option value="${item.nama}">${item.nama}</option>`;
                });
                $('#kabupaten_pemda').html(options);
            });
        } else {
            $('#kabupaten_pemda').html(options);
        }
    });

    $('#instansi').on('change', function() {
        const isPemda = $(this).val() === 'Pemerintah Daerah';
        const unitOrganisasiInput = $('#unit_organisasi');
        const containerPemda = $('#container_pemda_wilayah');
        const containerUnitOrg = $('#container_unit_organisasi');
        const requiredStar = $('.required-star');
        
        if (isPemda) {
            containerPemda.show();
            containerUnitOrg.hide();
            $('#provinsi_pemda').prop('required', true);
            $('#kabupaten_pemda').prop('required', true);
            unitOrganisasiInput.prop('required', false).val('Pemerintah Daerah');
        } else {
            containerPemda.hide();
            containerUnitOrg.show();
            $('#provinsi_pemda').prop('required', false).val('');
            $('#kabupaten_pemda').prop('required', false).val('');
            unitOrganisasiInput.prop('required', true);
            requiredStar.show();
            if (unitOrganisasiInput.val() === 'Pemerintah Daerah') {
                unitOrganisasiInput.val('');
            }
        }
    }).trigger('change');

    // PROTEKSI DOUBLE SUBMIT PADA FORM
    $('#ujiform').on('submit', function(e) {
        if (IS_FORM_DISABLED) {
            e.preventDefault();
            return false;
        }

        const $btn = $('#btnSubmitForm');
        if ($btn.hasClass('disabled') || $btn.prop('disabled')) {
            e.preventDefault();
            return false;
        }

        // Disable tombol submit dan tampilkan loading animasi
        $btn.addClass('disabled').prop('disabled', true)
            .html('<i class="fas fa-spinner fa-spin mr-1"></i> Memproses & Mengirim...');
    });

    <?php if (!empty($error_message)): ?>
        updateStepDisplay(3);
    <?php else: ?>
        updateStepDisplay(1);
    <?php endif; ?>

    $('input:not([type="file"]), select').addClass('form-control');
});
</script>
</body>
</html>