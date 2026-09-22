<?php
// FILE: verify_otp.php - VERIFIKASI OTP UNTUK RESET PASSWORD (2 Langkah)

session_start();
require_once 'koneksi.php'; 
// config_email tidak diperlukan di sini, tetapi bisa dipertahankan jika ingin fitur kirim ulang

// --- PENTING: ASUMSI NAMA TABEL USERS ---
$NAMA_TABEL_USERS = "users"; 

$message = '';
$is_error = false;
$email_to_verify = $_SESSION['reset_email'] ?? ''; 
$redirect_url = 'login.php';
$step = 1; // 1: Input OTP, 2: Input Password Baru

// Jika tidak ada email reset di sesi, arahkan kembali
if (empty($email_to_verify) && empty($_SESSION['reset_user_id'])) {
    $_SESSION['popup_type'] = 'error';
    $_SESSION['popup_message'] = "❌ Sesi reset password tidak ditemukan. Silakan mulai proses dari awal.";
    header('Location: forgot_password.php');
    exit;
}

// =========================================================
// A. PEMROSESAN POST
// =========================================================
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // --- SKENARIO 1: SUBMIT VERIFIKASI OTP ---
    if (isset($_POST['submit_otp'])) {
        $otp_input = trim($_POST['otp_code'] ?? '');
        
        if (empty($otp_input) || !is_numeric($otp_input) || strlen($otp_input) !== 6) {
            $is_error = true;
            $message = "Kode OTP wajib diisi dan harus 6 digit angka.";
            
        } else {
            // Cek OTP di database, menggunakan kolom reset_token dan reset_expiry
            $sql_fetch = "SELECT id, reset_token, reset_expiry FROM {$NAMA_TABEL_USERS} 
                          WHERE email = ? AND reset_token = ?";
            $stmt_fetch = mysqli_prepare($conn, $sql_fetch);
            mysqli_stmt_bind_param($stmt_fetch, "ss", $email_to_verify, $otp_input);
            mysqli_stmt_execute($stmt_fetch);
            $result = mysqli_stmt_get_result($stmt_fetch);
            $user = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt_fetch);

            if ($user) {
                $otp_expiry_time = strtotime($user['reset_expiry']);
                $current_time = time();

                if ($current_time <= $otp_expiry_time) {
                    // OTP Cocok dan Belum Kedaluwarsa - PINDAH KE LANGKAH 2
                    $step = 2; 
                    $message = "✅ Kode OTP valid. Silakan masukkan password baru Anda.";
                    
                    // Simpan ID pengguna di sesi untuk langkah update password
                    $_SESSION['reset_user_id'] = $user['id']; 

                } else {
                    // OTP Cocok TAPI Kedaluwarsa
                    $is_error = true;
                    $message = "Kode OTP telah **kedaluwarsa** (Telah melewati batas waktu 5 menit). Silakan <a href='forgot_password.php'>minta kode baru</a>.";
                }
            } else {
                // OTP TIDAK COCOK
                $is_error = true;
                $message = "Kode OTP yang Anda masukkan **salah** atau sudah digunakan. Mohon periksa kembali kodenya.";
            }
        }

    // --- SKENARIO 2: SUBMIT PASSWORD BARU (Hanya jika ID tersimpan di sesi) ---
    } elseif (isset($_POST['submit_password']) && isset($_SESSION['reset_user_id'])) {
        $new_password = $_POST['password'] ?? '';
        $confirm_password = $_POST['password_confirm'] ?? '';
        $user_id = $_SESSION['reset_user_id'];
        
        // Atur kembali step ke 2 untuk tampilan form jika terjadi error
        $step = 2;

        if (empty($new_password) || empty($confirm_password)) {
            $is_error = true;
            $message = "Password tidak boleh kosong.";
        } elseif ($new_password !== $confirm_password) {
            $is_error = true;
            $message = "Konfirmasi password tidak cocok.";
        } elseif (strlen($new_password) < 6) { 
            $is_error = true;
            $message = "Password minimal 6 karakter.";
        } else {
            // Hash password baru
            $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            
            // Update password, HAPUS token (Set NULL) dan HAPUS waktu kedaluwarsa
            // Menggunakan kolom 'password_hash' (sesuaikan jika nama kolom Anda berbeda)
            $stmt_update = mysqli_prepare($conn, "UPDATE {$NAMA_TABEL_USERS} SET password_hash = ?, reset_token = NULL, reset_expiry = NULL WHERE id = ?");
            mysqli_stmt_bind_param($stmt_update, "si", $password_hash, $user_id);

            if (mysqli_stmt_execute($stmt_update)) {
                // Bersihkan semua sesi reset
                unset($_SESSION['reset_user_id']);
                unset($_SESSION['reset_email']);

                // Sukses: Alihkan ke halaman login
                $_SESSION['popup_type'] = 'success';
                $_SESSION['popup_message'] = "✅ Password Anda berhasil diubah. Silakan login.";
                header('Location: ' . $redirect_url); 
                exit;
            } else {
                $is_error = true;
                $message = "❌ Gagal memperbarui password di database.";
            }
            mysqli_stmt_close($stmt_update);
        }
    }
}

