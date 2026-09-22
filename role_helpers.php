<?php
// FILE: role_helpers.php

// PENTING: Memastikan session sudah dimulai sebelum memuat file ini.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * 1. FUNGSI UTAMA CEK OTORISASI (Role-Based Access Check)
 * * Memeriksa apakah peran pengguna yang sedang login termasuk dalam daftar peran yang diizinkan.
 *
 * @param array $allowed_roles Array string peran yang diizinkan (misal: ['super_admin', 'admin', 'user_verifikator'])
 * @return bool True jika user memiliki akses, False jika tidak.
 */
function isAuthorized(array $allowed_roles) {
    // Jika user belum login, secara default akses ditolak.
    if (!isset($_SESSION['user_role'])) {
        return false;
    }
    
    $current_role = $_SESSION['user_role'];
    
    // Cek apakah peran user saat ini ada dalam daftar peran yang diizinkan (case-insensitive check)
    if (in_array(strtolower($current_role), array_map('strtolower', $allowed_roles))) {
        return true; // Akses diizinkan
    }

    return false; // Akses ditolak
}

/**
 * 2. FUNGSI KONTROL UI (Tombol, Form)
 * * Mengembalikan string 'disabled' jika user TIDAK memiliki peran yang diizinkan.
 * @param array $allowed_roles Array string peran yang diizinkan untuk mengaktifkan elemen.
 * @return string 'disabled' atau string kosong.
 */
function set_disabled(array $allowed_roles) {
    // Logika kebalikannya: Jika TIDAK diizinkan, maka disabled.
    return isAuthorized($allowed_roles) ? '' : 'disabled';
}

/**
 * 3. FUNGSI KONTROL UI (Area Konten)
 * * Mengembalikan string 'style="display: none;"' jika user TIDAK memiliki peran yang diizinkan.
 * @param array $allowed_roles Array string peran yang diizinkan untuk melihat elemen.
 * @return string 'style="display: none;"' atau string kosong.
 */
function hide_element(array $allowed_roles) {
    return isAuthorized($allowed_roles) ? '' : 'style="display: none;"';
}

/**
 * 4. FUNGSI PROTEKSI HALAMAN (Pencegahan Akses Langsung)
 * * @param array $allowed_roles Array string peran yang diizinkan.
 * @param string $redirect_url URL tujuan jika akses ditolak (misal: 'index.php')
 */
function checkAccessAndRedirect(array $allowed_roles, $redirect_url = 'index.php') {
    
    // Jika belum login
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['popup_type'] = 'error'; 
        $_SESSION['popup_message'] = 'Anda harus login untuk mengakses halaman tersebut.';
        header("Location: login.php"); 
        exit();
    }
    
    // Cek otorisasi berdasarkan role
    if (!isAuthorized($allowed_roles)) {
        // Arahkan ke halaman beranda/error jika tidak memiliki izin
        $_SESSION['popup_type'] = 'error'; 
        $_SESSION['popup_message'] = 'Akses Ditolak: Peran Anda tidak diizinkan untuk melihat halaman ini.';
        header("Location: " . $redirect_url);
        exit();
    }
    // Jika True, script akan terus berjalan
}
?>