<?php
// FILE: list_semua_tugas_evaluator.php - Halaman Daftar Tugas Evaluator

// =========================================================
// 1. PENGATURAN AWAL: OUTPUT BUFFERING & SESSION
// =========================================================
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- DEKLARASI VARIABEL UNTUK HIGHLIGHT MENU SIDEBAR ---
// Variabel ini diperlukan oleh template/sidebar.php
$page = 'uji_kompetensi';
$sub_page = 'tugas_evaluator';
// -------------------------------------------------------

// 2. PANGGIL FILE PENDUKUNG KRITIS
// Asumsi 'auth_guard.php' dan 'koneksi.php' tersedia di folder yang sama
require_once 'auth_guard.php'; 
require_once 'koneksi.php';    

// ------------------------------------------------------------
// 3. LOGIKA PENGAMBILAN DATA (Dijalankan sebelum template header)
// ------------------------------------------------------------
// Kriteria Evaluator: Peserta yang sudah disetujui oleh Admin (status_pengajuan='DISETUJUI') 
// dan belum selesai dinilai/ditolak oleh Evaluator (catatan_evaluasi IS NULL / IS '')

$sql = "SELECT 
            id,              -- Menggunakan kolom 'id' sebagai kunci utama
            nip,             -- NIP peserta
            nama,            -- Nama peserta
            jabatan,         -- Jabatan peserta
            instansi,        -- Instansi peserta
            jenis_pengajuan, -- Jenis Ujikom (sesuai struktur)
            tanggal_pengajuan,
            status_pengajuan,  -- Status pengajuan
            catatan_evaluasi,  -- Wajib diambil untuk kondisi WHERE
            p1_pns_status,     -- Kolom status untuk kriteria/dokumen (contoh)
            d4_portofolio_status -- Kolom status untuk kriteria/dokumen (contoh)
        FROM 
            pengajuan_ujikom
        WHERE 
            -- HANYA TAMPILKAN PENGAJUAN YANG SUDAH DISETUJUI ADMIN.
            -- ⚠️ PENTING: PASTIKAN NILAI 'DISETUJUI' INI SESUAI DENGAN DATA DI DB ANDA.
            status_pengajuan = 'DISETUJUI' AND 
            -- Tambahkan kondisi agar tugas yang sudah dinilai (catatan_evaluasi terisi) tidak muncul lagi.
            -- Mengecek NULL ATAU STRING KOSONG (''):
            (catatan_evaluasi IS NULL OR catatan_evaluasi = '')
        ORDER BY 
            tanggal_pengajuan ASC";

$result = mysqli_query($conn, $sql);

