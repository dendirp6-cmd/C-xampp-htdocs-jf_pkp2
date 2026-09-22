<?php
// FILE: tambah_pegawai.php - Menampilkan formulir dan memproses INSERT data Pegawai

// --- Memuat file penting ---
require_once 'koneksi.php'; 
require_once 'auth_guard.php'; 

$NAMA_TABEL_PEGAWAI = "detailpegawai"; 

// --- PENGATURAN JUDUL HALAMAN & MENU ---
$page = 'database'; 
$sub_page = 'tambah_pegawai'; 
$page_title = 'Tambah Data Pegawai JF'; 

$message = '';
$is_error = false;
$input_values = []; // Untuk menyimpan nilai input jika terjadi error (sticky form)

// =========================================================
// LOGIKA PEMROSESAN FORMULIR (INSERT)
// =========================================================
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // 1. Ambil dan Sanitasi Data LENGKAP dari Formulir
    // Field yang sudah ada
    $nip = trim($_POST['nip'] ?? '');
    $nama = trim($_POST['nama'] ?? ''); // Nama di tabel adalah 'nama'
    $jabatan = trim($_POST['jabatan'] ?? '');
    $jenjang = trim($_POST['jenjang'] ?? '');
    $nama_instansi = trim($_POST['nama_instansi'] ?? '');
    $jenis_instansi = trim($_POST['jenis_instansi'] ?? '');
    $provinsi = trim($_POST['provinsi'] ?? '');
    $kabupaten_kota = trim($_POST['kabupaten_kota'] ?? '');
    
    // FIELD BARU YANG DITAMBAHKAN
    $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
    $unit_kerja = trim($_POST['unit_kerja'] ?? '');
    $instansi_asal = trim($_POST['instansi_asal'] ?? '');
    $golongan = trim($_POST['golongan'] ?? '');
    $jabatan_lama = trim($_POST['jabatan_lama'] ?? '');

    
    // Simpan nilai input untuk 'sticky form'
    $input_values = $_POST;
    
    // 2. Validasi Minimal
    if (empty($nip) || empty($nama) || empty($jabatan)) {
        $message = "Nama Pegawai, NIP, dan Jabatan wajib diisi.";
        $is_error = true;
    } elseif (!$conn) {
         $message = "Koneksi database gagal saat menyimpan data.";
         $is_error = true;
    } else {
        // 3. Query INSERT yang diperbarui (Total 13 kolom, TIDAK TERMASUK ID)
        $sql_insert = "INSERT INTO {$NAMA_TABEL_PEGAWAI} 
            (nip, nama, nama_lengkap, unit_kerja, instansi_asal, golongan, jabatan_lama, provinsi, kabupaten_kota, nama_instansi, jenis_instansi, jabatan, jenjang) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = mysqli_prepare($conn, $sql_insert);
        
        if ($stmt) {
            // Binding parameter: Total 13 string (s)
            mysqli_stmt_bind_param($stmt, "sssssssssssss", 
                $nip, $nama, $nama_lengkap, $unit_kerja, $instansi_asal, $golongan, $jabatan_lama, 
                $provinsi, $kabupaten_kota, $nama_instansi, $jenis_instansi, $jabatan, $jenjang
            );
            
            if (mysqli_stmt_execute($stmt)) {
                // 🔴 OTOMATISASI LOG: Rekam aktivitas penambahan pegawai baru
                rekam_log_otomatis(
                    "Penambahan Pegawai Baru", 
                    "Berhasil menambahkan data pegawai baru atas nama " . $nama_lengkap . " (" . $jabatan . ") dengan NIP: " . $nip, 
                    "Fitur Baru"
                );

                // Berhasil: Redirect kembali ke database.php
                header("Location: database.php?success=" . urlencode("Data pegawai {$nama} berhasil ditambahkan!"));
                exit();
            } else {
                // Gagal Insert
                $message = "Gagal menyimpan data: " . mysqli_error($conn);
                $is_error = true;
            }
            mysqli_stmt_close($stmt);
        } else {
            $message = "Gagal menyiapkan query INSERT: " . mysqli_error($conn);
            $is_error = true;
        }
    }
}

