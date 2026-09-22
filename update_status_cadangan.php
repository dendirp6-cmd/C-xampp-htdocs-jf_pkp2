<?php
/**
 * ==================================================================================
 * FILE: update_status_cadangan.php
 * DESKRIPSI: Pemroses Perubahan Status Cadangan via AJAX
 * ==================================================================================
 */

require_once 'koneksi.php';

// Pastikan request adalah POST dan memiliki parameter ID serta Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id']) && isset($_POST['action'])) {
    
    $id = $_POST['id'];
    $action = $_POST['action'];
    
    // Inisialisasi status baru berdasarkan aksi yang diterima
    $status_baru = '';
    
    if ($action === 'set') {
        // Jika aksi adalah 'set', maka ubah status ke Cadangan
        $status_baru = 'Cadangan';
    } elseif ($action === 'batal') {
        // Jika aksi adalah 'batal', maka kembalikan ke Disetujui Direktur
        $status_baru = 'Disetujui Direktur';
    } else {
        http_response_code(400);
        echo "Aksi tidak dikenal";
        exit;
    }

    // Eksekusi Update ke Database
    $sql = "UPDATE pengajuan_ujikom SET status_pengajuan = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    
    if ($stmt) {
        $stmt->bind_param("si", $status_baru, $id);
        
        if ($stmt->execute()) {
            // Memberikan respon teks 'success' agar ditangkap oleh script AJAX
            echo "success";
        } else {
            // Jika eksekusi query gagal
            http_response_code(500);
            echo "Database Error: " . $stmt->error;
        }
        $stmt->close();
    } else {
        // Jika gagal mempersiapkan statement
        http_response_code(500);
        echo "Prepare Error: " . $conn->error;
    }
    
    $conn->close();
} else {
    // Jika akses langsung tanpa parameter yang sesuai
    http_response_code(400);
    echo "Invalid Request: Parameter ID atau Action tidak ditemukan";
}
?>