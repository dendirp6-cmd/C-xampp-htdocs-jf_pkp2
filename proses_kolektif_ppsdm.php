<?php
/**
 * ==================================================================================
 * FILE: proses_kolektif_ppsdm.php
 * DESKRIPSI: Memproses persetujuan seluruh peserta dalam satu gelombang (Bulk Update)
 * PERBAIKAN: Penambahan fitur unggah Surat Pengantar ke folder uploads/pengumuman/
 * ==================================================================================
 */

header('Content-Type: application/json');
require_once 'koneksi.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Metode akses tidak diizinkan.']);
    exit;
}

// 1. Ambil Parameter Gelombang
// Kita gunakan TRIM untuk memastikan tidak ada spasi liar yang menyebabkan salah deteksi gelombang
$id_gelombang = isset($_POST['gelombang']) ? trim($_POST['gelombang']) : '';
$jenis_pengajuan = isset($_POST['jenis_pengajuan']) ? trim($_POST['jenis_pengajuan']) : '';

if (empty($id_gelombang)) {
    echo json_encode(['status' => 'error', 'message' => 'Data Gelombang tidak terbaca oleh sistem.']);
    exit;
}

try {
    // 2. Cek apakah ada data yang perlu divalidasi
    $query_check = "SELECT COUNT(*) as jml FROM pengajuan_ujikom 
                    WHERE TRIM(gelombang) = ? 
                    AND status_pengajuan = 'Proses Direktur'";
    
    $check = $conn->prepare($query_check);
    $check->bind_param("s", $id_gelombang);
    $check->execute();
    $res_check = $check->get_result()->fetch_assoc();

    if ($res_check['jml'] == 0) {
        echo json_encode(['status' => 'error', 'message' => 'Tidak ada pengajuan dengan status "Proses Direktur" pada Gelombang ' . $id_gelombang]);
        exit;
    }

    // 3. Proses Validasi & Upload Surat Pengantar
    if (!isset($_FILES['surat_pengantar']) || $_FILES['surat_pengantar']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['status' => 'error', 'message' => 'Surat Pengantar wajib dilampirkan dan gagal diunggah.']);
        exit;
    }

    $file_tmp  = $_FILES['surat_pengantar']['tmp_name'];
    $file_name = $_FILES['surat_pengantar']['name'];
    $file_size = $_FILES['surat_pengantar']['size'];
    $file_ext  = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

    // Validasi Format & Ukuran
    if ($file_ext !== 'pdf') {
        echo json_encode(['status' => 'error', 'message' => 'Surat Pengantar harus berformat PDF.']);
        exit;
    }
    if ($file_size > 5 * 1024 * 1024) { // Max 5MB
        echo json_encode(['status' => 'error', 'message' => 'Ukuran Surat Pengantar maksimal 5MB.']);
        exit;
    }

    // Konfigurasi Direktori Upload
    $upload_dir = 'uploads/pengumuman/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    // Buat nama unik: Surat_Pengantar_Gelombang_X_Time.pdf
    $clean_gel_name = preg_replace('/[^A-Za-z0-9]+/', '_', $id_gelombang);
    $new_file_name = "Surat_Pengantar_Gel_" . $clean_gel_name . "_" . time() . ".pdf";
    $target_path = $upload_dir . $new_file_name;

    // Pindahkan file ke server
    if (!move_uploaded_file($file_tmp, $target_path)) {
        echo json_encode(['status' => 'error', 'message' => 'Sistem gagal menyimpan dokumen Surat Pengantar ke server.']);
        exit;
    }

    // 4. Mulai Transaksi Database
    $conn->begin_transaction();

    /**
     * 5. UPDATE STATUS MASSAL & INSERT SURAT PENGANTAR
     * - status_pengajuan diubah ke 'Disetujui Direktur'
     * - surat_pengantar diisi dengan nama file baru
     * - is_read_direktur = 1, is_read_ppsdm = 0, tgl_update = NOW()
     */
    $sql_update = "UPDATE pengajuan_ujikom 
                   SET status_pengajuan = 'Disetujui Direktur', 
                       surat_pengantar = ?,
                       is_read_direktur = 1,
                       is_read_ppsdm = 0, 
                       tgl_update = NOW() 
                   WHERE TRIM(gelombang) = ? 
                   AND status_pengajuan = 'Proses Direktur'";
    
    $stmt = $conn->prepare($sql_update);
    $stmt->bind_param("ss", $new_file_name, $id_gelombang);
    
    if ($stmt->execute()) {
        $affected_rows = $stmt->affected_rows;
        
        // Pastikan ada baris yang terupdate sebelum commit
        if ($affected_rows > 0) {
            $conn->commit();
            echo json_encode([
                'status' => 'success', 
                'message' => "Berhasil! $affected_rows peserta pada Gelombang $id_gelombang dan lampiran Surat Pengantar telah diteruskan ke PPSDM."
            ]);
        } else {
            $conn->rollback();
            @unlink($target_path); // Hapus file jika update dibatalkan
            echo json_encode(['status' => 'error', 'message' => 'Gagal memperbarui status. Data mungkin sudah berubah.']);
        }
    } else {
        throw new Exception("Gagal mengeksekusi perintah pembaruan database.");
    }

} catch (Exception $e) {
    // Batalkan perubahan jika terjadi error SQL dan hapus file
    $conn->rollback();
    if (isset($target_path) && file_exists($target_path)) {
        @unlink($target_path); 
    }
    echo json_encode(['status' => 'error', 'message' => 'Sistem Error: ' . $e->getMessage()]);
}

$conn->close();
?>