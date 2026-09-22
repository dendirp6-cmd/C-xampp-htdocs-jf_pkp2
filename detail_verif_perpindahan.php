<?php
// =========================================================
// FILE: detail_verif_perpindahan.php
// UPDATE: Fix Notifikasi (Hanya reset is_read_notif jika ada catatan baru)
// KETENTUAN: Menjaga Struktur, Fungsi, dan Tampilan Asli 100%
// =========================================================
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'auth_guard.php'; 
require_once 'koneksi.php'; 

// --- PENGGUNAAN DATA SESSION ---
$user_email_sesi = $_SESSION['user_email_sesi'] ?? $_SESSION['email'] ?? $_SESSION['Email'] ?? '';
$user_nama_sesi  = $_SESSION['user_nama_sesi'] ?? $_SESSION['nama'] ?? 'Pengguna JF';
$user_id_sesi    = $_SESSION['user_id_sesi'] ?? 0;
$user_role_sesi  = strtoupper($_SESSION['user_role_sesi'] ?? '');

// Identifikasi Role
$is_pengusul    = (strpos($user_role_sesi, 'PENGUSUL') !== false);
$is_ppsdm       = (strpos($user_role_sesi, 'PPSDM') !== false);
$is_verifikator = (strpos($user_role_sesi, 'VERIFIKATOR') !== false);
$is_evaluator   = (strpos($user_role_sesi, 'EVALUATOR') !== false);
$is_kasubdit    = (strpos($user_role_sesi, 'KASUBDIT') !== false);
$is_direktur    = (strpos($user_role_sesi, 'DIREKTUR') !== false);

$can_edit_status = ($is_verifikator || $is_ppsdm || $is_evaluator) && !$is_kasubdit && !$is_direktur;

$role_pengirim = "SISTEM";
if ($is_ppsdm) $role_pengirim = "PPSDM";
elseif ($is_direktur) $role_pengirim = "DIREKTUR";
elseif ($is_kasubdit) $role_pengirim = "KASUBDIT";
elseif ($is_evaluator) $role_pengirim = "EVALUATOR";
elseif ($is_verifikator) $role_pengirim = "VERIFIKATOR";
elseif ($is_pengusul) $role_pengirim = "PENGUSUL";

$UPLOAD_DIR_WEB_BASE = '/uploads/perpindahan/'; 
$NAMA_TABEL_PENGAJUAN = "pengajuan_ujikom"; 

$page_title = 'Verifikasi Peserta Perpindahan Jabatan';
$pengajuan_id = $_GET['id'] ?? null; 
$safe_id = mysqli_real_escape_string($conn, $pengajuan_id); 

$sql_cek = "SELECT p.*, v.nama as nama_verifikator_disposisi 
            FROM {$NAMA_TABEL_PENGAJUAN} p
            LEFT JOIN users v ON p.verifikator_id = v.id 
            WHERE p.id = '{$safe_id}'";
$res_cek = mysqli_query($conn, $sql_cek);
$dokumen_paths = mysqli_fetch_assoc($res_cek);
$pegawai_data = ($dokumen_paths) ? true : false;

$target_verifikator_label = "VERIFIKATOR";
$display_verifikator_name = "Belum Ditentukan"; 

if (!empty($dokumen_paths['nama_verifikator_disposisi'])) {
    $nama_v = strtoupper($dokumen_paths['nama_verifikator_disposisi']);
    $display_verifikator_name = $dokumen_paths['nama_verifikator_disposisi'];
    if (strpos($nama_v, '1') !== false) $target_verifikator_label = "VERIFIKATOR 1";
    elseif (strpos($nama_v, '2') !== false) $target_verifikator_label = "VERIFIKATOR 2";
}

$success_redirect_list = false;
$redirect_message = '';

$target_url = 'index_direktur.php';
if ($is_verifikator) $target_url = 'index_verifikator.php';
elseif ($is_ppsdm) $target_url = 'index_ppsdm.php';
elseif ($is_kasubdit) $target_url = 'index_kasubdit.php';
elseif ($is_pengusul) $target_url = 'index_pengusul.php';

