<?php
/**
 * FILE: rekap_ujikom.php
 * DESKRIPSI: Halaman Rekap Peserta Ujikom + Import CSV + Fitur Tambah Data Manual (Popup Modal)
 */

// 1. Inisialisasi Session & Error Reporting
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 2. Proteksi Halaman
$user_role = $_SESSION['user_role_sesi'] ?? 'Guest';
$is_admin_verifikator = ($user_role === 'user_admin' || $user_role === 'user_verifikator' || $user_role === 'user_super_admin');

if (!$is_admin_verifikator) {
    header("Location: index_asli.php");
    exit();
}

// 3. Variabel Penanda Sidebar Menu Data Ujikom
$page = 'ujikom';
$sub_page = 'rekap_ujikom';

// 4. Sertakan file koneksi database Anda
include 'koneksi.php'; 

if (!isset($koneksi)) {
    if (isset($conn)) {
        $koneksi = $conn;
    } elseif (isset($db)) {
        $koneksi = $db;
    } else {
        die("Gagal memuat koneksi database. Pastikan nama variabel koneksi di koneksi.php Anda adalah \$koneksi, \$conn, atau \$db.");
    }
}

$alert_status = '';
$alert_message = '';

// =========================================================
// LOGIKA PROSES TAMBAH DATA MANUAL VIA POPUP MODAL
// =========================================================
if (isset($_POST['btn_tambah_manual'])) {
    $nama                = mysqli_real_escape_string($koneksi, $_POST['nama']);
    $nip                 = mysqli_real_escape_string($koneksi, $_POST['nip']);
    $unit_kerja_saat_ini = mysqli_real_escape_string($koneksi, $_POST['unit_kerja_saat_ini']);
    $jenis_ujian         = mysqli_real_escape_string($koneksi, $_POST['jenis_ujian']);
    $jabatan_saat_ini    = mysqli_real_escape_string($koneksi, $_POST['jabatan_saat_ini']);
    $jabatan_dituju      = mysqli_real_escape_string($koneksi, $_POST['jabatan_dituju']);
    $kelulusan           = mysqli_real_escape_string($koneksi, $_POST['kelulusan']);
    $periode_ujikom      = mysqli_real_escape_string($koneksi, $_POST['periode_ujikom']);
    $keterangan          = mysqli_real_escape_string($koneksi, $_POST['keterangan']);
    $no_hp               = mysqli_real_escape_string($koneksi, $_POST['no_hp']);

    // Validasi Cek Ganda berdasarkan NIP dan Periode
    $cek_ganda = mysqli_query($koneksi, "SELECT id FROM rekap_peserta_ujikom WHERE nip = '$nip' AND periode_ujikom = '$periode_ujikom'");
    if (mysqli_num_rows($cek_ganda) > 0) {
        $alert_status = 'danger';
        $alert_message = "Gagal menyimpan! Data dengan NIP <strong>$nip</strong> pada periode <strong>$periode_ujikom</strong> sudah ada di database.";
    } else {
        $sql_insert = "INSERT INTO rekap_peserta_ujikom 
                       (nama, nip, unit_kerja_saat_ini, jenis_ujian, jabatan_saat_ini, jabatan_dituju, kelulusan, periode_ujikom, keterangan, no_hp) 
                       VALUES 
                       ('$nama', '$nip', '$unit_kerja_saat_ini', '$jenis_ujian', '$jabatan_saat_ini', '$jabatan_dituju', '$kelulusan', '$periode_ujikom', '$keterangan', '$no_hp')";
        
        if (mysqli_query($koneksi, $sql_insert)) {
            $alert_status = 'success';
            $alert_message = "Data peserta <strong>$nama</strong> berhasil ditambahkan secara manual.";
        } else {
            $alert_status = 'danger';
            $alert_message = "Gagal menambah data: " . mysqli_error($koneksi);
        }
    }
}

// =========================================================
// LOGIKA PROSES TRUNCATE (KOSONGKAN DATA)
// =========================================================
if (isset($_POST['btn_kosongkan_data'])) {
    $sql_truncate = "TRUNCATE TABLE rekap_peserta_ujikom";
    if (mysqli_query($koneksi, $sql_truncate)) {
        $alert_status = 'success';
        $alert_message = "Seluruh data dalam tabel <strong>rekap_peserta_ujikom</strong> berhasil dihapus secara permanen.";
    } else {
        $alert_status = 'danger';
        $alert_message = "Gagal mengosongkan data tabel: " . mysqli_error($koneksi);
    }
}

