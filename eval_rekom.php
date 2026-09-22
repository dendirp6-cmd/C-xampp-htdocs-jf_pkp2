<?php
// FILE: eval_rekom.php - Halaman Evaluasi Detail Usulan Rekomendasi Formasi
// -------------------------------------------------------------------------

// =========================================================================
// !!! CATATAN PENTING: MENGGUNAKAN FILE EKSTERNAL ASLI !!!
// File auth_guard.php dan koneksi.php di-require di sini.
// Pastikan kedua file tersebut sudah ada dan berfungsi.
// =========================================================================

// --- PENGATURAN UMUM & KONSTANTA ---
$template_path = 'template/';

// MEMANGGIL FILE AUTHENTIKASI DAN KONEKSI DATABASE (ASUMSI FILE ASLI ANDA)
require_once 'auth_guard.php'; 
// CATATAN: auth_guard.php diasumsikan sudah menjalankan session_start() 
// dan mengamankan akses, serta menyediakan $_SESSION['user_id_sesi'].

require_once 'koneksi.php';    
// CATATAN: koneksi.php diasumsikan sudah membuat koneksi $conn = new mysqli(...) 
// dan menangani error koneksi.

// 1. --- PENGATURAN VARIABEL HALAMAN UNTUK SIDEBAR & HEADER ---
$page = 'rekomendasi'; 
$sub_page = 'evaluasi'; 
$page_title = "Verifikasi Usulan Formasi JF Penata Kelola Perumahan";

// ID Usulan diambil dari parameter GET
$usulan_id = $_GET['id'] ?? null;

// Data Usulan default (jika ID tidak valid atau tidak ditemukan)
// Tambahkan path default untuk file agar link bisa dites
$usulan_data = [
    'nama_pengusul' => 'Data Tidak Ditemukan',
    'nip' => '-',
    'instansi' => 'N/A',
    'provinsi' => 'N/A',
    'kota_kab' => 'N/A',
    'file_usulan_formasi' => '#', // Path file harus di sini
    'file_tupoksi' => '#',
    'file_abk' => '#',
    'file_struktur' => '#',
    'file_peta_jabatan' => '#',
    'file_sk_kelola' => '#',
];
$notification_message = "";
$notification_type = "";

// Mapping Berkas ke Kolom Database
$file_map = [
    'Verifikasi Administrasi' => [
        'Surat Usulan Formasi JF Telah Sesuai' => 'file_usulan_formasi',
        'Tugas dan Fungsi Instansi Telah Sesuai' => 'file_tupoksi',
        'Analisis Beban Kerja (ABK) Telah Sesuai' => 'file_abk',
        'Struktur Organisasi Telah Sesuai' => 'file_struktur',
        'Peta Jabatan Telah Sesuai' => 'file_peta_jabatan',
        'SK Kelas Jabatan Telah Sesuai' => 'file_sk_kelola',
    ],
    'Verifikasi Teknis' => [
        'Kemampuan Anggaran Daerah' => 'file_anggaran_daerah', // Asumsi ada field ini
        'Bukti Pengajuan Jafung Ahli Madya' => 'file_bukti_jafung', // Asumsi ada field ini
    ]
];

