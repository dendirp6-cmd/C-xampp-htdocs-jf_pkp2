<?php
// =========================================================
// 🎯 DEBUGGING KRITIS: Pastikan semua error terlihat
// =========================================================
error_reporting(E_ALL);
ini_set('display_errors', 1);

// FILE: detail_verif.php - Halaman Penilaian Persyaratan Uji Kompetensi (AdminLTE)

// --- AUTH GUARD DITAMBAHKAN DI SINI ---
require_once 'auth_guard.php'; 
// Memuat koneksi database.
require_once 'koneksi.php'; 

// --- PENGGUNAAN DATA SESSION DARI auth_guard.php ---
// Variabel ini didefinisikan dari require_once 'auth_guard.php';
$user_data = [
    'nama' => $user_nama_sesi ?? 'Pengguna JF', 
    'role' => $user_role_sesi ?? 'User', 
    'email' => $user_email_sesi ?? 'user@instansi.go.id', 
    'join_date' => $_SESSION['join_date'] ?? 'Maret 2023' 
];

// --- PENGATURAN ID KOLOM UTAMA & NAMA TABEL ---
$NAMA_KOLOM_ID_PENGAJUAN = "id"; 
$NAMA_TABEL_PENGAJUAN = "pengajuan_ujikom"; // Tabel yang menyimpan dokumen dan status

// --- LOKASI FOLDER UPLOAD SESUAI PERMINTAAN (URL Relatif dari root aplikasi) ---
// Lokasi file fisik: C:\xampp\htdocs\jf_pkp2\uploads\kenaikan
$BASE_URL_DOKUMEN = 'uploads/kenaikan/'; 

// --- VARIABEL HALAMAN ---
$page = 'ujikom'; 
$sub_page = 'daftar_ujikom'; 
$page_title = 'Verifikasi Peserta Uji Kompetensi';

$pengajuan_id = $_GET['id'] ?? null; 
$pegawai_data = null; // Akan diisi true jika data pengajuan ditemukan
$dokumen_paths = []; 
$error_message = '';
$message = '';

// 🎯 KOLOM KRITIS BARU: NAMA PEGAWAI diambil dari tabel pengajuan_ujikom
// PERBAIKAN: Menggunakan nama kolom 'nama' sesuai permintaan user.
$NAMA_KOLOM_NAMA_DB = "nama"; 
$nama_pegawai = 'N/A';
$nip_pegawai = 'N/A';
$verifikasi_data = []; 

// Data daftar persyaratan 
$persyaratan_umum = [
    'p1_pns' => 'Pegawai berstatus Pegawai Negeri Sipil (PNS)',
    'p2_pendidikan' => 'Pendidikan terakhir pegawai minimal S-1/D4',
    'p3_bidang' => 'Pendidikan sesuai dengan bidang yang dibutuhkan',
    'p4_pengalaman' => 'Pengalaman kerja bidang Perumahan minimal 2 tahun',
    'p5_usia' => 'Usia pegawai memenuhi batas maksimal<br>a. ≤53 tahun (Ahli Pertama/Muda)<br>b. ≤55 tahun (Ahli Madya)<br>c. ≤60 tahun (Ahli Utama)',
];

