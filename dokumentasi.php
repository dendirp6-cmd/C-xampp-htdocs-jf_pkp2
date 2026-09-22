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

// Cek Role Access
$allowed_roles = ['user_admin', 'user_super_admin', 'user_verifikator', 'user_kasubdit'];
if (!isset($_SESSION['user_role_sesi']) || !in_array($_SESSION['user_role_sesi'], $allowed_roles)) {
    header("Location: index.php?error=unauthorized");
    exit;
}

// Folder Penyimpanan File Foto / Dokumentasi
$upload_dir = 'uploads/dokumentasi/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// =========================================================================
// LOGIKA PROSES TAMBAH, EDIT, & HAPUS DOKUMENTASI
// =========================================================================

// A. HAPUS DOKUMENTASI
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    // Ambil file foto sebelum dihapus dari DB
    $get_file = mysqli_query($conn, "SELECT file_foto FROM tb_dokumentasi WHERE id = $id");
    if ($row = mysqli_fetch_assoc($get_file)) {
        if (!empty($row['file_foto']) && file_exists($upload_dir . $row['file_foto'])) {
            unlink($upload_dir . $row['file_foto']); // Hapus foto fisik
        }
    }
    
    mysqli_query($conn, "DELETE FROM tb_dokumentasi WHERE id = $id");
    echo "<script>
            window.location.href = 'dokumentasi.php?msg=deleted';
          </script>";
    exit;
}

// B. TAMBAH / EDIT DOKUMENTASI (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_simpan_dokumentasi'])) {
    $id_dok          = isset($_POST['id_dok']) ? intval($_POST['id_dok']) : 0;
    $judul           = mysqli_real_escape_string($conn, trim($_POST['judul']));
    $kategori        = mysqli_real_escape_string($conn, trim($_POST['kategori']));
    $tanggal_kegiatan= mysqli_real_escape_string($conn, trim($_POST['tanggal_kegiatan']));
    $lokasi          = mysqli_real_escape_string($conn, trim($_POST['lokasi']));
    $deskripsi       = mysqli_real_escape_string($conn, trim($_POST['deskripsi']));
    $tipe            = mysqli_real_escape_string($conn, trim($_POST['tipe']));
    $link_url        = mysqli_real_escape_string($conn, trim($_POST['link_url']));

    $file_foto = "";

    // Upload Foto jika tipe = 'foto'
    if ($tipe === 'foto' && isset($_FILES['file_foto']) && $_FILES['file_foto']['error'] === UPLOAD_ERR_OK) {
        $file_name = $_FILES['file_foto']['name'];
        $file_tmp  = $_FILES['file_foto']['tmp_name'];
        $ext       = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];
        
        if (in_array($ext, $allowed_ext)) {
            $new_file_name = 'DOK_' . time() . '_' . rand(100, 999) . '.' . $ext;
            $destination   = $upload_dir . $new_file_name;
            
            if (move_uploaded_file($file_tmp, $destination)) {
                $file_foto = $new_file_name;
                
                // Jika edit & ada foto lama, hapus
                if ($id_dok > 0) {
                    $old = mysqli_query($conn, "SELECT file_foto FROM tb_dokumentasi WHERE id = $id_dok");
                    if ($r = mysqli_fetch_assoc($old)) {
                        if (!empty($r['file_foto']) && file_exists($upload_dir . $r['file_foto'])) {
                            unlink($upload_dir . $r['file_foto']);
                        }
                    }
                }
            }
        }
    }

    if ($id_dok > 0) {
        // UPDATE
        if (!empty($file_foto)) {
            $query = "UPDATE tb_dokumentasi SET 
                        judul = '$judul', 
                        kategori = '$kategori', 
                        tanggal_kegiatan = '$tanggal_kegiatan', 
                        lokasi = '$lokasi', 
                        deskripsi = '$deskripsi', 
                        tipe = '$tipe', 
                        file_foto = '$file_foto', 
                        link_url = '$link_url',
                        updated_at = NOW() 
                      WHERE id = $id_dok";
        } else {
            $query = "UPDATE tb_dokumentasi SET 
                        judul = '$judul', 
                        kategori = '$kategori', 
                        tanggal_kegiatan = '$tanggal_kegiatan', 
                        lokasi = '$lokasi', 
                        deskripsi = '$deskripsi', 
                        tipe = '$tipe', 
                        link_url = '$link_url',
                        updated_at = NOW() 
                      WHERE id = $id_dok";
        }
        mysqli_query($conn, $query);
        $status_msg = "updated";
    } else {
        // INSERT
        $query = "INSERT INTO tb_dokumentasi (judul, kategori, tanggal_kegiatan, lokasi, deskripsi, tipe, file_foto, link_url, created_at) 
                  VALUES ('$judul', '$kategori', '$tanggal_kegiatan', '$lokasi', '$deskripsi', '$tipe', '$file_foto', '$link_url', NOW())";
        mysqli_query($conn, $query);
        $status_msg = "created";
    }

    echo "<script>
            window.location.href = 'dokumentasi.php?msg=$status_msg';
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
    $where_clauses[] = "(judul LIKE '%$search_keyword%' OR deskripsi LIKE '%$search_keyword%' OR lokasi LIKE '%$search_keyword%')";
}

