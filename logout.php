<?php
// FILE: logout.php

// 1. Mulai sesi
session_start();

// 2. Hapus semua variabel sesi
$_SESSION = [];

// 3. Hancurkan sesi di server
// Jika menggunakan cookie sesi, hapus juga cookie tersebut.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 4. Hancurkan sesi
session_destroy();

// 5. Siapkan notifikasi (opsional, untuk ditampilkan di halaman login)
$_SESSION['popup_type'] = 'info';
$_SESSION['popup_message'] = "👋 Anda telah berhasil keluar. Sampai jumpa lagi!";

// 6. Alihkan ke halaman login.php
header('Location: index.php');
exit;
?>