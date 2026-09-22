<?php
/**
 * ==================================================================================
 * FILE: batal_kirim_ppsdm.php
 * DESKRIPSI: Membatalkan pengiriman gelombang ke PPSDM dan mengembalikan status ke Direktur
 * ==================================================================================
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'auth_guard.php';
require_once 'koneksi.php';

header('Content-Type: application/json');

if (!isset($conn) || !$conn) {
    echo json_encode(['status' => 'error', 'message' => 'Koneksi database gagal.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_gelombang = intval($_POST['gelombang'] ?? 0);

    if ($id_gelombang <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'ID Gelombang tidak valid.']);
        exit;
    }

    // Mulai transaksi database untuk keamanan data
    $conn->begin_transaction();

    try {
        // 1. Kembalikan status pengajuan peserta di gelombang tersebut dari 'Proses PPSDM' kembali ke 'Proses Direktur'
        // Menyesuaikan dengan kondisi id gelombang atau teks nama gelombang
        $sql_peserta = "UPDATE pengajuan_ujikom 
                        SET status_pengajuan = 'Proses Direktur', tgl_update = NOW() 
                        WHERE (gelombang = ? OR gelombang = (SELECT gelombang FROM tb_gelombang WHERE id = ?)) 
                        AND status_pengajuan IN ('Proses PPSDM', 'Disetujui Direktur', 'Menunggu Jadwal Ujikom')";
        
        $stmt_peserta = $conn->prepare($sql_peserta);
        $stmt_peserta->bind_param("ii", $id_gelombang, $id_gelombang);
        $stmt_peserta->execute();

        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => 'Pengiriman berhasil dibatalkan. Data ditarik kembali dari PPSDM.']);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Metode akses tidak valid.']);
}