<?php
// FILE: list_rekom_pengusul.php - Daftar Pengajuan Rekomendasi Formasi (Tampilan Pengusul)

// =========================================================
// ✅ SOLUSI KRITIS 1: OUTPUT BUFFERING
ob_start();

// ✅ SOLUSI KRITIS 2: SESSION START AMAN
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ----------------------------------------------------------------------------------
// --- PANGGIL FILE PENDUKUNG ---
require_once 'auth_guard.php'; // Otentikasi
require_once 'koneksi.php';    // Koneksi DB
// ----------------------------------------------------------------------------------

// --- DATA SESI PENGGUNA ---
// Mengambil data dari sesi login. Memperbaiki pengambilan variabel sesi agar lebih eksplisit/aman.
$user_nama = $_SESSION['user_nama_sesi'] ?? 'Pengusul'; 
$user_role = $_SESSION['user_role_sesi'] ?? 'Pengusul';

// --- SETTING HIGHLIGHT MENU SIDEBAR ---
$page = 'rekomendasi'; 
$sub_page = 'list_rekom_pengusul'; 
$page_title = 'Daftar Pengajuan Rekomendasi Formasi'; 
// ------------------------------------------------------------

// --- VARIABEL GLOBAL ---
// Asumsi $conn dibuat di koneksi.php
$conn = $conn ?? null; 
$NAMA_TABEL = "rekomendasi_formasi"; 
$data_pengajuan = [];
$is_error = false;
$error_message = ''; 

// ----------------------------------------------------------------------------------
// ✅ BLOK FETCH DATA
// ----------------------------------------------------------------------------------
try {
    // 1. Cek Koneksi
    if (!isset($conn) || !$conn) {
        throw new Exception("Koneksi database bermasalah.");
    }

    // 2. Validasi Sesi
    if (empty($user_nama) || $user_nama === 'Pengusul') {
        throw new Exception("Sesi Pengguna tidak valid atau belum ditemukan.");
    }
    
    // 3. FILTER: GUNAKAN LIKE (%) AGAR DATA BISA DITEMUKAN OLEH NAMA AKUN
    $filter_nama = "%" . strtolower(trim($user_nama)) . "%";

    // 4. QUERY SQL (LENGKAP SESUAI KOLOM DI DATABASE)
    $sql = "
        SELECT 
            id, 
            tanggal_pengajuan,
            nama_pengusul,
            nip,
            instansi, 
            provinsi, 
            kota_kab, 
            status
        FROM 
            {$NAMA_TABEL}
        WHERE 
            LOWER(nama_pengusul) LIKE ? 
        ORDER BY 
            tanggal_pengajuan DESC
    "; 
    
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new Exception("Gagal prepare query: " . $conn->error);
    }

    $stmt->bind_param("s", $filter_nama);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result === false) {
        throw new Exception("Gagal mengambil data: " . $stmt->error);
    }

    $data_pengajuan = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

} catch (Exception $e) {
    $is_error = true;
    $error_message = "❌ Terjadi Kesalahan: " . htmlspecialchars($e->getMessage());
}

