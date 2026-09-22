<?php
/**
 * FILE: form_sertifikat.php
 */

session_start();
require_once 'auth_guard.php';
require_once 'koneksi.php';

$id_pengajuan = isset($_GET['id']) ? mysqli_real_escape_string($conn, $_GET['id']) : '';
$asal_jabatan = isset($_GET['asal_jabatan']) ? mysqli_real_escape_string($conn, $_GET['asal_jabatan']) : '';

if (empty($id_pengajuan)) {
    header("Location: index_pengusul.php");
    exit;
}

if (empty($asal_jabatan)) {
    echo "<script>alert('Asal jabatan tidak diketahui. Silakan pilih kembali dari Dashboard.'); window.location.href='index_pengusul.php';</script>";
    exit;
}

$query = mysqli_query($conn, "SELECT * FROM pengajuan_ujikom WHERE id = '$id_pengajuan'");
$data  = mysqli_fetch_assoc($query);

if (!$data) {
    echo "<script>alert('Data tidak ditemukan!'); window.location.href='index_pengusul.php';</script>";
    exit;
}

$page_title = 'Formulir Sertifikat Kelulusan Ukom Perpindahan Jabatan Fungsional Penata Kelola Perumahan';
include 'template/header.php';
include 'template/sidebar.php';
include 'template/navbar.php';
?>

