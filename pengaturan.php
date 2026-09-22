<?php
/**
 * FILE: pengaturan.php - Halaman Pengaturan (Profil & Sistem)
 * DESKRIPSI: Mengatur profil pengguna dan konfigurasi sistem (khusus admin).
 */

// =========================================================
// 1. PENGATURAN AWAL: SESSION & ERROR REPORTING
// =========================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- AKTIFKAN PELAPORAN ERROR (DEBUGGING) ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// -------------------------------------------------------------
// --- PENTING: DEKLARASI VARIABEL UNTUK HIGHLIGHT MENU SIDEBAR ---
$page = 'pengaturan';
$sub_page = '';
$page_title = 'Pengaturan Aplikasi & Profil';
// -------------------------------------------------------------

// --- AUTH GUARD ---
require_once 'auth_guard.php';

// Memuat koneksi database
require_once 'koneksi.php';

$message = '';
$is_error = false;
$NAMA_TABEL_PENGGUNA = 'users'; 
$NAMA_TABEL_PENGATURAN = 'settings'; 

// --- DAFTAR ROLE YANG TERSEDIA ---
$list_roles = [
    'user_super_admin',
    'user_admin',
    'user_pengusul',
    'user_verifikator',
    'user_evaluator',
    'user_ppsdm',
    'user_kasubdit',
    'user_direktur'
];

// --- AMBIL DATA DARI SESI ---
$user_data = [
    'id' => $_SESSION['user_id_sesi'] ?? 0,
    'nama' => $_SESSION['user_nama_sesi'] ?? 'Pengguna JF',
    'role' => $_SESSION['user_role_sesi'] ?? 'user_pengusul',
    'email' => $_SESSION['user_email_sesi'] ?? 'user@instansi.go.id',
    'nip' => $_SESSION['user_nip_sesi'] ?? 'NIP Tidak Diketahui',
    'instansi' => $_SESSION['user_instansi_sesi'] ?? 'Instansi Tidak Diketahui', 
    'join_date' => $_SESSION['join_date_sesi'] ?? date('Y-m-d'),
    'foto' => 'default-avatar.png'
];

// Logika penentu akses admin (Super Admin dan Admin biasa)
$is_admin_access = in_array($user_data['role'], ['user_super_admin', 'user_admin']);

