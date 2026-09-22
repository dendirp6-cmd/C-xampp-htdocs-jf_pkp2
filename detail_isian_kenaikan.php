<?php
/**
 * FILE: detail_isian_kenaikan.php
 * DESKRIPSI: Menampilkan detail data pengajuan Kenaikan Jabatan beserta 5 dokumen wajibnya.
 * PERBAIKAN FINAL: Tombol perbaiki LANGSUNG HILANG (DI-HIDE) begitu berkas perbaikan dokumen 
 * tersebut berhasil diunggah dan disimpan ke dalam database.
 */

ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'auth_guard.php'; 
require_once 'koneksi.php'; 

// Cek parameter ID pengajuan
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Data ID Pengajuan tidak valid atau tidak ditemukan.");
}

$id_pengajuan = intval($_GET['id']);
$user_email = $_SESSION['email'] ?? $_SESSION['user_email_sesi'] ?? null;

$upload_url_path = "uploads/kenaikan/"; 
$pesan_notifikasi = ""; // Menampung alert sukses/gagal

// Pemetaan sinkronisasi kolom berkas dan kolom status validasi dari database
$DOKUMEN_MAPPING_STATUS = [
    'file_surat_usulan_uji' => 'd3_usulan_ujikom_status',
    'file_sk_jabatan'       => 'd7_sk_jabatan_status',
    'file_sk_pangkat'       => 'd6_sk_pangkat_status',
    'file_pak'              => 'd9_ijazah_transkrip_status',
    'file_skp'              => 'd8_nilai_skp_status'
];

// ==========================================
// PROSES FINALISASI KIRIM PERBAIKAN GLOBAL
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_kirim_final'])) {
    $sql_final = "UPDATE pengajuan_ujikom SET status_pengajuan = 'Menunggu Verifikasi' WHERE id = ? AND email = ?";
    $stmt_final = $conn->prepare($sql_final);
    $stmt_final->bind_param("is", $id_pengajuan, $user_email);
    if ($stmt_final->execute()) {
        $pesan_notifikasi = "
        <div class='alert alert-success alert-dismissible fade show shadow-sm' role='alert'>
            <h5><i class='icon fas fa-check-circle'></i> Berhasil Dikirim!</h5>
            Seluruh perbaikan dokumen Anda telah diteruskan ke tim verifikator. Status kembali 'Menunggu Verifikasi'.
            <button type='button' class='close' data-dismiss='alert' aria-label='Close'><span aria-hidden='true'>&times;</span></button>
        </div>";
    }
    $stmt_final->close();
}

