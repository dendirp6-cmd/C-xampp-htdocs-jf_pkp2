<?php
header('Content-Type: application/json');
require_once 'koneksi.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_pengajuan_raw = $_POST['id_pengajuan'] ?? ''; // Bisa berupa "1" atau "1,2,3"
    $id_verifikator = $_POST['id_verifikator'] ?? 0;

    // Perbaikan: Validasi hanya untuk id_pengajuan, id_verifikator boleh 0 (kosong)
    if (empty($id_pengajuan_raw)) {
        echo json_encode(['status' => 'error', 'message' => 'Data pengajuan tidak ditemukan.']);
        exit;
    }

    // Ubah string ID (komma) menjadi array agar aman
    $ids = explode(',', $id_pengajuan_raw);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    
    // Logika Tambahan: Tentukan status berdasarkan apakah verifikator dipilih atau tidak
    // Jika id_verifikator kosong, status mungkin kembali ke 'Menunggu Disposisi'
    // Jika ada id_verifikator, status menjadi 'Verifikasi Dokumen'
    $status_baru = (empty($id_verifikator)) ? 'Menunggu Disposisi' : 'Verifikasi Dokumen';
    
    // Update data: set verifikator dan ubah status
    $sql = "UPDATE pengajuan_ujikom SET 
            verifikator_id = ?, 
            status_pengajuan = ? 
            WHERE id IN ($placeholders)";

    $stmt = $conn->prepare($sql);
    
    // Bind Params dinamis
    // Menambahkan 's' untuk status_baru (string)
    $types = 'is' . str_repeat('i', count($ids));
    $stmt->bind_param($types, $id_verifikator, $status_baru, ...$ids);

    if ($stmt->execute()) {
        $msg = (empty($id_verifikator)) 
               ? count($ids) . ' Pengusul dikembalikan ke antrian (Belum Disposisi).' 
               : count($ids) . ' Pengusul berhasil didisposisikan!';
               
        echo json_encode(['status' => 'success', 'message' => $msg]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal memperbarui data database.']);
    }
}