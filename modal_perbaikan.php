<?php
require_once 'koneksi.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id == 0) die("ID Pengajuan tidak valid.");

// Ambil data pengajuan
$sql = "SELECT * FROM pengajuan_ujikom WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();

if (!$data) die("Data tidak ditemukan.");

// Definisi Mapping Dokumen
$dokumen_list = [
    ['label' => '1. Surat Usulan Perpindahan Jabatan', 'file' => 'file_surat_usulan_perpindahan', 'status' => 'd1_surat_usulan_status'],
    ['label' => '2. Dokumen Penetapan Kebutuhan JF', 'file' => 'file_dokumen_penetapan', 'status' => 'd2_rekomendasi_formasi_status'],
    ['label' => '3. Surat Usulan Uji Kompetensi', 'file' => 'file_surat_usulan_uji', 'status' => 'd3_usulan_ujikom_status'],
    ['label' => '4. Dokumen Portofolio', 'file' => 'file_portofolio', 'status' => 'd4_portofolio_status'],
    ['label' => '5. Salinan SK CPNS/PNS', 'file' => 'file_sk_cpns_pns', 'status' => 'd5_sk_cpns_pns_status'],
    ['label' => '6. Salinan SK Pangkat Terakhir', 'file' => 'file_sk_pangkat', 'status' => 'd6_sk_pangkat_status'],
    ['label' => '7. Salinan SK Jabatan Terakhir', 'file' => 'file_sk_jabatan', 'status' => 'd7_sk_jabatan_status'],
    ['label' => '8. Nilai SKP/PPK 2 Tahun Terakhir', 'file' => 'file_skp', 'status' => 'd8_nilai_skp_status'],
    ['label' => '9. Salinan Ijazah dan Transkrip', 'file' => 'file_ijazah_transkrip', 'status' => 'd9_ijazah_transkrip_status'],
    ['label' => '10. Pernyataan Integritas', 'file' => 'file_pernyataan_integritas', 'status' => 'd10_integritas_status'],
    ['label' => '11. Pernyataan Bersedia', 'file' => 'file_pernyataan_bersedia', 'status' => 'd11_bersedia_status'],
    ['label' => '12. Pernyataan Pengalaman 2 Tahun', 'file' => 'file_pernyataan_pengalaman', 'status' => 'd12_pengalaman_2th_status'],
    ['label' => '13. Rencana Penempatan', 'file' => 'file_rencana_penempatan', 'status' => 'd13_rencana_penempatan_status']
];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <style>
        body { background-color: #f4f6f9; padding: 15px; }
        .card-perbaikan { border-radius: 8px; margin-bottom: 15px; border-left: 5px solid #dee2e6; }
        .card-perbaikan.perlu-perbaikan { border-left-color: #dc3545; }
        .card-perbaikan.sudah-sesuai { border-left-color: #28a745; opacity: 0.8; }
        .status-badge { font-size: 11px; font-weight: 700; padding: 4px 8px; border-radius: 4px; text-transform: uppercase; float: right; }
        .badge-ya { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .badge-tidak { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .badge-menunggu { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        .file-info { font-size: 13px; color: #6c757d; margin-bottom: 10px; }
        .instruction { font-size: 12px; color: #dc3545; font-weight: 600; margin-top: 5px; }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="alert alert-info shadow-sm">
        <i class="fas fa-info-circle"></i> <strong>Informasi:</strong> 
        Silakan unggah kembali dokumen yang ditandai <span class="badge badge-danger">TIDAK SESUAI</span>.
    </div>

    <form id="formPerbaikan" action="proses_perbaikan.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="id_pengajuan" value="<?= $id ?>">

        <?php foreach ($dokumen_list as $doc) : 
            $status_verif = strtoupper($data[$doc['status']] ?? '');
            $file_existing = $data[$doc['file']] ?? '-';
            $is_tidak_sesuai = ($status_verif === 'TIDAK SESUAI' || $status_verif === 'TIDAK');
            $is_sesuai = ($status_verif === 'SESUAI' || $status_verif === 'YA');
            
            $card_class = $is_tidak_sesuai ? 'perlu-perbaikan' : 'sudah-sesuai';
            $badge_class = $is_sesuai ? 'badge-ya' : ($is_tidak_sesuai ? 'badge-tidak' : 'badge-menunggu');
            $badge_text = $is_sesuai ? 'YA (SESUAI)' : ($is_tidak_sesuai ? 'TIDAK SESUAI' : 'Menunggu Verifikasi');
        ?>
            <div class="card card-perbaikan <?= $card_class ?> shadow-sm">
                <div class="card-body p-3">
                    <span class="status-badge <?= $badge_class ?>"><?= $badge_text ?></span>
                    <h6 class="font-weight-bold mb-1"><?= $doc['label'] ?></h6>
                    
                    <div class="file-info">
                        <i class="fas fa-paperclip"></i> File saat ini: 
                        <span class="text-primary font-italic"><?= htmlspecialchars($file_existing) ?></span>
                    </div>

                    <?php if ($is_tidak_sesuai) : ?>
                        <div class="form-group mb-0">
                            <div class="custom-file">
                                <input type="file" name="<?= $doc['file'] ?>" class="custom-file-input input-file-perbaikan" id="input_<?= $doc['file'] ?>" accept=".pdf" required>
                                <label class="custom-file-label" for="input_<?= $doc['file'] ?>">Pilih file PDF perbaikan...</label>
                            </div>
                            <p class="instruction"><i class="fas fa-exclamation-triangle"></i> Wajib diunggah untuk melanjutkan perbaikan.</p>
                        </div>
                    <?php else : ?>
                        <div class="text-success small font-weight-bold">
                            <i class="fas fa-check-circle"></i> Dokumen sudah benar.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="mt-4 mb-4">
            <button type="submit" class="btn btn-danger btn-block shadow font-weight-bold py-2">
                <i class="fas fa-cloud-upload-alt"></i> UNGGAH PERBAIKAN SEKARANG
            </button>
        </div>
    </form>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    // Menampilkan nama file yang dipilih
    $('.custom-file-input').on('change', function() {
        let fileName = $(this).val().split('\\').pop();
        $(this).next('.custom-file-label').addClass("selected").html(fileName);
    });

    // Validasi tambahan: Pastikan semua input file perbaikan telah diisi
    $('#formPerbaikan').on('submit', function(e) {
        let allFilled = true;
        $('.input-file-perbaikan').each(function() {
            if ($(this).val() === "") {
                allFilled = false;
            }
        });

        if (!allFilled) {
            alert("Harap unggah semua file yang bertanda 'TIDAK SESUAI' sebelum mengirim perbaikan.");
            e.preventDefault();
        }
    });
</script>
</body>
</html>