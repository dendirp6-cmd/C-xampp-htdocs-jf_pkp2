<?php
// FILE: test_email.php

// --- AKTIFKAN DEBUGGING ERROR AGAR TIDAK BLANK WHITE SCREEN ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// -----------------------------------------------------------

// Memuat fungsi pengiriman email. Pastikan path ini benar!
require 'email_sender.php';

// Data Uji Coba
$email_tujuan = 'tujuan.email.anda@contoh.com'; // <-- GANTI DENGAN EMAIL TUJUAN ASLI!
$nama_pengguna = 'Pengguna Tes';

// Membuat link verifikasi dummy
$token_dummy = bin2hex(random_bytes(16));
$verification_link = "http://localhost/jf_pkp2/verify.php?token=" . $token_dummy; 

echo "Mencoba mengirim email verifikasi ke $email_tujuan...<br>";

// Panggil fungsi
$result = send_verification_email($email_tujuan, $nama_pengguna, $verification_link);

// Tampilkan hasilnya
if ($result['success']) {
    echo "<h3 style='color:green;'>✅ Berhasil!</h3>";
    echo "Email berhasil dikirim ke $email_tujuan. Silakan cek kotak masuk Anda.";
} else {
    echo "<h3 style='color:red;'>❌ Gagal!</h3>";
    echo "Detail Error: <b>" . htmlspecialchars($result['message']) . "</b>";
    echo "<br><br><b>Perhatian:</b> Jika error menunjukkan 'SMTP connect() failed' atau masalah SSL, coba aktifkan ekstensi 'openssl' di file php.ini Anda.";
}
?>