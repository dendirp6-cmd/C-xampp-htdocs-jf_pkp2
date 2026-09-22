<?php
// =========================================================================
// 1. MEMUAT FILE PHPMailer
// Pastikan path ke file Exception, PHPMailer, dan SMTP di bawah ini benar
// =========================================================================

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require 'PHPMailer-master/src/Exception.php';
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';

// Buat instansi PHPMailer
$mail = new PHPMailer(true); // 'true' untuk mengaktifkan Exception

try {
    // =========================================================================
    // 2. KONFIGURASI SERVER SMTP (PENGATURAN PENGIRIMAN)
    // Ganti dengan detail akun email Anda.
    // *Jika menggunakan GMAIL, Anda harus menggunakan App Password, bukan password akun.*
    // =========================================================================
    $mail->isSMTP();                           // Menggunakan protokol SMTP
    $mail->Host       = 'smtp.gmail.com';      // Server SMTP Gmail
    $mail->SMTPAuth   = true;                  // Aktifkan otentikasi SMTP
    $mail->Username   = 'dendirp6@gmail.com'; // **GANTI: Alamat Email Anda**
    $mail->Password   = 'mqah idxg rpcq unpr'; // **GANTI: App Password/Password Anda**
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Gunakan enkripsi SSL/SMTPS
    $mail->Port       = 465;                   // Port standar untuk SMTPS

    // =========================================================================
    // 3. PENGATURAN PENERIMA & PENGIRIM
    // =========================================================================
    $mail->setFrom('dendirp6@gmail.com', 'Totti'); // Email dan Nama Pengirim
    $mail->addAddress('email.tujuan@domain.com', 'Nama Penerima');       // **GANTI: Alamat Penerima**
    // $mail->addReplyTo('info@domain.com', 'Informasi Balasan'); // Opsional

    // =========================================================================
    // 4. KONTEN EMAIL
    // =========================================================================
    $mail->isHTML(true);                       // Format email diatur ke HTML
    $mail->Subject = 'Uji Coba Pengiriman Email PHPMailer Berhasil!';
    
    // Isi dalam format HTML
    $mail->Body    = '<h1>Selamat!</h1>
                      <p>Email ini dikirim menggunakan <b>PHPMailer</b> dengan sukses.
                      Ini adalah konten dalam format HTML.</p>';
    
    // Isi alternatif dalam teks biasa (untuk klien yang tidak mendukung HTML)
    $mail->AltBody = 'Selamat! Email ini dikirim menggunakan PHPMailer dengan sukses.';

    // =========================================================================
    // 5. KIRIM EMAIL
    // =========================================================================
    $mail->send();
    echo '✅ Pesan berhasil dikirim!';

} catch (Exception $e) {
    echo "❌ Pesan gagal dikirim. Error PHPMailer: {$mail->ErrorInfo}";
}

?>