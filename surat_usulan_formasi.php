<?php
// FILE: surat_usulan_formasi.php - Halaman untuk mencetak Surat Usulan Formasi (setelah Disetujui)

// Aktifkan buffering output
ob_start();

// Inisialisasi sesi jika belum
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Memuat file penting
require_once 'auth_guard.php'; // Proteksi sesi dan hak akses
require_once 'koneksi.php';    // Koneksi database

$rekom_id = $_GET['id'] ?? null;
$safe_rekom_id = $rekom_id ? intval($rekom_id) : null;
$data = null;
$error_message = null;

if (!$safe_rekom_id || $safe_rekom_id <= 0) {
    $error_message = "ID Rekomendasi tidak valid atau tidak ditemukan.";
} else {
    // 1. Query Utama - Mengambil data rekomendasi yang disetujui
    $sql = "
        SELECT 
            r.*, u.nama as nama_kepala_instansi
        FROM 
            rekomendasi_formasi r
        LEFT JOIN 
            users u ON r.user_id = u.id -- Asumsi user_id di rekomendasi adalah pengusul (yang juga Kepala Instansi/Pejabat)
        WHERE 
            r.id = ? AND r.status = 'Disetujui'
    ";
    
    if (!isset($conn)) {
        $error_message = "Koneksi database gagal dimuat.";
    } elseif ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $safe_rekom_id); 
        
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            
            if ($result && mysqli_num_rows($result) > 0) {
                $data = mysqli_fetch_assoc($result);
            } else {
                $error_message = "Data rekomendasi dengan ID {$safe_rekom_id} tidak ditemukan atau belum berstatus 'Disetujui'.";
            }
        } else {
            $error_message = "Gagal menjalankan query: " . mysqli_stmt_error($stmt);
        }
        mysqli_stmt_close($stmt);
    } else {
        $error_message = "Gagal menyiapkan query utama: " . mysqli_error($conn);
    }
}

// Fungsi untuk mendapatkan tanggal Indonesia
function format_tanggal_indonesia($timestamp) {
    if (empty($timestamp) || $timestamp === '0000-00-00 00:00:00') {
        return '...';
    }
    $bulan = array (
        1 => 'Januari',
        'Februari',
        'Maret',
        'April',
        'Mei',
        'Juni',
        'Juli',
        'Agustus',
        'September',
        'Oktober',
        'November',
        'Desember'
    );
    $pecahkan = explode('-', date('Y-m-d', strtotime($timestamp)));
    return $pecahkan[2] . ' ' . $bulan[ (int)$pecahkan[1] ] . ' ' . $pecahkan[0];
}

// Menetapkan nilai default jika data tidak ada
$instansi = htmlspecialchars($data['instansi'] ?? 'NAMA INSTANSI DAERAH');
$kota_kab = htmlspecialchars($data['kota_kab'] ?? 'KOTA/KABUPATEN');
$provinsi = htmlspecialchars($data['provinsi'] ?? 'PROVINSI');
$tanggal_surat = format_tanggal_indonesia($data['tanggal_pengajuan'] ?? date('Y-m-d'));
$nama_pejabat = htmlspecialchars($data['nama_kepala_instansi'] ?? 'Nama Pejabat/Kepala Instansi');
$nip_pejabat = htmlspecialchars($data['nip'] ?? 'NIP Pejabat'); // Menggunakan NIP Pengusul sebagai NIP Pejabat
$jabatan_pejabat = 'Kepala ' . $instansi; // Asumsi
$nomor_surat = 'Nomor: [Nomor Surat/BKN]'; // Nomor surat akan diisi secara manual atau dari kolom khusus

// Konten surat
$formasi_jf = htmlspecialchars($data['nama_jabatan_jf'] ?? 'Jabatan Fungsional (JF) yang Diajukan');
$jenjang_jf = htmlspecialchars($data['jenjang_jf'] ?? 'Jenjang Jabatan');
$kebutuhan = htmlspecialchars($data['kebutuhan_formasi'] ?? 0);
$dasar_hukum = htmlspecialchars($data['dasar_hukum'] ?? 'Dasar hukum yang relevan');
$keterangan = htmlspecialchars($data['keterangan_tambahan'] ?? 'Tidak ada keterangan tambahan.');


