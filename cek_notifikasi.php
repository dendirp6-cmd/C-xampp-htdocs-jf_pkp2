<?php
include 'koneksi.php';
session_start();

// Hitung jumlah berkas yang butuh evaluasi
$sql = "SELECT COUNT(*) as total FROM pengajuan_ujikom WHERE status_pengajuan = 'Proses Evaluasi Evaluator'";
$result = $conn->query($sql);
$data = $result->fetch_assoc();

echo json_encode(['total' => $data['total']]);
?>