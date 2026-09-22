<?php
// FILE: form_perpindahan_jabatan.php - Formulir Pendaftaran Uji Kompetensi (Multi-Step) untuk Perpindahan Jabatan

// --- ASUMSI FILE PENDUKUNG ---
require_once 'koneksi.php';    // Koneksi database
require_once 'auth_guard.php'; // Digunakan untuk menjaga sesi dan mendapatkan data user
require_once 'role_definitions.php'; // <<< FILE DITAMBAHKAN SESUAI PERMINTAAN

// Cek koneksi untuk mencegah error fatal
if (!isset($conn) || !$conn) {
    die("Fatal Error: Koneksi database tidak tersedia. Mohon cek koneksi.php");
}
// --- LOGIKA ON/OFF AKSES FORM --- 

// Ambil status akses dari tabel admin_update (ID 1 adalah baris utama)
$cek_akses = mysqli_query($conn, "SELECT status_form_pj FROM tb_admin_update WHERE id = 1");
$status = mysqli_fetch_assoc($cek_akses);

// Jika status_form_pj bernilai 0 (Tutup) atau data tidak ditemukan
if (!$status || $status['status_form_pj'] == 0) {
    // Arahkan ke halaman index_pengusul.php dengan pesan status tertutup
    header("Location: index_pengusul.php?status=closed");
    exit;
}

// --- Kontrol Akses Logika ---
// ASUMSI KONSTANTA ROLE ADA DI role_definitions.php (jika tidak, gunakan definisi fallback)
if (!defined('ROLE_SUPER_ADMIN')) define('ROLE_SUPER_ADMIN', 'super_admin'); // <<< TAMBAHAN UNTUK SUPER_ADMIN (Baris 21)
if (!defined('ROLE_ADMIN')) define('ROLE_ADMIN', 'admin'); 
if (!defined('ROLE_USER_ADMIN')) define('ROLE_USER_ADMIN', 'user_admin'); 
if (!defined('ROLE_USER_PENGUSUL')) define('ROLE_USER_PENGUSUL', 'user_pengusul'); // <<< FIX: DEFINISI ROLE DITAMBAHKAN
if (!defined('ROLE_USER_BIASA')) define('ROLE_USER_BIASA', 'user_biasa'); 

// Ambil data sesi (diasumsikan diatur di auth_guard.php)
$user_role_sesi = $_SESSION['user_role'] ?? ROLE_USER_BIASA; 
$user_nip = $_SESSION['nip'] ?? ''; 
$NAMA_TABEL_UJIKOM = "pengajuan_ujikom";
$user_nama_sesi = $_SESSION['user_name'] ?? ''; 
$user_email_sesi = $_SESSION['user_email'] ?? ''; 

$nip_sudah_ada = false;
$show_rereg_message = false;
$rereg_date = null;

if (!empty($user_nip)) {
    // Cari status pengajuan terakhir yang BUKAN draft untuk pengecekan aturan registrasi berkali-kali
    $sql_cek = "SELECT id, status_pengajuan, tanggal_re_registrasi 
                FROM {$NAMA_TABEL_UJIKOM} 
                WHERE nip = '$user_nip' 
                AND jenis_pengajuan = 'Perpindahan Jabatan'
                AND LOWER(status_pengajuan) != 'draft'
                ORDER BY id DESC LIMIT 1";

    $result_cek = $conn->query($sql_cek);

    if ($result_cek && $result_cek->num_rows > 0) {
        $data = $result_cek->fetch_assoc();
        $status = strtolower($data['status_pengajuan']);
        $rereg_date = $data['tanggal_re_registrasi'];

        if ($status == 'tidak lulus') {
            if (empty($rereg_date)) {
                $nip_sudah_ada = true;
            } else {
                $today = date('Y-m-d');
                if ($today < $rereg_date) {
                    $nip_sudah_ada = true;
                    $show_rereg_message = true;
                } else {
                    $nip_sudah_ada = false;
                }
            }
        } else {
            $nip_sudah_ada = true;
        }
    } else {
        $nip_sudah_ada = false;
    }
}

// Fungsi check_role
if (!function_exists('check_role')) {
    function check_role(array $allowed_roles) {
        global $user_role_sesi; 
        return in_array($user_role_sesi, $allowed_roles);
    }
}

// MODIFIKASI KRITIS: ROLE_USER_PENGUSUL dan ROLE_USER_BIASA ditambahkan agar bisa SUBMIT/isi form
$allowed_submit_roles = [ROLE_SUPER_ADMIN, ROLE_ADMIN, ROLE_USER_ADMIN, ROLE_USER_PENGUSUL, ROLE_USER_BIASA]; // <<< FIX UTAMA DI BARIS INI
// Variabel ini TRUE jika role saat ini TIDAK diizinkan untuk SUBMIT
$is_form_disabled = !check_role($allowed_submit_roles); 

// Siapkan atribut disabled/readonly untuk HTML umum
$disabled_attr = $is_form_disabled ? 'disabled' : '';
$readonly_attr = $is_form_disabled ? 'readonly' : '';

// --- LOGIKA KHUSUS NIP ---
if ($user_role_sesi == ROLE_ADMIN || $user_role_sesi == ROLE_SUPER_ADMIN) { 
    $nip_control_attr = ''; 
    $nip_title_attr = 'title="Anda adalah Admin/Super Admin: NIP dapat diubah untuk keperluan pengujian aplikasi."';
    $is_nip_locked = false; 
} else {
    $nip_control_attr = 'readonly';
    $nip_title_attr = 'title="NIP tidak dapat diubah karena diambil dari data akun Anda."';
    $is_nip_locked = true; 
}

