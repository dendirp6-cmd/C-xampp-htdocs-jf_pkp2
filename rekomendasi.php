<?php
// FILE: rekomendasi.php - Halaman Pengajuan Rekomendasi Formasi (AdminLTE)

// --- PENTING: DEKLARASI VARIABEL UNTUK HIGHLIGHT MENU SIDEBAR ---
$page = 'rekomendasi'; // Mengaktifkan highlight menu "Rekomendasi Formasi"
$sub_page = '';        // Variabel ini digunakan oleh logika menu Ujikom di sidebar
// -------------------------------------------------------------

// --- ASUMSI FILE PENDUKUNG ---
// Pastikan file-file ini ada di direktori utama Anda (C:\xampp\htdocs\jf_pkp2\)
require_once 'auth_guard.php'; 
require_once 'koneksi.php'; 

// --- DEKLARASI VARIABEL & ASUMSI ---
$message = ''; 
$is_error = false;
$status_default = "Dalam Penilaian"; 
$NAMA_TABEL_FORMASI = "data_formasi"; // Asumsi nama tabel formasi Anda

// Menggunakan variabel global yang disiapkan oleh auth_guard.php
$user_data = [
    'nama' => $user_nama_sesi ?? 'Pengguna JF', 
    'role' => $user_role_sesi ?? 'User', 
    'email' => $user_email_sesi ?? 'user@instansi.go.id', 
    'join_date' => $_SESSION['join_date'] ?? 'Maret 2023'
];

// --- LOGIKA PENYIMPANAN DATA (HANDLE POST REQUEST) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_rekomendasi'])) {
    
    // 1. Ambil dan Bersihkan Data dari Form
    $pengusul = mysqli_real_escape_string($conn, $_POST['pengusul']);
    $instansi = mysqli_real_escape_string($conn, $_POST['instansi']);
    $jumlah_formasi = mysqli_real_escape_string($conn, $_POST['jumlah_formasi']);
    $dokumen_pendukung_link = mysqli_real_escape_string($conn, $_POST['dokumen_pendukung_link']);
    $status = $status_default; 

    // Lakukan validasi dasar
    if (empty($pengusul) || empty($instansi) || empty($jumlah_formasi)) {
        $is_error = true;
        $message = "Semua kolom wajib (Pengusul, Instansi, Jumlah Formasi) harus diisi.";
    } else {
        // 2. Query untuk Menyimpan Data menggunakan Prepared Statement
        $sql_insert = "INSERT INTO {$NAMA_TABEL_FORMASI} (
                             pengusul, instansi, jumlah_formasi, dokumen_pendukung_link, status
                           ) VALUES (?, ?, ?, ?, ?)";
        
        $stmt = mysqli_prepare($conn, $sql_insert);
        
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "sssss", 
                $pengusul, $instansi, $jumlah_formasi, 
                $dokumen_pendukung_link, $status);
            
            if (mysqli_stmt_execute($stmt)) {
                $is_error = false;
                $message = "✅ Pengajuan rekomendasi formasi oleh **{$pengusul}** berhasil disimpan dengan status **{$status}**.";
                $_POST = [];
            } else {
                $is_error = true;
                $message = "❌ Gagal menyimpan data: " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        } else {
            $is_error = true;
            $message = "❌ Gagal menyiapkan query: " . mysqli_error($conn);
        }
    }
}

// --- LOGIKA AMBIL DATA & HELPER FUNCTION ---
/**
 * Mengubah status teks menjadi class warna AdminLTE/Bootstrap.
 */
function get_status_class($status) {
    $status_lower = strtolower(trim($status));
    if (strpos($status_lower, 'sesuai') !== false || strpos($status_lower, 'valid') !== false || strpos($status_lower, 'disetujui') !== false) {
        return 'success'; // Hijau
    } elseif (strpos($status_lower, 'perbaikan') !== false || strpos($status_lower, 'kurang') !== false || strpos( $status_lower, 'tolak') !== false) {
        return 'warning'; // Kuning/Jingga
    } elseif (strpos($status_lower, 'ditolak') !== false || strpos($status_lower, 'gagal') !== false) {
        return 'danger'; // Merah
    } else {
        return 'primary'; // Biru (untuk default "Dalam Penilaian")
    }
}