// Cek apakah query berhasil. Jika gagal, tampilkan error yang lebih informatif.
if (!$result) {
    // Tampilkan pesan error dan hentikan eksekusi
    $error_message = "Query Gagal: " . mysqli_error($conn) . 
                     ". Pastikan tabel 'pengajuan_ujikom' sudah ada dan semua kolom yang diminta tersedia.";
    exit("
        <div style='padding: 20px; border: 1px solid red; background-color: #fdd; margin: 20px;'>
            <strong>FATAL DATABASE ERROR:</strong> " . htmlspecialchars($error_message) . "
            <p><strong>SOLUSI:</strong> Harap cek kembali nama kolom dan pastikan nilai STATUS_PENGAJUAN di kueri SQL sudah benar (saat ini disetel ke 'DISETUJUI').</p>
        </div>
    ");
}

// Menghitung jumlah baris hasil kueri (diperlukan untuk card info)
$num_rows = ($result && mysqli_num_rows($result) > 0) ? mysqli_num_rows($result) : 0; 
?>

<?php 
// 4. PANGGIL FILE TEMPLATE
// Asumsi path template adalah 'template/' relatif dari file ini
require_once 'template/header.php'; 
require_once 'template/sidebar.php'; 
?>
    <!-- Konten Utama -->
    <div class="content-wrapper">
        <section class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1>Daftar Tugas Evaluasi Uji Kompetensi</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Tugas Evaluator</li>
                        </ol>
                    </div>
                </div>
            </div>
        </section>

        <!-- Bagian Card Info -->
        <section class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-lg-3 col-6">
                        <!-- small box -->
                        <div class="small-box bg-info">
                            <div class="inner">
                                <h3><?php echo $num_rows; ?></h3>
                                <p>Tugas Menunggu Evaluasi</p>
                            </div>
                            <div class="icon">
                                <i class="ion ion-ios-list-outline"></i>
                            </div>
                            <!-- Link ini mengarah ke ID tabel di bawah -->
                            <a href="#dataTugasEvaluator" class="small-box-footer">Lihat Detail <i class="fas fa-arrow-circle-down"></i></a>
                        </div>
                    </div>
                    <!-- Tambahkan card lain jika diperlukan -->
                </div>
                
                <!-- Main Content: Daftar Tugas Evaluasi -->
                <div class="row">
                    <div class="col-12">
                        <div class="card card-primary card-outline">
                            <div class="card-header">
                                <h3 class="card-title">Daftar Peserta Siap Dinilai (Lulus Verifikasi)</h3>
                            </div>
                            <div class="card-body">
                                <p class="text-muted">Tabel di bawah menampilkan daftar berkas peserta Ujikom yang telah **Lulus Verifikasi Administrasi** dan memerlukan **Penilaian Ahli Kompetensi** (Portofolio, Wawancara, dll.) oleh Anda sebagai Evaluator.</p>
                                <div class="table-responsive">
                                    <table id="dataTugasEvaluator" class="table table-bordered table-striped">
                                        <thead>
                                            <tr>
                                                <th style="width: 5%;">No.</th>
                                                <th>Nama Peserta</th>
                                                <th>NIP</th>
                                                <th>Jabatan</th>
                                                <th>Instansi</th>
                                                <th>Jenis Ujikom</th>
                                                <th>Tgl. Pengajuan</th>
                                                <th style="width: 15%;">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $no = 1;
                                            // Reset pointer result set agar bisa digunakan di tabel (jika sebelumnya digunakan untuk hitung $num_rows)
                                            if ($result && mysqli_num_rows($result) > 0) {
                                                mysqli_data_seek($result, 0); 
                                            }

                                            // Hanya jalankan loop jika kueri berhasil dan ada hasilnya
                                            if ($result && mysqli_num_rows($result) > 0):
                                                while ($row = mysqli_fetch_assoc($result)):
                                            ?>
                                            <tr>
                                                <td><?php echo $no++; ?></td>
                                                <td><?php echo htmlspecialchars($row['nama']); ?></td>
                                                <td><?php echo htmlspecialchars($row['nip']); ?></td>
                                                <td><?php echo htmlspecialchars($row['jabatan']); ?></td>
                                                <td><?php echo htmlspecialchars($row['instansi']); ?></td>
                                                <td><?php echo htmlspecialchars($row['jenis_pengajuan']); ?></td>
                                                <td><?php echo date('d M Y', strtotime($row['tanggal_pengajuan'])); ?></td>
                                                <td>
                                                    <!-- Tombol Aksi untuk Penilaian. Menggunakan kolom 'id' sebagai parameter -->
                                                    <a href="form_penilaian_evaluator.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-success" title="Mulai Evaluasi Kompetensi">
                                                        <i class="fas fa-check-square"></i> Nilai
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php 
                                                endwhile; 
                                            else:
                                            ?>
                                            <tr>
                                                <td colspan="8" class="text-center">Tidak ada tugas evaluasi yang menunggu.</td>
                                            </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
    
    <!-- Bagian JS Khusus Halaman Ini (Sebelum footer.php) -->
    <script>
        $(function () {
            // Inisialisasi DataTables
            // Asumsi jQuery dan DataTables sudah dimuat oleh template/footer.php
            if (typeof $.fn.DataTable !== 'undefined') {
                $("#dataTugasEvaluator").DataTable({
                    "responsive": true, 
                    "lengthChange": true, 
                    "autoWidth": false,
                    "order": [[6, "asc"]], // Urutkan berdasarkan tanggal pengajuan
                    "language": {
                        "url": "//cdn.datatables.net/plug-ins/1.11.5/i18n/id.json" // Menggunakan bahasa Indonesia
                    }
                });
            } else {
                 // Error logging jika DataTables belum dimuat
            }
        });
    </script>

<?php
// 5. TUTUP KONTEN DAN PANGGIL FOOTER
require_once 'template/footer.php'; 

// Tutup koneksi database
if (isset($conn) && $conn) {
    mysqli_close($conn);
}

// Tutup output buffering
ob_end_flush();
?>