<?php
/**
 * FILE: download_single_suket.php
 * DESKRIPSI: Generate & Download 1 Suket berdasarkan ID Peserta
 */
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

require_once 'auth_guard.php'; 
require_once 'koneksi.php';    
require_once 'tbs/tbs_class.php';
require_once 'tbs/tbs_plugin_opentbs.php';

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    exit("ID tidak valid.");
}

$stmt = $conn->prepare("SELECT * FROM tb_bimtek_peserta WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    exit("Data tidak ditemukan.");
}

$row = $result->fetch_assoc();
$nama_peserta = $row['nama_peserta'];
$clean_name = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $nama_peserta);
$no_suket_clean = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $row['no_suket'] ?? 'Suket');

$template_file = 'suket_word.docx';
if (!file_exists($template_file)) {
    exit("Template Word tidak ditemukan.");
}

$TBS = new clsTinyButStrong();
$TBS->Plugin(TBS_INSTALL, OPENTBS_PLUGIN);
$TBS->LoadTemplate($template_file, OPENTBS_ALREADY_UTF8);

$p = [];
$p['no_suket']          = $row['no_suket'] ?? '-';
$p['nama']              = $row['nama_peserta'] ?? '-';
$p['nip']               = $row['nip'] ?? '-';
$p['jabatan']           = $row['jabatan'] ?? '-';
$p['instansi']          = $row['instansi'] ?? '-';
$p['unit_kerja']        = $row['unit_kerja'] ?? '-';
$p['metode_kehadiran']  = $row['metode_kehadiran'] ?? '-';
$p['nilai_pretest']     = $row['nilai_pretest'] ?? '0';
$p['nilai_posttest']    = $row['nilai_posttest'] ?? '0';
$p['total_nilai']       = $row['total_nilai'] ?? '0';
$p['keterangan']        = $row['keterangan'] ?? '-';

$TBS->VarRef['p'] = $p;

$barcode_path_1 = $row['barcode_1'] ?? '';
if (!empty($barcode_path_1) && file_exists($barcode_path_1)) {
    $TBS->PlugIn(OPENTBS_CHANGE_PICTURE, 'barcode_img_1', $barcode_path_1);
}

$TBS->Show(OPENTBS_=&_WARNINGS);

$output_filename = 'Suket_' . $no_suket_clean . '_' . $clean_name . '.docx';

header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . $output_filename . '"');
header('Cache-Control: max-age=0');
$TBS->Show(OPENTBS_OUTPUT, false);
exit;
?>