<?php
// FILE: detail_isian_rekom.php - Tampilan Detail Pengajuan Rekomendasi Formasi (Untuk Pengusul)

ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Pastikan file koneksi dan pengaman sesi ada dan di-require
require_once 'auth_guard.php';
require_once 'koneksi.php';

$page = 'rekomendasi';
$sub_page = 'list_rekom';
$page_title = 'Detail Isian Rekomendasi Formasi';
$error_message = null;
$data = null;
$evaluations = [];
$is_revision_mode = false;
$evaluator_name = null;
$upload_success = false;
$resubmit_success = false;

// Mapping dari file key ke key evaluasi utama (berdasarkan eval_rekom.php)
$file_eval_map = [
    'file_usulan_formasi'   => 'eval_1a',
    'file_tupoksi'         => 'eval_2a',
    'file_abk'             => 'eval_3a',
    'file_struktur'        => 'eval_4a',
    'file_peta_jabatan'    => 'eval_5a',
    'file_sk_kelola'       => 'eval_6a',
    'file_anggaran_daerah' => 'eval_7a',
    'file_bukti_jafung'    => 'eval_8a',
];

// Mapping dari file key ke key catatan evaluasi detail
$file_note_map = [
    'file_usulan_formasi'   => 'eval_1b',
    'file_tupoksi'         => 'eval_2b',
    'file_abk'             => 'eval_3b',
    'file_struktur'        => 'eval_4b',
    'file_peta_jabatan'    => 'eval_5b',
    'file_sk_kelola'       => 'eval_6b',
    'file_anggaran_daerah' => 'eval_7b',
    'file_bukti_jafung'    => 'eval_8b',
];

// --- START: FILE UPLOAD HANDLER (Upload Dokumen Individu) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reupload_doc' && isset($_POST['rekom_id'], $_POST['file_key'])) {

    $upload_id = intval($_POST['rekom_id']);
    $upload_file_key = $_POST['file_key'];
    $current_file_input = 'new_' . $upload_file_key;

    if ($upload_id > 0 && array_key_exists($upload_file_key, $file_eval_map) && isset($_FILES[$current_file_input]) && $_FILES[$current_file_input]['error'] === UPLOAD_ERR_OK) {

        // --- LOGIKA PENYIMPANAN FILE ---
        $target_dir = __DIR__ . "/uploads/rekomendasi/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $file_extension = pathinfo($_FILES[$current_file_input]['name'], PATHINFO_EXTENSION);
        // Sanitasi extension sederhana
        $file_extension = preg_replace('/[^a-zA-Z0-9]/', '', $file_extension);
        $file_name = $upload_id . '_' . $upload_file_key . '_revisi_' . time() . '.' . $file_extension;
        $target_file_path = $target_dir . $file_name;

        if (move_uploaded_file($_FILES[$current_file_input]['tmp_name'], $target_file_path)) {
            // Simpan path relatif agar mudah dipanggil di browser
            $new_file_url = 'uploads/rekomendasi/' . $file_name;

            // Query untuk UPDATE kolom file dan tanggal revisi di database
            $update_sql = "UPDATE rekomendasi_formasi SET $upload_file_key = ?, tanggal_revisi = NOW() WHERE id = ?";

            if ($stmt_update = mysqli_prepare($conn, $update_sql)) {
                mysqli_stmt_bind_param($stmt_update, "si", $new_file_url, $upload_id);
                if (mysqli_stmt_execute($stmt_update)) {
                    header('Location: detail_isian_rekom.php?id=' . $upload_id . '&msg=upload_success');
                    exit;
                } else {
                    $error_message = "Gagal mengupdate database setelah upload: " . mysqli_stmt_error($stmt_update);
                }
                mysqli_stmt_close($stmt_update);
            } else {
                $error_message = "Gagal menyiapkan query update: " . mysqli_error($conn);
            }
        } else {
            $error_message = "Gagal memindahkan file yang diunggah. Cek izin folder: " . $target_dir;
        }
    } elseif ($upload_id > 0) {
        $error_message = "Upload gagal. Pastikan file dipilih dan ukurannya tidak melebihi batas server.";
    }
}
// --- END: FILE UPLOAD HANDLER ---


