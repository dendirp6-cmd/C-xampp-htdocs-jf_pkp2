<?php
// =========================================================================================
// FILE: daftar_pegawai.php - Halaman Master Data Pegawai Kementerian PKP
// UPDATE: 
// 1. Menambahkan Filter "Unit Kerja" yang terhubung dengan "Unit Organisasi".
// 2. Tanpa LIMIT (Memuat seluruh data 3.727+).
// 3. Sanitasi NIP (Tanda '@' / Email diubah jadi '-').
// =========================================================================================
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'auth_guard.php'; 
require_once 'koneksi.php'; 

$page = 'pegawai';
$sub_page = 'daftar_pegawai';
$page_title = 'Master Data Pegawai';

// --- FILTER HANDLING (AMBIL INPUT FORM GET) ---
$filter_unit       = $_GET['unit_organisasi'] ?? '';
$filter_unit_kerja = $_GET['unit_kerja'] ?? '';
$filter_gol        = $_GET['golongan'] ?? '';
$filter_jab        = $_GET['jabatan'] ?? '';
$filter_status     = $_GET['status'] ?? '';
$keyword           = trim($_GET['keyword'] ?? '');

// --- FETCH DATA UNIQUE UNTUK OPTION FILTER ---
$unit_org_list = $conn->query("SELECT DISTINCT unit_organisasi FROM pegawai WHERE unit_organisasi IS NOT NULL AND unit_organisasi != '-' AND unit_organisasi != '' ORDER BY unit_organisasi ASC");

// Fetch Unit Kerja (Jika Unit Organisasi dipilih, filter Unit Kerja sesuai Unor tersebut)
$sql_uk = "SELECT DISTINCT unit_kerja FROM pegawai WHERE unit_kerja IS NOT NULL AND unit_kerja != '-' AND unit_kerja != ''";
if (!empty($filter_unit)) {
    $sql_uk .= " AND unit_organisasi = '" . $conn->real_escape_string($filter_unit) . "'";
}
$sql_uk .= " ORDER BY unit_kerja ASC";
$unit_kerja_list = $conn->query($sql_uk);

$golongan_list = $conn->query("SELECT DISTINCT golongan FROM pegawai WHERE golongan IS NOT NULL AND golongan != '-' AND golongan != '' ORDER BY golongan ASC");
$jabatan_list  = $conn->query("SELECT DISTINCT jabatan FROM pegawai WHERE jabatan IS NOT NULL AND jabatan != '-' AND jabatan != '' ORDER BY jabatan ASC");
$status_list   = $conn->query("SELECT DISTINCT status FROM pegawai WHERE status IS NOT NULL AND status != '-' AND status != '' ORDER BY status ASC");

// --- BUILD QUERY MAIN DATA PEGAWAI ---
$where_clauses = ["1=1"];
$params = [];
$types = "";