// =========================================================
// LOGIKA PROSES IMPORT CSV
// =========================================================
if (isset($_POST['btn_import_csv'])) {
    if (isset($_FILES['file_csv']) && $_FILES['file_csv']['error'] == 0) {
        
        $file_name = $_FILES['file_csv']['name'];
        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        if ($ext === 'csv') {
            $file_tmp = $_FILES['file_csv']['tmp_name'];
            
            if (($handle = fopen($file_tmp, "r")) !== FALSE) {
                fgetcsv($handle, 1000, ","); 
                
                $sukses_insert = 0;
                $gagal_insert = 0;
                
                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    $nama                  = mysqli_real_escape_string($koneksi, $data[1] ?? '');
                    $nip                   = mysqli_real_escape_string($koneksi, $data[2] ?? '');
                    $unit_kerja_saat_ini   = mysqli_real_escape_string($koneksi, $data[3] ?? '');
                    $jenis_ujian           = mysqli_real_escape_string($koneksi, $data[4] ?? '');
                    $jabatan_saat_ini      = mysqli_real_escape_string($koneksi, $data[5] ?? '');
                    $jabatan_dituju        = mysqli_real_escape_string($koneksi, $data[6] ?? '');
                    $kelulusan             = mysqli_real_escape_string($koneksi, $data[7] ?? '');
                    $periode_ujikom        = mysqli_real_escape_string($koneksi, $data[8] ?? '');
                    $keterangan            = mysqli_real_escape_string($koneksi, $data[9] ?? '');
                    $no_hp                 = mysqli_real_escape_string($koneksi, $data[10] ?? '');
                    
                    if (empty($nip) && empty($nama)) {
                        continue;
                    }
                    
                    $cek_ganda = mysqli_query($koneksi, "SELECT id FROM rekap_peserta_ujikom WHERE nip = '$nip' AND periode_ujikom = '$periode_ujikom'");
                    if (mysqli_num_rows($cek_ganda) > 0) {
                        $gagal_insert++;
                        continue;
                    }
                    
                    $sql_import = "INSERT INTO rekap_peserta_ujikom 
                                   (nama, nip, unit_kerja_saat_ini, jenis_ujian, jabatan_saat_ini, jabatan_dituju, kelulusan, periode_ujikom, keterangan, no_hp) 
                                   VALUES 
                                   ('$nama', '$nip', '$unit_kerja_saat_ini', '$jenis_ujian', '$jabatan_saat_ini', '$jabatan_dituju', '$kelulusan', '$periode_ujikom', '$keterangan', '$no_hp')";
                    
                    if (mysqli_query($koneksi, $sql_import)) {
                        $sukses_insert++;
                    } else {
                        $gagal_insert++;
                    }
                }
                fclose($handle);
                
                $alert_status = 'success';
                $alert_message = "Proses import selesai. Berhasil: <strong>$sukses_insert</strong> data. Dilewati/Gagal: <strong>$gagal_insert</strong> data.";
            } else {
                $alert_status = 'danger';
                $alert_message = "Gagal membuka file CSV.";
            }
        } else {
            $alert_status = 'danger';
            $alert_message = "Format file salah! Harus berkstensi <strong>.csv</strong>";
        }
    } else {
        $alert_status = 'danger';
        $alert_message = "Silakan pilih file CSV terlebih dahulu.";
    }
}

// 5. Query Ambil Data
$query = "SELECT * FROM rekap_peserta_ujikom ORDER BY id DESC";
$result = mysqli_query($koneksi, $query);

if (!$result) {
    die("Gagal mengambil data dari database: " . mysqli_error($koneksi));
}

$total_peserta = mysqli_num_rows($result);
?>

<?php include 'template/header.php'; ?>

<!-- Link DataTables CSS -->
<link rel="stylesheet" href="plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="plugins/datatables-responsive/css/responsive.bootstrap4.min.css">