// --- START: SUBMIT ULANG VERIFIKASI HANDLER (PERBAIKAN STATUS) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_revisi' && isset($_POST['rekom_id_submit'])) {
    $submit_id = intval($_POST['rekom_id_submit']);

    // UBAH STATUS KE 'VERIFIKASI ULANG'
    $new_status = 'VERIFIKASI ULANG';
    // Hanya ubah jika status sebelumnya 'Perlu Revisi' — gunakan case-insensitive check via WHERE
    $submit_sql = "UPDATE rekomendasi_formasi SET status = ?, tanggal_pengajuan = NOW() WHERE id = ? AND status = 'Perlu Revisi'";

    if ($stmt_submit = mysqli_prepare($conn, $submit_sql)) {
        mysqli_stmt_bind_param($stmt_submit, "si", $new_status, $submit_id);
        if (mysqli_stmt_execute($stmt_submit) && mysqli_stmt_affected_rows($stmt_submit) > 0) {
            header('Location: detail_isian_rekom.php?id=' . $submit_id . '&msg=resubmit_success');
            exit;
        } else {
            $error_message = "Gagal mengajukan ulang. Status pengajuan mungkin sudah diperbarui atau data tidak ditemukan.";
        }
        mysqli_stmt_close($stmt_submit);
    } else {
        $error_message = "Gagal menyiapkan query ajukan ulang: " . mysqli_error($conn);
    }
}
// --- END: SUBMIT ULANG VERIFIKASI HANDLER ---


$rekom_id = $_GET['id'] ?? null;
$safe_rekom_id = $rekom_id ? intval($rekom_id) : null;
$upload_success = isset($_GET['msg']) && $_GET['msg'] === 'upload_success';
$resubmit_success = isset($_GET['msg']) && $_GET['msg'] === 'resubmit_success';

if (!$safe_rekom_id || $safe_rekom_id <= 0) {
    $error_message = "ID Rekomendasi tidak valid atau tidak ditemukan.";
} else {
    // 1. Query Utama - Mengambil data rekomendasi
    $sql = "
        SELECT 
            r.*, r.evaluasi_data_json, r.catatan_evaluasi, r.evaluator_id 
        FROM 
            rekomendasi_formasi r
        WHERE 
            r.id = ? 
        LIMIT 1
    ";

    if (!isset($conn)) {
        $error_message = "Koneksi database gagal dimuat.";
    } elseif ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $safe_rekom_id);

        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);

            if ($result && mysqli_num_rows($result) > 0) {
                $data = mysqli_fetch_assoc($result);
                $status = $data['status'] ?? 'Draft';

                // Mode revisi diaktifkan jika status: Perlu Revisi, Disetujui, Ditolak, atau VERIFIKASI ULANG
                $status_upper = strtoupper(trim($status));
                if ($status_upper === 'PERLU REVISI' || $status_upper === 'DISETUJUI' || $status_upper === 'DITOLAK' || $status_upper === 'VERIFIKASI ULANG') {
                    $is_revision_mode = true;
                    if (!empty($data['evaluasi_data_json'])) {
                        $decoded = json_decode($data['evaluasi_data_json'], true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                            $evaluations = $decoded;
                        }
                    }
                }

                // Query Tambahan - Mencari Nama Evaluator
                if (!empty($data['evaluator_id'])) {
                    $sql_evaluator = "SELECT nama FROM users WHERE id = ? LIMIT 1";

                    if ($stmt_eval = mysqli_prepare($conn, $sql_evaluator)) {
                        mysqli_stmt_bind_param($stmt_eval, "i", $data['evaluator_id']);

                        if (mysqli_stmt_execute($stmt_eval)) {
                            $res_eval = mysqli_stmt_get_result($stmt_eval);
                            if ($res_eval && mysqli_num_rows($res_eval) > 0) {
                                $eval_row = mysqli_fetch_assoc($res_eval);
                                $evaluator_name = htmlspecialchars($eval_row['nama']);
                            } else {
                                $evaluator_name = "ID Evaluator Tidak Ditemukan";
                            }
                        } else {
                            $evaluator_name = "Gagal Query Evaluator: " . mysqli_error($conn);
                        }
                        mysqli_stmt_close($stmt_eval);
                    } else {
                        $evaluator_name = "Gagal Prepare Query Evaluator: " . mysqli_error($conn);
                    }
                }
            } else {
                $error_message = "Data rekomendasi dengan ID {$safe_rekom_id} tidak ditemukan.";
            }
        } else {
            $error_message = "Gagal menjalankan query: " . mysqli_stmt_error($stmt);
        }
        mysqli_stmt_close($stmt);
    } else {
        $error_message = "Gagal menyiapkan query utama: " . mysqli_error($conn);
    }
}

