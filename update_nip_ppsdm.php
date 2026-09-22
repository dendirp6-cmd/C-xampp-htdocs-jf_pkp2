<?php
// update_nip_ppsdm.php
require_once 'koneksi.php';
header('Content-Type: application/json');

// Matikan error reporting agar tidak merusak format JSON jika ada notice
error_reporting(0); 

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nip = $_POST['nip'] ?? '';
    $uid = $_POST['uid'] ?? 0;

    if (empty($nip) || $uid == 0) {
        echo json_encode(['success' => false, 'message' => 'Data tidak valid.']);
        exit;
    }

    // Gunakan try-catch untuk menangkap error database
    try {
        // Update tabel users
        $stmt = $conn->prepare("UPDATE users SET nip_user = ? WHERE id = ?");
        $stmt->bind_param("si", $nip, $uid);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal update database.']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}