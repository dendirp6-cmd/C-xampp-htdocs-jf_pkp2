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

// Cek Role (Diperbarui: Menambahkan Kasubdit & Verifikator agar bisa mengakses)
$allowed_roles = ['user_admin', 'user_super_admin', 'user_verifikator', 'user_kasubdit'];
if (!isset($_SESSION['user_role_sesi']) || !in_array($_SESSION['user_role_sesi'], $allowed_roles)) {
    header("Location: index.php?error=unauthorized");
    exit;
}

// Variable role bantu untuk batasan aksi jika diperlukan
$user_role           = $_SESSION['user_role_sesi'];
$can_manage_materi  = in_array($user_role, ['user_admin', 'user_super_admin', 'user_verifikator', 'user_kasubdit']);

// Folder Penyimpanan File Materi
$upload_dir = 'uploads/materi/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// =========================================================================
// LOGIKA PROSES TAMBAH, EDIT, & HAPUS MATERI
// =========================================================================

// A. HAPUS MATERI
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    // Ambil data file sebelum dihapus dari DB
    $get_file = mysqli_query($conn, "SELECT file_materi FROM tb_materi_bimtek WHERE id = $id");
    if ($row = mysqli_fetch_assoc($get_file)) {
        if (!empty($row['file_materi']) && file_exists($upload_dir . $row['file_materi'])) {
            unlink($upload_dir . $row['file_materi']); // Hapus file fisik
        }
    }
    
    mysqli_query($conn, "DELETE FROM tb_materi_bimtek WHERE id = $id");
    echo "<script>
            window.location.href = 'materi_bimtek.php?msg=deleted';
          </script>";
    exit;
}

// B. TAMBAH / EDIT MATERI (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_simpan_materi'])) {
    $id_materi   = isset($_POST['id_materi']) ? intval($_POST['id_materi']) : 0;
    $judul       = mysqli_real_escape_string($conn, trim($_POST['judul']));
    $kategori    = mysqli_real_escape_string($conn, trim($_POST['kategori']));
    $deskripsi   = mysqli_real_escape_string($conn, trim($_POST['deskripsi']));
    $tipe        = mysqli_real_escape_string($conn, trim($_POST['tipe']));
    $link_url    = mysqli_real_escape_string($conn, trim($_POST['link_url']));

    $file_materi = "";

    // Logika Upload File jika Tipe = 'file'
    if ($tipe === 'file' && isset($_FILES['file_materi']) && $_FILES['file_materi']['error'] === UPLOAD_ERR_OK) {
        $file_name = $_FILES['file_materi']['name'];
        $file_tmp  = $_FILES['file_materi']['tmp_name'];
        $ext       = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        $allowed_ext = ['pdf', 'ppt', 'pptx', 'doc', 'docx', 'zip', 'rar', 'mp4'];
        
        if (in_array($ext, $allowed_ext)) {
            $new_file_name = time() . '_' . preg_replace("/[^a-zA-Z0-9\._-]/", "", $file_name);
            $destination   = $upload_dir . $new_file_name;
            
            if (move_uploaded_file($file_tmp, $destination)) {
                $file_materi = $new_file_name;
                
                // Jika edit & ada file lama, hapus file lama
                if ($id_materi > 0) {
                    $old = mysqli_query($conn, "SELECT file_materi FROM tb_materi_bimtek WHERE id = $id_materi");
                    if ($r = mysqli_fetch_assoc($old)) {
                        if (!empty($r['file_materi']) && file_exists($upload_dir . $r['file_materi'])) {
                            unlink($upload_dir . $r['file_materi']);
                        }
                    }
                }
            }
        }
    }

    if ($id_materi > 0) {
        // PROSES EDIT
        if (!empty($file_materi)) {
            $query = "UPDATE tb_materi_bimtek SET 
                        judul = '$judul', 
                        kategori = '$kategori', 
                        deskripsi = '$deskripsi', 
                        tipe = '$tipe', 
                        file_materi = '$file_materi', 
                        link_url = '$link_url',
                        updated_at = NOW() 
                      WHERE id = $id_materi";
        } else {
            $query = "UPDATE tb_materi_bimtek SET 
                        judul = '$judul', 
                        kategori = '$kategori', 
                        deskripsi = '$deskripsi', 
                        tipe = '$tipe', 
                        link_url = '$link_url',
                        updated_at = NOW() 
                      WHERE id = $id_materi";
        }
        mysqli_query($conn, $query);
        $status_msg = "updated";
    } else {
        // PROSES TAMBAH BARU
        $query = "INSERT INTO tb_materi_bimtek (judul, kategori, deskripsi, tipe, file_materi, link_url, created_at) 
                  VALUES ('$judul', '$kategori', '$deskripsi', '$tipe', '$file_materi', '$link_url', NOW())";
        mysqli_query($conn, $query);
        $status_msg = "created";
    }

    echo "<script>
            window.location.href = 'materi_bimtek.php?msg=$status_msg';
          </script>";
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
    $where_clauses[] = "(judul LIKE '%$search_keyword%' OR deskripsi LIKE '%$search_keyword%')";
}

