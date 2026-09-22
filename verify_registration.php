<?php
// FILE: verify_registration.php - VERIFIKASI OTP UNTUK PENDAFTARAN AKUN

session_start();
require_once 'koneksi.php'; 

// --- PENTING: ASUMSI NAMA TABEL USERS ---
$NAMA_TABEL_USERS = "users"; 

$message = '';
$is_error = false;

// Ambil email dari sesi yang dibuat oleh register.php
$email_to_verify = $_SESSION['verify_email'] ?? ''; 
$redirect_url = 'login.php';

// Jika tidak ada email verifikasi di sesi, arahkan kembali ke register
if (empty($email_to_verify)) {
    $_SESSION['popup_type'] = 'error';
    $_SESSION['popup_message'] = "❌ Sesi pendaftaran tidak ditemukan. Silakan mulai pendaftaran dari awal.";
    header('Location: register.php');
    exit;
}

// =========================================================
// A. PEMROSESAN POST
// =========================================================
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // --- SKENARIO 1: SUBMIT VERIFIKASI OTP ---
    $otp_input = trim($_POST['otp_code'] ?? '');
    
    if (empty($otp_input) || !is_numeric($otp_input) || strlen($otp_input) !== 6) {
        $is_error = true;
        $message = "Kode OTP wajib diisi dan harus 6 digit angka.";
        
    } else {
        // Cek OTP di database, menggunakan kolom otp_code dan otp_expiry (dari register.php)
        $sql_fetch = "SELECT id, otp_code, otp_expiry FROM {$NAMA_TABEL_USERS} 
                      WHERE email = ? AND otp_code = ? AND is_verified = 0";
        $stmt_fetch = mysqli_prepare($conn, $sql_fetch);
        mysqli_stmt_bind_param($stmt_fetch, "ss", $email_to_verify, $otp_input);
        mysqli_stmt_execute($stmt_fetch);
        $result = mysqli_stmt_get_result($stmt_fetch);
        $user = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt_fetch);

        if ($user) {
            $otp_expiry_time = strtotime($user['otp_expiry']);
            $current_time = time();

            if ($current_time <= $otp_expiry_time) {
                // OTP Cocok dan Belum Kedaluwarsa - AKSI: VERIFIKASI AKUN
                
                // Update: Set is_verified = 1, dan HAPUS kolom OTP
                $stmt_update = mysqli_prepare($conn, "UPDATE {$NAMA_TABEL_USERS} SET is_verified = 1, otp_code = NULL, otp_expiry = NULL WHERE id = ?");
                mysqli_stmt_bind_param($stmt_update, "i", $user['id']);

                if (mysqli_stmt_execute($stmt_update)) {
                    // Bersihkan sesi registrasi
                    unset($_SESSION['verify_email']);

                    // Sukses: Alihkan ke halaman login
                    $_SESSION['popup_type'] = 'success';
                    $_SESSION['popup_message'] = "✅ Akun Anda berhasil diverifikasi! Silakan login.";
                    header('Location: ' . $redirect_url); 
                    exit;
                } else {
                    $is_error = true;
                    $message = "❌ Gagal mengaktifkan akun di database.";
                }
                mysqli_stmt_close($stmt_update);

            } else {
                // OTP Kedaluwarsa
                $is_error = true;
                $message = "Kode OTP telah **kedaluwarsa** (Telah melewati batas waktu 5 menit). Silakan <a href='register.php'>coba daftar lagi</a>.";
            }
        } else {
            // OTP TIDAK COCOK
            $is_error = true;
            $message = "Kode OTP yang Anda masukkan **salah**. Mohon periksa kembali kodenya.";
        }
    }
}

// Tutup koneksi database
if (isset($conn) && $conn) {
    mysqli_close($conn);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verifikasi Akun</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
</head>
<body class="hold-transition login-page">
<div class="login-box">
    <div class="card card-outline card-primary">
        <div class="card-header text-center">
            <h1>Verifikasi Akun</h1>
        </div>
        <div class="card-body">
            <p class="login-box-msg">
                Masukkan Kode OTP yang telah kami kirimkan ke email Anda: <b><?php echo htmlspecialchars($email_to_verify); ?></b>
            </p>
            
            <?php if ($message): ?>
            <div class="alert alert-<?php echo $is_error ? 'danger' : 'success'; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <?php endif; ?>

            <form action="verify_registration.php" method="post">
                <div class="input-group mb-3">
                    <input type="text" name="otp_code" class="form-control" placeholder="Kode OTP (6 Digit)" required maxlength="6" pattern="\d{6}" title="Masukkan 6 digit angka OTP">
                    <div class="input-group-append">
                        <div class="input-group-text"><span class="fas fa-lock"></span></div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <button type="submit" name="submit_otp" class="btn btn-primary btn-block">Verifikasi Akun</button>
                    </div>
                </div>
            </form>

            <p class="mt-3 text-center">
                <a href="register.php">Minta Kode Baru (Ulangi Pendaftaran)</a>
            </p>

        </div>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
</body>
</html>