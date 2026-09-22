<?php
// FILE: list_rekom.php - Halaman Daftar Pengajuan Rekomendasi Formasi

// 1. --- PENGATURAN SESSION & KONEKSI ---
require_once 'auth_guard.php'; 
require_once 'koneksi.php'; 

// Cek koneksi
if (!$conn) {
    die("Fatal Error: Koneksi database tidak tersedia. Mohon cek koneksi.php");
}

// 2. --- PENGATURAN VARIABEL HALAMAN ---
$page = 'rekomendasi'; 
$sub_page = 'list_rekom'; 
$page_title = 'Daftar Data Pengajuan Rekomendasi Formasi'; 
$NAMA_TABEL_REKOM = "rekomendasi_formasi";

// 3. --- LOGIKA PENGAMBILAN DATA ---
$data_rekomendasi = [];
$error_message = null;
$success_message = null;

// Ambil pesan sukses dari session 
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

$query = "SELECT 
            id, 
            tanggal_pengajuan, 
            nama_pengusul, 
            nip, 
            instansi, 
            provinsi, 
            kota_kab, 
            status 
          FROM {$NAMA_TABEL_REKOM} 
          ORDER BY tanggal_pengajuan DESC";

$result = mysqli_query($conn, $query);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $data_rekomendasi[] = $row;
    }
} else {
    $error_message = "Error mengambil data: " . mysqli_error($conn);
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $page_title; ?></title>

    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap4.min.css">
    
    <style>
        .content-wrapper { background: #f4f6f9 !important; }
        .form-container { max-width:1400px; width:100%; padding:18px; margin: 0 auto; }
        .card-custom {border-radius:12px;box-shadow: 0 10px 30px rgba(10,20,30,0.15); border:none;}
        .data-status {
            padding: 5px 10px;
            border-radius: 5px;
            font-weight: bold;
            font-size: 12px;
            text-transform: uppercase;
            white-space: nowrap;
        }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

    <?php include 'template/navbar.php'; ?> 
    <?php include 'template/sidebar.php'; ?> 

    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0"><i class="fas fa-list"></i> <?php echo $page_title; ?></h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right" style="background-color: transparent;">
                            <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                            <li class="breadcrumb-item active">Rekomendasi Formasi</li>
                        </ol>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-12">
                        <a href="input_rekom.php" class="btn btn-success float-right">
                            <i class="fas fa-plus"></i> Input Pengajuan Baru
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <section class="content">
            <div class="container-fluid form-container">
                
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger" role="alert"><?php echo $error_message; ?></div>
                <?php endif; ?>

                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success_message; ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>

                <div class="card card-custom">
                    <div class="card-header border-0">
                        <h3 class="card-title">Data Pengajuan Rekomendasi</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="rekomendasiTable" class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>No.</th>
                                        <th>Tanggal</th>
                                        <th>Nama Pengusul</th>
                                        <th>NIP</th>
                                        <th>Instansi</th>
                                        <th>Provinsi</th>
                                        <th>Kab/Kota</th>
                                        <th>Status</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $no = 1; 
                                    foreach ($data_rekomendasi as $data): 
                                        
                                        $badge_class = '';
                                        // Membersihkan dan mengubah ke huruf besar untuk perbandingan yang konsisten
                                        $status_db = strtoupper(trim($data['status'])); 
                                        $status_display = htmlspecialchars($data['status']);

                                        // Logika Warna & Teks Status
                                        switch ($status_db) {
                                            case 'DIAJUKAN':
                                                $badge_class = 'bg-warning text-dark';
                                                $status_display = 'Menunggu Verifikasi'; // Tampilkan lebih jelas
                                                break;
                                            
                                            case 'MENUNGGU VERIFIKASI':
                                                $badge_class = 'bg-warning text-dark';
                                                $status_display = 'Menunggu Verifikasi';
                                                break;

                                            case 'VERIFIKASI ULANG':
                                                $badge_class = 'bg-info';
                                                $status_display = 'Verifikasi Ulang'; // Memastikan tampilan teks
                                                break;
                                            
                                            case 'DIVERIFIKASI':
                                            case 'DISETUJUI': 
                                            case 'LAYAK':
                                                $badge_class = 'bg-success';
                                                $status_display = 'Disetujui';
                                                break;

                                            case 'DITOLAK':
                                            case 'PERLU REVISI':
                                            case 'PERLU PERBAIKAN':
                                                $badge_class = 'bg-danger';
                                                $status_display = 'Perlu Perbaikan'; // Tampilan umum untuk status yang butuh revisi
                                                break;

                                            default:
                                                $badge_class = 'bg-secondary';
                                                break;
                                        }
                                    ?>
                                    <tr>
                                        <td><?php echo $no++; ?></td>
                                        <td><?php echo date('d-m-Y H:i', strtotime($data['tanggal_pengajuan'])); ?></td>
                                        <td><?php echo htmlspecialchars($data['nama_pengusul']); ?></td>
                                        <td><?php echo htmlspecialchars($data['nip']); ?></td>
                                        <td><?php echo htmlspecialchars($data['instansi']); ?></td> 
                                        <td><?php echo htmlspecialchars($data['provinsi']); ?></td> 
                                        <td><?php echo htmlspecialchars($data['kota_kab']); ?></td>

                                        <td><span class="data-status <?php echo $badge_class; ?>"><?php echo $status_display; ?></span></td>
                                        
                                        <td>
                                            <a href="eval_rekom.php?id=<?php echo htmlspecialchars($data['id']); ?>" 
                                                class="btn btn-sm btn-info" title="Lihat Detail">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </section>
    </div>

    <?php include 'template/footer.php'; ?> 

</div>

<script src="js/jquery.min.js"></script>
<script src="js/bootstrap.bundle.min.js"></script>
<script src="js/adminlte.min.js"></script>

<script>
$(document).ready(function() {
    // Inisialisasi DataTables
    $('#rekomendasiTable').DataTable({
        "paging": true,
        "lengthChange": true,
        "searching": true,
        "ordering": true,
        "info": true,
        "autoWidth": false,
        "responsive": true,
        "order": [[1, "desc"]]
    });
    
    // Auto-hide alert setelah 5 detik
    $(".alert-success").fadeTo(5000, 500).slideUp(500, function(){
        $(".alert-success").slideUp(500);
    });
});
</script>

<?php
if (isset($conn) && $conn) {
    mysqli_close($conn);
}
?>
</body>
</html>