$persyaratan_umum = [
    'p1_pns' => 'Pegawai berstatus Pegawai Negeri Sisil (PNS)',
    'p2_pendidikan' => 'Pendidikan terakhir pegawai minimal S-1/D4',
    'p3_bidang' => 'Pendidikan sesuai dengan bidang yang dibutuhkan',
    'p4_pengalaman' => 'Pengalaman kerja bidang Perumahan minimal 2 tahun',
    'p5_usia' => 'Usia pegawai memenuhi batas maksimal',
];

$dokumen_persyaratan = [
    'd1_surat_usulan'         => ['label' => 'Surat Usulan Perpindahan Jabatan', 'kolom' => 'file_surat_usulan_perpindahan'],
    'd2_rekomendasi_formasi' => ['label' => 'Dokumen Penetapan Kebutuhan JF', 'kolom' => 'file_dokumen_penetapan'],
    'd3_usulan_ujikom'        => ['label' => 'Surat Usulan Uji Kompetensi', 'kolom' => 'file_surat_usulan_uji'],
    'd4_portofolio'           => ['label' => 'Daftar Riwayat Hidup (DRH) / Portofolio', 'kolom' => 'file_portofolio'],
    'd5_sk_cpns_pns'          => ['label' => 'Surat Keterangan Sehat dari Dokter Pemerintah (Puskesmas, RSUD, dll)', 'kolom' => 'file_sk_cpns_pns'], 
    'd6_sk_pangkat'           => ['label' => 'Salinan SK Pangkat/Golongan Terakhir', 'kolom' => 'file_sk_pangkat'],
    'd7_sk_jabatan'           => ['label' => 'Salinan SK Jabatan Terakhir', 'kolom' => 'file_sk_jabatan'],
    'd8_nilai_skp'            => ['label' => 'Salinan SKP 2 Tahun Terakhir', 'kolom' => 'file_skp'], 
    'd9_ijazah_transkrip'     => ['label' => 'Salinan Ijazah dan Transkrip Nilai', 'kolom' => 'file_ijazah_transkrip'], 
    'd10_integritas'          => ['label' => 'Surat Pernyataan Integritas/Moralitas', 'kolom' => 'file_pernyataan_integritas'],
    'd11_bersedia'            => ['label' => 'Surat Pernyataan Bersedia Diangkat dalam JF', 'kolom' => 'file_pernyataan_bersedia'],
    'd12_pengalaman_2th'      => ['label' => 'Surat Pernyataan Memiliki Pengalaman Jabatan', 'kolom' => 'file_pernyataan_pengalaman'], 
    'd13_rencana_penempatan' => ['label' => 'Rencana Penempatan PNS', 'kolom' => 'file_rencana_penempatan'],
];

function filter_display_catatan($raw_text) {
    if (empty($raw_text) || $raw_text === '-') return $raw_text;
    return preg_replace('/\[.*?➔.*?\]\s+/', '', $raw_text);
}

// --- LOGIKA 1: SIMPAN CATATAN MANUAL (POPUP) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_catatan_popup'])) {
    $catatan_isi = mysqli_real_escape_string($conn, $_POST['isi_catatan']);
    $kolom_tujuan = mysqli_real_escape_string($conn, $_POST['target_kolom']);
    
    // reset is_read_notif hanya jika catatan tidak kosong
    $add_sql = (!empty(trim($catatan_isi)) && $catatan_isi !== '-') ? ", is_read_notif = 0" : "";
    $sql_note = "UPDATE {$NAMA_TABEL_PENGAJUAN} SET {$kolom_tujuan} = '{$catatan_isi}' {$add_sql} WHERE id = '{$safe_id}'";
    if (mysqli_query($conn, $sql_note)) {
        $success_redirect_list = true;
        $redirect_message = "Catatan berhasil diperbarui.";
        $target_url = "detail_verif_perpindahan.php?id=" . $pengajuan_id; 
    }
}