// Jika setelah pemrosesan POST (Skema 1) berhasil, set step ke 2 secara eksplisit
if (isset($_SESSION['reset_user_id']) && $step !== 2) {
    $step = 2;
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
    <title>Reset Password - Verifikasi OTP</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
</head>
<body class="hold-transition login-page">
<div class="login-box">
    <div class="card card-outline card-primary">
        <div class="card-header text-center">
            <h1><?php echo $step === 1 ? 'Verifikasi OTP' : 'Atur Password Baru'; ?></h1>
        </div>
        <div class="card-body">
            <p class="login-box-msg">
                <?php echo $step === 1 ? "Masukkan Kode OTP yang telah kami kirimkan ke email Anda: <b>" . htmlspecialchars($email_to_verify) . "</b>" : "Masukkan password baru Anda."; ?>
            </p>
            
            <?php if ($message): ?>
            <div class="alert alert-<?php echo $is_error ? 'danger' : 'success'; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <?php endif; ?>

            <?php if ($step === 1): // --- TAMPILAN LANGKAH 1: INPUT OTP --- ?>
                <form action="verify_otp.php" method="post">
                    <div class="input-group mb-3">
                        <input type="text" name="otp_code" class="form-control" placeholder="Kode OTP (6 Digit)" required maxlength="6" pattern="\d{6}" title="Masukkan 6 digit angka OTP">
                        <div class="input-group-append">
                            <div class="input-group-text"><span class="fas fa-lock"></span></div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <button type="submit" name="submit_otp" class="btn btn-primary btn-block">Verifikasi</button>
                        </div>
                    </div>
                </form>

                <p class="mt-3 text-center">
                    <a href="forgot_password.php">Minta Kode Baru</a>
                </p>

            <?php else: // --- TAMPILAN LANGKAH 2: INPUT PASSWORD BARU --- ?>
                <form action="verify_otp.php" method="post">
                    <div class="input-group mb-3">
                        <input type="password" name="password" class="form-control" placeholder="Password Baru (Min. 6)" required>
                        <div class="input-group-append"><div class="input-group-text"><span class="fas fa-lock"></span></div></div>
                    </div>
                    <div class="input-group mb-3">
                        <input type="password" name="password_confirm" class="form-control" placeholder="Ulangi Password Baru" required>
                        <div class="input-group-append"><div class="input-group-text"><span class="fas fa-lock"></span></div></div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <button type="submit" name="submit_password" class="btn btn-success btn-block">Ubah Password</button>
                        </div>
                    </div>
                </form>
            <?php endif; ?>

        </div>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
</body>
</html>