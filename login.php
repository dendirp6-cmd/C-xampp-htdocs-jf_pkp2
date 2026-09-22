<?php
/**
 * FILE: login.php
 * Pembaruan: Sinkronisasi Foto Profil & Logika Auto-Logout 10 Detik
 */

// 1. Pengaturan Cookie Sesi (Harus sebelum session_start)
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '', 
    'secure' => false, 
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_start();
require_once 'koneksi.php'; 

// --- KONFIGURASI TIMEOUT (AUTO LOGOUT JIKA IDLE) ---
$timeout_duration = 3600; // Untuk keperluan testing (Ganti ke 1800 untuk 30 menit nanti)

if (isset($_SESSION['LAST_ACTIVITY'])) {
    if (time() - $_SESSION['LAST_ACTIVITY'] > $timeout_duration) {
        // Simpan pesan dulu sebelum destroy
        session_unset();
        session_destroy();
        
        // Mulai session baru hanya untuk menampilkan popup logout
        session_start();
        $_SESSION['popup_type'] = 'info';
        $_SESSION['popup_message'] = "Sesi Anda telah berakhir karena tidak ada aktivitas.";
        header("Location: login.php");
        exit;
    }
}
// Update waktu aktivitas terakhir setiap kali file ini diakses
$_SESSION['LAST_ACTIVITY'] = time();

// --- DEFINISI NAMA TABEL ---
$NAMA_TABEL_USERS = 'users';

/**
 * FUNGSI PENENTU REDIRECT BERDASARKAN ROLE
 */
function getRedirectUrl($role) {
    if (empty($role) || $role === 'Guest' || $role === '-') {
        return 'waiting_room.php';
    }
    
    switch ($role) {
        case 'user_pengusul':    return 'index_pengusul.php';
        case 'user_verifikator': return 'index_verifikator.php';
        case 'user_ppsdm':       return 'index_ppsdm.php';
        case 'user_kasubdit':    return 'index_kasubdit.php';
        case 'user_direktur':    return 'index_direktur.php';
        case 'user_evaluator':   return 'index_evaluator.php';
        case 'user_admin':       return 'index_asli.php';
        case 'admin':            return 'index_asli.php';
        case 'user_super_admin': return 'index_asli.php';
        default:                 return 'index.php';
    }
}

// Jika pengguna sudah login dan session masih valid, arahkan ke dashboard
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['user_role_sesi'] ?? '';
    header("Location: " . getRedirectUrl($role));
    exit;
}

$message = '';
$is_error = false;
$input_email = ''; 

