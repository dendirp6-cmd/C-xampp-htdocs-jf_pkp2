<?php
// 1. Inisialisasi Session & Proteksi Halaman
session_start();

// Koneksi Database
require_once 'koneksi.php'; 

// Cek Login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Cek Role Access (Kasubdit, Verifikator, Admin, Super Admin)
$allowed_roles = ['user_admin', 'user_super_admin', 'user_verifikator', 'user_kasubdit'];
if (!isset($_SESSION['user_role_sesi']) || !in_array($_SESSION['user_role_sesi'], $allowed_roles)) {
    header("Location: index.php?error=unauthorized");
    exit;
}

// Folder Penyimpanan File LPJ
$upload_dir = 'uploads/lpj_bimtek/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// =========================================================================
// LOGIKA PROSES TAMBAH, EDIT, & HAPUS LPJ
// =========================================================================

// A. HAPUS DOKUMEN LPJ
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    // Ambil nama file sebelum menghapus baris di DB
    $get_file = mysqli_query($conn, "SELECT file_dokumen FROM tb_lpj_bimtek WHERE id = $id");
    if ($row = mysqli_fetch_assoc($get_file)) {
        if (!empty($row['file_dokumen']) && file_exists($upload_dir . $row['file_dokumen'])) {
            unlink($upload_dir . $row['file_dokumen']); // Hapus file dari folder
        }
    }
    
    mysqli_query($conn, "DELETE FROM tb_lpj_bimtek WHERE id = $id");
    echo "<script>window.location.href = 'bimtek_lpj.php?msg=deleted';</script>";
    exit;
}

// B. TAMBAH / EDIT DOKUMEN LPJ (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_simpan_lpj'])) {
    $id_lpj          = isset($_POST['id_lpj']) ? intval($_POST['id_lpj']) : 0;
    $judul_lpj       = mysqli_real_escape_string($conn, trim($_POST['judul_lpj']));
    $nomor_dokumen   = mysqli_real_escape_string($conn, trim($_POST['nomor_dokumen']));
    $kategori        = mysqli_real_escape_string($conn, trim($_POST['kategori']));
    $tanggal_dokumen = mysqli_real_escape_string($conn, trim($_POST['tanggal_dokumen']));
    $nominal         = floatval($_POST['nominal']);
    $keterangan      = mysqli_real_escape_string($conn, trim($_POST['keterangan']));

    $file_dokumen = "";

    // Upload File LPJ
    if (isset($_FILES['file_dokumen']) && $_FILES['file_dokumen']['error'] === UPLOAD_ERR_OK) {
        $file_name = $_FILES['file_dokumen']['name'];
        $file_tmp  = $_FILES['file_dokumen']['tmp_name'];
        $ext       = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        $allowed_ext = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'rar', 'jpg', 'jpeg', 'png'];
        
        if (in_array($ext, $allowed_ext)) {
            $new_file_name = 'LPJ_' . time() . '_' . rand(100, 999) . '.' . $ext;
            $destination   = $upload_dir . $new_file_name;
            
            if (move_uploaded_file($file_tmp, $destination)) {
                $file_dokumen = $new_file_name;
                
                // Hapus file lama jika sedang dalam mode edit
                if ($id_lpj > 0) {
                    $old = mysqli_query($conn, "SELECT file_dokumen FROM tb_lpj_bimtek WHERE id = $id_lpj");
                    if ($r = mysqli_fetch_assoc($old)) {
                        if (!empty($r['file_dokumen']) && file_exists($upload_dir . $r['file_dokumen'])) {
                            unlink($upload_dir . $r['file_dokumen']);
                        }
                    }
                }
            }
        }
    }

    if ($id_lpj > 0) {
        // UPDATE
        if (!empty($file_dokumen)) {
            $query = "UPDATE tb_lpj_bimtek SET 
                        judul_lpj = '$judul_lpj', 
                        nomor_dokumen = '$nomor_dokumen', 
                        kategori = '$kategori', 
                        tanggal_dokumen = '$tanggal_dokumen', 
                        nominal = $nominal, 
                        keterangan = '$keterangan', 
                        file_dokumen = '$file_dokumen', 
                        updated_at = NOW() 
                      WHERE id = $id_lpj";
        } else {
            $query = "UPDATE tb_lpj_bimtek SET 
                        judul_lpj = '$judul_lpj', 
                        nomor_dokumen = '$nomor_dokumen', 
                        kategori = '$kategori', 
                        tanggal_dokumen = '$tanggal_dokumen', 
                        nominal = $nominal, 
                        keterangan = '$keterangan', 
                        updated_at = NOW() 
                      WHERE id = $id_lpj";
        }
        mysqli_query($conn, $query);
        $status_msg = "updated";
    } else {
        // INSERT
        $query = "INSERT INTO tb_lpj_bimtek (judul_lpj, nomor_dokumen, kategori, tanggal_dokumen, nominal, keterangan, file_dokumen, created_at) 
                  VALUES ('$judul_lpj', '$nomor_dokumen', '$kategori', '$tanggal_dokumen', $nominal, '$keterangan', '$file_dokumen', NOW())";
        mysqli_query($conn, $query);
        $status_msg = "created";
    }

    echo "<script>window.location.href = 'bimtek_lpj.php?msg=$status_msg';</script>";
    exit;
}

