<?php
// =========================================================
// FILE: index_evaluator.php (Dashboard Evaluator / Pimpinan)
// UPDATE: Menampilkan status Terjadwal & Logika Kembali ke Evaluator
// =========================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- 1. KEAMANAN & KONEKSI ---
require_once 'auth_guard.php'; 
require_once 'koneksi.php'; 

if (!isset($conn) || !$conn) {
    die("Fatal Error: Koneksi database tidak tersedia.");
}

$swal_msg = "";

// --- 2. LOGIKA UPDATE STATUS (AKSI EVALUATOR) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi_setuju_evaluator'])) {
    $id_target = filter_input(INPUT_POST, 'id_pengajuan', FILTER_VALIDATE_INT);
    if ($id_target) {
        $sql_update = "UPDATE pengajuan_ujikom 
                       SET status_pengajuan = 'Disetujui Evaluator' 
                       WHERE id = ? AND (status_pengajuan = 'Proses Evaluasi Evaluator' OR status_pengajuan = 'Disetujui' OR status_pengajuan = 'Perlu Perbaikan')";
        $stmt_upd = $conn->prepare($sql_update);
        $stmt_upd->bind_param("i", $id_target);
        if ($stmt_upd->execute()) { $swal_msg = "success_update"; } 
        else { $swal_msg = "error_update"; }
        $stmt_upd->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi_kirim_ppsdm'])) {
    $id_target = filter_input(INPUT_POST, 'id_pengajuan', FILTER_VALIDATE_INT);
    if ($id_target) {
        $sql_ppsdm = "UPDATE pengajuan_ujikom 
                      SET status_pengajuan = 'Proses Evaluasi PPSDM' 
                      WHERE id = ? AND status_pengajuan = 'Disetujui Evaluator'";
        $stmt_ppsdm = $conn->prepare($sql_ppsdm);
        $stmt_ppsdm->bind_param("i", $id_target);
        if ($stmt_ppsdm->execute()) { $swal_msg = "success_ppsdm"; } 
        else { $swal_msg = "error_ppsdm"; }
        $stmt_ppsdm->close();
    }
}

// --- 3. AMBIL DATA PENGGUNA ---
$session_user_id = $_SESSION['user_id_sesi'] ?? 0;
$user_nama = "Evaluator"; $user_nip = "-"; $user_email = "-"; $user_instansi = "-"; $user_role = "-";

$sql_user = "SELECT nama, nip_user, email, instansi, role FROM users WHERE id = ?"; 
if ($stmt_user = $conn->prepare($sql_user)) {
    $stmt_user->bind_param("i", $session_user_id);
    $stmt_user->execute();
    $res_user = $stmt_user->get_result();
    if ($user_data = $res_user->fetch_assoc()) {
        $user_nama = $user_data['nama'];
        $user_nip = $user_data['nip_user'];
        $user_email = $user_data['email']; 
        $user_instansi = $user_data['instansi'];
        $user_role = $user_data['role'];
    }
    $stmt_user->close();
}

// --- 4. DATA PENGAJUAN (LOGIKA FILTER STATUS) ---
$sql_recent = "SELECT id, nip, nama, jenis_pengajuan, tanggal_pengajuan, 
                (CASE WHEN EXISTS (SELECT * FROM information_schema.columns WHERE table_name = 'pengajuan_ujikom' AND column_name = 'updated_at') 
                 THEN updated_at ELSE tanggal_pengajuan END) AS log_waktu,
                status_pengajuan, catatan_evaluator, catatan_ppsdm 
                FROM pengajuan_ujikom 
                WHERE status_pengajuan IN ('Proses Evaluasi Evaluator', 'Disetujui', 'Disetujui Evaluator', 'Proses Evaluasi PPSDM', 'Menunggu Jadwal Ujikom', 'Terjadwal', 'Perlu Perbaikan') 
                ORDER BY log_waktu DESC"; 
$result_recent = $conn->query($sql_recent);

require_once 'template/header.php';
require_once 'template/navbar.php';
require_once 'template/sidebar.php';
?>

