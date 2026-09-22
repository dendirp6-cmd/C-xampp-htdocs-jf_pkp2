<?php
// FILE: ujikom.php - Halaman Manajemen Uji Kompetensi (Gabungan Hasil Uji dan Pengajuan Form)

// --- AUTH GUARD DITAMBAHKAN DI SINI ---
require_once 'auth_guard.php'; 
require_once 'koneksi.php'; // Memuat koneksi dan variabel nama tabel

// --- PENTING: DEKLARASI VARIABEL UNTUK HIGHLIGHT MENU SIDEBAR ---
$page = 'ujikom'; // Menu tingkat 1
$sub_page = 'daftar_ujikom'; // Submenu yang spesifik
// ------------------------------------------------------------

// --- DEFINISI VARIABEL NAVIGASI (PENTING UNTUK SIDEBAR) ---
$page = 'ujikom';          // Mengaktifkan menu induk "Uji Kompetensi"
$sub_page = 'daftar_ujikom'; // Mengaktifkan sub-menu "Daftar Ujikom"

$page_title = "Manajemen Uji Kompetensi"; 

$message = ''; 
$is_error = false;

// --- PENDEFINISIAN NAMA TABEL ---
$NAMA_TABEL_UJIKOM = "data_ujikom"; // Tabel untuk menyimpan hasil ujian (Lulus/Tidak Lulus)
$NAMA_TABEL_PEGAWAI = "detailpegawai"; // Tabel detail pegawai
$NAMA_TABEL_PENGAJUAN = "pengajuan_ujikom"; // Tabel untuk pengajuan form/dokumen baru

// --- PENGGUNAAN DATA SESSION DARI auth_guard.php ---
$user_data = [
    'nama' => $user_nama_sesi ?? 'Pengguna', 
    'role' => $user_role_sesi ?? 'User', 
    'email' => $user_email_sesi ?? 'user@instansi.go.id', 
    'join_date' => $_SESSION['join_date'] ?? 'N/A' 
];

// --- VARIABEL UNTUK REPOPULASI FORM INPUT HASIL ---
$input_nip_repopulate = '';
$input_tanggal_uji_repopulate = date('Y-m-d'); 
$input_hasil_repopulate = '';


// --- FUNGSI HELPER ---

/**
 * Mengembalikan class AdminLTE untuk status hasil uji (Lulus, Tidak Lulus)
 */
function get_hasil_badge_adminlte($hasil) {
    $hasil_lower = strtolower(trim($hasil));
    if (strpos($hasil_lower, 'lulus') !== false) {
        return 'success'; // Hijau
    } elseif (strpos($hasil_lower, 'belum') !== false || strpos(
        $hasil_lower, 'proses') !== false || strpos($hasil_lower, 'menunggu') !== false) {
        return 'info'; // Biru/Biru Muda
    } else {
        return 'danger'; // Merah (Tidak Lulus/Gagal)
    }
}

/**
 * Mengembalikan class AdminLTE untuk status pengajuan form (Menunggu Verifikasi, Disetujui, Ditolak)
 * Logic menggunakan strpos untuk ketahanan terhadap variasi input database.
 */
function get_status_badge_adminlte($status) {
    $status_lower = strtolower(trim($status ?? ''));
    $class = 'badge-primary'; // Default: Biru tua
    
    if (strpos($status_lower, 'ditolak') !== false || strpos($status_lower, 'gagal') !== false) {
        $class = 'badge-danger'; // Merah
    } elseif (strpos($status_lower, 'menunggu') !== false || strpos($status_lower, 'belum') !== false) {
        $class = 'badge-warning'; // Kuning
    } elseif (strpos($status_lower, 'disetujui') !== false || strpos($status_lower, 'lulus') !== false) {
        $class = 'badge-success'; // Hijau
    } elseif (strpos($status_lower, 'diverifikasi') !== false || strpos($status_lower, 'proses') !== false) {
        $class = 'badge-info'; // Biru Muda
    }

    return $class;
}


