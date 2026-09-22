<?php
// FILE: edit_pegawai.php - Logika dan Tampilan untuk Mengedit Data Pegawai
// -------------------------------------------------------------------------
// PERHATIAN: Asumsi file 'koneksi.php', 'auth_guard.php', 'template/sidebar.php', 
// 'template/footer.php', dan 'template/header.php' sudah tersedia dan berfungsi.
// -------------------------------------------------------------------------

// --- AUTH GUARD DAN KONEKSI DATABASE ---
require_once 'koneksi.php'; 
require_once 'auth_guard.php'; 

// ⚠️ PENCEGAHAN ERROR FATAL: CEK KONEKSI
if (!$conn) {
    die("<div style='padding: 20px; background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;'>
        <h1>❌ Koneksi Database Gagal!</h1>
        <p><strong>Pesan Error MySQL:</strong> " . mysqli_connect_error() . "</p>
    </div>");
}

// --- PENGATURAN JUDUL HALAMAN & MENU ---
$page = 'database'; 
$sub_page = 'edit_pegawai'; 
$page_title = 'Edit Data Pegawai JF'; 

// Tentukan nama tabel pegawai
$NAMA_TABEL_PEGAWAI = "detailpegawai"; 

$message = ''; 
$is_error = false;
$id_pegawai = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$pegawai_data = []; // Akan menampung data lama atau nilai sticky form
$error_ambil_data = false;

// =========================================================
// LOGIKA 1: AMBIL DATA PEGAWAI BERDASARKAN ID (GET Request)
// =========================================================
if ($id_pegawai > 0) {
    $sql_select = "SELECT * FROM {$NAMA_TABEL_PEGAWAI} WHERE id = ?";
    $stmt_select = mysqli_prepare($conn, $sql_select);
    
    if ($stmt_select) {
        mysqli_stmt_bind_param($stmt_select, "i", $id_pegawai);
        mysqli_stmt_execute($stmt_select);
        $result_select = mysqli_stmt_get_result($stmt_select);
        
        if (mysqli_num_rows($result_select) === 1) {
            $pegawai_data = mysqli_fetch_assoc($result_select);
            $page_title = 'Edit Data Pegawai: ' . htmlspecialchars($pegawai_data['nama_lengkap'] ?? '');
        } else {
            $error_ambil_data = true;
            $message = "❌ Error: Data pegawai dengan ID $id_pegawai tidak ditemukan.";
        }
        mysqli_stmt_close($stmt_select);
    } else {
        $error_ambil_data = true;
        $message = "❌ Gagal menyiapkan statement SELECT: " . mysqli_error($conn);
    }
} else {
    $error_ambil_data = true;
    $message = "❌ Error: ID pegawai tidak valid atau tidak diberikan.";
}


// =========================================================
// LOGIKA 2: TANGANI SUBMIT FORM UPDATE (POST Request)
// =========================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && $id_pegawai > 0 && !$error_ambil_data) {
    
    // Ambil dan Sanitasi Data LENGKAP dari Formulir Berdasarkan Struktur Tabel Asli
    $id_update = (int)($_POST['id_pegawai'] ?? 0); 
    
    $email = trim($_POST['email'] ?? '');
    $nomor_hp = trim($_POST['nomor_hp'] ?? '');
    $nip = trim($_POST['nip'] ?? '');
    $status_kepegawaian = trim($_POST['status_kepegawaian'] ?? '');
    $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
    $jenis_kelamin = trim($_POST['jenis_kelamin'] ?? '');
    $instansi = trim($_POST['instansi'] ?? '');
    $tipe_dinas = trim($_POST['tipe_dinas'] ?? '');
    $unit_eselon_I = trim($_POST['unit_eselon_I'] ?? '');
    $unit_eselon_II = trim($_POST['unit_eselon_II'] ?? '');
    $unit_eselon_III_IV = trim($_POST['unit_eselon_III_IV'] ?? '');
    $pangkat_golongan = trim($_POST['pangkat_golongan'] ?? '');
    $jenjang_jf_pkp_terakhir = trim($_POST['jenjang_jf_pkp_terakhir'] ?? '');
    $tmt_jf_terkahir = !empty($_POST['tmt_jf_terkahir']) ? $_POST['tmt_jf_terkahir'] : null;
    $status_jf = trim($_POST['status_jf'] ?? '');
    $jenjang_pendidikan_terakhir = trim($_POST['jenjang_pendidikan_terakhir'] ?? '');
    $rumpun_ilmu = trim($_POST['rumpun_ilmu'] ?? '');
    $jurusan_program_studi = trim($_POST['jurusan_program_studi'] ?? '');
    $saran_masukan = trim($_POST['saran_masukan'] ?? '');
    
    // Simpan nilai input baru untuk 'sticky form' jika terjadi error validasi
    $pegawai_data = $_POST;
    $pegawai_data['id'] = $id_pegawai; 

    // Validasi Minimal (Nama Lengkap & NIP tidak boleh kosong)
    if ($id_update !== $id_pegawai || empty($nip) || empty($nama_lengkap)) {
        $message = "Nama Lengkap dan NIP wajib diisi, serta ID Pegawai harus valid.";
        $is_error = true;
    } else {
        // Query UPDATE (Total 19 kolom SET + 1 kolom WHERE ID)
        $sql_update = "UPDATE {$NAMA_TABEL_PEGAWAI} SET
            email = ?,
            nomor_hp = ?,
            nip = ?,
            status_kepegawaian = ?,
            nama_lengkap = ?,
            jenis_kelamin = ?,
            instansi = ?,
            tipe_dinas = ?,
            unit_eselon_I = ?,
            unit_eselon_II = ?,
            unit_eselon_III_IV = ?,
            pangkat_golongan = ?,
            jenjang_jf_pkp_terakhir = ?,
            tmt_jf_terkahir = ?,
            status_jf = ?,
            jenjang_pendidikan_terakhir = ?,
            rumpun_ilmu = ?,
            jurusan_program_studi = ?,
            saran_masukan = ?
            WHERE id = ?"; 
        
        $stmt_update = mysqli_prepare($conn, $sql_update);
        
        if ($stmt_update) {
            // Binding parameter: 19 string/null (s) + 1 integer (i)
            mysqli_stmt_bind_param($stmt_update, "sssssssssssssssssssi", 
                $email, $nomor_hp, $nip, $status_kepegawaian, $nama_lengkap, $jenis_kelamin, 
                $instansi, $tipe_dinas, $unit_eselon_I, $unit_eselon_II, $unit_eselon_III_IV, 
                $pangkat_golongan, $jenjang_jf_pkp_terakhir, $tmt_jf_terkahir, $status_jf, 
                $jenjang_pendidikan_terakhir, $rumpun_ilmu, $jurusan_program_studi, $saran_masukan,
                $id_update
            );
            
            if (mysqli_stmt_execute($stmt_update)) {
                // 🔴 OTOMATISASI LOG: Rekam aktivitas dengan keterangan tugas dan fungsi evaluator
                rekam_log_otomatis(
                    "Pembaruan Data Pegawai", 
                    "Berhasil memperbarui data pegawai atas nama " . $nama_lengkap . " (NIP: " . $nip . ") melalui keterangan tugas dan fungsi evaluator.", 
                    "Log Aktivitas"
                );

                // Berhasil: Redirect ke database.php dengan pesan sukses
                header("Location: database.php?success=" . urlencode("Data pegawai {$nama_lengkap} (ID: {$id_update}) berhasil diperbarui!"));
                exit();
            } else {
                $message = "❌ Gagal memperbarui data: " . mysqli_error($conn);
                $is_error = true;
            }
            mysqli_stmt_close($stmt_update);
        } else {
            $message = "❌ Gagal menyiapkan query UPDATE: " . mysqli_error($conn);
            $is_error = true;
        }
    }
}