<style>
    .dashboard-banner { background: linear-gradient(to right, #2c3e50, #4b6cb7); color: white; border-radius: 10px; padding: 20px; position: relative; overflow: hidden; }
    .focus-item { background: rgba(255, 255, 255, 0.15); margin-bottom: 8px; padding: 10px; border-radius: 6px; display: flex; align-items: center; transition: 0.3s; }
    .focus-item:hover { background: rgba(255, 255, 255, 0.25); transform: translateX(5px); }
    .focus-item i { margin-right: 12px; font-size: 1.1rem; color: #00d2ff; }

    .st-small { font-size: 0.72rem !important; padding: 4px 12px !important; font-weight: 700 !important; border-radius: 50px; display: inline-block; text-transform: capitalize; min-width: 110px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); text-align: center; color: #fff; }
    
    .bg-primary { background-color: #007bff !important; }
    .bg-success { background-color: #28a745 !important; }
    .bg-purple  { background-color: #605ca8 !important; }
    .bg-lime    { background-color: #01ff70 !important; color: #1f2d3d !important; }
    .bg-danger  { background-color: #dc3545 !important; }
    .bg-warning { background-color: #ffc107 !important; color: #1f2d3d !important; }
    .bg-info    { background-color: #17a2b8 !important; }
    .bg-fuchsia { background-color: #f012be !important; }
    .bg-secondary { background-color: #6c757d !important; }

    .btn-periksa { background-color: #007bff; color: white; border-radius: 4px; font-size: 0.8rem; padding: 4px 12px; border: none; }
    .btn-setujui-eval { background-color: #f39c12; color: white; border-radius: 4px; font-size: 0.8rem; padding: 4px 12px; border: none; margin-top: 4px; font-weight: bold; }
    .btn-kirim { background-color: #28a745; color: white; border-radius: 4px; font-size: 0.8rem; padding: 4px 12px; border: none; margin-top: 4px; }
    .btn-jadwal { background-color: #28a745; color: white; border-radius: 4px; font-size: 0.8rem; padding: 4px 12px; border: none; margin-top: 4px; font-weight: bold; }
    
    .table-modern thead th { background-color: #f8f9fa; color: #333; font-weight: 600; font-size: 0.85rem; border-bottom: 2px solid #dee2e6; }
    .log-text { font-size: 0.75rem; line-height: 1.2; }
    
    .chat-bubble { padding: 8px 12px; border-radius: 8px; margin-bottom: 5px; position: relative; }
    .bubble-eval { background-color: #fff3cd; border-left: 4px solid #ffc107; color: #856404; }
    .bubble-ppsdm { background-color: #d1ecf1; border-left: 4px solid #17a2b8; color: #0c5460; }
    .bubble-title { font-weight: bold; font-size: 0.65rem; text-transform: uppercase; margin-bottom: 2px; display: block; }
</style>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-12"><h5 class="text-dark font-weight-bold" style="font-size: 2rem;">Dashboard Evaluator</h5></div>
            </div>

            <div class="row mb-4">
                <div class="col-md-8">
                    <div class="dashboard-banner shadow-sm h-100">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h4 class="font-weight-bold"><i class="fas fa-comments-alt mr-2"></i>Selamat Datang, <?php echo $user_nama; ?></h4>
                                <p class="mb-3"> Koordinasikan hasil tinjauan Anda dengan catatan PPSDM untuk keputusan verifikasi yang tepat !</p>
                                <div class="row info-profile-text">
                                    <div class="col-4 info-label"><i class="fas fa-user mr-2"></i> Nama Lengkap</div>
                                    <div class="col-8">: <?php echo $user_nama; ?></div>
                                    <div class="col-4 info-label"><i class="fas fa-id-card mr-2"></i> NIP</div>
                                    <div class="col-8">: <?php echo $user_nip; ?></div>
                                    <div class="col-4 info-label"><i class="fas fa-envelope mr-2"></i> Email</div>
                                    <div class="col-8">: <?php echo $user_email; ?></div>
                                    <div class="col-4 info-label"><i class="fas fa-building mr-2"></i> Instansi</div>
                                    <div class="col-8">: <?php echo $user_instansi; ?></div>
                                    <div class="col-4 info-label"><i class="fas fa-user-shield mr-2"></i> Peran</div>
                                    <div class="col-8">: <?php echo $user_role; ?></div>
                                </div>
                            </div>
                           <img src="https://i.pravatar.cc/120?u=<?php echo urlencode($user_email); ?>" class="img-circle elevation-1" alt="User Image" width="50" height="50">
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="dashboard-banner h-100" style="background: #1a2a6c;">
                        <h6 class="font-weight-bold mb-3"><i class="fas fa-info-circle mr-2"></i> Panduan Evaluator</h6>
                        <div class="focus-item small"><i class="fas fa-file-download text-info"></i> Periksa Dokumen</div>
                        <div class="focus-item small"><i class="fas fa-search-plus mr-1"></i>Tinjau Catatan Dari PPSDM</div>
                        <div class="focus-item small"><i class="fas fa-check-circle text-success"></i>Kirim Ke PPSDM</div>
                        <div class="focus-item small"><i class="fas fa-undo text-warning"></i>Kembalikan Ke Verifikator</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table id="tabelEvaluator" class="table table-hover mb-0">
                            <thead class="table-modern">
                                <tr>
                                    <th class="text-center" style="width: 5%;">No.</th>
                                    <th style="width: 15%;">Pegawai</th>
                                    <th style="width: 15%;">Pengajuan</th>
                                    <th style="width: 35%;">Diskusi Internal (Evaluator & PPSDM)</th>
                                    <th class="text-center" style="width: 15%;">Status</th>
                                    <th class="text-center" style="width: 15%;">Tindakan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result_recent && $result_recent->num_rows > 0): $no = 1; ?>
                                    <?php while($row = $result_recent->fetch_assoc()): 
                                        $status = $row['status_pengajuan'];
                                        
                                        $badge_class = 'bg-secondary';
                                        switch ($status) {
                                            case 'Lulus Administrasi':        $badge_class = 'bg-primary'; break;
                                            case 'Disetujui':                 $badge_class = 'bg-success'; break;
                                            case 'Proses Evaluasi Evaluator': $badge_class = 'bg-purple'; break;
                                            case 'Proses Evaluasi PPSDM' :    $badge_class = 'bg-lime'; break;
                                            case 'Perlu Perbaikan':           $badge_class = 'bg-danger'; break;
                                            case 'Menunggu Verifikasi':       $badge_class = 'bg-warning'; break;
                                            case 'Verifikasi Dokumen':        $badge_class = 'bg-info'; break;
                                            case 'Menunggu Jadwal Ujikom':    $badge_class = 'bg-fuchsia'; break;
                                            case 'Disetujui Evaluator':       $badge_class = 'bg-success'; break;
                                            case 'Terjadwal':                 $badge_class = 'bg-info'; break;
                                            default:                          $badge_class = 'bg-secondary'; break;
                                        }

                                        $waktu_log = date('d/m/Y H:i', strtotime($row['log_waktu']));
                                    ?>
                                    <tr>
                                        <td class="text-center align-middle small"><?php echo $no++; ?></td>
                                        <td class="align-middle">
                                            <div class="font-weight-bold" style="font-size: 0.85rem;"><?php echo htmlspecialchars($row['nama']); ?></div>
                                            <div class="text-muted small">NIP: <?php echo $row['nip']; ?></div>
                                        </td>
                                        <td class="align-middle">
                                            <div class="font-weight-bold small"><?php echo $row['jenis_pengajuan']; ?></div>
                                            <div class="text-muted" style="font-size: 0.7rem;">Tgl: <?php echo date('d/m/Y', strtotime($row['tanggal_pengajuan'])); ?></div>
                                        </td>
                                        
                                        <td class="align-middle">
                                            <?php if(!empty($row['catatan_ppsdm'])): ?>
                                                <div class="chat-bubble bubble-ppsdm">
                                                    <span class="bubble-title"><i class="fas fa-university"></i> Catatan PPSDM:</span>
                                                    <span style="font-size: 0.75rem;"><?php echo htmlspecialchars($row['catatan_ppsdm']); ?></span>
                                                </div>
                                            <?php endif; ?>

                                            <?php if(!empty($row['catatan_evaluator'])): ?>
                                                <div class="chat-bubble bubble-eval">
                                                    <span class="bubble-title"><i class="fas fa-user-tie"></i> Respon/Sanggahan Evaluator:</span>
                                                    <span style="font-size: 0.75rem;"><?php echo htmlspecialchars($row['catatan_evaluator']); ?></span>
                                                </div>
                                            <?php endif; ?>

                                            <?php if(empty($row['catatan_ppsdm']) && empty($row['catatan_evaluator'])): ?>
                                                <span class="text-muted small italic">Tidak ada catatan internal.</span>
                                            <?php endif; ?>
                                        </td>

                                        <td class="text-center align-middle">
                                            <span class="st-small <?php echo $badge_class; ?>">
                                                <?php 
                                                    if ($status == 'Disetujui') {
                                                        echo 'Siap Disetujui Evaluator';
                                                    } elseif ($status == 'Perlu Perbaikan') {
                                                        echo 'Kembali ke Verifikator';
                                                    } else {
                                                        echo $status;
                                                    }
                                                ?>
                                            </span>
                                            <div class="text-muted mt-1" style="font-size: 0.65rem;">Log: <?php echo $waktu_log; ?></div>
                                        </td>

                                        <td class="text-center align-middle">
                                            <a href="detail_verif_perpindahan.php?id=<?php echo $row['id']; ?>" class="btn btn-periksa btn-block shadow-sm">
                                                <i class="fas fa-edit mr-1"></i> Tinjau & Respon
                                            </a>

                                            <?php if ($status == 'Disetujui' || $status == 'Proses Evaluasi Evaluator' || $status == 'Perlu Perbaikan'): ?>
                                                <form method="POST" class="mt-1">
                                                    <input type="hidden" name="id_pengajuan" value="<?php echo $row['id']; ?>">
                                                    <button type="submit" name="aksi_setuju_evaluator" class="btn btn-setujui-eval btn-block shadow-sm">
                                                        <i class="fas fa-check-double mr-1"></i> Setujui
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <?php if ($status == 'Disetujui Evaluator'): ?>
                                                <form method="POST" id="formPPSDM_<?php echo $row['id']; ?>" class="mt-1">
                                                    <input type="hidden" name="id_pengajuan" value="<?php echo $row['id']; ?>">
                                                    <input type="hidden" name="aksi_kirim_ppsdm" value="1">
                                                    <button type="button" onclick="confirmSendPPSDM(<?php echo $row['id']; ?>, '<?php echo addslashes($row['nama']); ?>')" class="btn btn-kirim btn-block shadow-sm">
                                                        <i class="fas fa-paper-plane mr-1"></i> Kirim PPSDM
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <?php if ($status == 'Terjadwal'): ?>
                                                <a href="lihat_jadwal.php?id=<?php echo $row['id']; ?>" class="btn btn-jadwal btn-block shadow-sm">
                                                    <i class="fas fa-calendar-alt mr-1"></i> Lihat Jadwal
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="6" class="text-center p-4 text-muted">Belum ada pengajuan masuk.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<?php require_once 'template/footer.php'; ?>

<script>
$(document).ready(function() {
    $('#tabelEvaluator').DataTable({
        "responsive": true, "autoWidth": false, "order": [[4, "desc"]],
        "language": { "search": "Cari:", "lengthMenu": "_MENU_", "paginate": { "next": ">", "previous": "<" } }
    });

    <?php if ($swal_msg == "success_update"): ?>
        Swal.fire({ icon: 'success', title: 'Berhasil!', text: 'Status diperbarui.', timer: 2000, showConfirmButton: false });
    <?php elseif ($swal_msg == "success_ppsdm"): ?>
        Swal.fire({ icon: 'success', title: 'Terkirim!', text: 'Berkas diteruskan ke PPSDM.', timer: 2000, showConfirmButton: false });
    <?php endif; ?>
});

function confirmSendPPSDM(id, nama) {
    Swal.fire({
        title: 'Kirim ke PPSDM?',
        text: "Pastikan sanggahan atau catatan sudah sesuai.",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Ya, Kirim!'
    }).then((result) => { 
        if (result.isConfirmed) { 
            document.getElementById('formPPSDM_' + id).submit(); 
        } 
    });
}
</script>