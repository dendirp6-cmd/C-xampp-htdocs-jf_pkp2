<?php
// FILE: edit_isian_rekom.php - Halaman Khusus untuk Mengunggah Berkas Revisi Tunggal

ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'auth_guard.php';
require_once 'koneksi.php';

// Fungsi sederhana untuk membersihkan nama file (dipertahankan)
function sanitize_filename($filename) {
    $filename = preg_replace('/[^a-z0-9\-\.]/i', '_', $filename);
    return strtolower($filename);
}

$page = 'rekomendasi';
$sub_page = 'list_rekom';
$error_message = null;
$success_message = null;
$rekom_data = null;
$max_file_size_mb = 5; // Batasan ukuran file dalam MB
$max_file_size_bytes = $max_file_size_mb * 1024 * 1024;

// --- 1. Ambil dan Validasi Input dari URL ---
$rekom_id = $_GET['id'] ?? null;
$file_key = $_GET['file'] ?? null;
$safe_rekom_id = $rekom_id ? intval($rekom_id) : null;

// Mapping label dokumen ke file_key
$file_map_label = [
    'file_usulan_formasi' => 'Dokumen Usulan Formasi JF',
    'file_tupoksi' => 'Tupoksi Unit Kerja',
    'file_abk' => 'Hasil Analisis Beban Kerja (ABK)',
    'file_struktur' => 'Struktur Organisasi Unit Kerja',
    'file_peta_jabatan' => 'Peta Jabatan Unit Kerja',
    'file_sk_kelola' => 'SK Kelas Jabatan',
    'file_anggaran_daerah' => 'File Anggaran Daerah',
    'file_bukti_jafung' => 'Bukti Dukung Jabatan Fungsional',
];

$file_label = $file_map_label[$file_key] ?? null;
$page_title = $file_label ? "Revisi Berkas: " . $file_label : "Revisi Berkas Dokumen";

// Tentukan kolom status revisi spesifik yang akan diupdate
// Contoh: file_tupoksi -> revisi_tupoksi
$status_key_part = str_replace('file_', '', $file_key);
$status_key = 'revisi_' . $status_key_part;
$is_valid_file_key = in_array($file_key, array_keys($file_map_label));


// Validasi dasar URL
if (!$safe_rekom_id || !$file_key || !$file_label || !$is_valid_file_key) {
    $error_message = "Parameter ID Rekomendasi atau Berkas tidak valid.";
    goto render_page; // Lompat ke bagian rendering halaman
}

// --- 2. Ambil Data Rekomendasi & Cek Status Revisi ---
$sql_get = "SELECT * FROM rekomendasi_formasi WHERE id = ?";
if ($stmt_get = mysqli_prepare($conn, $sql_get)) {
    mysqli_stmt_bind_param($stmt_get, "i", $safe_rekom_id);
    mysqli_stmt_execute($stmt_get);
    $result_get = mysqli_stmt_get_result($stmt_get);
    if (mysqli_num_rows($result_get) > 0) {
        $rekom_data = mysqli_fetch_assoc($result_get);
        $status_rekom = $rekom_data['status'] ?? 'Draft';
        
        // Cek apakah statusnya benar-benar 'Perlu Revisi'
        if ($status_rekom !== 'Perlu Revisi') {
            $error_message = "Pengajuan ini berstatus **{$status_rekom}**. Pengunggahan berkas revisi hanya diizinkan saat status 'Perlu Revisi'.";
        }
    } else {
        $error_message = "Data rekomendasi tidak ditemukan.";
    }
    mysqli_stmt_close($stmt_get);
} else {
    $error_message = "Gagal menyiapkan query data: " . mysqli_error($conn);
}


