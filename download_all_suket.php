<?php
ini_set('display_errors', 0);
error_reporting(0);

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

require_once 'auth_guard.php';
require_once 'koneksi.php';

// Pastikan library OpenTBS / PHPWord yang biasa Anda pakai untuk cetak suket tersedia di sini
// Contoh penyesuaian path template & library di bawah silakan disesuaikan dengan file cetak_suket.php Anda
require_once('tbs_class.php'); 
require_once('plugins/tbs_plugin_opentbs.php');

$sql = "SELECT * FROM tb_bimtek_peserta WHERE UPPER(TRIM(sertif)) = 'YA'";
$result = $conn->query($sql);

if (!$result || $result->num_rows === 0) {
    echo "<script>alert('Tidak ada data peserta dengan status sertifikat YA.'); window.history.back();</script>";
    exit;
}

$zip = new ZipArchive();
$zip_name = "Kumpulan_Surat_Keterangan_Bimtek_" . date('Ymd_His') . ".zip";
$temp_zip = sys_get_temp_dir() . '/' . $zip_name;

if ($zip->open($temp_zip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
    exit("Gagal membuat arsip ZIP.");
}

while ($row = $result->fetch_assoc()) {
    $id_peserta = $row['id'];
    $nama_bersih = preg_replace('/[^A-Za-z0-9 _-]/', '', $row['nama_peserta']);
    $nama_file_docx = "Surat_Keterangan_" . str_replace(' ', '_', $nama_bersih) . ".docx";

    // ---------------------------------------------------------
    // PROSES GENERATE WORD (Sesuaikan dengan logic di cetak_suket.php Anda)
    // ---------------------------------------------------------
    $TBS = new clsTinyButStrong;
    $TBS->Plugin(TBS_INSTALL, OPENTBS_PLUGIN);
    $template = 'template_suket.docx'; // Pastikan path template word Anda benar
    
    if (file_exists($template)) {
        $TBS->LoadTemplate($template, OPENTBS_ALREADY_UTF8);
        
        // Masukkan variabel data peserta ke template Word
        $TBS->MergeField('p', $row);
        
        $output_path = sys_get_temp_dir() . '/' . uniqid('suket_') . '.docx';
        $TBS->Show(OPENTBS_FILE, $output_path);
        
        if (file_exists($output_path)) {
            $zip->addFile($output_path, $nama_file_docx);
        }
    }
}

$zip->close();

// Kirim file ZIP ke browser untuk didownload
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $zip_name . '"');
header('Content-Length: ' . filesize($temp_zip));
readfile($temp_zip);

// Bersihkan file temporary ZIP di server
@unlink($temp_zip);
exit;