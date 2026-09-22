<?php
/**
 * ==================================================================================
 * FILE: cetak_suket.php
 * DESKRIPSI: Script untuk generate dan download Surat Keterangan Bimtek (.docx)
 * ==================================================================================
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

require_once 'auth_guard.php'; 
require_once 'koneksi.php';    

// Sertakan library TinyButStrong & OpenTBS
require_once 'tbs_class.php';
require_once 'tbs_plugin_opentbs.php';

// Ambil ID peserta dari URL
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    die("ID Peserta tidak valid!");
}

// Ambil data peserta dari database
$stmt = $conn->prepare("SELECT * FROM tb_bimtek_peserta WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$peserta = $result->fetch_assoc();

if (!$peserta) {
    die("Data peserta tidak ditemukan di database!");
}

// Tentukan path template Word (.docx) Anda
$template_file = 'template/suket_word.docx';

if (!file_exists($template_file)) {
    die("File template Surat Keterangan ($template_file) tidak ditemukan! Harap upload template .docx terlebih dahulu.");
}

// Ambil data dari database
$nomor_suket  = trim($peserta['no_suket'] ?? '-');
$nama_peserta = $peserta['nama_peserta'] ?? '-';

// Rapikan kapitalisasi nama
$nama_peserta = ucwords(strtolower($nama_peserta));

// Inisialisasi TinyButStrong
$TBS = new clsTinyButStrong();
$TBS->Plugin(TBS_INSTALL, OPENTBS_PLUGIN);

// Load template Word
$TBS->LoadTemplate($template_file, OPENTBS_ALREADY_UTF8);

// Masukkan data Nomor Suket dan Nama ke template
$TBS->MergeField('p', [
    'no_suket' => $nomor_suket,
    'nama'     => $nama_peserta
]);

// KHUSUS GAMBAR TTE/QR: Daftarkan variabel global untuk tag [onshow.ttd_895]
$TBS->VarRef['ttd_895'] = 'qr_tte.png';

// Format Nama File Download: Menggabungkan No Suket dan Nama Peserta
// Karakter seperti garis miring (/) pada nomor suket diganti underscore (_) agar valid sebagai nama file
$clean_nomor_suket = preg_replace('/[^A-Za-z0-9_-]/', '_', $nomor_suket);
$clean_nama_peserta = preg_replace('/[^A-Za-z0-9_-]/', '_', $peserta['nama_peserta']);

$output_file_name = 'Suket_' . $clean_nomor_suket . '_' . $clean_nama_peserta . '.docx';

// Bersihkan buffer output agar file Word tidak korup saat di-download
$TBS->Show(OPENTBS_DOWNLOAD, $output_file_name);
exit();
?>