// --- 3. Proses Unggah Berkas (Jika Form Disubmit) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file_revisi']) && !$error_message) {
    
    $file_upload = $_FILES['file_revisi'];

    // --- Validasi Berkas ---

    // A. Cek Error Upload PHP
    if ($file_upload['error'] !== UPLOAD_ERR_OK) {
        $upload_errors = [
            UPLOAD_ERR_INI_SIZE   => 'Ukuran berkas melebihi batas maksimum di server (php.ini).',
            UPLOAD_ERR_FORM_SIZE  => 'Ukuran berkas melebihi batas maksimum di form HTML.',
            UPLOAD_ERR_PARTIAL    => 'Berkas hanya terunggah sebagian.',
            UPLOAD_ERR_NO_FILE    => 'Tidak ada berkas yang dipilih.',
            UPLOAD_ERR_NO_TMP_DIR => 'Folder temporary tidak ada.',
            UPLOAD_ERR_CANT_WRITE => 'Gagal menulis berkas ke disk.',
            UPLOAD_ERR_EXTENSION  => 'Ekstensi PHP menghentikan unggahan berkas.',
        ];
        $error_message = "Gagal mengunggah berkas. " . ($upload_errors[$file_upload['error']] ?? "Kode Error: " . $file_upload['error']);
        goto render_page;
    }

    // B. Cek Ukuran File (sesuai batasan 5MB)
    if ($file_upload['size'] > $max_file_size_bytes) {
        $error_message = "Ukuran berkas melebihi batas maksimum {$max_file_size_mb}MB.";
        goto render_page;
    }

    // C. Cek Tipe File (Hanya PDF)
    if (class_exists('finfo')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file_upload['tmp_name']);
        finfo_close($finfo);
    } else {
        $mime_type = $file_upload['type'];
    }

    $allowed_mime = ['application/pdf'];
    $ext = strtolower(pathinfo($file_upload['name'], PATHINFO_EXTENSION));

    if (!in_array($mime_type, $allowed_mime) || $ext !== 'pdf') {
        $error_message = "Tipe berkas tidak valid. Hanya format PDF (.pdf) yang diizinkan.";
        goto render_page;
    }
    
    // --- Lanjutkan Proses Unggah Aman ---

    $upload_dir = 'uploads/rekomendasi/'; // Sesuaikan dengan direktori upload Anda
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0777, true)) {
            $error_message = "Gagal membuat direktori upload.";
            goto render_page;
        }
    }

    $new_file_name = $file_key . '_' . $safe_rekom_id . '_' . time() . '.' . $ext;
    $target_file = $upload_dir . $new_file_name;
    
    // --- Pindahkan file dan Simpan ke Database ---
    if (move_uploaded_file($file_upload['tmp_name'], $target_file)) {
        
        $old_file_path = $rekom_data[$file_key] ?? null;

        // 2. UPDATE KHUSUS: Perbarui kolom file DAN kolom status revisi individual
        // Status utama (status) TIDAK diubah.
        $new_status_value = 'Sudah Direvisi';
        
        // Gunakan backticks (`) untuk nama kolom dinamis
        // Inilah bagian KRITIS yang memastikan status revisi individual terupdate
        $sql_update = "UPDATE rekomendasi_formasi SET `{$file_key}` = ?, `{$status_key}` = ? WHERE id = ?";
        
        if ($stmt_update = mysqli_prepare($conn, $sql_update)) {
            $file_url_db = $target_file; 
            // Bind parameters: (String, String, Integer)
            mysqli_stmt_bind_param($stmt_update, "ssi", $file_url_db, $new_status_value, $safe_rekom_id);
            
            if (mysqli_stmt_execute($stmt_update)) {
                
                // 3. Hapus file lama (opsional tapi disarankan)
                if ($old_file_path && file_exists($old_file_path) && $old_file_path != $target_file) {
                    @unlink($old_file_path);
                }

                // Redirect ke halaman detail dengan notifikasi sukses dan label file yang direvisi
                ob_clean();
                header("Location: detail_isian_rekom.php?id={$safe_rekom_id}&status=success_revision_single&file_label=" . urlencode($file_label));
                exit();

            } else {
                $error_message = "Gagal memperbarui database: " . mysqli_stmt_error($stmt_update);
                if (strpos(mysqli_stmt_error($stmt_update), 'Unknown column') !== false) {
                    $error_message .= "<br><br> **SOLUSI KRITIS:** Kolom `{$status_key}` belum ditemukan di tabel `rekomendasi_formasi`. Mohon tambahkan kolom ini di database Anda.";
                }
                @unlink($target_file);
            }
            mysqli_stmt_close($stmt_update);
        } else {
            $error_message = "Gagal menyiapkan query update: " . mysqli_error($conn);
        }

    } else {
        $error_message = "Gagal memindahkan file ke direktori target. Pastikan direktori '{$upload_dir}' memiliki izin tulis yang benar.";
    }
}


// --- 4. Render Halaman ---
render_page:

include 'template/header.php';
include 'template/sidebar.php';

?>

