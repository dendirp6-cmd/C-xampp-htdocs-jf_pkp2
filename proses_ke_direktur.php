<?php
/**
 * ==================================================================================
 * FILE: proses_ke_direktur.php
 * DESKRIPSI: Memproses status ke 'Proses Direktur' dan menyimpan Nama Gelombang
 * UPDATE: Sinkronisasi nama kolom 'gelombang' & penanganan tipe data string
 * ==================================================================================
 */

ob_start();
error_reporting(0);
header('Content-Type: application/json');

require_once 'koneksi.php';

$response = [
    'status' => 'error',
    'message' => 'Gagal memproses data.'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // id_pengajuan berupa string "1,2,3"
    $ids_string  = $_POST['id_pengajuan'] ?? ''; 
    $status_baru = $_POST['status_baru'] ?? 'Proses Direktur';
    
    // Menangkap Nama Gelombang (Contoh: "Gelombang 1 2024")
    $gelombang_input = $_POST['gelombang'] ?? ''; 

    // Validasi input
    if (empty($ids_string) || empty($gelombang_input)) {
        echo json_encode(['status' => 'error', 'message' => 'Data pengusul atau gelombang tidak boleh kosong.']);
        exit;
    }

    $ids_array = explode(',', $ids_string);
    $ids_clean = array_map('intval', $ids_array); 
    $ids_placeholders = implode(',', array_fill(0, count($ids_clean), '?'));

    try {
        $conn->begin_transaction();

        /**
         * Query UPDATE:
         * Menggunakan kolom 'gelombang' sesuai struktur tabel pengajuan_ujikom Anda
         */
        $sql = "UPDATE pengajuan_ujikom 
                SET status_pengajuan = ?, 
                    gelombang = ?, 
                    is_read_direktur = 0
                WHERE id IN ($ids_placeholders) 
                AND status_pengajuan = 'Disetujui Verifikator'";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Gagal menyiapkan query: " . $conn->error);
        }

        // Binding parameter: 
        // "ss..." -> s (status_baru), s (gelombang_input), sisanya i (integer ids)
        $types = "ss" . str_repeat("i", count($ids_clean));
        
        // Pastikan gelombang_input diperlakukan sebagai string agar teks tidak hilang
        $params = array_merge([$status_baru, strval($gelombang_input)], $ids_clean);
        
        $stmt->bind_param($types, ...$params);

        if ($stmt->execute()) {
            $affected = $stmt->affected_rows;
            if ($affected > 0) {
                $conn->commit();
                $response['status'] = 'success';
                $response['message'] = "Berhasil mengirim $affected data ke Direktur.";
            } else {
                $conn->rollback();
                $response['message'] = "Tidak ada data yang diperbarui. Pastikan status awal adalah 'Disetujui Verifikator'.";
            }
        } else {
            throw new Exception($stmt->error);
        }

    } catch (Exception $e) {
        if (isset($conn)) $conn->rollback();
        $response['message'] = 'Terjadi kesalahan database: ' . $e->getMessage();
    }
} else {
    $response['message'] = 'Metode pengiriman data tidak sah.';
}

if (ob_get_length()) ob_clean();
echo json_encode($response);
exit;