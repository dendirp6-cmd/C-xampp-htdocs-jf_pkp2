<?php
/**
 * ==================================================================================
 * FILE: proses_gelombang_masal.php
 * DESKRIPSI: Memproses pembaruan gelombang (single/bulk) ke database
 * ==================================================================================
 */

header('Content-Type: application/json');
session_start();

require_once 'auth_guard.php';
require_once 'koneksi.php';

if (!isset($conn) || !$conn) {
    echo json_encode(['status' => 'error', 'message' => 'Koneksi database gagal.']);
    exit;
}

$id_raw = $_POST['id_pengajuan'] ?? '';
$id_gelombang = $_POST['id_gelombang'] ?? '';

if (empty($id_raw)) {
    echo json_encode(['status' => 'error', 'message' => 'Tidak ada pengusul yang dipilih.']);
    exit;
}

// Ubah comma-separated string menjadi array ID integer yang aman
$ids = array_map('intval', explode(',', $id_raw));
$ids = array_filter($ids, function($val) { return $val > 0; });

if (empty($ids)) {
    echo json_encode(['status' => 'error', 'message' => 'ID Pengajuan tidak valid.']);
    exit;
}

// Buat placeholders untuk Prepared Statement (misal: ?, ?, ?)
$placeholders = implode(',', array_fill(0, count($ids), '?'));

if ($id_gelombang === '' || $id_gelombang === null) {
    // Jika opsi "-- Belum Dipilih / Kosongkan --" yang dipilih
    $sql = "UPDATE pengajuan_ujikom SET gelombang = NULL WHERE id IN ($placeholders)";
    $stmt = $conn->prepare($sql);
    
    // Bind parameter untuk array $ids
    $types = str_repeat('i', count($ids));
    $stmt->bind_param($types, ...$ids);
} else {
    // Jika gelombang tertentu dipilih
    $id_gelombang = intval($id_gelombang);
    $sql = "UPDATE pengajuan_ujikom SET gelombang = ? WHERE id IN ($placeholders)";
    $stmt = $conn->prepare($sql);
    
    // Bind parameter untuk $id_gelombang dan array $ids
    $types = 'i' . str_repeat('i', count($ids));
    $params = array_merge([$id_gelombang], $ids);
    $stmt->bind_param($types, ...$params);
}

if ($stmt->execute()) {
    $affected = $stmt->affected_rows;
    $stmt->close();
    echo json_encode([
        'status'  => 'success', 
        'message' => 'Berhasil memperbarui gelombang untuk ' . count($ids) . ' pengusul.'
    ]);
} else {
    $error_msg = $stmt->error;
    $stmt->close();
    echo json_encode([
        'status'  => 'error', 
        'message' => 'Gagal memperbarui database: ' . $error_msg
    ]);
}