<div class="wrapper">

    <?php include 'template/navbar.php'; ?>
    <?php include 'template/sidebar.php'; ?>

    <div class="content-wrapper">
        <section class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1>Rekap Peserta Ujikom</h1>
                    </div>
                </div>
            </div>
        </section>

        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">
                
                <?php if (!empty($alert_status)): ?>
                    <div class="alert alert-<?= $alert_status; ?> alert-dismissible fade show" role="alert">
                        <i class="icon fas <?= ($alert_status == 'success') ? 'fa-check' : 'fa-ban'; ?>"></i>
                        <?= $alert_message; ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-12">
                        
                        <div class="card card-primary card-outline">
                            <div class="card-header d-flex align-items-center justify-content-between">
                                <h3 class="card-title">Daftar Tugas dan Fungsi Evaluator (Total: <?= $total_peserta; ?>)</h3>
                                <div class="card-tools ml-auto d-flex">
                                    
                                    <?php if ($total_peserta > 0): ?>
                                    <form action="" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin MENGHAPUS SEMUA DATA secara permanen?');" class="mr-2">
                                        <button type="submit" name="btn_kosongkan_data" class="btn btn-sm btn-danger">
                                            <i class="fas fa-trash-alt"></i> Kosongkan Semua Data
                                        </button>
                                    </form>
                                    <?php endif; ?>

                                    <!-- TOMBOL TAMBAH DATA MANUAL -->
                                    <button type="button" class="btn btn-sm btn-success mr-2" data-toggle="modal" data-target="#modalTambahManual">
                                        <i class="fas fa-plus"></i> Tambah Data Manual
                                    </button>

                                    <!-- TOMBOL IMPORT CSV -->
                                    <button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#modalImportCSV">
                                        <i class="fas fa-upload"></i> Import CSV / Excel
                                    </button>
                                </div>
                            </div>
                            
                            <div class="card-body p-3">
                                <table id="tabelRekapUjikom" class="table table-hover table-bordered table-striped width-100">
                                    <thead class="bg-light">
                                        <tr>
                                            <th style="width: 40px;" class="text-center">NO.</th>
                                            <th>NAMA</th>
                                            <th>NIP</th>
                                            <th>UNIT KERJA SAAT INI</th>
                                            <th>JENIS UJIAN</th>
                                            <th>JABATAN SAAT INI</th>
                                            <th>JABATAN DITUJU</th>
                                            <th>KELULUSAN</th>
                                            <th>PERIODE UJIKOM</th>
                                            <th>KETERANGAN</th>
                                            <th>No HP</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $no = 1;
                                        if ($total_peserta > 0) {
                                            while ($row = mysqli_fetch_assoc($result)) {
                                                ?>
                                                <tr>
                                                    <td class="text-center"><?= $no++; ?></td>
                                                    <td><strong><?= htmlspecialchars($row['nama']); ?></strong></td>
                                                    <td><?= htmlspecialchars($row['nip']); ?></td>
                                                    <td><?= htmlspecialchars($row['unit_kerja_saat_ini']); ?></td>
                                                    <td class="text-center">
                                                        <span class="badge bg-info p-2">
                                                            <?= htmlspecialchars($row['jenis_ujian']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?= htmlspecialchars($row['jabatan_saat_ini']); ?></td>
                                                    <td><span class="text-indigo font-weight-bold"><?= htmlspecialchars($row['jabatan_dituju']); ?></span></td>
                                                    <td class="text-center">
                                                        <?php 
                                                        $status = strtolower($row['kelulusan'] ?? '');
                                                        if (strpos($status, 'lulus') !== false && strpos($status, 'tidak') === false) {
                                                            echo '<span class="badge bg-success p-2">'.htmlspecialchars($row['kelulusan']).'</span>';
                                                        } elseif (strpos($status, 'tidak') !== false) {
                                                            echo '<span class="badge bg-danger p-2">'.htmlspecialchars($row['kelulusan']).'</span>';
                                                        } else {
                                                            echo '<span class="badge bg-warning p-2">'.htmlspecialchars($row['kelulusan'] ?? 'Pending').'</span>';
                                                        }
                                                        ?>
                                                    </td>
                                                    <td class="text-center"><span class="badge bg-secondary p-2"><?= htmlspecialchars($row['periode_ujikom']); ?></span></td>
                                                    <td><small><?= htmlspecialchars($row['keterangan'] ?? ''); ?></small></td>
                                                    <td><?= htmlspecialchars($row['no_hp']); ?></td>
                                                </tr>
                                                <?php
                                            }
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </section>
    </div>

    <!-- MODAL POPUP: TAMBAH DATA MANUAL -->
    <div class="modal fade" id="modalTambahManual" tabindex="-1" role="dialog" aria-labelledby="modalTambahLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <form action="" method="POST">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title" id="modalTambahLabel"><i class="fas fa-user-plus mr-2"></i> Tambah Data Peserta Ujikom Manual</h5>
                        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="row">
                            <div class="form-group col-md-6">
                                <label class="font-weight-bold">Nama Lengkap <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="nama" placeholder="Masukkan nama beserta gelar" required>
                            </div>
                            <div class="form-group col-md-6">
                                <label class="font-weight-bold">NIP <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="nip" maxlength="18" placeholder="Masukkan 18 digit NIP" required>
                            </div>
                            <div class="form-group col-md-6">
                                <label class="font-weight-bold">No. HP / WhatsApp <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="no_hp" placeholder="Contoh: 08123456789" required>
                            </div>
                            <div class="form-group col-md-6">
                                <label class="font-weight-bold">Periode Ujikom <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="periode_ujikom" placeholder="Contoh: Juni 2026" required>
                            </div>
                            <div class="form-group col-md-12">
                                <label class="font-weight-bold">Unit Kerja Saat Ini <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="unit_kerja_saat_ini" placeholder="Nama Dinas / Badan / Balai" required>
                            </div>
                            <div class="form-group col-md-4">
                                <label class="font-weight-bold">Jenis Ujian <span class="text-danger">*</span></label>
                                <select class="form-control" name="jenis_ujian" required>
                                    <option value="">-- Pilih Jenis --</option>
                                    <option value="Kenaikan Jenjang">Kenaikan Jenjang</option>
                                    <option value="Perpindahan">Perpindahan</option>
                                    <option value="Alih Jabatan">Alih Jabatan</option>
                                </select>
                            </div>
                            <div class="form-group col-md-4">
                                <label class="font-weight-bold">Jabatan Saat Ini <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="jabatan_saat_ini" placeholder="Contoh: Ahli Muda" required>
                            </div>
                            <div class="form-group col-md-4">
                                <label class="font-weight-bold">Jabatan Dituju <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="jabatan_dituju" placeholder="Contoh: Ahli Madya" required>
                            </div>
                            <div class="form-group col-md-6">
                                <label class="font-weight-bold">Status Kelulusan <span class="text-danger">*</span></label>
                                <select class="form-control" name="kelulusan" required>
                                    <option value="Lulus">Lulus</option>
                                    <option value="Tidak">Tidak Lulus</option>
                                    <option value="Pending">Pending</option>
                                </select>
                            </div>
                            <div class="form-group col-md-6">
                                <label class="font-weight-bold">Keterangan</label>
                                <input type="text" class="form-control" name="keterangan" placeholder="Catatan tambahan (opsional)">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                        <button type="submit" name="btn_tambah_manual" class="btn btn-success"><i class="fas fa-save mr-1"></i> Simpan Data</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL POPUP: IMPORT CSV -->
    <div class="modal fade" id="modalImportCSV" tabindex="-1" role="dialog" aria-labelledby="modalImportLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form action="" method="POST" enctype="multipart/form-data">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="modalImportLabel"><i class="fas fa-file-csv mr-2"></i> Import Data Rekap dari CSV</h5>
                        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="file_csv">Pilih File CSV (.csv)</label>
                            <input type="file" class="form-control-file border p-2 rounded" id="file_csv" name="file_csv" accept=".csv" required>
                            <small class="form-text text-muted mt-2">
                                * Susunan file CSV harus berurutan:<br>
                                <code>[NO.], NAMA, NIP, UNIT KERJA SAAT INI, JENIS UJIAN, JABATAN SAAT INI, JABATAN DITUJU, KELULUSAN, PERIODE UJIKOM, KETERANGAN, No HP</code>
                            </small>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                        <button type="submit" name="btn_import_csv" class="btn btn-primary"><i class="fas fa-check mr-1"></i> Mulai Proses Import</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php include 'template/footer.php'; ?>

</div>

<script src="plugins/jquery/jquery.min.js"></script>
<script src="plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="plugins/datatables/jquery.dataTables.min.js"></script>
<script src="plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<script src="dist/js/adminlte.min.js"></script>

<script>
$(function () {
    $("#tabelRekapUjikom").DataTable({
        "responsive": true, 
        "lengthChange": true, 
        "autoWidth": false,
        "order": [[0, "asc"]], // Default urutan penomoran terkecil
        "language": {
            "search": "Cari data:",
            "lengthMenu": "Tampilkan _MENU_ data per halaman",
            "zeroRecords": "Data tidak ditemukan",
            "info": "Menampilkan halaman _PAGE_ dari _PAGES_",
            "infoEmpty": "Tidak ada data tersedia",
            "infoFiltered": "(difilter dari _MAX_ total data)",
            "paginate": {
                "first": "Pertama", "last": "Terakhir", "next": "Lanjut", "previous": "Kembali"
            }
        }
    });
});
</script>
</body>
</html>