// Mapping dokumen persyaratan dengan NAMA KOLOM di tabel 'pengajuan_ujikom'
$dokumen_persyaratan = [
    'd1_surat_usulan'      => ['label' => 'Surat Usulan Kenaikan Jabatan ditandatangani pejabat tinggi pratama', 'kolom' => 'file_surat_usulan_perpindahan'],
    'd2_rekomendasi_formasi'=> ['label' => 'Dokumen Penetapan Kebutuhan JF', 'kolom' => 'file_dokumen_penetapan'],
    'd3_usulan_ujikom'      => ['label' => 'Surat Usulan Uji Kompetensi ditandatangani pejabat berwenang', 'kolom' => 'file_surat_usulan_uji'],
    'd4_portofolio'         => ['label' => 'Daftar Riwayat Hidup/Portofolio terbaru memuat pengalaman kerja relevan', 'kolom' => 'file_portofolio'],
    'd5_sk_cpns_pns'        => ['label' => 'Salinan SK CPNS dan SK PNS', 'kolom' => 'file_sk_cpns_pns'], 
    'd6_sk_pangkat'         => ['label' => 'SK Pangkat/Golongan terakhir', 'kolom' => 'file_sk_pangkat'],
    'd7_sk_jabatan'         => ['label' => 'SK Jabatan terakhir', 'kolom' => 'file_sk_jabatan'],
    'd8_nilai_skp'          => ['label' => 'Nilai SKP dua tahun terakhir minimal “Baik”', 'kolom' => 'file_skp'],
    'd9_ijazah_transkrip'   => ['label' => 'Salinan Ijazah dan Transkrip Nilai', 'kolom' => 'file_ijazah_transkrip'], 
    'd10_integritas'        => ['label' => 'Surat Pernyataan Integritas & Moralitas Baik', 'kolom' => 'file_pernyataan_integritas'],
    'd11_bersedia'          => ['label' => 'Surat Pernyataan Bersedia Diangkat dalam Jafung', 'kolom' => 'file_pernyataan_bersedia'],
    'd12_pengalaman_2th'    => ['label' => 'Surat Pernyataan Pengalaman Tugas minimal 2 tahun', 'kolom' => 'file_pernyataan_pengalaman'],
    'd13_rencana_penempatan'=> ['label' => 'Surat Keterangan Rencana Penempatan dari pejabat berwenang', 'kolom' => 'file_rencana_penempatan'],
];

if (!isset($conn)) {
    // Jika koneksi gagal didefinisikan di koneksi.php
    $error_message = "❌ Gagal terhubung ke database. Cek file koneksi.php.";
    $pengajuan_id = null;
}

if (!$pengajuan_id) {
    $error_message = $error_message ?: "❌ ID Pengajuan tidak ditemukan di URL.";
} else {
    $safe_id = mysqli_real_escape_string($conn, $pengajuan_id);
    
    // 1. AMBIL DATA PENGAJUAN (termasuk NIP, NAMA, dan path dokumen)
    // Query hanya ke tabel pengajuan_ujikom. Memastikan mengambil NIP dan NAMA
    $sql_pengajuan = "SELECT *, nip, {$NAMA_KOLOM_NAMA_DB} FROM {$NAMA_TABEL_PENGAJUAN} WHERE {$NAMA_KOLOM_ID_PENGAJUAN} = '{$safe_id}'";
    $result_pengajuan = mysqli_query($conn, $sql_pengajuan);

    if ($result_pengajuan === false) {
        // Tampilkan pesan error SQL yang sebenarnya jika query gagal
        $error_message = "❌ Error Query SQL: " . mysqli_error($conn) . ". Pastikan kolom `{$NAMA_KOLOM_NAMA_DB}` dan `nip` ada di tabel `{$NAMA_TABEL_PENGAJUAN}`.";
        $pegawai_data = false;
    } elseif (mysqli_num_rows($result_pengajuan) > 0) {
        $dokumen_paths = mysqli_fetch_assoc($result_pengajuan);

        // 🎯 Ambil Nama dan NIP langsung dari hasil query pengajuan
        $nama_pegawai = htmlspecialchars($dokumen_paths[$NAMA_KOLOM_NAMA_DB] ?? 'N/A (Nama tidak ada di tabel pengajuan)');
        $nip_pegawai = htmlspecialchars($dokumen_paths['nip'] ?? 'N/A');

        // Set flag agar formulir verifikasi tampil
        if ($nip_pegawai !== 'N/A') {
            $pegawai_data = true; 
        }

        // --- AMBIL STATUS VERIFIKASI YANG SUDAH ADA ---
        $verifikasi_data['catatan_evaluasi'] = htmlspecialchars($dokumen_paths['catatan_evaluasi'] ?? '');

        // Isi status verifikasi dari DB atau default ke 'Ya'
        $semua_keys = array_merge(array_keys($persyaratan_umum), array_keys($dokumen_persyaratan));
        foreach ($semua_keys as $key) { 
            // ASUMSI: Kolom status di DB bernama $key_status (misal: p1_pns_status)
            $db_column_status = $dokumen_paths[$key . '_status'] ?? null; 
            $verifikasi_data[$key] = $db_column_status ?: 'Ya'; 
        }

    } else {
        $error_message = "❌ Data pengajuan dengan ID: **{$pengajuan_id}** tidak ditemukan di tabel `{$NAMA_TABEL_PENGAJUAN}`.";
    }
}


