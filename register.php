<?php
// FILE: register.php - Halaman Pendaftaran (Diperbarui untuk OTP, Validasi Gmail & NIP Ganda)

session_start();
require_once 'koneksi.php'; // Memuat koneksi dan nama tabel
require_once 'config_email.php'; // Pastikan file ini berisi fungsi send_otp_email

// Cek apakah user sudah login
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$message = '';
$is_error = false;
$input_values = $_POST; // Simpan input POST untuk mengisi ulang form

// --- PENTING: ASUMSI NAMA TABEL USERS ---
if (!isset($NAMA_TABEL_USERS)) {
    $NAMA_TABEL_USERS = "users"; 
}
// ----------------------------------------

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // --- 1. Ambil & Bersihkan Input ---
    $nama = trim($input_values['nama'] ?? '');
    $nip = trim($input_values['nip_user'] ?? '');
    $email = trim($input_values['email'] ?? '');
    $instansi = trim($input_values['instansi'] ?? '');
    $password = $input_values['password'] ?? '';
    $password_confirm = $input_values['password_confirm'] ?? '';
    $role = 'user'; // Default role
    $is_verified = 0; // Belum terverifikasi
    
    // --- LOGIKA OTP ---
    $otp_code = rand(100000, 999999); 
    $otp_expiry = date('Y-m-d H:i:s', time() + (5 * 60)); 

    // --- 2. Validasi Form ---
    if (empty($nama) || empty($nip) || empty($email) || empty($password) || empty($password_confirm)) {
        $is_error = true;
        $message = "Semua kolom wajib diisi.";
    } 
    // VALIDASI KHUSUS GMAIL
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || !str_ends_with(strtolower($email), '@gmail.com')) {
        $is_error = true;
        $message = "❌ Pendaftaran hanya diperbolehkan menggunakan alamat @gmail.com";
    }
    elseif ($password !== $password_confirm) {
        $is_error = true;
        $message = "Konfirmasi password tidak cocok.";
    } elseif (strlen($password) < 6) {
        $is_error = true;
        $message = "Password minimal 6 karakter.";
    } else {
        // --- 3. Cek Duplikasi Email & NIP ---
        $stmt_check = mysqli_prepare($conn, "SELECT email, nip_user FROM {$NAMA_TABEL_USERS} WHERE email = ? OR nip_user = ?");
        mysqli_stmt_bind_param($stmt_check, "ss", $email, $nip);
        mysqli_stmt_execute($stmt_check);
        mysqli_stmt_bind_result($stmt_check, $db_email, $db_nip);

        $email_exists = false;
        $nip_exists = false;

        // Periksa semua baris yang cocok untuk melihat apakah email atau NIP yang berduplikat
        while (mysqli_stmt_fetch($stmt_check)) {
            if (strtolower($db_email) === strtolower($email)) {
                $email_exists = true;
            }
            if ($db_nip === $nip) {
                $nip_exists = true;
            }
        }
        mysqli_stmt_close($stmt_check); // Tutup statement pengecekan

        // Evaluasi hasil pengecekan ganda
        if ($nip_exists) {
            $is_error = true;
            $message = "❌ Pendaftaran gagal: NIP tersebut sudah terdaftar pada akun lain.";
        } elseif ($email_exists) {
            $is_error = true;
            $message = "❌ Email ini sudah terdaftar. Silakan login atau gunakan email lain.";
        } else {
            // --- 4. Simpan ke Database ---
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            $sql_insert = "INSERT INTO {$NAMA_TABEL_USERS} (nama, nip_user, email, password_hash, instansi, role, is_verified, otp_code, otp_expiry) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt_insert = mysqli_prepare($conn, $sql_insert);
            mysqli_stmt_bind_param($stmt_insert, "ssssssiss", 
                $nama, $nip, $email, $password_hash, $instansi, $role, $is_verified, $otp_code, $otp_expiry);
            
            if (mysqli_stmt_execute($stmt_insert)) {
                
                // --- 5. Kirim Email OTP ---
                $email_result = send_otp_email($email, $nama, $otp_code);
                
                if ($email_result['success']) {
                    $_SESSION['popup_type'] = 'info';
                    $_SESSION['popup_message'] = "✅ Pendaftaran berhasil! Kode OTP telah dikirimkan ke email **$email**. Silakan masukkan kode verifikasi.";
                    $_SESSION['verify_email'] = $email; 
                    header('Location: verify_registration.php');
                    exit; 
                } else {
                    // Gagal kirim email: Hapus data sementara
                    $sql_delete = "DELETE FROM {$NAMA_TABEL_USERS} WHERE email = ? AND is_verified = 0";
                    $stmt_delete = mysqli_prepare($conn, $sql_delete);
                    mysqli_stmt_bind_param($stmt_delete, "s", $email);
                    mysqli_stmt_execute($stmt_delete);
                    
                    $is_error = true;
                    $message = "❌ Pendaftaran gagal karena email verifikasi tidak terkirim. Error: " . $email_result['message'];
                }
            } else {
                $is_error = true;
                $message = "❌ Pendaftaran gagal disimpan di database: " . mysqli_error($conn);
            }
            if (isset($stmt_insert)) mysqli_stmt_close($stmt_insert);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Daftar Akun | Instansi Pembina JF</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@800&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    
    <style>
    .register-page {
        background-image: url('assets/bg.jpeg'); 
        background-repeat: repeat; 
        background-size: auto; 
        background-color: #2e5975; 
        position: relative; 
    }

    #header-logo-top-left {
        position: absolute;
        top: 20px;
        left: 20px;
        height: 50px;
        width: auto;
        z-index: 1000;
    }

    /* CSS PLACEHOLDER MERAH UNTUK EMAIL */
    input[name="email"]::placeholder {
        color: #7d7a7a !important;
        opacity: 1;
    }
    input[name="email"]:-ms-input-placeholder { color: #ff4d4d !important; }
    input[name="email"]::-ms-input-placeholder { color: #ff4d4d !important; }

    .register-box {
        width: 380px; 
    }
    
    @media (max-width: 576px) {
        .register-box { width: 90%; }
    }

    .register-header-logo {
        height: 80px;
        margin-bottom: -25px !important; 
        padding-bottom: 0px !important;
        width: auto;
    }

    .register-header-text {
        font-weight: 700; 
        margin-top: 5px !important;
        margin-bottom: 5px !important; 
        text-align: center;
    }

    .btn-primary {
        border-radius: 8px !important; 
    }

    .text-center {
        font-size: 14px; 
        font-weight: 500;
    }

    /* Pesan peringatan real-time di bawah input email */
    #email-warning {
        font-size: 12px;
        display: none;
        margin-top: -10px;
        margin-bottom: 10px;
    }
    </style>
</head>
<body class="hold-transition register-page">

<img src="assets/logo_pkp2.png" alt="Logo PKP2" id="header-logo-top-left">

<div class="register-box">
    <div class="card">
        <div class="card-body register-card-body">
            
            <div class="text-center mb-3"> 
                <img src="assets/kemenPKP.png" alt="Logo" class="register-header-logo">
            </div>
            <p class="register-header-text">Daftar Akun Baru</p>

            <?php if ($is_error && $message): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>

            <form action="register.php" method="post" id="registerForm">
                <div class="input-group mb-3">
                    <input type="text" name="nama" class="form-control" placeholder="Nama Lengkap" required value="<?php echo htmlspecialchars($input_values['nama'] ?? ''); ?>">
                    <div class="input-group-append"><div class="input-group-text"><span class="fas fa-user"></span></div></div>
                </div>

                <div class="input-group mb-3">
                    <input type="email" name="email" id="emailInput" class="form-control" placeholder="Email (Wajib @gmail.com)" required value="<?php echo htmlspecialchars($input_values['email'] ?? ''); ?>">
                    <div class="input-group-append"><div class="input-group-text"><span class="fas fa-envelope"></span></div></div>
                </div>
                <div id="email-warning" class="text-danger"><b>Harus menggunakan @gmail.com</b></div>

                <div class="input-group mb-3">
                    <input type="text" name="nip_user" class="form-control" placeholder="NIP" required autocomplete="off" value="<?php echo htmlspecialchars($input_values['nip_user'] ?? ''); ?>">
                    <div class="input-group-append"><div class="input-group-text"><span class="fas fa-id-card"></span></div></div>
                </div>

                <div class="input-group mb-3">
                    <input type="text" name="instansi" class="form-control" placeholder="Instansi/Unit Kerja" required value="<?php echo htmlspecialchars($input_values['instansi'] ?? ''); ?>">
                    <div class="input-group-append"><div class="input-group-text"><span class="fas fa-building"></span></div></div>
                </div>

                <div class="input-group mb-3">
                    <input type="password" name="password" class="form-control" placeholder="Password (Min. 6 Karakter)" required autocomplete="new-password">
                    <div class="input-group-append"><div class="input-group-text"><span class="fas fa-lock"></span></div></div>
                </div>

                <div class="input-group mb-3">
                    <input type="password" name="password_confirm" class="form-control" placeholder="Ulangi Password" required autocomplete="new-password">
                    <div class="input-group-append"><div class="input-group-text"><span class="fas fa-lock"></span></div></div>
                </div>

                <div class="row">
                    <div class="col-8">
                        <div class="icheck-primary">
                            <input type="checkbox" id="agreeTerms" name="terms" value="agree" required>
                            <label for="agreeTerms">Saya setuju dengan <a href="#">ketentuan</a></label>
                        </div>
                    </div>
                    <div class="col-4">
                        <button type="submit" id="submitBtn" class="btn btn-primary btn-block">Daftar</button>
                    </div>
                </div>
            </form>
            <a href="login.php" class="text-center">Saya sudah punya akun</a>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>

<script>
$(document).ready(function() {
    // Validasi Real-time saat mengetik email
    $('#emailInput').on('input', function() {
        var email = $(this).val().toLowerCase();
        var warning = $('#email-warning');
        var btn = $('#submitBtn');

        if (email.length > 0) {
            if (email.endsWith('@gmail.com')) {
                warning.hide();
                btn.prop('disabled', false);
                $(this).removeClass('is-invalid').addClass('is-valid');
            } else {
                warning.show();
                btn.prop('disabled', true);
                $(this).removeClass('is-valid').addClass('is-invalid');
            }
        } else {
            warning.hide();
            btn.prop('disabled', false);
            $(this).removeClass('is-invalid is-valid');
        }
    });
});
</script>

</body>
</html>
<?php
if (isset($conn) && $conn) {
    mysqli_close($conn);
}
?>