// --- LOGIKA 2: SIMPAN VERIFIKASI UTAMA ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_verifikasi'])) {
    $all_passed = true; 
    $sesuai_count = 0;
    $update_fields = [];
    $new_notes = [];
    $semua_keys_post = array_merge(array_keys($persyaratan_umum), array_keys($dokumen_persyaratan));

    foreach ($semua_keys_post as $key) {
        if (!$can_edit_status) {
            $status_db = $dokumen_paths["{$key}_status"] ?? 'Tidak';
            $post_val = ($status_db === 'Ya' || $status_db === 'Sesuai') ? 'Sesuai' : 'Tidak Sesuai';
        } else {
            $post_val = $_POST[$key] ?? 'Pilih';
        }

        $db_val = ($post_val === 'Sesuai') ? 'Ya' : (($post_val === 'Tidak Sesuai') ? 'Tidak' : 'Menunggu Verifikasi');

        $isi_catatan = trim($_POST[$key.'_catatan'] ?? '');
        $tujuan = $_POST[$key.'_tujuan'] ?? '';

        if (!empty($isi_catatan)) {
            $label_item = $persyaratan_umum[$key] ?? ($dokumen_persyaratan[$key]['label'] ?? $key);
            $target_display = (!empty($tujuan)) ? $tujuan : "PENGUSUL";
            $timestamp = date('d/m/H:i');
            $new_notes[] = "• [$role_pengirim ➔ $target_display] ($timestamp) | $label_item: $isi_catatan";
        }

        if ($post_val === 'Sesuai') $sesuai_count++;
        else $all_passed = false;
        
        if ($can_edit_status) {
            $update_fields[] = "{$key}_status = '$db_val'";
        }
    }
    
    // Simpan Catatan & HANYA RESET NOTIF JIKA ADA CATATAN BARU
    if (!empty($new_notes)) {
        $string_catatan_baru = mysqli_real_escape_string($conn, implode("\n", $new_notes));
        if ($is_verifikator) $update_fields[] = "catatan_evaluasi = CONCAT(IFNULL(catatan_evaluasi,''), '\n', '$string_catatan_baru')";
        elseif ($is_evaluator || $is_direktur) $update_fields[] = "catatan_evaluator = CONCAT(IFNULL(catatan_evaluator,''), '\n', '$string_catatan_baru')";
        elseif ($is_ppsdm) $update_fields[] = "catatan_ppsdm = CONCAT(IFNULL(catatan_ppsdm,''), '\n', '$string_catatan_baru')";
        elseif ($is_kasubdit) $update_fields[] = "catatan_evaluator = CONCAT(IFNULL(catatan_evaluator,''), '\n', '$string_catatan_baru')";
        
        // KRUSIAL: Notifikasi hanya menyala jika ada catatan tertulis
        $update_fields[] = "is_read_notif = 0";
    }

    if ($can_edit_status) {
        $progres = number_format(($sesuai_count / count($semua_keys_post)) * 100, 2, '.', '');
        if ($all_passed) {
            if ($is_ppsdm) $final_status = 'Menunggu Jadwal Ujikom';
            elseif ($is_verifikator) $final_status = 'Disetujui Verifikator';
            else $final_status = 'Disetujui';
        } else {
            $final_status = 'Perlu Perbaikan';
        }
        $update_fields[] = "progres_kelengkapan = '$progres'";
        $update_fields[] = "status_pengajuan = '$final_status'";
        // is_read_notif = 0 dihapus dari sini agar perubahan status murni tidak memicu notifikasi catatan
    }

    if (!empty($update_fields)) {
        $sql_update = "UPDATE {$NAMA_TABEL_PENGAJUAN} SET " . implode(', ', $update_fields) . " WHERE id = '{$safe_id}'";
        if (mysqli_query($conn, $sql_update)) {
            $success_redirect_list = true;
            $redirect_message = "Data berhasil disimpan.";
        }
    } else {
        $success_redirect_list = true; 
        $redirect_message = "Tidak ada perubahan data.";
    }
}

// Sinkronisasi data verifikasi untuk tampilan
$verifikasi_data = [];
if ($pegawai_data) {
    foreach (array_merge(array_keys($persyaratan_umum), array_keys($dokumen_persyaratan)) as $key) {
        $status_db = $dokumen_paths["{$key}_status"] ?? '';
        $verifikasi_data[$key] = ($status_db === 'Ya' || $status_db === 'Sesuai') ? 'Sesuai' : (($status_db === 'Tidak' || $status_db === 'Tidak Sesuai') ? 'Tidak Sesuai' : 'Pilih');
    }
}

