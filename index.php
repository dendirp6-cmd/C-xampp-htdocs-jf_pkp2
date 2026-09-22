<?php
// 1. Inisialisasi Session - Wajib paling atas
session_start();

// 2. Load Koneksi Database
require_once 'koneksi.php';

/** 
 * PERBAIKAN LOGIKA KONEKSI:
 * Memastikan $koneksi mengambil variabel yang tepat dari koneksi.php
 */
if (isset($conn)) {
    $koneksi = $conn;
} elseif (isset($db)) {
    $koneksi = $db;
} else {
    // Fallback jika variabel tidak ditemukan namun file koneksi.php ada
    $koneksi = mysqli_connect("localhost", "root", "", "db_nama_anda"); 
}

// 3. Logika Redirect Aman (Dijalankan sebelum konten render)
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['user_role_sesi'] ?? '';
    $dashboard_map = [
        'user_pengusul'    => 'index_pengusul.php',
        'user_verifikator' => 'index_verifikator.php',
        'user_ppsdm'       => 'index_ppsdm.php',
        'user_kasubdit'    => 'index_kasubdit.php',
        'user_direktur'    => 'index_direktur.php',
        'user_evaluator'   => 'index_evaluator.php'
    ];

    if (array_key_exists($role, $dashboard_map) && file_exists($dashboard_map[$role])) {
        header("Location: " . $dashboard_map[$role]);
        exit;
    }
}

// 4. Ambil Data Utama dari Database
$query_portal = mysqli_query($koneksi, "SELECT * FROM tb_admin_update WHERE id = 1 LIMIT 1");
$data_utama = mysqli_fetch_assoc($query_portal);

