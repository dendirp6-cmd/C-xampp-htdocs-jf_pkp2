<?php
/**
 * FILE: index_pengusul.php
 * DESKRIPSI: Dashboard Utama dengan Logika Notifikasi Kelulusan & Ketidaklulusan + Cek Akses Form
 * PERBAIKAN: Tombol perbaiki diarahkan langsung ke halaman detail berkas masing-masing jenis pengajuan.
 */

ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- LOGIKA TIMEOUT ---
$timeout_duration = 3600; 

if (isset($_SESSION['LAST_ACTIVITY'])) {
    $elapsed_time = time() - $_SESSION['LAST_ACTIVITY'];
    if ($elapsed_time > $timeout_duration) {
        session_unset();
        session_destroy();
        session_start();
        $_SESSION['popup_type'] = 'info';
        $_SESSION['popup_message'] = "Sesi Anda telah berakhir karena tidak ada aktivitas.";
        header("Location: login.php");
        exit;
    }
}
$_SESSION['LAST_ACTIVITY'] = time();

require_once 'auth_guard.php'; 
require_once 'koneksi.php';    

// --- LOGIKA ON/OFF AKSES FORM ---
$form_locked = false;
$cek_akses = mysqli_query($conn, "SELECT status_form_pj FROM tb_admin_update WHERE id = 1");
if ($cek_akses) {
    $status_db = mysqli_fetch_assoc($cek_akses);
    if (!$status_db || $status_db['status_form_pj'] == 0) {
        $form_locked = true; 
    }
}

// --- DATA SESSION ---
$user_email   = $_SESSION['email'] ?? $_SESSION['user_email_sesi'] ?? null;
$user_nama    = $_SESSION['nama_lengkap'] ?? $_SESSION['user_nama_sesi'] ?? 'Pengusul';
$user_nip     = $_SESSION['nip'] ?? $_SESSION['user_nip_sesi'] ?? '';
$user_role    = $_SESSION['role'] ?? $_SESSION['user_role_sesi'] ?? 'Pengusul';
$instansi     = $_SESSION['instansi'] ?? '';
$foto_session = $_SESSION['foto_user_sesi'] ?? ''; 

// --- VALIDASI DATA KOSONG ---
$is_profile_incomplete = false;
if (empty($user_nip) || empty($instansi) || $user_nip == 'NIP Tidak Diketahui' || $instansi == 'Instansi Tidak Diketahui' || $user_nip == '-') {
    $is_profile_incomplete = true;
}

$page = 'dashboard';
$sub_page = 'index_pengusul';
$page_title = 'Dashboard Pengusul';

// --- FETCH DATA TABEL & CATATAN TERBARU ---
$data_pengajuan = [];
$catatan_lulus_terbaru = null;
$id_lulus_terbaru = null; 
$is_form_filled_terbaru = false; 
$status_terbaru = null;
$jenis_pengajuan_terbaru = null;
$tgl_re_regis_terbaru = null;

// Variabel Tambahan untuk Hasil Ujikom Teratas
$file_hasil_ujikom_teratas = '';
$catatan_hasil_ujikom_teratas = '';

// Variabel Tambahan untuk Status Terjadwal
$id_terjadwal_terbaru = null;
$tgl_ujikom_terbaru = null;
$jam_ujikom_terbaru = null;
$lokasi_ujikom_terbaru = null;