// =========================================================
// 1. LOGIKA PENYIMPANAN DATA HASIL UJI (HANDLE POST REQUEST)
// =========================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_hasil'])) {
    if (!isset($conn) || !$conn instanceof mysqli) {
        $is_error = true;
        $message = "Koneksi database tidak tersedia/tidak valid.";
    } else {
        $nip = mysqli_real_escape_string($conn, $_POST['nip'] ?? '');
        $tanggal_uji = mysqli_real_escape_string($conn, $_POST['tanggal_uji'] ?? '');
        $hasil = mysqli_real_escape_string($conn, $_POST['hasil'] ?? '');
        
        $input_nip_repopulate = htmlspecialchars($nip);
        $input_tanggal_uji_repopulate = htmlspecialchars($tanggal_uji);
        $input_hasil_repopulate = htmlspecialchars($hasil);

        if (empty($nip) || empty($tanggal_uji) || empty($hasil)) {
            $is_error = true;
            $message = "Semua kolom (NIP, Tanggal Uji, Hasil) wajib diisi.";
        } elseif (strlen($nip) < 18) { 
            $is_error = true;
            $message = "NIP tidak valid. Pastikan NIP sudah benar.";
        } else {
            // 3. Cek apakah NIP terdaftar di tabel detailpegawai
            $sql_check_nip = "SELECT nip FROM {$NAMA_TABEL_PEGAWAI} WHERE nip = ?";
            $stmt_check = mysqli_prepare($conn, $sql_check_nip);
            mysqli_stmt_bind_param($stmt_check, "s", $nip);
            mysqli_stmt_execute($stmt_check);
            mysqli_stmt_store_result($stmt_check);

            if (mysqli_stmt_num_rows($stmt_check) == 0) {
                $is_error = true;
                $message = "Gagal. NIP **{$nip}** tidak ditemukan di database pegawai. Pastikan NIP sudah ada di menu Database.";
            } else {
                // 4. Proses Penyimpanan (Insert)
                $sql_insert = "INSERT INTO {$NAMA_TABEL_UJIKOM} (nip, tanggal_uji, hasil) VALUES (?, ?, ?)";
                $stmt_insert = mysqli_prepare($conn, $sql_insert);
                mysqli_stmt_bind_param($stmt_insert, "sss", $nip, $tanggal_uji, $hasil);

                if (mysqli_stmt_execute($stmt_insert)) {
                    $message = "✅ Hasil Uji Kompetensi untuk NIP **{$nip}** berhasil disimpan.";
                    $input_nip_repopulate = ''; 
                    $input_tanggal_uji_repopulate = date('Y-m-d');
                    $input_hasil_repopulate = '';
                } else {
                    $is_error = true;
                    if (mysqli_errno($conn) == 1062) {
                        $message = "❌ Data hasil uji untuk NIP **{$nip}** pada tanggal tersebut sudah ada.";
                    } else {
                        $message = "❌ Gagal menyimpan data: " . mysqli_error($conn);
                    }
                }
                mysqli_stmt_close($stmt_insert);
            }
            mysqli_stmt_close($stmt_check);
        }
    }
}
// =========================================================


// =========================================================
// 2. LOGIKA AMBIL DATA PENGAJUAN BARU (pengajuan_ujikom)
// =========================================================
$sql_select_pengajuan = "
    SELECT 
        * FROM 
        {$NAMA_TABEL_PENGAJUAN}
    ORDER BY 
        tanggal_pengajuan DESC
";
$res_data_pengajuan = @mysqli_query($conn, $sql_select_pengajuan);
$data_pengajuan = [];
$data_pengajuan_gagal = !$res_data_pengajuan;

if ($res_data_pengajuan && mysqli_num_rows($res_data_pengajuan) > 0) {
    while($row = mysqli_fetch_assoc($res_data_pengajuan)) {
        $data_pengajuan[] = $row;
    }
}


// =========================================================
// 3. LOGIKA AMBIL DATA HASIL UJI KOMPETENSI (data_ujikom)
// =========================================================
// MENGGUNAKAN NAMA KOLOM 'nama_lengkap' DI TABEL detailpegawai
$sql_select_hasil = "
    SELECT 
        ujikom.id, 
        pegawai.nama_lengkap AS nama_lengkap_gelar, 
        ujikom.nip, 
        pegawai.instansi_asal AS instansi,
        ujikom.tanggal_uji, 
        ujikom.hasil 
    FROM 
        {$NAMA_TABEL_UJIKOM} ujikom
    LEFT JOIN 
        {$NAMA_TABEL_PEGAWAI} pegawai ON ujikom.nip = pegawai.nip
    ORDER BY 
        ujikom.tanggal_uji DESC, ujikom.id DESC
";
$res_data_ujikom = @mysqli_query($conn, $sql_select_hasil); 
$data_ujikom_gagal = !$res_data_ujikom; 

$final_data_ujikom = [];

if ($res_data_ujikom && mysqli_num_rows($res_data_ujikom) > 0) {
    while($row = mysqli_fetch_assoc($res_data_ujikom)) {
        $final_data_ujikom[] = $row;
    }
}


