<?php
/**
 * ==================================================================================
 * FILE: list_peserta_ppsdm.php
 * DESKRIPSI: Detail Peserta per Gelombang & Jenis Pengajuan
 * FUNGSI: Verifikasi Berkas, Set Jadwal, Monitoring Hasil, Upload Hasil & Set Cadangan/Batal
 * ==================================================================================
 */

// 1. INISIALISASI SESSION & KEAMANAN
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'auth_guard.php'; 
require_once 'koneksi.php';    

if (!isset($conn) || !$conn) {
    die("Fatal Error: Koneksi database tidak tersedia.");
}

// --- PENGAMBILAN DATA SESSION & IDENTIFIKASI ROLE ---
$user_role_sesi = strtoupper($_SESSION['user_role_sesi'] ?? '');
$is_ppsdm       = (strpos($user_role_sesi, 'PPSDM') !== false);

// Ambil Parameter dari URL
$gel_filter   = $_GET['gel'] ?? '';
$jenis_filter = $_GET['jenis'] ?? '';

if (empty($gel_filter) || empty($jenis_filter)) {
    header("Location: index_ppsdm.php");
    exit;
}

// --- Ambil Nama Gelombang dari tb_gelombang agar Sinkron ---
$nama_gelombang_display = "Gelombang " . $gel_filter; 
$stmt_gel = $conn->prepare("SELECT gelombang FROM tb_gelombang WHERE id = ?");
$stmt_gel->bind_param("i", $gel_filter);
$stmt_gel->execute();
$res_gel = $stmt_gel->get_result();
if ($row_gel = $res_gel->fetch_assoc()) {
    $nama_gelombang_display = $row_gel['gelombang'];
}
$stmt_gel->close();

$page_title = "Detail Peserta - " . htmlspecialchars($nama_gelombang_display);

// 2. QUERY DATA PESERTA SPESIFIK
$status_ppsdm_list = [
    'Proses PPSDM', 'Disetujui Direktur', 'Disetujui', 
    'Menunggu Jadwal Ujikom', 'Terjadwal', 'Selesai', 
    'Cadangan', 'Lulus', 'Tidak Lulus'
];
$status_placeholders = implode(',', array_fill(0, count($status_ppsdm_list), '?'));

$sql = "SELECT * FROM pengajuan_ujikom 
        WHERE gelombang = ? 
        AND jenis_pengajuan = ? 
        AND status_pengajuan IN ($status_placeholders)
        ORDER BY nama ASC";

$stmt = $conn->prepare($sql);

$bind_types = "ss" . str_repeat("s", count($status_ppsdm_list));
$params = array_merge([$gel_filter, $jenis_filter], $status_ppsdm_list);

