<?php
// FILE: list_perpindahan_pengusul.php - Daftar Pengajuan Uji Kompetensi Khusus Perpindahan Jabatan (Tampilan Pengusul)

// =========================================================
// ✅ SOLUSI KRITIS 1: OUTPUT BUFFERING (ob_start()) HARUS DI BARIS PERTAMA!
ob_start();

// ✅ SOLUSI KRITIS 2: JALANKAN SESSION START SETELAH ob_start() DENGAN PENCEGAHAN DUPLIKASI
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ----------------------------------------------------------------------------------
// --- PANGGIL FILE PENDUKUNG KRITIS ---
require_once 'auth_guard.php'; // Digunakan untuk otentikasi sesi
require_once 'koneksi.php';    // Koneksi database
// ----------------------------------------------------------------------------------

// --- PENGGUNAAN DATA SESSION DARI auth_guard.php ---
$user_email = $user_email_sesi ?? null; 
$user_data = [
    'nama' => $user_nama_sesi ?? 'Pengusul', 
    'role' => $user_role_sesi ?? 'Pengusul', 
    'email' => $user_email, 
    'join_date' => $_SESSION['join_date'] ?? 'Maret 2023'
];

// --- PENTING: DEKLARASI VARIABEL UNTUK HIGHLIGHT MENU SIDEBAR --
$page = 'ujikom'; 
$sub_page = 'list_perpindahan_pengusul'; // Nama sub-halaman baru
$page_title = 'Daftar Pengajuan Perpindahan Jabatan Saya'; 
// ------------------------------------------------------------

// --- PENGATURAN GLOBAL DATABASE ---
$conn = $conn ?? null;
$NAMA_TABEL_PENGAJUAN = "pengajuan_ujikom"; 
$data_pengajuan = [];
$is_error = false;
$error_message = ''; 

// ----------------------------------------------------------------------------------
// ✅ BLOK FETCH DATA
// ----------------------------------------------------------------------------------
try {
    if (!isset($conn) || !$conn) {
        throw new Exception("Koneksi database tidak tersedia. Mohon cek file koneksi.php.");
    }

    if (empty($user_email)) {
        throw new Exception("Email pengguna tidak ditemukan dalam sesi. Pastikan autentikasi berhasil mengisi variabel sesi.");
    }
    
    // EKSEKUSI QUERY DENGAN FILTER ROBUST (Script Asli Terjaga)
    $sql = "
        SELECT 
            id, nip, nama, jenis_pengajuan, tanggal_pengajuan, status_pengajuan
        FROM 
            {$NAMA_TABEL_PENGAJUAN}
        WHERE 
            TRIM(jenis_pengajuan) LIKE 'Perpindahan%' 
            AND email = ?  
        ORDER BY 
            tanggal_pengajuan DESC
    "; 
    
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new Exception("Gagal mempersiapkan statement SQL: " . $conn->error);
    }

    $stmt->bind_param("s", $user_email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result === false) {
        throw new Exception("Query Gagal. Error: " . $stmt->error);
    }

    $data_pengajuan = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $error_message = ''; 

} catch (Exception $e) {
    $is_error = true;
    $error_message = "❌ Error Database! Gagal memproses data: " . htmlspecialchars($e->getMessage());
    $data_pengajuan = [];
}
// ----------------------------------------------------------------------------------
// ⬆️ AKHIR BLOK KODE DATABASE
// ----------------------------------------------------------------------------------