// Default data jika database kosong
if (!$data_utama) {
    $data_utama = [
        'judul_pengumuman' => 'Pendaftaran Belum Dibuka',
        'file_pengumuman'  => '',
        'kj_tgl_daftar'    => '-',
        'kj_tgl_ujian'     => '-',
        'pj_tgl_daftar'    => '-',
        'pj_tgl_ujian'     => '-',
    ];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Informasi Uji Kompetensi - JFPKP</title>
    
    <!-- Google Fonts & FontAwesome -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

    <style>
        :root {
            --bg-dark: #0a0f1d;
            --card-bg: rgba(18, 26, 47, 0.85);
            --border-glow: rgba(56, 189, 248, 0.25);
            --primary-cyan: #38bdf8;
            --primary-blue: #2563eb;
            --gradient-accent: linear-gradient(135deg, #38bdf8 0%, #3b82f6 100%);
            --gradient-gold: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            --text-title: #ffffff;
            --text-body: #e2e8f0;
            --text-muted: #cbd5e1;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-dark);
            color: var(--text-body);
            margin: 0;
            overflow-x: hidden;
            background-image: 
                radial-gradient(circle at 10% 20%, rgba(56, 189, 248, 0.12) 0%, transparent 40%),
                radial-gradient(circle at 90% 70%, rgba(59, 130, 246, 0.12) 0%, transparent 45%);
            background-attachment: fixed;
        }

        /* Navbar Glassmorphism */
        .navbar {
            background: rgba(10, 15, 29, 0.9);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
            padding: 16px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .navbar-brand {
            color: var(--text-title) !important;
            font-weight: 800;
            font-size: 1.4rem;
            letter-spacing: -0.5px;
        }

        /* Nav Buttons */
        .btn-auth-nav {
            background: var(--gradient-accent);
            color: #ffffff !important;
            padding: 9px 24px;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 700;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(56, 189, 248, 0.3);
            text-decoration: none;
        }

        .btn-auth-nav:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(56, 189, 248, 0.5);
        }

        .btn-regis-nav {
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: var(--text-title) !important;
            padding: 8px 22px;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            transition: 0.3s;
            text-decoration: none;
            background: rgba(255, 255, 255, 0.05);
        }

        .btn-regis-nav:hover {
            background: rgba(255, 255, 255, 0.15);
            border-color: var(--primary-cyan);
        }

        /* Hero Section */
        .hero {
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding-top: 120px;
            padding-bottom: 60px;
            position: relative;
        }

        .hero-title {
            color: var(--text-title);
            font-size: 3rem;
            font-weight: 800;
            line-height: 1.2;
        }

        .text-gradient {
            background: var(--gradient-accent);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero-desc {
            color: #cbd5e1;
            font-size: 1.05rem;
            line-height: 1.7;
        }

        /* Hero Buttons */
        .btn-hero-primary {
            background: var(--gradient-accent);
            color: #ffffff !important;
            border: none;
            padding: 14px 32px;
            border-radius: 50px;
            font-weight: 700;
            transition: all 0.3s ease;
            box-shadow: 0 4px 20px rgba(56, 189, 248, 0.35);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn-hero-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(56, 189, 248, 0.5);
        }

        .btn-hero-outline {
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-title) !important;
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 14px 32px;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn-hero-outline:hover {
            background: rgba(255, 255, 255, 0.12);
            border-color: var(--primary-cyan);
            color: var(--primary-cyan) !important;
            transform: translateY(-3px);
        }

        /* Schedule Container */
        .hero-schedule-container {
            background: var(--card-bg);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid var(--border-glow);
            border-radius: 28px;
            padding: 32px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
        }

        /* Announcement Card */
        .pembukaan-ujikom {
            background: rgba(245, 158, 11, 0.1);
            border: 1px solid rgba(245, 158, 11, 0.4);
            border-radius: 20px;
            padding: 22px;
            margin-bottom: 25px;
            text-align: center;
        }

        .pembukaan-ujikom h6 {
            color: #facc15 !important;
            font-weight: 800;
            letter-spacing: 0.5px;
        }

        .pembukaan-ujikom p {
            color: #f8fafc !important;
            font-size: 0.9rem;
        }

        .btn-download-surat {
            background: var(--gradient-gold);
            color: #000000 !important;
            font-size: 0.85rem;
            font-weight: 800;
            padding: 10px 24px;
            border-radius: 50px;
            text-decoration: none;
            display: inline-block;
            margin-top: 10px;
            transition: 0.3s;
            box-shadow: 0 4px 15px rgba(245, 158, 11, 0.3);
        }

        .btn-download-surat:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(245, 158, 11, 0.5);
        }

        /* Schedule Item Cards */
        .hero-schedule-item {
            background: rgba(10, 15, 29, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-left: 4px solid var(--primary-cyan);
            border-radius: 16px;
            padding: 20px;
            height: 100%;
            transition: all 0.3s ease;
        }

        .hero-schedule-item:hover {
            border-color: rgba(56, 189, 248, 0.5);
            transform: translateY(-2px);
            background: rgba(10, 15, 29, 0.95);
        }

        .schedule-label {
            color: #94a3b8;
            font-size: 0.8rem;
            font-weight: 600;
            margin-bottom: 2px;
        }

        .schedule-value {
            color: #ffffff !important;
            font-weight: 700;
            font-size: 0.9rem;
        }

        /* Badges */
        .badge-cyan {
            background: rgba(56, 189, 248, 0.15);
            color: var(--primary-cyan);
            border: 1px solid rgba(56, 189, 248, 0.3);
            padding: 6px 16px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .badge-purple {
            background: rgba(168, 85, 247, 0.15);
            color: #c084fc;
            border: 1px solid rgba(168, 85, 247, 0.3);
            padding: 6px 16px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        /* Footer Style Fix */
        .footer { 
            background: rgba(10, 15, 29, 0.98); 
            color: #ffffff !important; 
            padding: 35px 0; 
            text-align: center;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        /* Mobile Responsiveness */
        @media (max-width: 991.98px) {
            .hero {
                padding-top: 100px;
                text-align: center;
            }
            .hero-title {
                font-size: 2.2rem;
            }
            .btn-hero-primary, .btn-hero-outline {
                width: 100%;
            }
            .hero-schedule-container {
                padding: 20px;
            }
        }
    </style>
</head>
<body>

    <!-- Navigation Bar -->
    <nav class="navbar">
        <div class="container d-flex justify-content-between align-items-center">
            <a class="navbar-brand d-flex align-items-center" href="#">
                <i class="fas fa-city me-2 text-gradient"></i>JFPKP
            </a>
            <div class="d-flex gap-2">
                <?php if (!isset($_SESSION['user_id'])): ?>
                    <a href="login.php" class="btn-auth-nav"><i class="fas fa-sign-in-alt me-1"></i> Login</a>
                    <a href="register.php" class="btn-regis-nav"><i class="fas fa-user-plus me-1"></i> Daftar</a>
                <?php else: ?>
                    <a href="logout.php" class="btn-auth-nav bg-danger text-white border-0"><i class="fas fa-sign-out-alt me-1"></i> Logout</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <div class="row align-items-center g-5">
                
                <!-- Sisi Kiri: Deskripsi & Tombol Aksi -->
                <div class="col-lg-6 animate__animated animate__fadeIn">
                    <span class="badge badge-cyan mb-3">
                        <i class="fas fa-shield-alt me-1"></i> PORTAL RESMI JFPKP
                    </span>
                    <h1 class="hero-title mb-3">
                        Sistem Verifikasi <br>
                        <span class="text-gradient">Uji Kompetensi</span>
                    </h1>
                    <p class="hero-desc mb-4">
                        Platform digital terpadu untuk pengajuan dan verifikasi berkas uji kompetensi Jabatan Fungsional Penata Kelola Perumahan secara transparan, akuntabel, dan efisien.
                    </p>
                    
                    <div class="d-flex flex-column flex-sm-row gap-3">
                        <a href="register.php" class="btn-hero-primary">
                            <i class="fas fa-user-plus me-2"></i>Mulai Pendaftaran
                        </a>
                        <a href="panduan.php" class="btn-hero-outline">
                            <i class="fas fa-book-open me-2"></i>Panduan Sistem
                        </a>
                    </div>
                </div>

                <!-- Sisi Kanan: Pengumuman & Jadwal Ujikom -->
                <div class="col-lg-6 animate__animated animate__fadeInRight">
                    <div class="hero-schedule-container">
                        
                        <!-- Box Pengumuman Admin -->
                        <div class="pembukaan-ujikom">
                            <h6 class="mb-2 text-uppercase">
                                <i class="fas fa-bullhorn me-2"></i><?= htmlspecialchars($data_utama['judul_pengumuman']) ?>
                            </h6>
                            <p class="mb-3">Akses pendaftaran portofolio telah dibuka untuk seluruh peserta JFPKP.</p>
                            
                            <?php if(!empty($data_utama['file_pengumuman'])): ?>
                                <a href="uploads/pengumuman/<?= htmlspecialchars(basename($data_utama['file_pengumuman'])) ?>" 
                                   class="btn-download-surat shadow-sm" download>
                                     <i class="fas fa-file-download me-2"></i>Unduh Surat Pengumuman
                                </a>
                            <?php else: ?>
                                <span class="badge bg-dark text-light border border-secondary">File pengumuman belum diunggah</span>
                            <?php endif; ?>
                        </div>

                        <h5 class="mb-4 text-center fw-bold text-white fs-6">
                            <i class="far fa-calendar-alt me-2 text-gradient"></i>Jadwal Uji Kompetensi 2026
                        </h5>
                        
                        <div class="row g-3">
                            <!-- Card Kenaikan Jenjang -->
                            <div class="col-12 col-md-6">
                                <div class="hero-schedule-item">
                                    <span class="badge badge-cyan mb-3">KENAIKAN JENJANG</span>
                                    <div class="mb-2">
                                        <div class="schedule-label">Pendaftaran:</div>
                                        <div class="schedule-value"><?= htmlspecialchars($data_utama['kj_tgl_daftar']) ?></div>
                                    </div>
                                    <div>
                                        <div class="schedule-label">Selesai:</div>
                                        <div class="schedule-value"><?= htmlspecialchars($data_utama['kj_tgl_ujian']) ?></div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Card Perpindahan Jabatan -->
                            <div class="col-12 col-md-6">
                                <div class="hero-schedule-item" style="border-left-color: #c084fc;">
                                    <span class="badge badge-purple mb-3">PERPINDAHAN JABATAN</span>
                                    <div class="mb-2">
                                        <div class="schedule-label">Pendaftaran:</div>
                                        <div class="schedule-value"><?= htmlspecialchars($data_utama['pj_tgl_daftar']) ?></div>
                                    </div>
                                    <div>
                                        <div class="schedule-label">Selesai:</div>
                                        <div class="schedule-value"><?= htmlspecialchars($data_utama['pj_tgl_ujian']) ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <h5 class="mb-2 fw-bold text-white">JFPKP</h5>
            <p class="mb-0 small text-light opacity-75">
                &copy; 2026 - Kementerian Perumahan dan Kawasan Permukiman. Seluruh Hak Cipta Dilindungi.
            </p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>