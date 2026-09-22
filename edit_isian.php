<?php
// ==============================================
// 🛠️ edit_isian.php - SKRIP FORMULIR EDIT DATA DAN LOGIKA PEMROSESAN
// ==============================================

// Mulai sesi jika belum dimulai (sebaiknya dilakukan di auth_guard.php atau index.php)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- PANGGIL FILE KONEKSI.PHP ---
include 'koneksi.php';

// Tentukan judul halaman
$page_title = "Edit Isian Pendaftaran — Uji Kompetensi";

// Variabel aktivasi sidebar
$page = 'ujikom'; 
$sub_page = 'pengajuan_ujikom'; 

// Lokasi Template (Asumsi relatif terhadap file ini: ./template/)
$template_path = 'template/';

// Konfigurasi Database dan Utilitas
$table_name = "pengajuan_ujikom"; 
$upload_dir = 'uploads/perpindahan/'; 
$registration_id = isset($_GET['id']) ? (int)$_GET['id'] : 0; 
$data = null;
$error_message = "";
$success_redirect = false; // <-- Flag untuk JavaScript redirect
$redirect_id = 0;

$evaluator_name = null; // Tambahan: Nama evaluator
$verificator_notes = null; // Tambahan: Catatan evaluator

// ==============================================
// ⚙️ FUNGSI UTILITY 
// ==============================================

function get_data_value_form($data, $key) {
    if (is_array($data) && array_key_exists($key, $data) && $data[$key] !== null) {
        return htmlspecialchars((string)$data[$key]);
    }
    return ''; 
}

function get_file_name($data, $key) {
    $file_name = get_data_value_form($data, $key);
    return !empty($file_name) ? $file_name : 'Belum Ada File';
}

/**
 * Mendapatkan HTML badge status dan pesan tambahan untuk kolom dokumen.
 */
function get_doc_status_html($data, $status_column_name) {
    if (!is_array($data)) return '';

    $status_raw = get_data_value_form($data, $status_column_name);
    $status_upper = strtoupper($status_raw);

    $status_display = 'Menunggu Verifikasi';
    $status_class = 'status-menunggu';
    $message = '<span class="text-muted"><i class="fas fa-history"></i> Dokumen menunggu hasil verifikasi.</span>';

    if ($status_upper === 'SESUAI' || $status_upper === 'YA') {
        $status_display = 'YA (SESUAI)';
        $status_class = 'status-ya';
        $message = '<span class="text-success"><i class="fas fa-check-circle"></i> Dokumen ini telah diverifikasi dan **SESUAI**.</span>';
    } else if ($status_upper === 'TIDAK SESUAI' || $status_upper === 'TIDAK') {
        $status_display = 'TIDAK SESUAI';
        $status_class = 'status-tidak';
        $message = '<span class="text-danger"><i class="fas fa-times-circle"></i> Status: **PERLU PERBAIKAN**. Silakan unggah file baru untuk perbaikan.</span>';
    } else if ($status_upper === '' || $status_upper === '-') {
        $status_display = 'BELUM DIUNGGAH';
        $status_class = 'status-belum';
        $message = '<span class="text-muted"><i class="fas fa-info-circle"></i> Dokumen belum diunggah.</span>';
    } else {
        $status_display = $status_raw; // Tampilkan status apa adanya jika tidak terdefinisi
    }
    
    $badge_html = "<span class=\"status-badge {$status_class}\">{$status_display}</span>";
    
    return [
        'badge' => $badge_html,
        'message' => $message
    ];
}


