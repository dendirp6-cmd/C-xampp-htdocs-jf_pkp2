<?php
// =======================================================
// role_checker.php - Konfigurasi Akses dan Role
// =======================================================

/**
 * PENTING: Memastikan Session dimulai.
 * Baris ini mengatasi potensi error 'Failed to open stream'
 * atau Warning: Cannot access session data before session has started
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// 1. DEFINE KONSTANTA ROLE
// Gunakan konstanta agar lebih mudah dipanggil dan dibaca
define('ROLE_SUPER_ADMIN', 1);
define('ROLE_ADMIN', 2);
define('ROLE_USER_JF', 3);

/**
 * Mendapatkan Role ID dari user yang sedang login
 * ASUMSI: Role ID tersimpan dalam session setelah login
 *
 * @return int|null Role ID user yang sedang login, atau null jika belum login
 */
function getCurrentUserRole() {
    // Implementasi konkret: Cek dan ambil data dari SESSION
    // Note: Session sudah dipastikan aktif di awal file.
    if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true) {
        // ASUMSI: 'user_role_id' menyimpan nilai 1, 2, atau 3
        return $_SESSION['user_role_id'] ?? null;
    }
    return null;
}

/**
 * 2. FUNGSI UTAMA CEK OTORISASI (Role-Based Access Check)
 *
 * @param array $allowed_roles Array dari ROLE_ID yang diizinkan untuk mengakses halaman/fitur ini.
 * @return bool True jika user memiliki akses, False jika tidak.
 */
function isAuthorized(array $allowed_roles) {
    $current_role = getCurrentUserRole();

    // Jika Role null, artinya user belum login atau session tidak valid.
    if ($current_role === null) {
        return false;
    }

    // Cek apakah role user saat ini ada dalam daftar role yang diizinkan
    if (in_array($current_role, $allowed_roles)) {
        return true; // Akses diizinkan
    }

    return false; // Akses ditolak
}

/**
 * Fungsi untuk memeriksa otorisasi dan melakukan Redirect/Stop Eksekusi
 * @param array $allowed_roles Array dari ROLE_ID yang diizinkan.
 * @param string $redirect_url URL tujuan jika akses ditolak (misal: 'index.php')
 */
function checkAccessAndRedirect(array $allowed_roles, $redirect_url = 'index.php') {
    
    // Cek apakah user sudah login
    if (getCurrentUserRole() === null) {
        // Jika belum login, pastikan diarahkan ke halaman login.
        header("Location: login.php"); 
        exit();
    }

    // Cek otorisasi berdasarkan role
    if (!isAuthorized($allowed_roles)) {
        // Arahkan ke halaman beranda/error jika tidak memiliki izin
        header("Location: " . $redirect_url . "?error=AksesDitolak");
        exit();
    }
    // Jika True, script akan terus berjalan dan user memiliki akses
}