<?php
/**
 * ==================================================================================
 * FILE: detail_gelombang_direktur.php
 * DESKRIPSI: Menampilkan detail peserta gelombang dengan link tombol detail dinamis 
 *            (Kenaikan Jabatan -> detail_verif_kenaikan.php | Perpindahan -> detail_verif_perpindahan.php)
 * ==================================================================================
 */

require_once 'auth_guard.php'; 
require_once 'koneksi.php';

$id_gel = $_GET['gel'] ?? '';
if (empty($id_gel)) { header("Location: index_direktur.php"); exit; }

// Ambil Nama Gelombang
$nama_gelombang = "Gelombang " . $id_gel;
$sql_g = "SELECT gelombang FROM tb_gelombang WHERE id = ?";
if ($stmt_g = $conn->prepare($sql_g)) {
    $stmt_g->bind_param("i", $id_gel);
    $stmt_g->execute();
    $res_g = $stmt_g->get_result();
    if ($row_g = $res_g->fetch_assoc()) { $nama_gelombang = $row_g['gelombang']; }
}

// Data Peserta (Difilter disamakan dengan index_direktur.php agar hanya menampilkan data kiriman)
$sql = "SELECT p.*, v.nama as nama_verifikator, g.gelombang as nama_gelombang_teks 
        FROM pengajuan_ujikom p
        LEFT JOIN users v ON p.verifikator_id = v.id
        LEFT JOIN tb_gelombang g ON p.gelombang = g.id
        WHERE (p.gelombang = ? OR p.gelombang = (SELECT gelombang FROM tb_gelombang WHERE id = ?)) 
        AND p.status_pengajuan IN ('Proses Direktur', 'Menunggu Verifikasi Direktur', 'Disetujui Direktur', 'Proses PPSDM', 'Terjadwal', 'Selesai')
        ORDER BY p.nama ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $id_gel, $id_gel);
$stmt->execute();
$data_peserta = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

require_once 'template/header.php'; 
require_once 'template/navbar.php'; 
require_once 'template/sidebar.php'; 
?>

<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap4.min.css">
<style>
    .main-card { border-radius: 15px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); border: none; }
    .dt-buttons .btn { margin-right: 10px; border-radius: 6px; font-weight: bold; }
    .table thead th { vertical-align: middle; white-space: nowrap; }
    .table tbody td { vertical-align: middle; }
</style>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2 align-items-center">
                <div class="col-sm-6">
                    <h1 class="font-weight-bold">Peserta: <?= htmlspecialchars($nama_gelombang); ?></h1>
                </div>
                <div class="col-sm-6 text-right">
                    <a href="index_direktur.php" class="btn btn-secondary shadow-sm"><i class="fas fa-arrow-left"></i> Kembali</a>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="card main-card">
                <div class="card-header bg-white py-3">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h5 class="mb-0 font-weight-bold text-primary">Daftar Pengusul (Filtered Kenaikan)</h5>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover" id="tableDetailPeserta">
                            <thead>
                                <tr class="text-center bg-light">
                                    <th width="5%">No</th>
                                    <th>Nama</th>
                                    <th>NIP</th>
                                    <th>Gelombang</th>
                                    <th>Jenis Pengajuan</th>
                                    <th>No HP/WA</th>
                                    <th>Pangkat/Golongan</th>
                                    <th>Jabatan Saat Ini</th>
                                    <th>JF PKP yang Dituju</th>
                                    <th>Unit Kerja</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $no=1; foreach($data_peserta as $p): ?>
                                <?php 
                                    // Tentukan jenis pengajuan dan warna badge
                                    $jenis = $p['jenis_pengajuan'] ?? $p['kategori'] ?? 'Kenaikan Jabatan';
                                    $is_perpindahan = (stripos($jenis, 'perpindahan') !== false);
                                    
                                    $badge_color = $is_perpindahan ? 'badge-warning' : 'badge-info';
                                    
                                    // Tentukan halaman tujuan detail secara dinamis
                                    $url_detail = $is_perpindahan ? 'detail_verif_perpindahan.php' : 'detail_verif_kenaikan.php';
                                ?>
                                <tr>
                                    <td class="text-center"><?= $no++; ?></td>
                                    <td class="font-weight-bold"><?= htmlspecialchars($p['nama']); ?></td>
                                    <td><?= htmlspecialchars($p['nip']); ?></td>
                                    <td class="text-center">
                                        <span class="badge badge-primary"><?= htmlspecialchars($p['nama_gelombang_teks'] ?? $p['gelombang'] ?? '-'); ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge <?= $badge_color; ?>"><?= htmlspecialchars($jenis); ?></span>
                                    </td>
                                    <td><?= htmlspecialchars($p['no_hp'] ?? $p['hp'] ?? '-'); ?></td>
                                    <td><?= htmlspecialchars($p['pangkat'] ?? '-'); ?></td>
                                    <td><?= htmlspecialchars($p['jabatan_saat_ini'] ?? '-'); ?></td>
                                    <td><?= htmlspecialchars($p['jf_pkp_tujuan'] ?? '-'); ?></td>
                                    <td><?= htmlspecialchars($p['unit_saat_ini'] ?? $p['unit_kerja'] ?? '-'); ?></td>
                                    <td class="text-center align-middle text-nowrap">
                                        <a href="<?= $url_detail; ?>?id=<?= $p['id']; ?>" class="btn btn-sm btn-info shadow-sm" title="Lihat Detail">
                                            <i class="fas fa-eye mr-1"></i> Detail
                                        </a>
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

<?php require_once 'template/footer.php'; ?>

<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap4.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>

<script>
$(document).ready(function() {
    var table = $('#tableDetailPeserta').DataTable({
        "responsive": true,
        "autoWidth": false,
        "pageLength": 25,
        "dom": '<"row"<"col-md-6"B><"col-md-6"f>>rt<"row"<"col-md-6"i><"col-md-6"p>>',
        "buttons": [
            {
                extend: 'excelHtml5',
                text: '<i class="fas fa-file-excel mr-1"></i> Export ke Excel',
                className: 'btn btn-success btn-sm shadow-sm',
                title: 'Data Peserta Ujikom - <?= htmlspecialchars($nama_gelombang); ?>',
                exportOptions: {
                    columns: [0, 1, 2, 3, 4, 5, 6, 7, 8, 9] 
                }
            }
        ],
        "language": {
            "emptyTable": "Belum ada data peserta pada gelombang ini",
            "search": "Cari Peserta:"
        }
    });
});
</script>