function handle_file_upload($file_input_name, $upload_dir, $registration_id) {
    if (!isset($_FILES[$file_input_name]) || $_FILES[$file_input_name]['error'] !== UPLOAD_ERR_OK) {
        return ['status' => 'SKIP'];
    }

    $file = $_FILES[$file_input_name];
    $original_name = basename($file['name']);
    $file_ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
    $allowed_ext = ['pdf', 'jpg', 'jpeg', 'png'];

    if ($file['size'] > 5000000) { // 5 MB
        return ['status' => 'ERROR', 'message' => "Ukuran file untuk {$file_input_name} terlalu besar (maks 5MB)."];
    }
    
    if (!in_array($file_ext, $allowed_ext)) {
        return ['status' => 'ERROR', 'message' => "File untuk {$file_input_name} harus berformat PDF, JPG, atau PNG."];
    }

    $new_file_name = $registration_id . '_' . $file_input_name . '_' . time() . '.' . $file_ext;
    $target_file = $upload_dir . $new_file_name;

    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        return ['status' => 'SUCCESS', 'new_name' => $new_file_name];
    } else {
        return ['status' => 'ERROR', 'message' => "Gagal memindahkan file {$original_name} ke direktori upload."];
    }
}

// ==============================================
// 1. LOGIKA PEMROSESAN FORM SUBMISSION (UPDATE DATA & SET REDIRECT FLAG)
// ==============================================