// =========================================================================
// QUERY DATA & FILTER
// =========================================================================
$filter_kategori = isset($_GET['kat']) ? mysqli_real_escape_string($conn, $_GET['kat']) : '';
$search_keyword  = isset($_GET['q']) ? mysqli_real_escape_string($conn, $_GET['q']) : '';

$where_clauses = [];
if (!empty($filter_kategori)) {
    $where_clauses[] = "kategori = '$filter_kategori'";
}
if (!empty($search_keyword)) {
    $where_clauses[] = "(judul_lpj LIKE '%$search_keyword%' OR nomor_dokumen LIKE '%$search_keyword%' OR keterangan LIKE '%$search_keyword%')";
}

$where_sql = "";
if (count($where_clauses) > 0) {
    $where_sql = "WHERE " . implode(' AND ', $where_clauses);
}

$sql_lpj   = "SELECT * FROM tb_lpj_bimtek $where_sql ORDER BY tanggal_dokumen DESC, id DESC";
$query_lpj = mysqli_query($conn, $sql_lpj);

// Hitung Ringkasan Nominal & Total Dokumen
$sql_summary   = "SELECT COUNT(*) as total_dok, SUM(nominal) as total_nominal FROM tb_lpj_bimtek $where_sql";
$res_summary   = mysqli_fetch_assoc(mysqli_query($conn, $sql_summary));
$total_dok     = $res_summary['total_dok'] ?? 0;
$total_nominal = $res_summary['total_nominal'] ?? 0;

// Load Header
include 'template/header.php'; 
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">

