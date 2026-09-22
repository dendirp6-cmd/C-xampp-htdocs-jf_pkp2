<?php
/**
 * FILE: detail_formulir_admin.php
 * DESKRIPSI: Halaman detail tinjauan formulir sertifikat untuk Admin (Tampilan Single Page)
 * UPDATE: Penyesuaian tata letak dokumen (Grid Flip) berdasarkan jenis transisi jabatan
 */

session_start();
// Pastikan koneksi dan auth admin
require_once 'koneksi.php';

// Cek Login Admin
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Ambil ID Pengajuan dari URL
$id_pengajuan = isset($_GET['id']) ? mysqli_real_escape_string($conn, $_GET['id']) : '';

if (empty($id_pengajuan)) {
    header("Location: admin_update.php");
    exit;
}

// AMBIL DATA DARI DATABASE (Join dengan tb_gelombang untuk mendapatkan nama gelombang)
$sql = "SELECT p.*, g.gelombang AS nama_gelombang_label 
        FROM pengajuan_ujikom p 
        LEFT JOIN tb_gelombang g ON p.gelombang = g.id 
        WHERE p.id = '$id_pengajuan'";
$query = mysqli_query($conn, $sql);
$data  = mysqli_fetch_assoc($query);

if (!$data) {
    echo "<script>alert('Data tidak ditemukan!'); window.location.href='admin_update.php';</script>";
    exit;
}

// Deteksi Jenis Transisi Jabatan berdasarkan kelengkapan data
$is_jafung_to_jafung = (!empty($data['angka_kredit_sk']) || !empty($data['sk_jabatan']));

$page_title = 'Detail Formulir Sertifikat Peserta';
include 'template/header.php';
// Pastikan sidebar dan navbar admin yang dimuat
include 'template/sidebar.php'; 
?>

