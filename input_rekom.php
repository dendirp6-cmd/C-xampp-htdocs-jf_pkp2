<?php
// FILE: input_rekom.php - Formulir Pengajuan Rekomendasi Formasi Jabatan Fungsional
// UPDATE: Sticky NIP dari session/post, Sticky File Label pada kegagalan, Sanitasi pesan sukses.
// PERBAIKAN: Logika JavaScript untuk Sticky Provinsi/Kota Kabupaten yang lebih andal (tanpa setTimeout).

// --- PENGATURAN DEBUGGING (OPSIONAL) ---
// ini_set('display_errors', 1);
// error_reporting(E_ALL);

// 1. --- PENGATURAN SESSION & AUTORISASI ---
// Pastikan file ini ada
require_once 'auth_guard.php'; 
require_once 'koneksi.php'; 

// Cek koneksi untuk mencegah error
if (!isset($conn) || !$conn) {
    // 💥 PERBAIKAN: Karakter tak terlihat dihilangkan dari baris ini
    die("Fatal Error: Koneksi database tidak tersedia. Mohon cek koneksi.php");
}

// 2. --- PENGATURAN VARIABEL HALAMAN UNTUK SIDEBAR & HEADER ---
$page = 'rekomendasi'; 
$sub_page = 'input_rekom'; 
$page_title = 'Input Data Rekomendasi Formasi'; 

// Ambil data NIP dari session untuk Sticky Form default
$default_nip_from_session = $_SESSION['user_nip_sesi'] ?? $_SESSION['user_nip'] ?? '';

// Asumsi variabel user dari auth_guard.php sudah tersedia
$user_data = [
    'nama' => $_SESSION['user_nama_sesi'] ?? 'Pengguna JF', 
    'role' => $_SESSION['user_role_sesi'] ?? 'User', 
    'email' => $_SESSION['user_email_sesi'] ?? 'email@example.com',
    'join_date' => $_SESSION['join_date'] ?? '2023'
];

// Inisialisasi pesan feedback
$submit_message = '';
$is_success = false; 
$uploaded_file_names = []; // Array untuk menyimpan nama file (untuk sticky form jika gagal submit)

// 3. --- DAFTAR FILE & INFO PENDUKUNG (BLOK BERSIH) ---
$required_files_config = [
    'file_usulan_formasi' => ['label' => '1. Dokumen Usulan Formasi JF', 'required' => true],
    'file_tupoksi'          => ['label' => '2. Tupoksi Unit Kerja', 'required' => true],
    'file_abk'              => ['label' => '3. Hasil Analisis Beban Kerja (ABK)', 'required' => true],
    'file_struktur'         => ['label' => '4. Struktur Organisasi Unit Kerja', 'required' => true],
    'file_peta_jabatan'     => ['label' => '5. Peta Jabatan Unit Kerja', 'required' => true],
    'file_sk_kelola'        => ['label' => '6. SK Tim Pengelola JF (Opsional)', 'required' => false],
];

// Fungsi helper untuk menghindari undefined index saat form gagal (Sticky Form)
function get_post_value($key, $default = '') {
    $value = filter_input(INPUT_POST, $key, FILTER_DEFAULT); 
    return $value !== null ? trim(htmlspecialchars($value, ENT_QUOTES, 'UTF-8')) : $default;
}