/**
 * Membuat baris tabel untuk detail dokumen, dengan FORM UPLOAD ULANG.
 */
function get_doc_isian_row($data, $label, $file_key, $is_revision_mode, $evaluations, $file_eval_map, $file_note_map)
{
    $file_url = $data[$file_key] ?? null;
    $file_exists = !empty($file_url);

    $display_name = $file_exists ? basename($file_url) : "Belum diunggah";

    $status_html = $file_exists ? '<span class="badge badge-success">ADA</span>' : '<span class="badge badge-danger">TIDAK ADA</span>';
    $eval_status_html = '<span class="text-secondary">-</span>';
    $revision_note_html = '';

    $action_html = $file_exists
        ? '<a href="' . htmlspecialchars($file_url) . '" target="_blank" class="btn btn-sm btn-info"><i class="fas fa-file-download"></i> Lihat/Download</a>'
        : '';

    if ($is_revision_mode) {
        $eval_key = $file_eval_map[$file_key] ?? null;
        $note_key = $file_note_map[$file_key] ?? null;
        $eval_result = $eval_key ? ($evaluations[$eval_key] ?? '') : '';
        $revision_note = $note_key ? trim($evaluations[$note_key] ?? '') : '';

        if ($eval_result === 'Ya') {
            $eval_status_html = '<span class="badge badge-success">Diterima</span>';
        } elseif ($eval_result === 'Tidak') {
            $eval_status_html = '<span class="badge badge-danger"><i class="fas fa-exclamation-triangle"></i> REVISI</span>';
            $status_html = $file_exists ? '<span class="badge badge-warning">ADA</span>' : $status_html;

            $rekom_id_for_link = htmlspecialchars($data['id'] ?? '');
            $input_name = 'new_' . $file_key;

            $action_html .= '
                <div class="mt-2 p-2 bg-light border border-danger rounded">
                    <h6 class="text-danger mb-1" style="font-size: 0.9rem;">Upload Ulang Dokumen:</h6>
                    <form method="POST" enctype="multipart/form-data" action="detail_isian_rekom.php?id=' . $rekom_id_for_link . '">
                        <input type="hidden" name="action" value="reupload_doc">
                        <input type="hidden" name="rekom_id" value="' . $rekom_id_for_link . '">
                        <input type="hidden" name="file_key" value="' . htmlspecialchars($file_key) . '">
                        <div class="form-group mb-1">
                            <input type="file" name="' . $input_name . '" required class="form-control-file form-control-sm border p-1" accept=".pdf,.doc,.docx">
                            <small class="text-muted">Max. 5MB, format PDF/DOC</small>
                        </div>
                        <button type="submit" class="btn btn-sm btn-danger btn-block">
                            <i class="fas fa-upload"></i> Upload Berkas Baru
                        </button>
                    </form>
                </div>
            ';

            if (!empty($revision_note)) {
                $revision_note_html = '
                    <div class="mt-2 p-1 border border-danger rounded text-sm bg-light">
                        <small class="font-weight-bold text-danger"><i class="fas fa-comment-alt"></i> CATATAN DETAIL REVISI:</small><br>
                        <small>' . nl2br(htmlspecialchars($revision_note)) . '</small>
                    </div>
                ';
            }
        } else {
            $eval_status_html = '<span class="badge badge-secondary">Belum Dievaluasi</span>';
        }
    }

    return '
        <tr>
            <td>' . htmlspecialchars($label) . '</td>
            <td>' . htmlspecialchars($display_name) . '</td>
            <td>' . $status_html . '</td>
            <td>' . $eval_status_html . '</td>
            <td>' . $action_html . $revision_note_html . '</td>
        </tr>
    ';
}

