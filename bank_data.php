<?php
/**
 * FILE: bank_prompt.php
 * DESKRIPSI: Manajemen Prompt AI yang terintegrasi dengan template AdminLTE
 */

// 1. PENGATURAN AWAL & SESI
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. KEAMANAN & KONEKSI
require_once 'auth_guard.php'; 
require_once 'koneksi.php'; // Menggunakan koneksi.php pusat agar konsisten

// Pastikan koneksi menggunakan db_jfpkp2 (biasanya sudah diatur di koneksi.php)
// Jika koneksi.php belum ada, pastikan variabel $conn tersedia.

// 3. LOGIKA PENYIMPANAN DATA
$pesan = "";
if (isset($_POST['simpan'])) {
    $judul = mysqli_real_escape_string($conn, $_POST['judul']);
    $kategori = mysqli_real_escape_string($conn, $_POST['kategori']);
    $instruksi = mysqli_real_escape_string($conn, $_POST['instruksi']);

    $query = "INSERT INTO bank_data (judul, kategori, instruksi) VALUES ('$judul', '$kategori', '$instruksi')";
    
    if (mysqli_query($conn, $query)) {
        $pesan = "<div class='alert alert-success alert-dismissible fade show' role='alert'>
                    <i class='fas fa-check-circle mr-2'></i> <strong>Berhasil!</strong> Prompt baru telah ditambahkan.
                    <button type='button' class='close' data-dismiss='alert' aria-label='Close'>
                        <span aria-hidden='true'>&times;</span>
                    </button>
                  </div>";
    } else {
        $pesan = "<div class='alert alert-danger'>Gagal menyimpan: " . mysqli_error($conn) . "</div>";
    }
}

// 4. AMBIL DATA
$result = mysqli_query($conn, "SELECT * FROM bank_data ORDER BY id DESC");

// 5. DEKLARASI HALAMAN
$page = 'bank_prompt';
$page_title = 'AI Prompt Manager';

// 6. LOAD TEMPLATE HEADER, NAVBAR, SIDEBAR
require_once 'template/header.php'; 
require_once 'template/navbar.php'; 
require_once 'template/sidebar.php'; 
?>

<style>
    .card-prompt { border: none; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
    .table-container { background: white; border-radius: 12px; padding: 20px; }
    pre.code-view { 
        background: #2d2d2d; 
        color: #f8f8f2; 
        padding: 15px; 
        border-radius: 8px; 
        font-size: 0.85rem; 
        max-height: 150px; 
        overflow-y: auto;
        white-space: pre-wrap;
        word-wrap: break-word;
        border-left: 4px solid #007bff;
    }
    .badge-kategori { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 1px; font-weight: bold; }
</style>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 font-weight-bold text-dark"><i class="fas fa-robot mr-2 text-primary"></i> AI Prompt Manager</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="index_pengusul.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Bank Prompt</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            
            <?php echo $pesan; ?>

            <div class="card card-prompt mb-4">
                <div class="card-header bg-white">
                    <h3 class="card-title font-weight-bold text-primary"><i class="fas fa-plus-circle mr-2"></i>Tambah Prompt Baru</h3>
                </div>
                <div class="card-body">
                    <form action="" method="POST">
                        <div class="row">
                            <div class="col-md-7 mb-3">
                                <label class="form-label fw-semibold">Judul/Tujuan Perintah</label>
                                <input type="text" name="judul" class="form-control" placeholder="Contoh: Perbaiki Error Koneksi Database" required>
                            </div>
                            <div class="col-md-5 mb-3">
                                <label class="form-label fw-semibold">Kategori</label>
                                <select name="kategori" class="form-select form-control">
                                    <option value="Refactor">Refactor (Rapikan Kode)</option>
                                    <option value="Bug Fix">Bug Fix (Perbaikan Error)</option>
                                    <option value="Fitur Baru">Fitur Baru</option>
                                    <option value="Optimasi">Optimasi (Kecepatan)</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Detail Prompt / Instruksi Perintah</label>
                            <textarea name="instruksi" class="form-control" rows="4" placeholder="Salin perintah AI Anda di sini..." required></textarea>
                        </div>
                        <button type="submit" name="simpan" class="btn btn-primary px-4 shadow-sm font-weight-bold">
                            <i class="fas fa-save mr-2"></i>Simpan ke Bank Data
                        </button>
                    </form>
                </div>
            </div>

            <div class="card card-indigo card-outline shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title font-weight-bold"><i class="fas fa-list mr-1"></i> Daftar Prompt Tersimpan</h3>
                    <div class="card-tools ml-auto">
                        <span class="badge bg-dark px-3 py-2">Total: <?php echo mysqli_num_rows($result); ?> Prompt</span>
                    </div>
                </div>
                
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="tabelPrompt" class="table table-hover table-striped">
                            <thead class="bg-light">
                                <tr>
                                    <th width="50" class="text-center">No</th>
                                    <th width="250">Informasi Prompt</th>
                                    <th>Instruksi Perintah</th>
                                    <th width="120" class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $no = 1;
                                if(mysqli_num_rows($result) > 0):
                                    while($row = mysqli_fetch_assoc($result)): 
                                ?>
                                <tr>
                                    <td class="text-center align-middle"><?php echo $no++; ?></td>
                                    <td class="align-middle">
                                        <div class="fw-bold text-primary mb-1" style="font-weight: 700;"><?php echo $row['judul']; ?></div>
                                        <span class="badge badge-secondary badge-kategori"><?php echo $row['kategori']; ?></span><br>
                                        <small class="text-muted" style="font-size: 0.75rem;">
                                            <i class="far fa-clock mr-1"></i><?php echo date('d M Y', strtotime($row['tanggal_dibuat'] ?? date('Y-m-d'))); ?>
                                        </small>
                                    </td>
                                    <td class="align-middle">
                                        <pre class="code-view" id="prompt-<?php echo $row['id']; ?>"><?php echo htmlspecialchars($row['instruksi']); ?></pre>
                                    </td>
                                    <td class="text-center align-middle">
                                        <button class="btn btn-sm btn-outline-success shadow-sm" onclick="copyToClipboard('prompt-<?php echo $row['id']; ?>')">
                                            <i class="fas fa-copy mr-1"></i> Salin
                                        </button>
                                    </td>
                                </tr>
                                <?php 
                                    endwhile; 
                                else:
                                ?>
                                <tr>
                                    <td colspan="4" class="text-center py-5 text-muted">Belum ada data prompt tersimpan.</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<?php 
// LOAD TEMPLATE FOOTER
require_once 'template/footer.php'; 
?>

<script>
function copyToClipboard(elementId) {
    const text = document.getElementById(elementId).innerText;
    navigator.clipboard.writeText(text).then(() => {
        // Menggunakan Toast atau Alert bawaan template jika ada
        Swal.fire({
            icon: 'success',
            title: 'Berhasil!',
            text: 'Prompt telah disalin ke clipboard.',
            timer: 1500,
            showConfirmButton: false
        });
    }).catch(err => {
        console.error('Gagal menyalin: ', err);
    });
}

$(function () {
    // Inisialisasi DataTable jika diperlukan
    $("#tabelPrompt").DataTable({
        "responsive": true,
        "autoWidth": false,
        "order": [[0, "asc"]]
    });
});
</script>

<?php 
if (isset($conn)) { $conn->close(); }
ob_end_flush(); 
?>