$where_sql = "";
if (count($where_clauses) > 0) {
    $where_sql = "WHERE " . implode(' AND ', $where_clauses);
}

$sql_materi = "SELECT * FROM tb_materi_bimtek $where_sql ORDER BY id DESC";
$query_materi = mysqli_query($conn, $sql_materi);

// Load Header
include 'template/header.php'; 
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">

<style>
    :root {
        --primary-color: #4e73df;
        --secondary-color: #36b9cc;
        --success-color: #1cc88a;
    }

    .header-banner {
        background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
        border-radius: 16px;
        color: white;
        padding: 30px;
        margin-bottom: 30px;
        box-shadow: 0 10px 20px rgba(78, 115, 223, 0.2);
    }

    .card-materi {
        border: none;
        border-radius: 15px;
        transition: all 0.3s ease;
        background: #ffffff;
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    }

    .card-materi:hover {
        transform: translateY(-5px);
        box-shadow: 0 12px 24px rgba(0,0,0,0.1);
    }

    .icon-box {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
    }

    .bg-light-primary { background: rgba(78, 115, 223, 0.1); color: #4e73df; }
    .bg-light-danger { background: rgba(231, 74, 59, 0.1); color: #e74a3b; }
    .bg-light-success { background: rgba(28, 200, 138, 0.1); color: #1cc88a; }
    .bg-light-warning { background: rgba(246, 194, 62, 0.1); color: #f6c23e; }

    .badge-category {
        font-size: 0.75rem;
        padding: 6px 12px;
        border-radius: 30px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
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
            <div class="header-banner d-flex justify-content-between align-items-center flex-wrap gap-3 animate__animated animate__fadeIn">
                <div>
                    <h2 class="fw-bold mb-1"><i class="fas fa-book-reader me-2"></i> Materi & Pembekalan Bimtek</h2>
                    <p class="mb-0 opacity-75">Kelola modul, regulasi, petunjuk teknis, dan video tutorial Uji Kompetensi</p>
                </div>
                <div>
                    <button type="button" class="btn btn-light text-primary fw-bold px-4 py-2 rounded-pill shadow-sm" onclick="tambahMateri()">
                        <i class="fas fa-plus-circle me-2"></i> Tambah Materi Baru
                    </button>
                </div>
            </div>

            <!-- Filter & Search Bar -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-3">
                    <form action="" method="GET" class="row g-2 align-items-center">
                        <div class="col-md-5">
                            <div class="input-group">
                                <span class="input-group-text bg-white border-0 text-muted"><i class="fas fa-search"></i></span>
                                <input type="text" name="q" class="form-control border-0 bg-light rounded-2" placeholder="Cari judul atau isi materi..." value="<?= htmlspecialchars($search_keyword) ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <select name="kat" class="form-select border-0 bg-light rounded-2" onchange="this.form.submit()">
                                <option value="">-- Semua Kategori Materi --</option>
                                <option value="Petunjuk Teknis" <?= $filter_kategori == 'Petunjuk Teknis' ? 'selected' : '' ?>>Petunjuk Teknis (Juknis)</option>
                                <option value="Regulasi & Aturan" <?= $filter_kategori == 'Regulasi & Aturan' ? 'selected' : '' ?>>Regulasi & Aturan</option>
                                <option value="Soal & Latihan" <?= $filter_kategori == 'Soal & Latihan' ? 'selected' : '' ?>>Soal & Latihan Ujikom</option>
                                <option value="Video Pembekalan" <?= $filter_kategori == 'Video Pembekalan' ? 'selected' : '' ?>>Video Pembekalan</option>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex gap-2">
                            <button type="submit" class="btn btn-primary fw-bold w-100 rounded-2"><i class="fas fa-filter me-1"></i> Filter</button>
                            <?php if(!empty($search_keyword) || !empty($filter_kategori)): ?>
                                <a href="materi_bimtek.php" class="btn btn-outline-secondary rounded-2" title="Reset Filter"><i class="fas fa-undo"></i></a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Daftar Card Materi -->
            <div class="row g-4">
                <?php if($query_materi && mysqli_num_rows($query_materi) > 0): ?>
                    <?php while($row = mysqli_fetch_assoc($query_materi)): ?>
                        <?php 
                            // Penentuan ikon & warna berdasarkan kategori/tipe
                            $icon_class = "fa-file-alt";
                            $bg_class   = "bg-light-primary";
                            
                            if ($row['tipe'] == 'link') {
                                $icon_class = "fa-video";
                                $bg_class   = "bg-light-danger";
                            } elseif ($row['kategori'] == 'Regulasi & Aturan') {
                                $icon_class = "fa-balance-scale";
                                $bg_class   = "bg-light-warning";
                            } elseif ($row['kategori'] == 'Soal & Latihan') {
                                $icon_class = "fa-tasks";
                                $bg_class   = "bg-light-success";
                            }
                        ?>
                        <div class="col-xl-4 col-md-6">
                            <div class="card card-materi h-100 p-3">
                                <div class="card-body d-flex flex-column justify-content-between p-2">
                                    <div>
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div class="icon-box <?= $bg_class ?>">
                                                <i class="fas <?= $icon_class ?>"></i>
                                            </div>
                                            <span class="badge bg-light text-dark border badge-category">
                                                <?= htmlspecialchars($row['kategori']) ?>
                                            </span>
                                        </div>

                                        <h5 class="fw-bold text-dark mb-2"><?= htmlspecialchars($row['judul']) ?></h5>
                                        <p class="text-muted small mb-3" style="display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;">
                                            <?= !empty($row['deskripsi']) ? nl2br(htmlspecialchars($row['deskripsi'])) : '<em>Tidak ada deskripsi.</em>' ?>
                                        </p>
                                    </div>

                                    <div>
                                        <div class="d-flex align-items-center justify-content-between text-muted small mb-3 pt-2 border-top">
                                            <span><i class="far fa-clock me-1"></i> <?= date('d M Y', strtotime($row['created_at'])) ?></span>
                                            <span class="fw-bold text-uppercase"><?= $row['tipe'] == 'file' ? 'File Dokumen' : 'External Link' ?></span>
                                        </div>

                                        <div class="d-flex gap-2">
                                            <?php if($row['tipe'] == 'file' && !empty($row['file_materi'])): ?>
                                                <a href="<?= $upload_dir . $row['file_materi'] ?>" target="_blank" class="btn btn-sm btn-primary flex-grow-1 fw-bold rounded-2">
                                                    <i class="fas fa-download me-1"></i> Unduh File
                                                </a>
                                            <?php elseif($row['tipe'] == 'link' && !empty($row['link_url'])): ?>
                                                <a href="<?= htmlspecialchars($row['link_url']) ?>" target="_blank" class="btn btn-sm btn-danger flex-grow-1 fw-bold rounded-2">
                                                    <i class="fas fa-play-circle me-1"></i> Buka Link
                                                </a>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-secondary flex-grow-1 disabled" disabled>Tidak Ada File</button>
                                            <?php endif; ?>

                                            <!-- Opsi Admin / Edit / Hapus -->
                                            <button type="button" class="btn btn-sm btn-outline-warning" onclick='editMateri(<?= json_encode($row) ?>)' title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="hapusMateri(<?= $row['id'] ?>)" title="Hapus">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="card border-0 shadow-sm rounded-4 p-5 text-center">
                            <i class="fas fa-folder-open fa-4x text-muted mb-3 opacity-50"></i>
                            <h5 class="fw-bold text-secondary">Belum ada data materi bimtek.</h5>
                            <p class="text-muted small">Klik tombol "Tambah Materi Baru" untuk mengunggah materi pertama Anda.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </main>
</div>

<!-- MODAL TAMBAH / EDIT MATERI -->
<div class="modal fade" id="modalMateri" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form action="" method="POST" enctype="multipart/form-data">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header bg-primary text-white p-4">
                    <h5 class="modal-title fw-bold" id="modalMateriTitle"><i class="fas fa-plus-circle me-2"></i> Tambah Materi Bimtek</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" name="id_materi" id="id_materi" value="0">
                    
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-bold small text-muted">JUDUL MATERI / MODUL</label>
                            <input type="text" name="judul" id="input_judul" class="form-control form-control-lg bg-light border-0" placeholder="Contoh: Modul Tata Cara Penilaian Ujikom" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted">KATEGORI</label>
                            <select name="kategori" id="input_kategori" class="form-select form-select-lg bg-light border-0" required>
                                <option value="Petunjuk Teknis">Petunjuk Teknis</option>
                                <option value="Regulasi & Aturan">Regulasi & Aturan</option>
                                <option value="Soal & Latihan">Soal & Latihan</option>
                                <option value="Video Pembekalan">Video Pembekalan</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold small text-muted">RINGKASAN / DESKRIPSI</label>
                            <textarea name="deskripsi" id="input_deskripsi" class="form-control bg-light border-0" rows="3" placeholder="Tuliskan catatan atau penjelasan singkat materi..."></textarea>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold small text-muted">TIPE SUMBER MATERI</label>
                            <div class="d-flex gap-4 p-3 bg-light rounded-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="tipe" id="tipe_file" value="file" checked onclick="toggleTipeInput('file')">
                                    <label class="form-check-label fw-bold" for="tipe_file">
                                        <i class="fas fa-file-upload text-primary me-1"></i> Upload File (PDF, PPT, DOC, ZIP)
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="tipe" id="tipe_link" value="link" onclick="toggleTipeInput('link')">
                                    <label class="form-check-label fw-bold" for="tipe_link">
                                        <i class="fas fa-link text-danger me-1"></i> External Link / Youtube
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Input File Upload -->
                        <div class="col-12" id="box_upload_file">
                            <label class="form-label fw-bold small text-muted">FILE DOKUMEN</label>
                            <input type="file" name="file_materi" class="form-control bg-light border-0">
                            <div id="info_file_lama" class="mt-2"></div>
                            <div class="form-text" style="font-size: 0.75rem;">Format diizinkan: PDF, PPT, PPTX, DOC, DOCX, ZIP, RAR, MP4 (Maksimal 20MB).</div>
                        </div>

                        <!-- Input External Link -->
                        <div class="col-12 d-none" id="box_external_link">
                            <label class="form-label fw-bold small text-muted">URL / LINK VIDEO</label>
                            <input type="url" name="link_url" id="input_link_url" class="form-control bg-light border-0" placeholder="https://youtube.com/watch?v=... atau https://drive.google.com/...">
                        </div>
                    </div>

                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="submit" name="btn_simpan_materi" class="btn btn-primary btn-lg fw-bold w-100 rounded-3 shadow">
                        <i class="fas fa-save me-2"></i> Simpan Materi Bimtek
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
function toggleTipeInput(tipe) {
    const boxFile = document.getElementById('box_upload_file');
    const boxLink = document.getElementById('box_external_link');

    if (tipe === 'file') {
        boxFile.classList.remove('d-none');
        boxLink.classList.add('d-none');
    } else {
        boxFile.classList.add('d-none');
        boxLink.classList.remove('d-none');
    }
}

function tambahMateri() {
    document.getElementById('modalMateriTitle').innerHTML = '<i class="fas fa-plus-circle me-2"></i> Tambah Materi Bimtek';
    document.getElementById('id_materi').value = '0';
    document.getElementById('input_judul').value = '';
    document.getElementById('input_kategori').value = 'Petunjuk Teknis';
    document.getElementById('input_deskripsi').value = '';
    document.getElementById('input_link_url').value = '';
    document.getElementById('info_file_lama').innerHTML = '';
    
    document.getElementById('tipe_file').checked = true;
    toggleTipeInput('file');

    new bootstrap.Modal(document.getElementById('modalMateri')).show();
}

function editMateri(data) {
    document.getElementById('modalMateriTitle').innerHTML = '<i class="fas fa-edit me-2"></i> Edit Data Materi Bimtek';
    document.getElementById('id_materi').value = data.id;
    document.getElementById('input_judul').value = data.judul;
    document.getElementById('input_kategori').value = data.kategori;
    document.getElementById('input_deskripsi').value = data.deskripsi;
    document.getElementById('input_link_url').value = data.link_url || '';

    if (data.tipe === 'link') {
        document.getElementById('tipe_link').checked = true;
        toggleTipeInput('link');
    } else {
        document.getElementById('tipe_file').checked = true;
        toggleTipeInput('file');
        if (data.file_materi) {
            document.getElementById('info_file_lama').innerHTML = `<div class="alert alert-info py-2 small mb-0"><i class="fas fa-file-alt me-1"></i> File Terpasang: <strong>${data.file_materi}</strong></div>`;
        } else {
            document.getElementById('info_file_lama').innerHTML = '';
        }
    }

    new bootstrap.Modal(document.getElementById('modalMateri')).show();
}

function hapusMateri(id) {
    Swal.fire({
        title: 'Hapus Materi Ini?',
        text: "Materi yang dihapus tidak dapat dikembalikan!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e74a3b',
        cancelButtonColor: '#858796',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `materi_bimtek.php?action=delete&id=${id}`;
        }
    });
}
</script>

<!-- SweetAlert Notifikasi Respon -->
<?php if (isset($_GET['msg'])): ?>
<script>
    let statusMsg = "<?= $_GET['msg'] ?>";
    if (statusMsg === 'created') {
        Swal.fire('Berhasil!', 'Materi baru berhasil ditambahkan.', 'success');
    } else if (statusMsg === 'updated') {
        Swal.fire('Berhasil!', 'Data materi berhasil diperbarui.', 'success');
    } else if (statusMsg === 'deleted') {
        Swal.fire('Terhapus!', 'Materi telah berhasil dihapus.', 'success');
    }
</script>
<?php endif; ?>

<?php include 'template/footer.php'; ?>