function get_file_info($key, $dokumen_paths, $dokumen_persyaratan, $UPLOAD_DIR_WEB_BASE) {
    $col = $dokumen_persyaratan[$key]['kolom'];
    $file = $dokumen_paths[$col] ?? '';
    return !empty($file) ? ['url' => $UPLOAD_DIR_WEB_BASE . $file, 'class' => 'btn-primary', 'text' => 'Lihat'] : ['url' => '#', 'class' => 'btn-secondary disabled', 'text' => 'N/A'];
}

require_once 'template/header.php';
require_once 'template/navbar.php';
require_once 'template/sidebar.php';
?>

<style>
    :root { --green: #10b981; --red: #ef4444; --blue: #3b82f6; }
    .summary-box { background: #fff; padding: 15px; border-radius: 8px; border: 1px solid #ddd; margin-bottom: 20px; display: flex; justify-content: space-around; align-items: center; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
    .status-badge { font-weight: 700; padding: 5px 15px; border-radius: 20px; color: #fff; text-transform: uppercase; font-size: 0.85rem; }
    .select-verif { width: 100%; padding: 5px; border-radius: 4px; border: 1px solid #ccc; font-weight: 600; cursor: pointer; }
    .select-verif:disabled { background-color: #f8f9fa; cursor: not-allowed; opacity: 0.8; }
    .select-tujuan { font-size: 0.75rem; width: 100%; padding: 5px; border-radius: 4px; border: 1px dotted #999; margin-bottom: 3px; background: #fafafa; }
    .input-catatan { font-size: 0.85rem; padding: 5px; border-radius: 4px; border: 1px solid #ddd; width: 100%; }
    .log-container { max-height: 250px; overflow-y: auto; background: #fffbe6; border: 1px solid #ffe58f; padding: 10px; border-radius: 5px; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 0.9rem; line-height: 1.5; color: #333; font-weight: bold; }
    .label-log-box { font-weight: bold; display: block; margin-bottom: 5px; }
</style>

<div class="content-wrapper">
    <section class="content-header"><div class="container-fluid"><h1><i class="fas fa-user-check text-primary"></i> <?php echo $page_title; ?></h1></div></section>
    <section class="content">
        <div class="container-fluid">
            <?php if ($pegawai_data): ?>
            <div class="card card-widget widget-user-2 shadow-sm">
                <div class="widget-user-header bg-white">
                    <div class="float-right text-right">
                        <div class="d-inline-block mr-3">
                            <span class="text-muted small">Verifikator:</span><br>
                            <span class="badge bg-info px-3 py-2" style="font-size: 1rem;"><i class="fas fa-user-shield mr-1"></i> <?php echo htmlspecialchars($display_verifikator_name); ?></span>
                        </div>
                        <div class="d-inline-block">
                            <span class="text-muted small">Status Pengajuan:</span><br>
                            <span class="badge bg-primary px-3 py-2" style="font-size: 1rem;"><?php echo $dokumen_paths['status_pengajuan']; ?></span>
                        </div>
                    </div>
                    <h3 class="widget-user-username" style="margin-left:0; font-weight:bold;"><?php echo htmlspecialchars($dokumen_paths['nama'] ?? ''); ?></h3>
                    <h5 class="widget-user-desc" style="margin-left:0;">NIP. <?php echo htmlspecialchars($dokumen_paths['nip'] ?? ''); ?> | <i class="fas fa-phone"></i> <?php echo htmlspecialchars($dokumen_paths['hp'] ?? ''); ?></h5>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="row mb-4" id="section-catatan-log">
                        <div class="col-md-4">
                            <label class="label-log-box text-info"><i class="fas fa-history"></i> Catatan dari Verifikator</label>
                            <div class="log-container"><?php echo nl2br(filter_display_catatan($dokumen_paths['catatan_evaluasi'] ?: 'Belum ada catatan perbaikan.')); ?></div>
                        </div>
                        <div class="col-md-4">
                            <label class="label-log-box text-warning"><i class="fas fa-user-shield"></i> Catatan dari Kasubdit</label>
                            <div class="log-container"><?php echo nl2br(filter_display_catatan($dokumen_paths['catatan_evaluator'] ?: 'Belum ada diskusi.')); ?></div>
                        </div>
                        <div class="col-md-4">
                            <label class="label-log-box text-danger"><i class="fas fa-shield-alt"></i> Catatan dari PPSDM</label>
                            <div class="log-container"><?php echo nl2br(filter_display_catatan($dokumen_paths['catatan_ppsdm'] ?: 'Belum ada catatan PPSDM.')); ?></div>
                        </div>
                    </div>

                    <form method="POST">
                        <div class="summary-box">
                            <div><i class="fas fa-chart-pie"></i> Progres: <strong><?php echo $dokumen_paths['progres_kelengkapan']; ?>%</strong></div>
                            <div>Hasil: <span id="summary-result" class="status-badge bg-secondary">...</span></div>    
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered table-sm">
                                <thead class="bg-navy text-center">
                                    <tr>
                                        <th width="40">#</th><th>Persyaratan Umum</th><th width="140">Status</th><th width="450">Input Catatan & Tujuan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $n=1; foreach($persyaratan_umum as $k => $v): ?>
                                    <tr>
                                        <td class="text-center"><?php echo $n++; ?></td>
                                        <td><?php echo $v; ?></td>
                                        <td>
                                            <select name="<?php echo $k; ?>" class="select-verif" <?php echo (!$can_edit_status ? 'disabled' : ''); ?>>
                                                <option value="Pilih" <?php echo ($verifikasi_data[$k]=='Pilih'?'selected':''); ?>>- Pilih -</option>
                                                <option value="Sesuai" <?php echo ($verifikasi_data[$k]=='Sesuai'?'selected':''); ?>>Sesuai</option>
                                                <option value="Tidak Sesuai" <?php echo ($verifikasi_data[$k]=='Tidak Sesuai'?'selected':''); ?>>Tidak Sesuai</option>
                                            </select>
                                        </td>
                                        <td>
                                            <div class="row no-gutters">
                                                <div class="col-4 pr-1">
                                                    <select name="<?php echo $k; ?>_tujuan" class="select-tujuan" <?php echo ($is_pengusul ? 'disabled' : ''); ?>>
                                                        <?php if ($is_kasubdit || $is_direktur): ?>
                                                            <option value="<?php echo $target_verifikator_label; ?>">➔ <?php echo $target_verifikator_label; ?></option>
                                                            <option value="PPSDM">➔ PPSDM</option>
                                                        <?php elseif ($is_ppsdm): ?>
                                                            <option value="PENGUSUL">➔ Pengusul</option>
                                                            <option value="<?php echo $target_verifikator_label; ?>">➔ <?php echo $target_verifikator_label; ?></option>
                                                        <?php elseif ($is_verifikator): ?>
                                                            <option value="PENGUSUL">➔ Pengusul</option>
                                                            <option value="KASUBDIT">➔ Kasubdit</option>
                                                            <option value="DIREKTUR">➔ Direktur</option>
                                                        <?php else: ?>
                                                            <option value="">- N/A -</option>
                                                        <?php endif; ?>
                                                    </select>
                                                </div>
                                                <div class="col-8">
                                                    <input type="text" name="<?php echo $k; ?>_catatan" class="input-catatan" placeholder="<?php echo ($is_pengusul ? 'Hanya baca' : 'Tambahkan catatan...'); ?>" <?php echo ($is_pengusul ? 'disabled' : ''); ?>>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="table-responsive mt-4">
                            <table class="table table-bordered table-sm">
                                <thead class="bg-navy text-center">
                                    <tr>
                                        <th width="40">#</th><th>Dokumen Fisik</th><th width="80">File</th><th width="140">Status</th><th width="450">Input Catatan & Tujuan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $n=1; foreach($dokumen_persyaratan as $k => $i): $f = get_file_info($k, $dokumen_paths, $dokumen_persyaratan, $UPLOAD_DIR_WEB_BASE); ?>
                                    <tr>
                                        <td class="text-center"><?php echo $n++; ?></td>
                                        <td><?php echo $i['label']; ?></td>
                                        <td class="text-center"><a href="<?php echo $f['url']; ?>" target="_blank" class="btn btn-xs <?php echo $f['class']; ?>"><i class="fas fa-eye"></i></a></td>
                                        <td>
                                            <select name="<?php echo $k; ?>" class="select-verif" <?php echo (!$can_edit_status ? 'disabled' : ''); ?>>
                                                <option value="Pilih" <?php echo ($verifikasi_data[$k]=='Pilih'?'selected':''); ?>>- Pilih -</option>
                                                <option value="Sesuai" <?php echo ($verifikasi_data[$k]=='Sesuai'?'selected':''); ?>>Sesuai</option>
                                                <option value="Tidak Sesuai" <?php echo ($verifikasi_data[$k]=='Tidak Sesuai'?'selected':''); ?>>Tidak Sesuai</option>
                                            </select>
                                        </td>
                                        <td>
                                            <div class="row no-gutters">
                                                <div class="col-4 pr-1">
                                                    <select name="<?php echo $k; ?>_tujuan" class="select-tujuan" <?php echo ($is_pengusul ? 'disabled' : ''); ?>>
                                                        <?php if ($is_kasubdit || $is_direktur): ?>
                                                            <option value="<?php echo $target_verifikator_label; ?>">➔ <?php echo $target_verifikator_label; ?></option>
                                                            <option value="PPSDM">➔ PPSDM</option>
                                                        <?php elseif ($is_ppsdm): ?>
                                                            <option value="PENGUSUL">➔ Pengusul</option>
                                                            <option value="<?php echo $target_verifikator_label; ?>">➔ <?php echo $target_verifikator_label; ?></option>
                                                        <?php elseif ($is_verifikator): ?>
                                                            <option value="PENGUSUL">➔ Pengusul</option>
                                                            <option value="KASUBDIT">➔ Kasubdit</option>
                                                            <option value="DIREKTUR">➔ Direktur</option>
                                                        <?php else: ?>
                                                            <option value="">- N/A -</option>
                                                        <?php endif; ?>
                                                    </select>
                                                </div>
                                                <div class="col-8">
                                                    <input type="text" name="<?php echo $k; ?>_catatan" class="input-catatan" placeholder="<?php echo ($is_pengusul ? 'Hanya baca' : 'Tambahkan catatan...'); ?>" <?php echo ($is_pengusul ? 'disabled' : ''); ?>>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if (!$is_pengusul): ?>
                        <button type="submit" name="submit_verifikasi" class="btn btn-success btn-lg btn-block mt-4 shadow"><i class="fas fa-save"></i> SIMPAN PERUBAHAN & CATATAN</button>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<div class="modal fade" id="popVerif" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header bg-info"><h5>Catatan Instruksi Perbaikan</h5></div>
            <div class="modal-body">
                <input type="hidden" name="target_kolom" value="catatan_evaluasi">
                <textarea name="isi_catatan" class="form-control" rows="5" placeholder="Instruksi umum untuk pengusul..." <?php echo ($is_pengusul ? 'readonly' : ''); ?>><?php echo $dokumen_paths['catatan_evaluasi'] ?? ''; ?></textarea>
            </div>
            <?php if (!$is_pengusul): ?>
            <div class="modal-footer"><button type="submit" name="submit_catatan_popup" class="btn btn-primary">Update Catatan</button></div>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php require_once 'template/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const catatanSection = document.getElementById('section-catatan-log');
    if (catatanSection) {
        catatanSection.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    const selects = document.querySelectorAll('.select-verif');
    const res = document.getElementById('summary-result');
    
    function updateColors() {
        let ok = 0;
        selects.forEach(s => {
            if(s.value === 'Sesuai') {
                s.style.backgroundColor = '#d1fae5'; s.style.color = '#065f46'; s.style.borderColor = '#10b981';
                ok++;
            } else if(s.value === 'Tidak Sesuai') {
                s.style.backgroundColor = '#fee2e2'; s.style.color = '#991b1b'; s.style.borderColor = '#ef4444';
            } else {
                s.style.backgroundColor = '#fff'; s.style.color = '#000'; s.style.borderColor = '#ccc';
            }
        });
        if(res) {
            if(ok === selects.length) { res.textContent = 'LENGKAP'; res.className = 'status-badge bg-success'; }
            else { res.textContent = 'BELUM LENGKAP'; res.className = 'status-badge bg-danger'; }
        }
    }

    selects.forEach(s => s.addEventListener('change', updateColors));
    updateColors();

    <?php if ($success_redirect_list): ?>
    Swal.fire({ title: 'Berhasil!', text: '<?php echo $redirect_message; ?>', icon: 'success' })
    .then(() => { window.location.href = '<?php echo $target_url; ?>'; });
    <?php endif; ?>
});
</script>