if ($_SERVER["REQUEST_METHOD"] == "POST" && $registration_id > 0) {
    
    // ... [Original POST Logic] ... (Pastikan koneksi di 'koneksi.php' sudah dipanggil)
    
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // --- AMBIL DATA INPUT TEKS ---
    $nama = $_POST['nama'] ?? '';
    $nip = $_POST['nip'] ?? '';
    $hp = $_POST['hp'] ?? ''; 
    $email = $_POST['email'] ?? '';
    $jabatan = $_POST['jabatan'] ?? '';
    $pangkat = $_POST['pangkat'] ?? '';
    $tmt_pangkat = $_POST['tmt_pangkat'] ?? '';
    $jenjang_pendidikan = $_POST['jenjang_pendidikan'] ?? '';
    $program_studi = $_POST['program_studi'] ?? '';
    $instansi = $_POST['instansi'] ?? '';
    $instansi_daerah = $_POST['instansi_daerah'] ?? '';
    $unit_organisasi = $_POST['unit_organisasi'] ?? '';
    $unit_saat_ini = $_POST['unit_saat_ini'] ?? '';
    $unit_sebelumnya = $_POST['unit_sebelumnya'] ?? '';

    $file_updates = [];
    $file_updates_params = [];
    $file_upload_errors = [];

    // --- DAFTAR KOLOM FILE & STATUS ---
    $file_columns = [
        'file_surat_usulan_perpindahan', 'file_dokumen_penetapan', 'file_surat_usulan_uji', 'file_portofolio', 
        'file_sk_cpns_pns', 'file_sk_pangkat', 'file_sk_jabatan', 'file_skp', 'file_ijazah_transkrip', 
        'file_pernyataan_integritas', 'file_pernyataan_bersedia', 'file_pernyataan_pengalaman', 
        'file_rencana_penempatan'
    ];
    
    $status_col_map = [
        'file_surat_usulan_perpindahan' => 'd1_surat_usulan_status',
        'file_dokumen_penetapan' => 'd2_rekomendasi_formasi_status',
        'file_surat_usulan_uji' => 'd3_usulan_ujikom_status',
        'file_portofolio' => 'd4_portofolio_status',
        'file_sk_cpns_pns' => 'd5_sk_cpns_pns_status',
        'file_sk_pangkat' => 'd6_sk_pangkat_status',
        'file_sk_jabatan' => 'd7_sk_jabatan_status',
        'file_skp' => 'd8_nilai_skp_status',
        'file_ijazah_transkrip' => 'd9_ijazah_transkrip_status',
        'file_pernyataan_integritas' => 'd10_integritas_status',
        'file_pernyataan_bersedia' => 'd11_bersedia_status',
        'file_pernyataan_pengalaman' => 'd12_pengalaman_2th_status',
        'file_rencana_penempatan' => 'd13_rencana_penempatan_status',
    ];
    
    // Lakukan pemrosesan upload
    foreach ($file_columns as $col) {
        $upload_result = handle_file_upload($col, $upload_dir, $registration_id);
        
        if ($upload_result['status'] === 'SUCCESS') {
            $file_updates[] = "`{$col}` = ?";
            $file_updates_params[] = $upload_result['new_name'];
            
            // Set status verifikasi ke 'Menunggu Verifikasi' saat ada file baru diupload
            if (isset($status_col_map[$col])) {
                   $file_updates[] = "`{$status_col_map[$col]}` = 'Menunggu Verifikasi'";
            }
        } else if ($upload_result['status'] === 'ERROR') {
            $file_upload_errors[] = $upload_result['message'];
        }
    }
    
    // Jika ada error upload, isi $error_message
    if (!empty($file_upload_errors)) {
        $error_message = "Ditemukan kesalahan upload: " . implode('<br>', $file_upload_errors);
    } else {
        
        // --- BUAT QUERY UPDATE SQL ---
        $set_clause = "
            `nama` = ?, `nip` = ?, `hp` = ?, `email` = ?, `jabatan` = ?, 
            `pangkat` = ?, `tmt_pangkat` = ?, `jenjang_pendidikan` = ?, 
            `program_studi` = ?, `instansi` = ?, `instansi_daerah` = ?, 
            `unit_organisasi` = ?, `unit_saat_ini` = ?, `unit_sebelumnya` = ?
        ";
        // Tambahkan kolom status utama menjadi 'Menunggu Verifikasi' (jika ada perubahan)
        // Saya asumsikan user yang mengedit berarti status pengajuan utama harus kembali ke "Menunggu Verifikasi"
        if (!empty($file_updates) || $_POST['form_submitted'] == 'true') {
             // Jika ada perubahan data teks atau file, set status pengajuan kembali
             $set_clause .= ", `status_pengajuan` = 'Menunggu Verifikasi'";
        }
        
        $bind_types = "ssssssssssssss"; 
        $bind_params = [
            $nama, $nip, $hp, $email, $jabatan, $pangkat, $tmt_pangkat, 
            $jenjang_pendidikan, $program_studi, $instansi, $instansi_daerah, 
            $unit_organisasi, $unit_saat_ini, $unit_sebelumnya
        ];
        
        // Tambahkan parameter file
        if (!empty($file_updates)) {
            $set_clause .= ", " . implode(", ", $file_updates);
            $bind_types .= str_repeat('s', count($file_updates_params));
            $bind_params = array_merge($bind_params, $file_updates_params);
        }
        
        // Tambahkan ID untuk WHERE clause
        $bind_types .= "i";
        $bind_params[] = $registration_id;

        $sql_update = "UPDATE {$table_name} SET {$set_clause} WHERE id = ?";
        
        // --- EKSEKUSI UPDATE ---
        $stmt_update = $conn->prepare($sql_update);
        if ($stmt_update) {
            
            // Persiapan dinamis untuk bind_param
            $tmp_params = array_merge([$bind_types], $bind_params);
            $refs = [];
            foreach($tmp_params as $key => $value) {
                $refs[$key] = &$tmp_params[$key];
            }
            
            call_user_func_array([$stmt_update, 'bind_param'], $refs);
            
            if ($stmt_update->execute()) {
                // SET FLAG JAVASCRIPT REDIRECT
                $success_redirect = true;
                $redirect_id = $registration_id;
                
            } else {
                $error_message = "Gagal memperbarui data: " . $stmt_update->error;
            }
            $stmt_update->close();
        } else {
            $error_message = "Gagal mempersiapkan statement UPDATE: " . $conn->error;
        }
    }
}

// ==============================================
// 2. LOGIKA PENGAMBILAN DATA (Untuk mengisi form)
// ==============================================