$where_sql = "";
if (count($where_clauses) > 0) {
    $where_sql = "WHERE " . implode(' AND ', $where_clauses);
}

$sql_dok = "SELECT * FROM tb_dokumentasi $where_sql ORDER BY tanggal_kegiatan DESC, id DESC";
$query_dok = mysqli_query($conn, $sql_dok);

// Load Header
include 'template/header.php'; 
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">

<style>
    .header-banner-dok {
        background: linear-gradient(135deg, #1cc88a 0%, #13855c 100%);
        border-radius: 16px;
        color: white;
        padding: 30px;
        margin-bottom: 30px;
        box-shadow: 0 10px 20px rgba(28, 200, 138, 0.2);
    }

    .card-dok {
        border: none;
        border-radius: 15px;
        transition: all 0.3s ease;
        background: #ffffff;
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        overflow: hidden;
    }

    .card-dok:hover {
        transform: translateY(-5px);
        box-shadow: 0 12px 24px rgba(0,0,0,0.12);
    }

    .img-preview-container {
        height: 200px;
        background-color: #f1f3f9;
        position: relative;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .img-preview-container img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.5s ease;
    }

    .card-dok:hover .img-preview-container img {
        transform: scale(1.05);
    }

    .badge-category {
        position: absolute;
        top: 12px;
        right: 12px;
        font-size: 0.75rem;
        padding: 6px 12px;
        border-radius: 30px;
        font-weight: 700;
        backdrop-filter: blur(4px);
        background: rgba(0, 0, 0, 0.6);
        color: #ffffff;
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
            <div class="header-banner-dok d-flex justify-content-between align-items-center flex-wrap gap-3 animate__animated animate__fadeIn">
                <div>
                    <h2 class="fw-bold mb-1"><i class="fas fa-camera-retro me-2"></i> Dokumentasi & Galeri Kegiatan</h2>
                    <p class="mb-0 opacity-75">Arsip foto kegiatan, rapat, bimbingan teknis, dan tautan galeri cloud</p>
                </div>
                <div>
                    <button type="button" class="btn btn-light text-success fw-bold px-4 py-2 rounded-pill shadow-sm" onclick="tambahDokumentasi()">
                        <i class="fas fa-plus-circle me-2"></i> Tambah Dokumentasi
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
                                <input type="text" name="q" class="form-control border-0 bg-light rounded-2" placeholder="Cari nama kegiatan, lokasi, deskripsi..." value="<?= htmlspecialchars($search_keyword) ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <select name="kat" class="form-select border-0 bg-light rounded-2" onchange="this.form.submit()">
                                <option value="">-- Semua Kategori Kegiatan --</option>
                                <option value="Bimtek & Pembekalan" <?= $filter_kategori == 'Bimtek & Pembekalan' ? 'selected' : '' ?>>Bimtek & Pembekalan</option>
                                <option value="Uji Kompetensi" <?= $filter_kategori == 'Uji Kompetensi' ? 'selected' : '' ?>>Uji Kompetensi</option>
                                <option value="Rapat & Koordinasi" <?= $filter_kategori == 'Rapat & Koordinasi' ? 'selected' : '' ?>>Rapat & Koordinasi</option>
                                <option value="Monitoring & Evaluasi" <?= $filter_kategori == 'Monitoring & Evaluasi' ? 'selected' : '' ?>>Monitoring & Evaluasi</option>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex gap-2">
                            <button type="submit" class="btn btn-success fw-bold w-100 rounded-2"><i class="fas fa-filter me-1"></i> Filter</button>
                            <?php if(!empty($search_keyword) || !empty($filter_kategori)): ?>
                                <a href="dokumentasi.php" class="btn btn-outline-secondary rounded-2" title="Reset Filter"><i class="fas fa-undo"></i></a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Cards Grid Dokumentasi -->
            <div class="row g-4">
                <?php if($query_dok && mysqli_num_rows($query_dok) > 0): ?>
                    <?php while($row = mysqli_fetch_assoc($query_dok)): ?>
                        <div class="col-xl-4 col-md-6">
                            <div class="card card-dok h-100">
                                
                                <!-- Preview Image / Icon Box -->
                                <div class="img-preview-container">
                                    <span class="badge badge-category">
                                        <i class="fas fa-tag me-1"></i> <?= htmlspecialchars($row['kategori']) ?>
                                    </span>

                                    <?php if($row['tipe'] == 'foto' && !empty($row['file_foto']) && file_exists($upload_dir . $row['file_foto'])): ?>
                                        <img src="<?= $upload_dir . $row['file_foto'] ?>" alt="<?= htmlspecialchars($row['judul']) ?>">
                                    <?php else: ?>
                                        <div class="text-center text-muted p-4">
                                            <i class="fas fa-cloud-download-alt fa-3x mb-2 opacity-50"></i>
                                            <p class="small mb-0">Tautan External Drive/Cloud</p>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="card-body p-3 d-flex flex-column justify-content-between">
                                    <div>
                                        <h5 class="fw-bold text-dark mb-2 text-truncate" title="<?= htmlspecialchars($row['judul']) ?>">
                                            <?= htmlspecialchars($row['judul']) ?>
                                        </h5>

                                        <div class="d-flex flex-wrap text-muted small gap-3 mb-2">
                                            <span><i class="far fa-calendar-alt text-success me-1"></i> <?= date('d M Y', strtotime($row['tanggal_kegiatan'])) ?></span>
                                            <?php if(!empty($row['lokasi'])): ?>
                                                <span><i class="fas fa-map-marker-alt text-danger me-1"></i> <?= htmlspecialchars($row['lokasi']) ?></span>
                                            <?php endif; ?>
                                        </div>

                                        <p class="text-muted small mb-3" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                            <?= !empty($row['deskripsi']) ? nl2br(htmlspecialchars($row['deskripsi'])) : '<em>Tidak ada keterangan tambahan.</em>' ?>
                                        </p>
                                    </div>

                                    <div>
                                        <div class="d-flex gap-2 pt-2 border-top">
                                            <?php if($row['tipe'] == 'foto' && !empty($row['file_foto'])): ?>
                                                <a href="<?= $upload_dir . $row['file_foto'] ?>" target="_blank" class="btn btn-sm btn-outline-success flex-grow-1 fw-bold rounded-2">
                                                    <i class="fas fa-expand me-1"></i> Lihat Foto
                                                </a>
                                            <?php elseif($row['tipe'] == 'link' && !empty($row['link_url'])): ?>
                                                <a href="<?= htmlspecialchars($row['link_url']) ?>" target="_blank" class="btn btn-sm btn-outline-primary flex-grow-1 fw-bold rounded-2">
                                                    <i class="fas fa-external-link-alt me-1"></i> Buka Link Drive
                                                </a>
                                            <?php endif; ?>

                                            <button type="button" class="btn btn-sm btn-outline-warning" onclick='editDokumentasi(<?= json_encode($row) ?>)' title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="hapusDokumentasi(<?= $row['id'] ?>)" title="Hapus">
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
                            <i class="fas fa-images fa-4x text-muted mb-3 opacity-50"></i>
                            <h5 class="fw-bold text-secondary">Belum Ada Dokumentasi Kegiatan</h5>
                            <p class="text-muted small">Klik tombol "Tambah Dokumentasi" di atas untuk menambahkan foto/kegiatan baru.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </main>
</div>

<!-- MODAL TAMBAH / EDIT DOKUMENTASI -->
<div class="modal fade" id="modalDokumentasi" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form action="" method="POST" enctype="multipart/form-data">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header bg-success text-white p-4">
                    <h5 class="modal-title fw-bold" id="modalDokumentasiTitle"><i class="fas fa-plus-circle me-2"></i> Tambah Dokumentasi Kegiatan</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" name="id_dok" id="id_dok" value="0">
                    
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-bold small text-muted">NAMA / JUDUL KEGIATAN</label>
                            <input type="text" name="judul" id="input_judul" class="form-control form-control-lg bg-light border-0" placeholder="Contoh: Pembekalan Peserta Ujikom Angkatan I" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted">KATEGORI</label>
                            <select name="kategori" id="input_kategori" class="form-select form-select-lg bg-light border-0" required>
                                <option value="Bimtek & Pembekalan">Bimtek & Pembekalan</option>
                                <option value="Uji Kompetensi">Uji Kompetensi</option>
                                <option value="Rapat & Koordinasi">Rapat & Koordinasi</option>
                                <option value="Monitoring & Evaluasi">Monitoring & Evaluasi</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">TANGGAL KEGIATAN</label>
                            <input type="date" name="tanggal_kegiatan" id="input_tanggal_kegiatan" class="form-control bg-light border-0" value="<?= date('Y-m-d') ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">LOKASI KEGIATAN</label>
                            <input type="text" name="lokasi" id="input_lokasi" class="form-control bg-light border-0" placeholder="Contoh: Hotel Grand Ballroom / Zoom Meeting">
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold small text-muted">KETERANGAN / DESKRIPSI</label>
                            <textarea name="deskripsi" id="input_deskripsi" class="form-control bg-light border-0" rows="3" placeholder="Catatan atau rincian agenda kegiatan..."></textarea>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold small text-muted">TIPE MEDIA DOKUMENTASI</label>
                            <div class="d-flex gap-4 p-3 bg-light rounded-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="tipe" id="tipe_foto" value="foto" checked onclick="toggleTipeDok('foto')">
                                    <label class="form-check-label fw-bold" for="tipe_foto">
                                        <i class="fas fa-image text-success me-1"></i> Upload File Foto
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="tipe" id="tipe_link" value="link" onclick="toggleTipeDok('link')">
                                    <label class="form-check-label fw-bold" for="tipe_link">
                                        <i class="fab fa-google-drive text-primary me-1"></i> Link Google Drive / Cloud Galeri
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Upload File Foto -->
                        <div class="col-12" id="box_upload_foto">
                            <label class="form-label fw-bold small text-muted">FILE FOTO</label>
                            <input type="file" name="file_foto" class="form-control bg-light border-0" accept="image/*">
                            <div id="info_foto_lama" class="mt-2"></div>
                            <div class="form-text" style="font-size: 0.75rem;">Format diizinkan: JPG, JPEG, PNG, WEBP (Maksimal 10MB).</div>
                        </div>

                        <!-- External Link Drive -->
                        <div class="col-12 d-none" id="box_link_drive">
                            <label class="form-label fw-bold small text-muted">URL / LINK GALERI CLOUD</label>
                            <input type="url" name="link_url" id="input_link_url" class="form-control bg-light border-0" placeholder="https://drive.google.com/drive/folders/...">
                        </div>
                    </div>

                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="submit" name="btn_simpan_dokumentasi" class="btn btn-success btn-lg fw-bold w-100 rounded-3 shadow">
                        <i class="fas fa-save me-2"></i> Simpan Dokumentasi
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
function toggleTipeDok(tipe) {
    const boxFoto = document.getElementById('box_upload_foto');
    const boxLink = document.getElementById('box_link_drive');

    if (tipe === 'foto') {
        boxFoto.classList.remove('d-none');
        boxLink.classList.add('d-none');
    } else {
        boxFoto.classList.add('d-none');
        boxLink.classList.remove('d-none');
    }
}

function tambahDokumentasi() {
    document.getElementById('modalDokumentasiTitle').innerHTML = '<i class="fas fa-plus-circle me-2"></i> Tambah Dokumentasi Kegiatan';
    document.getElementById('id_dok').value = '0';
    document.getElementById('input_judul').value = '';
    document.getElementById('input_kategori').value = 'Bimtek & Pembekalan';
    document.getElementById('input_tanggal_kegiatan').value = "<?= date('Y-m-d') ?>";
    document.getElementById('input_lokasi').value = '';
    document.getElementById('input_deskripsi').value = '';
    document.getElementById('input_link_url').value = '';
    document.getElementById('info_foto_lama').innerHTML = '';
    
    document.getElementById('tipe_foto').checked = true;
    toggleTipeDok('foto');

    new bootstrap.Modal(document.getElementById('modalDokumentasi')).show();
}

function editDokumentasi(data) {
    document.getElementById('modalDokumentasiTitle').innerHTML = '<i class="fas fa-edit me-2"></i> Edit Dokumentasi Kegiatan';
    document.getElementById('id_dok').value = data.id;
    document.getElementById('input_judul').value = data.judul;
    document.getElementById('input_kategori').value = data.kategori;
    document.getElementById('input_tanggal_kegiatan').value = data.tanggal_kegiatan;
    document.getElementById('input_lokasi').value = data.lokasi || '';
    document.getElementById('input_deskripsi').value = data.deskripsi || '';
    document.getElementById('input_link_url').value = data.link_url || '';

    if (data.tipe === 'link') {
        document.getElementById('tipe_link').checked = true;
        toggleTipeDok('link');
    } else {
        document.getElementById('tipe_foto').checked = true;
        toggleTipeDok('foto');
        if (data.file_foto) {
            document.getElementById('info_foto_lama').innerHTML = `<div class="alert alert-info py-2 small mb-0"><i class="fas fa-image me-1"></i> Foto Terpasang: <strong>${data.file_foto}</strong></div>`;
        } else {
            document.getElementById('info_foto_lama').innerHTML = '';
        }
    }

    new bootstrap.Modal(document.getElementById('modalDokumentasi')).show();
}

function hapusDokumentasi(id) {
    Swal.fire({
        title: 'Hapus Dokumentasi Ini?',
        text: "Foto/Data dokumentasi akan dihapus secara permanen!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e74a3b',
        cancelButtonColor: '#858796',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `dokumentasi.php?action=delete&id=${id}`;
        }
    });
}
</script>

<!-- SweetAlert Notifikasi Respon -->
<?php if (isset($_GET['msg'])): ?>
<script>
    let statusMsg = "<?= $_GET['msg'] ?>";
    if (statusMsg === 'created') {
        Swal.fire('Berhasil!', 'Dokumentasi kegiatan berhasil ditambahkan.', 'success');
    } else if (statusMsg === 'updated') {
        Swal.fire('Berhasil!', 'Dokumentasi berhasil diperbarui.', 'success');
    } else if (statusMsg === 'deleted') {
        Swal.fire('Terhapus!', 'Dokumentasi telah berhasil dihapus.', 'success');
    }
</script>
<?php endif; ?>

<?php include 'template/footer.php'; ?>