// Render halaman
include 'template/header.php';
?>
<?php include 'template/sidebar.php'; ?>

<div class="content-wrapper">

    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-12">
                    <h1 class="m-0"><?php echo $page_title; ?></h1>
                    <small>ID Pengajuan: <?php echo htmlspecialchars($safe_rekom_id ?? 'N/A'); ?></small>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">

            <?php if ($upload_success): ?>
                <div class="alert alert-warning alert-dismissible fade show">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                    <h4><i class="icon fas fa-upload"></i> Berkas Berhasil Diunggah Ulang!</h4>
                    Dokumen berhasil diunggah ulang. Lanjutkan perbaikan pada dokumen lain (jika ada) dan <strong>klik tombol "Ajukan Ulang Verifikasi" di bawah</strong> setelah semua revisi selesai.
                </div>
            <?php endif; ?>

            <?php if ($resubmit_success): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                    <h4><i class="icon fas fa-check"></i> Pengajuan Ulang Berhasil!</h4>
                    Status pengajuan Anda kini adalah <strong>Verifikasi Ulang</strong>. Silakan tunggu proses verifikasi selanjutnya.
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-danger">
                    <h4><i class="icon fas fa-ban"></i> Error!</h4>
                    <?php echo $error_message; ?>
                    <p>
                        <button type="button" class="btn btn-sm btn-default mt-2" onclick="window.history.back()">
                            <i class="fas fa-arrow-left"></i> Kembali
                        </button>
                    </p>
                </div>
            <?php elseif ($data): ?>

                <div class="row">
                    <div class="col-md-12">
                        <?php
                        $status = $data['status'] ?? 'Draft';
                        $status_class = 'secondary';
                        $text_color_class = 'text-dark';

                        $status_upper = strtoupper(trim($status));
                        if ($status_upper === 'DISETUJUI') {
                            $status_class = 'success';
                            $text_color_class = 'text-success';
                        } elseif ($status_upper === 'MENUNGGU VERIFIKASI' || $status_upper === 'MENUNGGU VERIFIKASI') {
                            $status_class = 'warning';
                            $text_color_class = 'text-dark';
                        } elseif ($status_upper === 'VERIFIKASI ULANG') {
                            $status_class = 'info';
                            $text_color_class = 'text-info';
                        } elseif ($status_upper === 'PERLU REVISI') {
                            $status_class = 'danger';
                            $text_color_class = 'text-danger';
                        } elseif ($status_upper === 'DITOLAK') {
                            $status_class = 'dark';
                            $text_color_class = 'text-dark';
                        } else {
                            $status_class = 'info';
                            $text_color_class = 'text-info';
                        }
                        ?>
                        <div class="callout callout-<?= $status_class ?>">
                            <h5>Status Pengajuan: <strong class="<?= $text_color_class ?>"><?= htmlspecialchars($status) ?></strong></h5>
                            <p>Pengajuan ini dibuat pada tanggal: <?= date('d F Y H:i:s', strtotime($data['tanggal_pengajuan'] ?? 'now')) ?></p>

                            <?php
                            if ($is_revision_mode):
                                $catatan_evaluasi = trim($data['catatan_evaluasi'] ?? '');
                                $evaluator_display = $evaluator_name ? "Oleh: $evaluator_name" : "Oleh: N/A";
                                ?>
                                <hr class="my-2">
                                <h6><i class="fas fa-user-check"></i> Hasil Verifikasi</h6>
                                <p class="mb-1 text-sm"><?= $evaluator_display; ?></p>

                                <?php if (!empty($catatan_evaluasi)): ?>
                                    <div class="alert alert-info p-2 mt-2">
                                        <h6 class="mb-1"><i class="fas fa-comment-dots"></i> Catatan Verifikator/Revisi Umum:</h6>
                                        <p class="mb-0"><?= nl2br(htmlspecialchars($catatan_evaluasi)) ?></p>
                                    </div>
                                <?php endif; ?>

                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- DATA UMUM -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="card card-primary card-outline">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-building"></i> I. Data Umum Instansi & Pengusul</h3>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <dl class="row">
                                            <dt class="col-sm-4">Nama Instansi</dt>
                                            <dd class="col-sm-8">: <?= htmlspecialchars($data['instansi'] ?? '-') ?></dd>
                                            <dt class="col-sm-4">Provinsi / Kota</dt>
                                            <dd class="col-sm-8">: <?= htmlspecialchars($data['provinsi'] ?? '-') ?> / <?= htmlspecialchars($data['kota_kab'] ?? '-') ?></dd>
                                            <dt class="col-sm-4">Tanggal Pengajuan</dt>
                                            <dd class="col-sm-8">: <?= date('d F Y', strtotime($data['tanggal_pengajuan'] ?? 'now')) ?></dd>
                                        </dl>
                                    </div>
                                    <div class="col-md-6">
                                        <dl class="row">
                                            <dt class="col-sm-4">Nama Pengusul</dt>
                                            <dd class="col-sm-8">: <?= htmlspecialchars($data['nama_pengusul'] ?? '-') ?></dd>
                                            <dt class="col-sm-4">NIP Pengusul</dt>
                                            <dd class="col-sm-8">: <?= htmlspecialchars($data['nip'] ?? '-') ?></dd>
                                            <dt class="col-sm-4">Jabatan Pengusul</dt>
                                            <dd class="col-sm-8">: <?= htmlspecialchars($data['jabatan'] ?? '-') ?></dd>
                                        </dl>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- DETAIL FORMASI -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="card card-primary card-outline">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-users-cog"></i> II. Detail Formasi yang Diajukan</h3>
                            </div>
                            <div class="card-body">
                                <dl class="row">
                                    <dt class="col-sm-3">Nama Jabatan Fungsional</dt>
                                    <dd class="col-sm-9">: <?= htmlspecialchars($data['nama_jabatan_jf'] ?? '-') ?></dd>
                                    <dt class="col-sm-3">Jenjang Jabatan Yang Dituju</dt>
                                    <dd class="col-sm-9">: <?= htmlspecialchars($data['jenjang_jf'] ?? '-') ?></dd>
                                    <dt class="col-sm-3">Kebutuhan Formasi (Orang)</dt>
                                    <dd class="col-sm-9">: <?= htmlspecialchars($data['kebutuhan_formasi'] ?? 0) ?> Orang</dd>
                                    <dt class="col-sm-3">Dasar Hukum</dt>
                                    <dd class="col-sm-9">: <?= htmlspecialchars($data['dasar_hukum'] ?? '-') ?></dd>
                                </dl>
                                <p class="mt-3 text-sm">
                                    <strong>Keterangan Tambahan:</strong><br>
                                    <?= nl2br(htmlspecialchars($data['keterangan_tambahan'] ?? 'Tidak ada keterangan tambahan.')) ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- DOKUMEN PENDUKUNG -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="card card-primary card-outline">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-file-alt"></i> III. Dokumen Pendukung</h3>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-bordered table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th style="width: 30%">Nama Dokumen</th>
                                            <th style="width: 15%">Nama File Saat Ini</th>
                                            <th style="width: 10%">Status Unggah</th>
                                            <th style="width: 10%">Status Evaluasi</th>
                                            <th style="width: 35%">Aksi & Form Revisi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        echo get_doc_isian_row($data, '1. Dokumen Usulan Formasi JF', 'file_usulan_formasi', $is_revision_mode, $evaluations, $file_eval_map, $file_note_map);
                                        echo get_doc_isian_row($data, '2. Tupoksi Unit Kerja', 'file_tupoksi', $is_revision_mode, $evaluations, $file_eval_map, $file_note_map);
                                        echo get_doc_isian_row($data, '3. Hasil Analisis Beban Kerja (ABK)', 'file_abk', $is_revision_mode, $evaluations, $file_eval_map, $file_note_map);
                                        echo get_doc_isian_row($data, '4. Struktur Organisasi Unit Kerja', 'file_struktur', $is_revision_mode, $evaluations, $file_eval_map, $file_note_map);
                                        echo get_doc_isian_row($data, '5. Peta Jabatan Unit Kerja', 'file_peta_jabatan', $is_revision_mode, $evaluations, $file_eval_map, $file_note_map);
                                        echo get_doc_isian_row($data, '6. SK Kelas Jabatan', 'file_sk_kelola', $is_revision_mode, $evaluations, $file_eval_map, $file_note_map);
                                        echo get_doc_isian_row($data, '7. File Anggaran Daerah', 'file_anggaran_daerah', $is_revision_mode, $evaluations, $file_eval_map, $file_note_map);
                                        echo get_doc_isian_row($data, '8. Bukti Dukung Jabatan Fungsional', 'file_bukti_jafung', $is_revision_mode, $evaluations, $file_eval_map, $file_note_map);
                                        ?>
                                    </tbody>
                                </table>
                            </div>

                            <div class="card-footer clearfix">
                                <button type="button" class="btn btn-default" onclick="window.history.back()">
                                    <i class="fas fa-arrow-left"></i> Kembali ke Daftar
                                </button>

                                <?php
                                $current_status = $data['status'] ?? 'Draft';
                                $edit_link = 'input_rekom.php?id=' . $safe_rekom_id;

                                if (strtoupper(trim($current_status)) === 'DRAFT' || strtoupper(trim($current_status)) === 'PERLU REVISI'):
                                    ?>
                                    <a href="<?php echo $edit_link; ?>" class="btn btn-warning float-left mr-2">
                                        <i class="fas fa-edit"></i> Edit Data Utama
                                    </a>
                                <?php endif; ?>

                                <?php if (strtoupper(trim($current_status)) === 'PERLU REVISI'): ?>
                                    <form method="POST" action="detail_isian_rekom.php?id=<?= $safe_rekom_id ?>" class="d-inline float-right">
                                        <input type="hidden" name="action" value="submit_revisi">
                                        <input type="hidden" name="rekom_id_submit" value="<?= $safe_rekom_id ?>">
                                        <button type="submit" class="btn btn-success" onclick="return confirm('PASTIKAN SEMUA BERKAS REVISI SUDAH DIUNGGAH ULANG. Apakah Anda yakin ingin mengajukan ulang untuk verifikasi?');">
                                            <i class="fas fa-paper-plane"></i> Ajukan Ulang Verifikasi
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <?php if (strtoupper(trim($current_status)) === 'DISETUJUI'): ?>
                                    <a href="surat_usulan_formasi.php?id=<?= $safe_rekom_id ?>" target="_blank" class="btn btn-primary float-right ml-2">
                                        <i class="fas fa-print"></i> Cetak Surat Usulan
                                    </a>
                                <?php endif; ?>
                            </div>

                        </div>
                    </div>
                </div>

            <?php endif; ?>

        </div>
    </section>
</div>

<?php
if (isset($conn) && $conn) {
    mysqli_close($conn);
}

include 'template/footer.php';

ob_end_flush();
?>
