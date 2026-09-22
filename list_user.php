<?php
// FILE: list_user.php - Halaman Daftar Pengguna (User Management)
// -------------------------------------------------------------------------
// Fungsi: 
// 1. Menampilkan semua pengguna terdaftar.
// 2. Memberikan akses hanya untuk Super Admin dan Admin.
// 3. Menyediakan aksi untuk mengaktifkan/menonaktifkan (approval) dan mengubah role.
// -------------------------------------------------------------------------

// =========================================================================
// !!! PENGATURAN AWAL & GUARD AKSES !!!
// =========================================================================

// Pastikan output buffering dan session dimulai
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'auth_guard.php'; // Mengandung $user_role_sesi
require_once 'koneksi.php';    // Koneksi database
$template_path = 'template/';

// PENGAMANAN AKSES KRITIS: Hanya Super Admin dan Admin yang boleh melihat halaman ini
if (!in_array($user_role_sesi, ['Super Admin', 'Admin'])) {
    header("Location: index.php"); // Alihkan jika bukan Super Admin/Admin
    exit();
}

// 1. --- PENGATURAN VARIABEL HALAMAN UNTUK SIDEBAR & HEADER ---
$page = 'admin';         
$sub_page = 'list_user';     
$page_title = "Manajemen Pengguna (Approval dan Role)";

// 2. --- LOGIKA PENGAMBILAN DATA PENGGUNA ---
$list_users = [];
$query_error = '';

try {
    // ASUMSI: Tabel pengguna dinamakan 'user' (atau 'users')
    // Kolom yang digunakan: id, nama, email, role, status, tanggal_daftar
    $sql = "SELECT id, nama, email, role, status, DATE_FORMAT(tanggal_daftar, '%d-%m-%Y') AS tgl_daftar 
            FROM user ORDER BY status ASC, tanggal_daftar DESC";
    
    $result = $conn->query($sql);
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $list_users[] = $row;
        }
        $result->free();
    } else {
        $query_error = "Error saat mengambil data pengguna: " . $conn->error;
    }

} catch (Exception $e) {
    $query_error = "Terjadi kesalahan database: " . $e->getMessage();
}


// ----------------------------------------------------------------------------------
// --- PANGGIL HEADER, SIDEBAR, DAN TAMPILAN UTAMA ---
// ----------------------------------------------------------------------------------

// Asumsi template/header.php dan template/sidebar.php ada
include $template_path . 'header.php'; 
include $template_path . 'sidebar.php'; 
?>

