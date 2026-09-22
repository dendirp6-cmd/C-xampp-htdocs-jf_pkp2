<?php
session_start();
require_once 'koneksi.php';

// Proteksi Akses
if (!isset($_SESSION['user_id'])) {
    die("Akses ditolak. Silakan login terlebih dahulu.");
}

$id = isset($_GET['id']) ? mysqli_real_escape_string($conn, $_GET['id']) : '';

if (empty($id)) {
    die("ID Peserta tidak valid.");
}

// Ambil data detail pendaftar secara lengkap dari database Anda
$query = mysqli_query($conn, "SELECT p.*, g.gelombang as nama_gelombang 
                              FROM pengajuan_ujikom p
                              LEFT JOIN tb_gelombang g ON p.gelombang = g.id 
                              WHERE p.id = '$id'");
$data = mysqli_fetch_assoc($query);

if (!$data) {
    die("Data peserta tidak ditemukan.");
}

// Persiapan Variabel untuk Surat Rekomendasi
$nama = htmlspecialchars($data['nama']);
$nip = htmlspecialchars($data['nip'] ?? '-------------------');
$pangkat = htmlspecialchars($data['pangkat'] ?? '-------------------');
$tmt = htmlspecialchars($data['tmt'] ?? '-------------------');
$jabatan_sekarang = htmlspecialchars($data['jabatan_saat_ini'] ?? 'Evaluator Keamanan Pangan');
$unit_kerja = htmlspecialchars($data['unit_kerja'] ?? 'Dinas Ketahanan Pangan');
$angka_kredit = number_format($data['angka_kredit'], 3, ',', '.');
$nomor_surat = "KP.02.01/JFPKP/" . date('Y') . "/" . $data['id'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Surat Rekomendasi Uji Kompetensi - <?= $nama ?></title>
    <style>
        /* Konfigurasi Ukuran Kertas A4 Landscape */
        @page {
            size: A4 landscape;
            margin: 0;
        }

        body {
            font-family: 'Bookman Old Style', 'Georgia', 'Times New Roman', serif;
            line-height: 1.4;
            color: #000;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
        }

        /* Pembungkus Kertas Landscape - Dioptimalkan untuk 1 halaman */
        .page-landscape {
            width: 297mm;
            height: 210mm;
            margin: 10px auto;
            padding: 15mm 25mm 10mm 25mm;
            box-sizing: border-box;
            background-color: #fff;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            position: relative;
            overflow: hidden;
        }

        .header-logo {
            text-align: center;
            margin-bottom: 12px;
        }
        .logo-gold {
            width: 65px;
            height: auto;
        }
        .judul-surat {
            text-align: center;
            font-weight: bold;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 3px;
        }
        .nomor-surat {
            text-align: center;
            font-size: 13px;
            margin-bottom: 15px;
        }
        .isi-pembuka {
            text-align: justify;
            text-indent: 40px;
            font-size: 13px;
            margin-bottom: 12px;
        }

        /* Tabel Utama */
        .table-identitas {
            width: 100%;
            margin-left: 15px;
            margin-bottom: 10px;
            font-size: 13px;
            border-collapse: collapse;
        }
        .table-identitas td {
            padding: 3px 5px;
            vertical-align: top;
        }

        /* Inner Table khusus meluruskan TMT Gol/Ruang di sebelah kanan */
        .inner-row-table {
            width: 100%;
            border-collapse: collapse;
        }
        .inner-row-table td {
            padding: 0 !important;
            vertical-align: top;
        }

        /* Style Baru Khusus untuk Blok DIREKOMENDASIKAN di Tengah Halaman */
        .block-rekomendasi {
            text-align: center;
            font-weight: bold;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 15px 0; /* Memberi ruang vertikal atas dan bawah */
            width: 100%;
        }

        .penutup-surat {
            text-align: justify;
            font-size: 13px;
            margin-bottom: 15px;
        }
        
        /* Container Tanda Tangan */
        .ttd-wrapper {
            width: 100%;
            display: flex;
            justify-content: flex-end;
        }
        .ttd-container {
            width: 380px;
            text-align: left;
            font-size: 13px;
        }
        .ttd-space {
            height: 60px;
        }

        @media print {
            body {
                background-color: #fff;
            }
            .page-landscape {
                margin: 0;
                box-shadow: none;
                width: 297mm;
                height: 210mm;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>

    <div class="no-print" style="max-width: 297mm; margin: 10px auto; background: #e1f5fe; padding: 10px; border-radius: 6px; border: 1px solid #b3e5fc; display: flex; justify-content: space-between; align-items: center; box-sizing: border-box;">
        <span style="font-size: 13px; color: #0288d1; font-family: sans-serif;">
            <i class="fas fa-info-circle"></i> Teks status <strong>DIREKOMENDASIKAN</strong> sekarang telah diposisikan di tengah (center) halaman.
        </span>
        <button onclick="window.print()" style="background: #0288d1; color: white; border: none; padding: 8px 18px; border-radius: 4px; cursor: pointer; font-weight: bold; font-family: sans-serif;">Cetak / Save PDF</button>
    </div>

    <div class="page-landscape">
        
        <div class="header-logo">
            <svg class="logo-gold" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                <path d="M50 5 L85 25 L85 45 L50 95 L15 45 L15 25 Z" fill="none" stroke="#c5a059" stroke-width="4"/>
                <path d="M50 15 L75 30 L75 42 L50 80 L25 42 L25 30 Z" fill="none" stroke="#c5a059" stroke-width="2"/>
                <line x1="50" y1="15" x2="50" y2="80" stroke="#c5a059" stroke-width="2"/>
                <line x1="25" y1="42" x2="75" y2="42" stroke="#c5a059" stroke-width="2"/>
            </svg>
        </div>

        <div class="judul-surat">
            DIREKTORAT JENDERAL KAWASAN PERMUKIMAN<br>
            SURAT REKOMENDASI<br>
            PENGANGKATAN DALAM JABATAN FUNGSIONAL<br>
            PENATA KELOLA PERUMAHAN<br>
            MELALUI UJI KOMPETENSI
        </div>
        
        <div class="nomor-surat">
            Nomor : <?= $nomor_surat ?>
        </div>

        <div class="isi-pembuka">
            Berdasarkan hasil uji kompetensi calon pejabat fungsional Penata Kelola Perumahan yang telah dilakukan pada tanggal <strong><?= date('d F Y') ?></strong> melalui Kenaikan Jabatan atas nama sebagai berikut:
        </div>

        <table class="table-identitas">
            <tr>
                <td width="200">Nama</td>
                <td width="15">:</td>
                <td style="text-transform: uppercase; font-weight: bold;"><?= $nama ?></td>
            </tr>
            <tr>
                <td>NIP</td>
                <td>:</td>
                <td><?= $nip ?></td>
            </tr>
            <tr>
                <td>Pangkat/TMT</td>
                <td>:</td>
                <td>
                    <table class="inner-row-table">
                        <tr>
                            <td><?= $pangkat ?></td>
                            <td align="right" style="padding-right: 40px !important;">
                                <span>TMT Gol./Ruang &nbsp;&nbsp;&nbsp;&nbsp; : &nbsp;&nbsp;&nbsp;&nbsp; <?= $tmt ?></span>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td>Jabatan saat ini</td>
                <td>:</td>
                <td><?= $jabatan_sekarang ?></td>
            </tr>
            <tr>
                <td>Unit Kerja</td>
                <td>:</td>
                <td><?= $unit_kerja ?></td>
            </tr>
            <tr>
                <td>Hasil Uji Kompetensi</td>
                <td>:</td>
                <td></td>
            </tr>
        </table>

        <div class="block-rekomendasi">
            DIREKOMENDASIKAN
        </div>

        <table class="table-identitas">
            <tr>
                <td width="200">Jumlah Angka Kredit</td>
                <td width="15">:</td>
                <td style="font-weight: bold; color: #1565c0;"><?= $angka_kredit ?></td>
            </tr>
            <tr>
                <td>Periode Penilaian</td>
                <td>:</td>
                <td>s.d. Juni 2025</td>
            </tr>
            <tr>
                <td>Masa Berlaku</td>
                <td>:</td>
                <td>1 (Satu) Tahun Sejak Ditetapkan</td>
            </tr>
        </table>

        <div class="penutup-surat">
            Yang bersangkutan direkomendasikan untuk diangkat sebagai Pejabat Fungsional Penata Kelola Perumahan Ahli Pertama.
        </div>

        <div class="ttd-wrapper">
            <div class="ttd-container">
                Jakarta, &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Juli 2025<br>
                <strong>Direktur Bina Teknik Perumahan dan<br>
                Kawasan Permukiman,</strong>
                
                <div class="ttd-space"></div>
                
                <u><strong>Syamsiar Nurhayadi, S.T., M.M.</strong></u><br>
                NIP. 197108252002121001
            </div>
        </div>

    </div>
</body>
</html>