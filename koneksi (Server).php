<?php
// FILE: koneksi.php
// Pastikan file ini TIDAK ADA spasi, baris kosong, atau karakter lain
// sebelum dan sesudah tag PHP untuk menghindari masalah header/session.

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

// Tidak menggunakan tag penutup `?>