// --- TEMPLATE HEADER ---
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
                        <li class="breadcrumb-item active">Rekomendasi</li>
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
                            <h3 class="card-title">Data Pengajuan Anda</h3>
                            <div class="card-tools">
                                <a href="input_rekom.php" class="btn btn-sm btn-success">
                                    <i class="fas fa-plus"></i> Ajukan Baru
                                
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            
                            <?php if ($is_error): ?>
                                <div class="alert alert-danger alert-dismissible">
                                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                                    <h5><i class="icon fas fa-ban"></i> Error!</h5>
                                    <?php echo $error_message; ?>
                                </div>
                            <?php endif; ?>

                            <?php if (isset($_SESSION['popup_message'])): ?>
                                <div class="alert alert-success alert-dismissible">
                                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                                    <h5><i class="icon fas fa-check"></i> Berhasil!</h5>
                                    <?php echo $_SESSION['popup_message']; ?>
                                </div>
                                <?php unset($_SESSION['popup_message']); ?>
                            <?php endif; ?>

                            <?php if (empty($data_pengajuan) && !$is_error): ?>
                                <div class="alert alert-info">
                                    <h5><i class="icon fas fa-info"></i> Belum Ada Data</h5>
                                    Belum ada data yang cocok dengan nama akun Anda: <strong><?php echo htmlspecialchars($user_nama); ?></strong>
                                </div>
                            <?php else: ?>
                                <table id="tabelRekom" class="table table-bordered table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th width="5%">No</th>
                                            <th width="15%">Tanggal</th>
                                            <th>Nama Pengusul</th>
                                            <th>NIP</th>
                                            <th>Instansi / Lokasi</th>
                                            <th width="10%">Status</th>
                                            <th width="15%">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $no = 1;
                                        foreach ($data_pengajuan as $row): 
                                            // Nilai status dari database
                                            $st_db = $row['status']; 
                                            $st_display = $st_db;    // Nilai status untuk ditampilkan
                                            $badge = 'secondary';
                                            
                                            // Logika Badge Status YANG DIPERBAIKI: Mengatur warna badge
                                            if ($st_db == 'Disetujui') {
                                                $badge = 'success';
                                            } elseif ($st_db == 'Ditolak') {
                                                $badge = 'danger';
                                            } elseif ($st_db == 'Perlu Revisi') {
                                                $badge = 'danger';
                                                $st_display = 'Perlu Perbaikan'; // Teks yang ditampilkan ke Pengusul
                                            } elseif ($st_db == 'Diverifikasi') {
                                                $badge = 'primary';
                                            } elseif ($st_db == 'Menunggu Verifikasi') {
                                                $badge = 'warning';
                                            } elseif ($st_db == 'Menunggu Verifikasi Ulang') {
                                                $badge = 'info'; // Warna baru untuk status setelah revisi
                                                // ✅ PERUBAHAN: Setel teks tampilan secara eksplisit
                                                $st_display = 'Menunggu Verifikasi Ulang'; 
                                            } elseif ($st_db == 'Draft') {
                                                $badge = 'secondary';
                                            } else {
                                                // Default atau status yang tidak dikenal
                                                $badge = 'secondary';
                                            }

                                            // Lokasi Gabungan
                                            $lokasi = htmlspecialchars($row['instansi']);
                                            if(!empty($row['kota_kab'])) $lokasi .= "<br><small class='text-muted'>".htmlspecialchars($row['kota_kab'])."</small>";
                                        ?>
                                        <tr>
                                            <td><?php echo $no++; ?></td>
                                            <td><?php echo date('d-m-Y H:i', strtotime($row['tanggal_pengajuan'])); ?></td>
                                            <td><?php echo htmlspecialchars($row['nama_pengusul']); ?></td>
                                            <td><?php echo htmlspecialchars($row['nip']); ?></td>
                                            <td><?php echo $lokasi; ?></td>
                                            <td class="text-center">
                                                <span class="badge bg-<?php echo $badge; ?>"><?php echo htmlspecialchars($st_display); ?></span>
                                            </td>
                                            <td class="text-center">
                                                <a href="detail_isian_rekom.php?id=<?php echo $row['id']; ?>" class="btn btn-info btn-sm"><i class="fas fa-eye"></i> Detail</a>
                                                
                                                <?php if ($st_db == 'Draft' || $st_db == 'Perlu Revisi'): ?>
                                                <a href="edit_rekom.php?id=<?php echo $row['id']; ?>" class="btn btn-warning btn-sm"><i class="fas fa-edit"></i></a>
                                                <?php endif; ?>
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

<script>
$(function () {
    // Inisialisasi DataTables
    if ($("#tabelRekom").length) {
        $("#tabelRekom").DataTable({
        "responsive": true, 
        "lengthChange": true, 
        "autoWidth": false,
        "order": [[ 1, "desc" ]], // Urut berdasarkan tanggal (kolom ke-2) descending
        "columnDefs": [ // **PERBAIKAN: Menambahkan columnDefs**
            { "orderable": false, "searchable": false, "targets": [5, 6] } // Status (Index 5) dan Aksi (Index 6)
        ],
        "buttons": ["copy", "csv", "excel", "pdf", "print"]
        }).buttons().container().appendTo('#tabelRekom_wrapper .col-md-6:eq(0)');
    }
});
</script>

<?php
// Tutup koneksi & Footer
if (isset($conn) && $conn) mysqli_close($conn);
include 'template/footer.php';
ob_end_flush(); 
?>