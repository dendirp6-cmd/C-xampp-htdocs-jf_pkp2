<?php
// FILE: forgot_password.php (Versi OTP)

session_start();
require_once 'koneksi.php'; 
require_once 'config_email.php'; // Berisi send_otp_email()

$message = '';

// URL ini tidak digunakan untuk link, tapi untuk redirect internal
$project_root_url = "http://localhost/jf_pkp2/"; // <--- TETAPKAN INI

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $message = "Email tidak boleh kosong.";
    } else {
        // --- 1. Cek Email di Database ---
        $stmt_check = mysqli_prepare($conn, "SELECT id, nama FROM users WHERE email = ?");
        mysqli_stmt_bind_param($stmt_check, "s", $email);
        mysqli_stmt_execute($stmt_check);
        $result = mysqli_stmt_get_result($stmt_check);

        if ($user = mysqli_fetch_assoc($result)) {
            $user_id = $user['id'];
            $user_nama = $user['nama'];

            // --- 2. Hasilkan OTP (6 Digit Angka) & Expiry (5 Menit) ---
            // Menggunakan angka acak untuk OTP
            $otp_code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
            // Waktu kedaluwarsa: Waktu saat ini + 300 detik (5 menit)
            $expires = date('Y-m-d H:i:s', time() + 300); 

            // --- 3. Simpan OTP ke Database (di kolom reset_token) ---
            $stmt_insert = mysqli_prepare($conn, "UPDATE users SET reset_token = ?, reset_expiry = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt_insert, "ssi", $otp_code, $expires, $user_id);
            mysqli_stmt_execute($stmt_insert);
            mysqli_stmt_close($stmt_insert);

            // --- 4. Kirim Email OTP ---
            // Panggil fungsi yang sudah Anda miliki: send_otp_email
            $email_result = send_otp_email($email, $user_nama, $otp_code); 

            if ($email_result['success']) {
                // REDIRECT ke halaman verifikasi OTP
                $_SESSION['reset_email'] = $email; // Simpan email di sesi untuk verify_otp.php
                $_SESSION['popup_type'] = 'success';
                $_SESSION['popup_message'] = "✅ Kode OTP telah dikirim ke **{$email}**. Masukkan OTP di halaman berikutnya.";
                
                // Arahkan pengguna ke halaman verifikasi
                header('Location: verify_otp.php?email=' . urlencode($email));
                exit;
            } else {
                $message = "❌ Gagal mengirim email OTP: " . $email_result['message'];
            }
        } else {
            // Pesan umum
            $message = "✅ Kode OTP telah dikirim ke email jika terdaftar.";
        }
        mysqli_stmt_close($stmt_check);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lupa Password</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<style>
/* CSS Latar Belakang Baru */
.login-page {
    /* Menghapus background image */
    background-image: none !important;
    background-color: #d2d2d2ff !important; /* Warna Light Grey / Putih Kebiruan */
}
/* Menyesuaikan kotak login agar kontras dengan background baru */
.card-primary .card-header {
    border-top: 2px solid #89b1dcff; /* Garis biru default AdminLTE */
}
</style>
</head>
<body class="hold-transition login-page">
<div class="login-box">
    <div class="card card-outline card-primary">
        <div class="card-header text-center">
            <a href="#" class="h1"><b>Reset</b> Password</a>
        </div>
        <div class="card-body">
            <p class="login-box-msg">Masukkan email Anda untuk menerima Kode OTP.</p>

            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo strpos($message, '✅') !== false ? 'success' : 'danger'; ?>" role="alert">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <form action="forgot_password.php" method="post">
                <div class="input-group mb-3">
                    <input type="email" name="email" class="form-control" placeholder="Email" required>
                    <div class="input-group-append"><div class="input-group-text"><span class="fas fa-envelope"></span></div></div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary btn-block">Kirim Kode OTP</button>
                    </div>
                </div>
            </form>
            <p class="mt-3 mb-1">
                <a href="login.php">Login</a>
            </p>
        </div>
    </div>
</div>
</body>
</html>