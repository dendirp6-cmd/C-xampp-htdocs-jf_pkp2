<?php
// FILE: database.php - Halaman Database Pegawai JF PKP (Full Structure, Detail Modal, & Menu Aksi)

ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- AUTH GUARD ---
require_once 'auth_guard.php';

$user_nip_sesi = $_SESSION['user_nip_sesi'] ?? '';
$user_nama_sesi = $_SESSION['user_nama_sesi'] ?? null;
$user_role_sesi = $_SESSION['user_role_sesi'] ?? null;
$user_email_sesi = $_SESSION['user_email_sesi'] ?? null;
$join_date_sesi = $_SESSION['join_date'] ?? 'Maret 2023';

// Memuat koneksi database
require_once 'koneksi.php';

if (!isset($conn) || !$conn) {
    exit("<div style='padding: 20px; background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;'>
            <h1>❌ Koneksi Database Gagal!</h1>
            <p>Pastikan file <strong>koneksi.php</strong> sudah di-setup dengan benar.</p>
        </div>");
}

$page = 'database';
$sub_page = '';
$page_title = 'Database Pejabat Fungsional';

// --- AMBIL INPUT FILTER ---
$jenis_instansi = isset($_GET['jenisInstansi']) ? trim($_GET['jenisInstansi']) : ''; 
$nama_instansi  = isset($_GET['namaInstansi']) ? trim($_GET['namaInstansi']) : '';   
$provinsi       = isset($_GET['provinsi']) ? trim($_GET['provinsi']) : '';       
$kabupaten      = isset($_GET['kabupaten']) ? trim($_GET['kabupaten']) : '';      

$message = '';
$data_pegawai = [];
$master_provinsi = [];
$master_instansi_pusat = []; 
$master_tipe_dinas = [];     
$master_kabupaten = [];

$NAMA_TABEL_PEGAWAI = "detailpegawai";

// =========================================================
// SINKRONISASI & PEMBERSIHAN DROPDOWN (ANTI-DUPLIKAT)
// =========================================================

// 1. Master Tipe Dinas
$sql_tipe = "SELECT DISTINCT TRIM(tipe_dinas) as val FROM {$NAMA_TABEL_PEGAWAI} WHERE tipe_dinas IS NOT NULL AND tipe_dinas != '' ORDER BY val ASC";
$result_tipe = mysqli_query($conn, $sql_tipe);
if ($result_tipe) {
    while ($row = mysqli_fetch_assoc($result_tipe)) { $master_tipe_dinas[] = $row['val']; }
    mysqli_free_result($result_tipe);
}

// 2. Master Instansi Ind
$sql_instansi = "SELECT DISTINCT TRIM(instansi) as val FROM {$NAMA_TABEL_PEGAWAI} WHERE instansi IS NOT NULL AND instansi != '' ORDER BY val ASC";
$result_instansi = mysqli_query($conn, $sql_instansi);
if ($result_instansi) {
    while ($row = mysqli_fetch_assoc($result_instansi)) { $master_instansi_pusat[] = $row['val']; }
    mysqli_free_result($result_instansi);
}

// 3. Master Unit Eselon I
$sql_prov = "SELECT DISTINCT TRIM(unit_eselon_I) as val FROM {$NAMA_TABEL_PEGAWAI} WHERE unit_eselon_I IS NOT NULL AND unit_eselon_I != '' ORDER BY val ASC";
$result_prov = mysqli_query($conn, $sql_prov);
if ($result_prov) {
    while ($row = mysqli_fetch_assoc($result_prov)) { $master_provinsi[] = $row['val']; }
    mysqli_free_result($result_prov);
}

// 4. Master Eselon II 
if ($provinsi !== '') {
    $sql_kab = "SELECT DISTINCT TRIM(unit_eselon_II) as val FROM {$NAMA_TABEL_PEGAWAI} WHERE TRIM(unit_eselon_I) = ? AND unit_eselon_II IS NOT NULL AND unit_eselon_II != '' ORDER BY val ASC";
    $stmt_kab = mysqli_prepare($conn, $sql_kab);
    if ($stmt_kab) {
        mysqli_stmt_bind_param($stmt_kab, "s", $provinsi);
        mysqli_stmt_execute($stmt_kab);
        $result_kab = mysqli_stmt_get_result($stmt_kab);
        while ($row = mysqli_fetch_assoc($result_kab)) { $master_kabupaten[] = $row['val']; }
        mysqli_stmt_close($stmt_kab);
    }
} else {
    $sql_kab_all = "SELECT DISTINCT TRIM(unit_eselon_II) as val FROM {$NAMA_TABEL_PEGAWAI} WHERE unit_eselon_II IS NOT NULL AND unit_eselon_II != '' ORDER BY val ASC";
    $result_kab_all = mysqli_query($conn, $sql_kab_all);
    if ($result_kab_all) {
        while ($row = mysqli_fetch_assoc($result_kab_all)) { $master_kabupaten[] = $row['val']; }
        mysqli_free_result($result_kab_all);
    }
}

// =========================================================
// 5. QUERY SECURITY: FILTER DATA
// =========================================================
$where_clauses = [];
$param_values = [];
$param_types = "";

if ($jenis_instansi !== '') {
    $where_clauses[] = "TRIM(tipe_dinas) = ?";
    $param_values[] = $jenis_instansi;
    $param_types .= "s";
}
if ($nama_instansi !== '') {
    $where_clauses[] = "TRIM(instansi) = ?";
    $param_values[] = $nama_instansi;
    $param_types .= "s";
}
if ($provinsi !== '') {
    $where_clauses[] = "TRIM(unit_eselon_I) = ?";
    $param_values[] = $provinsi;
    $param_types .= "s";
}
if ($kabupaten !== '') {
    $where_clauses[] = "TRIM(unit_eselon_II) = ?";
    $param_values[] = $kabupaten;
    $param_types .= "s";
}

$where_sql = count($where_clauses) > 0 ? " WHERE " . implode(" AND ", $where_clauses) : "";

try {
    $sql_pegawai = "SELECT * FROM {$NAMA_TABEL_PEGAWAI} {$where_sql}";
    $stmt_pegawai = mysqli_prepare($conn, $sql_pegawai);
    
    if ($stmt_pegawai) {
        if (count($param_values) > 0) {
            mysqli_stmt_bind_param($stmt_pegawai, $param_types, ...$param_values);
        }
        mysqli_stmt_execute($stmt_pegawai);
        $result_pegawai = mysqli_stmt_get_result($stmt_pegawai);
        while ($row = mysqli_fetch_assoc($result_pegawai)) {
            $data_pegawai[] = $row;
        }
        mysqli_stmt_close($stmt_pegawai);
    } else {
        $message = "❌ Terjadi kesalahan query: " . mysqli_error($conn);
    }
} catch (Exception $e) {
    $message = "❌ Gagal memuat data: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $page_title; ?> | Instansi Pembina JF</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.25/css/dataTables.bootstrap4.min.css">
    <style>
        .brand-link { background-color: #111827; }
        .card-primary .card-header { background-color: #007bff !important; } 
        th, td { font-size: 13px !important; white-space: nowrap; text-align: left; vertical-align: middle !important; }
        .modal-body table th { width: 35%; background-color: #f4f6f9; }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

    <?php
    include 'template/navbar.php';
    include 'template/sidebar.php';
    ?>

    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0 text-white"><i class="fas fa-database text-white"></i> <?php echo $page_title; ?></h1>
                    </div>
                </div>
            </div>
        </div>

        <section class="content">
            <div class="container-fluid">

                <?php if ($message): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $message; ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>

                <div class="card card-primary">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-filter"></i> Filter Data Pegawai</h3>
                    </div>
                    <form method="GET" action="database.php" id="filterForm">
                        <div class="card-body">
                            <div class="row">
                                <div class="form-group col-md-3">
                                    <label for="jenisInstansi">Tipe Dinas</label>
                                    <select id="jenisInstansi" name="jenisInstansi" class="form-control">
                                        <option value="">-- Semua Tipe Dinas --</option>
                                        <?php foreach ($master_tipe_dinas as $tipe): ?>
                                            <option value="<?php echo htmlspecialchars($tipe); ?>" <?php echo $jenis_instansi === $tipe ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($tipe); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group col-md-3">
                                    <label for="namaInstansi">Instansi Induk</label>
                                    <select id="namaInstansi" name="namaInstansi" class="form-control">
                                        <option value="">-- Semua Instansi --</option>
                                        <?php foreach ($master_instansi_pusat as $instansi): ?>
                                            <option value="<?php echo htmlspecialchars($instansi); ?>" <?php echo $nama_instansi === $instansi ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($instansi); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group col-md-3">
                                    <label for="provinsiSelect">Unit Eselon I</label>
                                    <select id="provinsiSelect" name="provinsi" class="form-control">
                                        <option value="">-- Semua Eselon I --</option>
                                        <?php foreach ($master_provinsi as $prov): ?>
                                            <option value="<?php echo htmlspecialchars($prov); ?>" <?php echo $provinsi === $prov ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($prov); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group col-md-3">
                                    <label for="kabupatenSelect">Unit Eselon II</label>
                                    <select id="kabupatenSelect" name="kabupaten" class="form-control">
                                        <option value="">-- Semua Eselon II --</option>
                                        <?php foreach ($master_kabupaten as $kab): ?>
                                            <option value="<?php echo htmlspecialchars($kab); ?>" <?php echo $kabupaten === $kab ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($kab); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Terapkan Filter</button>
                            <a href="database.php" class="btn btn-secondary"><i class="fas fa-redo"></i> Reset Filter</a>
                        </div>
                    </form>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Tabel Data Pegawai JF PKP (<span class="badge badge-info"><?php echo count($data_pegawai); ?></span> Data)</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="tabelPegawai" class="table table-bordered table-striped" style="width:100%">
                                <thead>
                                    <tr>
                                        <th style="width: 130px; text-align: center;">Aksi</th>
                                        <th>No.</th>
                                        <th>NIP</th>
                                        <th>Nama Lengkap</th>
                                        <th>Status Kepegawaian</th>
                                        <th>Jenis Kelamin</th>
                                        <th>Tipe Dinas</th>
                                        <th>Instansi</th>
                                        <th>Unit Eselon I</th>
                                        <th>Unit Eselon II</th>
                                        <th>Pangkat / Gol.</th>
                                        <th>Status JF</th>
                                        <th>Jenjang JF Terakhir</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($data_pegawai)): $no = 1; foreach ($data_pegawai as $pegawai): ?>
                                        <tr>
                                            <td style="text-align: center;">
                                                <div class="btn-group">
                                                    <button type="button" class="btn btn-xs btn-info btn-detail" data-pegawai='<?php echo json_encode($pegawai, JSON_HEX_APOS | JSON_HEX_QUOT); ?>' title="Detail Data">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <a href="edit_pegawai.php?id=<?php echo urlencode($pegawai['id']); ?>" class="btn btn-xs btn-warning text-white" title="Edit Data">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="hapus_pegawai.php?id=<?php echo urlencode($pegawai['id']); ?>" class="btn btn-xs btn-danger btn-hapus" title="Hapus Data">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </a>
                                                </div>
                                            </td>
                                            <td><?php echo $no++; ?></td>
                                            <td><strong><?php echo htmlspecialchars($pegawai['nip'] ?? '-'); ?></strong></td>
                                            <td><?php echo htmlspecialchars($pegawai['nama_lengkap'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($pegawai['status_kepegawaian'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($pegawai['jenis_kelamin'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($pegawai['tipe_dinas'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($pegawai['instansi'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($pegawai['unit_eselon_I'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($pegawai['unit_eselon_II'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($pegawai['pangkat_golongan'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($pegawai['status_jf'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($pegawai['jenjang_jf_pkp_terakhir'] ?? '-'); ?></td>
                                        </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </section>
    </div>

    <div class="modal fade" id="modalDetailPegawai" tabindex="-1" role="dialog" aria-labelledby="modalDetailTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
            <div class="modal-content">
                <div class="modal-header bg-info">
                    <h5 class="modal-title" id="modalDetailTitle"><i class="fas fa-user-id-card"></i> Detail Lengkap Pegawai</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-0">
                    <table class="table table-striped table-bordered m-0">
                        <tbody>
                            <tr><th>Timestamp Input</th><td id="det-timestamp"></td></tr>
                            <tr><th>ID Sistem</th><td id="det-id"></td></tr>
                            <tr><th>NIP</th><td id="det-nip" style="font-weight: bold;"></td></tr>
                            <tr><th>Nama Lengkap</th><td id="det-nama"></td></tr>
                            <tr><th>Jenis Kelamin</th><td id="det-jk"></td></tr>
                            <tr><th>Email</th><td id="det-email"></td></tr>
                            <tr><th>Nomor HP</th><td id="det-hp"></td></tr>
                            <tr><th>Status Kepegawaian</th><td id="det-status-kep"></td></tr>
                            <tr><th>Tipe Dinas</th><td id="det-tipe-dinas"></td></tr>
                            <tr><th>Instansi Induk</th><td id="det-instansi"></td></tr>
                            <tr><th>Unit Eselon I</th><td id="det-eselon-i"></td></tr>
                            <tr><th>Unit Eselon II</th><td id="det-eselon-ii"></td></tr>
                            <tr><th>Unit Eselon III / IV</th><td id="det-eselon-iii-iv"></td></tr>
                            <tr><th>Pangkat / Golongan</th><td id="det-pangkat"></td></tr>
                            <tr><th>Status JF</th><td id="det-status-jf"></td></tr>
                            <tr><th>Jenjang JF PKP Terakhir</th><td id="det-jenjang-jf"></td></tr>
                            <tr><th>TMT JF Terakhir</th><td id="det-tmt-jf"></td></tr>
                            <tr><th>Jenjang Pendidikan Terakhir</th><td id="det-pendidikan"></td></tr>
                            <tr><th>Rumpun Ilmu</th><td id="det-rumpun"></td></tr>
                            <tr><th>Jurusan / Program Studi</th><td id="det-prodi"></td></tr>
                            <tr><th>Saran & Masukan</th><td id="det-saran" style="white-space: normal;"></td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <footer class="main-footer">
        <strong>© <?php echo date('Y'); ?> Instansi Pembina JF</strong>.
    </footer>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.10.25/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.10.25/js/dataTables.bootstrap4.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>

<script>
$(function () {
    // Inisialisasi DataTables
    $("#tabelPegawai").DataTable({
        "scrollX": true,
        "pageLength": 10,
        "order": [[1, "asc"]], // Default urut berdasarkan No.
        "columnDefs": [
            { "orderable": false, "targets": 0 } // Kolom Aksi tidak bisa di-sort
        ],
        "language": { "url": "https://cdn.datatables.net/plug-ins/1.10.25/i18n/Indonesian.json" }
    });

    // Auto-submit saat filter dropdown berubah
    $('#jenisInstansi, #namaInstansi, #provinsiSelect, #kabupatenSelect').on('change', function() {
        if(this.id === 'provinsiSelect') {
            $('#kabupatenSelect').val(''); 
        }
        $('#filterForm').submit();
    });

    // Handle Tombol Detail Klik
    $('#tabelPegawai').on('click', '.btn-detail', function() {
        const data = $(this).data('pegawai');
        
        $('#det-timestamp').text(data.timestamp || '-');
        $('#det-id').text(data.id || '-');
        $('#det-nip').text(data.nip || '-');
        $('#det-nama').text(data.nama_lengkap || '-');
        $('#det-jk').text(data.jenis_kelamin || '-');
        $('#det-email').text(data.email || '-');
        $('#det-hp').text(data.nomor_hp || '-');
        $('#det-status-kep').text(data.status_kepegawaian || '-');
        $('#det-tipe-dinas').text(data.tipe_dinas || '-');
        $('#det-instansi').text(data.instansi || '-');
        $('#det-eselon-i').text(data.unit_eselon_I || '-');
        $('#det-eselon-ii').text(data.unit_eselon_II || '-');
        $('#det-eselon-iii-iv').text(data.unit_eselon_III_IV || '-');
        $('#det-pangkat').text(data.pangkat_golongan || '-');
        $('#det-status-jf').text(data.status_jf || '-');
        $('#det-jenjang-jf').text(data.jenjang_jf_pkp_terakhir || '-');
        $('#det-tmt-jf').text(data.tmt_jf_terkahir || '-');
        $('#det-pendidikan').text(data.jenjang_pendidikan_terakhir || '-');
        $('#det-rumpun').text(data.rumpun_ilmu || '-');
        $('#det-prodi').text(data.jurusan_program_studi || '-');
        $('#det-saran').text(data.saran_masukan || '-');

        $('#modalDetailPegawai').modal('show');
    });

    // JS Konfirmasi Hapus Data (Anti Kasus Gak Sengaja Kepencet)
    $('#tabelPegawai').on('click', '.btn-hapus', function(e) {
        e.preventDefault();
        const urlHref = $(this).attr('href');
        
        if (confirm("Apakah Anda yakin ingin menghapus data pegawai ini secara permanen?")) {
            window.location.href = urlHref;
        }
    });
});
</script>
</body>
</html>
<?php
mysqli_close($conn);
ob_end_flush();
?>