// Tutup koneksi (di akhir script)
if (isset($conn) && $conn) {
    mysqli_close($conn);
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
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
    <?php require_once 'template/header.php'; // Sesuaikan nama file header Anda ?>
    <?php require_once 'template/sidebar.php'; // Sesuaikan nama file sidebar Anda ?>

    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0"><i class="fas fa-user-plus"></i> <?php echo $page_title; ?></h1>
                    </div>
                </div>
            </div>
        </div>
        <section class="content">
            <div class="container-fluid">

                <?php if ($message): // Tampilkan pesan sukses atau error ?>
                    <div class="alert alert-<?php echo $is_error ? 'danger' : 'success'; ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>
                
                <div class="card card-success"> 
                    <div class="card-header">
                        <h3 class="card-title">Formulir Input Data Pegawai Baru</h3>
                    </div>
                    <form method="POST" action="tambah_pegawai.php"> 
                        <div class="card-body">
                            
                            <div class="form-group">
                                <label for="nama">Nama Panggilan/Singkat (Kolom 'nama')</label>
                                <input type="text" class="form-control" id="nama" name="nama" required 
                                    value="<?php echo htmlspecialchars($input_values['nama'] ?? ''); ?>">
                                <small class="form-text text-muted">Contoh: Budi S. (Sesuai kolom 'nama' di database)</small>
                            </div>
                            <div class="form-group">
                                <label for="nama_lengkap">Nama Lengkap</label>
                                <input type="text" class="form-control" id="nama_lengkap" name="nama_lengkap" 
                                    value="<?php echo htmlspecialchars($input_values['nama_lengkap'] ?? ''); ?>">
                                <small class="form-text text-muted">Contoh: Budi Santoso (Sesuai kolom 'nama_lengkap')</small>
                            </div>
                            <div class="form-group">
                                <label for="nip">NIP</label>
                                <input type="text" class="form-control" id="nip" name="nip" required 
                                    value="<?php echo htmlspecialchars($input_values['nip'] ?? ''); ?>">
                            </div>
                            
                            <hr>
                            <h4>Informasi Jabatan & Golongan</h4>
                            <div class="row">
                                <div class="form-group col-md-6">
                                    <label for="jabatan">Jabatan Fungsional</label>
                                    <input type="text" class="form-control" id="jabatan" name="jabatan" required
                                        value="<?php echo htmlspecialchars($input_values['jabatan'] ?? ''); ?>">
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="jenjang">Jenjang Jabatan</label>
                                    <input type="text" class="form-control" id="jenjang" name="jenjang"
                                        value="<?php echo htmlspecialchars($input_values['jenjang'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="form-group col-md-6">
                                    <label for="golongan">Golongan</label>
                                    <input type="text" class="form-control" id="golongan" name="golongan" 
                                        value="<?php echo htmlspecialchars($input_values['golongan'] ?? ''); ?>">
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="jabatan_lama">Jabatan Lama</label>
                                    <input type="text" class="form-control" id="jabatan_lama" name="jabatan_lama" 
                                        value="<?php echo htmlspecialchars($input_values['jabatan_lama'] ?? ''); ?>">
                                </div>
                            </div>

                            <hr>
                            <h4>Informasi Instansi</h4>

                            <div class="form-group">
                                <label for="jenis_instansi">Jenis Instansi</label>
                                <select id="jenis_instansi" name="jenis_instansi" class="form-control" required>
                                    <option value="">-- Pilih Jenis Instansi --</option>
                                    <option value="Pusat" <?php echo ($input_values['jenis_instansi'] ?? '') == 'Pusat' ? 'selected' : ''; ?>>Pusat</option>
                                    <option value="Daerah" <?php echo ($input_values['jenis_instansi'] ?? '') == 'Daerah' ? 'selected' : ''; ?>>Daerah</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="nama_instansi">Nama Instansi (Pusat)</label>
                                <input type="text" class="form-control" id="nama_instansi" name="nama_instansi" required 
                                    value="<?php echo htmlspecialchars($input_values['nama_instansi'] ?? ''); ?>">
                                <small class="form-text text-muted">Contoh: Kementerian PUPR (Sesuai kolom 'nama_instansi')</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="unit_kerja">Unit Kerja</label>
                                <input type="text" class="form-control" id="unit_kerja" name="unit_kerja" 
                                    value="<?php echo htmlspecialchars($input_values['unit_kerja'] ?? ''); ?>">
                                <small class="form-text text-muted">Contoh: Direktorat Jenderal Cipta Karya</small>
                            </div>

                            <div class="form-group">
                                <label for="instansi_asal">Instansi Asal</label>
                                <input type="text" class="form-control" id="instansi_asal" name="instansi_asal" 
                                    value="<?php echo htmlspecialchars($input_values['instansi_asal'] ?? ''); ?>">
                                <small class="form-text text-muted">Contoh: Pemerintah Provinsi Jawa Barat (Sesuai kolom 'instansi_asal')</small>
                            </div>


                            <div class="row">
                                <div class="form-group col-md-6">
                                    <label for="provinsi">Provinsi</label>
                                    <input type="text" class="form-control" id="provinsi" name="provinsi" 
                                        value="<?php echo htmlspecialchars($input_values['provinsi'] ?? ''); ?>">
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="kabupaten_kota">Kabupaten/Kota</label>
                                    <input type="text" class="form-control" id="kabupaten_kota" name="kabupaten_kota" 
                                        value="<?php echo htmlspecialchars($input_values['kabupaten_kota'] ?? ''); ?>">
                                </div>
                            </div>

                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Simpan Data Pegawai</button>
                            <a href="database.php" class="btn btn-secondary float-right"><i class="fas fa-times"></i> Batal</a>
                        </div>
                    </form>
                </div>
            </div>
        </section>
    </div>
    
    <?php require_once 'template/footer.php'; // Sesuaikan nama file footer Anda ?> 
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>

</body>
</html>