// --- PENTING: DEKLARASI VARIABEL UNTUK HIGHLIGHT MENU SIDEBAR --
$page = 'ujikom'; 
$sub_page = 'perpindahan_jabatan';      
$page_title = 'Pengajuan Uji Kompetensi - Perpindahan Jabatan'; 
// -------------------------------------------------------------

// --- PENGATURAN GLOBAL ---
$TARGET_DIR = "uploads/perpindahan/"; 
$MAX_FILE_SIZE = 5242880; // 5 MB

$success_message = '';
$error_message = '';

// ARRAY FILE MAP - MENGGUNAKAN NAMA KOLOM DATABASE
$file_map = [
    'file_usulan' => 'file_surat_usulan_perpindahan', 
    'file_kebutuhan' => 'file_dokumen_penetapan', 
    'file_usulan_ukom' => 'file_surat_usulan_uji', 
    'file_drh' => 'file_portofolio', 
    'file_cpns_pns' => 'file_sk_cpns_pns', 
    'file_pangkat' => 'file_sk_pangkat', 
    'file_jabatan' => 'file_sk_jabatan', 
    'file_skp' => 'file_skp', 
    'file_ijazah_transkrip' => 'file_ijazah_transkrip', 
    'file_integritas' => 'file_pernyataan_integritas', 
    'file_bersedia' => 'file_pernyataan_bersedia', 
    'file_pengalaman' => 'file_pernyataan_pengalaman', 
    'file_penempatan' => 'file_rencana_penempatan', 
];

$file_descriptions = [
    'file_usulan' => 'Surat Usulan Perpindahan Jabatan',
    'file_kebutuhan' => 'Dokumen Penetapan Kebutuhan JF',
    'file_usulan_ukom' => 'Surat Usulan Uji Kompetensi',
    'file_drh' => 'Daftar Riwayat Hidup (DRH) / Portofolio',
    'file_cpns_pns' => 'Suket Sehat dari Dokter Pemerintah (Puskesmas, RSUD, dll)',
    'file_pangkat' => 'Salinan SK Pangkat/Golongan Terakhir',
    'file_jabatan' => 'Salinan SK Jabatan Terakhir',
    'file_skp' => 'Salinan SKP 2 Tahun Terakhir',
    'file_ijazah_transkrip' => 'Salinan Ijazah dan Transkrip Nilai',
    'file_integritas' => 'Surat Pernyataan Integritas/Moralitas',
    'file_bersedia' => 'Surat Pernyataan Bersedia Diangkat',
    'file_pengalaman' => 'Surat Pernyataan Memiliki Pengalaman Jabatan',
    'file_penempatan' => 'Rencana Penempatan PNS',
];

// ==========================================
// DETEKSI APAKAH USER MEMILIKI DATA DRAFT? (PRE-POPULATE)
// ==========================================
$is_draft_exist = false;
$existing_draft_data = [];

// Siapkan nilai fallback default dari POST, Sesi, atau Kosong
$nama = $_POST['nama'] ?? $user_nama_sesi ?? '';
$nip = $_POST['nip'] ?? $user_nip ?? '';
$jabatan_saat_ini = $_POST['jabatan_saat_ini'] ?? '';
$jf_pkp_tujuan = $_POST['jf_pkp_tujuan'] ?? '';
$pangkat = $_POST['pangkat'] ?? '';
$tmt = $_POST['tmt'] ?? date('Y-m-d');
$jenjang = $_POST['jenjang'] ?? '';
$prodi = $_POST['prodi'] ?? '';
$email = $_POST['email'] ?? $user_email_sesi ?? '';
$hp = $_POST['hp'] ?? '';
$instansi = $_POST['instansi'] ?? '';
$unit_organisasi = $_POST['unit_organisasi'] ?? '';
$instansi_daerah = $_POST['instansi_daerah'] ?? '';
$unit_saat_ini = $_POST['unit_saat_ini'] ?? '';
$unit_sebelumnya = $_POST['unit_sebelumnya'] ?? '';

if (!empty($user_nip) && !$is_form_disabled) {
    $sql_cek_draft = "SELECT * FROM {$NAMA_TABEL_UJIKOM} WHERE nip = '$user_nip' AND jenis_pengajuan = 'Perpindahan Jabatan' AND LOWER(status_pengajuan) = 'draft' LIMIT 1";
    $result_draft = $conn->query($sql_cek_draft);
    if ($result_draft && $result_draft->num_rows > 0) {
        $is_draft_exist = true;
        $existing_draft_data = $result_draft->fetch_assoc();
        
        // Timpa variabel dengan data yang tersimpan di database draft
        if (empty($_POST)) {
            $nama = $existing_draft_data['nama'];
            $nip = $existing_draft_data['nip'];
            $jabatan_saat_ini = $existing_draft_data['jabatan_saat_ini'];
            $jf_pkp_tujuan = $existing_draft_data['jf_pkp_tujuan'];
            $pangkat = $existing_draft_data['pangkat'];
            $tmt = $existing_draft_data['tmt_pangkat'];
            $jenjang = $existing_draft_data['jenjang_pendidikan'];
            $prodi = $existing_draft_data['program_studi'];
            $email = $existing_draft_data['email'];
            $hp = $existing_draft_data['hp'];
            $instansi = $existing_draft_data['instansi'];
            $unit_organisasi = $existing_draft_data['unit_organisasi'];
            $instansi_daerah = $existing_draft_data['unit_daerah'];
            $unit_saat_ini = $existing_draft_data['unit_saat_ini'];
            $unit_sebelumnya = $existing_draft_data['unit_sebelumnya'];
        }
    }
}

