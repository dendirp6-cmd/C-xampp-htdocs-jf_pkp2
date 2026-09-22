<?php
// ==============================================
// 🛠️ detail_isian.php - VERSI LENGKAP (13 DOKUMEN)
// INTEGRASI HISTORI CATATAN INTERNAL & PUBLIK
// ==============================================

// Mulai sesi jika belum dimulai
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Tentukan judul halaman
$page_title = "Detail Isian Pendaftaran — Uji Kompetensi";

// Variabel aktivasi sidebar
$page = 'ujikom'; 
$sub_page = 'pengajuan_ujikom'; 

// Lokasi Template
$template_path = 'template/';

// IDENTIFIKASI ROLE USER
$user_role_sesi = strtoupper($_SESSION['user_role_sesi'] ?? 'PENGUSUL');
$is_pengusul = ($user_role_sesi === 'PENGUSUL');

// 1. Sertakan Template
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
    <section class="content">
        <div class="container-fluid">
            
            <?php
            // ==============================================
            // 🛠️ PENGAMBILAN DATA DARI DATABASE
            // ==============================================
            include 'koneksi.php';

            $table_name = "pengajuan_ujikom"; 
            $registration_id = isset($_GET['id']) ? (int)$_GET['id'] : 0; 

            // --- LOGIKA RAKYAT: Update is_read_notif ---
            if ($registration_id > 0 && isset($conn)) {
                // Tandai sudah dibaca agar tidak muncul lagi di navbar
                $conn->query("UPDATE {$table_name} SET is_read_notif = 1 WHERE id = $registration_id");
            }

            $data = null;
            $error_message = "";
            $verifikator_name = null;

            // --- FUNGSI FILTER TAMPILAN HISTORI CATATAN UNTUK BOX PUBLIK ---
            function filter_catatan_untuk_pengusul($raw_text, $role_view) {
                if (empty($raw_text) || $raw_text === '-') return "";
                if ($role_view !== 'PENGUSUL') {
                    // Jika bukan pengusul (admin/verifikator), bersihkan tag role saja agar rapi
                    return preg_replace('/\[.*?➔.*?\]\s+/', '', $raw_text);
                }

                $lines = explode("\n", $raw_text);
                $filtered = [];
                foreach ($lines as $line) {
                    if (empty(trim($line))) continue;
                    if (strpos($line, '➔ PENGUSUL') !== false) {
                        // Hilangkan tag [VERIFIKATOR ➔ PENGUSUL] agar yang tampil hanya isinya
                        $clean_line = preg_replace('/\[.*?➔.*?\]\s+/', '', $line);
                        $filtered[] = $clean_line;
                    }
                }
                return !empty($filtered) ? implode("\n", $filtered) : "";
            }

            if (!isset($conn) || $conn->connect_error) { 
                $error_message = "Kesalahan koneksi database.";
            } else if ($registration_id == 0) {
                $error_message = "ID Pendaftaran tidak valid.";
            } else {
                $sql = "SELECT * FROM {$table_name} WHERE id = ?"; 
                $stmt = $conn->prepare($sql);
                
                if ($stmt) {
                    $stmt->bind_param("i", $registration_id);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($result->num_rows > 0) {
                        $data = $result->fetch_assoc();
                        
                        // --- PENYESUAIAN: Menggunakan verifikator_id ---
                        $v_id = get_data_value($data, 'verifikator_id');
                        
                        if ($v_id !== '-' && (int)$v_id > 0) {
                            $sql_v = "SELECT nama FROM users WHERE id = ?"; 
                            if ($stmt_v = $conn->prepare($sql_v)) {
                                $stmt_v->bind_param("i", $v_id);
                                $stmt_v->execute();
                                $res_v = $stmt_v->get_result();
                                if ($res_v && $res_v->num_rows > 0) {
                                    $v_row = $res_v->fetch_assoc();
                                    $verifikator_name = htmlspecialchars($v_row['nama']);
                                }
                                $stmt_v->close();
                            }
                        }
                    } else {
                        $error_message = "Data pendaftaran dengan ID #{$registration_id} tidak ditemukan.";
                    }
                    $stmt->close();
                }
            }

            $edit_link = "edit_isian.php?id=" . $registration_id;

            // --- FUNGSI UTILITY ---
            function get_data_value($data, $key) {
                if (is_array($data) && array_key_exists($key, $data)) {
                    $value = $data[$key];
                    if ($value === null || $value === '') return '-';
                    return htmlspecialchars((string)$value); 
                }
                return '-'; 
            }

            function get_doc_isian_row($data, $label, $file_column_name, $status_column_name, $edit_link_base) {
                if ($data === null) return "<tr><td colspan='4'>Data tidak tersedia.</td></tr>";
                
                $file_name = get_data_value($data, $file_column_name);
                $status_verif = strtoupper(get_data_value($data, $status_column_name)); 

                $full_path = ($file_name !== '-') ? '/uploads/perpindahan/' . $file_name : '#';
                
                $is_tidak_sesuai = false;
                $status_display = 'Menunggu Verifikasi';
                $status_class = 'status-menunggu';

                if ($status_verif === 'SESUAI' || $status_verif === 'YA') {
                    $status_display = 'YA (SESUAI)'; $status_class = 'status-ya'; 
                } else if ($status_verif === 'TIDAK SESUAI' || $status_verif === 'TIDAK') {
                    $status_display = 'TIDAK SESUAI'; $status_class = 'status-tidak'; $is_tidak_sesuai = true; 
                } else if ($file_name === '-') {
                    $status_display = 'BELUM DIUNGGAH'; $status_class = 'status-belum'; 
                }

                $file_link = ($file_name !== '-') ? "<a href='{$full_path}' target='_blank' class='file-name'><i class='fas fa-file-pdf'></i> Lihat File</a>" : 'Belum Diunggah';

                $action_button = $is_tidak_sesuai 
                    ? "<a href='{$edit_link_base}#{$file_column_name}' class='btn-action btn-perbaiki'>Perbaiki <i class=\"fas fa-wrench\"></i></a>"
                    : "<button type='button' class='btn-action btn-perbaiki btn-disabled' disabled>Perbaiki <i class=\"fas fa-wrench\"></i></button>";

                return "<tr>
                            <td class='doc-label'>{$label}</td>
                            <td class='doc-file'>{$file_link}</td>
                            <td class='doc-current-status'><span class=\"status-badge {$status_class}\">{$status_display}</span></td>
                            <td class='doc-action'>{$action_button}</td>
                        </tr>";
            }
            ?>

            <style>
                @import url('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css');
                :root{ --panel:#fff; --muted:#6b7280; --accent-green:#028a0f; --card-shadow: 0 1px 3px rgba(0,0,0,.1); --radius:8px; --field-border:#e2e8f0; --link:#028a0f; --danger: #dc2626; --success: #198754; --warning: #ffc107; }
                .header-card, .card { background: var(--panel); border-radius: var(--radius); box-shadow: var(--card-shadow); padding: 20px; margin-bottom: 20px; border: 1px solid #dee2e6; }
                .header-top {font-size:14px;color:var(--muted);margin-bottom:6px; display: flex; align-items: center; gap: 10px;}
                .header-title {font-size:22px;font-weight:700;color:#0b1720;margin:0;}
                .section-title { font-size:18px;font-weight:600;color:#0b1720;margin:30px 0 15px; border-bottom:2px solid var(--field-border);padding-bottom:8px; display: flex; align-items: center; gap: 8px; }
                .section-title i { color: var(--accent-green); }
                table {width:100%;border-collapse:collapse;margin-bottom:20px;}
                td.label { width:30%;padding:12px 15px;background:#f8f9fa; border:1px solid var(--field-border);font-weight:600;font-size:14px;color:#343a40;}
                td.value { border:1px solid var(--field-border);padding:12px 15px;font-size:14px;word-break: break-all; }
                .doc-table th, .doc-table td { padding:12px 15px; border:1px solid var(--field-border); vertical-align:middle; font-size:14px;}
                .doc-table th { background:#e9ecef; font-weight:600; text-align:left;}
                .status-badge { display:inline-block;padding:5px 8px;border-radius:4px;font-size:11px;font-weight:600;min-width:110px;text-align:center;text-transform:uppercase;}
                .status-ya {background:#d4edda;color:var(--success) !important; border: 1px solid #c3e6cb;}
                .status-tidak {background:#f8d7da;color:var(--danger) !important; border: 1px solid #f5c6cb;}
                .status-menunggu {background:#fff3cd;color:#856404 !important; border: 1px solid #ffeeba;}
                .status-belum {background:#e2e6ea;color:#495057 !important; border: 1px solid #dae0e5;}
                .btn-action { padding: 6px 10px; border-radius: 4px; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; text-decoration: none; border: 1px solid transparent;}
                .btn-perbaiki { background: #ffc107; color: #212529; border-color: #ffc107;}
                .btn-disabled { background: #e9ecef !important; color: #adb5bd !important; cursor: not-allowed !important;}
                .btn-primary { background:#007bff; color:#fff; padding:10px 20px; border-radius:4px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 8px;}
                .header-card-content { display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; }
                
                /* --- STYLE BOX CATATAN ALA DETAIL_VERIF --- */
                .note-box { border-radius: 6px; padding: 15px; margin-bottom: 15px; border-left: 5px solid #ccc; min-height: 120px; }
                .note-box-verifikator { background-color: #f0f7ff; border: 1px solid #cfe2ff; border-left: 5px solid #0d6efd; }
                .note-box-kasubdit { background-color: #fff9f0; border: 1px solid #ffe5b4; border-left: 5px solid #fd7e14; }
                .note-box-ppsdm { background-color: #fff5f5; border: 1px solid #feb2b2; border-left: 5px solid #dc3545; }
                
                .note-header { font-weight: bold; font-size: 0.95rem; margin-bottom: 8px; display: flex; align-items: center; gap: 8px; }
                .note-content { font-size: 0.88rem; line-height: 1.5; color: #444; font-weight: 1000; }
                .note-empty { font-style: italic; color: #999; font-weight: normal; }
            </style>

            <div class="row">
                <div class="col-md-12">
                    <div class="card header-card">
                        <?php 
                        $status_akhir_raw = $data ? get_data_value($data, 'status_pengajuan') : 'BELUM DIISI';
                        $status_akhir = strtoupper($status_akhir_raw);
                        $status_class_header = 'status-belum';
                        
                        if (in_array($status_akhir, ['FINAL', 'SESUAI', 'YA', 'DISETUJUI'])) {
                            $status_class_header = 'status-ya';
                            $status_display_header = 'FINAL / DISETUJUI';
                        } else if (in_array($status_akhir, ['PERLU PERBAIKAN', 'TIDAK SESUAI', 'TIDAK'])) {
                            $status_class_header = 'status-tidak';
                            $status_display_header = 'PERLU PERBAIKAN';
                        } else {
                            $status_class_header = 'status-menunggu';
                            $status_display_header = $status_akhir_raw;
                        }
                        ?>
                        <div class="header-card-content">
                            <div class="header-card-info">
                                <div class="header-top">Status Pendaftaran: <span class="status-badge <?php echo $status_class_header; ?>"><?php echo $status_display_header; ?></span></div>
                                <h1 class="header-title">Detail Isian Peserta: <?php echo $data ? get_data_value($data, 'nama') : 'Data Tidak Ditemukan'; ?></h1>
                                <small class="text-muted">ID Pendaftaran: #<?php echo $registration_id; ?> | Verifikator: <strong><?= $verifikator_name ?: 'Belum Ditentukan'; ?></strong></small>
                            </div>
                            <?php if ($data && $is_pengusul): ?>
                                <a href="<?php echo $edit_link; ?>" class="btn-primary"><i class="fas fa-edit"></i> Edit Data Utama</a>
                            <?php endif; ?>
                        </div>

                        <div class="row mt-4">
                            <div class="col-md-4">
                                <div class="note-box note-box-verifikator">
                                    <div class="note-header text-primary">
                                        <i class="fas fa-history"></i> Catatan dari Verifikator
                                    </div>
                                    <div class="note-content">
                                        <?php 
                                            $note_eval = filter_catatan_untuk_pengusul(get_data_value($data, 'catatan_evaluasi'), $user_role_sesi);
                                            echo (!empty($note_eval) && $note_eval !== '-') ? nl2br($note_eval) : '<span class="note-empty">Tidak ada catatan perbaikan.</span>'; 
                                        ?>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="note-box note-box-kasubdit">
                                    <div class="note-header text-warning">
                                        <i class="fas fa-user-shield"></i> Catatan dari Kasubdit
                                    </div>
                                    <div class="note-content">
                                        <?php 
                                            $note_internal_eval = get_data_value($data, 'catatan_evaluator');
                                            echo (!empty($note_internal_eval) && $note_internal_eval !== '-') ? nl2br($note_internal_eval) : '<span class="note-empty">Tidak ada catatan kasubdit.</span>'; 
                                        ?>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="note-box note-box-ppsdm">
                                    <div class="note-header text-danger">
                                        <i class="fas fa-shield-alt"></i> Catatan dari PPSDM
                                    </div>
                                    <div class="note-content">
                                        <?php 
                                            $note_ppsdm = get_data_value($data, 'catatan_ppsdm');
                                            echo (!empty($note_ppsdm) && $note_ppsdm !== '-') ? nl2br($note_ppsdm) : '<span class="note-empty">Tidak ada catatan PPSDM.</span>'; 
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if ($error_message || !$data): ?>
                        <div class="card" style="border-left: 5px solid var(--danger);">
                            <h3 style="color:var(--danger);">Error Memuat Data</h3>
                            <p><?= $error_message ?></p>
                            <button type="button" class="btn-default" onclick="window.history.back()">Kembali</button>
                        </div>
                    <?php else: ?>

                    <div class="card">
                        <h2 class="section-title"><i class="fas fa-user-alt"></i> Biodata Peserta</h2>
                        <table>
                            <tr><td class="label">Nama Lengkap</td><td class="value"><?= get_data_value($data, 'nama') ?></td></tr>
                            <tr><td class="label">NIP</td><td class="value"><?= get_data_value($data, 'nip') ?></td></tr>
                            <tr><td class="label">No. HP</td><td class="value"><?= get_data_value($data, 'hp') ?></td></tr>
                            <tr><td class="label">Email</td><td class="value"><?= get_data_value($data, 'email') ?></td></tr>
                            <tr><td class="label">Jabatan Saat Ini</td><td class="value"><?= get_data_value($data, 'jabatan_saat_ini') ?></td></tr>
                            <tr><td class="label">JF PKP Yang Dituju</td><td class="value"><?= get_data_value($data, 'jf_pkp_tujuan') ?></td></tr>
                            <tr><td class="label">Pangkat / Golongan</td><td class="value"><?= get_data_value($data, 'pangkat') ?></td></tr>
                            <tr><td class="label">TMT Pangkat/Golongan</td><td class="value"><?= get_data_value($data, 'tmt_pangkat') ?></td></tr>
                            <tr><td class="label">Jenjang Pendidikan Terakhir</td><td class="value"><?= get_data_value($data, 'jenjang_pendidikan') ?></td></tr>
                            <tr><td class="label">Program Studi</td><td class="value"><?= get_data_value($data, 'program_studi') ?></td></tr>
                        </table>

                        <h2 class="section-title"><i class="fas fa-building"></i> Data Instansi</h2>
                        <table>
                            <tr><td class="label">Nama Instansi</td><td class="value"><?= get_data_value($data, 'instansi') ?></td></tr>
                            <tr><td class="label">Instansi Daerah</td><td class="value"><?= get_data_value($data, 'unit_daerah') ?></td></tr>
                            <tr><td class="label">Nama Unit Organisasi</td><td class="value"><?= get_data_value($data, 'unit_organisasi') ?></td></tr>
                            <tr><td class="label">Unit Kerja Saat Ini</td><td class="value"><?= get_data_value($data, 'unit_saat_ini') ?></td></tr>
                            <tr><td class="label">Unit Kerja Sebelumnya</td><td class="value"><?= get_data_value($data, 'unit_sebelumnya') ?></td></tr>
                        </table>

                        <h2 class="section-title"><i class="fas fa-folder-open"></i> Dokumen Terunggah dan Status Verifikasi (13 Dokumen)</h2>
                        <table class="doc-table">
                            <thead>
                                <tr>
                                    <th>Jenis Berkas</th>
                                    <th>Link File</th>
                                    <th style="text-align:center;">Status Verifikasi</th>
                                    <th style="text-align:center;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                echo get_doc_isian_row($data, '1. Surat Usulan Perpindahan Jabatan', 'file_surat_usulan_perpindahan', 'd1_surat_usulan_status', $edit_link);
                                echo get_doc_isian_row($data, '2. Dokumen Penetapan Kebutuhan JF', 'file_dokumen_penetapan', 'd2_rekomendasi_formasi_status', $edit_link);
                                echo get_doc_isian_row($data, '3. Surat Usulan Uji Kompetensi', 'file_surat_usulan_uji', 'd3_usulan_ujikom_status', $edit_link);
                                echo get_doc_isian_row($data, '4. Dokumen Portofolio', 'file_portofolio', 'd4_portofolio_status', $edit_link);
                                echo get_doc_isian_row($data, '5. Suket Sehat dari Dokter Pemerintah (Puskesmas, RSUD, dll)', 'file_sk_cpns_pns', 'd5_sk_cpns_pns_status', $edit_link);
                                echo get_doc_isian_row($data, '6. Salinan SK Pangkat Terakhir', 'file_sk_pangkat', 'd6_sk_pangkat_status', $edit_link);
                                echo get_doc_isian_row($data, '7. Salinan SK Jabatan Terakhir', 'file_sk_jabatan', 'd7_sk_jabatan_status', $edit_link);
                                echo get_doc_isian_row($data, '8. Nilai SKP/PPK 2 Tahun Terakhir', 'file_skp', 'd8_nilai_skp_status', $edit_link);
                                echo get_doc_isian_row($data, '9. Salinan Ijazah dan Transkrip', 'file_ijazah_transkrip', 'd9_ijazah_transkrip_status', $edit_link);
                                echo get_doc_isian_row($data, '10. Pernyataan Integritas', 'file_pernyataan_integritas', 'd10_integritas_status', $edit_link);
                                echo get_doc_isian_row($data, '11. Pernyataan Bersedia', 'file_pernyataan_bersedia', 'd11_bersedia_status', $edit_link);
                                echo get_doc_isian_row($data, '12. Pernyataan Pengalaman 2 Tahun', 'file_pernyataan_pengalaman', 'd12_pengalaman_2th_status', $edit_link);
                                echo get_doc_isian_row($data, '13. Rencana Penempatan', 'file_rencana_penempatan', 'd13_rencana_penempatan_status', $edit_link);
                                ?>
                            </tbody>
                        </table>
                        
                        <div class="actions" style="text-align: right; margin-top: 20px;">
                            <?php if($is_pengusul): ?>
                            <a href="<?= $edit_link ?>" class="btn-primary" style="margin-left:10px;"><i class="fas fa-edit"></i> Edit Data Utama</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
</div>

<?php
include $template_path . 'footer.php';
if (isset($conn) && $conn->ping()) { $conn->close(); }
?>