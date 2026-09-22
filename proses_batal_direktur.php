<?php
session_start();
require_once 'auth_guard.php';
require_once 'koneksi.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_pengajuan = $_POST['id_pengajuan'] ?? 0;

    if (empty($id_pengajuan)) {
        echo json_encode(['status' => 'error', 'message' => 'ID Pengajuan tidak valid.']);
        exit;
    }

    // Ubah status kembali menjadi 'Disetujui Verifikator'
    $stmt = $conn->prepare("UPDATE pengajuan_ujikom SET status_pengajuan = 'Disetujui Verifikator' WHERE id = ?");
    $stmt->bind_param("i", $id_pengajuan);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Pengiriman berhasil dibatalkan. Status dikembalikan ke Disetujui Verifikator.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal memperbarui status di database.']);
    }
    $stmt->close();
    exit;
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}