// >>> LOGIKA PEMROSESAN FORM SUBMISSION <<<
if (!$is_form_disabled && $_SERVER["REQUEST_METHOD"] == "POST") {
    
    $nama = $_POST['nama'] ?? '';
    $nip = $_POST['nip'] ?? $user_nip;
    $jabatan_saat_ini = $_POST['jabatan_saat_ini'] ?? '';
    $jf_pkp_tujuan = $_POST['jf_pkp_tujuan'] ?? '';
    $pangkat = $_POST['pangkat'] ?? '';
    $tmt = $_POST['tmt'] ?? '';
    $jenjang = $_POST['jenjang'] ?? '';
    $prodi = $_POST['prodi'] ?? '';
    $email = $_POST['email'] ?? $user_email_sesi; 
    $hp = $_POST['hp'] ?? '';
    $instansi = $_POST['instansi'] ?? '';
    $unit_organisasi = $_POST['unit_organisasi'] ?? '';
    $instansi_daerah = $_POST['instansi_daerah'] ?? ''; 
    $unit_saat_ini = $_POST['unit_saat_ini'] ?? '';
    $unit_sebelumnya = $_POST['unit_sebelumnya'] ?? '';

    // Cek apakah tombol yang diklik adalah Selesaikan/Kirim Final atau Simpan Draft
    $is_final_submit = isset($_POST['submit_final']);
    $status_pengajuan_baru = $is_final_submit ? 'Menunggu Verifikasi' : 'Draft';

    $upload_ok = true;
    
    if (empty($nip)) {
         $error_message .= "❌ NIP wajib diisi.<br>";
         $upload_ok = false;
    }
    
    $file_paths = [];
    
    if (!is_dir($TARGET_DIR)) {
        if (!@mkdir($TARGET_DIR, 0777, true)) { 
            $error_message .= "Gagal membuat folder upload ({$TARGET_DIR}).<br>";
            $upload_ok = false;
        }
    }

    if ($upload_ok) {
        foreach ($file_map as $form_field => $db_column) {
            
            // Jika ada file yang diupload (Error code 4 berarti user tidak memilih file baru)
            if (isset($_FILES[$form_field]) && $_FILES[$form_field]['error'] != 4) {
                 
                if ($_FILES[$form_field]['error'] != 0) {
                    $error_message .= "Terjadi error saat upload file **{$file_descriptions[$form_field]}** (Code: {$_FILES[$form_field]['error']}).<br>";
                    $upload_ok = false;
                    break;
                }
                
                $file_temp = $_FILES[$form_field]['tmp_name'];
                $file_name_original = basename($_FILES[$form_field]['name']);
                $file_ext = strtolower(pathinfo($file_name_original, PATHINFO_EXTENSION));
                
                if ($file_ext != "pdf") {
                    $error_message .= "File **{$file_descriptions[$form_field]}** harus berformat PDF.<br>";
                    $upload_ok = false;
                    break;
                }
                if ($_FILES[$FILES[$form_field]['size'] > $MAX_FILE_SIZE]) { 
                    $error_message .= "File **{$file_descriptions[$form_field]}** terlalu besar. Maksimal 5MB.<br>";
                    $upload_ok = false;
                    break;
                }

                $prefix = str_replace(['file_', '_'], ['','-'], $form_field); 
                $clean_nama = substr(preg_replace('/[^A-Za-z0-9-]/', '', str_replace(' ', '-', $nama)), 0, 15); 
                $new_file_name = "{$prefix}_{$nip}_{$clean_nama}_" . time() . ".{$file_ext}";
                $target_file = $TARGET_DIR . $new_file_name;

                if (move_uploaded_file($file_temp, $target_file)) {
                    $file_paths[$db_column] = $conn->real_escape_string($new_file_name); 
                    
                    // Bersihkan file lama jika meng-update draf agar hosting Anda tidak penuh berkas sampah
                    if ($is_draft_exist && !empty($existing_draft_data[$db_column])) {
                        @unlink($TARGET_DIR . $existing_draft_data[$db_column]);
                    }
                } else {
                    $error_message .= "Gagal mengunggah file **{$file_descriptions[$form_field]}**.<br>";
                    $upload_ok = false;
                    break;
                }

            } else {
                // Jika user klik "Kirim Final", pastikan file lama ada ATAU file baru telah dimasukkan
                if ($is_final_submit) {
                    $file_lama_ada = ($is_draft_exist && !empty($existing_draft_data[$db_column]));
                    if (!$file_lama_ada) {
                        $error_message .= "File **{$file_descriptions[$form_field]}** wajib diunggah untuk pengiriman final.<br>";
                        $upload_ok = false;
                    }
                }
            }
        }
    }
    
    // Simpan perubahan ke Database
    if ($upload_ok && empty($error_message)) {
        
        $unit_organisasi_value = ($instansi == 'Kementerian/Lembaga') ? $unit_organisasi : '';
        $unit_daerah_value = ($instansi == 'Pemerintah Daerah') ? $instansi_daerah : '';
        
        $safe_nip = $conn->real_escape_string($nip);
        $safe_nama = $conn->real_escape_string($nama);
        $safe_email = $conn->real_escape_string($email);
        $safe_hp = $conn->real_escape_string($hp);
        $safe_jabatan_saat_ini = $conn->real_escape_string($jabatan_saat_ini);
        $safe_jf_pkp_tujuan = $conn->real_escape_string($jf_pkp_tujuan);
        $safe_pangkat = $conn->real_escape_string($pangkat);
        $safe_tmt = $conn->real_escape_string($tmt);
        $safe_jenjang = $conn->real_escape_string($jenjang);
        $safe_prodi = $conn->real_escape_string($prodi);
        $safe_instansi = $conn->real_escape_string($instansi);
        $safe_unit_organisasi = $conn->real_escape_string($unit_organisasi_value);
        $safe_unit_daerah = $conn->real_escape_string($unit_daerah_value);
        $safe_unit_saat_ini = $conn->real_escape_string($unit_saat_ini);
        $safe_unit_sebelumnya = $conn->real_escape_string($unit_sebelumnya);

        if ($is_draft_exist) {
            // JIKA AKSI UPDATE DRAFT YANG SUDAH ADA
            $sql_update = "UPDATE {$NAMA_TABEL_UJIKOM} SET 
                            nama = '$safe_nama',
                            jabatan_saat_ini = '$safe_jabatan_saat_ini',
                            jf_pkp_tujuan = '$safe_jf_pkp_tujuan',
                            email = '$safe_email',
                            hp = '$safe_hp',
                            pangkat = '$safe_pangkat',
                            tmt_pangkat = '$safe_tmt',
                            jenjang_pendidikan = '$safe_jenjang',
                            program_studi = '$safe_prodi',
                            instansi = '$safe_instansi',
                            unit_organisasi = '$safe_unit_organisasi',
                            unit_daerah = '$safe_unit_daerah',
                            unit_saat_ini = '$safe_unit_saat_ini',
                            unit_sebelumnya = '$safe_unit_sebelumnya',
                            status_pengajuan = '$status_pengajuan_baru',
                            tanggal_pengajuan = NOW()";

            foreach ($file_paths as $col => $path) {
                $sql_update .= ", {$col} = '{$path}'";
            }
            $sql_update .= " WHERE id = " . $existing_draft_data['id'];
            $exec_query = $conn->query($sql_update);
        } else {
            // JIKA AKSI BUAT DATA INSERT BARU BERSTATUS DRAFT / LANGSUNG KIRIM
            $columns = "nip, nama, jabatan_saat_ini, jf_pkp_tujuan, email, hp, pangkat, tmt_pangkat, jenjang_pendidikan, program_studi, instansi, unit_organisasi, unit_daerah, unit_saat_ini, unit_sebelumnya, jenis_pengajuan, tanggal_pengajuan, status_pengajuan";
            $values = "'$safe_nip', '$safe_nama', '$safe_jabatan_saat_ini', '$safe_jf_pkp_tujuan', '$safe_email', '$safe_hp', '$safe_pangkat', '$safe_tmt', '$safe_jenjang', '$safe_prodi', '$safe_instansi', '$safe_unit_organisasi', '$safe_unit_daerah', '$safe_unit_saat_ini', '$safe_unit_sebelumnya', 'Perpindahan Jabatan', NOW(), '$status_pengajuan_baru'";

            foreach ($file_map as $form_field => $db_column) {
                $columns .= ", " . $db_column;
                $val = isset($file_paths[$db_column]) ? "'".$file_paths[$db_column]."'" : "NULL";
                $values .= ", " . $val;
            }
            
            $sql_insert = "INSERT INTO {$NAMA_TABEL_UJIKOM} ({$columns}) VALUES ({$values})";
            $exec_query = $conn->query($sql_insert);
        }
        
        if ($exec_query) {
            if ($is_final_submit) {
                header("Location: list_perpindahan_pengusul.php?status=success"); 
            } else {
                header("Location: form_perpindahan_jabatan.php?status=draft_saved");
            }
            exit(); 
        } else {
             $error_message .= "❌ Gagal menyimpan data ke database: " . $conn->error;
        }
    }

    // Cleanup file jika ada error submit
    if (!empty($error_message) && !empty($file_paths)) { 
        foreach ($file_paths as $path) {
            $full_path = $TARGET_DIR . $path;
            if (file_exists($full_path)) @unlink($full_path); 
        }
    }
} 