// ==========================================
// PROSES UPDATE DOKUMEN SATU PER SATU
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_update_dokumen'])) {
    $db_column_target = $_POST['db_column'];
    $allowed_columns = ['file_surat_usulan_uji', 'file_sk_jabatan', 'file_sk_pangkat', 'file_pak', 'file_skp'];
    
    if (in_array($db_column_target, $allowed_columns)) {
        if (isset($_FILES['dokumen_baru']) && $_FILES['dokumen_baru']['error'] === UPLOAD_ERR_OK) {
            
            $file_tmp = $_FILES['dokumen_baru']['tmp_name'];
            $file_original_name = $_FILES['dokumen_baru']['name'];
            $file_ext = strtolower(pathinfo($file_original_name, PATHINFO_EXTENSION));
            $allowed_ext = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
            
            if (in_array($file_ext, $allowed_ext)) {
                $clean_filename = time() . "_" . preg_replace("/[^a-zA-Z0-9\._-]/", "_", $file_original_name);
                $target_file = $upload_url_path . $clean_filename;
                
                if (!is_dir($upload_url_path)) {
                    mkdir($upload_url_path, 0755, true);
                }
                
                if (move_uploaded_file($file_tmp, $target_file)) {
                    
                    // Ambil nama file lama untuk dihapus dari folder server
                    $sql_old = "SELECT $db_column_target FROM pengajuan_ujikom WHERE id = ? AND email = ?";
                    $stmt_old = $conn->prepare($sql_old);
                    $stmt_old->bind_param("is", $id_pengajuan, $user_email);
                    $stmt_old->execute();
                    $res_old = $stmt_old->get_result()->fetch_assoc();
                    $stmt_old->close();
                    
                    if (!empty($res_old[$db_column_target]) && file_exists($upload_url_path . $res_old[$db_column_target])) {
                        @unlink($upload_url_path . $res_old[$db_column_target]);
                    }
                    
                    // Cari kolom status verifikasi pendamping berkas ini
                    $db_status_column = $DOKUMEN_MAPPING_STATUS[$db_column_target];
                    
                    // LOGIKA BARU: Update berkas BARU sekaligus MENGOSONGKAN status validasinya agar tombol "Perbaiki" langsung tersembunyi
                    $sql_update = "UPDATE pengajuan_ujikom SET $db_column_target = ?, $db_status_column = 'Sudah Diperbaiki' WHERE id = ? AND email = ?";
                    $stmt_up = $conn->prepare($sql_update);
                    $stmt_up->bind_param("sis", $clean_filename, $id_pengajuan, $user_email);
                    
                    if ($stmt_up->execute()) {
                        $pesan_notifikasi = "
                        <div class='alert alert-info alert-dismissible fade show shadow-sm' role='alert'>
                            <h5><i class='icon fas fa-info-circle'></i> Berkas Berhasil Diperbarui!</h5>
                            Dokumen baru berhasil disimpan. Tombol perbaiki untuk dokumen ini telah disembunyikan. <strong>Jangan lupa klik tombol 'Selesai & Kirim Perbaikan' di bawah jika sudah selesai memperbaiki seluruh dokumen.</strong>
                            <button type='button' class='close' data-dismiss='alert' aria-label='Close'><span aria-hidden='true'>&times;</span></button>
                        </div>";
                    } else {
                        $pesan_notifikasi = "<div class='alert alert-danger'>Gagal menyimpan berkas ke database.</div>";
                    }
                    $stmt_up->close();
                    
                } else {
                    $pesan_notifikasi = "<div class='alert alert-danger'>Gagal mengunggah file ke server.</div>";
                }
            } else {
                $pesan_notifikasi = "<div class='alert alert-warning'>Format file tidak didukung!</div>";
            }
        }
    }
}

// Ambil data ter-update dari database
$sql = "SELECT * FROM pengajuan_ujikom WHERE id = ? AND email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $id_pengajuan, $user_email);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();
$stmt->close();

if (!$data) {
    die("Maaf, data pengajuan tidak ditemukan.");
}

$page = 'ujikom'; 
$sub_page = 'form_kenaikan';      
$page_title = 'Detail Pengajuan Kenaikan Jabatan'; 

$status_current = $data['status_pengajuan'] ?? 'Belum Diperiksa';
$badge_status_class = 'bg-secondary';
$is_perbaikan_status = false;

if (strpos(strtolower($status_current), 'perbaikan') !== false) {
    $badge_status_class = 'bg-danger text-white';
    $is_perbaikan_status = true; 
} elseif (strpos(strtolower($status_current), 'setuju') !== false || strpos(strtolower($status_current), 'lulus') !== false) {
    $badge_status_class = 'bg-success text-white';
} elseif (strpos(strtolower($status_current), 'proses') !== false || strpos(strtolower($status_current), 'periksa') !== false) {
    $badge_status_class = 'bg-warning text-dark';
}

include 'template/header.php'; 
include 'template/sidebar.php';
include 'template/navbar.php';   
?>

