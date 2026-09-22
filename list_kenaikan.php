<?php
// FILE: list_kenaikan.php - Daftar Pengajuan Uji Kompetensi Khusus Kenaikan Jabatan

// =========================================================
// 🎯 FUNGSI: Mengambil dan menampilkan HANYA pengajuan dengan jenis "Kenaikan" dari database.
// =========================================================

// ----------------------------------------------------------------------------------
// 🛑 BLOK 1: AUTENTIKASI, OTORISASI, DAN INISIALISASI
// ----------------------------------------------------------------------------------
// PENTING: Memastikan 'auth_guard.php' dipanggil untuk memproteksi halaman
require_once 'auth_guard.php'; 

// --- EKSTRAKSI VARIABEL SESI DARI auth_guard.php (Pola yang lebih eksplisit) ---
$user_nip_sesi = $_SESSION['user_nip_sesi'] ?? '';
$user_nama_sesi = $_SESSION['user_nama_sesi'] ?? 'Pengguna JF'; 
$user_role_sesi = $_SESSION['user_role_sesi'] ?? 'User'; 
$user_email_sesi = $_SESSION['user_email_sesi'] ?? 'user@instansi.go.id'; 
$join_date_sesi = $_SESSION['join_date'] ?? 'Maret 2023';

// --- PENGGUNAAN DATA SESSION UNTUK HEADER/SIDEBAR ---
$user_data = [
    'nip' => $user_nip_sesi,
    'nama' => $user_nama_sesi, 
    'role' => $user_role_sesi, 
    'email' => $user_email_sesi, 
    'join_date' => $join_date_sesi
];

// --- DEKLARASI VARIABEL & KONFIGURASI HALAMAN ---
$page = 'ujikom'; 
$sub_page = 'list_kenaikan'; 
$page_title = 'Daftar Pengajuan Kenaikan Jabatan'; 
$NAMA_TABEL_PENGAJUAN = "pengajuan_ujikom"; 
$data_pengajuan = [];
$is_error = false;
$error_message = '';


// ----------------------------------------------------------------------------------
// ✅ BLOK 2: KONEKSI DAN FETCH DATA 
// ----------------------------------------------------------------------------------
$conn = null;
try {
    // KREDENSIAL: Sesuaikan dengan konfigurasi server Anda!
    $host = "localhost"; 
    $user = "root"; 
    $pass = ""; 
    $dbname = "db_jfpkp2"; 
    
    // AKTIFKAN KONEKSI
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); // Aktifkan pelaporan error MySQLi
    $conn = new mysqli($host, $user, $pass, $dbname);
    
    if ($conn->connect_error) {
        throw new Exception("Koneksi Database Gagal: Server MySQL tidak merespons atau kredensial salah. Error: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8mb4");

    // EKSEKUSI QUERY DENGAN FILTER ROBUST (hanya pengajuan jenis 'Kenaikan')
    $sql = "
        SELECT 
            id, nip, nama, jenis_pengajuan, tanggal_pengajuan, status_pengajuan
        FROM 
            {$NAMA_TABEL_PENGAJUAN}
        WHERE 
            TRIM(jenis_pengajuan) LIKE 'Kenaikan%' 
        ORDER BY 
            tanggal_pengajuan DESC
    "; 
    
    $result = $conn->query($sql);
    
    if ($result === false) {
        throw new Exception("Query Gagal. Pastikan nama tabel ({$NAMA_TABEL_PENGAJUAN}) dan nama kolom sudah benar. Error: " . $conn->error);
    }

    $data_pengajuan = $result->fetch_all(MYSQLI_ASSOC);
    $error_message = ''; 

} catch (Exception $e) {
    // Tangani error koneksi atau query
    $is_error = true;
    $error_message = "❌ Error Database! Gagal memproses data: " . htmlspecialchars($e->getMessage());
    $data_pengajuan = [];
} finally {
    // Pastikan koneksi ditutup
    if ($conn) {
        // Biarkan koneksi tetap terbuka jika akan digunakan oleh template/sidebar.php atau file lain, 
        // namun untuk skrip ini, kita menutupnya di blok finally untuk keamanan jika DB tidak dibutuhkan lagi
        // Kecuali template memerlukan $conn untuk data dinamis, kita tutup di footer.
        // Dalam kasus ini, kita tutup di finally/footer, tapi saya akan pastikan jika sudah ditutup tidak ditutup lagi.
        // Jika DB diperlukan di header/sidebar, blok finally ini akan dihapus dan penutupan dilakukan di footer.
        // Mengingat pola AdminLTE, biasanya penutupan dilakukan di akhir script utama. 
        // Untuk amannya, biarkan blok finally ini (kecuali jika koneksi di-handle di luar). 
        // Mari kita hapus penutupan di finally dan letakkan di footer seperti pola AdminLTE umumnya.
        // $conn->close(); // Dihapus untuk mematuhi pola AdminLTE/template
    }
}
// ----------------------------------------------------------------------------------


// --- PANGGIL HEADER & SIDEBAR ---
include 'template/header.php'; 
include 'template/sidebar.php'; 