// --- LOGIKA PEMROSESAN FORM SUBMISSION ---
if (isset($_POST['submit_pendaftaran'])) {
    
    // 4. --- SANITASI INPUT TEKS & VALIDASI DASAR ---
    // Data Pengusul
    $nama = get_post_value('nama_pengusul');
    $nip = get_post_value('nip');
    $jabatan = get_post_value('jabatan');
    $hp = get_post_value('no_hp');
    $instansi = get_post_value('instansi');
    $provinsi = get_post_value('provinsi');
    $kota_kab = get_post_value('kota_kab');
    
    // DATA BARU (DETAIL FORMASI)
    $nama_jabatan_jf = get_post_value('nama_jabatan_jf');
    $jenjang_jf = get_post_value('jenjang_jf');
    $kebutuhan_formasi = (int)get_post_value('kebutuhan_formasi', 0); 
    $dasar_hukum = get_post_value('dasar_hukum');

    // Status default untuk pengajuan baru
    $status_default = 'Menunggu Verifikasi';
    $target_dir = "uploads/rekomendasi/";

    // Validasi dasar untuk field wajib
    if (empty($nama) || empty($nip) || empty($jabatan) || empty($hp) || empty($instansi) || empty($provinsi) || empty($kota_kab) || 
        empty($nama_jabatan_jf) || empty($jenjang_jf) || empty($dasar_hukum) || $kebutuhan_formasi <= 0) {
        $submit_message = "❌ Gagal: Semua kolom wajib diisi dengan benar, termasuk Detail Formasi (Kebutuhan Formasi harus > 0).";
        goto end_submission;
    }
    
    // Pastikan folder ada
    if (!is_dir($target_dir)) {
        if (!@mkdir($target_dir, 0777, true)) {
            $submit_message = "❌ Fatal Error: Gagal membuat folder upload '{$target_dir}'. Cek izin folder server.";
            goto end_submission;
        }
    }
        
    $file_paths = []; 
    $upload_errors = false;

    // 5. --- VALIDASI DAN UPLOAD FILE ---
    foreach ($required_files_config as $field_name => $info) {
        
        $display_name = $info['label'];
        $is_required = $info['required'];

        if (!isset($_FILES[$field_name]) || $_FILES[$field_name]['error'] == UPLOAD_ERR_NO_FILE) {
            if ($is_required) {
                $submit_message = "❌ Gagal: File **{$display_name}** wajib diunggah.";
                $upload_errors = true;
                break;
            } else {
                $file_paths[$field_name] = ''; 
                continue; 
            }
        }

        $file_error = $_FILES[$field_name]['error'];
        $file_name = $_FILES[$field_name]['name'];
        $file_tmp = $_FILES[$field_name]['tmp_name'];
        $file_size = $_FILES[$field_name]['size'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        if ($file_error !== UPLOAD_ERR_OK) {
            $submit_message = "❌ Gagal upload file {$display_name}. Kode Error: {$file_error}.";
            $upload_errors = true;
            break;
        }

        if ($file_ext != "pdf") {
            $submit_message = "❌ Gagal: File **{$display_name}** harus dalam format PDF.";
            $upload_errors = true;
            break;
        }
        
        if ($file_size > 5000000) { 
            $submit_message = "❌ Gagal: Ukuran file **{$display_name}** maksimal 5 MB.";
            $upload_errors = true;
            break;
        }

        // Generasi nama file unik
        $new_file_name = $field_name . "_" . $nip . "_" . uniqid() . "." . $file_ext;
        $target_file = $target_dir . $new_file_name;
        
        if (move_uploaded_file($file_tmp, $target_file)) {
            // Hanya simpan path relatif yang akan masuk ke database
            $file_paths[$field_name] = $target_file; 
            // Simpan nama file original untuk sticky form jika gagal DB
            $uploaded_file_names[$field_name] = htmlspecialchars($file_name); 
        } else {
            // Hapus file yang mungkin sebagian terupload
            if (file_exists($target_file)) @unlink($target_file);
            $submit_message = "❌ Gagal memindahkan file {$display_name} ke server. Cek izin folder 'uploads/rekomendasi/'.";
            $upload_errors = true;
            break;
        }
    }
    
    // Jika ada error upload, loncat ke akhir
    if ($upload_errors) {
        goto end_submission;
    }

    // 6. --- PENYIMPANAN KE DATABASE ---
    
    // 6.1. Ambil path file
    $file_usulan = $file_paths['file_usulan_formasi'] ?? '';
    $file_tupoksi = $file_paths['file_tupoksi'] ?? '';
    $file_abk = $file_paths['file_abk'] ?? '';
    $file_struktur = $file_paths['file_struktur'] ?? '';
    $file_peta_jabatan = $file_paths['file_peta_jabatan'] ?? '';
    $file_sk_kelola = $file_paths['file_sk_kelola'] ?? ''; 
    
    // Ambil tanggal pengajuan saat ini (sebagai string untuk di-bind)
    $tanggal_pengajuan = date('Y-m-d H:i:s'); // Format datetime MySQL

    // 6.2. Tentukan Query SQL (TOTAL 19 Kolom)
    $sql = "INSERT INTO rekomendasi_formasi (
                tanggal_pengajuan, 
                nama_pengusul, nip, jabatan, no_hp, instansi, provinsi, kota_kab, 
                file_usulan_formasi, file_tupoksi, file_abk, file_struktur, file_peta_jabatan, file_sk_kelola, 
                status, 
                nama_jabatan_jf, jenjang_jf, kebutuhan_formasi, dasar_hukum 
              ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"; 

    // 6.3. Persiapkan statement
    if ($stmt = $conn->prepare($sql)) {
        
        // 6.4. Variabel untuk Binding Parameters (TOTAL 19 Variabel)
        $params = [
            $tanggal_pengajuan, // 1 (s)
            
            $nama, // 2 (s)
            $nip, // 3 (s)
            $jabatan, // 4 (s)
            $hp, // 5 (s)
            $instansi, // 6 (s)
            $provinsi, // 7 (s)
            $kota_kab, // 8 (s)
            
            $file_usulan, // 9 (s)
            $file_tupoksi, // 10 (s)
            $file_abk, // 11 (s)
            $file_struktur, // 12 (s)
            $file_peta_jabatan, // 13 (s)
            $file_sk_kelola, // 14 (s)
            
            $status_default, // 15 (s)

            $nama_jabatan_jf, // 16 (s)
            $jenjang_jf, // 17 (s)
            $kebutuhan_formasi, // 18 (i) (integer)
            $dasar_hukum // 19 (s)
        ];

        // 6.5. Tipe Data: 17 's' + 1 'i' + 1 's' = sssssssssssssssssis (Kebutuhan Formasi adalah integer)
        $types = "sssssssssssssssssis"; 
        
        // Menggunakan bind_param secara langsung 
        if (!$stmt->bind_param($types, ...$params)) {
             // Cleanup file jika gagal bind
             foreach ($file_paths as $file_path_db) {
                 if (!empty($file_path_db) && file_exists($file_path_db)) {
                     @unlink($file_path_db);
                 }
             }
             $submit_message = "❌ Gagal mengikat parameter: Cek tipe data dan jumlah variabel. Tipe seharusnya: " . $types . ". Error: " . $stmt->error;
             goto end_submission;
        }
        
        // 6.6. Eksekusi statement
        if ($stmt->execute()) {
            $submit_message = "✅ **Berhasil!** Data Rekomendasi Formasi Anda telah berhasil diajukan dan sedang menunggu verifikasi.";
            $is_success = true; 
            
            // Kosongkan variabel POST agar form kembali kosong setelah sukses
            $_POST = []; 
            $uploaded_file_names = []; // Kosongkan daftar file
        } else {
            $error_db = $stmt->error;
            
            if (strpos($error_db, 'Duplicate entry') !== false && strpos($error_db, "'nip'") !== false) {
                $submit_message = "❌ Gagal: NIP Anda ($nip) sudah pernah mengajukan rekomendasi formasi. Silakan cek status pengajuan sebelumnya.";
            } else {
                $submit_message = "❌ Gagal menyimpan data ke database. Error: " . htmlspecialchars($error_db);
            }
            // Hapus file yang sudah terlanjur di-upload jika terjadi kegagalan DB
            foreach ($file_paths as $file_path_db) {
                if (!empty($file_path_db) && file_exists($file_path_db)) {
                    @unlink($file_path_db);
                }
            }
        }
        $stmt->close();
    } else {
        $submit_message = "❌ Gagal mempersiapkan statement SQL: " . htmlspecialchars($conn->error);
    }
}

// Label untuk lompatan GOTO
end_submission: 
// Penutupan koneksi dipindahkan ke bagian footer
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AdminLTE 3 | <?php echo $page_title; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .card-header {
            background-color: #007bff;
            color: white;
        }
        .custom-file-label {
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
        }
        .custom-file-label::after {
            content: "Pilih File";
        }
    </style>
</head>
<body class="hold-transition sidebar-mini">
    <div class="wrapper">
        <?php 
        // Pastikan file ini ada
        require_once 'template/navbar.php'; 
        require_once 'template/sidebar.php'; 
        ?>

        <div class="content-wrapper">
            <div class="content-header">
                <div class="container-fluid">
                    <div class="row mb-2">
                        <div class="col-sm-6">
                            <h1 class="m-0"><?php echo $page_title; ?></h1>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-right">
                                <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
                                <li class="breadcrumb-item active"><?php echo $page_title; ?></li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <div class="content">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-md-10 offset-md-1">
                            
                            <?php if ($submit_message): // Tampilkan Alert untuk sukses atau gagal ?>
                            <div class="alert alert-<?php echo $is_success ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                                <?php echo $submit_message; ?>
                                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <?php endif; ?>

                            <div class="card card-primary card-outline">
                                <div class="card-header">
                                    <h3 class="card-title">Formulir Pengajuan</h3>
                                </div>
                                <form id="rekomform" action="input_rekom.php" method="POST" enctype="multipart/form-data">
                                    <div class="card-body">
                                        
                                        <h5 class="mb-3 text-bold text-primary"><i class="fas fa-user-circle"></i> Data Pengusul/Instansi</h5>
                                        <div class="row">
                                            <div class="form-group col-md-6">
                                                <label for="nama_pengusul">Nama Pengusul</label>
                                                <input type="text" class="form-control" id="nama_pengusul" name="nama_pengusul" value="<?php echo get_post_value('nama_pengusul', $user_data['nama']); ?>" required>
                                            </div>
                                            <div class="form-group col-md-6">
                                                <label for="nip">NIP (Nomor Induk Pegawai)</label>
                                                <input type="text" class="form-control" id="nip" name="nip" value="<?php echo get_post_value('nip', $default_nip_from_session); ?>" required pattern="\d{18}" title="NIP harus 18 digit angka." maxlength="18">
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="form-group col-md-6">
                                                <label for="jabatan">Jabatan Pengusul</label>
                                                <input type="text" class="form-control" id="jabatan" name="jabatan" value="<?php echo get_post_value('jabatan'); ?>" required>
                                            </div>
                                            <div class="form-group col-md-6">
                                                <label for="no_hp">Nomor HP</label>
                                                <input type="text" class="form-control" id="no_hp" name="no_hp" value="<?php echo get_post_value('no_hp'); ?>" required pattern="\d+" title="Nomor HP hanya boleh angka.">
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label for="instansi">Nama Instansi</label>
                                            <input type="text" class="form-control" id="instansi" name="instansi" value="<?php echo get_post_value('instansi'); ?>" required>
                                        </div>
                                        <div class="row">
                                            <div class="form-group col-md-6">
                                                <label for="provinsi">Provinsi</label>
                                                <select class="form-control" id="provinsi" name="provinsi" required>
                                                    <option value="">-- Pilih Provinsi --</option>
                                                </select>
                                            </div>
                                            <div class="form-group col-md-6">
                                                <label for="kota_kab">Kota/Kabupaten</label>
                                                <select class="form-control" id="kota_kab" name="kota_kab" required disabled>
                                                    <option value="">-- Pilih Kota/Kabupaten --</option>
                                                </select>
                                            </div>
                                        </div>
                                        
                                        <hr class="my-4">

                                        <h5 class="mb-3 text-bold text-info"><i class="fas fa-briefcase"></i> Detail Jabatan Fungsional</h5>
                                        <div class="form-group">
                                            <label for="nama_jabatan_jf">Nama Jabatan Fungsional</label>
                                            <input type="text" class="form-control" id="nama_jabatan_jf" name="nama_jabatan_jf" value="<?php echo get_post_value('nama_jabatan_jf'); ?>" placeholder="Contoh: Analis Kebijakan Ahli Muda" required>
                                        </div>
                                        <div class="row">
                                            <div class="form-group col-md-6">
                                                <label for="jenjang_jf">Jenjang Jabatan yang Dituju</label>
                                                <select class="form-control" id="jenjang_jf" name="jenjang_jf" required>
                                                    <option value="">-- Pilih Jenjang --</option>
                                                    <option value="Pemula" <?php echo get_post_value('jenjang_jf') == 'Pemula' ? 'selected' : ''; ?>>Pemula</option>
                                                    <option value="Terampil" <?php echo get_post_value('jenjang_jf') == 'Terampil' ? 'selected' : ''; ?>>Terampil</option>
                                                    <option value="Mahir" <?php echo get_post_value('jenjang_jf') == 'Mahir' ? 'selected' : ''; ?>>Mahir</option>
                                                    <option value="Penyelia" <?php echo get_post_value('jenjang_jf') == 'Penyelia' ? 'selected' : ''; ?>>Penyelia</option>
                                                    <option value="Ahli Pertama" <?php echo get_post_value('jenjang_jf') == 'Ahli Pertama' ? 'selected' : ''; ?>>Ahli Pertama</option>
                                                    <option value="Ahli Muda" <?php echo get_post_value('jenjang_jf') == 'Ahli Muda' ? 'selected' : ''; ?>>Ahli Muda</option>
                                                    <option value="Ahli Madya" <?php echo get_post_value('jenjang_jf') == 'Ahli Madya' ? 'selected' : ''; ?>>Ahli Madya</option>
                                                    <option value="Ahli Utama" <?php echo get_post_value('jenjang_jf') == 'Ahli Utama' ? 'selected' : ''; ?>>Ahli Utama</option>
                                                </select>
                                            </div>
                                            <div class="form-group col-md-6">
                                                <label for="kebutuhan_formasi">Kebutuhan Formasi (Orang)</label>
                                                <input type="number" class="form-control" id="kebutuhan_formasi" name="kebutuhan_formasi" value="<?php echo get_post_value('kebutuhan_formasi', 1); ?>" min="1" required>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label for="dasar_hukum">Dasar Hukum Pembentukan JF di Instansi Anda</label>
                                            <textarea class="form-control" id="dasar_hukum" name="dasar_hukum" rows="3" required placeholder="Contoh: Peraturan Menteri No. 12 Tahun 2024 tentang Jabatan Fungsional..."><?php echo get_post_value('dasar_hukum'); ?></textarea>
                                        </div>

                                        <hr class="my-4">

                                        <h5 class="mb-3 text-bold text-success"><i class="fas fa-upload"></i> Upload Dokumen Pendukung (Hanya PDF, Max 5MB)</h5>
                                        
                                        <?php 
                                        foreach ($required_files_config as $field_name => $info):
                                        ?>
                                        <div class="form-group">
                                            <label for="<?php echo $field_name; ?>"><?php echo $info['label']; ?> <?php echo $info['required'] ? '<span class="text-danger">*</span>' : ''; ?></label>
                                            <div class="input-group">
                                                <div class="custom-file">
                                                    <input type="file" class="custom-file-input" id="<?php echo $field_name; ?>" name="<?php echo $field_name; ?>" accept="application/pdf" <?php echo $info['required'] ? 'required' : ''; ?>>
                                                    <label class="custom-file-label" for="<?php echo $field_name; ?>" data-browse="Telusuri" data-initial-label="Pilih file PDF..."></label>
                                                </div>
                                            </div>
                                            <small class="form-text text-muted">Maksimal 5 MB, Format: PDF.</small>
                                        </div>
                                        <?php endforeach; ?>
                                        
                                    </div>
                                    <div class="card-footer">
                                        <button type="submit" name="submit_pendaftaran" class="btn btn-primary btn-block">
                                            <i class="fas fa-paper-plane"></i> Ajukan Rekomendasi
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php 
        require_once 'template/footer.php'; 
        ?>
    </div>
    <script src="js/jquery.min.js"></script>
<script src="js/bootstrap.bundle.min.js"></script>
<script src="js/adminlte.min.js"></script>

<script>
    // 8. --- LOGIKA JAVASCRIPT ---
    
    // Data Wilayah (Contoh Sederhana, bisa diganti dengan fetch dari API jika tersedia)
    const dataWilayah = {
        "DKI Jakarta": ["Jakarta Pusat", "Jakarta Timur", "Jakarta Selatan", "Jakarta Barat", "Jakarta Utara", "Kepulauan Seribu"],
        "Jawa Barat": ["Bandung", "Bekasi", "Bogor", "Depok", "Cirebon", "Sukabumi", "Garut", "Karawang", "Purwakarta"],
        "Jawa Tengah": ["Semarang", "Surakarta", "Magelang", "Pekalongan", "Tegal"],
        "Jawa Timur": ["Surabaya", "Malang", "Kediri", "Madiun", "Banyuwangi"]
    };

    const provinsiSelect = document.getElementById('provinsi');
    const kotaSelect = document.getElementById('kota_kab');
    // Ambil daftar file yang di-upload saat submit gagal (untuk sticky form)
    const submittedFiles = <?php echo json_encode($uploaded_file_names); ?>; 

    /**
     * Mengisi dropdown Kota/Kabupaten berdasarkan Provinsi yang dipilih dan 
     * memilih Kota/Kabupaten yang 'sticky' jika ada.
     * @param {string} prov Nama Provinsi yang dipilih.
     * @param {string} selectedKota Nilai Kota/Kabupaten yang harus dipilih (untuk sticky form).
     */
    function populateKotaKab(prov, selectedKota = '') {
        // Kosongkan dan reset dropdown Kota/Kab
        kotaSelect.innerHTML = '<option value="">-- Pilih Kota/Kabupaten --</option>';
        kotaSelect.disabled = true;

        if (prov && dataWilayah[prov]) {
            // Mengurutkan dan mengisi opsi Kota/Kabupaten
            dataWilayah[prov].sort().forEach(kab => {
                const option = document.createElement('option');
                option.value = kab;
                option.textContent = kab;
                // Tetapkan nilai sticky
                if (kab === selectedKota) {
                    option.selected = true; 
                }
                kotaSelect.appendChild(option);
            });
            kotaSelect.disabled = false;
        }
    }


    // Isi Dropdown Provinsi saat halaman dimuat dan set nilai sticky
    Object.keys(dataWilayah).sort().forEach(prov => {
        const option = document.createElement('option');
        option.value = prov;
        option.textContent = prov;
        // Penanganan nilai sticky untuk Provinsi
        const selectedProvinsi = "<?php echo get_post_value('provinsi'); ?>";
        if (prov === selectedProvinsi) {
            option.selected = true;
        }
        provinsiSelect.appendChild(option);
    });

    // Event listener untuk perubahan Provinsi (akan memanggil fungsi helper)
    provinsiSelect.addEventListener('change', function() {
        // Saat berubah manual, tidak perlu ada selectedKota
        populateKotaKab(this.value);
    });
    
    // JQuery untuk Sticky Form dan Tampilan File Upload
    $(document).ready(function() {
        const selectedProvinsi = "<?php echo get_post_value('provinsi'); ?>";
        const selectedKota = "<?php echo get_post_value('kota_kab'); ?>";
        
        // 1. Sticky Provinsi dan Kota/Kab (Dipanggil saat load jika ada nilai post)
        if (selectedProvinsi) {
            // Jika Provinsi sudah dipilih, muat dan pilih Kota/Kabupaten
            populateKotaKab(selectedProvinsi, selectedKota);
        }
        
        // 2. Mengubah label custom file input
        $('.custom-file-input').on('change', function() {
            // Mengambil nama file dari path
            let fileName = $(this).val().split('\\').pop(); 
            // Mengubah teks label menjadi nama file
            $(this).next('.custom-file-label').html(fileName);
        });
        
        // 2.1. Sticky File Input Label (Jika gagal submit, tapi file sudah dipilih)
        $.each(submittedFiles, function(fieldName, fileName) {
            if (fileName) {
                // Periksa jika file yang baru tidak di-upload (label masih default)
                let currentLabel = $('#' + fieldName).next('.custom-file-label').text().trim();
                if (currentLabel === 'Pilih file PDF...') { // Asumsi label default
                    $('#' + fieldName).next('.custom-file-label').html(fileName);
                }
            }
        });


        // 3. Tambahkan validasi NIP dan HP (hanya angka) dan Kebutuhan Formasi
        $('#nip, #no_hp, #kebutuhan_formasi').on('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
        });

        // 4. LOGIKA REDIRECT SETELAH SUKSES 
        const isSuccess = <?php echo $is_success ? 'true' : 'false'; ?>;
        // Sanitasi pesan untuk dimasukkan ke alert JS
        const successMessage = "<?php echo str_replace(["\r", "\n"], ['\r', '\n'], $submit_message); ?>"; 

        if (isSuccess) {
            // Tunda sedikit agar semua skrip dimuat dan alert muncul
            setTimeout(function() {
                alert(successMessage + "\n\nAnda akan diarahkan ke daftar pengajuan.");
                // REDIRECT KE HALAMAN LIST REKOMENDASI PENGUSUL
                window.location.href = 'list_rekom_pengusul.php'; 
            }, 100); 
        }
    });
</script> 

    <?php
    // Tutup koneksi database
    if (isset($conn) && $conn) {
        mysqli_close($conn);
    }
    ?>
    </body>
    </html>