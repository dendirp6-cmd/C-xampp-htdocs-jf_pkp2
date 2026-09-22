<?php
require_once 'koneksi.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $gel = $conn->real_escape_string($_POST['gelombang']);
    $jenis = $conn->real_escape_string($_POST['jenis_pengajuan']);

    // Query hapus berdasarkan gelombang dan jenis (sesuai grouping di tabel)
    $sql = "DELETE FROM pengajuan_ujikom WHERE gelombang = '$gel' AND jenis_pengajuan = '$jenis'";
    
    if ($conn->query($sql)) {
        echo json_encode(['status' => 'success', 'message' => 'Data gelombang berhasil dihapus.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal menghapus data: ' . $conn->error]);
    }
}
?>