// --- 2. LOGIKA PENGAMBILAN DATA DARI DATABASE ---
if ($usulan_id) {
    // Validasi ID Usulan
    $usulan_id = (int) $usulan_id;

    // Gunakan Prepared Statement untuk mencegah SQL Injection
    $sql_get_data = "SELECT 
                        id, nama_pengusul, nip, instansi, provinsi, kota_kab, 
                        file_usulan_formasi, file_tupoksi, file_abk, file_struktur, file_peta_jabatan, file_sk_kelola,
                        evaluasi_data_json, catatan_evaluasi
                     FROM rekomendasi_formasi 
                     WHERE id = ?";
    
    // Pastikan variabel $conn tersedia dari koneksi.php
    if (!isset($conn) || $conn->connect_error) {
        $notification_message = "❌ Fatal Error: Koneksi database tidak tersedia. Pastikan koneksi.php berfungsi.";
        $notification_type = "danger";
    } elseif ($stmt = $conn->prepare($sql_get_data)) {
        $stmt->bind_param("i", $usulan_id); // 'i' untuk integer
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $fetched_data = $result->fetch_assoc();
            // Timpa data default dengan data yang diambil
            $usulan_data = array_merge($usulan_data, $fetched_data);
            
            // Ambil data evaluasi yang sudah tersimpan (jika ada)
            $stored_eval = json_decode($usulan_data['evaluasi_data_json'] ?? '[]', true);
            if (is_array($stored_eval)) {
                 $evaluations = $stored_eval;
            }
            $catatan_value = htmlspecialchars($usulan_data['catatan_evaluasi'] ?? '');

        } else {
            // Jika ID ada tapi data tidak ditemukan
            $notification_message = "❌ Error: Data usulan dengan ID #{$usulan_id} tidak ditemukan.";
            $notification_type = "danger";
            $usulan_id = null;
        }
        $stmt->close();
    } else {
        $notification_message = "❌ Error SQL: Gagal mempersiapkan query data usulan. " . $conn->error;
        $notification_type = "danger";
    }
} else {
    // Jika ID tidak ada di URL
    $notification_message = "⚠️ Peringatan: ID Usulan tidak ditemukan di URL. Tidak dapat menampilkan data.";
    $notification_type = "warning";
}

// 3. --- DATA EVALUASI & INISIALISASI ---
$total_komponen = 19; // Pastikan ini benar (3+3+2+3+3+3+1+1) = 19
$catatan_value = $catatan_value ?? "";
$evaluations = $evaluations ?? []; 

// Data Evaluasi dalam bentuk array PHP (Tetap sama seperti yang diberikan user)
$eval_data = [
    'Verifikasi Administrasi' => [
        'Surat Usulan Formasi JF Telah Sesuai' => [
            ['a.', 'Surat sudah ditandatangani pejabat berwenang', 'eval_1a', 'file_usulan_formasi', 3],
            ['b.', 'Ditujukan ke Sekretaris Jenderal cq. Direktur Bina Teknik', 'eval_1b', '', 0],
            ['c.', 'Sesuai dengan format yang diberikan', 'eval_1c', '', 0],
        ],
        'Tugas dan Fungsi Instansi Telah Sesuai' => [
            ['a.', 'Sesuai dengan bidang perumahan dan kawasan permukiman', 'eval_2a', 'file_tupoksi', 3],
            ['b.', 'Relevan dengan kebutuhan JF PKP', 'eval_2b', '', 0],
            ['c.', 'Tugas dan fungsi Instansi disahkan dalam bentuk Peraturan Daerah (Pemda) atau Peraturan yang berlaku untuk Pemerintah Pusat', 'eval_2c', '', 0],
        ],
        'Analisis Beban Kerja (ABK) Telah Sesuai' => [
            ['a.', 'Kesesuaian metode perhitungan ABK (Data sesuai dengan Permen PANRB 87 Tahun 2021)', 'eval_3a', 'file_abk', 2],
            ['b.', 'Jumlah formasi proporsional dan realistis', 'eval_3b', '', 0],
        ],
        'Struktur Organisasi Telah Sesuai' => [
            ['a.', 'Mencerminkan kebutuhan organisasi terhadap JF PKP', 'eval_4a', 'file_struktur', 3],
            ['b.', 'Posisi JF PKP jelas dalam struktur', 'eval_4b', '', 0],
            ['c.', 'Struktur Organisasi disahkan dalam bentuk Peraturan Daerah', 'eval_4c', '', 0],
        ],
        'Peta Jabatan Telah Sesuai' => [
            ['a.', 'Tersedia dan menunjukkan posisi JF PKP dalam organisasi', 'eval_5a', 'file_peta_jabatan', 3],
            ['b.', 'Sesuai dengan ABK yang disusun', 'eval_5b', '', 0],
            ['c.', 'Peta Jabatan telah disahkan/ditandatangani minimal Pejabat Pembina Kepegawaian setingkat Eselon II', 'eval_5c', '', 0],
        ],
        'SK Kelas Jabatan Telah Sesuai' => [
            ['a.', 'SK disahkan oleh Pejabat Pembina Kepegawaian minimal Eselon II.', 'eval_6a', 'file_sk_kelola', 3],
            ['b.', 'SK menyebutkan kelas jabatan (Grade) JF PKP sesuai jenjangnya (Ahli Pertama, Muda, atau Madya).', 'eval_6b', '', 0],
            ['c.', 'SK masih berlaku dan sesuai dengan struktur serta nomenklatur terbaru', 'eval_6c', '', 0],
        ],
    ],
    'Verifikasi Teknis' => [
        'Kemampuan Anggaran Daerah' => [
            ['a.', 'Kemampuan anggaran daerah memadai', 'eval_7a', 'file_anggaran_daerah', 1], // Asumsi field db: file_anggaran_daerah
        ],
        'Bukti Pengajuan Jafung Ahli Madya' => [
            ['a.', 'Pemerintah tingkat Kabupaten memiliki bukti memadai untuk pengajuan jafung ahli Madya', 'eval_8a', 'file_bukti_jafung', 1], // Asumsi field db: file_bukti_jafung
        ]
    ]
];