<style>
    .card-custom { box-shadow: 0 4px 6px rgba(0,0,0,0.05); border-radius: 8px; transition: transform 0.2s; }
    .table-detail th { background-color: rgba(0,0,0,0.02); color: #495057; font-weight: 600; width: 30%; }
    .table-detail td { color: #212529; }
    .status-panel { border-left: 5px solid #6610f2; background-color: #fafbfe; }
    .btn-preview-doc, .btn-fix-doc { border-radius: 20px; font-size: 0.85rem; font-weight: 600; padding: 6px 15px; transition: all 0.2s; max-width: 160px; margin: 3px auto; }
    .btn-preview-doc:hover, .btn-fix-doc:hover { transform: scale(1.03); }
</style>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-3 align-items-center">
                <div class="col-sm-6">
                    <h1 class="m-0 font-weight-bold text-dark" style="font-size: 1.6rem;">
                        <i class="fas fa-file-invoice text-teal mr-2"></i>Detail Riwayat Berkas Anda
                    </h1>
                </div>
                <div class="col-sm-6 text-right">
                    <a href="index_pengusul.php" class="btn btn-outline-secondary btn-sm shadow-sm font-weight-bold">
                        <i class="fas fa-arrow-left mr-1"></i> Kembali ke Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            
            <?= $pesan_notifikasi; ?>
            
            <div class="card card-outline card-teal card-custom mb-4">
                <div class="card-body py-3 px-4 d-flex align-items-center justify-content-between flex-wrap status-panel">
                    <div class="my-1">
                        <span class="text-muted small text-uppercase font-weight-bold d-block"><i class="fas fa-star-of-life mr-1 text-xs"></i> Status Pengajuan Saat Ini:</span>
                        <h4 class="font-weight-bold mb-0 mt-1 px-3 py-1 d-inline-block rounded <?= $badge_status_class; ?>" style="font-size: 1.25rem; letter-spacing: 0.5px;">
                            <?= strtoupper(htmlspecialchars($status_current)); ?>
                        </h4>
                    </div>
                    <div class="my-1 text-md-right">
                        <span class="text-muted small text-uppercase font-weight-bold d-block"><i class="far fa-calendar-check mr-1"></i> Waktu Kirim Berkas:</span>
                        <p class="font-weight-bold mb-0 text-dark mt-1" style="font-size: 1.05rem;">
                            <?= date('d F Y — H:i', strtotime($data['tanggal_pengajuan'])); ?> WIB
                        </p>
                    </div>
                </div>
            </div>

            <?php if (!empty($data['catatan_evaluator'])): ?>
                <div class="card bg-light-danger border-danger card-custom mb-4" style="border-left: 5px solid #dc3545; background-color: #fff5f5;">
                    <div class="card-body p-3">
                        <h5 class="font-weight-bold text-danger mb-2"><i class="fas fa-exclamation-circle mr-1"></i> Catatan Evaluasi Tim Verifikator:</h5>
                        <p class="mb-0 text-dark font-italic bg-white p-3 border rounded shadow-sm">
                            "<?= nl2br(htmlspecialchars($data['catatan_evaluator'])); ?>"
                        </p>
                    </div>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-12">
                    
                    <div class="card card-indigo card-outline card-custom mb-4">
                        <div class="card-header border-0 bg-white pt-3 pb-2">
                            <h3 class="card-title font-weight-bold text-indigo" style="font-size: 1.1rem;">
                                <i class="fas fa-user-circle mr-2 text-indigo"></i>Biodata Pribadi Pegawai
                            </h3>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-striped table-detail m-0">
                                <tr><th>Nama Lengkap</th><td><?= htmlspecialchars($data['nama']); ?></td></tr>
                                <tr><th>NIP</th><td><code><?= htmlspecialchars($data['nip']); ?></code></td></tr>
                                <tr><th>Jabatan Saat Ini</th><td><?= htmlspecialchars($data['jabatan']); ?></td></tr>
                                <tr><th>Pangkat / Golongan</th><td><?= htmlspecialchars($data['pangkat']); ?></td></tr>
                                <tr><th>TMT Pangkat</th><td><?= !empty($data['tmt_pangkat']) ? date('d-m-Y', strtotime($data['tmt_pangkat'])) : '-'; ?></td></tr>
                                <tr><th>Pendidikan Terakhir</th><td><?= htmlspecialchars($data['jenjang_pendidikan']); ?></td></tr>
                                <tr><th>Program Studi</th><td><?= htmlspecialchars($data['program_studi']); ?></td></tr>
                                <tr><th>Alamat Email</th><td><?= htmlspecialchars($data['email']); ?></td></tr>
                                <tr><th>No. Handphone (WA)</th><td><?= htmlspecialchars($data['hp']); ?></td></tr>
                            </table>
                        </div>
                    </div>

                    <div class="card card-indigo card-outline card-custom mb-4">
                        <div class="card-header border-0 bg-white pt-3 pb-2">
                            <h3 class="card-title font-weight-bold text-indigo" style="font-size: 1.1rem;">
                                <i class="fas fa-landmark mr-2 text-indigo"></i>Asal Instansi & Unit Kerja
                            </h3>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-striped table-detail m-0">
                                <tr><th>Instansi Induk</th><td><?= htmlspecialchars($data['instansi']); ?></td></tr>
                                <tr><th>Unit Organisasi (Eselon I)</th><td><?= htmlspecialchars($data['unit_organisasi']); ?></td></tr>
                                <tr><th>Unit Kerja Saat Ini</th><td><?= htmlspecialchars($data['unit_saat_ini']); ?></td></tr>
                                <tr><th>Unit Kerja Sebelumnya</th><td><?= !empty($data['unit_sebelumnya']) ? htmlspecialchars($data['unit_sebelumnya']) : '<span class="text-muted font-italic">-</span>'; ?></td></tr>
                            </table>
                        </div>
                    </div>

                    <div class="card card-teal card-outline card-custom mb-4">
                        <div class="card-header border-0 bg-white pt-3 pb-2">
                            <h3 class="card-title font-weight-bold text-teal" style="font-size: 1.1rem;">
                                <i class="fas fa-folder-open mr-2 text-teal"></i>Dokumen Lampiran Wajib Kenaikan
                            </h3>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover table-bordered m-0" style="vertical-align: middle;">
                                    <thead class="bg-light text-secondary small font-weight-bold text-uppercase text-center">
                                        <tr>
                                            <th style="width: 6%;">No</th>
                                            <th style="width: 54%; text-align: left;">Nama Berkas Dokumen</th>
                                            <th style="width: 40%;">Aksi Dokumen</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        // Struktur sinkronisasi 5 dokumen wajib kenaikan yang sudah diurutkan kembali
                                        $files_to_show = [
                                            [
                                                'label' => 'SK Kenaikan Pangkat Terakhir',            
                                                'db_column' => 'file_sk_pangkat',
                                                'db_status' => 'd6_sk_pangkat_status'
                                            ],
                                            [
                                                'label' => 'SK Jabatan Fungsional Terakhir',          
                                                'db_column' => 'file_sk_jabatan',
                                                'db_status' => 'd7_sk_jabatan_status'
                                            ],
                                            [
                                                'label' => 'Penetapan Angka Kredit (PAK) Terakhir',   
                                                'db_column' => 'file_pak',
                                                'db_status' => 'd9_ijazah_transkrip_status'
                                            ],
                                            [
                                                'label' => 'Penilaian Kinerja (SKP) 2 Tahun Terakhir',
                                                'db_column' => 'file_skp',
                                                'db_status' => 'd8_nilai_skp_status'
                                            ],
                                            [
                                                'label' => 'Surat Rekomendasi / Usulan dari Instansi', 
                                                'db_column' => 'file_surat_usulan_uji',
                                                'db_status' => 'd3_usulan_ujikom_status'
                                            ],
                                        ];

                                        $no = 1;
                                        foreach ($files_to_show as $f):
                                            $file_name = $data[$f['db_column']] ?? '';
                                            $file_exists = !empty($file_name);
                                            
                                            // Membaca status dokumen spesifik dari DB
                                            $status_validasi_dokumen = $data[$f['db_status']] ?? 'Belum Diperiksa';
                                        ?>
                                            <tr>
                                                <td class="text-center font-weight-bold text-muted" style="vertical-align: middle;"><?= $no++; ?>.</td>
                                                <td style="vertical-align: middle;">
                                                    <div class="text-dark mb-1" style="font-size: 0.95rem; line-height: 1.3; font-weight: 600;">
                                                        <?= $f['label']; ?>
                                                    </div>
                                                    <?php if ($file_exists): ?>
                                                        <span class="text-muted small text-break d-inline-block bg-light px-2 py-0.5 border rounded" style="font-size: 0.75rem;">
                                                            <i class="fas fa-paperclip mr-1 text-xs"></i> <?= htmlspecialchars($file_name); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center" style="vertical-align: middle;">
                                                    <div class="d-flex flex-wrap justify-content-center align-items-center">
                                                        
                                                        <?php if ($file_exists): ?>
                                                            <a href="<?= $upload_url_path . urlencode($file_name); ?>" target="_blank" class="btn btn-sm btn-info btn-preview-doc shadow-sm mx-1">
                                                                <i class="fas fa-external-link-alt mr-1"></i> Buka File
                                                            </a>
                                                        <?php else: ?>
                                                            <span class="badge badge-pill badge-danger py-2 px-3 font-weight-bold mx-1" style="font-size: 0.8rem; width: 110px;">
                                                                <i class="fas fa-times-circle mr-1"></i> Kosong
                                                            </span>
                                                        <?php endif; ?>

                                                        <?php if ($is_perbaikan_status && $status_validasi_dokumen === 'Tidak Sesuai'): ?>
                                                            <button type="button" 
                                                                    class="btn btn-sm btn-warning btn-fix-doc shadow-sm text-dark mx-1"
                                                                    data-toggle="modal" 
                                                                    data-target="#modalPerbaikanDokumen"
                                                                    data-label="<?= htmlspecialchars($f['label']); ?>"
                                                                    data-column="<?= htmlspecialchars($f['db_column']); ?>">
                                                                <i class="fas fa-edit mr-1"></i> Perbaiki
                                                            </button>
                                                        <?php endif; ?>

                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <?php if ($is_perbaikan_status): ?>
                            <div class="card-footer bg-white text-center py-3 border-top">
                                <form action="" method="POST" onsubmit="return confirm('Apakah Anda yakin sudah selesai memperbaiki semua dokumen and ingin mengirimkannya sekarang?');">
                                    <input type="hidden" name="action_kirim_final" value="1">
                                    <button type="submit" class="btn btn-success font-weight-bold shadow-sm px-4 rounded-pill">
                                        <i class="fas fa-paper-plane mr-2"></i> Selesai & Kirim Perbaikan Berkas
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                        
                    </div>

                </div>
            </div>

        </div>
    </section>
</div>

<div class="modal fade" id="modalPerbaikanDokumen" tabindex="-1" role="dialog" aria-labelledby="modalPerbaikanTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title font-weight-bold" id="modalPerbaikanTitle">
                    <i class="fas fa-upload mr-2"></i> Unggah Perbaikan Dokumen
                </h5>
                <button type="button" class="close text-dark" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden='true'>&times;</span>
                </button>
            </div>
            <form action="" method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="action_update_dokumen" value="1">
                    <input type="hidden" name="db_column" id="modal_db_column" value="">
                    
                    <div class="form-group mb-3">
                        <label class="text-muted small text-uppercase font-weight-bold d-block">Jenis Dokumen Yang Diperbaiki:</label>
                        <p id="modal_document_label" class="font-weight-bold text-dark bg-light p-2 border rounded" style="font-size: 1rem;"></p>
                    </div>
                    
                    <div class="form-group mb-0">
                        <label for="dokumen_baru" class="font-weight-bold text-dark">Pilih File Baru <span class="text-danger">*</span></label>
                        <div class="custom-file">
                            <input type="file" class="custom-file-input" id="dokumen_baru" name="dokumen_baru" required accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                            <label class="custom-file-label" for="dokumen_baru">Pilih berkas...</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary font-weight-bold rounded-pill px-4" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success font-weight-bold rounded-pill px-4 shadow-sm">
                        <i class="fas fa-save mr-1"></i> Simpan Dokumen
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php 
include 'template/footer.php'; 
?>

<script>
$(document).ready(function() {
    $('#modalPerbaikanDokumen').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget); 
        var labelDokumen = button.data('label'); 
        var namaKolomDb = button.data('column'); 

        var modal = $(this);
        modal.find('#modal_document_label').text(labelDokumen);
        modal.find('#modal_db_column').val(namaKolomDb);
        modal.find('#dokumen_baru').val('');
        modal.find('.custom-file-label').html('Pilih berkas...');
    });

    $('.custom-file-input').on('change', function() {
        var fileName = $(this).val().split('\\').pop();
        $(this).next('.custom-file-label').addClass("selected").html(fileName);
    });
});
</script>

<?php
if (isset($conn)) { $conn->close(); }
ob_end_flush(); 
?>