// --- PANGGIL TEMPLATE HEADER (Membuka tag body, wrapper, navbar) ---
include 'template/header.php'; 
include 'template/sidebar.php'; 
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0"><?php echo $page_title; ?></h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="index_pengusul.php">Home</a></li>
                        <li class="breadcrumb-item active">Uji Kompetensi</li>
                        <li class="breadcrumb-item active">Perpindahan Jabatan</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    <section class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="card card-indigo card-outline">
                        <div class="card-header">
                            <h3 class="card-title">Data Pengajuan Khusus Perpindahan Jabatan (Mililk Anda)</h3>
                        </div>
                        <div class="card-body">
                            
                            <div class="alert alert-dark p-2" style="font-family: monospace; font-size: 12px;">
                                <strong>[DEBUG INFO]</strong><br>
                                Sesi Email Aktif saat ini: <strong>"<?php echo htmlspecialchars($user_email); ?>"</strong><br>
                                <?php 
                                // Cek data kasat mata di tabel tanpa filter email & jenis pengajuan
                                $cek_db = $conn->query("SELECT email, jenis_pengajuan FROM {$NAMA_TABEL_PENGAJUAN} LIMIT 1");
                                if ($cek_db && $row = $cek_db->fetch_assoc()) {
                                    echo "Data terakhir di DB -> Email: <strong>\"".$row['email']."\"</strong> | Jenis: <strong>\"".$row['jenis_pengajuan']."\"</strong>";
                                } else {
                                    echo "Tabel {$NAMA_TABEL_PENGAJUAN} terdeteksi kosong atau query gagal.";
                                }
                                ?>
                            </div>
                            
                            <?php 
                            // Tampilkan pesan error/informasi
                            if (!empty($error_message)): 
                            ?>
                                <div class="alert alert-<?php echo $is_error ? 'danger' : 'warning'; ?> alert-dismissible">
                                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                                    <h5><i class="icon fas fa-exclamation-triangle"></i> <?php echo $is_error ? 'Gagal Total!' : 'Informasi!'; ?></h5> 
                                    <?php echo $error_message; ?>
                                </div>
                            <?php endif; ?>

                            <?php if (empty($data_pengajuan) && !$is_error): ?>
                                <div class="alert alert-info alert-dismissible">
                                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                                    <h5><i class="icon fas fa-info"></i> Informasi!</h5>
                                    Anda belum memiliki data pengajuan **Perpindahan Jabatan** yang sesuai.
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($data_pengajuan)): ?>
                            <table id="tabelUjikomPerpindahan" class="table table-bordered table-striped dataTable dtr-inline responsive-table">
                                <thead>
                                    <tr>
                                        <th style="width: 5%">No.</th>
                                        <th style="width: 15%">NIP</th>
                                        <th style="width: 25%">Nama</th>
                                        <th style="width: 15%">Jenis Pengajuan</th> 
                                        <th style="width: 15%">Tanggal Pengajuan</th>
                                        <th style="width: 10%">Status</th>
                                        <th style="width: 15%">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $no = 1;
                                    foreach ($data_pengajuan as $data): 
                                        $status = $data['status_pengajuan'] ?? 'Menunggu Verifikasi';
                                        $badge_class = 'badge-secondary';
                                        
                                        // ✅ LOGIKA PEWARNAAN STATUS
                                        switch ($status) {
                                            case 'Lulus Administrasi':
                                                $badge_class = 'bg-primary';
                                                break;
                                            case 'Perlu Perbaikan':
                                            case 'Ditolak':
                                            case 'Tidak Lulus': // <--- Tambahan warna merah untuk Tidak Lulus
                                                $badge_class = 'bg-danger';
                                                break;
                                            case 'Selesai Uji':
                                            case 'Disetujui':
                                            case 'Lulus':
                                                $badge_class = 'bg-success';    
                                                break;
                                            case 'Menunggu Verifikasi':
                                                $badge_class = 'bg-warning text-dark';
                                                break;
                                            case 'Verifikasi Dokumen':
                                                $badge_class = 'bg-info';
                                                break;
                                            default:
                                                $badge_class = 'bg-secondary';
                                                break;
                                        }
                                    ?>
                                        <tr>
                                            <td><?php echo $no++; ?></td>
                                            <td><?php echo htmlspecialchars($data['nip'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($data['nama'] ?? 'N/A'); ?></td>
                                            <td>
                                                <?php 
                                                $jenis_pengajuan = htmlspecialchars($data['jenis_pengajuan'] ?? 'N/A'); 
                                                $jenis_badge = 'badge-dark'; 
                                                ?>
                                                <span class="badge <?php echo $jenis_badge; ?> border border-secondary px-2">
                                                    <?php echo $jenis_pengajuan; ?>
                                                </span>
                                            </td> 
                                            <td>
                                                <?php 
                                                $tanggal = $data['tanggal_pengajuan'] ?? date('Y-m-d');
                                                echo date('d-m-Y', strtotime($tanggal)); 
                                                ?>
                                            </td>
                                            <td><span class="badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($status); ?></span></td>
                                            <td>
                                                <a href="detail_isian.php?id=<?php echo htmlspecialchars($data['id']); ?>" 
                                                class="btn btn-primary btn-sm">
                                                    <i class="fas fa-eye"></i> Lihat Berkas 
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php endif; ?>
                            
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    </div>
<aside class="control-sidebar control-sidebar-dark"></aside>
<?php
// Script untuk Inisialisasi Datatables
?>
<script>
  $(function () {
    if (!$.fn.DataTable.isDataTable('#tabelUjikomPerpindahan')) {
        $("#tabelUjikomPerpindahan").DataTable({
          "responsive": true,
          "lengthChange": true,
          "autoWidth": false,
          "buttons": ["copy", "csv", "excel", "pdf", "print", "colvis"]
        }).buttons().container().appendTo('#tabelUjikomPerpindahan_wrapper .col-md-6:eq(0)');
    }
  });
</script>

<?php
// Tutup koneksi database
if (isset($conn) && $conn) {
    mysqli_close($conn);
}

// --- PANGGIL FOOTER ---
include 'template/footer.php';

// ✅ SOLUSI KRITIS 3: TUTUP OUTPUT BUFFERING
ob_end_flush(); 
?>