// Handle Notifikasi Status URL Get
if (isset($_GET['status']) && $_GET['status'] == 'success') {
    $success_message = "✅ Data pendaftaran uji kompetensi **berhasil dikirim**! Status: Menunggu Verifikasi.";
} elseif (isset($_GET['status']) && $_GET['status'] == 'draft_saved') {
    $success_message = "💾 **Draft formulir berhasil disimpan!** Data Anda aman di sistem. Silakan lengkapi sisa berkas kapan saja sebelum dikirim final.";
}

$user_data = [
    'nama' => $user_nama_sesi ?? 'Pengguna JF',
    'role' => $user_role_sesi,
    'email' => $user_email_sesi ?? 'user@instansi.go.id',
    'join_date' => $_SESSION['join_date'] ?? 'Maret 2023'
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <h1 class="font-weight-bold" style="color: #ffffff !important;">
    <title><?php echo $page_title; ?> | Instansi Pembina JF</title>
    </h1>

    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <style>
        .brand-link { background-color: #111827; }
        .brand-link .logo-pupr i { color: #0f62fe; }
        .logo-pupr { background: #fff; padding: 4px; width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; border-radius: 50%;}
        .step-box { border: 1px solid #ccc; padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        .step-header { background-color: #f8f9fa; padding: 10px; margin: -15px -15px 15px -15px; border-bottom: 1px solid #ccc; font-weight: bold; }
        .progress-indicator { display: flex; justify-content: space-between; margin-bottom: 20px; }
        .progress-step { flex: 1; text-align: center; padding: 10px; border-bottom: 3px solid #ccc; }
        .progress-step.active { border-bottom-color: #007bff; font-weight: bold; color: #007bff; }
        .progress-step.completed { border-bottom-color: #28a745; color: #28a745; }
        .message.error { color: #721c24; background-color: #f8d7da; border-color: #f5c6cb; padding: 10px; border-radius: .25rem; margin-bottom: 15px; }
        .message.success { color: #155724; background-color: #d4edda; border-color: #c3e6cb; padding: 10px; border-radius: .25rem; margin-bottom: 15px; }
        .message.warning { color: #856404; background-color: #fff3cd; border-color: #ffeeba; padding: 10px; border-radius: .25rem; margin-bottom: 15px; }
    </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">

    <?php include 'template/navbar.php'; ?>
    <?php include 'template/sidebar.php'; ?>

    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0"><i class="fas fa-file-upload"></i> Pendaftaran Uji Kompetensi</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                            <li class="breadcrumb-item active">Formulir</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <section class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-md-12">
                        <div class="card card-primary">
                            <div class="card-header">
                                <h3 class="card-title" id="card-title-step"><i class="fas fa-edit"></i> Formulir Perpindahan Jabatan Fungsional (Langkah 1/3)</h3>
                            </div>
                            <div class="card-body">
    <?php if ($success_message): ?>
        <div class="message success">
            <?php echo $success_message; ?>
            <p class="mt-2"><a href="list_perpindahan_pengusul.php" class="btn btn-sm btn-success">Lihat Status Pengajuan</a></p>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="message error">
            <i class="fas fa-times-circle"></i> **Gagal Mengirimkan Data:** <p><?php echo $error_message; ?></p>
        </div>
    <?php endif; ?>

    <?php if ($show_rereg_message): ?>
    <div class="alert alert-info">
        <h5><i class="icon fas fa-info-circle"></i> Informasi Re-Registrasi</h5>
        Anda dinyatakan <b>TIDAK LULUS</b>.<br>
        Anda dapat melakukan <b>Re-Registrasi mulai tanggal (<?php echo date('d F Y', strtotime($rereg_date)); ?>)</b>.
        <br><br>
        Silakan cek kembali setelah tanggal tersebut.
    </div>
    <?php 
        $is_form_disabled = true;
        $disabled_attr = 'disabled';
        $readonly_attr = 'readonly';
    ?>
<?php elseif ($nip_sudah_ada): ?>
    <div class="alert alert-warning">
        <h5><i class="icon fas fa-exclamation-triangle"></i> Pengajuan Terdeteksi!</h5>
        Sistem mendeteksi bahwa NIP Anda (<b><?php echo htmlspecialchars($user_nip); ?></b>) sudah pernah mengirimkan data pengajuan Perpindahan Jabatan yang valid. 
        Sesuai ketentuan, Anda hanya diperbolehkan mengirimkan pengajuan sebanyak <b>satu kali</b>. <br>
        <a href="list_perpindahan_pengusul.php" class="btn btn-sm btn-dark mt-2">Cek Riwayat Pengajuan Saya</a>
    </div>
    <?php 
        $is_form_disabled = true; 
        $disabled_attr = 'disabled';
        $readonly_attr = 'readonly';
    ?>
<?php elseif ($is_form_disabled): ?>
        <div class="message warning">
            <i class="fas fa-exclamation-triangle"></i> **PERINGATAN AKSES DIBATASI!**
            <p>Peran Anda saat ini (**<?php echo htmlspecialchars(strtoupper($user_role_sesi)); ?>**) hanya diizinkan untuk melihat tampilan formulir ini.</p>
        </div>
    <?php else: ?>
        <div class="message success">
            <i class="fas fa-check-circle"></i> **AKSES FORMULIR AKTIF!**
            <p>Anda sedang mengisi formulir sebagai **<?php echo htmlspecialchars(strtoupper($user_role_sesi)); ?>**. <?php echo $is_draft_exist ? 'Menampilkan data simpanan draf terakhir Anda.' : 'Silakan isi berkas pendaftaran.'; ?></p>
        </div>
    <?php endif; ?>
    
    <?php if (empty($success_message) || isset($_GET['status']) && $_GET['status'] == 'draft_saved'): ?>
        <div class="progress-indicator">
            <div class="progress-step active" data-step="1">Data Pribadi</div>
            <div class="progress-step" data-step="2">Data Instansi</div>
            <div class="progress-step" data-step="3">Upload Dokumen</div>
        </div>

        <form id="perpindahanForm" method="POST" enctype="multipart/form-data" action="form_perpindahan_jabatan.php">
    
    <div class="step-box" id="step1">
    <div class="step-header"><i class="fas fa-user"></i> Langkah 1: Data Pribadi</div>
    
    <div class="row">
    <div class="col-md-6 form-group">
        <label>Nama Lengkap dan Gelar <span class="text-danger">*</span></label>
        <div class="input-group">
            <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-user"></i></span></div>
            <input type="text" name="nama" class="form-control" placeholder="Nama Lengkap" required value="<?php echo htmlspecialchars($nama); ?>" <?php echo $readonly_attr; ?>>
        </div>
    </div>
    <div class="col-md-6 form-group">
        <label>NIP <span class="text-danger">*</span></label>
        <div class="input-group">
            <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-id-card"></i></span></div>
            <input type="text" name="nip" class="form-control" placeholder="Nomor Induk Pegawai" required value="<?php echo htmlspecialchars($nip); ?>" <?php echo $nip_control_attr; ?> <?php echo $nip_title_attr; ?>>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6 form-group">
        <label>Jabatan Saat Ini <span class="text-danger">*</span></label>
        <div class="input-group">
            <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-briefcase"></i></span></div>
            <input type="text" name="jabatan_saat_ini" class="form-control" placeholder="Cth: Analis Kebijakan Ahli Muda" required value="<?php echo htmlspecialchars($jabatan_saat_ini); ?>" <?php echo $readonly_attr; ?>>
        </div>
    </div>
    <div class="col-md-6 form-group">
        <label>JF PKP Yang Dituju <span class="text-danger">*</span></label>
        <div class="input-group">
            <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-bullseye"></i></span></div>
            <?php $select_disabled = (!empty($readonly_attr)) ? 'disabled' : ''; ?>
            <select name="jf_pkp_tujuan" class="form-control select2" required <?php echo $select_disabled; ?>>
                <option value="">-- Pilih Jenjang Tujuan --</option>
                <option value="Penata Kelola Perumahan Ahli Pertama" <?php echo ($jf_pkp_tujuan == 'Penata Kelola Perumahan Ahli Pertama') ? 'selected' : ''; ?>>Penata Kelola Perumahan Ahli Pertama</option>
                <option value="Penata Kelola Perumahan Ahli Muda" <?php echo ($jf_pkp_tujuan == 'Penata Kelola Perumahan Ahli Muda') ? 'selected' : ''; ?>>Penata Kelola Perumahan Ahli Muda</option>
                <option value="Penata Kelola Perumahan Ahli Madya" <?php echo ($jf_pkp_tujuan == 'Penata Kelola Perumahan Ahli Madya') ? 'selected' : ''; ?>>Penata Kelola Perumahan Ahli Madya</option>
                <option value="Penata Kelola Perumahan Ahli Utama" <?php echo ($jf_pkp_tujuan == 'Penata Kelola Perumahan Ahli Utama') ? 'selected' : ''; ?>>Penata Kelola Perumahan Ahli Utama</option>
            </select>
        </div>
        <?php if ($select_disabled): ?>
            <input type="hidden" name="jf_pkp_tujuan" value="<?php echo htmlspecialchars($jf_pkp_tujuan); ?>">
        <?php endif; ?>
    </div>
</div>

<div class="row">
    <div class="col-md-6 form-group">
        <label>Pangkat/Golongan <span class="text-danger">*</span></label>
        <div class="input-group">
            <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-layer-group"></i></span></div>
            <select name="pangkat" class="form-control" required <?php echo $disabled_attr; ?>>
                <option value="">-- Pilih Pangkat/Golongan --</option>
                <?php
                $golongans = ['III/a', 'III/b', 'III/c', 'III/d', 'IV/a', 'IV/b', 'IV/c', 'IV/d', 'IV/e'];
                foreach ($golongans as $gol) {
                    $selected = ($pangkat == $gol) ? 'selected' : '';
                    echo "<option value=\"$gol\" $selected>$gol</option>";
                }
                ?>
            </select>
        </div>
    </div>
    <div class="col-md-6 form-group">
        <label>TMT Pangkat/Golongan <span class="text-danger">*</span></label>
        <div class="input-group">
            <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-calendar-alt"></i></span></div>
            <input type="date" name="tmt" class="form-control" required value="<?php echo htmlspecialchars($tmt); ?>" <?php echo $readonly_attr; ?>>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6 form-group">
        <label>Jenjang Pendidikan <span class="text-danger">*</span></label>
        <div class="input-group">
            <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-graduation-cap"></i></span></div>
            <select name="jenjang" class="form-control" required <?php echo $disabled_attr; ?>>
                <option value="">-- Pilih Jenjang --</option>
                <?php 
                $levels = ["S1 / D4", "S2", "S3"];
                foreach($levels as $lvl) {
                    $selected = ($jenjang == $lvl) ? 'selected' : '';
                    echo "<option value=\"$lvl\" $selected>$lvl</option>";
                }
                ?>
            </select>
        </div>
    </div>
    <div class="col-md-6 form-group">
        <label>Program Studi/Jurusan <span class="text-danger">*</span></label>
        <input type="text" name="prodi" class="form-control" placeholder="Cth: Teknik Sipil" required value="<?php echo htmlspecialchars($prodi); ?>" <?php echo $readonly_attr; ?>>
    </div>
</div>

<div class="row">
    <div class="col-md-6 form-group">
        <label>Email <span class="text-danger">*</span></label>
        <div class="input-group">
            <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-envelope"></i></span></div>
            <input type="email" name="email" class="form-control" placeholder="alamat@email.com" required value="<?php echo htmlspecialchars($email); ?>" <?php echo $readonly_attr; ?>>
        </div>
    </div>
    <div class="col-md-6 form-group">
        <label>Nomor HP (WhatsApp) <span class="text-danger">*</span></label>
        <div class="input-group">
            <div class="input-group-prepend"><span class="input-group-text"><i class="fab fa-whatsapp"></i></span></div>
            <input type="text" name="hp" class="form-control" placeholder="08123456789" required value="<?php echo htmlspecialchars($hp); ?>" <?php echo $readonly_attr; ?>>
        </div>
    </div>
</div>

    <div class="actions text-right">
        <button type="button" class="btn btn-primary next-step" data-next="2" <?php echo $disabled_attr; ?>>Lanjut <i class="fas fa-arrow-right"></i></button>
    </div>
</div>

    <div class="step-box" id="step2" style="display:none;">
        <div class="step-header"><i class="fas fa-building"></i> Langkah 2: Data Instansi</div>
        <div class="form-group">
            <label for="instansi">Instansi Saat Ini <span class="text-danger">*</span></label>
            <select id="instansi" name="instansi" class="form-control" required <?php echo $disabled_attr; ?>>
                <option value="">-- Pilih --</option>
                <option value="Kementerian/Lembaga" <?php echo ($instansi == 'Kementerian/Lembaga') ? 'selected' : ''; ?>>Kementerian/Lembaga (Pusat)</option>
                <option value="Pemerintah Daerah" <?php echo ($instansi == 'Pemerintah Daerah') ? 'selected' : ''; ?>>Pemerintah Daerah (Pemda)</option>
            </select>
        </div>
        
        <div class="form-group" id="group-unit-organisasi">
            <label for="unit_organisasi">Nama Unit Organisasi (Eselon I) <span class="text-danger required-star">*</span></label>
            <input type="text" id="unit_organisasi" name="unit_organisasi" class="form-control" placeholder="Cth: Direktorat Jenderal Cipta Karya" value="<?php echo htmlspecialchars($unit_organisasi); ?>" <?php echo $readonly_attr; ?>>
        </div>

        <div class="form-group" id="group-instansi-daerah" style="display:none;">
            <label for="instansi_daerah">Nama Instansi Pemerintah Daerah <span class="text-danger required-star-daerah">*</span></label>
            <input type="text" id="instansi_daerah" name="instansi_daerah" class="form-control" placeholder="Cth: Pemerintah Provinsi Jawa Barat" value="<?php echo htmlspecialchars($instansi_daerah); ?>" <?php echo $readonly_attr; ?>>
        </div>
        
        <div class="row">
            <div class="col-md-6 form-group">
                <label for="unit_saat_ini">Unit Kerja Saat Ini (Eselon II) <span class="text-danger">*</span></label>
                <input type="text" id="unit_saat_ini" name="unit_saat_ini" class="form-control" required placeholder="Cth: Dinas Perumahan" value="<?php echo htmlspecialchars($unit_saat_ini); ?>" <?php echo $readonly_attr; ?>>
            </div>
            <div class="col-md-6 form-group">
                <label for="unit_sebelumnya">Unit Kerja Sebelumnya <span class="text-danger">*</span></label>
                <input type="text" id="unit_sebelumnya" name="unit_sebelumnya" class="form-control" required placeholder="Cth: Sub Bagian Umum" value="<?php echo htmlspecialchars($unit_sebelumnya); ?>" <?php echo $readonly_attr; ?>>
            </div>
        </div>

        <div class="actions text-right">
            <button type="button" class="btn btn-secondary prev-step" data-prev="1" <?php echo $disabled_attr; ?>><i class="fas fa-arrow-left"></i> Kembali</button>
            <button type="button" class="btn btn-primary next-step" data-next="3" <?php echo $disabled_attr; ?>>Lanjut <i class="fas fa-arrow-right"></i></button>
        </div>
    </div>

    <!-- MODIFIKASI LANGKAH 3: DRAFT COMPATIBLE -->
    <div class="step-box" id="step3" style="display:none;">
        <div class="step-header"><i class="fas fa-file-pdf"></i> Langkah 3: Upload Dokumen Persyaratan (Wajib PDF, Maks 5MB)</div>
        <p class="text-info"><i class="fas fa-info-circle"></i> Berkas dapat dicicil. Klik <b>Simpan sebagai Draft</b> jika berkas belum lengkap keseluruhan.</p>
        
        <table class="table table-bordered table-sm">
            <thead>
                <tr>
                    <th style="width: 10px;">No</th>
                    <th>Nama Dokumen Persyaratan</th>
                    <th>Status Dokumen</th>
                    <th>Upload File</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $upload_fields = [
                    ['file_usulan', $file_descriptions['file_usulan'], 'file_surat_usulan_perpindahan'],
                    ['file_kebutuhan', $file_descriptions['file_kebutuhan'], 'file_dokumen_penetapan'],
                    ['file_usulan_ukom', $file_descriptions['file_usulan_ukom'], 'file_surat_usulan_uji'],
                    ['file_drh', $file_descriptions['file_drh'], 'file_portofolio'],
                    ['file_cpns_pns', $file_descriptions['file_cpns_pns'], 'file_sk_cpns_pns'],
                    ['file_pangkat', $file_descriptions['file_pangkat'], 'file_sk_pangkat'],
                    ['file_jabatan', $file_descriptions['file_jabatan'], 'file_sk_jabatan'],
                    ['file_skp', $file_descriptions['file_skp'], 'file_skp'],
                    ['file_ijazah_transkrip', $file_descriptions['file_ijazah_transkrip'], 'file_ijazah_transkrip'],
                    ['file_integritas', $file_descriptions['file_integritas'], 'file_pernyataan_integritas'],
                    ['file_bersedia', $file_descriptions['file_bersedia'], 'file_pernyataan_bersedia'],
                    ['file_pengalaman', $file_descriptions['file_pengalaman'], 'file_pernyataan_pengalaman'],
                    ['file_penempatan', $file_descriptions['file_penempatan'], 'file_rencana_penempatan'],
                ];

                $no = 1;
                foreach ($upload_fields as $upload):
                    $db_col = $upload[2];
                    $is_uploaded = ($is_draft_exist && !empty($existing_draft_data[$db_col]));
                ?>
                    <tr>
                        <td><?php echo $no++; ?></td>
                        <td><?php echo $upload[1]; ?></td>
                        <td>
                            <?php if ($is_uploaded): ?>
                                <span class="badge badge-success"><i class="fas fa-check"></i> Tersimpan</span>
                                <br><small><a href="<?php echo $TARGET_DIR . $existing_draft_data[$db_col]; ?>" target="_blank" class="text-primary font-weight-bold">Lihat File</a></small>
                            <?php else: ?>
                                <span class="badge badge-danger"><i class="fas fa-times"></i> Belum Ada</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="input-group">
                                <div class="custom-file">
                                    <input type="file" class="custom-file-input" id="<?php echo $upload[0]; ?>" name="<?php echo $upload[0]; ?>" accept="application/pdf" <?php echo $disabled_attr; ?>>
                                    <label class="custom-file-label" for="<?php echo $upload[0]; ?>">
                                        <?php echo $is_uploaded ? "Ganti berkas PDF jika ingin mengubah" : "Pilih file PDF"; ?>
                                    </label>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="actions d-flex justify-content-between mt-4">
            <button type="button" class="btn btn-secondary prev-step" data-prev="2" <?php echo $disabled_attr; ?>><i class="fas fa-arrow-left"></i> Kembali</button>
            <div>
                <button type="submit" name="submit_draft" class="btn btn-info mr-2" <?php echo $disabled_attr; ?>>
                    <i class="fas fa-save"></i> Simpan sebagai Draft
                </button>
                <button type="submit" name="submit_final" class="btn btn-success" <?php echo $disabled_attr; ?> onclick="return confirm('Apakah Anda yakin ingin mengirim data final? Seluruh berkas wajib terunggah.');">
                    <i class="fas fa-paper-plane"></i> Selesaikan & Kirim Pendaftaran
                </button>
            </div>
        </div>
    </div>

</form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </section>
    </div>

    <footer class="main-footer">
        <div class="float-right d-none d-sm-inline"></div>
        <strong>© <?php echo date('Y'); ?> Instansi Pembina Jabatan Fungsional</strong>
    </footer>
</div>

    <?php include 'template/footer.php'; ?>

<script src="js/jquery.min.js"></script>
<script src="js/bootstrap.bundle.min.js"></script>
<script src="js/adminlte.min.js"></script>

<script>
$(function () {
    let currentStep = 1;
    const MAX_FILE_SIZE = <?php echo $MAX_FILE_SIZE; ?>; 
    const IS_FORM_DISABLED = <?php echo $is_form_disabled ? 'true' : 'false'; ?>;
    const IS_NIP_LOCKED = <?php echo $is_nip_locked ? 'true' : 'false'; ?>;

    function updateStepDisplay(step) {
        currentStep = step;
        $('.step-box').hide();
        $('#step' + step).show();

        $('.progress-step').removeClass('active completed');
        for (let i = 1; i <= 3; i++) {
            const $stepIndicator = $(`.progress-step[data-step="${i}"]`);
            if (i < step) {
                $stepIndicator.addClass('completed');
            } else if (i === step) {
                $stepIndicator.addClass('active');
            }
        }
        
        $('#card-title-step').html('<i class="fas fa-edit"></i> Formulir Perpindahan Jabatan Fungsional (Langkah ' + step + '/3)');
        $('.card-body').get(0).scrollIntoView({ behavior: 'smooth' });
    }

    $('.custom-file-input').on('change', function() {
        var fileName = $(this).val().split('\\').pop();
        if (fileName) {
            $(this).siblings('.custom-file-label').html(fileName);
        }
    });

    function validateStep(step) {
        let isValid = true;
        $('#step' + step).find('.is-invalid').removeClass('is-invalid');
        const $requiredInputs = $('#step' + step).find('input[required]:not([type="file"]), select[required], textarea[required]');
        
        $requiredInputs.each(function() {
             if (!$(this).prop('disabled') && !$(this).prop('readonly')) {
                 if ($(this).val() === '' || $(this).val() === null) {
                     $(this).addClass('is-invalid');
                     isValid = false;
                 }
             }
        });
        
        if (step === 1 && !IS_NIP_LOCKED) {
             const $nipInput = $('input[name="nip"]');
             if ($nipInput.val() && $nipInput.val().length !== 18) {
                 $nipInput.addClass('is-invalid');
                 isValid = false;
             }
        }
        
        if (step === 2) {
             const instansiValue = $('#instansi').val();
             const unitOrganisasiInput = $('#unit_organisasi');
             if (instansiValue === 'Kementerian/Lembaga' && !unitOrganisasiInput.prop('disabled')) {
                 if (!unitOrganisasiInput.val()) {
                     unitOrganisasiInput.addClass('is-invalid');
                     isValid = false;
                 }
             }
             const instansiDaerahInput = $('#instansi_daerah');
             if (instansiValue === 'Pemerintah Daerah' && !instansiDaerahInput.prop('disabled')) {
                 if (!instansiDaerahInput.val()) {
                     instansiDaerahInput.addClass('is-invalid');
                     isValid = false;
                 }
             }
        }

        // VALIDASI LANGKAH 3 DI-BYPASS SAAT PERPINDAHAN STEP UNTUK MENDUKUNG SIMPAN PARSIAL (DRAFT)
        if (step === 3) {
            isValid = true;
        }

        if (!isValid && !IS_FORM_DISABLED) {
             alert('Mohon lengkapi semua data yang wajib diisi.');
        }

        return isValid;
    }

    $('.next-step').on('click', function() {
        if (!IS_FORM_DISABLED) {
             if (validateStep(currentStep)) {
                const nextStep = parseInt($(this).data('next'));
                if (nextStep) updateStepDisplay(nextStep);
             }
        } else { 
             const nextStep = parseInt($(this).data('next'));
             if (nextStep) updateStepDisplay(nextStep);
        }
    });

    $('.prev-step').on('click', function() {
        const prevStep = parseInt($(this).data('prev'));
        if (prevStep) updateStepDisplay(prevStep);
    });

    function applyInstansiRule() {
        const isPemda = $('#instansi').val() === 'Pemerintah Daerah';
        const isKL = $('#instansi').val() === 'Kementerian/Lembaga';
        
        const unitOrganisasiInput = $('#unit_organisasi');
        const instansiDaerahInput = $('#instansi_daerah');
        
        $('#group-unit-organisasi').toggle(isKL); 
        $('#group-instansi-daerah').toggle(isPemda); 
        
        unitOrganisasiInput.prop('required', isKL);
        $('.required-star').toggle(isKL);

        if (IS_FORM_DISABLED) {
            unitOrganisasiInput.attr('readonly', true).prop('disabled', false); 
        } else {
            unitOrganisasiInput.prop('disabled', !isKL).attr('readonly', false);
            if (!isKL) unitOrganisasiInput.val('');
        }

        instansiDaerahInput.prop('required', isPemda);
        $('.required-star-daerah').toggle(isPemda);

        if (IS_FORM_DISABLED) {
            instansiDaerahInput.attr('readonly', true).prop('disabled', false); 
        } else {
             instansiDaerahInput.prop('disabled', !isPemda).attr('readonly', false);
             if (!isPemda) instansiDaerahInput.val('');
        }
    }

    $('#instansi').on('change', applyInstansiRule).trigger('change'); 
    updateStepDisplay(1);
    $('input:not([type="file"]), select').addClass('form-control');
});
</script>

<?php
if (isset($conn) && $conn) {
    mysqli_close($conn);
}
?>