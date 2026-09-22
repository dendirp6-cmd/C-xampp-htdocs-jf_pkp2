<?php
// FILE: edit_user.php - Halaman Edit Pengguna (Admin Only)

// --- AKTIFKAN PELAPORAN ERROR ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- AUTH GUARD DAN KONEKSI ---
require_once 'auth_guard.php'; 
require_once 'koneksi.php'; 

// HANYA IZINKAN ADMIN/SUPER ADMIN
if (($user_role_sesi ?? '') !== 'user_super_admin' && ($user_role_sesi ?? '') !== 'admin') {
    die("Akses ditolak. Anda tidak memiliki izin untuk mengakses halaman ini.");
}

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user_to_edit = null;
$message = '';
$is_error = false;

// 1. Logika Pengambilan Data Pengguna yang Akan Diedit
if ($user_id > 0 && isset($conn)) {
    // PERBAIKAN: Mengubah 'nip' menjadi 'nip_user' agar sesuai database
    $stmt = $conn->prepare("SELECT id, nama, email, role, status, instansi, nip_user FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user_to_edit = $result->fetch_assoc();
    } else {
        $message = "❌ Error: Data pengguna tidak ditemukan.";
        $is_error = true;
    }
    $stmt->close();
} else {
    $message = "❌ Error: ID Pengguna tidak valid.";
    $is_error = true;
}

// 2. Logika Pemrosesan Form POST (Update Data)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_user'])) {
    $new_nama = $_POST['nama'] ?? '';
    $new_role = $_POST['role'] ?? '';
    $new_status = $_POST['status'] ?? '';
    $new_password = $_POST['password_baru'] ?? '';
    $new_instansi = $_POST['instansi'] ?? '';

    // Gunakan prepared statement untuk UPDATE
    $sql = "UPDATE users SET nama=?, role=?, status=?, instansi=?";
    $params = [$new_nama, $new_role, $new_status, $new_instansi];
    $types = "ssss";

    if (!empty($new_password)) {
        // HASH PASSWORD SEBELUM DISIMPAN!
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        // PERBAIKAN: Menyesuaikan kolom password (biasanya password_hash di sistem Anda)
        $sql .= ", password_hash=?"; 
        $params[] = $hashed_password;
        $types .= "s";
    }

    $sql .= " WHERE id = ?";
    $params[] = $user_id;
    $types .= "i";

    try {
        $stmt_update = $conn->prepare($sql);
        $stmt_update->bind_param($types, ...$params);
        
        if ($stmt_update->execute()) {
            $message = "✅ Data pengguna **" . htmlspecialchars($new_nama) . "** berhasil diperbarui!";
            $is_error = false;
            // Refresh data setelah update agar form menampilkan nilai baru
            header("Location: edit_user.php?id={$user_id}&status=success&msg=" . urlencode($message));
            exit();
        } else {
            throw new Exception("Error saat update database: " . $stmt_update->error);
        }
    } catch (Exception $e) {
        $message = "❌ Gagal memperbarui data: " . $e->getMessage();
        $is_error = true;
    } finally {
        if (isset($stmt_update)) $stmt_update->close();
    }
}

// Penanganan pesan dari redirect setelah sukses update
if (isset($_GET['status']) && isset($_GET['msg'])) {
    $message = htmlspecialchars(urldecode($_GET['msg']));
    $is_error = $_GET['status'] === 'error';
}

// Jika data pengguna tidak ditemukan setelah semua proses
if (!$user_to_edit && $user_id > 0 && !$is_error) {
     $message = "❌ Error: Data pengguna tidak ditemukan.";
     $is_error = true;
}

// Daftar Role dan Status yang mungkin (Sesuaikan dengan yang ada di tabel Anda)
$roles = ['user_super_admin', 'admin', 'user_pengusul', 'user_verifikator', 'user_kasubdit', 'user_ppsdm', 'user_direktur'];
$statuses = ['active', 'pending_approval', 'inactive', 'rejected'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Pengguna | Admin</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
    <div class="content-wrapper" style="min-height: 800px; padding-top: 20px;">
        <section class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-md-8 offset-md-2">
                        <div class="card card-info">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-user-edit"></i> Edit Data Pengguna ID: <?php echo htmlspecialchars($user_id); ?></h3>
                            </div>
                            
                            <?php if ($message): ?>
                                <div class="alert <?php echo $is_error ? 'alert-danger' : 'alert-success'; ?> alert-dismissible fade show m-3" role="alert">
                                    <?php echo $message; ?>
                                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                            <?php endif; ?>

                            <?php if ($user_to_edit): ?>
                            <form method="POST" action="edit_user.php?id=<?php echo $user_id; ?>">
                                <input type="hidden" name="update_user" value="1">
                                <div class="card-body">

                                    <div class="form-group">
                                        <label for="email">Email (Username)</label>
                                        <input type="email" id="email" class="form-control" value="<?php echo htmlspecialchars($user_to_edit['email']); ?>" readonly>
                                    </div>

                                    <div class="form-group">
                                        <label for="nip">NIP</label>
                                        <input type="text" id="nip" class="form-control" value="<?php echo htmlspecialchars($user_to_edit['nip_user'] ?? 'N/A'); ?>" readonly>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="nama">Nama Lengkap</label>
                                        <input type="text" id="nama" name="nama" class="form-control" value="<?php echo htmlspecialchars($user_to_edit['nama']); ?>" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="instansi">Instansi / Unit Kerja</label>
                                        <input type="text" id="instansi" name="instansi" class="form-control" value="<?php echo htmlspecialchars($user_to_edit['instansi']); ?>" required>
                                    </div>

                                    <div class="form-group">
                                        <label for="role">Peran (Role)</label>
                                        <select id="role" name="role" class="form-control" required>
                                            <?php foreach ($roles as $role): ?>
                                                <option value="<?php echo $role; ?>" <?php echo ($user_to_edit['role'] == $role ? 'selected' : ''); ?>>
                                                    <?php echo strtoupper(str_replace('_', ' ', $role)); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="form-group">
                                        <label for="status">Status Akun</label>
                                        <select id="status" name="status" class="form-control" required>
                                            <?php foreach ($statuses as $status): ?>
                                                <option value="<?php echo $status; ?>" <?php echo ($user_to_edit['status'] == $status ? 'selected' : ''); ?>>
                                                    <?php echo strtoupper(str_replace('_', ' ', $status)); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="form-group">
                                        <label for="password_baru">Password Baru</label>
                                        <input type="password" id="password_baru" name="password_baru" class="form-control" placeholder="Isi hanya jika ingin mengubah password">
                                    </div>

                                </div>
                                <div class="card-footer">
                                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Perubahan</button>
                                    <a href="pengaturan.php" class="btn btn-secondary float-right">Kembali</a>
                                </div>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
    
    <footer class="main-footer">
        </footer>
</div>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
</body>
</html>