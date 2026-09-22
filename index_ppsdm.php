<?php
/**
 * ==================================================================================
 * FILE: index_ppsdm.php
 * DESKRIPSI: Dashboard Utama PPSDM dengan Fitur Direct Link Lihat Jadwal
 * PERBAIKAN: Menambahkan form input Pakaian, Keterangan Tambahan, & Lokasi di Modal Jadwal
 * UPDATE: Menonaktifkan sementara tombol Set Jadwal
 * ==================================================================================
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'auth_guard.php'; 
require_once 'koneksi.php';    

if (!isset($conn) || !$conn) {
    die("Fatal Error: Koneksi database tidak tersedia.");
}

// 1. Logika Update Status Baca
if (isset($_GET['update_read_p'])) {
    $ids_to_update = $conn->real_escape_string($_GET['update_read_p']);
    if (!empty($ids_to_update)) {
        $check_col = $conn->query("SHOW COLUMNS FROM pengajuan_ujikom LIKE 'tgl_update'");
        if ($check_col->num_rows > 0) {
            $conn->query("UPDATE pengajuan_ujikom SET is_read_ppsdm = 1, tgl_update = NOW() WHERE id IN ($ids_to_update)");
        } else {
            $conn->query("UPDATE pengajuan_ujikom SET is_read_ppsdm = 1 WHERE id IN ($ids_to_update)");
        }
    }
    header("Location: index_ppsdm.php");
    exit;
}

$page_title = "Panel Evaluasi PPSDM";

// 2. Query Statistik
$status_ppsdm = "'Proses PPSDM', 'Disetujui Direktur', 'Disetujui', 'Menunggu Jadwal Ujikom', 'Terjadwal', 'Selesai', 'Cadangan', 'Lulus', 'Tidak Lulus'";

$sql_stat = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status_pengajuan = 'Terjadwal' THEN 1 ELSE 0 END) as terjadwal,
                SUM(CASE WHEN status_pengajuan = 'Lulus' THEN 1 ELSE 0 END) as jml_lulus,
                SUM(CASE WHEN is_read_ppsdm = 0 THEN 1 ELSE 0 END) as belum_dilihat
             FROM pengajuan_ujikom 
             WHERE status_pengajuan IN ($status_ppsdm)";
$res_stat_query = $conn->query($sql_stat);
$res_stat = ($res_stat_query) ? $res_stat_query->fetch_assoc() : ['total'=>0, 'terjadwal'=>0, 'jml_lulus'=>0, 'belum_dilihat'=>0];

/**
 * 3. Query Grouping Tabel
 */
$sql_group = "SELECT 
                p.id as id_pengajuan_tunggal,
                p.gelombang as id_gel_asli,
                g.gelombang as nama_gel_text, 
                p.jenis_pengajuan, 
                MAX(p.surat_pengantar) as surat_pengantar,
                COUNT(p.id) as total_peserta,
                SUM(CASE WHEN p.status_pengajuan = 'Terjadwal' THEN 1 ELSE 0 END) as jml_terjadwal,
                SUM(CASE WHEN p.status_pengajuan = 'Cadangan' THEN 1 ELSE 0 END) as jml_cadangan,
                SUM(CASE WHEN p.status_pengajuan = 'Selesai' THEN 1 ELSE 0 END) as jml_selesai,
                SUM(CASE WHEN p.status_pengajuan = 'Lulus' THEN 1 ELSE 0 END) as jml_lulus,
                SUM(CASE WHEN p.status_pengajuan = 'Tidak Lulus' THEN 1 ELSE 0 END) as jml_tdk_lulus
              FROM pengajuan_ujikom p
              LEFT JOIN tb_gelombang g ON p.gelombang = g.id
              WHERE p.status_pengajuan IN ($status_ppsdm)
              GROUP BY p.gelombang, p.jenis_pengajuan
              ORDER BY p.gelombang DESC, p.jenis_pengajuan ASC";
$result_group = $conn->query($sql_group);

require_once 'template/header.php'; 
require_once 'template/navbar.php'; 
require_once 'template/sidebar.php'; 
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" type="text/css" href="https://npmcdn.com/flatpickr/dist/themes/airbnb.css">

