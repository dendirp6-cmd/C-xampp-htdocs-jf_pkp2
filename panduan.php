<?php
// Inisialisasi Session jika diperlukan
session_start();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panduan Pendaftaran - JFPKP</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

    <style>
        :root {
            --primary-color: #0f4c75;
            --secondary-color: #3282b8;
            --dark-blue: #1b262c;
            --info-light: #0dcaf0;
            --accent-color: #ffc107;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f0f4f8;
            color: #333;
            overflow-x: hidden;
        }

        /* Header yang lebih compact */
        .header-section {
            background: linear-gradient(135deg, var(--dark-blue) 0%, var(--primary-color) 100%);
            padding: 60px 0 40px;
            color: white;
            text-align: center;
            border-bottom: 5px solid var(--info-light);
        }

        .nav-back {
            position: absolute;
            top: 20px;
            left: 20px;
            z-index: 1000;
        }

        .btn-back {
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.3);
            padding: 8px 20px;
            border-radius: 50px;
            text-decoration: none;
            backdrop-filter: blur(10px);
            transition: 0.3s;
            font-size: 0.9rem;
        }

        .btn-back:hover {
            background: white;
            color: var(--primary-color);
            transform: translateX(-5px);
        }

        /* Card Container */
        .guide-container {
            margin-top: -30px;
        }

        .guide-card {
            background: white;
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.08);
            transition: 0.3s;
        }

        /* Timeline Styles */
        .timeline-wrapper {
            position: relative;
            padding: 20px 0;
        }

        .timeline-item {
            padding: 20px 25px;
            border-left: 4px solid var(--info-light);
            position: relative;
            background: white;
            margin-bottom: 15px;
            border-radius: 0 15px 15px 0;
            transition: all 0.3s ease;
            cursor: default;
        }

        .timeline-item:hover {
            transform: scale(1.02);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-left-color: var(--accent-color);
        }

        .step-number {
            width: 40px;
            height: 40px;
            background: var(--primary-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            position: absolute;
            left: -22px;
            top: 20px;
            border: 3px solid white;
            box-shadow: 0 3px 6px rgba(0,0,0,0.2);
        }

        /* Sidebar Info */
        .tips-box {
            background: linear-gradient(180deg, #ffffff 0%, #eef2f3 100%);
            border-radius: 15px;
            border-top: 4px solid var(--accent-color);
        }

        .icon-box {
            width: 45px;
            height: 45px;
            background: rgba(13, 202, 240, 0.1);
            color: var(--info-light);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            margin-right: 15px;
        }

        .footer { 
            background: var(--dark-blue); 
            color: rgba(255,255,255,0.6); 
            padding: 30px 0; 
            text-align: center;
            margin-top: 50px;
        }

        /* Menghilangkan scrollbar horizontal */
        body { overflow-x: hidden; }
    </style>
</head>
<body>

    <div class="nav-back">
        <a href="index.php" class="btn-back shadow-sm">
            <i class="fas fa-arrow-left me-2"></i> Kembali ke Portal
        </a>
    </div>

    <section class="header-section">
        <div class="container animate__animated animate__fadeIn">
            <h1 class="fw-bold mb-2">Panduan Pengguna</h1>
            <p class="lead opacity-75 mb-0">Alur pendaftaran Uji Kompetensi JFPKP Tahun 2026</p>
        </div>
    </section>

    <div class="container guide-container">
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="timeline-wrapper ms-3">
                    
                    <div class="timeline-item animate__animated animate__fadeInLeft" style="animation-delay: 0.1s;">
                        <div class="step-number">1</div>
                        <h5 class="fw-bold text-primary">Registrasi & Aktivasi Akun</h5>
                        <p class="text-muted small mb-0">Klik tombol <b>Registrasi</b> di halaman utama. Gunakan NIP valid dan akun Gmail aktif. Cek inbox email Anda untuk konfirmasi aktivasi akun sebelum login.</p>
                    </div>

                    <div class="timeline-item animate__animated animate__fadeInLeft" style="animation-delay: 0.2s;">
                        <div class="step-number">2</div>
                        <h5 class="fw-bold text-primary">Lengkapi Profil Pegawai</h5>
                        <p class="text-muted small mb-0">Setelah masuk, pilih menu <b>Profil</b>. Pastikan data jabatan, pangkat, dan unit kerja sudah sesuai. Data ini menjadi dasar validasi berkas Anda.</p>
                    </div>

                    <div class="timeline-item animate__animated animate__fadeInLeft" style="animation-delay: 0.3s;">
                        <div class="step-number">3</div>
                        <h5 class="fw-bold text-primary">Pilih Jenis Uji Kompetensi</h5>
                        <p class="text-muted small mb-0">Buka menu <b>Pendaftaran</b>. Pilih kategori (Kenaikan Jenjang/Perpindahan Jabatan). Perhatikan tanggal penutupan pendaftaran agar tidak terlambat.</p>
                    </div>

                    <div class="timeline-item animate__animated animate__fadeInLeft" style="animation-delay: 0.4s;">
                        <div class="step-number">4</div>
                        <h5 class="fw-bold text-primary">Unggah Portofolio (PDF)</h5>
                        <p class="text-muted small mb-0">Siapkan scan SK Jabatan, PAK, dan bukti kompetensi. Gabungkan atau pisahkan file sesuai instruksi kolom unggah. Maksimal 2MB per file.</p>
                    </div>

                    <div class="timeline-item animate__animated animate__fadeInLeft" style="animation-delay: 0.5s;">
                        <div class="step-number">5</div>
                        <h5 class="fw-bold text-primary">Verifikasi & Pengumuman</h5>
                        <p class="text-muted small mb-0">Status <b>"Menunggu"</b> berarti berkas sedang diperiksa. Jika <b>"Ditolak"</b>, baca alasan penolakan dan segera perbaiki sebelum batas waktu berakhir.</p>
                    </div>

                </div>
            </div>

            <div class="col-lg-4">
                <div class="tips-box p-4 shadow-sm animate__animated animate__fadeInRight">
                    <h6 class="fw-bold mb-4"><i class="fas fa-shield-halved text-warning me-2"></i>Tips Penting</h6>
                    
                    <div class="d-flex mb-3">
                        <div class="icon-box"><i class="fab fa-google"></i></div>
                        <div>
                            <p class="small mb-0"><b>Wajib Akun Gmail</b><br>Gunakan alamat email resmi <b>@gmail.com</b> untuk menjamin notifikasi sistem sampai ke inbox Anda.</p>
                        </div>
                    </div>

                    <div class="d-flex mb-3">
                        <div class="icon-box"><i class="fas fa-file-pdf"></i></div>
                        <div>
                            <p class="small mb-0"><b>Format File</b><br>Gunakan format PDF. Hindari format gambar (JPG/PNG) kecuali diminta oleh sistem.</p>
                        </div>
                    </div>

                    <div class="d-flex mb-3">
                        <div class="icon-box"><i class="fas fa-search"></i></div>
                        <div>
                            <p class="small mb-0"><b>Kualitas Scan</b><br>Pastikan tulisan dan stempel pada dokumen terbaca jelas oleh tim verifikator.</p>
                        </div>
                    </div>

                    <div class="d-flex mb-3">
                        <div class="icon-box"><i class="fas fa-sync"></i></div>
                        <div>
                            <p class="small mb-0"><b>Update Berkala</b><br>Cek dashboard setiap hari selama masa verifikasi pendaftaran berlangsung.</p>
                        </div>
                    </div>

                    <hr>
                    
                    <div class="bg-primary text-white p-3 rounded-3 mt-4">
                        <p class="small mb-2"><i class="fas fa-headset me-2"></i><b>Bantuan Teknis?</b></p>
                        <p class="extra-small mb-0 opacity-75">Hubungi Sekretariat JFPKP melalui grup koordinasi atau Admin PPSDM pada jam kerja (08:00 - 16:00 WIB).</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="footer">
        <div class="container">
            <p class="extra-small mb-0">&copy; 2026 JFPKP - Kementerian Perumahan dan Kawasan Permukiman</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>