// --- LOGIKA POPUP DARI SESSION ---
$popup_data = [];
if (isset($_SESSION['popup_message']) && isset($_SESSION['popup_type'])) {
    $popup_data = [
        'type' => $_SESSION['popup_type'],
        'message' => $_SESSION['popup_message'],
    ];
    unset($_SESSION['popup_message']);
    unset($_SESSION['popup_type']);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $input_email = $email;

    if (empty($email) || empty($password)) {
        $is_error = true;
        $message = "Email dan Password wajib diisi.";
    } else {
        $stmt = mysqli_prepare($conn, "SELECT id, nama, email, password, password_hash, role, is_verified, nip_user, instansi, foto FROM {$NAMA_TABEL_USERS} WHERE email = ?");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($user = mysqli_fetch_assoc($result)) {
            $authenticated = false;
            if (password_verify($password, $user['password_hash'])) {
                $authenticated = true;
            } elseif (md5($password) === $user['password']) { 
                $authenticated = true;
            }

            if ($authenticated) {
                if ($user['is_verified'] == 0) {
                    $is_error = true;
                    $message = "❌ Akun Anda belum diverifikasi.";
                } else {
                    // Login Berhasil!
                    session_regenerate_id(true);

                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_id_sesi'] = $user['id']; 
                    $_SESSION['user_name'] = $user['nama'];
                    $_SESSION['nip'] = $user['nip_user'];
                    $_SESSION['instansi'] = $user['instansi'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_role'] = $user['role']; 
                    $_SESSION['user_nama_sesi'] = $user['nama'];
                    $_SESSION['user_role_sesi'] = $user['role']; 
                    $_SESSION['foto_user_sesi'] = !empty($user['foto']) ? $user['foto'] : '';
                    
                    // Inisialisasi waktu aktivitas saat login sukses
                    $_SESSION['LAST_ACTIVITY'] = time(); 
                    
                    $_SESSION['popup_type'] = 'success';
                    $_SESSION['popup_message'] = "Selamat Datang kembali, **{$user['nama']}**! Anda berhasil login.";

                    header("Location: " . getRedirectUrl($user['role']));
                    exit;
                }
            } else {
                $is_error = true;
                $message = "❌ Email atau Password salah.";
            }
        } else {
            $is_error = true;
            $message = "❌ Email atau Password salah.";
        }
        mysqli_stmt_close($stmt);
    }
}
if (isset($conn) && $conn) { mysqli_close($conn); }
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login | Instansi Pembina JF</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <style>
        .login-page { background-image: url('assets/bg.jpeg'); background-repeat: repeat; background-size: auto; background-color: #2e5975; }
        .top-left-logo { position: fixed; top: 25px; left: 25px; max-height: 50px; width: auto; z-index: 1050; }
        .login-internal-logo { max-height: 120px; width: auto; margin-top: -30px !important; display: block; margin: 0 auto 0px auto; }
        .custom-title-internal { font-size: 1.8rem; font-weight: 700 !important; margin-bottom: 0px; line-height: 1.5; }
        .custom-title-internal .text-coklat { font-family:'Montserrat', sans-serif; color: #313030ff !important; }
        .btn-primary { border-radius: 10px !important; }
        .login-box { width: 350px; }
    </style>
</head>
<body class="hold-transition login-page">
<img src="assets/logo_pkp2.png" alt="Logo Instansi" class="top-left-logo">
<div class="login-box">
    <div class="card">
        <div class="card-body login-card-body text-center">  
            <img src="assets/kemenPKP.png" alt="Logo KemenPKP" class="login-internal-logo">
            <p class="custom-title-internal"><span class="text-coklat">Login Akun</span></p>
            <p class="login-box-msg">Masuk untuk memulai sesi Anda!</p>

            <?php if ($is_error && $message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <?php endif; ?>

            <form action="login.php" method="post">
                <div class="input-group mb-3">
                    <input type="email" name="email" class="form-control" placeholder="Email" required value="<?php echo htmlspecialchars($input_email); ?>">
                    <div class="input-group-append"><div class="input-group-text"><span class="fas fa-envelope"></span></div></div>
                </div>
                <div class="input-group mb-3">
                    <input type="password" name="password" class="form-control" placeholder="Password" required>
                    <div class="input-group-append"><div class="input-group-text"><span class="fas fa-lock"></span></div></div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary btn-block">Sign In</button>
                    </div>
                </div>
            </form>
            <p class="mb-0 mt-3"><a href="forgot_password.php"> Lupa Password?</a></p>
            <p class="mb-0"><a href="register.php" class="text-center">Daftar Akun Baru</a></p>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>

<?php if (!empty($popup_data)): ?>
<div class="modal fade" id="sessionModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-<?php echo ($popup_data['type'] == 'success' ? 'success' : 'info'); ?> text-white">
                <h5 class="modal-title"><i class="fas fa-check-circle"></i> Notifikasi</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true" style="color:white">&times;</span></button>
            </div>
            <div class="modal-body text-center"><p><?php echo $popup_data['message']; ?></p></div>
            <div class="modal-footer justify-content-center"><button type="button" class="btn btn-primary" data-dismiss="modal">OK</button></div>
        </div>
    </div>
</div>
<script>$(document).ready(function() { $('#sessionModal').modal('show'); });</script>
<?php endif; ?>
</body>
</html>