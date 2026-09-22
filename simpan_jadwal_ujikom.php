<?php
/**
 * ==================================================================================
 * FILE: simpan_jadwal_ujikom.php
 * DESKRIPSI: Backend untuk menyimpan jadwal massal berdasarkan filter status & gelombang
 * LOKASI FILE: C:\xampp\htdocs\jf_pkp2\simpan_jadwal_ujikom.php
 * ==================================================================================
 */

header('Content-Type: application/json');
require_once 'koneksi.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. VALIDASI INPUT AWAL
    if (!isset($_POST['id_pengajuan']) || empty($_POST['id_pengajuan'])) {
        echo json_encode(['status' => 'error', 'message' => 'ID Pengajuan tidak ditemukan.']);
        exit;
    }

    $id_pengajuan      = $_POST['id_pengajuan'];
    $target_status     = $_POST['target_status'] ?? 'ALL'; // Ambil target dari dropdown
    $filter_gelombang  = $_POST['filter_gelombang'] ?? ''; 
    $filter_jenis      = $_POST['filter_jenis'] ?? '';
    
    $tanggal_ujikom    = $_POST['tanggal_ujikom'] ?? '';
    $jam_ujikom        = $_POST['jam_ujikom'] ?? '';
    $metode_ujikom     = $_POST['metode_ujikom'] ?? '';
    $lokasi_ujikom     = $_POST['lokasi_ujikom'] ?? '';
    $pakaian_ujikom    = $_POST['pakaian_ujikom'] ?? '';
    $keterangan_ujikom = $_POST['keterangan_ujikom'] ?? '';

    $nama_file_final = null;

    // --- LOGIKA UPLOAD FILE ---
    if (isset($_FILES['surat_pengumuman_jadwal']) && $_FILES['surat_pengumuman_jadwal']['error'] === UPLOAD_ERR_OK) {
        $target_dir = "uploads/pengumuman/";
        
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $file_name = $_FILES['surat_pengumuman_jadwal']['name'];
        $file_tmp  = $_FILES['surat_pengumuman_jadwal']['tmp_name'];
        $file_size = $_FILES['surat_pengumuman_jadwal']['size']; 
        $file_ext  = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        if ($file_ext !== 'pdf') {
            echo json_encode(['status' => 'error', 'message' => 'Format file harus PDF.']);
            exit;
        }

        if ($file_size > 2 * 1024 * 1024) {
            echo json_encode(['status' => 'error', 'message' => 'Ukuran file maksimal 2MB.']);
            exit;
        }

        $nama_file_final = "SURAT_UKOM_" . time() . "_" . uniqid() . "." . $file_ext;
        $target_file = $target_dir . $nama_file_final;

        if (!move_uploaded_file($file_tmp, $target_file)) {
            echo json_encode(['status' => 'error', 'message' => 'Gagal mengupload file.']);
            exit;
        }
    }

    // --- LOGIKA UPDATE BERDASARKAN FILTER STATUS ---
    
    // Bangun Klausa WHERE untuk status
    if ($target_status === 'ALL') {
        // Jika ALL, maka menyasar Disetujui Direktur DAN Cadangan
        $where_status = "AND (status_pengajuan = 'Disetujui Direktur' OR status_pengajuan = 'Cadangan')";
    } else {
        // Jika spesifik (Misal hanya 'Cadangan')
        $where_status = "AND status_pengajuan = '" . $conn->real_escape_string($target_status) . "'";
    }

    // Query Dasar
    $sql = "UPDATE pengajuan_ujikom SET 
            tanggal_ujikom = ?, 
            jam_ujikom = ?, 
            metode_ujikom = ?, 
            lokasi_ujikom = ?, 
            pakaian_ujikom = ?, 
            keterangan_ujikom = ?, ";

    // Tambahkan kolom surat jika ada upload
    if ($nama_file_final !== null) {
        $sql .= "surat_pengumuman_jadwal = ?, ";
    }

    $sql .= "status_pengajuan = 'Terjadwal' 
            WHERE gelombang = ? 
            AND jenis_pengajuan = ? 
            $where_status";

    $stmt = $conn->prepare($sql);

    // Bind Param dinamis berdasarkan keberadaan file
    if ($nama_file_final !== null) {
        $stmt->bind_param("sssssssss", 
            $tanggal_ujikom, 
            $jam_ujikom, 
            $metode_ujikom, 
            $lokasi_ujikom, 
            $pakaian_ujikom, 
            $keterangan_ujikom, 
            $nama_file_final,
            $filter_gelombang,
            $filter_jenis
        );
    } else {
        $stmt->bind_param("ssssssss", 
            $tanggal_ujikom, 
            $jam_ujikom, 
            $metode_ujikom, 
            $lokasi_ujikom, 
            $pakaian_ujikom, 
            $keterangan_ujikom, 
            $filter_gelombang,
            $filter_jenis
        );
    }

    if ($stmt->execute()) {
        $affected = $stmt->affected_rows;
        if ($affected > 0) {
            echo json_encode([
                'status' => 'success', 
                'message' => "Jadwal berhasil disimpan untuk $affected peserta ($target_status)."
            ]);
        } else {
            echo json_encode([
                'status' => 'error', 
                'message' => "Tidak ada data dengan status '$target_status' yang ditemukan untuk diperbarui pada gelombang ini."
            ]);
        }
    } else {
        echo json_encode([
            'status' => 'error', 
            'message' => 'Gagal memperbarui database: ' . $stmt->error
        ]);
    }

    $stmt->close();
    $conn->close();

} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request Method']);
}
?>