// --- LOGIKA UPDATE/SIMPAN VERIFIKASI (Handle POST Request) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_verifikasi']) && $dokumen_paths) {
    
    $catatan = mysqli_real_escape_string($conn, $_POST['catatan_evaluasi'] ?? '');
    $all_passed = true; 
    $status_pengajuan = 'Ditolak'; 

    // Siapkan array untuk update query
    $update_fields = [
        "catatan_evaluasi = '{$catatan}'", 
    ];

    // Simpan status Ya/Tidak untuk setiap persyaratan/dokumen ke DB 
    $semua_keys_post = array_merge(array_keys($persyaratan_umum), array_keys($dokumen_persyaratan));
    foreach ($semua_keys_post as $key) {
        $status_value = mysqli_real_escape_string($conn, $_POST[$key] ?? 'Tidak');
        
        if ($status_value === 'Tidak') {
            $all_passed = false;
        }

        $update_fields[] = "{$key}_status = '{$status_value}'"; 
        $verifikasi_data[$key] = $_POST[$key] ?? 'Tidak';
    }
    
    if ($all_passed) {
        $final_status_text = 'Disetujui';
    } else {
        $final_status_text = 'Ditolak';
    }
    
    $update_fields[] = "status_pengajuan = '{$final_status_text}'"; 

    $sql_update = "UPDATE {$NAMA_TABEL_PENGAJUAN} SET " . implode(', ', $update_fields) . " WHERE id = '{$safe_id}'";
    
    if (mysqli_query($conn, $sql_update)) {
        // Ambil nama dari variabel yang sudah didefinisikan sebelumnya
        $nama_pegawai_saat_ini = $nama_pegawai;
        $message = "✅ Verifikasi untuk peserta: **{$nama_pegawai_saat_ini}** berhasil disimpan. Status akhir: **{$final_status_text}**.";
        $dokumen_paths['catatan_evaluasi'] = $catatan; 
    } else {
        $error_message = "❌ Gagal menyimpan verifikasi: " . mysqli_error($conn);
    }
}