<div class="content-wrapper">

    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-12">
                    <h1 class="m-0"><?php echo $page_title; ?></h1> 
                </div>
            </div>
        </div>
    </div>
    <section class="content">
        <div class="container-fluid">
            
            <?php if (!empty($query_error)): ?>
                <div class="alert alert-danger">
                    <strong>Kesalahan Database!</strong> <?php echo $query_error; ?>
                </div>
            <?php endif; ?>

            <?php 
            // Tampilkan pesan sukses/gagal dari redirect aksi_user.php
            if (isset($_SESSION['flash_message'])): ?>
                <div class="alert alert-<?php echo htmlspecialchars($_SESSION['flash_message']['type']); ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($_SESSION['flash_message']['msg']); ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <?php unset($_SESSION['flash_message']); ?>
            <?php endif; ?>

            <div class="row">
                <div class="col-12">
                    <div class="card card-primary card-outline">
                        <div class="card-header">
                            <h3 class="card-title">Daftar Semua Pengguna Terdaftar</h3>
                        </div>
                        <div class="card-body">
                            
                            <?php if (empty($list_users)): ?>
                                <div class="alert alert-info text-center">
                                    Tidak ada data pengguna yang ditemukan.
                                </div>
                            <?php else: ?>
                            
                            <table id="tabelManajemenUser" class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th style="width: 5%;">No</th>
                                        <th style="width: 25%;">Nama / Email</th>
                                        <th style="width: 10%;">Role</th>
                                        <th style="width: 15%;">Tgl. Daftar</th>
                                        <th style="width: 15%;">Status</th>
                                        <th style="width: 30%;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $no = 1; foreach ($list_users as $user): ?>
                                    <tr>
                                        <td><?php echo $no++; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($user['nama']); ?></strong><br>
                                            <small><?php echo htmlspecialchars($user['email']); ?></small>
                                        </td>
                                        <td>
                                            <?php 
                                            // Tampilkan Role dengan Badge
                                            $role_badge = 'secondary';
                                            if ($user['role'] == 'Super Admin') $role_badge = 'danger';
                                            elseif ($user['role'] == 'Admin') $role_badge = 'primary';
                                            elseif ($user['role'] == 'Pengusul') $role_badge = 'info';
                                            ?>
                                            <span class="badge badge-<?php echo $role_badge; ?>"><?php echo htmlspecialchars($user['role']); ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($user['tgl_daftar']); ?></td>
                                        <td>
                                            <?php 
                                            // Tampilkan Status dengan Badge
                                            $status_text = htmlspecialchars($user['status']);
                                            $status_badge = 'secondary'; // Default
                                            if ($user['status'] == 'Aktif') $status_badge = 'success';
                                            elseif ($user['status'] == 'Menunggu Approval') $status_badge = 'warning';
                                            elseif ($user['status'] == 'Nonaktif') $status_badge = 'danger';
                                            ?>
                                            <span class="badge badge-<?php echo $status_badge; ?>"><?php echo $status_text; ?></span>
                                        </td>
                                        <td>
                                            <?php 
                                            // Jangan izinkan aksi pada diri sendiri (penting untuk Super Admin)
                                            $is_self = ($user['id'] == ($_SESSION['user_id_sesi'] ?? 0));
                                            
                                            if (!$is_self): 
                                            ?>
                                                <!-- Aksi Status -->
                                                <?php if ($user['status'] != 'Aktif'): ?>
                                                    <!-- Tombol Aktifkan/Approve -->
                                                    <a href="aksi_user.php?id=<?php echo $user['id']; ?>&action=activate" 
                                                       class="btn btn-success btn-sm mb-1"
                                                       onclick="return confirm('Aktifkan/Setujui pengguna ini?');">
                                                        <i class="fas fa-check"></i> Aktifkan
                                                    </a>
                                                <?php else: ?>
                                                    <!-- Tombol Nonaktifkan -->
                                                    <a href="aksi_user.php?id=<?php echo $user['id']; ?>&action=deactivate" 
                                                       class="btn btn-danger btn-sm mb-1"
                                                       onclick="return confirm('Nonaktifkan pengguna ini?');">
                                                        <i class="fas fa-ban"></i> Nonaktifkan
                                                    </a>
                                                <?php endif; ?>

                                                <!-- Dropdown Ubah Role -->
                                                <div class="btn-group mb-1">
                                                    <button type="button" class="btn btn-info btn-sm dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                        Ubah Role
                                                    </button>
                                                    <div class="dropdown-menu">
                                                        <?php 
                                                        $roles_available = ['Pengusul', 'Admin', 'Super Admin'];
                                                        foreach ($roles_available as $role): 
                                                            if ($role != $user['role']): // Hindari menampilkan role yang sudah dimiliki
                                                        ?>
                                                                <a class="dropdown-item" href="aksi_user.php?id=<?php echo $user['id']; ?>&action=set_role&role=<?php echo urlencode($role); ?>"
                                                                   onclick="return confirm('Yakin ingin mengubah role menjadi <?php echo $role; ?>?');">
                                                                    Set ke: <?php echo $role; ?>
                                                                </a>
                                                        <?php 
                                                            endif; 
                                                        endforeach; 
                                                        ?>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-secondary">Anda tidak dapat mengubah status/role diri sendiri.</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php endif; ?>
                            
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<?php
// Tutup koneksi database
if (isset($conn) && $conn) {
    mysqli_close($conn);
}

// --- PANGGIL FOOTER (Menutup tag body/html dan menambahkan script JS AdminLTE/Datatables) ---
include $template_path . 'footer.php';
// ✅ SOLUSI KRITIS 3: TUTUP OUTPUT BUFFERING
ob_end_flush();
?>

<script>
  // Script untuk Inisialisasi Datatables (Asumsi script Datatables sudah dimuat di footer.php)
  $(function () {
    $("#tabelManajemenUser").DataTable({
      "responsive": true,
      "lengthChange": true, 
      "autoWidth": false,
      // Tambahkan tombol ekspor jika diperlukan
      "buttons": ["copy", "csv", "excel", "pdf", "print", "colvis"] 
    }).buttons().container().appendTo('#tabelManajemenUser_wrapper .col-md-6:eq(0)');
  });
</script>