// Tutup koneksi (sebelum output HTML)
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

    <?php require_once 'template/header.php'; ?>
    <?php require_once 'template/sidebar.php'; ?>

    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0"><i class="fas fa-edit"></i> <?php echo $page_title; ?></h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                            <li class="breadcrumb-item"><a href="database.php">Database</a></li>
                            <li class="breadcrumb-item active">Edit</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        
        <section class="content">
            <div class="container-fluid">

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $is_error ? 'danger' : 'success'; ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>
                
                <?php if (!$error_ambil_data && $pegawai_data): ?>
                    <div class="card card-primary"> 
                        <div class="card-header">
                            <h3 class="card-title">Formulir Edit Data Pegawai ID: <?php echo htmlspecialchars($id_pegawai); ?></h3>
                        </div>
                        <form method="POST" action="edit_pegawai.php?id=<?php echo htmlspecialchars($id_pegawai); ?>"> 
                            
                            <input type="hidden" name="id_pegawai" value="<?php echo htmlspecialchars($id_pegawai); ?>">

                            <div class="card-body">
                                
                                <h4>Informasi Dasar</h4>
                                <div class="row">
                                    <div class="form-group col-md-6">
                                        <label for="nama_lengkap">Nama Lengkap</label>
                                        <input type="text" class="form-control" id="nama_lengkap" name="nama_lengkap" required 
                                            value="<?php echo htmlspecialchars($pegawai_data['nama_lengkap'] ?? ''); ?>">
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label for="nip">NIP</label>
                                        <input type="text" class="form-control" id="nip" name="nip" required 
                                            value="<?php echo htmlspecialchars($pegawai_data['nip'] ?? ''); ?>">
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="form-group col-md-6">
                                        <label for="jenis_kelamin">Jenis Kelamin</label>
                                        <select id="jenis_kelamin" name="jenis_kelamin" class="form-control">
                                            <option value="">-- Pilih Jenis Kelamin --</option>
                                            <option value="Laki-laki" <?php echo ($pegawai_data['jenis_kelamin'] ?? '') == 'Laki-laki' ? 'selected' : ''; ?>>Laki-laki</option>
                                            <option value="Perempuan" <?php echo ($pegawai_data['jenis_kelamin'] ?? '') == 'Perempuan' ? 'selected' : ''; ?>>Perempuan</option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label for="status_kepegawaian">Status Kepegawaian</label>
                                        <input type="text" class="form-control" id="status_kepegawaian" name="status_kepegawaian" 
                                            value="<?php echo htmlspecialchars($pegawai_data['status_kepegawaian'] ?? ''); ?>" placeholder="Contoh: PNS / CPNS / PPPK">
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="form-group col-md-6">
                                        <label for="email">Email</label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                            value="<?php echo htmlspecialchars($pegawai_data['email'] ?? ''); ?>">
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label for="nomor_hp">Nomor HP</label>
                                        <input type="text" class="form-control" id="nomor_hp" name="nomor_hp" 
                                            value="<?php echo htmlspecialchars($pegawai_data['nomor_hp'] ?? ''); ?>">
                                    </div>
                                </div>
                                
                                <hr>
                                
                                <h4>Informasi Jabatan & Golongan</h4>
                                <div class="row">
                                    <div class="form-group col-md-6">
                                        <label for="pangkat_golongan">Pangkat / Golongan</label>
                                        <input type="text" class="form-control" id="pangkat_golongan" name="pangkat_golongan" 
                                            value="<?php echo htmlspecialchars($pegawai_data['pangkat_golongan'] ?? ''); ?>" placeholder="Contoh: Penata Muda / III/a">
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label for="status_jf">Status JF</label>
                                        <input type="text" class="form-control" id="status_jf" name="status_jf" 
                                            value="<?php echo htmlspecialchars($pegawai_data['status_jf'] ?? ''); ?>">
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="form-group col-md-4">
                                        <label for="jenjang_jf_pkp_terakhir">Jenjang JF PKP Terakhir</label>
                                        <input type="text" class="form-control" id="jenjang_jf_pkp_terakhir" name="jenjang_jf_pkp_terakhir"
                                            value="<?php echo htmlspecialchars($pegawai_data['jenjang_jf_pkp_terakhir'] ?? ''); ?>" placeholder="Contoh: Ahli Pertama">
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="tmt_jf_terkahir">TMT JF Terakhir</label>
                                        <input type="date" class="form-control" id="tmt_jf_terkahir" name="tmt_jf_terkahir"
                                            value="<?php echo htmlspecialchars($pegawai_data['tmt_jf_terkahir'] ?? ''); ?>">
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="tipe_dinas">Tipe Dinas</label>
                                        <input type="text" class="form-control" id="tipe_dinas" name="tipe_dinas" 
                                            value="<?php echo htmlspecialchars($pegawai_data['tipe_dinas'] ?? ''); ?>">
                                    </div>
                                </div>

                                <hr>
                                
                                <h4>Informasi Instansi & Eselon</h4>
                                <div class="form-group">
                                    <label for="instansi">Nama Instansi</label>
                                    <input type="text" class="form-control" id="instansi" name="instansi" 
                                        value="<?php echo htmlspecialchars($pegawai_data['instansi'] ?? ''); ?>">
                                </div>

                                <div class="row">
                                    <div class="form-group col-md-4">
                                        <label for="unit_eselon_I">Unit Eselon I</label>
                                        <input type="text" class="form-control" id="unit_eselon_I" name="unit_eselon_I" 
                                            value="<?php echo htmlspecialchars($pegawai_data['unit_eselon_I'] ?? ''); ?>">
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="unit_eselon_II">Unit Eselon II</label>
                                        <input type="text" class="form-control" id="unit_eselon_II" name="unit_eselon_II" 
                                            value="<?php echo htmlspecialchars($pegawai_data['unit_eselon_II'] ?? ''); ?>">
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="unit_eselon_III_IV">Unit Eselon III / IV</label>
                                        <input type="text" class="form-control" id="unit_eselon_III_IV" name="unit_eselon_III_IV" 
                                            value="<?php echo htmlspecialchars($pegawai_data['unit_eselon_III_IV'] ?? ''); ?>">
                                    </div>
                                </div>

                                <hr>
                                
                                <h4>Pendidikan Terakhir</h4>
                                <div class="row">
                                    <div class="form-group col-md-4">
                                        <label for="jenjang_pendidikan_terakhir">Jenjang Pendidikan</label>
                                        <input type="text" class="form-control" id="jenjang_pendidikan_terakhir" name="jenjang_pendidikan_terakhir" 
                                            value="<?php echo htmlspecialchars($pegawai_data['jenjang_pendidikan_terakhir'] ?? ''); ?>" placeholder="Contoh: S1 / S2">
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="rumpun_ilmu">Rumpun Ilmu</label>
                                        <input type="text" class="form-control" id="rumpun_ilmu" name="rumpun_ilmu" 
                                            value="<?php echo htmlspecialchars($pegawai_data['rumpun_ilmu'] ?? ''); ?>">
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="jurusan_program_studi">Jurusan / Program Studi</label>
                                        <input type="text" class="form-control" id="jurusan_program_studi" name="jurusan_program_studi" 
                                            value="<?php echo htmlspecialchars($pegawai_data['jurusan_program_studi'] ?? ''); ?>">
                                    </div>
                                </div>

                                <hr>
                                
                                <div class="form-group">
                                    <label for="saran_masukan">Saran / Masukan</label>
                                    <textarea class="form-control" id="saran_masukan" name="saran_masukan" rows="3"><?php echo htmlspecialchars($pegawai_data['saran_masukan'] ?? ''); ?></textarea>
                                </div>

                            </div>
                            <div class="card-footer">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Perubahan</button>
                                <a href="database.php" class="btn btn-secondary float-right">
                                    <i class="fas fa-times"></i> Batal
                                </a>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>

            </div>
        </section>
    </div>

    <?php require_once 'template/footer.php'; ?>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>

</body>
</html>