// --- FUNGSI HELPER MENCARI NAMA FILE ---
function get_file_info($key, $dokumen_paths, $dokumen_persyaratan, $BASE_URL_DOKUMEN) {
    $info = $dokumen_persyaratan[$key] ?? null;
    if (!$info) return ['url' => '#', 'class' => 'btn-secondary disabled', 'text' => 'Mapping Error'];

    $kolom_db = $info['kolom'];
    $file_name = $dokumen_paths[$kolom_db] ?? '';
    
    if (!empty($file_name)) {
        return [
            // PENTING: Gunakan $BASE_URL_DOKUMEN yang sudah diperbarui
            'url' => $BASE_URL_DOKUMEN . urlencode($file_name), 
            'class' => 'btn-success',
            'text' => 'Buka Dokumen'
        ];
    } else {
        return [
            'url' => '#',
            'class' => 'btn-secondary disabled',
            'text' => 'Tidak Ada File'
        ];
    }
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $page_title; ?> | AdminLTE</title>

    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <style>
        /* Styling tambahan agar tabel detail tidak terlalu lebar */
        .detail-table td:first-child { width: 70%; }
        .detail-table td:nth-child(2) { width: 150px; text-align: center; }
        .detail-table td:nth-child(3) { width: 100px; text-align: center; }
        
        /* Tambahan styling untuk Brand/Logo */
        .brand-link { background-color: #111827; }
        .brand-link .logo-pupr i { color: #0f62fe; }
        .logo-pupr { background: #fff; padding: 4px; width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; border-radius: 50%;}
    </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">

    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
            </li>
        </ul>
        <ul class="navbar-nav ml-auto">
             <li class="nav-item dropdown user-menu">
                <a href="#" class="nav-link dropdown-toggle" data-toggle="dropdown">
                    <img src="https://i.pravatar.cc/36?u=<?php echo urlencode($user_data['email']); ?>" class="user-image img-circle elevation-2" alt="User Image">
                    <span class="d-none d-md-inline"><?php echo htmlspecialchars($user_data['nama']); ?></span>
                </a>
                <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                    <li class="user-header bg-primary">
                        <img src="https://i.pravatar.cc/90?u=<?php echo urlencode($user_data['email']); ?>" class="img-circle elevation-2" alt="User Image">
                        <p>
                            <?php echo htmlspecialchars($user_data['nama']); ?> - <?php echo htmlspecialchars($user_data['role']); ?>
                            <small>Member since <?php echo htmlspecialchars($user_data['join_date']); ?></small>
                        </p>
                    </li>
                    <li class="user-footer">
                        <a href="pengaturan.php" class="btn btn-default btn-flat">
                            <i class="fas fa-cog"></i> Pengaturan
                        </a>
                        <a href="logout.php" class="btn btn-default btn-flat float-right">
                            <i class="fas fa-sign-out-alt"></i> Sign out
                        </a>
                    </li>
                </ul>
            </li>
        </ul>
    </nav>
    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <?php 
        // File ini diasumsikan ada di folder yang sama atau subfolder 'template'
        include 'template/sidebar.php'; 
        ?>
    </aside>
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0"><?php echo $page_title; ?></h1>
                        <span class="text-muted">Penilaian Persyaratan dan Dokumen</span>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="ujikom.php">Uji Kompetensi</a></li>
                            <li class="breadcrumb-item active">Verifikasi</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        <section class="content">
            <div class="container-fluid">
                
                <?php 
                // Tampilkan pesan error atau sukses
                if ($error_message) {
                    echo "<div class='alert alert-danger alert-dismissible'><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button><i class='icon fas fa-ban'></i> " . $error_message . "</div>";
                }
                if ($message) {
                    echo "<div class='alert alert-success alert-dismissible'><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button><i class='icon fas fa-check'></i> " . $message . "</div>";
                }
                
                // Tampilkan formulir hanya jika data dokumen ditemukan (pegawai_data = true)
                if ($dokumen_paths && $pegawai_data):
                ?>

                <div class="card card-primary card-outline">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-user-tag"></i> Data Peserta</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="detail_verif.php?id=<?php echo htmlspecialchars($pengajuan_id); ?>">
                            <div class="form-group row">
                                <label for="nama_pegawai" class="col-sm-2 col-form-label">Nama</label>
                                <div class="col-sm-4">
                                    <input type="text" class="form-control" value="<?php echo $nama_pegawai; ?>" readonly>
                                </div>
                                <label for="nip_pegawai" class="col-sm-2 col-form-label">NIP</label>
                                <div class="col-sm-4">
                                    <input type="text" class="form-control" value="<?php echo $nip_pegawai; ?>" readonly>
                                </div>
                            </div>

                            <div class="row mt-4">
                                <div class="col-12">
                                    <h4><i class="fas fa-check-circle text-primary"></i> A. Persyaratan Umum</h4>
                                    <div class="float-right mb-2">
                                        <button type="submit" name="submit_verifikasi" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Verifikasi</button>
                                    </div>
                                    <table class="table table-bordered detail-table">
                                        <thead>
                                            <tr class="bg-light">
                                                <th>Persyaratan</th>
                                                <th class="text-center">Dokumen Rujukan</th>
                                                <th class="text-center">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php $no = 1; foreach ($persyaratan_umum as $key => $label): 
                                                // Asumsi dokumen rujukan untuk Persyaratan Umum diambil dari 'd3_usulan_ujikom'
                                                $dok_key_rujukan = 'd3_usulan_ujikom'; 
                                                $file_info_rujukan = get_file_info($dok_key_rujukan, $dokumen_paths, $dokumen_persyaratan, $BASE_URL_DOKUMEN);
                                            ?>
                                            <tr>
                                                <td><?php echo $no++; ?>. <?php echo $label; ?></td>
                                                <td>
                                                     <a href="<?php echo htmlspecialchars($file_info_rujukan['url']); ?>" target="_blank" class="btn btn-sm <?php echo $file_info_rujukan['class']; ?>" <?php echo ($file_info_rujukan['url'] == '#') ? 'onclick="return false;"' : ''; ?> title="Buka Dokumen Usulan Ujikom">
                                                         <i class="fas fa-file-alt"></i> <?php echo $file_info_rujukan['text']; ?>
                                                     </a>
                                                </td>
                                                <td>
                                                     <select name="<?php echo $key; ?>" class="form-control form-control-sm">
                                                          <option value="Ya" <?php echo (($verifikasi_data[$key] ?? '') == 'Ya' ? 'selected' : ''); ?>>Ya</option>
                                                          <option value="Tidak" <?php echo (($verifikasi_data[$key] ?? '') == 'Tidak' ? 'selected' : ''); ?>>Tidak</option>
                                                       </select>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="row mt-4">
                                <div class="col-12">
                                    <h4><i class="fas fa-file-upload text-primary"></i> B. Dokumen Persyaratan</h4>
                                    <div class="float-right mb-2">
                                        <button type="submit" name="submit_verifikasi" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Verifikasi</button>
                                    </div>
                                    <table class="table table-bordered detail-table">
                                        <thead>
                                            <tr class="bg-light">
                                                <th>Dokumen</th>
                                                <th class="text-center">File</th>
                                                <th class="text-center">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php $no = 1; foreach ($dokumen_persyaratan as $key => $info): 
                                                $file_info = get_file_info($key, $dokumen_paths, $dokumen_persyaratan, $BASE_URL_DOKUMEN);
                                            ?>
                                            <tr>
                                                <td><?php echo $no++; ?>. <?php echo htmlspecialchars($info['label']); ?></td>
                                                <td>
                                                     <a href="<?php echo htmlspecialchars($file_info['url']); ?>" target="_blank" class="btn btn-sm <?php echo $file_info['class']; ?>" <?php echo ($file_info['url'] == '#') ? 'onclick="return false;"' : ''; ?>>
                                                         <i class="fas fa-file-alt"></i> <?php echo $file_info['text']; ?>
                                                     </a>
                                                </td>
                                                <td>
                                                     <select name="<?php echo $key; ?>" class="form-control form-control-sm">
                                                          <option value="Ya" <?php echo (($verifikasi_data[$key] ?? '') == 'Ya' ? 'selected' : ''); ?>>Ya</option>
                                                          <option value="Tidak" <?php echo (($verifikasi_data[$key] ?? '') == 'Tidak' ? 'selected' : ''); ?>>Tidak</option>
                                                       </select>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="row mt-4">
                                <div class="col-12">
                                    <h4><i class="fas fa-comments text-primary"></i> C. Catatan Evaluasi</h4>
                                    <div class="form-group">
                                        <label for="catatan-evaluasi">Tambahkan Catatan Evaluasi</label>
                                        <textarea id="catatan-evaluasi" name="catatan_evaluasi" rows="4" class="form-control" placeholder="Masukkan catatan evaluasi di sini..."><?php echo htmlspecialchars($dokumen_paths['catatan_evaluasi'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                            </div>

                            <div class="row mt-3">
                                <div class="col-12">
                                    <button type="submit" name="submit_verifikasi" class="btn btn-lg btn-block btn-info"><i class="fas fa-share-square"></i> Selesaikan Verifikasi dan Simpan</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <?php else: ?>
                    <div class="card">
                        <div class="card-body">
                            <p class="lead">
                                <?php 
                                    // Tampilkan pesan error spesifik jika ada
                                    if ($error_message) {
                                        echo $error_message . "<br>Periksa juga apakah Anda sudah mengakses halaman dengan parameter ID: `detail_verif.php?id=...`";
                                    } else {
                                        echo "Data pengajuan peserta tidak dapat dimuat atau ID tidak ditemukan. Periksa koneksi database dan URL Anda.";
                                    }
                                ?>
                            </p>
                            <a href="ujikom.php" class="btn btn-warning"><i class="fas fa-arrow-left"></i> Kembali ke Daftar Uji Kompetensi</a>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </section>
        </div>
    <footer class="main-footer">
        <div class="float-right d-none d-sm-inline">
            Version 3.2
        </div>
        <strong>© <?php echo date('Y'); ?> Instansi Pembina Jabatan Fungsional Penata Kelola Perumahan</strong> — Semua Hak Dilindungi.
    </footer>
</div>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>

</body>
</html>
<?php 
// Tutup koneksi database
if (isset($conn)) {
    mysqli_close($conn); 
}
?>