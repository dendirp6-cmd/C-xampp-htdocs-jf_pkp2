<?php
/**
 * FILE: unduh_sertifikat.php
 * DESKRIPSI: Menangani proses download file sertifikat dari folder uploads/sertifikat/
 */

session_start();
require_once 'koneksi.php';

// Pastikan user sudah login
if (!isset($_SESSION['email']) && !isset($_SESSION['user_email_sesi'])) {
    die("Akses ditolak. Silakan login terlebih dahulu.");
}

if (isset($_GET['id'])) {
    $id = mysqli_real_escape_string($conn, $_GET['id']);
    
    // Ambil data nama file dan nama peserta untuk penamaan file saat didownload
    $query = mysqli_query($conn, "SELECT nama, file_sertifikat FROM pengajuan_ujikom WHERE id = '$id'");
    $data = mysqli_fetch_assoc($query);

    if ($data && !empty($data['file_sertifikat'])) {
        $filename = $data['file_sertifikat'];
        $nama_peserta = str_replace(' ', '_', $data['nama']); // Ganti spasi dengan underscore agar aman
        $filepath = 'uploads/sertifikat/' . $filename;

        // Cek apakah file fisik benar-benar ada di folder uploads/sertifikat/
        if (file_exists($filepath)) {
            
            // Bersihkan buffer untuk mencegah file korup
            if (ob_get_level()) {
                ob_end_clean();
            }

            // Header untuk proses download
            header('Content-Description: File Transfer');
            header('Content-Type: application/pdf');
            // Penamaan file yang akan muncul di komputer user
            header('Content-Disposition: attachment; filename="Sertifikat_Ujikom_' . $nama_peserta . '.pdf"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($filepath));
            
            // Baca file dan kirim ke browser
            readfile($filepath);
            exit;
            
        } else {
            echo "<script>alert('Error: File fisik tidak ditemukan di server. Silakan hubungi admin.'); window.history.back();</script>";
        }
    } else {
        echo "<script>alert('Error: Data sertifikat tidak ditemukan di database.'); window.history.back();</script>";
    }
} else {
    echo "<script>alert('ID tidak valid.'); window.history.back();</script>";
}
?>