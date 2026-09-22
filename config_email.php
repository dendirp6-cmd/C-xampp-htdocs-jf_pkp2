<?php
// FILE: config_email.php (dengan fungsi tambahan untuk Reset Password)

// ... (Bagian 'require_once' dan 'use' tetap sama) ...
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// =========================================================
// BARIS UNTUK MEMUAT FILE PHPMailer SECARA MANUAL (TETAP SAMA)
// =========================================================
require_once 'vendor/phpmailer/phpmailer/src/Exception.php'; 
require_once 'vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once 'vendor/phpmailer/phpmailer/src/SMTP.php';
// =========================================================

// =========================================================
// 1. FUNGSI send_otp_email (DIPERBAIKI)
// =========================================================
function send_otp_email($recipient_email, $username, $otp_code) {
    // *** BARIS PENTING DITAMBAHKAN/DIPERBAIKI UNTUK MENGATASI ERROR FATAL ***
    $mail = new PHPMailer(true); // <--- INISIALISASI OBJEK $mail
    
    try {
        // --- KONFIGURASI SMTP ---
        $mail->isSMTP();
        $mail->Host      = 'smtp.gmail.com'; 
        $mail->SMTPAuth  = true;
        
        // Kredensial
        // BARIS INI SEKARANG AMAN KARENA $mail sudah diinisialisasi
        $mail->Username  = 'dendirp6@gmail.com'; 
        $mail->Password  = 'cjhatspsijwklxbu'; 
        
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port      = 587; 

        $mail->CharSet   = 'UTF-8';
        $mail->SMTPKeepAlive = true;

        // Pengirim dan Penerima
        $mail->setFrom($mail->Username, 'Verifikator OTP');
        $mail->addAddress($recipient_email, $username);

        // --- Konten Email OTP ---
        $mail->isHTML(true);
        $mail->Subject = 'Kode Verifikasi OTP Reset Password Anda';
        $mail->Body    = "Halo $username,<br><br>"
                       . "Berikut adalah Kode OTP Anda untuk Reset Password: <b>$otp_code</b><br><br>"
                       . "Kode ini akan kedaluwarsa dalam 5 menit. Segera masukkan kode ini pada halaman verifikasi.";
        $mail->AltBody = "Kode OTP Anda: $otp_code. Kedaluwarsa dalam 5 menit.";
        
        $mail->send();
        return ['success' => true, 'message' => 'OTP email sent successfully.'];

    } catch (Exception $e) {
        return [
            'success' => false, 
            'message' => "Gagal mengirim email OTP. Error: {$mail->ErrorInfo}"
        ];
    }
}

// =========================================================
// 2. FUNGSI BARU: send_reset_link_email (SUDAH BENAR)
// =========================================================
// (Fungsi ini tidak perlu diubah, karena sudah memiliki "$mail = new PHPMailer(true);" di awal)
function send_reset_link_email($recipient_email, $username, $reset_link) {
    $mail = new PHPMailer(true); 

    try {
        $mail->SMTPDebug = \PHPMailer\PHPMailer\SMTP::DEBUG_SERVER;
        // --- KONFIGURASI SMTP (Sama seperti fungsi OTP Anda) ---
        $mail->isSMTP();
        $mail->Host      = 'smtp.gmail.com'; 
        $mail->SMTPAuth  = true;
        
        // Kredensial (Gunakan yang sama)
        $mail->Username  = 'dendirp6@gmail.com'; 
        $mail->Password  = 'mqahidxgrpcqunpr';  
        
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port      = 587; 

        $mail->CharSet   = 'UTF-8';
        $mail->SMTPKeepAlive = true;

        // Pengirim dan Penerima
        $mail->setFrom($mail->Username, 'Subdit Jafung Notifikasi');
        $mail->addAddress($recipient_email, $username);

        // --- Konten Email Reset Password ---
        $mail->isHTML(true);
        $mail->Subject = 'Permintaan Reset Password Akun Anda';
        $mail->Body    = "Halo **$username**, <br><br>Kami menerima permintaan untuk mereset password akun Anda. Klik tombol di bawah ini untuk melanjutkan:<br><br>
                          <div style='text-align:center;'>
                          <a href='$reset_link' style='display:inline-block; padding: 10px 20px; background-color: #007bff; color: white; text-decoration: none; border-radius: 5px; font-weight: bold;'>
                          RESET PASSWORD SAYA
                          </a>
                          </div><br>
                          Tautan ini akan kedaluwarsa dalam 1 jam. Jika Anda tidak merasa melakukan permintaan ini, abaikan email ini.<br><br>
                          Salam Hormat,<br>Tim Subdit Jafung";

        $mail->AltBody = "Halo $username, Klik tautan ini untuk reset password: $reset_link. Tautan ini akan kedaluwarsa dalam 1 jam.";
        
        $mail->send();
        return ['success' => true, 'message' => 'Tautan reset password berhasil dikirim.'];
    } catch (Exception $e) {
        return [
            'success' => false, 
            'message' => "Tautan reset password gagal dikirim. Error: {$mail->ErrorInfo}"
        ];
    }
}
?>