<style>
    .header-banner-lpj {
        background: linear-gradient(135deg, #0f766e 0%, #115e59 100%);
        border-radius: 16px;
        color: white;
        padding: 30px;
        margin-bottom: 25px;
        box-shadow: 0 10px 20px rgba(15, 118, 110, 0.2);
    }

    .card-stat {
        border: none;
        border-radius: 12px;
        background: #ffffff;
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        transition: transform 0.2s ease;
    }

    .card-stat:hover {
        transform: translateY(-3px);
    }

    .table-lpj th {
        background-color: #f1f5f9;
        color: #334155;
        font-weight: 700;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 2px solid #e2e8f0;
    }

    .table-lpj td {
        vertical-align: middle;
        font-size: 0.9rem;
    }

    .badge-kategori {
        font-size: 0.75rem;
        padding: 5px 10px;
        border-radius: 20px;
        font-weight: 600;
    }
</style>

<div class="d-flex" id="wrapper" style="min-height: 100vh;">
    <!-- Sidebar -->
    <aside id="sidebar-container" style="width: 250px; flex-shrink: 0; background-color: #2c3e50;">
        <?php include 'template/sidebar.php'; ?>
    </aside>

    <!-- Main Content -->
    <main id="page-content-wrapper" class="flex-grow-1" style="background-color: #f8f9fc; min-width: 0;">
        <div class="container-fluid p-4">
            
            <!-- Banner Header -->
            <div class="header-banner-lpj d-flex justify-content-between align-items-center flex-wrap gap-3 animate__animated animate__fadeIn">
                <div>
                    <h2 class="fw-bold mb-1"><i class="fas fa-file-invoice-dollar me-2"></i> Laporan Pertanggungjawaban (LPJ) Bimtek</h2>
                    <p class="mb-0 opacity-75">Kelola berkas pertanggungjawaban keuangan, kwitansi, SPPD, dan laporan pertanggungjawaban kegiatan</p>
                </div>
                <div>
                    <button type="button" class="btn btn-light text-success fw-bold px-4 py-2 rounded-pill shadow-sm" onclick="tambahLPJ()">
                        <i class="fas fa-plus-circle me-2"></i> Upload Berkas LPJ
                    </button>
                </div>
            </div>

            <!-- Ringkasan Statistik -->
            <div class="row g-3 mb-4">
                <div class="col-md-6 col-xl-4">
                    <div class="card card-stat p-3 d-flex flex-row align-items-center justify-content-between">
                        <div>
                            <span class="text-muted small fw-bold">TOTAL DOKUMEN LPJ</span>
                            <h3 class="fw-bold text-dark mb-0 mt-1"><?= number_format($total_dok, 0, ',', '.') ?> <span class="fs-6 text-muted fw-normal">Berkas</span></h3>
                        </div>
                        <div class="rounded-circle bg-teal-subtle p-3 text-teal">
                            <i class="fas fa-folder-closed fa-2x text-success"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-4">
                    <div class="card card-stat p-3 d-flex flex-row align-items-center justify-content-between">
                        <div>
                            <span class="text-muted small fw-bold">TOTAL ANGGARAN TERLAPORKAN</span>
                            <h3 class="fw-bold text-success mb-0 mt-1">Rp <?= number_format($total_nominal, 0, ',', '.') ?></h3>
                        </div>
                        <div class="rounded-circle bg-success-subtle p-3 text-success">
                            <i class="fas fa-coins fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter & Search Bar -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-3">
                    <form action="" method="GET" class="row g-2 align-items-center">
                        <div class="col-md-5">
                            <div class="input-group">
                                <span class="input-group-text bg-white border-0 text-muted"><i class="fas fa-search"></i></span>
                                <input type="text" name="q" class="form-control border-0 bg-light rounded-2" placeholder="Cari judul LPJ, nomor dokumen, keterangan..." value="<?= htmlspecialchars($search_keyword) ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <select name="kat" class="form-select border-0 bg-light rounded-2" onchange="this.form.submit()">
                                <option value="">-- Semua Kategori LPJ --</option>
                                <option value="Laporan Kegiatan" <?= $filter_kategori == 'Laporan Kegiatan' ? 'selected' : '' ?>>Laporan Kegiatan</option>
                                <option value="Kwitansi & Nota" <?= $filter_kategori == 'Kwitansi & Nota' ? 'selected' : '' ?>>Kwitansi & Nota</option>
                                <option value="SPPD & Perjalanan Dinas" <?= $filter_kategori == 'SPPD & Perjalanan Dinas' ? 'selected' : '' ?>>SPPD & Perjalanan Dinas</option>
                                <option value="BAST & Honorarium" <?= $filter_kategori == 'BAST & Honorarium' ? 'selected' : '' ?>>BAST & Honorarium</option>
                                <option value="Dokumen Pendukung" <?= $filter_kategori == 'Dokumen Pendukung' ? 'selected' : '' ?>>Dokumen Pendukung</option>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex gap-2">
                            <button type="submit" class="btn btn-success fw-bold w-100 rounded-2"><i class="fas fa-filter me-1"></i> Filter</button>
                            <?php if(!empty($search_keyword) || !empty($filter_kategori)): ?>
                                <a href="bimtek_lpj.php" class="btn btn-outline-secondary rounded-2" title="Reset Filter"><i class="fas fa-undo"></i></a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Data Table LPJ -->
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-lpj mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-4" style="width: 5%;">No</th>
                                    <th style="width: 30%;">Dokumen LPJ / Nomor</th>
                                    <th style="width: 18%;">Kategori</th>
                                    <th style="width: 12%;">Tanggal</th>
                                    <th style="width: 15%;">Nominal (Rp)</th>
                                    <th style="width: 20%;" class="text-center pe-4">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if($query_lpj && mysqli_num_rows($query_lpj) > 0): ?>
                                    <?php $no = 1; while($row = mysqli_fetch_assoc($query_lpj)): ?>
                                        <tr>
                                            <td class="ps-4 fw-bold text-muted"><?= $no++ ?></td>
                                            <td>
                                                <div class="fw-bold text-dark"><?= htmlspecialchars($row['judul_lpj']) ?></div>
                                                <div class="small text-muted"><i class="fas fa-hashtag me-1"></i><?= !empty($row['nomor_dokumen']) ? htmlspecialchars($row['nomor_dokumen']) : '-' ?></div>
                                                <?php if(!empty($row['keterangan'])): ?>
                                                    <div class="small text-secondary italic mt-1" style="font-size:0.8rem;"><?= htmlspecialchars($row['keterangan']) ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge badge-kategori bg-teal-subtle text-success border border-success-subtle">
                                                    <?= htmlspecialchars($row['kategori']) ?>
                                                </span>
                                            </td>
                                            <td class="text-muted small">
                                                <i class="far fa-calendar-alt me-1"></i><?= date('d/m/Y', strtotime($row['tanggal_dokumen'])) ?>
                                            </td>
                                            <td class="fw-bold text-dark">
                                                Rp <?= number_format($row['nominal'], 0, ',', '.') ?>
                                            </td>
                                            <td class="text-center pe-4">
                                                <div class="d-flex justify-content-center gap-2">
                                                    <?php if(!empty($row['file_dokumen']) && file_exists($upload_dir . $row['file_dokumen'])): ?>
                                                        <a href="<?= $upload_dir . $row['file_dokumen'] ?>" target="_blank" class="btn btn-sm btn-outline-success fw-bold rounded-2" title="Unduh Berkas">
                                                            <i class="fas fa-download me-1"></i> Download
                                                        </a>
                                                    <?php endif; ?>

                                                    <button type="button" class="btn btn-sm btn-outline-warning rounded-2" onclick='editLPJ(<?= json_encode($row) ?>)' title="Edit Data">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    
                                                    <button type="button" class="btn btn-sm btn-outline-danger rounded-2" onclick="hapusLPJ(<?= $row['id'] ?>)" title="Hapus Data">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center p-5">
                                            <i class="fas fa-folder-open fa-3x text-muted mb-3 opacity-50"></i>
                                            <h5 class="fw-bold text-secondary">Belum Ada Dokumen LPJ</h5>
                                            <p class="text-muted small">Silakan klik "Upload Berkas LPJ" di atas untuk menambahkan dokumen baru.</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </main>
</div>

<!-- MODAL TAMBAH / EDIT LPJ -->
<div class="modal fade" id="modalLPJ" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form action="" method="POST" enctype="multipart/form-data">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header bg-success text-white p-4">
                    <h5 class="modal-title fw-bold" id="modalLPJTitle"><i class="fas fa-plus-circle me-2"></i> Upload Berkas LPJ Bimtek</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" name="id_lpj" id="id_lpj" value="0">
                    
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-bold small text-muted">JUDUL DOKUMEN / KETERANGAN LPJ</label>
                            <input type="text" name="judul_lpj" id="input_judul_lpj" class="form-control form-control-lg bg-light border-0" placeholder="Contoh: LPJ Pelaksanaan Bimtek JF Angkatan II" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted">KATEGORI</label>
                            <select name="kategori" id="input_kategori" class="form-select form-select-lg bg-light border-0" required>
                                <option value="Laporan Kegiatan">Laporan Kegiatan</option>
                                <option value="Kwitansi & Nota">Kwitansi & Nota</option>
                                <option value="SPPD & Perjalanan Dinas">SPPD & Perjalanan Dinas</option>
                                <option value="BAST & Honorarium">BAST & Honorarium</option>
                                <option value="Dokumen Pendukung">Dokumen Pendukung</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">NOMOR DOKUMEN / SURAT</label>
                            <input type="text" name="nomor_dokumen" id="input_nomor_dokumen" class="form-control bg-light border-0" placeholder="Contoh: 005/LPJ-BIMTEK/2026">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">TANGGAL DOKUMEN</label>
                            <input type="date" name="tanggal_dokumen" id="input_tanggal_dokumen" class="form-control bg-light border-0" value="<?= date('Y-m-d') ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">NOMINAL ANGGARAN (RP)</label>
                            <input type="number" step="0.01" name="nominal" id="input_nominal" class="form-control bg-light border-0" placeholder="0" value="0">
                            <div class="form-text" style="font-size: 0.75rem;">Isi dengan angka tanpa titik/koma (misal: 15000000).</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">FILE BERKAS DOKUMEN</label>
                            <input type="file" name="file_dokumen" id="input_file_dokumen" class="form-control bg-light border-0">
                            <div id="info_file_lama" class="mt-2"></div>
                            <div class="form-text" style="font-size: 0.75rem;">PDF, DOCX, XLSX, ZIP, atau Gambar (Maks 15MB).</div>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold small text-muted">CATATAN / CATATAN TAMBAHAN</label>
                            <textarea name="keterangan" id="input_keterangan" class="form-control bg-light border-0" rows="3" placeholder="Rincian tambahan atau pertimbangan pertanggungjawaban..."></textarea>
                        </div>
                    </div>

                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="submit" name="btn_simpan_lpj" class="btn btn-success btn-lg fw-bold w-100 rounded-3 shadow">
                        <i class="fas fa-save me-2"></i> Simpan Data LPJ
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
function tambahLPJ() {
    document.getElementById('modalLPJTitle').innerHTML = '<i class="fas fa-plus-circle me-2"></i> Upload Berkas LPJ Bimtek';
    document.getElementById('id_lpj').value = '0';
    document.getElementById('input_judul_lpj').value = '';
    document.getElementById('input_nomor_dokumen').value = '';
    document.getElementById('input_kategori').value = 'Laporan Kegiatan';
    document.getElementById('input_tanggal_dokumen').value = "<?= date('Y-m-d') ?>";
    document.getElementById('input_nominal').value = '0';
    document.getElementById('input_keterangan').value = '';
    document.getElementById('input_file_dokumen').required = true;
    document.getElementById('info_file_lama').innerHTML = '';

    new bootstrap.Modal(document.getElementById('modalLPJ')).show();
}

function editLPJ(data) {
    document.getElementById('modalLPJTitle').innerHTML = '<i class="fas fa-edit me-2"></i> Edit Data LPJ Bimtek';
    document.getElementById('id_lpj').value = data.id;
    document.getElementById('input_judul_lpj').value = data.judul_lpj;
    document.getElementById('input_nomor_dokumen').value = data.nomor_dokumen || '';
    document.getElementById('input_kategori').value = data.kategori;
    document.getElementById('input_tanggal_dokumen').value = data.tanggal_dokumen;
    document.getElementById('input_nominal').value = data.nominal || '0';
    document.getElementById('input_keterangan').value = data.keterangan || '';
    document.getElementById('input_file_dokumen').required = false;

    if (data.file_dokumen) {
        document.getElementById('info_file_lama').innerHTML = `<div class="alert alert-info py-2 small mb-0"><i class="fas fa-file-alt me-1"></i> Berkas Terpasang: <strong>${data.file_dokumen}</strong></div>`;
    } else {
        document.getElementById('info_file_lama').innerHTML = '';
    }

    new bootstrap.Modal(document.getElementById('modalLPJ')).show();
}

function hapusLPJ(id) {
    Swal.fire({
        title: 'Hapus Dokumen LPJ Ini?',
        text: "Berkas dan data pertanggungjawaban akan dihapus secara permanen!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e74a3b',
        cancelButtonColor: '#858796',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `bimtek_lpj.php?action=delete&id=${id}`;
        }
    });
}
</script>

<!-- SweetAlert Notifikasi Respon -->
<?php if (isset($_GET['msg'])): ?>
<script>
    let statusMsg = "<?= $_GET['msg'] ?>";
    if (statusMsg === 'created') {
        Swal.fire('Berhasil!', 'Dokumen LPJ telah berhasil diunggah.', 'success');
    } else if (statusMsg === 'updated') {
        Swal.fire('Berhasil!', 'Data LPJ berhasil diperbarui.', 'success');
    } else if (statusMsg === 'deleted') {
        Swal.fire('Terhapus!', 'Dokumen LPJ telah berhasil dihapus.', 'success');
    }
</script>
<?php endif; ?>

<?php include 'template/footer.php'; ?>