if (!empty($filter_unit)) {
    $where_clauses[] = "unit_organisasi = ?";
    $types .= "s";
    $params[] = $filter_unit;
}
if (!empty($filter_unit_kerja)) {
    $where_clauses[] = "unit_kerja = ?";
    $types .= "s";
    $params[] = $filter_unit_kerja;
}
if (!empty($filter_gol)) {
    $where_clauses[] = "golongan = ?";
    $types .= "s";
    $params[] = $filter_gol;
}
if (!empty($filter_jab)) {
    $where_clauses[] = "jabatan = ?";
    $types .= "s";
    $params[] = $filter_jab;
}
if (!empty($filter_status)) {
    $where_clauses[] = "status = ?";
    $types .= "s";
    $params[] = $filter_status;
}
if (!empty($keyword)) {
    $where_clauses[] = "(nip LIKE ? OR nama_lengkap LIKE ? OR unit_kerja LIKE ? OR email LIKE ?)";
    $types .= "ssss";
    $search_term = "%{$keyword}%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$sql_where = implode(" AND ", $where_clauses);
$sql = "SELECT * FROM pegawai WHERE {$sql_where} ORDER BY id ASC";

$stmt = $conn->prepare($sql);
if (!empty($types)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result_pegawai = $stmt->get_result();

require_once 'template/header.php';
require_once 'template/navbar.php';
require_once 'template/sidebar.php';
?>

<!-- DataTables & Extension CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap4.min.css">

<style>
    .badge-pns { background-color: #28a745; color: #fff; }
    .badge-pppk { background-color: #17a2b8; color: #fff; }
    .badge-other { background-color: #6c757d; color: #fff; }
    .filter-card { border-top: 3px solid #007bff; }
    .table-pegawai td, .table-pegawai th { vertical-align: middle !important; }
</style>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="font-weight-bold text-white"><i class="fas fa-users text-primary mr-2"></i>Data Pegawai PKP</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                        <li class="breadcrumb-item active">Master Pegawai</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">

            <!-- PANEL FILTER DATA -->
            <div class="card card-default filter-card shadow-sm mb-4">
                <div class="card-header bg-light">
                    <h3 class="card-title font-weight-bold text-dark"><i class="fas fa-filter mr-1 text-info"></i> Filter & Pencarian Data</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-minus"></i></button>
                    </div>
                </div>
                <div class="card-body">
                    <form method="GET" action="" id="form-filter">
                        <div class="row">
                            <!-- Filter 1: Unit Organisasi -->
                            <div class="col-md-3 form-group">
                                <label class="small font-weight-bold">Unit Organisasi:</label>
                                <select name="unit_organisasi" id="select_unor" class="form-control form-control-sm" onchange="this.form.submit()">
                                    <option value="">-- Semua Unor --</option>
                                    <?php while ($row = $unit_org_list->fetch_assoc()): ?>
                                        <option value="<?= htmlspecialchars($row['unit_organisasi']); ?>" <?= $filter_unit === $row['unit_organisasi'] ? 'selected' : ''; ?>>
                                            <?= htmlspecialchars($row['unit_organisasi']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <!-- Filter 2: Unit Kerja (Menyesuaikan Unor) -->
                            <div class="col-md-3 form-group">
                                <label class="small font-weight-bold">Unit Kerja:</label>
                                <select name="unit_kerja" id="select_unit_kerja" class="form-control form-control-sm">
                                    <option value="">-- Semua Unit Kerja --</option>
                                    <?php while ($row = $unit_kerja_list->fetch_assoc()): ?>
                                        <option value="<?= htmlspecialchars($row['unit_kerja']); ?>" <?= $filter_unit_kerja === $row['unit_kerja'] ? 'selected' : ''; ?>>
                                            <?= htmlspecialchars($row['unit_kerja']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <!-- Filter 3: Golongan -->
                            <div class="col-md-2 form-group">
                                <label class="small font-weight-bold">Golongan:</label>
                                <select name="golongan" class="form-control form-control-sm">
                                    <option value="">-- Semua Gol --</option>
                                    <?php while ($row = $golongan_list->fetch_assoc()): ?>
                                        <option value="<?= htmlspecialchars($row['golongan']); ?>" <?= $filter_gol === $row['golongan'] ? 'selected' : ''; ?>>
                                            <?= htmlspecialchars($row['golongan']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <!-- Filter 4: Jenis Jabatan -->
                            <div class="col-md-2 form-group">
                                <label class="small font-weight-bold">Jenis Jabatan:</label>
                                <select name="jabatan" class="form-control form-control-sm">
                                    <option value="">-- Semua Jabatan --</option>
                                    <?php while ($row = $jabatan_list->fetch_assoc()): ?>
                                        <option value="<?= htmlspecialchars($row['jabatan']); ?>" <?= $filter_jab === $row['jabatan'] ? 'selected' : ''; ?>>
                                            <?= htmlspecialchars($row['jabatan']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <!-- Filter 5: Status -->
                            <div class="col-md-2 form-group">
                                <label class="small font-weight-bold">Status Pegawai:</label>
                                <select name="status" class="form-control form-control-sm">
                                    <option value="">-- Semua Status --</option>
                                    <?php while ($row = $status_list->fetch_assoc()): ?>
                                        <option value="<?= htmlspecialchars($row['status']); ?>" <?= $filter_status === $row['status'] ? 'selected' : ''; ?>>
                                            <?= htmlspecialchars($row['status']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <!-- Filter Teks Kata Kunci -->
                            <div class="col-md-8 form-group">
                                <label class="small font-weight-bold">Pencarian Teks Kata Kunci (NIP / Nama / Unit / Email):</label>
                                <input type="text" name="keyword" class="form-control form-control-sm" placeholder="Ketik kata kunci pencarian..." value="<?= htmlspecialchars($keyword); ?>">
                            </div>
                            
                            <!-- Tombol Aksi Filter -->
                            <div class="col-md-4 form-group d-flex align-items-end justify-content-end">
                                <button type="submit" class="btn btn-primary btn-sm px-3 mr-2 shadow-sm"><i class="fas fa-search mr-1"></i> Terapkan Filter</button>
                                <a href="daftar_pegawai.php" class="btn btn-secondary btn-sm px-3 shadow-sm"><i class="fas fa-sync-alt mr-1"></i> Reset</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- TABEL DATA PEGAWAI -->
            <div class="card card-outline card-primary shadow-sm mb-5">
                <div class="card-header border-0">
                    <h3 class="card-title font-weight-bold text-dark"><i class="fas fa-list text-primary mr-1"></i> Daftar Pegawai (<?= number_format($result_pegawai->num_rows, 0, ',', '.'); ?> Records Loaded)</h3>
                </div>
                <div class="card-body p-3">
                    <div class="table-responsive">
                        <table id="tabel-pegawai" class="table table-bordered table-striped table-hover table-pegawai w-100">
                            <thead class="bg-primary text-white text-center">
                                <tr>
                                    <th width="5%">No.</th>
                                    <th width="15%">NIP</th>
                                    <th width="20%">Nama Lengkap</th>
                                    <th width="8%">Gol.</th>
                                    <th width="22%">Nama Jabatan</th>
                                    <th width="18%">Unit Kerja</th>
                                    <th width="7%">Status</th>
                                    <th width="5%">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $no = 1;
                                while ($p = $result_pegawai->fetch_assoc()): 
                                    // Sanitasi NIP: Jika NIP berisi email (mengandung @), tampilkan '-'
                                    $raw_nip = trim($p['nip'] ?? '');
                                    $display_nip = (strpos($raw_nip, '@') !== false || empty($raw_nip)) ? '-' : $raw_nip;

                                    $status_badge = 'badge-other';
                                    if (strpos(strtoupper($p['status'] ?? ''), 'PNS') !== false) {
                                        $status_badge = 'badge-pns';
                                    } elseif (strpos(strtoupper($p['status'] ?? ''), 'PPPK') !== false) {
                                        $status_badge = 'badge-pppk';
                                    }
                                ?>
                                    <tr>
                                        <td class="text-center font-weight-bold"><?= $no++; ?></td>
                                        <td class="font-weight-bold text-dark text-center">
                                            <?php if ($display_nip !== '-'): ?>
                                                <code><?= htmlspecialchars($display_nip); ?></code>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="font-weight-bold"><?= htmlspecialchars($p['nama_lengkap'] ?? '-'); ?></td>
                                        <td class="text-center"><span class="badge badge-light border"><?= htmlspecialchars($p['golongan'] ?? '-'); ?></span></td>
                                        <td class="small"><?= htmlspecialchars($p['nama_jabatan'] ?? '-'); ?></td>
                                        <td class="small"><?= htmlspecialchars($p['unit_kerja'] ?? '-'); ?></td>
                                        <td class="text-center"><span class="badge <?= $status_badge; ?> px-2 py-1"><?= htmlspecialchars($p['status'] ?? '-'); ?></span></td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-info btn-xs btn-detail shadow-sm" data-pegawai='<?= json_encode($p, JSON_HEX_APOS | JSON_HEX_QUOT); ?>' title="Lihat Profil Lengkap">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </section>
</div>

<!-- MODAL DETAIL PEGAWAI -->
<div class="modal fade" id="modal-detail-pegawai" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title font-weight-bold"><i class="fas fa-id-card mr-2"></i> Profil Lengkap Pegawai</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-4">
                <div class="row mb-3">
                    <div class="col-md-12">
                        <h4 id="det-nama" class="font-weight-bold text-primary mb-1"></h4>
                        <p class="text-muted mb-0"><i class="fas fa-barcode mr-1"></i> NIP: <code id="det-nip"></code></p>
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-sm table-borderless">
                            <tr><th width="40%">Jenis Kelamin</th><td>: <span id="det-jk"></span></td></tr>
                            <tr><th>TTL</th><td>: <span id="det-ttl"></span></td></tr>
                            <tr><th>Umur</th><td>: <span id="det-umur"></span> Tahun</td></tr>
                            <tr><th>Agama</th><td>: <span id="det-agama"></span></td></tr>
                            <tr><th>Pendidikan</th><td>: <span id="det-pendidikan"></span></td></tr>
                            <tr><th>Email</th><td>: <span id="det-email"></span></td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-sm table-borderless">
                            <tr><th width="40%">Status</th><td>: <span id="det-status" class="badge badge-success"></span></td></tr>
                            <tr><th>Eselon / Gol</th><td>: <span id="det-eselon-gol"></span></td></tr>
                            <tr><th>Jenis Jabatan</th><td>: <span id="det-jabatan"></span></td></tr>
                            <tr><th>Nama Jabatan</th><td>: <span id="det-nama-jabatan"></span></td></tr>
                            <tr><th>Unit Organisasi</th><td>: <span id="det-unor"></span></td></tr>
                            <tr><th>Unit Kerja</th><td>: <span id="det-unit-kerja"></span></td></tr>
                        </table>
                    </div>
                </div>
                <div class="row mt-2 bg-light p-2 rounded">
                    <div class="col-md-6"><strong>Unit ES3:</strong> <span id="det-es3">-</span></div>
                    <div class="col-md-6"><strong>Unit ES4:</strong> <span id="det-es4">-</span></div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<?php require_once 'template/footer.php'; ?>

<!-- DataTables JS & Plugins -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap4.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>

<script>
$(document).ready(function() {
    var table = $('#tabel-pegawai').DataTable({
        "responsive": true,
        "lengthChange": true,
        "autoWidth": false,
        "pageLength": 10,
        "lengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Semua"]],
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Indonesian.json"
        },
        "dom": "<'row'<'col-md-6'l><'col-md-6 text-right'B>>" +
               "<'row'<'col-sm-12'tr>>" +
               "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
        "buttons": [
            { extend: 'copy', className: 'btn-sm btn-secondary' },
            { extend: 'excel', className: 'btn-sm btn-success', title: 'Data Pegawai PKP' },
            { extend: 'pdf', className: 'btn-sm btn-danger', title: 'Data Pegawai PKP' },
            { extend: 'print', className: 'btn-sm btn-info' }
        ]
    });

    // Handle Klik Tombol Detail
    $(document).on('click', '.btn-detail', function() {
        var data = $(this).data('pegawai');

        $('#det-nama').text(data.nama_lengkap || '-');
        
        var nipVal = (data.nip && data.nip.indexOf('@') === -1) ? data.nip : '-';
        $('#det-nip').text(nipVal);
        
        $('#det-jk').text(data.jenis_kelamin || '-');
        
        var ttl = (data.tempat_lahir || '-') + ', ' + (data.tanggal_lahir || '-');
        $('#det-ttl').text(ttl);
        
        $('#det-umur').text(data.umur || '-');
        $('#det-agama').text(data.agama || '-');
        $('#det-pendidikan').text(data.pendidikan || '-');
        $('#det-email').text(data.email || '-');
        $('#det-status').text(data.status || '-');
        
        var eselonGol = (data.eselon || '-') + ' / ' + (data.golongan || '-');
        $('#det-eselon-gol').text(eselonGol);
        
        $('#det-jabatan').text(data.jabatan || '-');
        $('#det-nama-jabatan').text(data.nama_jabatan || '-');
        $('#det-unor').text(data.unit_organisasi || '-');
        $('#det-unit-kerja').text(data.unit_kerja || '-');
        $('#det-es3').text(data.unit_es3 || '-');
        $('#det-es4').text(data.unit_es4 || '-');

        $('#modal-detail-pegawai').modal('show');
    });
});
</script>