// =========================================================
// 2. LOGIKA UPDATE & EXPORT DATA (POST)
// =========================================================
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // LOGIKA EXPORT EXCEL (ADMIN ONLY)
    if (isset($_POST['export_excel']) && $is_admin_access) {
        // Membersihkan buffer agar tidak ada spasi/html yang ikut masuk ke file excel
        if (ob_get_length()) ob_clean(); 
        
        $filename = "Data_Pengguna_JFPKP_" . date('Ymd_His') . ".xls";
        header("Content-Type: application/vnd.ms-excel");
        header("Content-Disposition: attachment; filename=\"$filename\"");
        header("Pragma: no-cache");
        header("Expires: 0");

        echo '<table border="1">';
        echo '<tr style="background-color: #0D8ABC; color: #ffffff;">
                <th>ID</th>
                <th>Nama Lengkap</th>
                <th>Email</th>
                <th>NIP</th>
                <th>Instansi / Unit Kerja</th>
                <th>Role</th>
                <th>Status</th>
              </tr>';

        $sql_export = "SELECT id, nama, email, nip_user, role, status, instansi FROM {$NAMA_TABEL_PENGGUNA} ORDER BY id ASC";
        $res_export = mysqli_query($conn, $sql_export);
        if ($res_export) {
            while ($row = mysqli_fetch_assoc($res_export)) {
                echo '<tr>';
                echo '<td>' . $row['id'] . '</td>';
                echo '<td>' . htmlspecialchars($row['nama']) . '</td>';
                echo '<td>' . htmlspecialchars($row['email']) . '</td>';
                // Mencegah excel merubah NIP menjadi format scientific (1.99E+17)
                echo '<td style="mso-number-format:\'\@\';">' . htmlspecialchars($row['nip_user'] ?? '-') . '</td>';
                echo '<td>' . htmlspecialchars($row['instansi'] ?? 'N/A') . '</td>';
                echo '<td>' . htmlspecialchars(str_replace('user_', '', $row['role'])) . '</td>';
                echo '<td>' . htmlspecialchars($row['status']) . '</td>';
                echo '</tr>';
            }
        }
        echo '</table>';
        exit; // Hentikan eksekusi script setelah excel terdownload
    }

    // UPDATE PROFIL
    elseif (isset($_POST['update_profil'])) {
        $new_nama = trim($_POST['nama_user'] ?? '');
        $new_email = trim($_POST['email_user'] ?? '');
        $new_nip = trim($_POST['nip_user'] ?? '');
        $new_instansi = trim($_POST['instansi_user'] ?? '');
        $new_password = $_POST['password_baru'] ?? '';
        $user_id = $user_data['id'];

        if (empty($new_nama) || empty($new_email)) {
            $is_error = true;
            $message = "Nama dan Email tidak boleh kosong.";
        } elseif (!empty($user_id) && isset($conn)) {
            if (!empty($new_password)) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $sql_update = "UPDATE {$NAMA_TABEL_PENGGUNA} SET nama = ?, email = ?, nip_user = ?, instansi = ?, password_hash = ? WHERE id = ?";
                $stmt = $conn->prepare($sql_update);
                $stmt->bind_param("sssssi", $new_nama, $new_email, $new_nip, $new_instansi, $hashed_password, $user_id);
            } else {
                $sql_update = "UPDATE {$NAMA_TABEL_PENGGUNA} SET nama = ?, email = ?, nip_user = ?, instansi = ? WHERE id = ?";
                $stmt = $conn->prepare($sql_update);
                $stmt->bind_param("ssssi", $new_nama, $new_email, $new_nip, $new_instansi, $user_id);
            }
            
            if ($stmt && $stmt->execute()) {
                $message = "✅ Perubahan profil berhasil disimpan!";
                // Update Session agar perubahan langsung terlihat di navbar/sidebar
                $_SESSION['user_nama_sesi'] = $new_nama;
                $_SESSION['user_email_sesi'] = $new_email;
                $_SESSION['user_nip_sesi'] = $new_nip;
                $_SESSION['user_instansi_sesi'] = $new_instansi;
            } else {
                $is_error = true;
                $message = "❌ Gagal menyimpan perubahan: " . ($stmt ? $stmt->error : 'Database error');
            }
            if ($stmt) $stmt->close();
        }
    }

    // UPDATE STATUS USER (ADMIN ONLY)
    elseif (isset($_POST['update_status_user']) && $is_admin_access) {
        $target_user_id = intval($_POST['target_user_id'] ?? 0);
        $new_status = $_POST['new_status'] ?? '';

        if ($target_user_id > 0 && !empty($new_status)) {
            $sql_status = "UPDATE {$NAMA_TABEL_PENGGUNA} SET status = ? WHERE id = ?";
            $stmt_status = $conn->prepare($sql_status);
            if ($stmt_status) {
                $stmt_status->bind_param("si", $new_status, $target_user_id);
                if ($stmt_status->execute()) {
                    $message = "✅ Status pengguna berhasil diperbarui!";
                    $is_error = false;
                }
                $stmt_status->close();
            }
        }
    }

    // UPDATE ROLE USER (ADMIN ONLY)
    elseif (isset($_POST['update_role_user']) && $is_admin_access) {
        $target_user_id = intval($_POST['target_user_id'] ?? 0);
        $new_role = $_POST['new_role'] ?? '';

        if ($target_user_id > 0 && !empty($new_role) && in_array($new_role, $list_roles)) {
            $sql_role = "UPDATE {$NAMA_TABEL_PENGGUNA} SET role = ? WHERE id = ?";
            $stmt_role = $conn->prepare($sql_role);
            if ($stmt_role) {
                $stmt_role->bind_param("si", $new_role, $target_user_id);
                if ($stmt_role->execute()) {
                    $message = "✅ Role pengguna berhasil diperbarui!";
                    $is_error = false;
                }
                $stmt_role->close();
            }
        }
    }

    // LOGIKA UPDATE ROLE MASSAL (ADMIN ONLY)
    elseif (isset($_POST['update_role_massal']) && $is_admin_access) {
        $selected_ids = $_POST['selected_users'] ?? [];
        $mass_role = $_POST['mass_role'] ?? '';

        if (!empty($selected_ids) && !empty($mass_role) && in_array($mass_role, $list_roles)) {
            $ids_placeholder = implode(',', array_fill(0, count($selected_ids), '?'));
            $sql_mass = "UPDATE {$NAMA_TABEL_PENGGUNA} SET role = ? WHERE id IN ($ids_placeholder)";
            $stmt_mass = $conn->prepare($sql_mass);
            
            if ($stmt_mass) {
                $types = 's' . str_repeat('i', count($selected_ids));
                $params = array_merge([$mass_role], array_map('intval', $selected_ids));
                $stmt_mass->bind_param($types, ...$params);
                
                if ($stmt_mass->execute()) {
                    $message = "✅ Berhasil memperbarui role untuk " . count($selected_ids) . " pengguna!";
                    $is_error = false;
                } else {
                    $is_error = true;
                    $message = "❌ Gagal memperbarui role secara massal.";
                }
                $stmt_mass->close();
            }
        } else {
            $is_error = true;
            $message = "❌ Mohon pilih pengguna dan role tujuan.";
        }
    }
}

