<?php
session_start();
require_once 'koneksi.php';

// Matikan error reporting agar tidak merusak format JSON
error_reporting(0); 
header('Content-Type: application/json');

// Identifikasi user dari session yang ada di index_pengusul.php
$user_email = $_SESSION['email'] ?? $_SESSION['user_email_sesi'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user_email) {
    $nip = $_POST['nip_user'];
    $instansi = $_POST['instansi'];

    // Pastikan nama kolom di tabel users adalah 'nip_user' dan 'instansi'
    $sql = "UPDATE users SET nip_user = ?, instansi = ? WHERE email = ?";
    $stmt = $conn->prepare($sql);
    
    // "sss" berarti 3 string (nip, instansi, email)
    $stmt->bind_param("sss", $nip, $instansi, $user_email);

    if ($stmt->execute()) {
        // PENTING: Update session agar notifikasi kuning langsung hilang
        $_SESSION['nip'] = $nip;
        $_SESSION['user_nip_sesi'] = $nip;
        $_SESSION['instansi'] = $instansi;

        echo json_encode(['status' => 'success', 'message' => 'Data berhasil disimpan!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
    }
    $stmt->close();
} else {
    echo json_encode(['status' => 'error', 'message' => 'Sesi tidak valid atau form kosong.']);
}
$conn->close();
exit;