?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Surat Usulan Formasi - ID <?= $safe_rekom_id ?></title>
    <style>
        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 12pt;
            margin: 0;
            padding: 2cm; /* Margin halaman untuk cetak */
        }
        .container {
            width: 100%;
            margin: 0 auto;
        }
        .kop-surat {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 5px;
            margin-bottom: 20px;
        }
        .kop-surat h2, .kop-surat h3 {
            margin: 0;
            line-height: 1.2;
        }
        .content {
            line-height: 1.6;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        .data-table td {
            vertical-align: top;
            padding: 3px 0;
        }
        .tanda-tangan {
            margin-top: 50px;
            width: 100%;
            display: table;
        }
        .tanda-tangan div {
            width: 45%;
            float: right;
            text-align: center;
        }
        .clear {
            clear: both;
        }

        /* Print Specific Styles */
        @media print {
            body {
                padding: 1cm;
                margin: 0;
            }
            /* Menghilangkan tombol cetak saat mencetak */
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="container">

        <?php if ($error_message): ?>
            <div style="color: red; text-align: center; border: 1px solid red; padding: 20px;">
                <h3>TERJADI KESALAHAN</h3>
                <p><?= $error_message ?></p>
                <button class="no-print" onclick="window.close()">Tutup Jendela</button>
            </div>
        <?php else: ?>
            
            <div class="no-print" style="margin-bottom: 20px; text-align: right;">
                <button onclick="window.print()" style="padding: 10px 20px; font-size: 14pt; cursor: pointer;">
                    <i class="fas fa-print"></i> CETAK SURAT
                </button>
            </div>

            <div class="kop-surat">
                <h3>PEMERINTAH <?= strtoupper($provinsi) ?></h3>
                <h2><?= strtoupper($instansi) ?></h2>
                <p style="font-size: 10pt;">Alamat: [Isi Alamat Lengkap Instansi]</p>
            </div>

            <div class="content">
                <table class="data-table">
                    <tr>
                        <td style="width: 15%;">Nomor</td>
                        <td style="width: 1%;">:</td>
                        <td><?= $nomor_surat ?></td>
                        <td style="width: 35%; text-align: right;"><?= $kota_kab ?>, <?= $tanggal_surat ?></td>
                    </tr>
                    <tr>
                        <td>Sifat</td>
                        <td>:</td>
                        <td>Penting</td>
                        <td></td>
                    </tr>
                    <tr>
                        <td>Lampiran</td>
                        <td>:</td>
                        <td>1 (Satu) berkas</td>
                        <td></td>
                    </tr>
                    <tr>
                        <td>Hal</td>
                        <td>:</td>
                        <td>**Usulan Kebutuhan Formasi Jabatan Fungsional**</td>
                        <td></td>
                    </tr>
                </table>

                <br>
                
                <p>Kepada Yth.</p>
                <p>Kepala Badan Kepegawaian Negara</p>
                <p>di Jakarta</p>

                <p style="text-indent: 1cm;">Dengan hormat,</p>
                
                <p style="text-indent: 1cm; text-align: justify;">
                    Menindaklanjuti kebutuhan organisasi dan dalam rangka peningkatan efektivitas pelaksanaan tugas
                    di lingkungan <?= $instansi ?>, bersama ini kami mengajukan usulan penetapan kebutuhan formasi
                    untuk Jabatan Fungsional sebagai berikut:
                </p>

                <table style="width: 80%; margin: 15px auto; border-collapse: collapse; border: 1px solid #000;">
                    <tr>
                        <td style="border: 1px solid #000; padding: 5px; width: 30%;">Jabatan Fungsional</td>
                        <td style="border: 1px solid #000; padding: 5px; width: 70%; text-align: center;">**<?= $formasi_jf ?>**</td>
                    </tr>
                    <tr>
                        <td style="border: 1px solid #000; padding: 5px;">Jenjang Jabatan</td>
                        <td style="border: 1px solid #000; padding: 5px; text-align: center;"><?= $jenjang_jf ?></td>
                    </tr>
                    <tr>
                        <td style="border: 1px solid #000; padding: 5px;">Jumlah Kebutuhan Formasi</td>
                        <td style="border: 1px solid #000; padding: 5px; text-align: center;">**<?= $kebutuhan ?> (<?= ucwords(terbilang($kebutuhan)) ?>) Orang**</td>
                    </tr>
                    <tr>
                        <td style="border: 1px solid #000; padding: 5px;">Dasar Hukum</td>
                        <td style="border: 1px solid #000; padding: 5px;"><?= $dasar_hukum ?></td>
                    </tr>
                </table>

                <p style="text-indent: 1cm; text-align: justify;">
                    Sebagai bahan pertimbangan, bersama surat ini kami lampirkan dokumen pendukung
                    sebagaimana terlampir dalam sistem pengajuan.
                </p>

                <p style="text-indent: 1cm;">
                    Demikian usulan ini kami sampaikan, atas perhatian dan persetujuan Bapak/Ibu, kami ucapkan terima kasih.
                </p>
            </div>

            <div class="tanda-tangan">
                <div>
                    <p style="margin-bottom: 70px;">
                        <?= $jabatan_pejabat ?>,
                    </p>
                    
                    <p style="margin: 0; text-decoration: underline;">
                        **<?= strtoupper($nama_pejabat) ?>**
                    </p>
                    <p style="margin: 0;">
                        NIP. <?= $nip_pejabat ?>
                    </p>
                </div>
                <div class="clear"></div>
            </div>

        <?php endif; ?>
    </div>
</body>
</html>
<?php
// Fungsi terbilang sederhana (untuk kebutuhan formasi)
function terbilang($x) {
    $angka = ["", "satu", "dua", "tiga", "empat", "lima", "enam", "tujuh", "delapan", "sembilan", "sepuluh", "sebelas"];
    $temp = "";
    if ($x < 12) {
        $temp = " " . $angka[$x];
    } else if ($x < 20) {
        $temp = terbilang($x - 10) . " belas";
    } else if ($x < 100) {
        $temp = terbilang($x / 10) . " puluh" . terbilang($x % 10);
    } else if ($x < 200) {
        $temp = " seratus" . terbilang($x - 100);
    } else if ($x < 1000) {
        $temp = terbilang($x / 100) . " ratus" . terbilang($x % 100);
    } else if ($x < 2000) {
        $temp = " seribu" . terbilang($x - 1000);
    } else if ($x < 1000000) {
        $temp = terbilang($x / 1000) . " ribu" . terbilang($x % 1000);
    } else if ($x < 1000000000) {
        $temp = terbilang($x / 1000000) . " juta" . terbilang($x % 1000000);
    } 
    // Batasi hingga Miliar/Juta agar fungsi tetap sederhana dan relevan untuk formasi
    
    return trim($temp);
}

if (isset($conn) && $conn) {
    mysqli_close($conn);
}

ob_end_flush();
?>