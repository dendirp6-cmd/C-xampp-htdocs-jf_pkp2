<?php
/**
 * ==================================================================================
 * FILE: edit_jadwal.php
 * DESKRIPSI: Form Pembaruan Jadwal Ujikom (Massal Per Gelombang, Jenis, & Status)
 * PERBAIKAN: 
 * - Proteksi Status: Disable jika status "Lulus" atau "Tidak Lulus"
 * - Penyesuaian nama kolom database: status_pengajuan & jenis_pengajuan
 * - Integrasi SweetAlert2 untuk Notifikasi Pop-up
 * - Filter Update Masal diselaraskan dengan simpan_jadwal_ujikom.php
 * ==================================================================================
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'auth_guard.php';
require_once 'koneksi.php';

$id_pengajuan = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_pengajuan == 0) {
    echo "<script>alert('ID tidak valid!'); window.location.href='index_ppsdm.php';</script>";
    exit;
}

// 1. AMBIL DATA AWAL (GELOMBANG, JENIS, & STATUS)
$sql_init = "SELECT p.gelombang, p.status_pengajuan, p.jenis_pengajuan, g.gelombang as nama_gel_text 
             FROM pengajuan_ujikom p 
             LEFT JOIN tb_gelombang g ON p.gelombang = g.id 
             WHERE p.id = ?";
$stmt_init = $conn->prepare($sql_init);
$stmt_init->bind_param("i", $id_pengajuan);
$stmt_init->execute();
$res_init = $stmt_init->get_result()->fetch_assoc();

if (!$res_init || empty($res_init['gelombang'])) {
    echo "<script>alert('Data tidak valid! Update masal tidak dapat dilakukan.'); window.location.href='index_ppsdm.php';</script>";
    exit;
}

$id_gelombang    = $res_init['gelombang'];
$status_saat_ini = $res_init['status_pengajuan'];
$jenis_saat_ini  = $res_init['jenis_pengajuan'];
$nama_gelombang  = $res_init['nama_gel_text'];
$stmt_init->close();

/**
 * LOGIKA PROTEKSI: CEK STATUS LULUS / TIDAK LULUS
 */
$is_disabled = false;
if (in_array($status_saat_ini, ['Lulus', 'Tidak Lulus'])) {
    $is_disabled = true;
}

$success_trigger = false;
$error_message = "";
$affected_count = 0;

// 2. PROSES UPDATE DATA (Hanya jika tidak disabled)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$is_disabled) {
    $tanggal = $_POST['tanggal_ujikom'];
    $jam = $_POST['jam_ujikom'];
    $metode = $_POST['metode_ujikom'];
    $lokasi = $_POST['lokasi_ujikom'];
    $pakaian = $_POST['pakaian_ujikom'];
    $keterangan = $_POST['keterangan_ujikom'];
    
    $file_sql = "";
    $params = [$tanggal, $jam, $metode, $lokasi, $pakaian, $keterangan];
    
    // Handling Upload File
    if (!empty($_FILES['surat_pengumuman']['name'])) {
        $target_dir = "uploads/pengumuman/";
        if (!is_dir($target_dir)) { mkdir($target_dir, 0755, true); }
        
        $file_name = "SURAT_UKOM_" . time() . "_" . uniqid() . ".pdf";
        $target_file = $target_dir . $file_name;
        
        if (move_uploaded_file($_FILES['surat_pengumuman']['tmp_name'], $target_file)) {
            $file_sql = ", surat_pengumuman_jadwal = ?";
            $params[] = $file_name;
        }
    }

    // Query UPDATE masal
    $sql_update = "UPDATE pengajuan_ujikom SET 
                    tanggal_ujikom = ?, 
                    jam_ujikom = ?, 
                    metode_ujikom = ?, 
                    lokasi_ujikom = ?, 
                    pakaian_ujikom = ?, 
                    keterangan_ujikom = ? 
                    $file_sql
                  WHERE gelombang = ? AND jenis_pengajuan = ? AND status_pengajuan = ?";
    
    $params[] = $id_gelombang;
    $params[] = $jenis_saat_ini;
    $params[] = $status_saat_ini;
    
    $stmt_upd = $conn->prepare($sql_update);
    $types = str_repeat("s", count($params) - 3) . "iss"; 
    $stmt_upd->bind_param($types, ...$params);
    
    if ($stmt_upd->execute()) {
        $success_trigger = true;
        $affected_count = $stmt_upd->affected_rows;
    } else {
        $error_message = $conn->error;
    }
    $stmt_upd->close();
}