// Re-open connection if it was closed by POST logic or fetch new data
if ($registration_id > 0) {
    if (!isset($conn) || empty($conn) || (isset($conn->connect_error) && $conn->connect_error)) {
         include 'koneksi.php'; // Asumsi ini akan membuat $conn baru
    }
    
    if (isset($conn) && !empty($conn) && empty($conn->connect_error)) {
        $sql_select = "SELECT * FROM {$table_name} WHERE id = ?";
        $stmt_select = $conn->prepare($sql_select);
        
        if ($stmt_select) {
            $stmt_select->bind_param("i", $registration_id);
            $stmt_select->execute();
            $result_select = $stmt_select->get_result();

            if ($result_select->num_rows > 0) {
                $data = $result_select->fetch_assoc();
                
                // --- AMBIL DATA EVALUATOR DAN CATATAN ---
                $verificator_notes = get_data_value_form($data, 'catatan_evaluasi');
                $evaluator_id = get_data_value_form($data, 'evaluator_id');
                
                // Fetch Evaluator Name
                if ($evaluator_id !== '' && (int)$evaluator_id > 0) {
                    // Cek apakah tabel 'users' ada dan nama kolomnya benar
                    $sql_evaluator = "SELECT nama FROM users WHERE id = ?"; 
                    if ($stmt_eval = $conn->prepare($sql_evaluator)) {
                        $stmt_eval->bind_param("i", $evaluator_id);
                        if ($stmt_eval->execute()) {
                            $res_eval = $stmt_eval->get_result();
                            if ($res_eval && $res_eval->num_rows > 0) {
                                $eval_row = $res_eval->fetch_assoc();
                                $evaluator_name = htmlspecialchars($eval_row['nama']);
                            }
                        }
                        $stmt_eval->close();
                    }
                }

            } else {
                $data = null; 
                if (empty($error_message)) {
                    $error_message = "Data pendaftaran dengan ID #{$registration_id} tidak ditemukan di database.";
                }
            }
            $stmt_select->close();
        } else if (empty($error_message)){
               $error_message = "Gagal mempersiapkan statement SELECT: " . $conn->error;
        }
    } else if (empty($error_message)){
          $error_message = "Koneksi database gagal saat mengambil data.";
    }
} else {
    $error_message = "ID Pendaftaran tidak valid atau tidak diberikan. Pastikan URL memiliki parameter ?id=X.";
}

// Tutup koneksi setelah selesai (jika masih terbuka)
if (isset($conn) && !empty($conn) && method_exists($conn, 'close') && empty($conn->connect_error)) {
    $conn->close();
}

// ==============================================
// 3. INCLUDE TEMPLATE (OUTPUT DIMULAI DI SINI)
// ==============================================
include $template_path . 'header.php'; 
include $template_path . 'sidebar.php'; 
?>

