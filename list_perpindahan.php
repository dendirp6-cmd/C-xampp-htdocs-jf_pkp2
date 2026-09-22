<?php
/**
 * FILE: list_perpindahan.php
 * DESKRIPSI: Daftar Pengajuan Uji Kompetensi Khusus Perpindahan Jabatan
 * TAMPILAN: AdminLTE 3 Standard
 * TERAKHIR DIPERBARUI: Desember 2025
 */

// ==================================================================================
// 1. PENGATURAN AWAL: SESSION & AUTORISASI
// ==================================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Logika Keamanan:
 * Memastikan pengguna sudah login sebelum mengakses halaman ini.
 * File auth_guard.php harus menangani pengecekan session user_nip_sesi.
 */

// ----------------------------------------------------------------------------------
// 2. PANGGIL FILE PENDUKUNG KRITIS & INISIALISASI
// ----------------------------------------------------------------------------------
require_once 'auth_guard.php'; // WAJIB DIPANGGIL PALING AWAL
require_once 'koneksi.php';    // Koneksi Database

// Cek koneksi untuk mencegah fatal error pada sistem
if (!isset($conn) || !$conn) {
    die("Fatal Error: Koneksi database tidak tersedia. Mohon cek file koneksi.php Anda.");
}

// --- EKSTRAKSI VARIABEL SESI (Fallback/Default) ---
$user_nip_sesi   = $_SESSION['user_nip_sesi']   ?? '';
$user_nama_sesi  = $_SESSION['user_nama_sesi']  ?? 'Pengguna JF'; 
$user_role_sesi  = $_SESSION['user_role_sesi']  ?? 'User'; 
$user_email_sesi = $_SESSION['user_email_sesi'] ?? 'user@instansi.go.id'; 

// --- DEKLARASI VARIABEL UNTUK HALAMAN ---
$page        = 'ujikom'; 
$sub_page    = 'list_perpindahan'; 
$page_title  = 'Daftar Pengajuan Perpindahan Jabatan'; 

// --- PENGATURAN GLOBAL DATABASE ---
$NAMA_TABEL_PENGAJUAN = "pengajuan_ujikom"; 
$NAMA_TABEL_PEGAWAI   = "pegawai"; 
$data_pengajuan       = []; 
$is_error             = false;
$error_message        = ''; 
$success_message      = '';