<div class="content-wrapper">

    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-12">
                    <h1 class="m-0 text-dark"><?php echo htmlspecialchars($page_title); ?></h1>
                    <small class="text-secondary">ID Pengajuan: <?php echo htmlspecialchars($safe_rekom_id); ?></small>
                </div>
            </div>
        </div>
    </div>
    
    <section class="content">
        <div class="container-fluid">
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                    <h4><i class="icon fas fa-exclamation-triangle"></i> Error!</h4>
                    <?php echo nl2br($error_message); ?>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-lg-8 col-md-10"> 
                    <div class="card card-danger card-outline"> 
                        <div class="card-header bg-danger">
                            <h3 class="card-title text-white font-weight-bold"><i class="fas fa-file-upload"></i> Unggah Berkas Revisi Baru</h3>
                        </div>
                        
                        <?php if (!$error_message && $rekom_data && ($rekom_data['status'] ?? '') == 'Perlu Revisi'): ?>
                        
                        <form action="edit_isian_rekom.php?id=<?php echo $safe_rekom_id; ?>&file=<?php echo htmlspecialchars($file_key); ?>" method="POST" enctype="multipart/form-data">
                            <div class="card-body">
                                
                                <div class="alert alert-warning border-left-warning">
                                    <p class="mb-1 text-dark">
                                        <i class="fas fa-exclamation-triangle mr-2"></i> **PERHATIAN:** Anda akan **mengganti** berkas lama untuk **<?php echo htmlspecialchars($file_label); ?>**. Status revisi untuk berkas ini akan diubah menjadi **Sudah Direvisi**.
                                    </p>
                                    <?php if ($rekom_data && !empty($rekom_data['catatan_evaluasi'])): ?>
                                        <hr class="my-2">
                                        <p class="mb-0 text-sm font-italic text-dark">
                                            **Catatan Verifikator:** <?php echo nl2br(htmlspecialchars($rekom_data['catatan_evaluasi'])); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>

                                <div class="form-group mt-4">
                                    <label for="file_revisi">Pilih Berkas Revisi Baru</label>
                                    <div class="input-group">
                                        <div class="custom-file">
                                            <input type="file" class="custom-file-input" id="file_revisi" name="file_revisi" required 
                                                     accept="application/pdf" data-max-size="<?php echo $max_file_size_bytes; ?>">
                                            <label class="custom-file-label" for="file_revisi">Pilih file PDF</label>
                                        </div>
                                    </div>
                                    <small class="form-text text-danger font-weight-bold">
                                        <i class="fas fa-info-circle"></i> Hanya menerima format PDF. Ukuran maksimum: <?php echo $max_file_size_mb; ?>MB.
                                    </small>
                                </div>
                                
                                <div class="form-group mt-4">
                                    <?php 
                                    $current_file = $rekom_data[$file_key] ?? null;
                                    if ($current_file): 
                                    ?>
                                        <p class="mb-1 text-muted">Berkas Lama yang akan diganti:</p>
                                        <a href="<?php echo htmlspecialchars($current_file); ?>" target="_blank" class="btn btn-sm btn-outline-info rounded-pill">
                                            <i class="fas fa-file-pdf"></i> Lihat Berkas Lama (<?php echo basename($current_file); ?>)
                                        </a>
                                    <?php else: ?>
                                        <div class="alert alert-info py-2 text-sm">Belum ada berkas lama yang ditemukan di kolom ini.</div>
                                    <?php endif; ?>
                                </div>

                            </div>
                            <div class="card-footer d-flex justify-content-between">
                                <a href="detail_isian_rekom.php?id=<?php echo $safe_rekom_id; ?>" class="btn btn-default">
                                    <i class="fas fa-arrow-left"></i> Kembali / Batalkan
                                </a>
                                <button type="submit" class="btn btn-danger font-weight-bold" id="submit_button" disabled>
                                    <i class="fas fa-check-circle"></i> Unggah Revisi Berkas Ini
                                </button>
                            </div>
                        </form>
                        
                        <?php else: ?>
                            <?php if (!$error_message): ?>
                                <div class="card-body">
                                    <div class="alert alert-info">
                                        <h5 class="alert-heading"><i class="icon fas fa-lock"></i> Pengunggahan Tidak Diizinkan</h5>
                                        <p>Saat ini status pengajuan adalah **<?php echo htmlspecialchars($status_rekom); ?>**. Anda hanya dapat merevisi berkas jika status pengajuan adalah **'Perlu Revisi'**.</p>
                                        <a href="detail_isian_rekom.php?id=<?php echo $safe_rekom_id; ?>" class="btn btn-info btn-sm mt-2">
                                            <i class="fas fa-eye"></i> Lihat Detail Pengajuan
                                        </a>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>

                    </div>
                </div>
            </div>
            
        </div>
    </section>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const fileInput = document.getElementById('file_revisi');
        const customFileDiv = fileInput ? fileInput.closest('.custom-file') : null;
        const fileLabel = customFileDiv ? customFileDiv.querySelector('.custom-file-label') : null;
        const submitButton = document.getElementById('submit_button');

        if (fileInput && fileLabel && submitButton) {
            
            submitButton.disabled = true;

            fileInput.addEventListener('change', function(e) {
                const files = e.target.files;
                const file = files.length ? files[0] : null;

                fileLabel.textContent = file ? file.name : 'Pilih file PDF';

                if (file) {
                    const maxSize = parseInt(e.target.dataset.maxSize);
                    
                    if (file.size > maxSize) {
                        alert('ERROR: Ukuran file melebihi batas maksimum <?php echo $max_file_size_mb; ?>MB. Silakan pilih file yang lebih kecil.');
                        
                        e.target.value = '';
                        fileLabel.textContent = 'Pilih file PDF';
                        submitButton.disabled = true;
                    } else {
                        submitButton.disabled = false;
                    }
                } else {
                    submitButton.disabled = true;
                }
            });
        }
    });
</script>

<?php
if (isset($conn) && $conn) {
    mysqli_close($conn);
}

include 'template/footer.php';

ob_end_flush();
?>