// 3. QUERY DATA UNTUK MENGISI FORM DISPLAY
$sql = "SELECT p.*, g.gelombang as nama_gel_text FROM pengajuan_ujikom p 
        LEFT JOIN tb_gelombang g ON p.gelombang = g.id WHERE p.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_pengajuan);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();

require_once 'template/header.php';
?>
<link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

<?php
require_once 'template/navbar.php';
require_once 'template/sidebar.php';
?>

<style>
    .card-jadwal { border-radius: 20px; border: none; overflow: hidden; }
    .header-banner { background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%); color: #000; padding: 40px 30px; }
    .header-banner.disabled { background: linear-gradient(135deg, #94a3b8 0%, #cbd5e1 100%); }
    .info-box-custom { background: #f8fafc; border-radius: 15px; padding: 20px; border: 1px solid #e2e8f0; margin-bottom: 20px; height: 100%; }
    .label-custom { color: #64748b; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; margin-bottom: 8px; display: block; }
    .form-control-custom { border-radius: 10px; border: 1px solid #cbd5e1; padding: 12px; font-weight: 600; background-color: #fff !important; }
    .form-control-custom:focus { border-color: #f59e0b; box-shadow: 0 0 0 0.2rem rgba(245, 158, 11, 0.25); }
    .form-control-custom:disabled { background-color: #f1f5f9 !important; color: #64748b; cursor: not-allowed; }
    .badge-bulk { background: #000; color: #fff; padding: 5px 12px; border-radius: 5px; font-size: 0.8rem; text-transform: uppercase; }
    .calendar-icon { position: absolute; right: 25px; top: 48px; color: #64748b; }
</style>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <h1 class="m-0 font-weight-bold">
                <i class="fas fa-edit mr-2"></i>Edit Jadwal <span class="badge-bulk">Masal</span>
            </h1>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <?php if ($is_disabled): ?>
                <div class="alert alert-danger shadow-sm mb-4" style="border-radius: 12px;">
                    <i class="fas fa-exclamation-triangle mr-2"></i> <strong>Akses Dibatasi:</strong> Jadwal tidak dapat diubah karena peserta dalam kategori ini sudah berstatus <strong><?= $status_saat_ini; ?></strong>.
                </div>
            <?php endif; ?>

            <form id="formEditJadwal" action="" method="POST" enctype="multipart/form-data">
                <div class="card shadow-lg card-jadwal">
                    <div class="header-banner <?= $is_disabled ? 'disabled' : ''; ?>">
                        <div class="row align-items-center">
                            <div class="col-md-12">
                                <h2 class="font-weight-bold mb-1">Update Gelombang: <?= htmlspecialchars($nama_gelombang); ?></h2>
                                <p class="lead mb-0">Update diterapkan pada seluruh pengusul <strong><?= htmlspecialchars($jenis_saat_ini); ?></strong> dengan status: <strong><?= htmlspecialchars($status_saat_ini); ?></strong></p>
                            </div>
                        </div>
                    </div>

                    <div class="card-body p-4">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="info-box-custom position-relative">
                                    <label class="label-custom">Tanggal Pelaksanaan (Rentang)</label>
                                    <input type="text" name="tanggal_ujikom" id="range_calendar" class="form-control form-control-custom" value="<?= htmlspecialchars($data['tanggal_ujikom']); ?>" placeholder="Pilih Rentang Tanggal" readonly required <?= $is_disabled ? 'disabled' : ''; ?>>
                                    <i class="fas fa-calendar-alt calendar-icon"></i>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="info-box-custom">
                                    <label class="label-custom">Jam Mulai (WIB)</label>
                                    <input type="time" name="jam_ujikom" class="form-control form-control-custom" value="<?= $data['jam_ujikom']; ?>" required <?= $is_disabled ? 'disabled' : ''; ?>>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="info-box-custom">
                                    <label class="label-custom">Metode Ujian</label>
                                    <select name="metode_ujikom" class="form-control form-control-custom" <?= $is_disabled ? 'disabled' : ''; ?>>
                                        <option value="Daring" <?= $data['metode_ujikom'] == 'Daring' ? 'selected' : ''; ?>>Daring (Online)</option>
                                        <option value="Luring" <?= $data['metode_ujikom'] == 'Luring' ? 'selected' : ''; ?>>Luring (Offline)</option>
                                        <option value="Hybrid" <?= $data['metode_ujikom'] == 'Hybrid' ? 'selected' : ''; ?>>Hybrid</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-8">
                                <div class="info-box-custom" style="border-left: 5px solid #f59e0b;">
                                    <label class="label-custom">Lokasi atau Tautan Meeting</label>
                                    <textarea name="lokasi_ujikom" class="form-control form-control-custom" rows="3" required <?= $is_disabled ? 'disabled' : ''; ?>><?= htmlspecialchars($data['lokasi_ujikom']); ?></textarea>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="info-box-custom">
                                    <label class="label-custom">Pakaian</label>
                                    <input type="text" name="pakaian_ujikom" class="form-control form-control-custom" value="<?= htmlspecialchars($data['pakaian_ujikom']); ?>" placeholder="Contoh: Bebas Rapi / PDH" <?= $is_disabled ? 'disabled' : ''; ?>>
                                </div>
                            </div>

                            <div class="col-md-12">
                                <div class="info-box-custom" style="border-top: 3px solid #dc3545;">
                                    <label class="label-custom">Ganti Dokumen Surat Pengumuman (PDF)</label>
                                    <input type="file" name="surat_pengumuman" class="form-control border-0 p-0" accept=".pdf" <?= $is_disabled ? 'disabled' : ''; ?>>
                                    <small class="text-muted mt-2 d-block">File saat ini: <?= $data['surat_pengumuman_jadwal'] ?: 'Belum ada file'; ?></small>
                                </div>
                            </div>

                            <div class="col-md-12">
                                <div class="info-box-custom">
                                    <label class="label-custom">Keterangan Tambahan</label>
                                    <textarea name="keterangan_ujikom" class="form-control form-control-custom" rows="2" <?= $is_disabled ? 'disabled' : ''; ?>><?= htmlspecialchars($data['keterangan_ujikom']); ?></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="text-center mt-4">
                            <a href="index_ppsdm.php" class="btn btn-outline-secondary btn-lg px-5 shadow-sm mr-2" style="border-radius: 12px;">
                                <i class="fas fa-times mr-2"></i> Kembali
                            </a>
                            <?php if (!$is_disabled): ?>
                            <button type="submit" class="btn btn-warning btn-lg px-5 shadow-sm" style="border-radius: 12px; font-weight: bold;">
                                <i class="fas fa-save mr-2"></i> Simpan Perubahan Masal
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </section>
</div>

<?php require_once 'template/footer.php'; ?>

<script type="text/javascript" src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(function() {
    <?php if (!$is_disabled): ?>
    $('#range_calendar').daterangepicker({
        autoUpdateInput: false,
        locale: {
            cancelLabel: 'Clear',
            format: 'DD MMMM YYYY'
        }
    });

    $('#range_calendar').on('apply.daterangepicker', function(ev, picker) {
        $(this).val(picker.startDate.format('DD MMMM YYYY') + ' - ' + picker.endDate.format('DD MMMM YYYY'));
    });

    $('#range_calendar').on('cancel.daterangepicker', function(ev, picker) {
        $(this).val('');
    });
    <?php endif; ?>

    <?php if ($success_trigger): ?>
        Swal.fire({
            icon: 'success',
            title: 'Berhasil!',
            text: '<?= $affected_count ?> data pengusul (<?= $jenis_saat_ini ?>) dengan status <?= $status_saat_ini ?> berhasil diperbarui.',
            confirmButtonColor: '#f59e0b',
            confirmButtonText: 'Kembali ke Dashboard'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'index_ppsdm.php';
            }
        });
    <?php endif; ?>

    <?php if ($error_message): ?>
        Swal.fire({
            icon: 'error',
            title: 'Gagal Update',
            text: 'Kesalahan Database: <?= addslashes($error_message) ?>',
            confirmButtonColor: '#dc3545'
        });
    <?php endif; ?>

    // Alert awal jika disabled
    <?php if ($is_disabled): ?>
        Swal.fire({
            icon: 'warning',
            title: 'Akses Terkunci',
            text: 'Peserta dengan status <?= $status_saat_ini ?> tidak diizinkan untuk diubah jadwalnya.',
            confirmButtonColor: '#94a3b8'
        });
    <?php endif; ?>
});
</script>

<?php 
$stmt->close();
$conn->close();
?>