// --- REFRESH DATA PROFIL DARI DB SETELAH UPDATE ---
$stmt_profil = null;
if (!empty($user_data['id']) && isset($conn)) {
    try {
        $sql_profil = "SELECT nama, role, email, nip_user, instansi, join_date, foto FROM {$NAMA_TABEL_PENGGUNA} WHERE id = ? LIMIT 1";
        $stmt_profil = $conn->prepare($sql_profil);
        if ($stmt_profil) {
            $stmt_profil->bind_param("i", $user_data['id']);
            $stmt_profil->execute();
            $result_profil = $stmt_profil->get_result();
            if ($row_profil = $result_profil->fetch_assoc()) {
                $user_data['nama'] = htmlspecialchars($row_profil['nama']);
                $user_data['role'] = htmlspecialchars($row_profil['role']);
                $user_data['email'] = htmlspecialchars($row_profil['email']);
                $user_data['nip'] = htmlspecialchars($row_profil['nip_user'] ?? '-');
                $user_data['instansi'] = htmlspecialchars($row_profil['instansi'] ?? 'Instansi Tidak Diketahui');
                $user_data['foto'] = (!empty($row_profil['foto'])) ? $row_profil['foto'] : 'default-avatar.png';
            }
        }
    } catch (Exception $e) { error_log($e->getMessage()); } finally { if ($stmt_profil) $stmt_profil->close(); }
}

// Logika URL Foto
$path_foto_asli = "assets/profile/" . $user_data['foto'];
if (!empty($user_data['foto']) && file_exists(__DIR__ . "/assets/profile/" . $user_data['foto'])) {
    $url_foto_final = $path_foto_asli . "?t=" . time();
} else {
    $url_foto_final = "https://ui-avatars.com/api/?name=" . urlencode($user_data['nama']) . "&background=0D8ABC&color=fff&size=128";
}

// Data Sistem Default
$current_nama_aplikasi = "SI JF Penata Kelola Perumahan";
$current_versiapp = "1.0.3";
$current_status_pemeliharaan = "Nonaktif";

// LOGIKA LIST PENGGUNA (HANYA UNTUK ADMIN)
$users_list = [];
if ($is_admin_access && isset($conn)) {
    $sql_users = "SELECT id, nama, email, nip_user, role, status, instansi, join_date FROM {$NAMA_TABEL_PENGGUNA} ORDER BY id DESC";
    $result_users = mysqli_query($conn, $sql_users);
    if ($result_users) {
        while ($row = mysqli_fetch_assoc($result_users)) { $users_list[] = $row; }
        mysqli_free_result($result_users);
    }
}

// Menangani pesan GET
if (isset($_GET['msg_type']) && isset($_GET['msg_text'])) {
    $is_error = ($_GET['msg_type'] === 'error');
    $message = htmlspecialchars($_GET['msg_text']);
}