<style>
    .content-wrapper { background-color: #f4f7f6; }
    .banner-ppsdm {
        background-color: #1a3a3a; 
        border-radius: 10px; padding: 25px; color: white; margin-bottom: 20px;
    }
    .stat-box {
        background: rgba(255, 255, 255, 0.15);
        border: 1px solid rgba(255,255,255,0.2);
        border-radius: 12px; padding: 15px; text-align: center; min-width: 140px;
    }
    .stat-box h2 { font-size: 28px; font-weight: 800; margin: 0; color: #fff; }
    .stat-box p { font-size: 11px; margin: 5px 0 0; color: #cbd5e0; font-weight: 700; text-transform: uppercase; }
    .main-card { border: none; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
    .table thead th { 
        background-color: #f8fafc; text-transform: uppercase; 
        font-size: 12px; letter-spacing: 0.5px; border-bottom: 2px solid #edf2f7; color: #64748b;
    }
    .badge-gel {
        background: #e2e8f0; color: #475569; padding: 8px 12px; 
        border-radius: 6px; font-weight: 700; display: inline-flex; align-items: center;
        position: relative;
    }
    .badge-peserta {
        background: #10b981; color: white; padding: 6px 15px; 
        border-radius: 6px; font-weight: 700;
    }
    .btn-lihat {
        background: #3b82f6; color: white; border-radius: 8px; 
        padding: 8px 15px; font-weight: 700; transition: 0.3s; border: none; font-size: 13px;
    }
    .btn-lihat:hover { background: #2563eb; color: white; transform: translateY(-2px); }
    
    .btn-jadwal {
        background: #f59e0b; color: white; border-radius: 8px; 
        padding: 8px 15px; font-weight: 700; transition: 0.3s; border: none; font-size: 13px;
    }
    .btn-jadwal:hover { background: #d97706; color: white; transform: translateY(-2px); }

    .btn-info-jadwal {
        background: #6366f1; color: white; border-radius: 8px; 
        padding: 8px 15px; font-weight: 700; transition: 0.3s; border: none; font-size: 13px;
    }
    .btn-info-jadwal:hover { background: #4f46e5; color: white; transform: translateY(-2px); }

    .btn-surat {
        border-radius: 8px; font-weight: bold; font-size: 13px; padding: 6px 12px;
        transition: 0.3s;
    }

    .dot-cadangan {
        height: 10px; width: 10px; background-color: #ef4444;
        border-radius: 50%; display: inline-block; margin-left: 8px;
        animation: pulse-red 2s infinite;
    }
    @keyframes pulse-red {
        0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); }
        70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(239, 68, 68, 0); }
        100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
    }
</style>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="banner-ppsdm shadow-sm d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h1 class="h4 font-weight-bold mb-1">Executive Dashboard PPSDM</h1>
                    <p class="mb-0 text-white-50 small">Data sinkron dengan validasi Direktur</p>
                </div>
                <div class="d-flex mt-3 mt-md-0" style="gap: 15px;">
                    <div class="stat-box">
                        <h2><?= number_format($res_stat['total']); ?></h2>
                        <p>Total Usulan</p>
                    </div>
                    <div class="stat-box">
                        <h2><?= number_format($res_stat['terjadwal']); ?></h2>
                        <p>Terjadwal</p>
                    </div>
                    <div class="stat-box">
                        <h2><?= number_format($res_stat['jml_lulus']); ?></h2>
                        <p>Lulus</p>
                    </div>
                </div>
            </div>

            <div class="card main-card">
                <div class="card-header bg-white py-3 border-0">
                    <h5 class="card-title font-weight-bold mb-0" style="color: #334155;">Daftar Gelombang Pengajuan</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table id="tablePPSDM" class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="text-center" style="width: 5%">NO</th>
                                    <th>GELOMBANG</th>
                                    <th class="text-center">TOTAL PESERTA</th>
                                    <th>JENIS PENGAJUAN</th>
                                    <th class="text-center">SURAT USULAN PESERTA UJI KOMPETENSI</th>
                                    <th class="text-center">AKSI</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $no = 1;
                                if ($result_group && $result_group->num_rows > 0):
                                    while ($row = $result_group->fetch_assoc()): 
                                        $nama_tampil = !empty($row['nama_gel_text']) ? $row['nama_gel_text'] : "Gelombang " . $row['id_gel_asli'];
                                        
                                        // LOGIKA TOMBOL: Jika (Terjadwal + Selesai + Lulus + Tidak Lulus) == Total Peserta
                                        $sudah_ok = (int)$row['jml_terjadwal'] + (int)$row['jml_selesai'] + (int)$row['jml_lulus'] + (int)$row['jml_tdk_lulus'];
                                        $is_fully_scheduled = ($row['total_peserta'] > 0 && $sudah_ok >= $row['total_peserta']);
                                        
                                        $ada_cadangan = ($row['jml_cadangan'] > 0);
                                ?>
                                <tr>
                                    <td class="text-center font-weight-bold text-muted"><?= $no++; ?></td>
                                    <td>
                                        <div class="badge-gel">
                                            <i class="fas fa-layer-group text-primary mr-2"></i>
                                            <?= htmlspecialchars($nama_tampil); ?>
                                            <?php if ($ada_cadangan): ?>
                                                <span class="dot-cadangan" title="Terdapat <?= $row['jml_cadangan']; ?> Peserta Cadangan"></span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($ada_cadangan): ?>
                                            <br><small class="text-danger font-weight-bold" style="font-size: 10px; margin-left: 2px;">
                                                <i class="fas fa-exclamation-circle"></i> ADA CADANGAN (<?= $row['jml_cadangan']; ?>)
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge-peserta"><?= $row['total_peserta']; ?> Orang</span>
                                    </td>
                                    <td>
                                        <span class="text-dark font-weight-bold"><?= htmlspecialchars($row['jenis_pengajuan']); ?></span>
                                    </td>
                                    <td class="text-center">
                                        <?php if(!empty($row['surat_pengantar'])): ?>
                                            <a href="uploads/pengumuman/<?= htmlspecialchars($row['surat_pengantar']); ?>" target="_blank" class="btn btn-outline-info btn-surat shadow-sm">
                                                <i class="fas fa-file-pdf mr-1"></i> Lihat Surat
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted small font-italic">Tidak Ada</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex justify-content-center" style="gap: 8px;">
                                            <a href="list_peserta_ppsdm.php?gel=<?= urlencode($row['id_gel_asli']); ?>&jenis=<?= urlencode($row['jenis_pengajuan']); ?>" 
                                               class="btn btn-lihat shadow-sm">
                                                <i class="fas fa-search mr-1"></i> LIHAT PESERTA
                                            </a>
                                            
                                            <?php if ($is_fully_scheduled): ?>
                                                <a href="lihat_jadwal.php?id=<?= $row['id_pengajuan_tunggal']; ?>&mode=bulk" 
                                                   class="btn btn-info-jadwal shadow-sm">
                                                   <i class="fas fa-eye mr-1"></i> LIHAT JADWAL
                                                </a>
                                            <?php else: ?>
                                                <!-- TOMBOL DINONAKTIFKAN SEMENTARA -->
                                                <button type="button" 
                                                      class="btn btn-secondary shadow-sm" disabled style="cursor: not-allowed;" title="Fitur Set Jadwal Dinonaktifkan Sementara"
                                                      data-id="<?= $row['id_pengajuan_tunggal']; ?>"
                                                      data-gel_id="<?= $row['id_gel_asli']; ?>"
                                                      data-gel_nama="<?= htmlspecialchars($nama_tampil); ?>"
                                                      data-jenis="<?= htmlspecialchars($row['jenis_pengajuan']); ?>">
                                                      <i class="fas fa-calendar-alt mr-1"></i> SET JADWAL
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5">
                                        <div class="text-muted">
                                            <i class="fas fa-folder-open fa-3x mb-3"></i>
                                            <p>Belum ada data gelombang dengan status terkait.</p>
                                        </div>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalJadwal" tabindex="-1" role="dialog" aria-labelledby="modalJadwalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content" style="border-radius: 15px; border: none;">
            <div class="modal-header" style="background-color: #1a3a3a; color: white; border-radius: 15px 15px 0 0;">
                <h5 class="modal-title font-weight-bold" id="modalJadwalLabel">
                    <i class="fas fa-calendar-plus mr-2"></i> Atur Jadwal Ujikom
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="formSimpanJadwal" enctype="multipart/form-data">
                <div class="modal-body p-4">
                    <div class="alert alert-info border-0 shadow-sm mb-4">
                        <div class="row text-small">
                            <div class="col-6"><strong>Gelombang:</strong> <span id="text_gel"></span></div>
                            <div class="col-6"><strong>Jenis:</strong> <span id="text_jenis"></span></div>
                        </div>
                    </div>

                    <input type="hidden" name="id_pengajuan" id="modal_id_pengajuan">
                    <input type="hidden" name="filter_gelombang" id="modal_filter_gelombang">
                    <input type="hidden" name="filter_jenis" id="modal_filter_jenis">
                    
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="font-weight-bold">Terapkan Jadwal Untuk Status: <span class="text-danger">*</span></label>
                            <select name="target_status" id="target_status" class="form-control" required>
                                <option value="ALL">-- SEMUA STATUS (Massal) --</option>
                                <option value="Disetujui Direktur">Disetujui Direktur</option>
                                <option value="Cadangan">Cadangan</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="font-weight-bold">Tanggal Ujikom <span class="text-danger">*</span></label>
                            <input type="text" name="tanggal_ujikom" id="tanggal_ujikom" class="form-control datepicker-input" required readonly placeholder="Pilih tanggal...">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="font-weight-bold">Jam Ujikom <span class="text-danger">*</span></label>
                            <input type="time" name="jam_ujikom" id="jam_ujikom" class="form-control" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="font-weight-bold">Metode Ujikom</label>
                            <select name="metode_ujikom" id="metode_ujikom" class="form-control">
                                <option value="Tatap Muka / Luring">Tatap Muka / Luring</option>
                                <option value="Daring / Online">Daring / Online</option>
                                <option value="Hybrid">Hybrid</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="font-weight-bold">Surat Pengumuman (PDF)</label>
                            <div class="custom-file">
                                <input type="file" name="surat_pengumuman_jadwal" class="custom-file-input" id="surat_pengumuman_jadwal" accept=".pdf">
                                <label class="custom-file-label" for="surat_pengumuman_jadwal">Pilih file...</label>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="font-weight-bold">Lokasi / Tautan Meeting <span class="text-danger">*</span></label>
                            <input type="text" name="lokasi_ujikom" id="lokasi_ujikom" class="form-control" placeholder="Contoh: Link Zoom / Ruang Rapat..." required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="font-weight-bold">Pakaian <span class="text-danger">*</span></label>
                            <input type="text" name="pakaian_ujikom" id="pakaian_ujikom" class="form-control" placeholder="Contoh: Kemeja Putih Berdasi..." required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="font-weight-bold">Keterangan Tambahan</label>
                            <textarea name="keterangan_ujikom" id="keterangan_ujikom" class="form-control" rows="2" placeholder="Opsional: Catatan tambahan untuk peserta..."></textarea>
                        </div>
                    </div>
                    </div>
                <div class="modal-footer bg-light" style="border-radius: 0 0 15px 15px;">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">TUTUP</button>
                    <button type="submit" class="btn btn-primary px-4" id="btnSimpan">
                        <i class="fas fa-save mr-1"></i> SIMPAN JADWAL
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'template/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/id.js"></script>

<script>
$(document).ready(function() {
    // Inisialisasi DataTable
    if ($.fn.DataTable.isDataTable('#tablePPSDM')) {
        $('#tablePPSDM').DataTable().destroy();
    }
    $('#tablePPSDM').DataTable({
        "responsive": true,
        "autoWidth": false,
        "language": { "search": "Cari:" }
    });

    // Inisialisasi Flatpickr
    flatpickr("#tanggal_ujikom", {
        mode: "range",
        showMonths: 2,
        dateFormat: "d F Y",
        locale: "id",
        minDate: "today"
    });

    $('.btn-buka-modal').on('click', function() {
        $('#formSimpanJadwal')[0].reset();
        $('#modal_id_pengajuan').val($(this).data('id'));
        $('#modal_filter_gelombang').val($(this).data('gel_id'));
        $('#modal_filter_jenis').val($(this).data('jenis'));
        $('#text_gel').text($(this).data('gel_nama'));
        $('#text_jenis').text($(this).data('jenis'));
        $('#modalJadwal').modal('show');
    });

    $('#formSimpanJadwal').on('submit', function(e) {
        e.preventDefault();
        const btn = $('#btnSimpan');
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Memproses...');

        $.ajax({
            url: 'simpan_jadwal_ujikom.php',
            type: 'POST',
            data: new FormData(this),
            contentType: false,
            processData: false,
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    Swal.fire({ icon: 'success', title: 'Berhasil!', text: response.message, timer: 1500, showConfirmButton: false })
                    .then(() => { location.reload(); });
                } else {
                    Swal.fire('Gagal!', response.message, 'error');
                    btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> SIMPAN JADWAL');
                }
            },
            error: function() {
                Swal.fire('Error!', 'Terjadi kesalahan sistem.', 'error');
                btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> SIMPAN JADWAL');
            }
        });
    });
});
</script>