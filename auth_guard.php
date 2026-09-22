<?php
// FILE: auth_guard.php
// "Penjaga" untuk setiap halaman yang dilindungi

// Periksa apakah sesi sudah dimulai.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- MEMUAT FUNGSI BANTUAN ROLE (LANGKAH 1) ---
// File ini berisi fungsi isAuthorized(), set_disabled(), dan hide_element()
require_once 'role_helpers.php'; 
// ------------------------------------------------

// Periksa apakah 'user_id' ada di dalam Sesi.
if (!isset($_SESSION['user_id'])) {
    
    // --- PENGGUNA TIDAK LOGIN ---
    $_SESSION['popup_type'] = 'error'; 
    $_SESSION['popup_message'] = 'Anda harus login untuk mengakses halaman tersebut.';
    
    header('Location: login.php');
    exit;
}

// =========================================================================
// ✅ PERBAIKAN KRITIS: SINKRONISASI VARIABEL SESI DENGAN AKHIRAN _SESI
// =========================================================================

// 1. Ambil data asli dari sesi (Asumsi: Sesi asli diset saat login)
$original_user_id = $_SESSION['user_id'];
$original_user_name = $_SESSION['user_name'] ?? 'Pengguna';

// --- PERBAIKAN KRITIS UNTUK EMAIL ---
// Mencari kunci email yang mungkin (user_email, email, user_mail)
if (isset($_SESSION['user_email'])) {
    $original_user_email = $_SESSION['user_email'];
} elseif (isset($_SESSION['email'])) {
    $original_user_email = $_SESSION['email'];
} elseif (isset($_SESSION['user_mail'])) {
    // Kunci lama yang mungkin digunakan di beberapa sistem
    $original_user_email = $_SESSION['user_mail'];
} else {
    $original_user_email = ''; // Default string kosong jika tidak ditemukan
}
// ------------------------------------

$original_user_role = $_SESSION['user_role'] ?? 'guest';
$original_user_nip = $_SESSION['user_nip'] ?? null; 
$original_join_date = $_SESSION['join_date'] ?? null; // Asumsi ada join_date

// 2. Tulis data ke variabel Sesi standar (*_sesi)
//    Ini memastikan file lain (seperti list_rekom_pengusul.php) dapat membaca dengan kunci yang konsisten.
$_SESSION['user_id_sesi'] = $original_user_id;
$_SESSION['user_nama_sesi'] = $original_user_name;
$_SESSION['user_email_sesi'] = $original_user_email; // <-- FIX UTAMA
$_SESSION['user_role_sesi'] = $original_user_role;
$_SESSION['user_nip_sesi'] = $original_user_nip;
$_SESSION['join_date_sesi'] = $original_join_date;
$_SESSION['user_nip_sesi'] = $_SESSION['nip'] ?? $original_user_nip; 
$_SESSION['instansi_sesi'] = $_SESSION['instansi'] ?? 'Instansi Tidak Diketahui';
$_SESSION['join_date_sesi'] = $original_join_date;


// 3. Set variabel lokal (jika file yang meng-include ingin menggunakannya langsung)
$user_id_sesi = $_SESSION['user_id_sesi'];
$user_nama_sesi = $_SESSION['user_nama_sesi'];
$user_email_sesi = $_SESSION['user_email_sesi'];
$user_role_sesi = $_SESSION['user_role_sesi'];
$user_nip_sesi = $_SESSION['user_nip_sesi'];
$join_date_sesi = $_SESSION['join_date_sesi'];
$user_nip_sesi = $_SESSION['user_nip_sesi'];
$instansi_sesi = $_SESSION['instansi_sesi'];    
$join_date_sesi = $_SESSION['join_date_sesi'];


// Setelah ini, di halaman manapun Anda bisa langsung menggunakan:
// 1. isAuthorized(['super_admin', 'admin'])
// 2. set_disabled(['super_admin'])
// 3. hide_element(['user_pengusul'])
?>