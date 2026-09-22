<?php
/**
 * FILE: proses_update_status_khusus.php
 * DESKRIPSI: Memproses pembaruan status khusus/TMS secara langsung ke Database
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

require_once 'auth_guard.php';
require_once 'koneksi.php';

if (!isset($conn) || !$conn) {
    echo json_encode(['status' => 'error', 'message' => 'Koneksi database gagal.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_GET['id'] ?? ($_POST['id_pengajuan'] ?? 0));
    $status_baru = trim($_POST['status_baru'] ?? '');
    $keterangan = trim($_POST['keterangan_status'] ?? '');

    if ($id <= 0 || empty($status_baru)) {
        echo json_encode(['status' => 'error', 'message' => 'Parameter ID atau Status tidak valid.']);
        exit;
    }

    // Update status dan keterangan_status di database
    $sql = "UPDATE pengajuan_ujikom SET status_pengajuan = ?, keterangan_status = ? WHERE id = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("ssi", $status_baru, $keterangan, $id);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Status berhasil diperbarui ke database.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Gagal memperbarui status: ' . $stmt->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error query statement database.']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Metode request tidak diizinkan.']);
}