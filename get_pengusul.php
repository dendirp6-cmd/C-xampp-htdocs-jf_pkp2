<?php
/**
 * ==================================================================================
 * FILE: get_pengusul.php
 * DESKRIPSI: Mengambil data pengusul yang siap dikirim ke Direktur
 * KRITERIA: Status = 'Disetujui Verifikator'
 * ==================================================================================
 */

// Pastikan tidak ada output sebelum header json
ob_start();
error_reporting(0); 

header('Content-Type: application/json');
require_once 'koneksi.php';

$response = [
    'status' => 'error', 
    'data' => [], 
    'message' => 'Terjadi kesalahan internal.'
];

// Cek apakah koneksi database tersedia
if (!$conn) {
    echo json_encode(['status' => 'error', 'message' => 'Koneksi database gagal.']);
    exit;
}

try {
    /**
     * Mengambil data pengusul dengan status "Disetujui Verifikator".
     * Kolom jenis_pengajuan ditambahkan agar pemfilteran di popup berjalan normal.
     */
    $sql = "SELECT id, nama, nip, jenis_pengajuan, status_pengajuan 
            FROM pengajuan_ujikom 
            WHERE status_pengajuan = 'Disetujui Verifikator'
            ORDER BY nama ASC";

    $result = $conn->query($sql);

    if ($result) {
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        
        $response['status'] = 'success';
        $response['data'] = $data;
        $response['message'] = (count($data) > 0) ? 'Data berhasil dimuat.' : 'Tidak ada data pengusul dengan status Disetujui Verifikator.';
    } else {
        $response['message'] = 'Query error: ' . $conn->error;
    }

} catch (Exception $e) {
    $response['message'] = 'Exception: ' . $e->getMessage();
}

// Bersihkan buffer untuk memastikan hanya JSON yang dikirim
if (ob_get_length()) ob_clean();

echo json_encode($response);
exit;