$stmt->bind_param($bind_types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$data_peserta = [];
while ($row = $result->fetch_assoc()) {
    $data_peserta[] = $row;
}

// 3. LOAD TEMPLATE
require_once 'template/header.php'; 
require_once 'template/navbar.php'; 
require_once 'template/sidebar.php'; 
?>

<style>
    :root { --dark-panel: #223553ff; }
    .content-wrapper { background-color: #f4f6f9; }
    .header-detail-banner {
        background-color: var(--dark-panel);
        border-radius: 15px; padding: 25px 30px; margin-bottom: 20px; 
        color: white; display: flex; justify-content: space-between; align-items: center;
    }
    .main-card-ppsdm { 
        border: none; border-radius: 20px; 
        box-shadow: 0 10px 30px rgba(0,0,0,0.08) !important; 
        overflow: hidden; 
    }
    .card-header-gradient { background: white; border-bottom: 2px solid #f0f0f0; padding: 20px 25px; }
    .status-pill { padding: 6px 14px; border-radius: 50px; font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; gap: 5px; color: white; }
    .note-bubble { background: #f1f5f9; padding: 8px 12px; border-radius: 10px; font-size: 0.85rem; color: #475569; border-left: 3px solid #cbd5e1; max-width: 250px; }
    .btn-action { border-radius: 8px; padding: 6px 15px; font-weight: 600; margin-bottom: 4px; transition: all 0.2s; font-size: 0.85rem; display: block; width: 100%; text-align: center; border: none; }
    .btn-detail { background: #fff; border: 1px solid #e2e8f0; color: #334155; }
    .btn-detail:hover { background: #17a2b8; color: #fff; border-color: #17a2b8; }
    .btn-jadwal { background: #f012be; border: 1px solid #f012be; color: #fff; }
    .btn-jadwal:hover { background: #c7109e; color: #fff; }
    .btn-upload { background: #28a745; border: 1px solid #28a745; color: #fff; }
    .btn-upload:hover { background: #218838; color: #fff; }
    .btn-cadangan { background: #ffc107; border: 1px solid #ffc107; color: #212529; }
    .btn-cadangan:hover { background: #e0a800; color: #212529; }
    .btn-batal-cadangan { background: #6c757d; border: 1px solid #6c757d; color: #fff; }
    .btn-batal-cadangan:hover { background: #5a6268; color: #fff; }
    .bg-fuchsia { background-color: #f012be !important; color: #fff; }
</style>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <?php if ($is_ppsdm): ?>
            <div class="alert alert-info shadow-sm mb-3">
                <i class="fas fa-info-circle mr-2"></i><strong>Mode Tonton/Kontrol Terbatas:</strong> Anda login sebagai akun PPSDM. Tindakan pengubahan status cadangan dibatasi/dinonaktifkan.
            </div>
            <?php endif; ?>

            <?php if (empty($data_peserta)): ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    Data tidak ditemukan untuk <strong><?= htmlspecialchars($jenis_filter) ?></strong> (Gel. <?= htmlspecialchars($gel_filter) ?>). Pastikan status pengajuan sudah di tahap PPSDM/Lulus.
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                </div>
            <?php endif; ?>

            <div class="header-detail-banner shadow">
                <div>
                    <h1 class="h3 font-weight-bold mb-1"><?= htmlspecialchars($jenis_filter); ?></h1>
                    <p class="mb-0 text-white-50">Gelombang: <strong><?= htmlspecialchars($nama_gelombang_display); ?></strong> | Total: <?= count($data_peserta); ?> Peserta</p>
                </div>
                <a href="index_ppsdm.php" class="btn btn-outline-light" style="border-radius: 8px;">
                    <i class="fas fa-arrow-left mr-2"></i> Kembali
                </a>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="card main-card-ppsdm">
                <div class="card-header card-header-gradient">
                    <h3 class="card-title font-weight-bold">Daftar Peserta Uji Kompetensi</h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table id="tableDetailPeserta" class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th class="text-center" style="width: 5%">#</th>
                                    <th>Data Pegawai</th>
                                    <th>Jenis Pengajuan</th>
                                    <th>Catatan PPSDM</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-center">Hasil Ujikom</th>
                                    <th class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $no = 1; foreach ($data_peserta as $row): ?>
                                    <tr>
                                        <td class="align-middle text-center"><?= $no++; ?></td>
                                        <td class="align-middle">
                                            <strong><?= htmlspecialchars($row['nama']); ?></strong><br>
                                            <small class="text-muted"><?= htmlspecialchars($row['nip']); ?></small>
                                        </td>
                                        <td class="align-middle">
                                            <?php 
                                                $jp = $row['jenis_pengajuan'] ?? $jenis_filter;
                                                $badge_jp = (stripos($jp, 'kenaikan') !== false) ? 'badge-info' : 'badge-primary';
                                            ?>
                                            <span class="badge <?= $badge_jp; ?> p-2" style="font-size: 0.8rem;"><?= htmlspecialchars($jp); ?></span>
                                        </td>
                                        <td class="align-middle">
                                            <div class="note-bubble"><?= !empty($row['catatan_ppsdm']) ? htmlspecialchars($row['catatan_ppsdm']) : 'Tidak ada catatan'; ?></div>
                                        </td>
                                        <td class="align-middle text-center">
                                            <?php 
                                                $s = $row['status_pengajuan'];
                                                $badge = 'bg-secondary';
                                                
                                                if($s == 'Lulus' || $s == 'Disetujui') $badge = 'bg-success';
                                                elseif($s == 'Tidak Lulus') $badge = 'bg-danger';
                                                elseif(in_array($s, ['Proses PPSDM', 'Disetujui Direktur'])) $badge = 'bg-primary';
                                                elseif($s == 'Terjadwal') $badge = 'bg-info';
                                                elseif($s == 'Menunggu Jadwal Ujikom') $badge = 'bg-fuchsia';
                                                elseif($s == 'Cadangan') $badge = 'bg-warning text-dark';
                                                elseif($s == 'Selesai') $badge = 'bg-dark';
                                            ?>
                                            <span class="status-pill <?= $badge; ?>"><?= htmlspecialchars($s); ?></span>
                                        </td>
                                        <td class="align-middle text-center">
                                            <?php 
                                                $hasil = $row['hasil_ujikom'] ?? '-';
                                                $color = ($hasil == 'Lulus' || $s == 'Lulus') ? 'text-success' : (($hasil == 'Tidak Lulus' || $s == 'Tidak Lulus') ? 'text-danger' : 'text-muted');
                                            ?>
                                            <span class="font-weight-bold <?= $color; ?>"><?= htmlspecialchars($hasil); ?></span>
                                        </td>
                                        <td class="align-middle text-center">
                                            <div style="width: 150px; margin: 0 auto;">
                                                <?php 
                                                    $jenis_pengajuan = $row['jenis_pengajuan'] ?? $jenis_filter;
                                                    $url_detail = (stripos($jenis_pengajuan, 'kenaikan') !== false) 
                                                                ? 'detail_verif_kenaikan.php' 
                                                                : 'detail_verif_perpindahan.php';
                                                ?>
                                                <a href="<?= $url_detail; ?>?id=<?= $row['id']; ?>" class="btn-action btn-detail shadow-sm">
                                                    <i class="fas fa-file-alt mr-1"></i> Periksa Berkas
                                                </a>

                                                <?php if (in_array($s, ['Disetujui', 'Menunggu Jadwal Ujikom', 'Proses PPSDM'])): ?>
                                                    <button type="button" class="btn-action btn-jadwal shadow-sm" 
                                                            data-toggle="modal" data-target="#modalSetJadwal" 
                                                            data-id="<?= $row['id']; ?>" 
                                                            data-nama="<?= htmlspecialchars($row['nama']); ?>">
                                                        <i class="fas fa-calendar-plus mr-1"></i> Atur Jadwal
                                                    </button>
                                                <?php endif; ?>

                                                <?php if (in_array($s, ['Terjadwal', 'Selesai', 'Lulus', 'Tidak Lulus'])): ?>
                                                    <button type="button" class="btn-action btn-upload shadow-sm"
                                                            data-toggle="modal" data-target="#modalUploadHasil"
                                                            data-id="<?= $row['id']; ?>"
                                                            data-nama="<?= htmlspecialchars($row['nama']); ?>">
                                                        <i class="fas fa-upload mr-1"></i> Upload Hasil
                                                    </button>
                                                <?php endif; ?>

                                                <?php if (!in_array($s, ['Lulus', 'Tidak Lulus', 'Selesai'])): ?>
                                                    <?php if ($s != 'Cadangan'): ?>
                                                        <button type="button" class="btn-action btn-cadangan shadow-sm <?= $is_ppsdm ? 'disabled' : ''; ?>" 
                                                            <?= $is_ppsdm ? 'disabled title="Akses dinonaktifkan untuk role PPSDM"' : "onclick=\"setCadangan({$row['id']}, '" . addslashes($row['nama']) . "')\""; ?>>
                                                            <i class="fas fa-user-clock mr-1"></i> Set Cadangan
                                                        </button>
                                                    <?php else: ?>
                                                        <button type="button" class="btn-action btn-batal-cadangan shadow-sm <?= $is_ppsdm ? 'disabled' : ''; ?>" 
                                                            <?= $is_ppsdm ? 'disabled title="Akses dinonaktifkan untuk role PPSDM"' : "onclick=\"batalCadangan({$row['id']}, '" . addslashes($row['nama']) . "')\""; ?>>
                                                            <i class="fas fa-user-check mr-1"></i> Batal Cadangan
                                                        </button>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<div class="modal fade" id="modalSetJadwal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content" style="border-radius: 15px;">
            <div class="modal-header bg-fuchsia text-white">
                <h5 class="modal-title font-weight-bold">Atur Jadwal Uji Kompetensi</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form id="formSetJadwal">
                <input type="hidden" name="id_pengajuan" id="modal_id_pengajuan">
                <div class="modal-body">
                    <div class="alert alert-info">Peserta: <strong id="modal_nama_peserta"></strong></div>
                    <div class="row">
                        <div class="col-md-6"><div class="form-group"><label>Tanggal</label><input type="date" name="tanggal_ujikom" class="form-control" required></div></div>
                        <div class="col-md-6"><div class="form-group"><label>Waktu (WIB)</label><input type="time" name="jam_ujikom" class="form-control" required></div></div>
                    </div>
                    <div class="form-group"><label>Metode</label>
                        <select name="metode_ujikom" class="form-control">
                            <option value="Daring (Online Zoom/Teams)">Daring (Online Zoom/Teams)</option>
                            <option value="Luring (Tatap Muka)">Luring (Tatap Muka)</option>
                        </select>
                    </div>
                    <div class="form-group"><label>Lokasi / Tautan</label><textarea name="lokasi_ujikom" class="form-control" rows="2" required></textarea></div>
                    <div class="row">
                        <div class="col-md-6"><div class="form-group"><label>Pakaian</label><input type="text" name="pakaian_ujikom" class="form-control" placeholder="Contoh: Batik" required></div></div>
                        <div class="col-md-6"><div class="form-group"><label>Keterangan Tambahan</label><input type="text" name="keterangan_ujikom" class="form-control"></div></div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                    <button type="submit" class="btn btn-fuchsia font-weight-bold">Simpan Jadwal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalUploadHasil" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius: 15px;">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title font-weight-bold"><i class="fas fa-file-upload mr-2"></i>Upload Surat Hasil Ujikom</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form id="formUploadHasil" enctype="multipart/form-data">
                <input type="hidden" name="id_pengajuan" id="upload_id_pengajuan">
                <div class="modal-body">
                    <div class="alert alert-success">Peserta: <strong id="upload_nama_peserta"></strong></div>
                    
                    <div class="form-group">
                        <label class="font-weight-bold">Status Kelulusan</label>
                        <select name="hasil_ujikom" class="form-control" required>
                            <option value="">-- Pilih Hasil --</option>
                            <option value="Lulus">Lulus</option>
                            <option value="Tidak Lulus">Tidak Lulus</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold">Dokumen Surat Hasil (PDF/Gambar)</label>
                        <input type="file" name="file_hasil_ujikom" class="form-control-file" accept=".pdf,.jpg,.jpeg,.png" required>
                        <small class="text-muted">Format yang didukung: PDF, JPG, PNG. Maksimal 2MB.</small>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold">Catatan / Keterangan Tambahan</label>
                        <textarea name="catatan_hasil" class="form-control" rows="3" placeholder="Contoh: Surat Rekomendasi No..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success font-weight-bold">Unggah Dokumen</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php 
require_once 'template/footer.php'; 
if (isset($stmt)) $stmt->close();
if (isset($conn)) $conn->close(); 
?>

<script>
$(document).ready(function() {
    $('#tableDetailPeserta').DataTable({ 
        "responsive": true, 
        "autoWidth": false,
        "language": {
            "emptyTable": "Tidak ada data peserta dengan kriteria ini"
        }
    });

    // Event modal Set Jadwal
    $('#modalSetJadwal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget); 
        var modal = $(this);
        modal.find('#modal_id_pengajuan').val(button.data('id'));
        modal.find('#modal_nama_peserta').text(button.data('nama'));
    });

    // Event modal Upload Hasil Ujikom
    $('#modalUploadHasil').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var modal = $(this);
        modal.find('#upload_id_pengajuan').val(button.data('id'));
        modal.find('#upload_nama_peserta').text(button.data('nama'));
    });

    // Submit Set Jadwal
    $('#formSetJadwal').on('submit', function(e) {
        e.preventDefault();
        $.ajax({
            url: 'simpan_jadwal_ujikom.php', 
            type: 'POST',
            data: $(this).serialize(),
            success: function(res) {
                Swal.fire('Tersimpan!', 'Jadwal berhasil diperbarui.', 'success').then(() => location.reload());
            },
            error: function() {
                Swal.fire('Error!', 'Gagal menghubungi server.', 'error');
            }
        });
    });

    // Submit Upload Surat Hasil Ujikom
    $('#formUploadHasil').on('submit', function(e) {
        e.preventDefault();
        var formData = new FormData(this);

        $.ajax({
            url: 'simpan_upload_hasil.php',
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            success: function(res) {
                if(res.trim() === "success") {
                    Swal.fire('Berhasil!', 'Surat Hasil Ujikom telah berhasil diunggah.', 'success').then(() => location.reload());
                } else {
                    Swal.fire('Gagal!', 'Pesan Error: ' + res, 'error');
                }
            },
            error: function() {
                Swal.fire('Error!', 'Gagal memproses unggahan file ke server.', 'error');
            }
        });
    });
});

function setCadangan(id, nama) {
    Swal.fire({
        title: 'Jadikan Cadangan?',
        text: "Peserta: " + nama,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Proses',
        confirmButtonColor: '#ffc107'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'update_status_cadangan.php',
                type: 'POST',
                data: { id: id, action: 'set' },
                success: function(response) {
                    if (response.trim() === "success") {
                        Swal.fire('Berhasil!', 'Status telah diperbarui.', 'success').then(() => location.reload());
                    } else {
                        Swal.fire('Gagal!', 'Pesan: ' + response, 'error');
                    }
                }
            });
        }
    });
}

function batalCadangan(id, nama) {
    Swal.fire({
        title: 'Batalkan Status Cadangan?',
        text: "Peserta: " + nama,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Kembalikan'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'update_status_cadangan.php',
                type: 'POST',
                data: { id: id, action: 'batal' },
                success: function(response) {
                    if (response.trim() === "success") {
                        Swal.fire('Berhasil!', 'Status telah diperbarui.', 'success').then(() => location.reload());
                    }
                }
            });
        }
    });
}
</script>