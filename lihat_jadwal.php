<?php
/**
 * ==================================================================================
 * FILE: lihat_jadwal.php
 * DESKRIPSI: Menampilkan Detail Jadwal Ujikom (Individu atau Per Gelombang)
 * PERBAIKAN: 
 * - Tampilan Dokumen Surat Pengumuman yang lebih User Friendly
 * - Mapping Dashboard dinamis: Pengusul, Kasubdit, Verifikator, Direktur, PPSDM
 * - Menampilkan Tanggal Ujikom format Rentang (VARCHAR)
 * - Tombol Edit khusus Role user_ppsdm
 * - Perubahan Fungsi: Hapus Catatan PPSDM & Ubah Unduh ke Lihat File
 * ==================================================================================
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'auth_guard.php';
require_once 'koneksi.php';

// 1. AMBIL PARAMETER URL DAN ROLE
$id_pengajuan = isset($_GET['id']) ? intval($_GET['id']) : 0;
$is_bulk = (isset($_GET['mode']) && $_GET['mode'] === 'bulk'); 
$user_role = $_SESSION['role'] ?? ''; 

/**
 * LOGIKA NAVIGASI DASHBOARD BERDASARKAN ROLE
 */
switch ($user_role) {
    case 'user_pengusul':
        $dashboard_url = 'index_pengusul.php';
        break;
    case 'user_kasubdit':
        $dashboard_url = 'index_kasubdit.php';
        break;
    case 'user_verifikator':
        $dashboard_url = 'index_verifikator.php';
        break;
    case 'user_direktur':
        $dashboard_url = 'index_direktur.php';
        break;
    case 'user_ppsdm':
        $dashboard_url = 'index_ppsdm.php';
        break;
    default:
        $dashboard_url = 'index.php'; // Fallback jika role tidak terdaftar
        break;
}

if ($id_pengajuan == 0) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        echo json_encode(['status' => 'error', 'message' => 'ID tidak valid']);
        exit;
    }
    echo "<script>alert('ID Pengajuan tidak valid!'); window.location.href='$dashboard_url';</script>";
    exit;
}

// 2. QUERY DATA
$sql = "SELECT p.id, p.nama, p.nip, p.jenis_pengajuan, p.status_pengajuan, p.gelombang as id_gel,
               p.catatan_evaluator, p.catatan_ppsdm,
               p.tanggal_ujikom, p.jam_ujikom, p.metode_ujikom, 
               p.lokasi_ujikom, p.pakaian_ujikom, p.keterangan_ujikom, p.surat_pengumuman_jadwal,
               g.gelombang as nama_gel_text
        FROM pengajuan_ujikom p
        LEFT JOIN tb_gelombang g ON p.gelombang = g.id
        WHERE p.id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_pengajuan);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();

// 3. LOGIKA DETEKSI AJAX
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    if (!$data) {
        echo json_encode(['status' => 'error', 'message' => 'Data tidak ditemukan']);
    } else {
        $data['tanggal_range'] = $data['tanggal_ujikom'];
        $data['file_path'] = !empty($data['surat_pengumuman_jadwal']) ? "uploads/pengumuman/" . $data['surat_pengumuman_jadwal'] : null;
        echo json_encode($data);
    }
    exit;
}

if (!$data) {
    echo "<script>alert('Data jadwal tidak ditemukan!'); window.location.href='$dashboard_url';</script>";
    exit;
}

require_once 'template/header.php';
require_once 'template/navbar.php';
require_once 'template/sidebar.php';
?>