<div class="content-wrapper">

    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-12">
                    <h1 class="m-0"><?php echo $page_title; ?></h1> 
                </div>
            </div>
        </div>
    </div>
    <?php if (!empty($error_message) && !$success_redirect): ?>
    <section class="content" style="padding-top: 5px; padding-bottom: 0;">
        <div class="container-fluid">
            <div class="alert alert-danger alert-dismissible">
                <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                <h5><i class="icon fas fa-exclamation-triangle"></i> Gagal!</h5>
                Terjadi kesalahan: <?php echo $error_message; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <section class="content">
        <div class="container-fluid">
            
            <style>
                /* Import Font Awesome (agar ikon seperti 'fas fa-save' terlihat) */
                @import url('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css');
                
                /* Style untuk tampilan form */
                :root{
                    --panel:#fff; --muted:#6b7280; --accent-green:#028a0f;
                    --card-shadow: 0 1px 3px rgba(0,0,0,.1); --radius:8px; 
                    --field-border:#d1d5db; --link:#028a0f; --danger: #dc2626;
                    --success: #198754; 
                    --warning: #ffc107;
                }
                .card {
                    background: var(--panel); border-radius: var(--radius);
                    box-shadow: var(--card-shadow); padding: 20px;
                    margin-bottom: 20px; border: 1px solid #dee2e6;
                }
                .section-title {
                    font-size:18px;font-weight:600;color:#0b1720;margin:30px 0 15px;
                    border-bottom:2px solid var(--field-border);padding-bottom:8px;
                }

                /* Form Styles */
                .form-group { margin-bottom: 15px; }
                .form-group label { display: block; margin-bottom: 5px; font-weight: 600; color: #4b5563; }
                .form-control { 
                    width: 100%; padding: 10px; border: 1px solid var(--field-border); 
                    border-radius: 4px; box-sizing: border-box; font-size: 14px;
                }
                .form-control:focus { border-color: var(--accent-green); outline: none; box-shadow: 0 0 0 1px var(--accent-green); }
                .file-info { font-size: 12px; color: var(--muted); margin-top: 5px; }
                .text-danger { color: var(--danger); }
                .text-success { color: var(--success); }
                .text-muted { color: var(--muted); }

                .file-upload-section { 
                    border: 1px solid #e5e7eb; padding: 15px; border-radius: 6px; margin-top: 10px;
                    background: #f9fafb;
                }
                
                /* Status Badge Styles (Replikasi dari detail_isian.php) */
                .status-badge {
                    display:inline-block;padding:3px 6px;border-radius:4px;font-size:11px;font-weight:600;
                    text-transform:uppercase;letter-spacing:0.5px;text-align:center;
                }
                .status-ya {background:#d4edda;color:var(--success) !important; border: 1px solid #c3e6cb;}
                .status-tidak {background:#f8d7da;color:var(--danger) !important; border: 1px solid #f5c6cb;}
                .status-menunggu {background:#fff3cd;color:#856404 !important; border: 1px solid #ffeeba;}
                .status-belum {background:#e2e6ea;color:#495057 !important; border: 1px solid #dae0e5;}

                /* Notes Box Styles */
                .verifikator-notes-container { 
                    margin-top: 15px; 
                    border-top: 1px solid #e9ecef; 
                    padding-top: 15px; 
                    margin-bottom: 20px;
                }
                .notes-box {
                    padding: 10px; 
                    background: #f8f9fa; 
                    border-left: 3px solid #dee2e6; /* Default border */
                    border-radius: 4px; 
                    margin-top: 10px;
                }
                .notes-box.is-revision {
                    border-left: 3px solid var(--danger);
                }
                .notes-box.is-final {
                    border-left: 3px solid var(--success);
                }
                
                /* Tombol */
                .actions .btn {
                    padding: 8px 15px; border-radius: 4px; border: none; font-weight: 600; cursor: pointer; font-size: 15px;
                    transition: all 0.2s; display: inline-flex; align-items: center; gap: 8px;
                    text-decoration: none; 
                }
                .btn-default { background:#6c757d; color:#fff; }
                .btn-default:hover { background:#5a6268; }
                .btn-success { background:var(--accent-green) !important; color:#fff !important; }
                .btn-success:hover { background:#016d0c !important; }

                /* --- TOAST NOTIFICATION STYLES (Diposisikan di atas, menggunakan fixed) --- */
                #success-toast {
                    position: fixed;
                    top: 20px; /* Posisi di atas */
                    right: 20px;
                    background-color: var(--accent-green);
                    color: white;
                    padding: 15px 20px;
                    border-radius: 8px;
                    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
                    z-index: 1050;
                    opacity: 0;
                    transition: opacity 0.5s ease-in-out;
                    display: none;
                }
                #success-toast.show {
                    opacity: 1;
                }
                #success-toast i {
                    margin-right: 10px;
                }
                
            </style>

            <div id="success-toast">
                <i class="fas fa-check-circle"></i> Data berhasil diperbarui!
            </div>
            
            <div class="row">
                <div class="col-md-12">
                    
                    <?php if ($data === null && !empty($error_message) && !$success_redirect): ?>
                        <div class="card" style="border-left: 5px solid var(--danger);">
                            <h3 style="color:var(--danger);"><i class="fas fa-exclamation-triangle"></i> Error Memuat Data</h3>
                            <p><?php echo $error_message; ?></p>
                            <div class="actions">
                                <button type="button" class="btn btn-default" onclick="window.history.back()">Kembali</button>
                            </div>
                        </div>
                    <?php elseif ($data !== null): ?>

                    <div class="card" id="edit-form-card">
                        
                        <?php 
                        // Tampilkan kotak catatan verifikator di sini
                        $catatan_display = trim($verificator_notes);
                        $status_akhir_raw = get_data_value_form($data, 'status_pengajuan');
                        $status_akhir = strtoupper($status_akhir_raw);
                        
                        // Kondisi: Tampilkan jika ada catatan terisi ATAU nama evaluator terdeteksi.
                        $note_or_evaluator_exists = (!empty($catatan_display) && $catatan_display !== '-') || $evaluator_name;

                        if ($note_or_evaluator_exists): 
                            $evaluator_display = $evaluator_name ? "Oleh: <strong>{$evaluator_name}</strong>" : "Oleh: N/A";
                        ?>
                            <div class="verifikator-notes-container">
                                <h6 style="font-weight: 600; margin-bottom: 5px;"><i class="fas fa-user-check"></i> Hasil Verifikasi Dokumen</h6>
                                <p style="font-size: 13px; color: var(--muted); margin-bottom: 5px;"><?= $evaluator_display; ?></p>
                                
                                <?php 
                                // Hanya tampilkan kotak catatan jika isinya ada dan bukan hanya strip '-'
                                if (!empty($catatan_display) && $catatan_display !== '-'): 
                                    
                                    // Logika untuk class warna kotak catatan
                                    $notes_box_class = '';
                                    if ($status_akhir == 'TIDAK SESUAI' || $status_akhir == 'TIDAK') {
                                        $notes_box_class = 'is-revision'; // Merah
                                    } else if ($status_akhir == 'FINAL' || $status_akhir == 'SESUAI' || $status_akhir == 'YA') {
                                        $notes_box_class = 'is-final'; // Hijau
                                    }
                                ?>
                                    <div class="notes-box <?= $notes_box_class ?>">
                                        <h6 style="margin-bottom: 5px; font-size: 14px;"><i class="fas fa-comment-dots"></i> Catatan Verifikator:</h6>
                                        <p style="margin-bottom: 0; font-size: 14px; white-space: pre-wrap;"><?= nl2br($catatan_display) ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="form_submitted" value="true">
                            
                            <h2 class="section-title">Biodata Peserta</h2>
                            
                            <div class="row">
                                <div class="col-md-6 form-group">
                                    <label for="nama">Nama Lengkap</label>
                                    <input type="text" id="nama" name="nama" class="form-control" value="<?php echo get_data_value_form($data, 'nama'); ?>" required>
                                </div>
                                <div class="col-md-6 form-group">
                                    <label for="nip">NIP</label>
                                    <input type="text" id="nip" name="nip" class="form-control" value="<?php echo get_data_value_form($data, 'nip'); ?>" required>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 form-group">
                                    <label for="hp">No. HP</label>
                                    <input type="text" id="hp" name="hp" class="form-control" value="<?php echo get_data_value_form($data, 'hp'); ?>" required>
                                </div>
                                <div class="col-md-6 form-group">
                                    <label for="email">Email</label>
                                    <input type="email" id="email" name="email" class="form-control" value="<?php echo get_data_value_form($data, 'email'); ?>" required>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="jabatan">Jabatan Saat Ini</label>
                                <input type="text" id="jabatan_saat_ini" name="jabatan_saat_ini" class="form-control" value="<?php echo get_data_value_form($data, 'jabatan_saat_ini'); ?>" required>
                            </div>

                            <div class="row">
                                <div class="col-md-6 form-group">
                                    <label for="pangkat">Pangkat / Golongan</label>
                                    <input type="text" id="pangkat" name="pangkat" class="form-control" value="<?php echo get_data_value_form($data, 'pangkat'); ?>" required>
                                </div>
                                <div class="col-md-6 form-group">
                                    <label for="tmt_pangkat">TMT Pangkat/Golongan</label>
                                    <input type="date" id="tmt_pangkat" name="tmt_pangkat" class="form-control" value="<?php echo get_data_value_form($data, 'tmt_pangkat'); ?>">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 form-group">
                                    <label for="jenjang_pendidikan">Jenjang Pendidikan Terakhir</label>
                                    <input type="text" id="jenjang_pendidikan" name="jenjang_pendidikan" class="form-control" value="<?php echo get_data_value_form($data, 'jenjang_pendidikan'); ?>">
                                </div>
                                <div class="col-md-6 form-group">
                                    <label for="program_studi">Program Studi</label>
                                    <input type="text" id="program_studi" name="program_studi" class="form-control" value="<?php echo get_data_value_form($data, 'program_studi'); ?>">
                                </div>
                            </div>


                            <h2 class="section-title">Data Instansi</h2>
                            
                            <div class="form-group">
                                <label for="instansi">Instansi Pusat</label>
                                <input type="text" id="instansi" name="instansi" class="form-control" value="<?php echo get_data_value_form($data, 'instansi'); ?>">
                            </div>
                            <div class="form-group">
                                <label for="instansi_daerah">Instansi Daerah</label>
                                <input type="text" id="unit_daerah" name="unit_daerah" class="form-control" value="<?php echo get_data_value_form($data, 'unit_daerah'); ?>">
                            </div>
                            <div class="form-group">
                                <label for="unit_organisasi">Nama Unit Organisasi</label>
                                <input type="text" id="unit_organisasi" name="unit_organisasi" class="form-control" value="<?php echo get_data_value_form($data, 'unit_organisasi'); ?>">
                            </div>
                            <div class="form-group">
                                <label for="unit_saat_ini">Unit Kerja Saat Ini</label>
                                <input type="text" id="unit_saat_ini" name="unit_saat_ini" class="form-control" value="<?php echo get_data_value_form($data, 'unit_saat_ini'); ?>">
                            </div>
                            <div class="form-group">
                                <label for="unit_sebelumnya">Unit Kerja Sebelumnya</label>
                                <input type="text" id="unit_sebelumnya" name="unit_sebelumnya" class="form-control" value="<?php echo get_data_value_form($data, 'unit_sebelumnya'); ?>">
                            </div>

                            <h2 class="section-title" id="dokumen-section">Perbaikan Dokumen</h2>
                            <p class="text-danger"><i class="fas fa-exclamation-circle"></i> **Perhatian:** Mengunggah file baru akan **mengganti** file lama dan status verifikasi dokumen tersebut akan diubah kembali menjadi **'Menunggu Verifikasi'**.</p>
                            
                            <div class="file-upload-section">
                                <?php 
                                // Daftar Dokumen dan Kolom Statusnya
                                $dokumen_list = [
                                    ['label' => '1. Surat Usulan Perpindahan Jabatan (Maks 5MB, PDF/JPG/PNG)', 'file_col' => 'file_surat_usulan_perpindahan', 'status_col' => 'd1_surat_usulan_status'],
                                    ['label' => '2. Dokumen Penetapan Kebutuhan JF (Maks 5MB, PDF/JPG/PNG)', 'file_col' => 'file_dokumen_penetapan', 'status_col' => 'd2_rekomendasi_formasi_status'],
                                    ['label' => '3. Surat Usulan Uji Kompetensi (Maks 5MB, PDF/JPG/PNG)', 'file_col' => 'file_surat_usulan_uji', 'status_col' => 'd3_usulan_ujikom_status'],
                                    ['label' => '4. Dokumen Portofolio (Maks 5MB, PDF/JPG/PNG)', 'file_col' => 'file_portofolio', 'status_col' => 'd4_portofolio_status'],
                                    ['label' => '5. Salinan SK CPNS/PNS (Maks 5MB, PDF/JPG/PNG)', 'file_col' => 'file_sk_cpns_pns', 'status_col' => 'd5_sk_cpns_pns_status'],
                                    ['label' => '6. Salinan SK Pangkat Terakhir (Maks 5MB, PDF/JPG/PNG)', 'file_col' => 'file_sk_pangkat', 'status_col' => 'd6_sk_pangkat_status'],
                                    ['label' => '7. Salinan SK Jabatan Terakhir (Maks 5MB, PDF/JPG/PNG)', 'file_col' => 'file_sk_jabatan', 'status_col' => 'd7_sk_jabatan_status'],
                                    ['label' => '8. Nilai SKP/PPK 2 Tahun Terakhir (Maks 5MB, PDF/JPG/PNG)', 'file_col' => 'file_skp', 'status_col' => 'd8_nilai_skp_status'],
                                    ['label' => '9. Salinan Ijazah dan Transkrip (Maks 5MB, PDF/JPG/PNG)', 'file_col' => 'file_ijazah_transkrip', 'status_col' => 'd9_ijazah_transkrip_status'],
                                    ['label' => '10. Pernyataan Integritas (Maks 5MB, PDF/JPG/PNG)', 'file_col' => 'file_pernyataan_integritas', 'status_col' => 'd10_integritas_status'],
                                    ['label' => '11. Pernyataan Bersedia (Maks 5MB, PDF/JPG/PNG)', 'file_col' => 'file_pernyataan_bersedia', 'status_col' => 'd11_bersedia_status'],
                                    ['label' => '12. Pernyataan Pengalaman 2 Tahun (Maks 5MB, PDF/JPG/PNG)', 'file_col' => 'file_pernyataan_pengalaman', 'status_col' => 'd12_pengalaman_2th_status'],
                                    ['label' => '13. Rencana Penempatan (Maks 5MB, PDF/JPG/PNG)', 'file_col' => 'file_rencana_penempatan', 'status_col' => 'd13_rencana_penempatan_status'],
                                ];
                                
                                foreach ($dokumen_list as $doc):
                                    $status_info = get_doc_status_html($data, $doc['status_col']);
                                ?>
                                <div class="form-group" id="<?php echo $doc['file_col']; ?>">
                                    <label for="<?php echo $doc['file_col']; ?>"><?php echo $doc['label']; ?></label>
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                                        <p class="file-info" style="margin: 0;">File Saat Ini: **<?php echo get_file_name($data, $doc['file_col']); ?>**</p>
                                        <div class="status-display"><?php echo $status_info['badge']; ?></div>
                                    </div>
                                    <input type="file" id="<?php echo $doc['file_col']; ?>" name="<?php echo $doc['file_col']; ?>" class="form-control">
                                    <p class="file-info" style="margin-top: 5px;"><?php echo $status_info['message']; ?></p>
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="actions" style="margin-top: 30px;">
                                <button type="button" class="btn btn-default" onclick="window.location.href='detail_isian.php?id=<?php echo $registration_id; ?>'">
                                    <i class="fas fa-arrow-left"></i> Kembali ke Detail
                                </button>
                                <button type="submit" class="btn btn-success" style="margin-left: 10px;">
                                    <i class="fas fa-save"></i> Simpan Perubahan
                                </button>
                            </div>
                        </form>
                        </div> 
                    <?php endif; ?>

                </div>
            </div>
            
        </div>
    </section>
</div> 
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Ambil nilai dari flag PHP
        const shouldRedirect = <?php echo $success_redirect ? 'true' : 'false'; ?>;
        const redirectId = <?php echo $redirect_id; ?>;
        
        if (shouldRedirect) {
            const toast = document.getElementById('success-toast');
            
            if (toast) {
                // 1. Tampilkan toast
                toast.style.display = 'block';
                // Gunakan timeout kecil agar transisi opacity berjalan
                setTimeout(() => {
                    toast.classList.add('show');
                }, 10); 
                
                // 2. Tunda redirection (3 detik)
                setTimeout(() => {
                    // Sembunyikan toast
                    toast.classList.remove('show');
                    
                    // Lakukan Redirection ke detail_isian.php
                    window.location.href = `detail_isian.php?id=${redirectId}&success=true`;
                }, 3000); // Tahan notifikasi selama 3 detik sebelum redirect
            }
        }
        
        // Bersihkan URL dari parameter success jika ada (misal setelah refresh manual)
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('success')) {
            const id = urlParams.get('id');
            let newUrl = window.location.pathname;
            if(id) {
                newUrl += `?id=${id}`;
            }
            // Menggunakan replaceState untuk menghindari page reload
            history.replaceState(null, null, newUrl);
        }
    });
</script>


<?php
// 4. Sertakan Footer Template
include $template_path . 'footer.php';
?>