try {
    if (empty($user_email)) {
        throw new Exception("Sesi email tidak ditemukan. Silakan login kembali.");
    }

    $sql = "SELECT id, nip, nama, jenis_pengajuan, tanggal_pengajuan, status_pengajuan, 
                    catatan_lulus, jf_pkp_tujuan, angka_kredit, file_sertifikat, 
                    jabatan_sebelum_jafung, f_skp_lengkap,
                    tanggal_tidak_lulus, tanggal_re_registrasi,
                    tanggal_ujikom, jam_ujikom, lokasi_ujikom,
                    file_hasil_ujikom, catatan_hasil_ujikom
            FROM pengajuan_ujikom 
            WHERE (TRIM(jenis_pengajuan) LIKE 'Perpindahan%' OR TRIM(jenis_pengajuan) LIKE 'Kenaikan%')
            AND email = ?  
            ORDER BY tanggal_pengajuan DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $user_email);
    $stmt->execute();
    $result = $stmt->get_result();
    $data_pengajuan = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (!empty($data_pengajuan)) {
        $teratas = $data_pengajuan[0];
        $status_terbaru = $teratas['status_pengajuan'];
        $jenis_pengajuan_terbaru = $teratas['jenis_pengajuan'];
        $tgl_re_regis_terbaru = $teratas['tanggal_re_registrasi'];
        
        // Ambil data file hasil ujikom dari baris pengajuan teratas aktif
        $file_hasil_ujikom_teratas = !empty($teratas['file_hasil_ujikom']) ? $teratas['file_hasil_ujikom'] : '';
        $catatan_hasil_ujikom_teratas = !empty($teratas['catatan_hasil_ujikom']) ? $teratas['catatan_hasil_ujikom'] : '';

        if ($status_terbaru == 'Terjadwal') {
            $id_terjadwal_terbaru = $teratas['id'];
            $tgl_ujikom_terbaru = $teratas['tanggal_ujikom'];
            $jam_ujikom_terbaru = $teratas['jam_ujikom'];
            $lokasi_ujikom_terbaru = $teratas['lokasi_ujikom'];
        }

        foreach ($data_pengajuan as $row) {
            if ($row['status_pengajuan'] == 'Lulus') {
                $catatan_lulus_terbaru = $row['catatan_lulus'];
                $id_lulus_terbaru = $row['id'];
                $is_form_filled_terbaru = !empty($row['jabatan_sebelum_jafung']) || !empty($row['f_skp_lengkap']);
                break; 
            }
        }
    }

} catch (Exception $e) {
    $error_message = "❌ Gagal memuat data: " . htmlspecialchars($e->getMessage());
}

include 'template/header.php'; 
include 'template/sidebar.php';
include 'template/navbar.php';   
?>