<style>
    .card-jadwal { border-radius: 20px; border: none; overflow: hidden; }
    .header-banner { background: linear-gradient(135deg, #1d4ed8 0%, #3b82f6 100%); color: white; padding: 40px 30px; }
    .info-box-custom { background: #f8fafc; border-radius: 15px; padding: 20px; height: 100%; border: 1px solid #e2e8f0; }
    .label-custom { color: #64748b; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }
    .value-custom { color: #1e293b; font-size: 1.1rem; font-weight: 600; margin-top: 5px; }
    .status-badge { background: rgba(255,255,255,0.2); backdrop-filter: blur(5px); padding: 8px 15px; border-radius: 50px; font-size: 0.85rem; border: 1px solid rgba(255,255,255,0.3); }
    
    /* Document View Custom Styling */
    .doc-viewer-card { background: #ffffff; border: 1px dashed #cbd5e1; border-radius: 12px; transition: all 0.3s ease; }
    .doc-viewer-card:hover { border-color: #3b82f6; background: #f0f7ff; }
    .file-icon-box { background: #fee2e2; color: #dc2626; width: 50px; height: 50px; display: flex; align-items: center; justify-content: center; border-radius: 10px; font-size: 1.5rem; }
    .btn-view-doc { border-radius: 10px; font-weight: 600; letter-spacing: 0.3px; transition: transform 0.2s; }
    .btn-view-doc:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(220, 38, 38, 0.2); }

    @media print { .btn-print-group { display: none; } .content-wrapper { margin-left: 0 !important; } }
</style>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 font-weight-bold">Informasi Jadwal Pelaksanaan</h1>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <div class="card shadow-lg card-jadwal">
                <div class="header-banner">
                    <div class="row align-items-center">
                        <div class="col-md-7">
                            <span class="status-badge mb-3 d-inline-block"><i class="fas fa-calendar-check mr-2"></i>Status: <?= htmlspecialchars($data['status_pengajuan']); ?></span>
                            
                            <?php if ($is_bulk): ?>
                                <h2 class="font-weight-bold mb-1"><?= htmlspecialchars($data['nama_gel_text'] ?: 'Gelombang Tidak Diketahui'); ?></h2>
                                <p class="lead mb-0">Informasi Jadwal Pelaksanaan Ujian Kompetensi (Massal)</p>
                            <?php else: ?>
                                <h2 class="font-weight-bold mb-1"><?= htmlspecialchars($data['nama']); ?></h2>
                                <p class="lead mb-0">NIP. <?= htmlspecialchars($data['nip']); ?> | <?= htmlspecialchars($data['jenis_pengajuan']); ?></p>
                                <small class="badge badge-warning mt-2"><?= htmlspecialchars($data['nama_gel_text']); ?></small>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-5 text-md-right mt-3 mt-md-0 btn-print-group">
                            <?php if ($user_role === 'user_ppsdm'): ?>
                                <a href="edit_jadwal.php?id=<?= $data['id']; ?>" class="btn btn-warning btn-lg shadow-sm mr-2" style="border-radius: 12px; color: #000; font-weight: bold;">
                                    <i class="fas fa-edit mr-2"></i> Edit Jadwal
                                </a>
                            <?php endif; ?>

                            <button onclick="window.print()" class="btn btn-light btn-lg shadow-sm" style="border-radius: 12px;">
                                <i class="fas fa-print mr-2"></i> Cetak
                            </button>
                        </div>
                    </div>
                </div>

                <div class="card-body p-4">
                    <div class="row">
                        <!-- Tanggal -->
                        <div class="col-md-4 mb-4">
                            <div class="info-box-custom">
                                <div class="label-custom"><i class="fas fa-calendar-day mr-2"></i> Tanggal Pelaksanaan</div>
                                <div class="value-custom">
                                    <?php 
                                    if (!empty($data['tanggal_ujikom']) && $data['tanggal_ujikom'] != '0000-00-00') {
                                        echo htmlspecialchars($data['tanggal_ujikom']);
                                    } else {
                                        echo '<span class="text-danger">Belum ditentukan</span>';
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>

                        <!-- Jam -->
                        <div class="col-md-4 mb-4">
                            <div class="info-box-custom">
                                <div class="label-custom"><i class="fas fa-clock mr-2"></i> Jam Mulai (WIB)</div>
                                <div class="value-custom"><?= (!empty($data['jam_ujikom']) && $data['jam_ujikom'] != '00:00:00') ? date('H:i', strtotime($data['jam_ujikom'])) : '-'; ?> WIB</div>
                            </div>
                        </div>

                        <!-- Metode -->
                        <div class="col-md-4 mb-4">
                            <div class="info-box-custom">
                                <div class="label-custom"><i class="fas fa-laptop-house mr-2"></i> Metode</div>
                                <div class="value-custom"><span class="badge badge-primary"><?= htmlspecialchars($data['metode_ujikom'] ?: 'TBC'); ?></span></div>
                            </div>
                        </div>

                        <!-- Lokasi -->
                        <div class="col-md-8 mb-4">
                            <div class="info-box-custom" style="border-left: 5px solid #3b82f6;">
                                <div class="label-custom"><i class="fas fa-map-marker-alt mr-2"></i> Lokasi / Tautan Meeting</div>
                                <div class="value-custom">
                                    <?php if (filter_var($data['lokasi_ujikom'], FILTER_VALIDATE_URL)): ?>
                                        <a href="<?= htmlspecialchars($data['lokasi_ujikom']); ?>" target="_blank" class="text-primary font-weight-bold text-break">
                                            <i class="fas fa-external-link-alt mr-1"></i> Klik untuk Masuk ke Ruang Ujian
                                        </a>
                                    <?php else: ?>
                                        <?= nl2br(htmlspecialchars($data['lokasi_ujikom'] ?: 'Informasi lokasi menyusul')); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Pakaian -->
                        <div class="col-md-4 mb-4">
                            <div class="info-box-custom">
                                <div class="label-custom"><i class="fas fa-tshirt mr-2"></i> Pakaian</div>
                                <div class="value-custom"><?= htmlspecialchars($data['pakaian_ujikom'] ?: '-'); ?></div>
                            </div>
                        </div>

                        <!-- Surat Pengumuman Jadwal - USER FRIENDLY VERSION -->
                        <div class="col-md-12 mb-4">
                            <div class="info-box-custom" style="border-top: 3px solid #dc3545;">
                                <div class="label-custom mb-3"><i class="fas fa-file-pdf mr-2 text-danger"></i> Dokumen Resmi Pengumuman</div>
                                
                                <?php if(!empty($data['surat_pengumuman_jadwal'])): ?>
                                    <div class="doc-viewer-card p-3">
                                        <div class="row align-items-center">
                                            <div class="col-auto">
                                                <div class="file-icon-box">
                                                    <i class="fas fa-file-pdf"></i>
                                                </div>
                                            </div>
                                            <div class="col">
                                                <h6 class="mb-1 font-weight-bold text-dark">Surat Pengumuman Jadwal</h6>
                                                <p class="mb-0 text-muted small text-break"><?= htmlspecialchars($data['surat_pengumuman_jadwal']); ?></p>
                                            </div>
                                            <div class="col-md-auto mt-3 mt-md-0">
                                                <a href="uploads/pengumuman/<?= $data['surat_pengumuman_jadwal']; ?>" target="_blank" class="btn btn-danger btn-view-doc px-4 shadow-sm">
                                                    <i class="fas fa-external-link-alt mr-2"></i> BUKA DOKUMEN
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-3 bg-light rounded" style="border: 1px dashed #cbd5e1;">
                                        <i class="fas fa-file-excel fa-2x text-muted mb-2"></i>
                                        <p class="text-muted small mb-0 italic">Belum ada dokumen surat pengumuman yang diunggah.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Keterangan -->
                        <div class="col-md-12 mb-4">
                            <div class="info-box-custom">
                                <div class="label-custom text-info"><i class="fas fa-info-circle mr-2"></i> Keterangan Tambahan</div>
                                <div class="mt-2 text-dark font-weight-500">
                                    <?= !empty($data['keterangan_ujikom']) ? nl2br(htmlspecialchars($data['keterangan_ujikom'])) : '<span class="text-muted italic">Tidak ada keterangan tambahan</span>'; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Alert Info -->
                    <div class="alert alert-info mt-3 shadow-sm border-0" style="border-radius: 12px; background-color: #f0f9ff; border-left: 5px solid #0ea5e9 !important;">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-info-circle fa-2x mr-3 text-info"></i>
                            <div class="text-dark">
                                <strong>Pemberitahuan Penting:</strong> 
                                <ul class="mb-0 mt-1 pl-3">
                                    <li>Peserta wajib hadir/standby <strong>30 menit</strong> sebelum jadwal dimulai.</li>
                                    <li>Pastikan koneksi internet, perangkat audio, dan kamera berfungsi dengan baik.</li>
                                    <li>Siapkan kartu identitas (KTP/Kartu Pegawai) untuk proses verifikasi.</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Tombol Kembali -->
                    <div class="text-center mt-5 btn-print-group">
                        <a href="<?= $dashboard_url; ?>" class="btn btn-outline-secondary btn-lg px-5 shadow-sm" style="border-radius: 12px;">
                            <i class="fas fa-chevron-left mr-2"></i> Kembali ke Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<?php 
require_once 'template/footer.php'; 
$stmt->close();
$conn->close();
?>