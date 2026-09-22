<?php
// FILE: koneksi.php
// Pastikan file ini TIDAK ADA spasi, baris kosong, atau karakter lain
// sebelum tag PHP untuk menghindari masalah header/session.

// --- PENTING: GANTI DENGAN DETAIL KONEKSI DATABASE ANDA ---
$host = "sql113.infinityfree.com";
$user = "if0_40304497"; 
$pass = "jfpkp123"; 
$db = "if0_40304497_db_jfpkp2"; 
// --------------------------------------------------------

// Koneksi ke Database menggunakan MySQLi Procedural
$conn = mysqli_connect($host, $user, $pass, $db);

// Cek Koneksi
if (!$conn) {
    // Berhenti total jika koneksi gagal
    die("❌ Koneksi database gagal: " . mysqli_connect_error());
}

// Set karakter set ke UTF-8 (Penting untuk mendukung karakter khusus dan konsistensi data)
mysqli_set_charset($conn, "utf8mb4");

// Definisikan variabel-variabel global untuk nama tabel
// Ini membantu konsistensi di seluruh aplikasi
$NAMA_TABEL_USERS = "users"; 
$NAMA_TABEL_FORMASI = "pengajuan_rekomendasi";
$NAMA_TABEL_PEGAWAI = "detailpegawai";

// =========================================================================
// FUNGSI GLOBAL: REKAM LOG OTOMATIS (SYSTEM AUTOMATED LOG)
// =========================================================================
if (!function_exists('rekam_log_otomatis')) {
    function rekam_log_otomatis($judul, $deskripsi, $kategori = 'Aktivitas Sistem') {
        global $conn; // Mengambil objek koneksi database di atas

        // 1. Definisikan versi aplikasi saat ini
        $versi = "v2.1.0"; 

        // 2. Ambil nama user pelaksana dari session aplikasi
        $developer = $_SESSION['user_nama_sesi'] ?? 'System Automated';

        // 3. Ambil NIP user pelaksana jika tersedia untuk memperjelas log aktivitas
        $nip_user = $_SESSION['user_nip_sesi'] ?? '';
        if (!empty($nip_user)) {
            $deskripsi .= " (Eksekutor NIP: " . $nip_user . ")";
        }

        // 4. Query Insert menggunakan Prepared Statement agar aman dari SQL Injection
        $sql_log = "INSERT INTO log_update (versi, judul, deskripsi, kategori, tanggal, developer) VALUES (?, ?, ?, ?, NOW(), ?)";
        
        try {
            $stmt = mysqli_prepare($conn, $sql_log);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "sssss", $versi, $judul, $deskripsi, $kategori, $developer);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        } catch (Exception $e) {
            // Mencegah aplikasi utama crash/blank jika penulisan log error
            error_log("Gagal menulis log otomatis jf_pkp: " . $e->getMessage());
        }
    }
}