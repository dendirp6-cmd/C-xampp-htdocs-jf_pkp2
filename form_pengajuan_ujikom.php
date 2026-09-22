<?php
// FILE: form_pengajuan_ujikom.php - Halaman Pilihan Jenis Pengajuan Uji Kompetensi
// Lokasi: C:\xampp\htdocs\jf_pkp2\form_pengajuan_ujikom.php

// --- PENGGUNAAN VARIABEL DEFAULT (HARUS DISEDIAKAN OLEH AUTH & KONEKSI) ---
// Jika Anda memiliki file auth_guard.php, masukkan di sini:
// require_once 'auth_guard.php'; 

// Variabel data pengguna (diambil dari sesi, atau default jika belum ada)
$user_data = [
    'nama' => $user_nama_sesi ?? 'Pengguna JF', 
    'role' => $user_role_sesi ?? 'User', 
];

// --- PENGATURAN JUDUL DAN SIDEBAR ---
// Variabel ini diperlukan oleh sidebar.php untuk mengaktifkan menu
$page = 'ujikom'; // Mengaktifkan menu Uji Kompetensi (Tingkat 1)
// Menggunakan $sub_page yang konsisten agar menu Pengajuan Ujikom (Tingkat 2) terbuka
$sub_page = 'perpindahan_jabatan'; 
$page_title = 'Pilih Jenis Pengajuan Uji Kompetensi'; // Judul Halaman
?>

<?php require_once 'template/header.php'; ?>
    
<?php require_once 'template/navbar.php'; ?>
    
<?php require_once 'template/sidebar.php'; ?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0"><?php echo $page_title; ?></h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="#">Uji Kompetensi</a></li>
                        <li class="breadcrumb-item active">Pengajuan Ujikom</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">

            <div class="jumbotron text-center bg-info p-4 rounded shadow-sm">
                <h1 class="display-5 text-white">Selamat Datang, <?php echo htmlspecialchars($user_data['nama']); ?>!</h1>
                <p class="lead text-white-50">Silakan pilih jenis pengajuan Uji Kompetensi yang sesuai dengan kebutuhan Anda saat ini.</p>
                <hr class="my-3 border-white-50">
                <p class="text-white-50">Setiap jenis pengajuan memiliki persyaratan yang berbeda. Pastikan pilihan Anda tepat.</p>
            </div>
            
            <h2 class="mt-4 mb-3 text-center">Pilihan Jenis Pengajuan</h2>
            
            <div class="row">
                
                <div class="col-md-6">
                    <div class="card card-primary card-outline">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-exchange-alt mr-1"></i> **Perpindahan Jabatan** (Inpassing)
                            </h3>
                        </div>
                        <div class="card-body">
                            <p class="text-justify">
                                Ditujukan bagi Pegawai Negeri Sipil (PNS) yang akan **berpindah** dari jabatan fungsional atau struktural lain ke Jabatan Fungsional Penata Kelola Perumahan (JF-PKP). Proses ini sering disebut **Inpassing** atau Penyesuaian.
                            </p>
                            <p class="text-muted text-sm">
                                *Persyaratan umum meliputi ijazah sesuai, pengalaman teknis, dan surat rekomendasi dari instansi terkait.
                            </p>
                            
                            <a href="form_perpindahan_jabatan.php" class="btn btn-lg btn-primary btn-block mt-4">
                                <i class="fas fa-arrow-right"></i> Ajukan Perpindahan Jabatan
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card card-success card-outline">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-level-up-alt mr-1"></i> **Kenaikan Jabatan/Jenjang**
                            </h3>
                        </div>
                        <div class="card-body">
                            <p class="text-justify">
                                Ditujukan bagi Pejabat Fungsional Penata Kelola Perumahan (JF-PKP) yang ingin **naik jenjang** setingkat lebih tinggi dalam JF yang sama, misalnya dari JF Ahli Muda ke JF Ahli Madya.
                            </p>
                            <p class="text-muted text-sm">
                                *Persyaratan umum meliputi pencapaian Angka Kredit (AK) minimum, masa kerja, dan penilaian kinerja yang baik.
                            </p>
                            
                            <a href="form_kenaikan_jabatan.php" class="btn btn-lg btn-success btn-block mt-4">
                                <i class="fas fa-arrow-right"></i> Ajukan Kenaikan Jabatan
                            </a>
                        </div>
                    </div>
                </div>

            </div>
            
            <div class="card card-warning mt-4">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-exclamation-triangle"></i> Perhatian</h3>
                </div>
                <div class="card-body">
                    <p class="text-danger mb-0">Jika Anda baru pertama kali masuk ke JF-PKP dari jabatan lain, **PILIH PERPINDAHAN JABATAN**.</p>
                </div>
            </div>

        </div>
    </section>
</div>

<?php require_once 'template/footer.php'; ?>