$sql_select = "SELECT id, pengusul, instansi, jumlah_formasi, status FROM {$NAMA_TABEL_FORMASI} ORDER BY id DESC";
$res_data_formasi = @mysqli_query($conn, $sql_select);
$data_formasi_gagal = !$res_data_formasi; 

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rekomendasi Formasi | Instansi Pembina JF</title>

    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    
    <style>
        .brand-link { background-color: #111827; }
        .brand-link .logo-pupr i { color: #0f62fe; }
        .logo-pupr { background: #fff; padding: 4px; width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; border-radius: 50%;}
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
            </li>
        </ul>
        
        <ul class="navbar-nav ml-auto">
             <li class="nav-item d-none d-sm-inline-block">
                 <div style="padding-top: 8px; text-align: right;">
                     Hi, selamat datang kembali!<br>
                     <span class="text-muted" style="font-size:13px">Sistem informasi jabatan fungsional</span>
                 </div>
             </li>

             <li class="nav-item dropdown user-menu ml-3">
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
    
    <?php include 'template/sidebar.php'; ?> 

    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0"><i class="fas fa-chart-line"></i> Pengajuan Rekomendasi Formasi</h1>
                    </div>
                    <div class="col-sm-6 text-right">
                        <ol class="breadcrumb float-sm-right" style="background-color: transparent;">
                             <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                             <li class="breadcrumb-item active">Rekomendasi Formasi</li>
                             <li>
                                 <button class="btn btn-sm btn-primary ml-3"><i class="fas fa-file-pdf"></i> Generate Laporan</button>
                             </li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        <section class="content">
            <div class="container-fluid">

                <?php if ($message): ?>
                    <div class="alert <?php echo $is_error ? 'alert-danger' : 'alert-success'; ?> alert-dismissible fade show" role="alert">
                        <?php echo $message; ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>

                <div class="card card-primary">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-edit"></i> Input Pengajuan Rekomendasi Baru</h3>
                    </div>
                    <form method="POST" action="rekomendasi.php">
                        <div class="card-body">
                            
                            <div class="form-group">
                                <label for="pengusul">Nama Pengusul / Pejabat *</label>
                                <input type="text" id="pengusul" name="pengusul" class="form-control" required value="<?php echo htmlspecialchars($_POST['pengusul'] ?? ''); ?>">
                            </div>

                            <div class="form-group">
                                <label for="instansi">Instansi Pengusul *</label>
                                <input type="text" id="instansi" name="instansi" class="form-control" required value="<?php echo htmlspecialchars($_POST['instansi'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="jumlah_formasi">Jumlah Kebutuhan Formasi *</label>
                                <input type="number" id="jumlah_formasi" name="jumlah_formasi" class="form-control" required min="1" value="<?php echo htmlspecialchars($_POST['jumlah_formasi'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="dokumen_pendukung_link">Link Dokumen Pendukung (Google Drive/Sharepoint)</label>
                                <input type="url" id="dokumen_pendukung_link" name="dokumen_pendukung_link" class="form-control" placeholder="Contoh: https://drive.google.com/link-dokumen-kebutuhan" value="<?php echo htmlspecialchars($_POST['dokumen_pendukung_link'] ?? ''); ?>">
                            </div>
                            
                            <p class="text-muted" style="font-size:12px; margin-top:20px;">* Status awal akan diatur sebagai "**<?php echo $status_default; ?>**".</p>

                        </div>
                        <div class="card-footer">
                            <button type="submit" name="submit_rekomendasi" class="btn btn-primary"><i class="fas fa-upload"></i> Ajukan Rekomendasi</button>
                        </div>
                    </form>
                </div>

                <div class="card card-info">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-table"></i> Daftar Pengajuan Rekomendasi Formasi</h3>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th style="width: 5%">No</th>
                                    <th style="width: 30%">Pengusul</th>
                                    <th style="width: 35%">Instansi</th>
                                    <th style="width: 10%">Jml. Formasi</th>
                                    <th style="width: 15%">Status</th>
                                    <th style="width: 5%">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                if ($data_formasi_gagal) {
                                    $error_msg = isset($conn) ? mysqli_error($conn) : 'Koneksi database belum diinisiasi.';
                                    echo "<tr><td colspan='6' class='text-center text-danger font-weight-bold'>Gagal memuat daftar pengajuan. Error: " . $error_msg . "</td></tr>";
                                } elseif (@mysqli_num_rows($res_data_formasi) > 0) {
                                    $no = 1;
                                    while($row = @mysqli_fetch_assoc($res_data_formasi)) {
                                        $status_class = get_status_class($row['status']);
                                        echo "<tr>";
                                        echo "<td>" . $no++ . "</td>";
                                        echo "<td>" . htmlspecialchars($row['pengusul']) . "</td>";
                                        echo "<td>" . htmlspecialchars($row['instansi']) . "</td>";
                                        echo "<td>" . htmlspecialchars($row['jumlah_formasi']) . "</td>";
                                        echo "<td><span class='badge badge-" . $status_class . "'>" . htmlspecialchars($row['status']) . "</span></td>";
                                        echo "<td><a href='detail_rekomendasi.php?id=" . $row['id'] . "' class='text-info' title='Lihat Detail'><i class='fas fa-eye'></i></a></td>";
                                        echo "</tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='6' class='text-center text-muted'>Belum ada pengajuan rekomendasi formasi.</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                    </div>

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

<?php
// Tutup koneksi database
if (isset($conn) && $conn) {
    mysqli_close($conn);
}
?>
</body>
</html>