// ----------------------------------------------------------------------------------
// 3. BLOK LOGIKA PENGIRIMAN DATA KE EVALUATOR
// ----------------------------------------------------------------------------------
/**
 * Fitur: Kirim Data ke Evaluator
 * Data yang berstatus 'Disetujui' dapat diteruskan ke Evaluator
 * dengan mengubah status menjadi 'Proses Evaluasi Evaluator'.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi_kirim_evaluator'])) {
    $id_target = filter_input(INPUT_POST, 'id_pengajuan', FILTER_VALIDATE_INT);
    
    if ($id_target) {
        try {
            // Update status pengajuan agar terbaca oleh role evaluator
            // Status diubah menjadi 'Proses Evaluasi Evaluator'
            $sql_update = "UPDATE {$NAMA_TABEL_PENGAJUAN} 
                           SET status_pengajuan = 'Proses Evaluasi Evaluator' 
                           WHERE id = ? AND status_pengajuan = 'Disetujui'";
            
            if ($stmt_up = $conn->prepare($sql_update)) {
                $stmt_up->bind_param("i", $id_target);
                if ($stmt_up->execute()) {
                    if ($stmt_up->affected_rows > 0) {
                        $success_message = "✅ Berhasil! Data pengajuan ID #$id_target telah dikirim dan status berubah menjadi Proses Evaluasi Evaluator.";
                    } else {
                        $error_message = "⚠️ Gagal mengirim. Pastikan status pengajuan saat ini adalah 'Disetujui'.";
                    }
                }
                $stmt_up->close();
            }
        } catch (Exception $e) {
            $error_message = "❌ Terjadi kesalahan sistem: " . $e->getMessage();
        }
    }
}

// ----------------------------------------------------------------------------------
// 4. BLOK FETCH DATA PROFIL PENGGUNA (UNTUK NAVBAR & SIDEBAR)
// ----------------------------------------------------------------------------------
$user_data = [
    'nama'        => $user_nama_sesi, 
    'role'        => $user_role_sesi, 
    'email'       => $user_email_sesi, 
    'join_date'   => $_SESSION['join_date'] ?? 'Desember 2025',
    'nip'         => $user_nip_sesi,
    'foto_profil' => 'default.png' 
];

if (!empty($user_nip_sesi)) {
    try {
        $sql_user = "SELECT nama, role, email, foto_profil FROM {$NAMA_TABEL_PEGAWAI} WHERE nip = ? LIMIT 1";
        if ($stmt_user = $conn->prepare($sql_user)) {
            $stmt_user->bind_param("s", $user_nip_sesi);
            $stmt_user->execute();
            $result_user = $stmt_user->get_result();
            if ($row_user = $result_user->fetch_assoc()) {
                $user_data['nama']        = htmlspecialchars($row_user['nama']);
                $user_data['role']        = htmlspecialchars($row_user['role']);
                $user_data['email']       = htmlspecialchars($row_user['email']);
                $user_data['foto_profil'] = htmlspecialchars($row_user['foto_profil'] ?? 'default.png');
            }
            $stmt_user->close();
        }
    } catch (Exception $e) {
        // Fallback tetap menggunakan data session jika query gagal
    }
}

// ----------------------------------------------------------------------------------
// 5. BLOK LOGIKA & FETCH DATA UTAMA (DAFTAR PENGAJUAN DENGAN FILTER)
// ----------------------------------------------------------------------------------

// --- Filter Status ---
$allowed_statuses = [
    'Semua Status', 
    'Menunggu Verifikasi', 
    'Verifikasi Dokumen',
    'Perlu Perbaikan', 
    'Lulus Administrasi',
    'Disetujui', 
    'Selesai Uji',
    'Proses Evaluasi Evaluator',
    'Proses Evaluasi PPSDM',
    'Menunggu Jadwal Ujikom',
    'Disetujui Evaluator'
];

$filter_status = $_GET['status'] ?? 'Semua Status';
if (!in_array($filter_status, $allowed_statuses)) { 
    $filter_status = 'Semua Status'; 
}

// --- Filter Kelengkapan ---
$allowed_kelengkapan = ['Semua Kelengkapan', 'Dibawah 50%', '50% - 99%', 'Lengkap (100%)'];
$filter_kelengkapan  = $_GET['kelengkapan'] ?? 'Semua Kelengkapan';
if (!in_array($filter_kelengkapan, $allowed_kelengkapan)) { 
    $filter_kelengkapan = 'Semua Kelengkapan'; 
}

// --- Konstruksi Query Dinamis ---
$query_parts = [];
$params      = [];
$types       = "";

// Filter Jenis Pengajuan (Wajib)
$query_parts[] = "TRIM(jenis_pengajuan) LIKE ?";
$params[]      = 'Perpindahan%';
$types         .= "s";

// Filter Status
if ($filter_status !== 'Semua Status') {
    $query_parts[] = "status_pengajuan = ?";
    $params[]      = $filter_status;
    $types         .= "s";
}

// Filter Kelengkapan
if ($filter_kelengkapan === 'Dibawah 50%') {
    $query_parts[] = "progres_kelengkapan < 50";
} elseif ($filter_kelengkapan === '50% - 99%') {
    $query_parts[] = "progres_kelengkapan >= 50 AND progres_kelengkapan < 100";
} elseif ($filter_kelengkapan === 'Lengkap (100%)') {
    $query_parts[] = "progres_kelengkapan = 100";
}

$where_clause = implode(" AND ", $query_parts);

try {
    $sql = "SELECT id, nip, nama, jenis_pengajuan, tanggal_pengajuan, status_pengajuan, progres_kelengkapan 
            FROM {$NAMA_TABEL_PENGAJUAN}
            WHERE {$where_clause}
            ORDER BY tanggal_pengajuan DESC"; 
    
    if ($stmt = $conn->prepare($sql)) {
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $data_pengajuan = $result->fetch_all(MYSQLI_ASSOC);
        $total_data_ditemukan = count($data_pengajuan);
        $stmt->close();
    }
} catch (Exception $e) {
    $is_error = true;
    $error_message = "❌ Error Database! " . htmlspecialchars($e->getMessage());
}

// ----------------------------------------------------------------------------------
// 6. STRUKTUR HTML/TAMPILAN ADMINLTE
// ----------------------------------------------------------------------------------
require_once 'template/header.php'; 
require_once 'template/navbar.php'; 
require_once 'template/sidebar.php'; 
?>

<div class="content-wrapper"> 
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-dark"><?php echo $page_title; ?></h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                        <li class="breadcrumb-item active">Ujikom</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            
            <div class="row">
                <div class="col-12">
                    <div class="card card-indigo card-outline">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-list mr-1"></i>
                                Data Pengajuan Khusus Perpindahan Jabatan 
                                <span class="badge badge-secondary ml-2">Status: <?php echo htmlspecialchars($filter_status); ?></span>
                                <span class="badge badge-secondary ml-2">Kelengkapan: <?php echo htmlspecialchars($filter_kelengkapan); ?></span>
                                <span class="badge badge-primary ml-2">(<?php echo $total_data_ditemukan ?? 0; ?> Data Ditemukan)</span>
                            </h3>
                            <div class="card-tools">
                                <button type="button" class="btn btn-tool" data-card-widget="collapse" title="Collapse">
                                    <i class="fas fa-minus"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="card-body">
                            
                            <div class="mb-4 bg-light p-3" style="border-radius: 5px;">
                                <form method="GET" action="list_perpindahan.php" class="form-inline">
                                    <div class="form-group mr-3">
                                        <label for="status_filter" class="mr-2 font-weight-bold">Status:</label>
                                        <select name="status" id="status_filter" class="form-control form-control-sm" style="min-width: 180px;">
                                            <?php foreach ($allowed_statuses as $status_option): 
                                                $selected = ($status_option == $filter_status) ? 'selected' : ''; ?>
                                                <option value="<?php echo htmlspecialchars($status_option); ?>" <?php echo $selected; ?>>
                                                    <?php echo htmlspecialchars($status_option); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group mr-3">
                                        <label for="kelengkapan_filter" class="mr-2 font-weight-bold">Kelengkapan:</label>
                                        <select name="kelengkapan" id="kelengkapan_filter" class="form-control form-control-sm" style="min-width: 180px;">
                                            <?php foreach ($allowed_kelengkapan as $kelengkapan_option): 
                                                $selected = ($kelengkapan_option == $filter_kelengkapan) ? 'selected' : ''; ?>
                                                <option value="<?php echo htmlspecialchars($kelengkapan_option); ?>" <?php echo $selected; ?>>
                                                    <?php echo htmlspecialchars($kelengkapan_option); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <button class="btn btn-primary btn-sm" type="submit">
                                        <i class="fas fa-filter mr-1"></i> Tampilkan
                                    </button>
                                    <a href="list_perpindahan.php" class="btn btn-default btn-sm ml-2">
                                        <i class="fas fa-sync"></i> Reset
                                    </a>
                                </form>
                            </div>
                            
                            <?php if (!empty($success_message)): ?>
                                <div class="alert alert-success alert-dismissible">
                                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                                    <h5><i class="icon fas fa-check"></i> Sukses!</h5>
                                    <?php echo $success_message; ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($error_message)): ?>
                                <div class="alert alert-<?php echo $is_error ? 'danger' : 'warning'; ?> alert-dismissible">
                                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                                    <h5><i class="icon fas fa-exclamation-triangle"></i> <?php echo $is_error ? 'Gagal Total!' : 'Informasi!'; ?></h5> 
                                    <?php echo $error_message; ?>
                                </div>
                            <?php endif; ?>

                            <?php if (empty($data_pengajuan) && !$is_error): ?>
                                <div class="alert alert-info alert-dismissible">
                                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                                    <h5><i class="icon fas fa-info"></i> Kosong!</h5>
                                    Tidak ditemukan data pengajuan dengan kriteria tersebut.
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($data_pengajuan)): ?>
                            <div class="table-responsive"> 
                                <table id="tabelUjikomPerpindahan" class="table table-bordered table-striped dataTable dtr-inline">
                                    <thead class="bg-indigo text-white">
                                        <tr>
                                            <th style="width: 5%">No.</th>
                                            <th style="width: 12%">NIP</th>
                                            <th style="width: 18%">Nama</th>
                                            <th style="width: 12%">Kelengkapan</th>
                                            <th style="width: 12%">Tanggal</th>
                                            <th style="width: 12%">Status</th>
                                            <th style="width: 29%">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $no = 1;
                                        foreach ($data_pengajuan as $data): 
                                            $status = $data['status_pengajuan'] ?? 'Menunggu Verifikasi';
                                            $display_status = htmlspecialchars($status);
                                            
                                            // --- LOGIKA PEWARNAAN BADGE (DINAMIS) ---
                                            $badge_class = 'bg-secondary';
                                            switch ($status) {
                                                case 'Lulus Administrasi':        $badge_class = 'bg-primary'; break;
                                                case 'Disetujui Verifikator':     $badge_class = 'bg-success'; break;
                                                case 'Evaluasi Direktur':         $badge_class = 'bg-purple'; break;
                                                case 'Proses Evaluasi PPSDM' :    $badge_class = 'bg-lime'; break;
                                                case 'Proses Direktur' :          $badge_class = 'bg-teal'; break;
                                                case 'Proses Verifikasi' :        $badge_class = 'bg-orange'; break;
                                                case 'Terjadwal' :                $badge_class = 'bg-gray'; break;
                                                case 'Perlu Perbaikan':           $badge_class = 'bg-danger'; break;
                                                case 'Menunggu Verifikasi':       $badge_class = 'bg-warning'; break;
                                                case 'Menunggu Disposisi':        $badge_class = 'bg-indigo'; break;
                                                case 'Verifikasi Dokumen':        $badge_class = 'bg-info'; break;
                                                // Fallback tambahan dari list filter
                                                case 'Disetujui':                 $badge_class = 'bg-success'; break;
                                                case 'Proses Evaluasi Evaluator': $badge_class = 'bg-purple'; break;
                                                case 'Menunggu Jadwal Ujikom':    $badge_class = 'bg-fuchsia'; break;
                                                case 'Disetujui Evaluator':       $badge_class = 'bg-success'; break;
                                                default:                          $badge_class = 'bg-secondary'; break;
                                            }
                                        ?>
                                            <tr>
                                                <td class="text-center"><?php echo $no++; ?></td>
                                                <td><code><?php echo htmlspecialchars($data['nip'] ?? 'N/A'); ?></code></td>
                                                <td><strong><?php echo htmlspecialchars($data['nama'] ?? 'N/A'); ?></strong></td>
                                                <td>
                                                    <?php 
                                                    $progres = (int)($data['progres_kelengkapan'] ?? 0);
                                                    $progress_class = ($progres >= 100) ? 'bg-success' : (($progres >= 50) ? 'bg-warning' : 'bg-danger');
                                                    ?>
                                                    <div class="progress progress-xs mb-1">
                                                        <div class="progress-bar <?php echo $progress_class; ?>" style="width: <?php echo $progres; ?>%"></div>
                                                    </div>
                                                    <small class="badge <?php echo $progress_class; ?>"><?php echo $progres; ?>%</small>
                                                </td>
                                                <td><?php echo date('d/m/Y', strtotime($data['tanggal_pengajuan'])); ?></td>
                                                <td><span class="badge <?php echo $badge_class; ?> p-2"><?php echo $display_status; ?></span></td>
                                                <td>
                                                    <div class="btn-group">
                                                        <a href="detail_verif_perpindahan.php?id=<?php echo htmlspecialchars($data['id']); ?>" 
                                                           class="btn btn-info btn-sm">
                                                            <i class="fas fa-search"></i> Detail
                                                        </a>
                                                        
                                                        <?php if ($status === 'Disetujui'): ?>
                                                            <form method="POST" class="ml-1" onsubmit="return confirm('Kirim data ke Evaluator sekarang?')">
                                                                <input type="hidden" name="id_pengajuan" value="<?php echo $data['id']; ?>">
                                                                <button type="submit" name="aksi_kirim_evaluator" class="btn btn-success btn-sm">
                                                                    <i class="fas fa-paper-plane"></i> Kirim Evaluator
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>
                            
                        </div>
                        <div class="card-footer text-muted small">
                            Sistem Monitoring Ujikom - Terakhir diperbarui: <?php echo date('d-m-Y H:i'); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/js/adminlte.min.js"></script>

<script src="plugins/datatables/jquery.dataTables.min.js"></script>
<script src="plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="plugins/datatables-buttons/js/dataTables.buttons.min.js"></script>
<script src="plugins/datatables-buttons/js/buttons.bootstrap4.min.js"></script>
<script src="plugins/jszip/jszip.min.js"></script>
<script src="plugins/pdfmake/pdfmake.min.js"></script>
<script src="plugins/pdfmake/vfs_fonts.js"></script>
<script src="plugins/datatables-buttons/js/buttons.html5.min.js"></script>
<script src="plugins/datatables-buttons/js/buttons.print.min.js"></script>

<script>
    $(function () {
      $("#tabelUjikomPerpindahan").DataTable({
        "responsive": true, 
        "lengthChange": true, 
        "autoWidth": false,
        "dom": 'Bfrtip',
        "buttons": [
            { extend: "copy", className: "btn-sm" },
            { extend: "csv", className: "btn-sm" },
            { extend: "excel", className: "btn-sm" },
            { extend: "pdf", className: "btn-sm" },
            { extend: "print", className: "btn-sm" }
        ],
        "paging": true,
        "searching": true,
        "ordering": true,
        "info": true,
        "order": [[4, "desc"]], // Sort by Tanggal by default
        "language": {
            "search": "Pencarian Cepat:",
            "lengthMenu": "Tampilkan _MENU_ baris",
            "info": "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
            "paginate": {
                "first": "Pertama",
                "last": "Terakhir",
                "next": "Lanjut",
                "previous": "Kembali"
            }
        }
      }).buttons().container().appendTo('#tabelUjikomPerpindahan_wrapper .col-md-6:eq(0)');
    });
</script>

<?php 
require_once 'template/footer.php'; 

// Tutup koneksi secara eksplisit
if (isset($conn) && $conn) { 
    $conn->close(); 
}
?>