<style>
    .main-title { font-weight: 700; color: #4b1979; line-height: 1.2; }
    .sub-title-gelombang { font-size: 1.1rem; color: #6610f2; font-weight: 600; margin-top: -5px; display: block; }
    /* Style khusus tampilan admin */
    .form-control[readonly] { background-color: #f8f9fa; opacity: 1; border: 1px solid #dee2e6; color: #333; }
    .file-box { border: 1px solid #ddd; padding: 10px; border-radius: 5px; background: #fff; transition: 0.3s; }
    .file-box:hover { background: #f1f1f1; }
    .section-title { border-left: 4px solid #6610f2; padding-left: 10px; margin-bottom: 20px; }
</style>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-12 d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="m-0 main-title text-uppercase" style="font-size: 1.5rem;">
                            <i class="fas fa-file-signature mr-2"></i> <?= $page_title ?>
                        </h1>
                        <?php if (!empty($data['nama_gelombang_label'])): ?>
                            <span class="sub-title-gelombang">
                                <i class="fas fa-layer-group mr-1"></i><?= htmlspecialchars($data['nama_gelombang_label']) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <a href="javascript:history.back()" class="btn btn-secondary shadow-sm">
                        <i class="fas fa-arrow-left mr-1"></i> Kembali
                    </a>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <div class="card card-indigo card-outline shadow">
                <div class="card-body p-4">
                    
                    <div id="section-identitas">
                        <h5 class="section-title text-indigo font-weight-bold">
                            <i class="fas fa-user-tie mr-2"></i> Identitas Peserta (Tinjauan Admin)
                            <span class="badge <?= $is_jafung_to_jafung ? 'badge-success' : 'badge-info' ?> ml-2" style="font-size: 0.8rem; vertical-align: middle;">
                                <?= $is_jafung_to_jafung ? 'Jafung ke Jafung' : 'Pelaksana/Struktural ke Jafung' ?>
                            </span>
                        </h5>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Nama Lengkap (dengan Gelar)</label>
                                    <input type="text" class="form-control" value="<?= htmlspecialchars($data['nama'] ?? '-') ?>" readonly>
                                </div>
                               <div class="form-group">
                                    <label>Pendidikan Terakhir</label>
                                    <input type="text" class="form-control" value="<?= htmlspecialchars($data['jenjang_pendidikan'] ?? '-') ?>" readonly>
                                </div>
                                <div class="form-group">
                                    <label>Nama Jabatan Pelaksana sebelum Menjadi Jafung PKP</label>
                                    <input type="text" class="form-control" value="<?= htmlspecialchars($data['jabatan_sebelum_jafung'] ?? $data['jabatan_saat_ini'] ?? '-') ?>" readonly>
                                </div>
                                
                                <?php if($is_jafung_to_jafung): ?>
                                <div class="form-group">
                                    <label>TMT Jabatan</label>
                                    <input type="text" class="form-control" value="<?= !empty($data['tmt_jabatan']) ? date('d F Y', strtotime($data['tmt_jabatan'])) : '-' ?>" readonly>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Tujuan Jenjang Jabatan Fungsional</label>
                                    <input type="text" class="form-control" value="<?= htmlspecialchars($data['jf_pkp_tujuan'] ?? '-') ?>" readonly>
                                </div>
                                
                                <?php if(!$is_jafung_to_jafung): ?>
                                <div class="form-group">
                                    <label>Golongan Saat Uji Kompetensi</label>
                                    <input type="text" class="form-control" value="<?= htmlspecialchars($data['pangkat'] ?? '-') ?>" readonly>
                                </div>
                                <div class="form-group">
                                    <label>TMT Golongan</label>
                                    <input type="text" class="form-control" value="<?= !empty($data['tmt_pangkat']) ? date('d F Y', strtotime($data['tmt_pangkat'])) : '-' ?>" readonly>
                                </div>
                                <?php endif; ?>

                                <?php if($is_jafung_to_jafung): ?>
                                <div class="form-group">
                                    <label>Angka Kredit yang Tertera di SK Jabatan Fungsional</label>
                                    <input type="text" class="form-control" value="<?= htmlspecialchars($data['angka_kredit_sk'] ?? '-') ?>" readonly>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4">

                    <div id="section-berkas">
                        <h5 class="section-title text-indigo font-weight-bold">
                            <i class="fas fa-file-pdf mr-2"></i> Berkas Pendukung yang Diunggah
                        </h5>
                        <div class="row">
                            <?php if (!$is_jafung_to_jafung): ?>
                                <div class="col-md-6 mb-3">
                                    <label>Ijazah Terakhir</label>
                                    <div class="file-box d-flex justify-content-between align-items-center">
                                        <span class="small text-muted text-truncate mr-2"><?= !empty($data['file_ijazah_transkrip']) ? $data['file_ijazah_transkrip'] : 'Tidak ada file' ?></span>
                                        <?php if(!empty($data['file_ijazah_transkrip'])): ?>
                                            <a href="uploads/perpindahan/<?= $data['file_ijazah_transkrip'] ?>" target="_blank" class="btn btn-sm btn-info"><i class="fas fa-eye"></i> Lihat</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label>SK Pangkat/Golongan</label>
                                    <div class="file-box d-flex justify-content-between align-items-center">
                                        <span class="small text-muted text-truncate mr-2"><?= !empty($data['file_sk_pangkat']) ? $data['file_sk_pangkat'] : 'Tidak ada file' ?></span>
                                        <?php if(!empty($data['file_sk_pangkat'])): ?>
                                            <a href="uploads/perpindahan/<?= $data['file_sk_pangkat'] ?>" target="_blank" class="btn btn-sm btn-info"><i class="fas fa-eye"></i> Lihat</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label>SK Pencantuman Gelar (Pertek BKN Untuk S2 dan S3)</label>
                                    <div class="file-box d-flex justify-content-between align-items-center">
                                        <span class="small text-muted text-truncate mr-2"><?= !empty($data['sk_pencantuman_gelar']) ? $data['sk_pencantuman_gelar'] : 'Tidak melampirkan' ?></span>
                                        <?php if(!empty($data['sk_pencantuman_gelar'])): ?>
                                            <a href="uploads/perpindahan/<?= $data['sk_pencantuman_gelar'] ?>" target="_blank" class="btn btn-sm btn-info"><i class="fas fa-eye"></i> Lihat</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label>SKP Lengkap dari Tanggal TMT Golongan (Tidak hanya 2 tahun terakhir)</label>
                                    <div class="file-box d-flex justify-content-between align-items-center">
                                        <span class="small text-muted text-truncate mr-2"><?= !empty($data['skp_lengkap']) ? $data['skp_lengkap'] : 'Tidak ada file' ?></span>
                                        <?php if(!empty($data['skp_lengkap'])): ?>
                                            <a href="uploads/perpindahan/<?= $data['skp_lengkap'] ?>" target="_blank" class="btn btn-sm btn-info"><i class="fas fa-eye"></i> Lihat</a>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label>Berkas Lainnya (Diminta Admin)</label>
                                    <div class="file-box d-flex justify-content-between align-items-center">
                                        <span class="small text-muted text-truncate mr-2">
                                            <?= !empty($data['f_berkas_lainnya']) ? $data['f_berkas_lainnya'] : 'Peserta tidak melampirkan file yang diminta admin' ?>
                                        </span>
                                        <?php if(!empty($data['f_berkas_lainnya'])): ?>
                                            <a href="uploads/perpindahan/<?= $data['f_berkas_lainnya'] ?>" target="_blank" class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i> Lihat
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>

                            <?php else: ?>
                                <div class="col-md-6 mb-3">
                                    <label>Ijazah Terakhir</label>
                                    <div class="file-box d-flex justify-content-between align-items-center">
                                        <span class="small text-muted text-truncate mr-2"><?= !empty($data['file_ijazah_transkrip']) ? $data['file_ijazah_transkrip'] : 'Tidak ada file' ?></span>
                                        <?php if(!empty($data['file_ijazah_transkrip'])): ?>
                                            <a href="uploads/perpindahan/<?= $data['file_ijazah_transkrip'] ?>" target="_blank" class="btn btn-sm btn-info"><i class="fas fa-eye"></i> Lihat</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label>Skp lengkap dari tmt jabatan saat ini</label>
                                    <div class="file-box d-flex justify-content-between align-items-center">
                                        <span class="small text-muted text-truncate mr-2"><?= !empty($data['skp_lengkap']) ? $data['skp_lengkap'] : 'Tidak ada file' ?></span>
                                        <?php if(!empty($data['skp_lengkap'])): ?>
                                            <a href="uploads/perpindahan/<?= $data['skp_lengkap'] ?>" target="_blank" class="btn btn-sm btn-info"><i class="fas fa-eye"></i> Lihat</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label>Sk jabatan fungsional saat ini </label>
                                    <div class="file-box d-flex justify-content-between align-items-center border-success">
                                        <span class="small text-muted text-truncate mr-2"><?= !empty($data['sk_jabatan']) ? $data['sk_jabatan'] : 'Tidak ada file' ?></span>
                                        <?php if(!empty($data['sk_jabatan'])): ?>
                                            <a href="uploads/perpindahan/<?= $data['sk_jabatan'] ?>" target="_blank" class="btn btn-sm btn-info"><i class="fas fa-eye"></i> Lihat</a>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label>Berkas Lainnya (Diminta Admin)</label>
                                    <div class="file-box d-flex justify-content-between align-items-center">
                                        <span class="small text-muted text-truncate mr-2">
                                            <?= !empty($data['f_berkas_lainnya']) ? $data['f_berkas_lainnya'] : 'Peserta tidak melampirkan file yang diminta admin' ?>
                                        </span>
                                        <?php if(!empty($data['f_berkas_lainnya'])): ?>
                                            <a href="uploads/perpindahan/<?= $data['f_berkas_lainnya'] ?>" target="_blank" class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i> Lihat
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>

                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-top text-center">
                        <div class="alert alert-warning d-inline-block py-1 px-4 small shadow-sm">
                            <i class="fas fa-exclamation-triangle mr-1"></i> Mode Admin (Hanya Baca) - Harap periksa kesesuaian data sebelum mencetak sertifikat.
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </section>
</div>

<?php include 'template/footer.php'; ?>