<style>
    .alert-custom { border-left: 5px solid #ffc107; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
    .alert-danger-custom { border-left: 5px solid #dc3545; box-shadow: 0 2px 10px rgba(0,0,0,0.1); background-color: #f8d7da; color: #721c24; }
    .text-nowrap { white-space: nowrap; }
    .bg-teal { background-color: #20c997 !important; color: #fff; }
    
    .welcome-text {
        font-size: 1.15rem;
        font-weight: 700;
        letter-spacing: 0.5px;
        text-shadow: 1px 1px 2px rgba(0,0,0,0.2);
        color: #ffffff;
    }

    .note-container {
        background: rgba(255, 255, 255, 0.15);
        border-radius: 12px;
        padding: 15px;
        border: 1px dashed rgba(255, 255, 255, 0.4);
        display: flex;
        align-items: flex-start;
        transition: all 0.3s ease;
    }
    .note-container:hover {
        background: rgba(255, 255, 255, 0.25);
        transform: translateY(-2px);
    }
    .note-icon {
        font-size: 2rem;
        margin-right: 15px;
        color: #ffc107;
        animation: pulse 2s infinite;
    }
    @keyframes pulse {
        0% { transform: scale(1); opacity: 1; }
        50% { transform: scale(1.1); opacity: 0.8; }
        100% { transform: scale(1); opacity: 1; }
    }

    .btn-quick {
        background: rgba(255,255,255,0.15);
        color: white;
        border: 1px solid rgba(255,255,255,0.3);
        margin-bottom: 8px;
        text-align: left;
        transition: 0.3s;
    }
    .btn-quick:hover:not([disabled]) {
        background: #ffc107;
        color: #0c3b7d;
        transform: translateX(5px);
    }
    
    .note-lulus {
        display: block;
        font-size: 0.75rem;
        margin-top: 5px;
        color: #155724;
        background-color: #d4edda;
        border: 1px solid #c3e6cb;
        padding: 2px 5px;
        border-radius: 4px;
        max-width: 200px;
        white-space: normal;
    }

    .note-tidak-lulus {
        display: block;
        font-size: 0.72rem;
        margin-top: 5px;
        color: #721c24;
        background-color: #f8d7da;
        border: 1px solid #f5c6cb;
        padding: 4px 6px;
        border-radius: 4px;
        line-height: 1.3;
    }
</style>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6"><h1 class="m-0"><?php echo $page_title; ?></h1></div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">

            <?php if ($form_locked): ?>
            <div class="alert alert-danger-custom alert-dismissible fade show py-3 px-3 mb-4" role="alert">
                <h5 class="mb-1" style="font-size: 1.1rem;"><i class="icon fas fa-lock"></i> Akses Pengajuan Ditutup!</h5>
                <p class="mb-0 small">Mohon maaf, saat ini <strong>Form Pengajuan Baru belum bisa diakses</strong> karena sedang ditutup oleh Admin. Silakan cek berkala atau hubungi bantuan.</p>
            </div>
            <?php endif; ?>

            <?php if ($is_profile_incomplete): ?>
            <div class="alert alert-warning alert-dismissible fade show alert-custom py-2 px-3" role="alert">
                <h5 class="mb-1" style="font-size: 1.1rem;"><i class="icon fas fa-exclamation-triangle"></i> Profil Belum Lengkap!</h5>
                <p class="mb-2 small">Data <strong>NIP</strong> atau <strong>Instansi</strong> Anda masih kosong. Harap lengkapi data profil Anda.</p>
                <button type="button" class="btn btn-warning btn-sm font-weight-bold shadow-sm" style="border-radius: 8px;" data-toggle="modal" data-target="#modalLengkapiProfil">
                    <i class="fas fa-user-edit mr-1"></i> Lengkapi Data Profil Sekarang
                </button>
            </div>
            <?php endif; ?>

            <div class="row align-items-stretch">
                <div class="col-md-7 mb-4">
                    <div class="card h-100 mb-0" style="background: linear-gradient(to right, #0c3b7d, #5160b9); border-radius: 15px;">
                        <div class="card-header border-0 bg-transparent pt-4">
                            <h5 class="welcome-text mb-0">Informasi Pengajuan & Kelulusan:</h5>
                        </div>
                        <div class="card-body text-white pb-4">
                            
                            <?php if (!empty($file_hasil_ujikom_teratas)): ?>
                                <div class="note-container mb-3" style="border: 1px solid #28a745; background: rgba(40, 167, 69, 0.25);">
                                    <div class="note-icon" style="color: #28a745;">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                    <div class="w-100">
                                        <h4 class="font-weight-bold mb-1" style="color: #fff; font-size: 1.35rem;">Selamat! Anda Dinyatakan Lulus Ujikom</h4>
                                        <p class="mb-3 small" style="line-height: 1.5; color: #f8f9fa;">
                                            Surat Hasil Uji Kompetensi Anda telah resmi diterbitkan oleh PPSDM. Silakan klik tombol di bawah untuk mengunduh berkas.
                                        </p>
                                        
                                        <a href="./uploads/hasil_ujikom/<?php echo $file_hasil_ujikom_teratas; ?>" target="_blank" class="btn btn-warning btn-sm font-weight-bold shadow-sm mb-3 px-3 py-2" style="border-radius: 8px; color: #0c3b7d;">
                                            <i class="fas fa-file-download mr-2"></i> Download Surat Hasil Ujikom
                                        </a>

                                        <?php if (!empty($catatan_hasil_ujikom_teratas)): ?>
                                            <div class="p-2" style="background: rgba(255,255,255,0.1); border-radius: 6px; border-left: 3px solid #ffc107;">
                                                <small class="d-block text-warning font-weight-bold mb-1"><i class="fas fa-comment-dots mr-1"></i> Catatan PPSDM:</small>
                                                <p class="mb-0 font-italic small" style="color: #e2e8f0;">"<?= htmlspecialchars($catatan_hasil_ujikom_teratas); ?>"</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                            <?php elseif ($status_terbaru == 'Tidak Lulus'): ?>
                                <?php 
                                    $skrg = date('Y-m-d'); 
                                    $is_masa_tunggu_berakhir = (!empty($tgl_re_regis_terbaru) && $skrg >= $tgl_re_regis_terbaru);
                                ?>

                                <?php if ($is_masa_tunggu_berakhir): ?>
                                    <div class="note-container mb-3" style="border: 1px solid #28a745; background: rgba(40, 167, 69, 0.2);">
                                        <div class="note-icon" style="color: #28a745;">
                                            <i class="fas fa-check-circle"></i>
                                        </div>
                                        <div>
                                            <h6 class="font-weight-bold mb-1" style="color: #fff;">Kesempatan Baru Tersedia</h6>
                                            <p class="mb-2 small" style="line-height: 1.5; color: #f8f9fa;">
                                                Selamat anda sudah bisa mengajukan perpindahan jabatan lagi, silakan tunggu jadwal pendaftaran perpindahan gelombang berikutnya.
                                            </p>
                                            <a href="form_perpindahan_jabatan.php" class="btn btn-warning btn-sm font-weight-bold shadow-sm" style="border-radius: 8px;">
                                                <i class="fas fa-paper-plane mr-1"></i> Klik di sini untuk Mengajukan
                                            </a>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="note-container mb-3" style="border: 1px solid #f8d7da; background: rgba(220, 53, 69, 0.2);">
                                        <div class="note-icon" style="color: #f8d7da;">
                                            <i class="fas fa-times-circle"></i>
                                        </div>
                                        <div>
                                            <h6 class="font-weight-bold mb-1" style="color: #f8d7da;">Mohon Maaf, Anda Tidak Lulus</h6>
                                            <p class="mb-0 small" style="line-height: 1.5; color: #f8f9fa;">
                                                Pengajuan <strong><?= htmlspecialchars($jenis_pengajuan_terbaru); ?></strong> Anda dinyatakan <span class="badge badge-danger">Tidak Lulus</span>.
                                                <?php if (!empty($tgl_re_regis_terbaru)): ?>
                                                    <br>Anda dapat melakukan Re-Registrasi mulai tanggal: <strong><?= date('d/m/Y', strtotime($tgl_re_regis_terbaru)); ?></strong>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                    </div>
                                <?php endif; ?>

                            <?php elseif ($status_terbaru == 'Terjadwal'): ?>
                                <div class="note-container mb-3" style="border: 1px solid #17a2b8; background: rgba(23, 162, 184, 0.2);">
                                    <div class="note-icon" style="color: #17a2b8;">
                                        <i class="fas fa-calendar-check"></i>
                                    </div>
                                    <div>
                                        <h6 class="font-weight-bold mb-1" style="color: #fff;">Selamat! Anda Dinyatakan Lulus Administrasi</h6>
                                        <p class="mb-1 small" style="line-height: 1.5; color: #f8f9fa;">
                                            Anda dapat mengikuti ujian pada:<br>
                                            <i class="fas fa-calendar-day mr-1"></i> Tanggal: <strong><?= (!empty($tgl_ujikom_terbaru)) ? date('d/m/Y', strtotime($tgl_ujikom_terbaru)) : '-'; ?></strong><br>
                                            <i class="fas fa-clock mr-1"></i> Jam: <strong><?= $jam_ujikom_terbaru ?? '-'; ?></strong><br>
                                            <i class="fas fa-map-marker-alt mr-1"></i> Lokasi: <strong><?= $lokasi_ujikom_terbaru ?? '-'; ?></strong>
                                        </p>
                                        <a href="lihat_jadwal.php?id=<?= $id_terjadwal_terbaru; ?>" class="btn btn-info btn-xs mt-1 shadow-sm font-weight-bold">
                                            <i class="fas fa-external-link-alt mr-1"></i>Lihat Selengkapnya
                                        </a>
                                    </div>
                                </div>

                            <?php elseif ($status_terbaru && !in_array($status_terbaru, ['Lulus', 'Tidak Lulus', 'Terjadwal'])): ?>
                                <div class="note-container mb-3" style="border: 1px solid #ffc107; background: rgba(255, 193, 7, 0.1);">
                                    <div class="note-icon" style="font-size: 2.5rem;">
                                        <i class="fas fa-bell"></i>
                                    </div>
                                    <div>
                                        <h4 class="font-weight-bold mb-2" style="color: #ffc107; font-size: 1.5rem;">Update Status Terakhir</h4>
                                        <p class="mb-0" style="line-height: 1.6; color: #f8f9fa; font-size: 1.15rem;">
                                            Pengajuan <strong><?= htmlspecialchars($jenis_pengajuan_terbaru); ?></strong> Anda saat ini berstatus: 
                                            <span class="badge badge-warning text-dark px-3 py-2" style="font-size: 1.1rem;"><?= htmlspecialchars($status_terbaru); ?></span>
                                        </p>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($id_lulus_terbaru): ?>
                                <div class="note-container">
                                    <div class="note-icon" style="font-size: 2.5rem;">
                                        <i class="fas fa-certificate"></i>
                                    </div>
                                    <div>
                                        <h4 class="font-weight-bold mb-2" style="color: #ffc107; font-size: 1.5rem;">Selamat! Anda Dinyatakan Lulus</h4>
                                        <?php if (!$is_form_filled_terbaru): ?>
                                            <p class="mb-0" style="line-height: 1.6; color: #f8f9fa; font-size: 1.15rem;">
                                                "<?= htmlspecialchars($catatan_lulus_terbaru); ?>"
                                                <br><a href="#" class="text-warning font-weight-bold btn-pilih-jabatan" data-id="<?= $id_lulus_terbaru; ?>" style="text-decoration: underline; font-size: 1.15rem;">Klik di sini</a> untuk perhitungan angka kredit dalam sertifikat/surat rekomendasi
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php elseif (!$status_terbaru): ?>
                                <div class="note-container">
                                    <div class="note-icon" style="color: #fff; opacity: 0.5;">
                                        <i class="fas fa-info-circle"></i>
                                    </div>
                                    <div>
                                        <h6 class="font-weight-bold mb-1">Belum Ada Pengajuan</h6>
                                        <p class="mb-0 small" style="opacity: 0.8;">
                                            Anda belum memiliki riwayat pengajuan. Klik "Buat Pengajuan Baru" untuk memulai.
                                        </p>
                                    </div>
                                </div>
                            <?php elseif ($status_terbaru && !in_array($status_terbaru, ['Lulus', 'Tidak Lulus', 'Terjadwal']) && empty($file_hasil_ujikom_teratas)): ?>
                                <div class="note-container">
                                    <div class="note-icon" style="color: #fff; opacity: 0.5;">
                                        <i class="fas fa-tasks"></i>
                                    </div>
                                    <div>
                                        <h6 class="font-weight-bold mb-1">Pengajuan Sedang Diproses</h6>
                                        <p class="mb-0 small" style="opacity: 0.8;">
                                            Silakan pantau berkala status pengajuan Anda pada tabel di bawah ini.
                                        </p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-5 mb-4">
                    <div class="card h-100 mb-0" style="background: linear-gradient(to right, #0c3b7d, #5160b9); border-radius: 15px;">
                        <div class="card-header border-0 bg-transparent">
                            <h3 class="card-title text-white font-weight-bold"><i class="fas fa-rocket mr-2"></i> Akses Cepat</h3>
                        </div>
                        <div class="card-body p-3 d-flex flex-column justify-content-center">
                            
                            <?php if ($form_locked): ?>
                                <button class="btn btn-block btn-quick btn-sm" disabled style="background: rgba(255,255,255,0.05); color: #ccc; cursor: not-allowed;">
                                    <i class="fas fa-lock mr-2"></i> Buat Pengajuan (Ditutup)
                                </button>
                            <?php else: ?>
                                <a href="form_perpindahan_jabatan.php" class="btn btn-block btn-quick btn-sm">
                                    <i class="fas fa-plus-circle mr-2"></i> Buat Pengajuan Baru
                                </a>
                            <?php endif; ?>

                            <a href="https://wa.me/62812345678" target="_blank" class="btn btn-block btn-quick btn-sm">
                                <i class="fab fa-whatsapp mr-2"></i> Butuh Bantuan? (Hubungi Admin via WhatsApp)
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-12">
                    <div class="card card-indigo card-outline">
                        <div class="card-header">
                            <h3 class="card-title font-weight-bold"><i class="fas fa-history mr-1"></i> Daftar Pengajuan Saya</h3>
                        </div>
                        <div class="card-body">
                            <table id="tabelDaftarSaya" class="table table-bordered table-striped responsive w-100">
                                <thead class="bg-indigo text-white">
                                    <tr>
                                        <th>No.</th>
                                        <th>NIP</th>
                                        <th>Nama</th>
                                        <th>Jenis Pengajuan</th>
                                        <th>Status</th>
                                        <th>Angka Kredit</th>
                                        <th>Surat Hasil Ujikom</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $no = 1; 
                                    foreach ($data_pengajuan as $row): 
                                        $status_asli = $row['status_pengajuan'];
                                        $jenis_pengajuan = $row['jenis_pengajuan'];
                                        $catatan_row = $row['catatan_lulus'] ?? '';
                                        $angka_kredit = $row['angka_kredit'] ?? '-';
                                        $file_sertifikat = $row['file_sertifikat'] ?? '';
                                        
                                        $file_hasil_ujikom = !empty($row['file_hasil_ujikom']) ? $row['file_hasil_ujikom'] : '';
                                        $catatan_hasil_ujikom = !empty($row['catatan_hasil_ujikom']) ? $row['catatan_hasil_ujikom'] : '';
                                        
                                        $tgl_mulai = $row['tanggal_tidak_lulus'];
                                        $tgl_selesai = $row['tanggal_re_registrasi'];

                                        $is_form_filled = !empty($row['jabatan_sebelum_jafung']) || !empty($row['f_skp_lengkap']);

                                        $badge_class = 'bg-secondary';
                                        switch ($status_asli) {
                                            case 'Lulus Administrasi':        $badge_class = 'bg-primary'; break;
                                            case 'Disetujui':                 $badge_class = 'bg-success'; break;
                                            case 'Proses Evaluasi Evaluator': $badge_class = 'bg-purple'; break;
                                            case 'Proses Evaluasi PPSDM' :    $badge_class = 'bg-lime'; break;
                                            case 'Proses Direktur' :          $badge_class = 'bg-indigo'; break;
                                            case 'Perlu Perbaikan':           $badge_class = 'bg-danger'; break;
                                            case 'Kembali ke Verifikator':    $badge_class = 'bg-warning'; break;
                                            case 'Menunggu Verifikasi':       $badge_class = 'bg-warning'; break;
                                            case 'Verifikasi Dokumen':        $badge_class = 'bg-info'; break;
                                            case 'Menunggu Jadwal Ujikom':    $badge_class = 'bg-fuchsia'; break;
                                            case 'Disetujui Evaluator':       $badge_class = 'bg-success'; break;
                                            case 'Terjadwal':                 $badge_class = 'bg-primary'; break;
                                            case 'Lulus':                     $badge_class = 'bg-success'; break;
                                            case 'Tidak Lulus':               $badge_class = 'bg-danger'; break;
                                        }

                                        $jenis_badge = 'badge-secondary';
                                        $url_detail = 'detail_isian.php'; 

                                        if (stripos($jenis_pengajuan, 'Perpindahan') !== false) {
                                            $jenis_badge = 'badge-info';
                                            $url_detail = 'detail_isian.php';
                                        } elseif (stripos($jenis_pengajuan, 'Kenaikan') !== false) {
                                            $jenis_badge = 'bg-teal';
                                            $url_detail = 'detail_isian_kenaikan.php';
                                        }
                                    ?>
                                    <tr>
                                        <td><?php echo $no++; ?></td>
                                        <td><?php echo htmlspecialchars($row['nip']); ?></td>
                                        <td><strong><?php echo htmlspecialchars($row['nama']); ?></strong></td>
                                        <td><span class="badge <?php echo $jenis_badge; ?> px-2 py-1"><?php echo htmlspecialchars($jenis_pengajuan); ?></span></td>
                                        <td>
                                            <span class="badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($status_asli); ?></span>
                                            
                                            <?php if ($status_asli == 'Lulus' && !empty($catatan_row) && !$is_form_filled): ?>
                                                <small class="note-lulus shadow-sm">
                                                    <i class="fas fa-info-circle mr-1"></i> <?= htmlspecialchars($catatan_row); ?>
                                                </small>
                                            <?php endif; ?>

                                            <?php if ($status_asli == 'Tidak Lulus' && !empty($tgl_mulai)): ?>
                                                <small class="note-tidak-lulus shadow-sm">
                                                    <i class="fas fa-calendar-times mr-1"></i> 
                                                    <strong>Dinyatakan Tidak Lulus Pada:</strong> <?= date('d/m/Y', strtotime($tgl_mulai)); ?><br>
                                                    <i class="fas fa-redo mr-1"></i> 
                                                    <strong>Dapat Mendaftar Kembali Pada:</strong> <?= (!empty($tgl_selesai)) ? date('d/m/Y', strtotime($tgl_selesai)) : '-'; ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?= $angka_kredit; ?></strong>
                                        </td>
                                        <td class="text-center">
                                            <?php if (!empty($file_hasil_ujikom)): ?>
                                                <a href="./uploads/hasil_ujikom/<?php echo $file_hasil_ujikom; ?>" target="_blank" class="btn btn-success btn-xs font-weight-bold" title="Lihat Hasil Ujikom">
                                                    <i class="fas fa-file-download mr-1"></i> Unduh Hasil
                                                </a>
                                                <?php if (!empty($catatan_hasil_ujikom)): ?>
                                                    <small class="d-block text-left text-muted mt-1" style="font-size: 0.7rem; max-width: 150px; white-space: normal;">
                                                        <strong>Catatan:</strong> <?php echo htmlspecialchars($catatan_hasil_ujikom); ?>
                                                    </small>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-nowrap">
                                            <a href="<?php echo $url_detail; ?>?id=<?php echo $row['id']; ?>" class="btn btn-primary btn-sm" title="Lihat Detail">
                                                <i class="fas fa-eye"></i> Lihat
                                            </a>
                                            
                                            <?php if ($status_asli == 'Terjadwal'): ?>
                                            <a href="lihat_jadwal.php?id=<?php echo $row['id']; ?>" class="btn btn-warning btn-sm" title="Lihat Jadwal">
                                                <i class="fas fa-calendar-alt"></i> Lihat Jadwal
                                            </a>
                                            <?php endif; ?>

                                            <?php if ($status_asli == 'Lulus'): ?>
                                                <?php if ($is_form_filled): ?>
                                                    <?php if (!empty($file_sertifikat)): ?>
                                                        <a href="generate_sertifikat.php?id=<?= $row['id']; ?>" class="btn btn-info btn-sm" title="Unduh Sertifikat">
                                                            <i class="fas fa-download"></i> Unduh Sertifikat
                                                        </a>
                                                    <?php else: ?>
                                                        <button class="btn btn-success btn-sm disabled" title="Menunggu Sertifikat dari Admin" disabled>
                                                            <i class="fas fa-clock"></i> Proses Sertifikat
                                                        </button>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <a href="#" class="btn btn-success btn-sm btn-pilih-jabatan" data-id="<?php echo $row['id']; ?>" title="Isi Formulir Sertifikat">
                                                        <i class="fas fa-file-signature"></i> Isi Formulir
                                                    </a>
                                                <?php endif; ?>
                                            <?php endif; ?>

                                            <!-- PERBAIKAN LOGIKA: Mengubah button menjadi tag anchor (a) untuk langsung dialihkan ke halaman detail -->
                                            <?php if ($status_asli == 'Perlu Perbaikan'): ?>
                                            <a href="<?php echo $url_detail; ?>?id=<?php echo $row['id']; ?>" class="btn btn-danger btn-sm text-white font-weight-bold" title="Perbaiki Data">
                                                <i class="fas fa-edit"></i> Perbaiki
                                            </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<div class="modal fade" id="modalPilihJenisJabatan" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius: 15px;">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title font-weight-bold"><i class="fas fa-question-circle mr-2"></i> Pilih Jenis Perpindahan</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <p class="mb-3">Tentukan jenis Perpindahan Jabatan yang anda lakukan:</p>
                <form id="formPilihJabatan">
                    <input type="hidden" id="pilih_jabatan_id" value="">
                    
                    <div class="custom-control custom-radio mb-3">
                        <input type="radio" id="jabatan_struktural" name="jenis_jabatan" class="custom-control-input" value="Pelaksana_Perbendaharaan_Struktural" required>
                        <label class="custom-control-label" for="jabatan_struktural" style="cursor: pointer; line-height: 1.8;">
                            Dari <strong style="color: #1e40af; background: #dbeafe; padding: 3px 8px; border-radius: 6px;">Jabatan Pelaksana / Struktural / Perbendaharaan</strong> 
                            ke <strong style="color: #065f46; background: #d1fae5; padding: 3px 8px; border-radius: 6px;">Jabatan Fungsional</strong>
                        </label>
                    </div>

                    <div class="custom-control custom-radio mb-3">
                        <input type="radio" id="jabatan_fungsional" name="jenis_jabatan" class="custom-control-input" value="Fungsional" required>
                        <label class="custom-control-label" for="jabatan_fungsional" style="cursor: pointer; line-height: 1.8;">
                            Dari <strong style="color: #065f46; background: #d1fae5; padding: 3px 8px; border-radius: 6px;">Jabatan Fungsional</strong> 
                            ke <strong style="color: #065f46; background: #d1fae5; padding: 3px 8px; border-radius: 6px;">Jabatan Fungsional</strong>
                        </label>
                    </div>
                </form> 
            </div>
            <div class="modal-footer border-top-0 pt-0">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-success px-4 font-weight-bold" id="btnLanjutSertifikat">
                    Lanjut <i class="fas fa-arrow-right ml-1"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalLengkapiProfil" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius: 15px;">
            <div class="modal-header bg-warning">
                <h5 class="modal-title font-weight-bold"><i class="fas fa-user-edit mr-2"></i> Lengkapi Data Profil</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <form id="formLengkapiProfil">
                <div class="modal-body">
                    <div class="form-group">
                        <label>NIP User</label>
                        <input type="text" name="nip_user" class="form-control" placeholder="Masukkan NIP" value="<?= ($user_nip == 'NIP Tidak Diketahui' || $user_nip == '-') ? '' : $user_nip; ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Instansi</label>
                        <input type="text" name="instansi" class="form-control" placeholder="Masukkan Nama Instansi" value="<?= ($instansi == 'Instansi Tidak Diketahui') ? '' : $instansi; ?>" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary" id="btnSimpanProfil">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'template/footer.php'; ?>

<script>
$(document).ready(function() {
    $("#tabelDaftarSaya").DataTable({
        "responsive": true,
        "autoWidth": false,
        "order": [[0, "asc"]]
    });

    $(document).on('click', '.btn-pilih-jabatan', function(e) {
        e.preventDefault();
        const idPengajuan = $(this).data('id');
        $('#pilih_jabatan_id').val(idPengajuan);
        $('input[name="jenis_jabatan"]').prop('checked', false); 
        $('#modalPilihJenisJabatan').modal('show');
    });

    $('#btnLanjutSertifikat').on('click', function() {
        const selectedJabatan = $('input[name="jenis_jabatan"]:checked').val();
        const idPengajuan = $('#pilih_jabatan_id').val();

        if (!selectedJabatan) {
            alert('Mohon pilih salah satu jenis asal jabatan terlebih dahulu.');
            return;
        }

        window.location.href = 'form_sertifikat.php?id=' + idPengajuan + '&asal_jabatan=' + encodeURIComponent(selectedJabatan);
    });

    $('#formLengkapiProfil').on('submit', function(e) {
        e.preventDefault();
        const btn = $('#btnSimpanProfil');
        btn.html('<i class="fas fa-spinner fa-spin"></i> Menyimpan...').attr('disabled', true);

        $.ajax({
            url: 'update_profil.php',
            type: 'POST',
            data: $(this).serialize(),
            success: function(response) {
                try {
                    const res = typeof response === 'object' ? response : JSON.parse(response);
                    if (res.status === 'success') {
                        alert(res.message);
                        location.reload(); 
                    } else {
                        alert('Gagal: ' + res.message);
                        btn.html('Simpan Perubahan').attr('disabled', false);
                    }
                } catch (e) {
                    alert("Terjadi kesalahan format data.");
                    btn.html('Simpan Perubahan').attr('disabled', false);
                }
            },
            error: function() {
                alert('Terjadi kesalahan koneksi ke server.');
                btn.html('Simpan Perubahan').attr('disabled', false);
            }
        });
    });
});
</script>
<?php 
if (isset($conn)) { $conn->close(); }
ob_end_flush(); 
?>