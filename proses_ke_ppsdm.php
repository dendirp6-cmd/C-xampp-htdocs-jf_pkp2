<?php
/**
 * FILE: proses_ke_ppsdm.php
 * DESKRIPSI: Memproses pengiriman data dari Direktur ke PPSDM
 */
require_once 'koneksi.php';
session_start();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_pengajuan = $_POST['id_pengajuan'] ?? '';

    if (empty($id_pengajuan)) {
        echo json_encode(['status' => 'error', 'message' => 'ID Pengajuan tidak ditemukan.']);
        exit;
    }

    // Ubah status menjadi tahapan berikutnya (PPSDM)
    $status_baru = 'Proses PPSDM'; 
    
    // Logika untuk menangani satu ID atau banyak ID sekaligus (Bulk)
    $ids = explode(',', $id_pengajuan);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $bind_types = "s" . str_repeat('i', count($ids));
    $params = array_merge([$status_baru], $ids);

    $sql = "UPDATE pengajuan_ujikom SET status_pengajuan = ? WHERE id IN ($placeholders)";
    $stmt = $conn->prepare($sql);
    
    if ($stmt) {
        $stmt->bind_param($bind_types, ...$params);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Data berhasil diteruskan ke PPSDM.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal menyiapkan query database.']);
    }
}
?>