// Perbaikan: Lakukan hitungan ulang untuk total_komponen
$calculated_total_komponen = 0;
foreach ($eval_data as $section_title => $sasaran_group) {
    foreach ($sasaran_group as $sasaran_judul => $indikators) {
        $calculated_total_komponen += count($indikators);
    }
}
$total_komponen = $calculated_total_komponen;

// --- 4. LOGIKA PENANGANAN FORMULIR SUBMIT EVALUASI ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Ambil data evaluasi dan catatan dari POST
    $evaluations = $_POST['evaluasi'] ?? [];
    $catatan_value = trim($_POST['catatan_evaluasi'] ?? '');

    // Cek apakah ada ID Usulan yang diproses
    if (!$usulan_id) {
        $notification_message = "❌ Gagal Submit: ID Usulan tidak valid. Data tidak tersimpan.";
        $notification_type = "danger";
        goto end_submission;
    }
    
    // Logic untuk menghitung total komponen dan memeriksa semua status terpilih
    $all_selected = true;
    $eval_keys = [];
    foreach ($eval_data as $section_title => $sasaran_group) {
        foreach ($sasaran_group as $sasaran_judul => $indikators) {
            foreach ($indikators as $indikator) {
                $eval_keys[] = $indikator[2]; 
            }
        }
    }

    // Memeriksa apakah semua status telah dipilih
    foreach ($eval_keys as $key) {
        $value = $evaluations[$key] ?? '';
        // Periksa apakah ada yang masih 'Pilih' atau kosong
        if (empty($value) || $value === 'Pilih' || ($value !== 'Ya' && $value !== 'Tidak')) { 
            $all_selected = false;
            break;
        }
    }

    if (!$all_selected) {
        $notification_message = "Mohon lengkapi semua status evaluasi (Ya/Tidak) sebelum Submit.";
        $notification_type = "danger";
        // Pertahankan nilai yang sudah diisi di formulir
    } else {
        $ya_count = array_sum(array_map(function($v) {
            return $v === 'Ya' ? 1 : 0;
        }, $evaluations));
        
        $persen = number_format(($ya_count / $total_komponen) * 100, 2);
        // Hasil evaluasi Layak hanya jika SEMUA komponen adalah 'Ya'
        $hasil_evaluasi = ($ya_count === $total_komponen) ? 'Layak' : 'Perlu Revisi';

        // 4.1. Persiapan Data untuk Update Database
        $eval_json = json_encode($evaluations, JSON_UNESCAPED_UNICODE); // Simpan hasil evaluasi sebagai JSON
        $evaluator_id = $_SESSION['user_id_sesi'] ?? 0; // Menggunakan ID user dari session yang disediakan oleh auth_guard.php

        // --- START PERBAIKAN LOGIKA STATUS ---
        if ($hasil_evaluasi === 'Layak') {
            $status_update = 'Disetujui';
        } else {
            $status_update = 'Perlu Revisi';
        }
        // --- END PERBAIKAN LOGIKA STATUS ---


        // 4.2. Query Update
        // CATATAN: Pastikan kolom `evaluasi_data_json` bertipe TEXT/JSON, dan `persen_evaluasi` bertipe DOUBLE/DECIMAL.
        $sql_update = "UPDATE rekomendasi_formasi SET 
                            hasil_evaluasi = ?, 
                            persen_evaluasi = ?, 
                            catatan_evaluasi = ?, 
                            evaluator_id = ?, 
                            evaluasi_data_json = ?, 
                            status = ? 
                        WHERE id = ?";

        if ($stmt = $conn->prepare($sql_update)) {
            // Binding parameters: sdsissi (String, Double/String, String, Integer, String, String, Integer)
            // Mengubah tipe persen menjadi 's' untuk string/desimal agar lebih aman, atau 'd' jika DB support double.
            $stmt->bind_param("sdsissi", 
                $hasil_evaluasi, 
                $persen, 
                $catatan_value, 
                $evaluator_id, 
                $eval_json,
                $status_update, // Menggunakan nilai 'Disetujui' atau 'Perlu Revisi'
                $usulan_id
            );
            
            if ($stmt->execute()) {
                $notification_message = "✅ **Sukses!** Data Verifikasi berhasil disubmit. Hasil: <strong>$hasil_evaluasi</strong> ($ya_count dari $total_komponen, $persen%). **Status Usulan:** <strong>$status_update</strong>.";
                $notification_type = "success";
                
                // Setelah sukses, muat ulang data evaluasi dari DB/POST, 
                // atau cukup pertahankan nilai POST untuk feedback visual yang konsisten.
                // Untuk contoh ini, kita pertahankan POST values agar tidak perlu redirect/fetch ulang.
            } else {
                $notification_message = "❌ Gagal menyimpan hasil evaluasi ke database. Error: " . $stmt->error;
                $notification_type = "danger";
            }
            $stmt->close();
        } else {
            $notification_message = "❌ Gagal mempersiapkan statement SQL Update: " . $conn->error;
            $notification_type = "danger";
        }
    }
}
end_submission: 
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AdminLTE 3 | <?php echo $page_title; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Variabel CSS untuk konsistensi */
        :root {
            --primary: #007bff; /* Primary AdminLTE */
            --blue-dark: #1e3a8a; 
            --green-select: #10b981;
            --red-select: #ef4444;
            --neutral-select: #e5e7eb;
            --muted: #6b7280;
            --green: #28a745; /* Green AdminLTE */
            --red: #dc3545; /* Red AdminLTE */
            --text: #1f2937;
        }
        
        main {
            padding: 30px;
        }

        /* Card Customization */
        .card-header {
            background-color: var(--primary);
            color: white;
            font-weight: bold;
        }
        
        /* Table Styles */
        table{width:100%;border-collapse:collapse;font-size:14px;margin-bottom:16px;}
        th{background:#e5e7eb;color:var(--text);font-weight:600;text-align:center;padding:12px 10px;border:1px solid #d1d5db;}
        td{border:1px solid #d1d5db;padding:8px 10px;vertical-align:top;}
        td.Sasaran{font-weight:600;width:22%;text-align:left;background:#f3f4f6;}
        td.no{width:5%;text-align:center;font-weight:600;}
        td.uraian{width:33%;line-height:1.6;}
        td.status{width:15%;text-align:center;}
        td.berkas{width:25%;text-align:center;vertical-align:middle;}
        
        /* SELECT BUTTON (Evaluasi Status) */
        select.eval {
            padding:5px 8px; border:1px solid #d1d5db; border-radius:6px; background:#fff; color:#000;
            font-size:13px; width:100%; max-width:100px; transition: all 0.3s ease;
            -webkit-appearance: none; -moz-appearance: none; appearance: none;
        }
        
        /* Tombol BUKA BERKAS */
        a.btn-berkas, button.btn-berkas {
            padding:6px 14px; 
            font-size:13px; 
            background:var(--blue-dark); 
            color:#fff; 
            border:none;
            border-radius:4px; 
            cursor:pointer; 
            transition: background 0.3s ease, box-shadow 0.3s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.3); 
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none; /* Untuk tag <a> */
        }
        a.btn-berkas:hover, button.btn-berkas:hover{
            background:#1e2fa5;
            box-shadow: 0 4px 8px rgba(0,0,0,0.35); 
            color: #fff;
        }
        
        /* Summary Box */
        .summary-box{background:#fef3c7;padding:16px;border-radius:8px;margin-top:20px;font-weight:500;}
        .summary-row{display:flex;justify-content:space-between;margin-bottom:8px;}
        .summary-row:last-child{margin-bottom:0;font-weight:600;}
        
        /* Hasil Evaluasi */
        #hasil{
            padding:4px 10px; border-radius:6px;
            background: var(--red); 
            color: white;
            text-align: center;
        }
        #hasil.layak{
            background:var(--green) !important; color:#fff !important; font-weight:600;
        }

        .data-usulan {
            background: #e9ecef;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .data-usulan strong {
            display: inline-block;
            width: 150px;
        }
        .section-header h3 {
            border-bottom: 2px solid var(--primary);
            padding-bottom: 5px;
            margin-top: 20px;
            color: var(--blue-dark);
            font-size: 1.2rem;
            font-weight: 700;
        }
    </style>
    </head>
<body class="hold-transition sidebar-mini">
    <div class="wrapper">
        <?php 
        // Menggunakan file dummy karena path template tidak ada
        include 'template/navbar.php'; 
        include 'template/sidebar.php'; 
        ?>

        <div class="content-wrapper">
            <div class="content-header">
                <div class="container-fluid">
                    <div class="row mb-2">
                        <div class="col-sm-6">
                            <h1 class="m-0"><?php echo $page_title; ?></h1>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-right">
                                <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
                                <li class="breadcrumb-item"><a href="list_rekom.php">Rekomendasi</a></li>
                                <li class="breadcrumb-item active">Evaluasi</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
            <div class="content">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-md-12">
                            
                            <?php if ($notification_message): ?>
                            <div class="alert alert-<?php echo $notification_type === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                                <?php echo $notification_message; ?>
                                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <?php endif; ?>

                            <div class="card card-info card-outline">
                                <div class="card-header">
                                    <h3 class="card-title"><i class="fas fa-file-alt"></i> Detail Usulan #<?php echo $usulan_id ?? 'N/A'; ?></h3>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <p><strong>Nama Pengusul:</strong> <?php echo htmlspecialchars($usulan_data['nama_pengusul']); ?></p>
                                            <p><strong>NIP:</strong> <?php echo htmlspecialchars($usulan_data['nip']); ?></p>
                                        </div>
                                        <div class="col-md-6">
                                            <p><strong>Instansi:</strong> <?php echo htmlspecialchars($usulan_data['instansi']); ?></p>
                                            <p><strong>Wilayah:</strong> <?php echo htmlspecialchars($usulan_data['kota_kab'] . ', ' . $usulan_data['provinsi']); ?></p>
                                        </div>
                                    </div>
                                    <hr>
                                    <h5 class="text-bold text-primary"><i class="fas fa-link"></i> Tautan Berkas Usulan</h5>
                                    <div class="row">
                                        <?php 
                                        $file_fields = [
                                            'file_usulan_formasi' => 'Usulan Formasi',
                                            'file_tupoksi' => 'Tupoksi',
                                            'file_abk' => 'ABK',
                                            'file_struktur' => 'Struktur Org.',
                                            'file_peta_jabatan' => 'Peta Jabatan',
                                            'file_sk_kelola' => 'SK Kelas Jabatan',
                                            // Asumsi untuk file teknis, meskipun tidak ada di query, untuk demo:
                                            'file_anggaran_daerah' => 'Anggaran Daerah', 
                                            'file_bukti_jafung' => 'Bukti Jafung Madya',
                                        ];
                                        foreach ($file_fields as $field => $label): 
                                            // Mengambil path dari $usulan_data. Jika field tidak ada, gunakan '#'
                                            $path = $usulan_data[$field] ?? '#'; 
                                            $disabled_class = (empty($path) || $path === '#') ? 'disabled' : '';
                                            $href = (empty($path) || $path === '#') ? 'javascript:void(0);' : $path;
                                        ?>
                                        <div class="col-6 col-sm-4 col-md-2 mb-2">
                                            <a href="<?php echo $href; ?>" target="_blank" class="btn-berkas btn-block <?php echo $disabled_class; ?>" 
                                               <?php echo $disabled_class ? 'onclick="alert(\'Berkas tidak tersedia.\')"' : ''; ?>>
                                                <i class="fas fa-file-pdf mr-1"></i> <?php echo $label; ?>
                                            </a>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="card card-primary">
                                <div class="card-header">
                                    <h3 class="card-title"><i class="fas fa-check-square"></i> Tabel Evaluasi Kelengkapan Dokumen</h3>
                                </div>
                                <div class="card-body">
                                    <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . '?id=' . $usulan_id; ?>">
                                        
                                        <?php 
                                        $sasaran_count = 0;
                                        foreach ($eval_data as $section_title => $sasaran_group): 
                                        ?>
                                            <div class="section-header"><h3><?php echo $section_title; ?></h3></div>
                                            <table>
                                                <thead>
                                                    <tr>
                                                        <th rowspan="2">Sasaran</th>
                                                        <th colspan="2">Indikator</th>
                                                        <th rowspan="2">Status</th>
                                                        <th rowspan="2">Berkas Pendukung</th>
                                                    </tr>
                                                    <tr>
                                                        <th>No.</th>
                                                        <th>Uraian</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php 
                                                    $global_row_index = 0; // Untuk menentukan baris di mana Berkas akan diletakkan
                                                    foreach ($sasaran_group as $sasaran_judul => $indikators): 
                                                        $sasaran_count++;
                                                        $total_indikator = count($indikators);
                                                        $rowspan_set = false;
                                                        
                                                        // Cari tahu kolom berkas yang relevan untuk grup sasaran ini
                                                        $file_field_name = $file_map[$section_title][$sasaran_judul] ?? ''; 
                                                        $berkas_url = $usulan_data[$file_field_name] ?? '#';
                                                        $berkas_btn_text = array_search($file_field_name, $file_fields) ?: 'Buka Berkas';
                                                        $is_berkas_available = !empty($berkas_url) && $berkas_url !== '#';
                                                        
                                                        $berkas_rowspan = $indikators[0][4] ?? 1; // Ambil rowspan dari indikator pertama, default 1
                                                        
                                                        foreach ($indikators as $index => $indikator):
                                                            $indikator_no = $indikator[0];
                                                            $uraian = $indikator[1];
                                                            $input_name = $indikator[2];
                                                            // $berkas_rowspan = $indikator[4]; // Tidak perlu karena sudah ditentukan di atas
                                                            $current_value = $evaluations[$input_name] ?? ''; 
                                                            
                                                            $global_row_index++;
                                                        ?>
                                                            <tr>
                                                                <?php if (!$rowspan_set): ?>
                                                                    <td class="Sasaran" rowspan="<?php echo $total_indikator; ?>"><?php echo $sasaran_count . '. ' . $sasaran_judul; ?></td>
                                                                    <?php $rowspan_set = true; ?>
                                                                <?php endif; ?>

                                                                <td class="no"><?php echo $indikator_no; ?></td>
                                                                <td class="uraian"><?php echo $uraian; ?></td>
                                                                <td class="status">
                                                                    <select class="eval form-control" name="evaluasi[<?php echo $input_name; ?>]">
                                                                        <option value="" <?php echo $current_value == '' || $current_value == 'Pilih' ? 'selected' : ''; ?>>Pilih</option>
                                                                        <option value="Ya" <?php echo $current_value == 'Ya' ? 'selected' : ''; ?>>Ya</option>
                                                                        <option value="Tidak" <?php echo $current_value == 'Tidak' ? 'selected' : ''; ?>>Tidak</option>
                                                                    </select>
                                                                </td>
                                                                
                                                                <?php if ($index === 0 && $berkas_rowspan > 0): ?>
                                                                    <td class="berkas" rowspan="<?php echo $berkas_rowspan; ?>">
                                                                        <a href="<?php echo $berkas_url; ?>" target="_blank" 
                                                                           class="btn-berkas <?php echo !$is_berkas_available ? 'disabled' : ''; ?>"
                                                                           <?php echo !$is_berkas_available ? 'onclick="alert(\'Berkas untuk Sasaran ini tidak tersedia.\'); return false;"' : ''; ?>>
                                                                            <i class="fas fa-search mr-1"></i> Buka Berkas: <?php echo $berkas_btn_text; ?>
                                                                        </a>
                                                                    </td>
                                                                <?php endif; ?>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        <?php endforeach; ?>

                                        <div class="summary-box">
                                            <div class="summary-row"><span>Jumlah Sesuai (Ya)</span><span id="jumlah">0</span></div>
                                            <div class="summary-row"><span>Total Komponen</span><span><?php echo $total_komponen; ?></span></div>
                                            <div class="summary-row"><span>Persentase Kesesuaian</span><span id="persen">0.00%</span></div>
                                            <div class="summary-row"><span>**Hasil Evaluasi Akhir**</span><span id="hasil">Perlu Revisi</span></div>
                                        </div>

                                        <div class="section-header" style="margin-top: 25px;"><h3>Catatan Evaluasi</h3></div>
                                        <div class="form-group">
                                            <textarea class="form-control" id="catatan-evaluasi" name="catatan_evaluasi" rows="4" placeholder="Masukkan catatan evaluasi di sini..."><?php echo htmlspecialchars($catatan_value); ?></textarea>
                                        </div>

                                        <div class="card-footer text-right">
                                            <button type="submit" class="btn btn-success btn-lg" id="btnSubmit">
                                                <i class="fas fa-save mr-1"></i> Simpan & Tentukan Hasil Evaluasi
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            </div>
                    </div>
                </div>
            </div>
            </div>
        <?php 
        include 'template/footer.php'; 
        ?>
    </div>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/js/adminlte.min.js"></script>

    <script>
        // Logika JavaScript untuk memperbarui warna status dan ringkasan secara dinamis
        const selects = document.querySelectorAll('select.eval');
        const elJumlah = document.getElementById('jumlah');
        const elPersen = document.getElementById('persen');
        const elHasil = document.getElementById('hasil');
        const totalKomponen = <?php echo $total_komponen; ?>;
        
        // Mengambil variabel CSS
        const rootStyles = getComputedStyle(document.documentElement);
        const greenSelect = rootStyles.getPropertyValue('--green-select').trim() || '#10b981';
        const redSelect = rootStyles.getPropertyValue('--red-select').trim() || '#ef4444';
        const neutralSelect = rootStyles.getPropertyValue('--neutral-select').trim() || '#e5e7eb';
        const mutedColor = '#4b5563'; 
        const greenAdminLTE = rootStyles.getPropertyValue('--green').trim() || '#28a745';
        const redAdminLTE = rootStyles.getPropertyValue('--red').trim() || '#dc3545';

        /**
         * Memperbarui warna latar belakang dan teks dari elemen <select> berdasarkan nilainya.
         * @param {HTMLSelectElement} select
         */
        function updateColorAndValue(select) {
            const value = select.value;
            const style = select.style;

            if (value === 'Ya') {
                style.backgroundColor = greenSelect;
                style.color = 'white';
            } else if (value === 'Tidak') {
                style.backgroundColor = redSelect;
                style.color = 'white';
            } else {
                // Nilai 'Pilih' atau kosong
                style.backgroundColor = neutralSelect;
                style.color = mutedColor;
            }
        }

        /**
         * Menghitung dan memperbarui ringkasan hasil evaluasi.
         */
        function updateSummary() {
            const yaCount = Array.from(selects).filter(s => s.value === 'Ya').length;
            const persen = totalKomponen > 0 ? ((yaCount / totalKomponen) * 100).toFixed(2) : 0;

            elJumlah.textContent = yaCount;
            elPersen.textContent = persen + '%';
            
            const hasilText = (yaCount === totalKomponen) ? 'Layak' : 'Perlu Revisi';
            elHasil.textContent = hasilText;
            
            // Atur warna background untuk summary box hasil
            if (yaCount === totalKomponen) {
                elHasil.classList.add('layak');
                elHasil.style.backgroundColor = greenAdminLTE;
            } else {
                elHasil.classList.remove('layak');
                elHasil.style.backgroundColor = redAdminLTE;
            }
        }

        // 1. Inisialisasi dan Event Listener
        selects.forEach(select => {
            updateColorAndValue(select);
            
            select.addEventListener('change', function() {
                updateColorAndValue(this);
                updateSummary();
            });
        });

        // 2. Hitung ringkasan awal saat halaman dimuat
        updateSummary();

    </script>

    <?php
    // Tutup koneksi database
    if (isset($conn) && $conn) {
        // Hanya menutup koneksi jika objek $conn sudah diinisialisasi dan bukan null
        if ($conn instanceof mysqli) {
            mysqli_close($conn);
        }
    }
    ?>
    </body>
    </html>