// --- PANGGIL TEMPLATE HEADER (Menyertakan HTML dasar AdminLTE untuk kelengkapan)---
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
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.25/css/dataTables.bootstrap4.min.css">
    <style>
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
    <?php include 'template/sidebar.php'; ?>
    <div class="content-wrapper">
        <section class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1><?php echo $page_title; ?></h1>
                    </div>
                </div>
            </div>
        </section>

        <section class="content">
            <div class="container-fluid">

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $is_error ? 'danger' : 'success'; ?> alert-dismissible fade show" role="alert">
                        <?php echo $message; ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-12">
                           <div class="card card-warning">
                                 <div class="card-header">
                                      <h3 class="card-title"><i class="fas fa-plus-circle"></i> Input Hasil Uji Kompetensi</h3>
                                 </div>
                                <form method="POST" action="ujikom.php">
                                        <div class="card-body row">
                                             <div class="form-group col-md-4">
                                                  <label for="nip">NIP Peserta</label>
                                                  <input type="text" id="nip" name="nip" class="form-control" required placeholder="NIP Pegawai" value="<?php echo $input_nip_repopulate; ?>">
                                             </div>
                                             <div class="form-group col-md-4">
                                                  <label for="tanggal_uji">Tanggal Uji</label>
                                                  <input type="date" id="tanggal_uji" name="tanggal_uji" class="form-control" required value="<?php echo $input_tanggal_uji_repopulate; ?>">
                                             </div>
                                             <div class="form-group col-md-4">
                                                  <label for="hasil">Hasil Uji</label>
                                                  <select id="hasil" name="hasil" class="form-control" required>
                                                         <option value="">-- Pilih --</option>
                                                         <option value="Lulus" <?php echo ($input_hasil_repopulate == 'Lulus' ? 'selected' : ''); ?>>Lulus</option>
                                                         <option value="Tidak Lulus" <?php echo ($input_hasil_repopulate == 'Tidak Lulus' ? 'selected' : ''); ?>>Tidak Lulus</option>
                                                         <option value="Menunggu Nilai" <?php echo ($input_hasil_repopulate == 'Menunggu Nilai' ? 'selected' : ''); ?>>Menunggu Nilai</option>
                                                  </select>
                                             </div>
                                        </div>
                                        <div class="card-footer">
                                             <button type="submit" name="submit_hasil" class="btn btn-warning"><i class="fas fa-upload"></i> Simpan Hasil</button>
                                        </div>
                                </form>
                           </div>
                    </div>

                    <div class="col-md-12">
                        <div class="card card-info">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-file-alt"></i> Daftar Pengajuan Uji Kompetensi Baru (Verifikasi Dokumen)</h3>
                            </div>
                            <div class="card-body table-responsive">
                                 <?php if ($data_pengajuan_gagal): ?>
                                     <p class="text-center text-danger font-weight-bold">Gagal memuat data pengajuan. Pastikan tabel **'<?php echo $NAMA_TABEL_PENGAJUAN; ?>'** sudah ada.</p>
                                 <?php elseif (empty($data_pengajuan)): ?>
                                     <p class="lead text-center text-muted">Belum ada pengajuan dokumen baru.</p>
                                 <?php else: ?>
                                     <table id="tabelPengajuan" class="table table-bordered table-striped">
                                         <thead>
                                             <tr>
                                                 <th>No</th>
                                                 <th>NIP</th>
                                                 <th>Nama</th>
                                                 <th>Jenis Pengajuan</th>
                                                 <th>Tgl Pengajuan</th>
                                                 <th>Status</th>
                                                 <th>Aksi</th>
                                             </tr>
                                         </thead>
                                         <tbody>
                                             <?php $no = 1; foreach ($data_pengajuan as $data): ?>
                                             <tr>
                                                 <td><?php echo $no++; ?></td>
                                                 <td><?php echo htmlspecialchars($data['nip'] ?? '-'); ?></td>
                                                 <td><?php echo htmlspecialchars($data['nama'] ?? '-'); ?></td>
                                                 <td><?php echo htmlspecialchars($data['jenis_pengajuan'] ?? '-'); ?></td>
                                                 <td><?php echo date('d-m-Y H:i', strtotime($data['tanggal_pengajuan'] ?? '')); ?></td>
                                                 <td>
                                                     <span class="badge badge-<?php echo get_status_badge_adminlte($data['status_pengajuan'] ?? 'Tidak Diketahui'); ?>">
                                                         <?php echo htmlspecialchars($data['status_pengajuan'] ?? 'Tidak Diketahui'); ?>
                                                     </span>
                                                 </td>
                                                 <td>
                                                     <?php
                                                     // Logic untuk menentukan halaman verifikasi berdasarkan jenis pengajuan
                                                     $jenis_pengajuan = strtolower($data['jenis_pengajuan'] ?? '');
                                                     $detail_page_target = 'detail_verif.php'; // Default fallback jika jenis_pengajuan tidak terdefinisi
                                                     
                                                     if ($jenis_pengajuan == 'kenaikan') {
                                                         $detail_page_target = 'detail_verif_kenaikan.php';
                                                     } elseif ($jenis_pengajuan == 'perpindahan') {
                                                         $detail_page_target = 'detail_verif_perpindahan.php';
                                                     }
                                                     
                                                     
                                                     // Baris kosong untuk menjaga jumlah baris
                                                     ?>
                                                     <a href="<?php echo $detail_page_target; ?>?id=<?php echo htmlspecialchars($data['id']); ?>" class="btn btn-sm btn-info" title="Detail Dokumen & Verifikasi">
                                                         <i class="fas fa-check-square"></i> Verifikasi
                                                     </a>
                                                 </td>
                                             </tr>
                                             <?php endforeach; ?>
                                         </tbody>
                                     </table>
                                 <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-12">
                        <div class="card card-primary">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-list-ul"></i> Daftar Hasil Uji Kompetensi</h3>
                            </div>
                            <div class="card-body table-responsive">
                                <table id="tabelUjikom" class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>No</th>
                                            <th>Nama Lengkap dan Gelar</th>
                                            <th>NIP</th>
                                            <th>Instansi</th>
                                            <th>Tanggal Uji</th>
                                            <th>Hasil</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($data_ujikom_gagal): ?>
                                            <tr><td colspan='7' class="text-center text-danger font-weight-bold">Gagal memuat data. Periksa koneksi atau pastikan tabel **'<?php echo $NAMA_TABEL_UJIKOM; ?>'** dan **'<?php echo $NAMA_TABEL_PEGAWAI; ?>'** sudah benar.</td></tr>
                                        <?php elseif (empty($final_data_ujikom)): ?>
                                            <tr><td colspan='7' class="text-center text-muted">Belum ada hasil uji kompetensi yang terdaftar.</td></tr>
                                        <?php else: ?>
                                            <?php $no = 1; foreach($final_data_ujikom as $row): ?>
                                                <tr>
                                                    <td><?php echo $no++; ?></td>
                                                    <td><?php echo htmlspecialchars($row['nama_lengkap_gelar'] ?? 'N/A (Pegawai Tidak Ditemukan)'); ?></td>
                                                    <td><?php echo htmlspecialchars($row['nip']); ?></td>
                                                    <td><?php echo htmlspecialchars($row['instansi'] ?? 'N/A'); ?></td>
                                                    <td><?php echo date('d-m-Y', strtotime($row['tanggal_uji'])); ?></td>
                                                    <td>
                                                        <span class="badge badge-<?php echo get_hasil_badge_adminlte($row['hasil']); ?>">
                                                            <?php echo htmlspecialchars($row['hasil']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <a href="detail_verif.php?id_hasil=<?php echo urlencode($row['id']); ?>" class="btn btn-sm btn-info" title="Detail Verifikasi/Edit">
                                                             <i class="fas fa-edit"></i> Edit Hasil
                                                         </a>
                                                         <button class="btn btn-sm btn-danger btn-hapus-ujikom" data-id="<?php echo htmlspecialchars($row['id']); ?>" title="Hapus Data">
                                                             <i class="fas fa-trash"></i> Hapus
                                                         </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </section>
    </div>

    <footer class="main-footer">
        <div class="float-right d-none d-sm-inline">
            Version 3.2
        </div>
        <strong>© <?php echo date('Y'); ?> Instansi Pembina Jabatan Fungsional</strong>
    </footer>
    </div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.10.25/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.10.25/js/dataTables.bootstrap4.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>

<script>
    $(function () {
        // Inisialisasi DataTables untuk Hasil Uji Kompetensi
        $("#tabelUjikom").DataTable({
            "responsive": true, 
            "autoWidth": false,
            "pageLength": 10,
        });

        // Inisialisasi DataTables untuk Pengajuan Baru
        $("#tabelPengajuan").DataTable({
            "responsive": true, 
            "autoWidth": false,
            "pageLength": 10,
            "order": [[4, "desc"]] // Urutkan berdasarkan tanggal pengajuan terbaru
        });
        
        // Logika untuk tombol hapus (Ujikom)
        $('.btn-hapus-ujikom').on('click', function(e) {
            e.preventDefault();
            var id_data = $(this).data('id');
            if (confirm('Anda yakin ingin menghapus data hasil uji kompetensi dengan ID ' + id_data + '? Tindakan ini tidak dapat dibatalkan.')) {
                // Ganti ini dengan logika AJAX/redirect ke script delete_ujikom.php
                alert('Data dengan ID ' + id_data + ' dikirim ke proses hapus.');
                // Contoh: window.location.href = 'delete_ujikom.php?id=' + id_data;
            }
        });
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