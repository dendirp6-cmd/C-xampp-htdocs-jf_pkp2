<?php
// FILE: email_sender.php
// Berisi fungsi untuk mengirim email verifikasi menggunakan PHPMailer.

// Pastikan path ke file PHPMailer sudah benar
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require_once 'vendor/phpmailer/phpmailer/src/Exception.php';
require_once 'vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once 'vendor/phpmailer/phpmailer/src/SMTP.php';

// =========================================================
// KREDENSIAL PENGIRIM DARI AKUN GOOGLE ANDA
// =========================================================
define('SMTP_USER', 'dendi.rpriandanu023@gmail.com'); 
// PENTING: Ganti nilai di bawah dengan Sandi Aplikasi 16 Karakter Tanpa Spasi!
define('SMTP_PASS', 'ofotnjoggydevday'); 
// =========================================================


/**
 * Mengirim email verifikasi ke pengguna baru.
 *
 * @param string $recipient_email
 * @param string $username
 * @param string $verification_link
 * @return array
 */
function send_verification_email($recipient_email, $username, $verification_link) {
    $mail = new PHPMailer(true);

    try {
        // Konfigurasi Server SMTP Gmail
        $mail->isSMTP();                             
        $mail->Host       = 'smtp.gmail.com';      
        $mail->SMTPAuth   = true;                 
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; 
        $mail->Port       = 587; 

        // Opsional: Untuk debugging, hapus komentar pada baris di bawah ini
        // $mail->SMTPDebug = SMTP::DEBUG_SERVER; 

        $mail->CharSet    = 'UTF-8';

        // Pengirim dan Penerima
        $mail->setFrom(SMTP_USER, 'Subdit Jafung Notifikasi');
        $mail->addAddress($recipient_email, $username);

        // Konten Email (HTML)
        $mail->isHTML(true);
        $mail->Subject = 'Verifikasi Akun Anda - Instansi Pembina JF';
        
        // --- START PERUBAHAN: Hapus Teks Cadangan/Fallback Link ---
        $mail->Body    = "
            Halo **$username**, <br><br>
            Terima kasih telah mendaftar. Akun Anda berhasil dibuat dan memerlukan verifikasi.<br>
            Silakan klik tautan berikut untuk memverifikasi email Anda:<br><br>
            <a href=\"$verification_link\" style=\"background-color:#007bff; color:#ffffff; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;\">
                Verifikasi Akun Saya
            </a>
            <br><br>
            Salam Hormat,<br>Tim Subdit Jafung";
        // --- END PERUBAHAN ---

        // Teks alternatif tanpa HTML (penting untuk klien email lama)
        $mail->AltBody = "Halo $username, Akun Anda berhasil dibuat. Silakan salin tautan berikut ke browser Anda untuk verifikasi: $verification_link";


        $mail->send();
        return ['success' => true, 'message' => 'Email verifikasi berhasil dikirim.'];

    } catch (Exception $e) {
        // Mengembalikan pesan error detail untuk debugging
        return [
            'success' => false, 
            'message' => "Email verifikasi gagal dikirim. Error PHPMailer: {$mail->ErrorInfo}"
        ];
    }
}
?>