<style>
    .form-step { display: none; }
    .form-step-active { display: block; }
    .step-indicator { display: flex; justify-content: space-between; margin-bottom: 30px; position: relative; }
    .step-indicator::before { content: ""; position: absolute; top: 50%; left: 0; right: 0; height: 2px; background: #ddd; z-index: 1; transform: translateY(-50%); }
    .step-node { width: 35px; height: 35px; border-radius: 50%; background: #fff; border: 2px solid #ddd; display: flex; align-items: center; justify-content: center; z-index: 2; font-weight: bold; transition: 0.3s; }
    .step-node.active { border-color: #6610f2; color: #6610f2; background: #eef0ff; }
    .step-node.completed { background: #6610f2; border-color: #6610f2; color: #fff; }
    .btn-view-file { padding: 0px 5px; font-size: 12px; margin-left: 5px; }
    .main-title { font-weight: 700; color: #4b1979; line-height: 1.2; }
    .form-control-plaintext { background-color: #f8f9fa; padding-left: 10px; border: 1px solid #dee2e6; border-radius: 4px; }
</style>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-12">
                    <h1 class="m-0 main-title text-uppercase" style="font-size: 1.5rem;"><?= $page_title ?></h1>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <div class="card card-indigo card-outline shadow">
                <div class="card-body">
                    <div class="step-indicator mx-auto" style="max-width: 400px;">
                        <div class="step-node active" id="node1">1</div>
                        <div class="step-node" id="node2">2</div>
                    </div>

                    <form id="formSertifikat" enctype="multipart/form-data">
                        <input type="hidden" name="id_pengajuan" value="<?= $id_pengajuan ?>">
                        <input type="hidden" name="asal_jabatan" value="<?= htmlspecialchars($asal_jabatan) ?>">

                        <div class="form-step form-step-active" id="step1">
                            <h5 class="mb-4 text-indigo">
                                <i class="fas fa-user-tie mr-2"></i> Identitas Peserta 
                                <span class="badge badge-info ml-2" style="font-size: 0.8rem; vertical-align: middle;">
                                    <?php echo ($asal_jabatan == 'Fungsional') ? 'Jafung ke Jafung' : 'Pelaksana ke Jafung'; ?>
                                </span>
                            </h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Nama Lengkap dengan Gelar </label>
                                        <input type="text" class="form-control-plaintext" value="<?= htmlspecialchars($data['nama'] ?? '') ?>" readonly>
                                    </div>
                                    <div class="form-group">
                                        <label>Pendidikan Terakhir</label>
                                        <input type="text" class="form-control-plaintext" value="<?= htmlspecialchars($data['jenjang_pendidikan'] ?? '') ?>" readonly>
                                    </div>
                                    <div class="form-group">
                                        <label>Nama Jabatan Pelaksana Sebelum Menjadi Jafung PKP <span class="text-danger">*</span></label>
                                        <input type="text" name="jabatan_sebelum_jafung" class="form-control" value="<?= htmlspecialchars($data['jabatan_saat_ini'] ?? '') ?>" readonly>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Tujuan Jenjang Jabatan Fungsional </label>
                                        <input type="text" class="form-control-plaintext" value="<?= htmlspecialchars($data['jf_pkp_tujuan'] ?? '') ?>" readonly>
                                    </div>

                                    <?php if ($asal_jabatan == 'Pelaksana_Perbendaharaan_Struktural'): ?>
                                    <div class="form-group">
                                        <label>Golongan Saat Uji Kompetensi</label>
                                        <input type="text" class="form-control-plaintext" value="<?= htmlspecialchars($data['pangkat'] ?? '') ?>" readonly>
                                    </div>
                                    <div class="form-group">
                                        <label>TMT Golongan</label>
                                        <input type="text" class="form-control-plaintext" value="<?= htmlspecialchars($data['tmt_pangkat'] ?? '') ?>" readonly>
                                    </div>
                                    <?php endif; ?>

                                    <?php if ($asal_jabatan == 'Fungsional'): ?>
                                    <div class="form-group">
                                        <label>Angka Kredit yang Tertera di SK Jabatan Fungsional
                                         <span class="text-danger">*</span></label>
                                        <input type="text" name="angka_kredit_sk" class="form-control" placeholder="Contoh: 150" value="<?= htmlspecialchars($data['angka_kredit_sk'] ?? '') ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label>TMT Jabatan Fungsional Saat Ini <span class="text-danger">*</span></label>
                                        <input type="date" name="tmt_jabatan" class="form-control" value="<?= htmlspecialchars($data['tmt_jabatan'] ?? '') ?>" required>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <hr>
                            <div class="text-right">
                                <button type="button" class="btn btn-primary next-step">Selanjutnya <i class="fas fa-arrow-right ml-1"></i></button>
                            </div>
                        </div>

                        <div class="form-step" id="step2">
                            <h5 class="mb-4 text-indigo"><i class="fas fa-file-upload mr-2"></i> Unggah Berkas Pendukung</h5>
                            <div class="alert alert-info py-2 small">
                                <i class="fas fa-info-circle mr-1"></i> Format file SK/SKP adalah <strong>.PDF</strong>. Untuk Berkas Lainnya dapat berupa <strong>.ZIP / .RAR</strong>. Maksimal 5MB per file.
                            </div>
                            
                            <div class="row">
                                <?php if ($asal_jabatan == 'Pelaksana_Perbendaharaan_Struktural'): ?>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>SK Pencantuman Gelar (Pertek BKN Untuk S2 dan S3)</label>
                                            <input type="file" name="f_sk_gelar" class="form-control-file" accept=".pdf">
                                            <?php if(!empty($data['sk_pencantuman_gelar'])): ?>
                                                <small class="text-success"><i class="fas fa-check"></i> Terisi: <a href="uploads/perpindahan/<?= $data['sk_pencantuman_gelar'] ?>" target="_blank">Lihat SK Gelar</a></small>
                                            <?php endif; ?>
                                        </div>
                                        <div class="form-group mt-3">
                                            <label>SKP Lengkap dari Tanggal TMT Golongan (Tidak hanya 2 tahun terakhir) <span class="text-danger">*</span></label>
                                            <input type="file" name="f_skp_lengkap" class="form-control-file" accept=".pdf" <?= empty($data['skp_lengkap']) ? 'required' : '' ?>>
                                            <?php if(!empty($data['skp_lengkap'])): ?>
                                                <small class="text-success"><i class="fas fa-check"></i> Terisi: <a href="uploads/perpindahan/<?= $data['skp_lengkap'] ?>" target="_blank">Lihat SKP</a></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Berkas Lainnya (Diminta Admin)</label>
                                            <input type="file" name="f_berkas_lainnya" class="form-control-file" accept=".zip,.rar,.pdf">
                                            <small class="text-muted">Gunakan format .ZIP atau .RAR jika ingin mengunggah banyak file sekaligus.</small>
                                            <?php if(!empty($data['f_berkas_lainnya'])): ?>
                                                <br><small class="text-success"><i class="fas fa-check"></i> Terisi: <a href="uploads/perpindahan/<?= $data['f_berkas_lainnya'] ?>" target="_blank">Lihat Berkas Lainnya</a></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                
                                <?php elseif ($asal_jabatan == 'Fungsional'): ?>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Skp lengkap dari tmt jabatan saat ini <span class="text-danger">*</span></label>
                                            <input type="file" name="f_skp_lengkap" class="form-control-file" accept=".pdf" <?= empty($data['skp_lengkap']) ? 'required' : '' ?>>
                                            <?php if(!empty($data['skp_lengkap'])): ?>
                                                <small class="text-success"><i class="fas fa-check"></i> Terisi: <a href="uploads/perpindahan/<?= $data['skp_lengkap'] ?>" target="_blank">Lihat SKP</a></small>
                                            <?php endif; ?>
                                        </div>
                                        <div class="form-group mt-3">
                                            <label>Sk jabatan fungsional saat ini  <span class="text-danger">*</span></label>
                                            <input type="file" name="f_sk_jabatan" class="form-control-file" accept=".pdf" <?= empty($data['sk_jabatan']) ? 'required' : '' ?>>
                                            <?php if(!empty($data['sk_jabatan'])): ?>
                                                <br><small class="text-success"><i class="fas fa-check"></i> Terisi: <a href="uploads/perpindahan/<?= $data['sk_jabatan'] ?>" target="_blank">Lihat SK Jabatan</a></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Berkas Lainnya (Diminta Admin)</label>
                                            <input type="file" name="f_berkas_lainnya" class="form-control-file" accept=".zip,.rar,.pdf">
                                            <small class="text-muted">Gunakan format .ZIP atau .RAR jika ingin mengunggah banyak file sekaligus.</small>
                                            <?php if(!empty($data['f_berkas_lainnya'])): ?>
                                                <br><small class="text-success"><i class="fas fa-check"></i> Terisi: <a href="uploads/perpindahan/<?= $data['f_berkas_lainnya'] ?>" target="_blank">Lihat Berkas Lainnya</a></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <hr>
                            <div class="d-flex justify-content-between">
                                <button type="button" class="btn btn-secondary prev-step"><i class="fas fa-arrow-left mr-1"></i> Sebelumnya</button>
                                <button type="submit" class="btn btn-success" id="btnSimpan"><i class="fas fa-save mr-1"></i> Simpan & Ajukan</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>
</div>

<?php include 'template/footer.php'; ?>

<script>
$(document).ready(function() {
    // Navigasi ke Step 2 (dengan validasi dinamis berdasarkan asal jabatan dari PHP)
    $(".next-step").click(function() {
        
        if ($("input[name='jabatan_sebelum_jafung']").val().trim() == "") {
            Swal.fire('Perhatian', 'Nama Jabatan Pelaksana wajib diisi.', 'warning');
            return;
        }
        
        <?php if ($asal_jabatan == 'Fungsional'): ?>
            if ($("input[name='angka_kredit_sk']").val().trim() == "") {
                Swal.fire('Perhatian', 'Angka Kredit Sesuai SK wajib diisi.', 'warning');
                return;
            }
            if ($("input[name='tmt_jabatan']").val().trim() == "") {
                Swal.fire('Perhatian', 'TMT Jabatan wajib diisi.', 'warning');
                return;
            }
        <?php endif; ?>

        $("#step1").fadeOut(300, function() {
            $("#step2").fadeIn(300);
            $("#node1").addClass("completed");
            $("#node2").addClass("active");
        });
    });

    // Kembali ke Step 1
    $(".prev-step").click(function() {
        $("#step2").fadeOut(300, function() {
            $("#step1").fadeIn(300);
            $("#node2").removeClass("active");
            $("#node1").removeClass("completed").addClass("active");
        });
    });

    // Proses Submit via AJAX
    $('#formSertifikat').on('submit', function(e) {
        e.preventDefault();
        let formData = new FormData(this);
        formData.append('aksi', 'simpan_formulir_sertifikat');

        $.ajax({
            url: 'proses_sertifikat.php',
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            dataType: 'json', 
            beforeSend: function() {
                $('#btnSimpan').attr('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Memproses...');
            },
            success: function(response) {
                if (response.status === 'success') {
                    Swal.fire({
                        title: 'Berhasil!',
                        text: response.message,
                        icon: 'success'
                    }).then(() => {
                        window.location.href = 'index_pengusul.php';
                    });
                } else {
                    Swal.fire('Gagal!', response.message, 'error');
                    $('#btnSimpan').attr('disabled', false).html('<i class="fas fa-save mr-1"></i> Simpan & Ajukan');
                }
            },
            error: function(xhr, status, error) {
                console.error(xhr.responseText);
                Swal.fire('Error', 'Gagal terhubung ke server atau terjadi kesalahan pada skrip pemroses.', 'error');
                $('#btnSimpan').attr('disabled', false).html('<i class="fas fa-save mr-1"></i> Simpan & Ajukan');
            }
        });
    });
});
</script>