// 1. Definisikan peran yang diizinkan untuk melakukan aksi (mengaktifkan tombol)
$allowed_to_act = ['super_admin', 'admin', 'verifikator'];
// Asumsi fungsi set_disabled tersedia (misalnya di 'auth_guard.php' atau 'role_helpers.php')
// Jika set_disabled tidak ada, baris ini akan menyebabkan Fatal Error. Asumsi fungsi ada.
$disable_aksi = isset($user_role_sesi) ? set_disabled($allowed_to_act) : ''; 
?>

<div class="content-wrapper"> 
    
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-white"><?php echo $page_title; ?></h1>
                </div>
                <!-- Navbar / Breadcrumb di sini jika ada -->
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="card card-indigo card-outline">
                        <div class="card-header">
                            <h3 class="card-title">Daftar Pengajuan Khusus Kenaikan Jabatan</h3>
                        </div>
                        <div class="card-body">
                            
                            <div class="mb-3">
                                <button class="btn btn-sm btn-primary" <?php echo $disable_aksi; ?>>
                                    <i class="fas fa-edit"></i> Tombol Aksi (Hanya Admin)
                                </button>
                            </div>

                            <?php 
                            // Tampilkan pesan error/informasi
                            if (!empty($error_message)): 
                            ?>
                                <div class="alert alert-<?php echo $is_error ? 'danger' : 'warning'; ?>">
                                    <strong><?php echo $is_error ? 'Gagal Total!' : 'Informasi!'; ?></strong> 
                                    <?php echo $error_message; ?>
                                </div>
                            <?php endif; ?>

                            <?php if (empty($data_pengajuan) && !$is_error): ?>
                                <div class="alert alert-info">
                                    <strong>Informasi!</strong> Tidak ditemukan data pengajuan **Kenaikan Jabatan**.
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($data_pengajuan)): ?>
                            
                            <div class="table-responsive"> 
                                <table id="tabelUjikomKenaikan" class="table table-bordered table-striped"> 
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
                                            // Tentukan badge status
                                            $status = $data['status_pengajuan'] ?? 'Menunggu Verifikasi';
                                            $badge_class = 'badge-secondary';
                                            switch ($status) {
                                                case 'Lulus Administrasi': $badge_class = 'bg-primary'; break;
                                                case 'Ditolak': $badge_class = 'bg-danger'; break;
                                                case 'Selesai Uji': $badge_class = 'bg-success'; break;
                                                case 'Menunggu Verifikasi': $badge_class = 'bg-warning'; break;
                                                case 'Verifikasi Dokumen': $badge_class = 'bg-info'; break;
                                                default: $badge_class = 'bg-secondary'; break;
                                            }
                                            $jenis_pengajuan = htmlspecialchars($data['jenis_pengajuan'] ?? 'N/A'); 
                                            $jenis_badge = 'badge-dark'; // Jenis pengajuan selalu 'Kenaikan' di sini, jadi badge-nya konsisten
                                            $tanggal = $data['tanggal_pengajuan'] ?? date('Y-m-d');
                                            $tanggal_format = date('d-m-Y', strtotime($tanggal));
                                        ?>
                                            <tr>
                                                <td><?php echo $no++; ?></td>
                                                <td><?php echo htmlspecialchars($data['nip'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($data['nama'] ?? 'N/A'); ?></td>
                                                <td>
                                                    <span class="badge <?php echo $jenis_badge; ?> border border-secondary px-2">
                                                        <?php echo $jenis_pengajuan; ?>
                                                    </span>
                                                </td> 
                                                <td><?php echo $tanggal_format; ?></td>
                                                <td><span class="badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($status); ?></span></td>
                                                <td>
                                                    <a href="detail_verif_kenaikan.php?id=<?php echo htmlspecialchars($data['id']); ?>" 
                                                    class="btn btn-info btn-sm">
                                                        <i class="fas fa-search"></i> Detail / Verifikasi
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <?php endif; ?>
                            
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

<footer class="main-footer">
    <div class="float-right d-none d-sm-inline">
         </div>
    <strong>© <?php echo date('Y'); ?> Instansi Pembina Jabatan Fungsional Penata Kelola Perumahan</strong> — Semua Hak Dilindungi.
</footer>
<aside class="control-sidebar control-sidebar-dark"></aside>

<?php
// Tutup koneksi database
if (isset($conn) && $conn) {
    // Hanya tutup jika koneksi berhasil dibuat dan masih terbuka
    $conn->close(); 
}

// --- PANGGIL FOOTER ---
include 'template/footer.php'; 
?>

<script>
    $(document).ready(function() {
        // Hancurkan inisialisasi DataTables yang mungkin sudah ada (untuk debugging)
        if ($.fn.DataTable.isDataTable('#tabelUjikomKenaikan')) {
            $('#tabelUjikomKenaikan').DataTable().destroy();
        }
        
        // Inisialisasi DataTables
        $('#tabelUjikomKenaikan').DataTable({
            "paging": true,
            "lengthChange": true,
            "searching": true,
            "ordering": true,
            "info": true,
            "autoWidth": false,
            "responsive": true, // Pastikan opsi responsive aktif
            // Opsional: Konfigurasi bahasa Indonesia  
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.10.25/i18n/Indonesian.json" 
            }
        });
        
        // Opsional: Handle klik pada tombol/link yang disabled secara visual
        $('a[disabled], button[disabled]').on('click', function(e) {
            e.preventDefault();
        });
    });
</script>