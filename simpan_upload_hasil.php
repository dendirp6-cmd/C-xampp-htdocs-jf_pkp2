<?php
/**
 * ==================================================================================
 * FILE: simpan_upload_hasil.php
 * DESKRIPSI: Backend Processor untuk Upload Surat Hasil Ujikom Peserta
 * FUNGSI: Validasi file, pemindahan berkas ke storage, dan update database pengajuan
 * ==================================================================================
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. KEAMANAN & OTENTIKASI
require_once 'auth_guard.php'; 
require_once 'koneksi.php';    

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Akses ditolak. Metode request salah.");
}

// 2. VALIDASI INPUT DATA
$id_pengajuan = isset($_POST['id_pengajuan']) ? intval($_POST['id_pengajuan']) : 0;
$hasil_ujikom = isset($_POST['hasil_ujikom']) ? trim($_POST['hasil_ujikom']) : '';
$catatan_hasil = isset($_POST['catatan_hasil']) ? trim($_POST['catatan_hasil']) : '';

if ($id_pengajuan === 0 || empty($hasil_ujikom)) {
    die("Data input tidak lengkap. ID atau Hasil Ujikom wajib diisi.");
}

// 3. VALIDASI & HANDLING FILE UPLOAD
if (!isset($_FILES['file_hasil_ujikom']) || $_FILES['file_hasil_ujikom']['error'] !== UPLOAD_ERR_OK) {
    die("Gagal merespon file. Pastikan file telah dipilih dan tidak korup.");
}

$fileTmpPath   = $_FILES['file_hasil_ujikom']['tmp_name'];
$fileName      = $_FILES['file_hasil_ujikom']['name'];
$fileSize      = $_FILES['file_hasil_ujikom']['size'];
$fileType      = $_FILES['file_hasil_ujikom']['type'];

// Ekstrak Ekstensi File
$fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
$allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png'];

if (!in_array($fileExtension, $allowedExtensions)) {
    die("Format file tidak didukung. Hanya diperbolehkan: PDF, JPG, JPEG, PNG.");
}

// Batasi Ukuran File (Maksimal 2MB = 2097152 Bytes)
$maxFileSize = 2 * 1024 * 1024;
if ($fileSize > $maxFileSize) {
    die("Ukuran file terlalu besar. Maksimal batas ukuran adalah 2MB.");
}

// 4. DIREKTORI PENYIMPANAN
$uploadFileDir = './uploads/hasil_ujikom/';

// Cek jika folder belum ada, buat otomatis dengan permission aman
if (!is_dir($uploadFileDir)) {
    mkdir($uploadFileDir, 0755, true);
}

// Modifikasi nama file agar unik mencegah file tertimpa (Format: id_waktu_namafile)
$newFileName = 'hasil_' . $id_pengajuan . '_' . time() . '.' . $fileExtension;
$dest_path   = $uploadFileDir . $newFileName;

// Proses Pemindahan File dari Temporary ke Target Folder
if (!move_uploaded_file($fileTmpPath, $dest_path)) {
    die("Terjadi error saat menyimpan file ke folder server. Cek permission folder.");
}

// 5. UPDATE DATA KE DATABASE
// Otomatis merubah status_pengajuan menjadi nilai hasil_ujikom (Lulus/Tidak Lulus) demi alur sistem
$sql_update = "UPDATE pengajuan_ujikom 
               SET hasil_ujikom = ?, 
                   status_pengajuan = ?, 
                   file_hasil_ujikom = ?, 
                   catatan_hasil_ujikom = ? 
               WHERE id = ?";

$stmt = $conn->prepare($sql_update);

if ($stmt) {
    // Parameter status_pengajuan disamakan dengan hasil_ujikom ('Lulus' atau 'Tidak Lulus')
    $stmt->bind_param("ssssi", $hasil_ujikom, $hasil_ujikom, $newFileName, $catatan_hasil, $id_pengajuan);
    
    if ($stmt->execute()) {
        echo "success"; // Output string ini dibaca oleh AJAX SweetAlert di list_peserta_ppsdm.php
    } else {
        echo "Gagal mengupdate data di database: " . $stmt->error;
    }
    $stmt->close();
} else {
    echo "Gagal menyiapkan query database.";
}

if (isset($conn)) $conn->close();
?>