// =========================================================
// 3. STRUKTUR TAMPILAN
// =========================================================
require_once 'template/header.php'; 
require_once 'template/navbar.php'; 
require_once 'template/sidebar.php'; 
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0"><i class="fas fa-cog"></i> <?php echo $page_title; ?></h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right" style="background-color: transparent;">
                        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                        <li class="breadcrumb-item active">Pengaturan</li>
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

            <div class="row">
                <div class="<?php echo $is_admin_access ? 'col-md-6' : 'col-md-12'; ?>">
                    <div class="card card-info">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-user-circle"></i> Profil Pengguna</h3>
                        </div>
                        <form method="POST" action="">
                            <input type="hidden" name="update_profil" value="1">
                            <div class="card-body">
                                <div class="text-center mb-4">
                                    <div class="d-inline-block elevation-2" style="width: 100px; height: 100px; overflow: hidden; border-radius: 50%;">
                                        <img src="<?= $url_foto_final; ?>" alt="User Photo" style="width: 100%; height: 100%; object-fit: cover;">
                                    </div>
                                    <h4 class="mt-2 mb-0"><?php echo $user_data['nama']; ?></h4>
                                    <span class="badge badge-primary"><?php echo strtoupper(str_replace('user_', '', $user_data['role'])); ?></span>
                                    <br>
                                    <a href="profile.php" class="btn btn-xs btn-outline-primary mt-2">Ubah Foto di Profil</a>
                                </div>

                                <div class="form-group">
                                    <label for="nama_user">Nama Lengkap</label>
                                    <input type="text" id="nama_user" name="nama_user" class="form-control" value="<?php echo $user_data['nama']; ?>" required>
                                </div>

                                <div class="form-group">
                                    <label for="email_user">Email</label>
                                    <input type="email" id="email_user" name="email_user" class="form-control" value="<?php echo $user_data['email']; ?>" required>
                                </div>

                                <div class="form-group">
                                    <label for="nip_user">NIP</label>
                                    <input type="text" id="nip_user" name="nip_user" class="form-control" value="<?php echo $user_data['nip']; ?>">
                                </div>

                                <div class="form-group">
                                    <label for="instansi_user">Instansi / Unit Kerja</label>
                                    <input type="text" id="instansi_user" name="instansi_user" class="form-control" value="<?php echo $user_data['instansi']; ?>">
                                </div>

                                <div class="form-group">
                                    <label for="password_baru">Password Baru</label>
                                    <input type="password" id="password_baru" name="password_baru" class="form-control" placeholder="Isi hanya jika ingin mengubah password">
                                    <small class="text-muted">Kosongkan jika tidak ingin mengganti password.</small>
                                </div>
                            </div>
                            <div class="card-footer">
                                <button type="submit" class="btn btn-primary" name="update_profil"><i class="fas fa-save"></i> Simpan Perubahan Profil</button>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if ($is_admin_access): ?>
                <div class="col-md-6">
                    <div class="card card-warning">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-tools"></i> Pengaturan Sistem Aplikasi</h3>
                        </div>
                        <form method="POST" action="">
                            <input type="hidden" name="update_sistem" value="1">
                            <div class="card-body">
                                <div class="form-group">
                                    <label for="nama_aplikasi">Nama Aplikasi</label>
                                    <input type="text" id="nama_aplikasi" name="nama_aplikasi" class="form-control" value="<?php echo htmlspecialchars($current_nama_aplikasi); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="versiapp">Versi Aplikasi</label>
                                    <input type="text" id="versiapp" name="versiapp" class="form-control" value="<?php echo htmlspecialchars($current_versiapp); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="status_pemeliharaan">Status Pemeliharaan</label>
                                    <select id="status_pemeliharaan" name="status_pemeliharaan" class="form-control">
                                        <option value="Nonaktif" <?php echo ($current_status_pemeliharaan == 'Nonaktif' ? 'selected' : ''); ?>>Nonaktif</option>
                                        <option value="Aktif" <?php echo ($current_status_pemeliharaan == 'Aktif' ? 'selected' : ''); ?>>Aktif</option>
                                    </select>
                                </div>
                            </div>
                            <div class="card-footer">
                                <button type="submit" class="btn btn-warning" name="update_sistem"><i class="fas fa-sync-alt"></i> Update Sistem</button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($is_admin_access): ?>
            <div class="row mt-4">
                <div class="col-md-12">
                    <div class="card card-primary">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-users"></i> Daftar Pengguna (Administrasi)</h3>
                            <!-- TOMBOL EXPORT EXCEL DITAMBAHKAN DI SINI -->
                            <div class="card-tools">
                                <form method="POST" action="" class="m-0">
                                    <button type="submit" name="export_excel" class="btn btn-sm btn-success">
                                        <i class="fas fa-file-excel"></i> Export Excel
                                    </button>
                                </form>
                            </div>
                        </div>
                        <div class="card-body">
                            <form id="formMassal" method="POST" action="">
                                <div class="row mb-3 p-2 bg-light border rounded">
                                    <div class="col-md-6 d-flex align-items-center">
                                        <h6 class="mb-0 mr-3 text-bold text-primary">Aksi Massal:</h6>
                                        <select name="mass_role" class="form-control form-control-sm mr-2" style="width: 200px;">
                                            <option value="" disabled selected>Pilih Role Tujuan...</option>
                                            <?php foreach($list_roles as $r): ?>
                                                <option value="<?= $r ?>"><?= ucwords(str_replace(['user_', '_'], ['', ' '], $r)) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" name="update_role_massal" class="btn btn-sm btn-primary" onclick="return confirm('Update role untuk pengguna terpilih?')">
                                            <i class="fas fa-users-cog"></i> Update Role Terpilih
                                        </button>
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table id="usersTable" class="table table-bordered table-striped">
                                        <thead>
                                            <tr>
                                                <th width="40px" class="text-center"><input type="checkbox" id="checkAll"></th>
                                                <th>ID</th>
                                                <th>Nama / Email</th>
                                                <th>NIP</th> 
                                                <th>Instansi</th>
                                                <th>Role</th>
                                                <th>Status</th>
                                                <th>Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($users_list as $user): 
                                                $st = $user['status'] ?? '';
                                                $status_class = match($st) {
                                                    'active' => 'success',
                                                    'pending_approval' => 'warning',
                                                    'inactive', 'blocked', 'rejected', 'Tidak Lulus' => 'danger',
                                                    default => 'secondary'
                                                };
                                            ?>
                                            <tr>
                                                <td class="text-center"><input type="checkbox" name="selected_users[]" value="<?php echo $user['id']; ?>"></td>
                                                <td><?php echo $user['id']; ?></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($user['nama']); ?></strong><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($user['email']); ?></small>
                                                </td>
                                                <td><?php echo htmlspecialchars($user['nip_user'] ?? '-'); ?></td> 
                                                <td><?php echo htmlspecialchars($user['instansi'] ?? 'N/A'); ?></td>
                                                <td><span class="badge badge-info"><?php echo str_replace('user_', '', htmlspecialchars($user['role'])); ?></span></td>
                                                <td><span class="badge badge-<?php echo $status_class; ?>"><?php echo strtoupper(str_replace('_', ' ', htmlspecialchars($user['status'] ?? 'UNKNOWN'))); ?></span></td>
                                                <td>
                                                    <div class="d-flex align-items-center" style="gap: 5px;">
                                                        <form method="POST" action="" class="m-0">
                                                            <input type="hidden" name="update_status_user" value="1">
                                                            <input type="hidden" name="target_user_id" value="<?php echo $user['id']; ?>">
                                                            <select name="new_status" class="form-control form-control-sm" onchange="this.form.submit()" style="width: 110px;">
                                                                <option value="" disabled selected>Status</option>
                                                                <option value="active">🟢 Active</option>
                                                                <option value="pending_approval">🟡 Pending</option>
                                                                <option value="inactive">⚪ Inactive</option>
                                                                <option value="rejected">❌ Rejected</option>
                                                                <option value="Tidak Lulus">❌ Tidak lulus</option>
                                                            </select>
                                                        </form>

                                                        <form method="POST" action="" class="m-0">
                                                            <input type="hidden" name="update_role_user" value="1">
                                                            <input type="hidden" name="target_user_id" value="<?php echo $user['id']; ?>">
                                                            <select name="new_role" class="form-control form-control-sm" onchange="this.form.submit()" style="width: 120px;">
                                                                <option value="" disabled selected>Role</option>
                                                                <?php foreach($list_roles as $r): ?>
                                                                    <option value="<?= $r ?>" <?= ($user['role'] == $r) ? 'selected' : '' ?>>
                                                                        <?= ucwords(str_replace(['user_', '_'], ['', ' '], $r)) ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </form>

                                                        <div class="btn-group">
                                                            <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-primary"><i class="fas fa-edit"></i></a>
                                                            <a href="aksi_user.php?action=delete&id=<?php echo $user['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Hapus?');"><i class="fas fa-trash"></i></a>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/js/adminlte.min.js"></script>
<script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.10.24/js/dataTables.bootstrap4.min.js"></script>

<script>
    $(document).ready(function() {
        if ($('#usersTable').length) {
            var table = $('#usersTable').DataTable({
                "responsive": true, "autoWidth": false, "order": [[1, "desc"]],
                "columnDefs": [{ "orderable": false, "targets": [0, 7] }],
                "language": { "search": "Cari:", "lengthMenu": "_MENU_ data", "info": "_TOTAL_ pengguna" }
            });

            $('#checkAll').on('click', function() {
                var rows = table.rows({ 'search': 'applied' }).nodes();
                $('input[type="checkbox"]', rows).prop('checked', this.checked);
            });
        }
    });
</script>

<?php 
require_once 'template/footer.php'; 
if (isset($conn)) mysqli_close($conn); 
?>