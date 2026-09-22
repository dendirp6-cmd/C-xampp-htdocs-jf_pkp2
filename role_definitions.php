<?php
// FILE: role_definitions.php - Definisi Konstanta dan Fungsi Peran (RBAC)

// --- DEFINISI KONSTANTA ROLE ---
define('ROLE_ADMIN', 'admin');
define('ROLE_USER_ADMIN', 'user_admin');   // Role untuk Pengaju Data (Boleh input)
define('ROLE_USER_BIASA', 'user_biasa');   // Role untuk Viewer/Supervisor (Tidak boleh input, hanya lihat)
define('ROLE_VERIFIKATOR', 'verifikator'); // Contoh Role Tambahan Jika Ada

// --- FUNGSI BANTUAN UNTUK KONTROL AKSES ---
// Fungsi ini mengecek apakah $user_role_sesi ada di dalam array peran yang diizinkan.
if (!function_exists('check_role')) {
    function check_role($allowed_roles) {
        // Ambil variabel role dari sesi global.
        $user_role_sesi = $_SESSION['user_role'] ?? 'guest'; 
        return in_array($user_role_sesi, (array) $allowed_roles);
    }
}
?>