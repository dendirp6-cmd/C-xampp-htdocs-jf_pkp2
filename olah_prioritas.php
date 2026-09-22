<?php
/**
 * ==================================================================================
 * FILE: olah_prioritas.php
 * DESKRIPSI: Halaman Analisis & Pengurutan Prioritas Usulan Jafung (Versi AJAX Debug)
 * ==================================================================================
 */

// Aktifkan reporting error untuk debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Mulai Output Buffering paling awal
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Deteksi apakah请求 ini adalah AJAX
$is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
           || (isset($_POST['is_ajax']) && $_POST['is_ajax'] == '1');

// Proteksi Session untuk AJAX (Cegah redirect HTML/auth_guard merusak JSON)
if ($is_ajax) {
    if (!isset($_SESSION['user_id']) && !isset($_SESSION['id_user']) && !isset($_SESSION['username']) && !isset($_SESSION['email'])) {
        ob_clean();
        header('Content-Type: application/json', true, 401);
        echo json_encode(['status' => 'error', 'message' => 'Sesi login Anda telah habis. Silakan muat ulang halaman dan login kembali.']);
        exit;
    }
}

require_once 'auth_guard.php'; 
require_once 'koneksi.php';    

if (!isset($conn) || !$conn) {
    if ($is_ajax) {
        ob_clean();
        header('Content-Type: application/json', true, 500);
        echo json_encode(['status' => 'error', 'message' => 'Fatal Error: Koneksi database tidak tersedia.']);
        exit;
    }
    die("Fatal Error: Koneksi database tidak tersedia.");
}

// ==================================================================================
// HELPER FUNCTIONS
// ==================================================================================
function renderStatusPengajuanBadge($row) {
    $st = trim((string)(
        $row['status_pengajuan'] ?? 
        ($row['status_verifikasi'] ?? 
        ($row['status_verifikator'] ?? 
        ($row['status'] ?? '')))
    ));
    
    $st_lower = strtolower($st);

    if (strpos($st_lower, 'disetujui') !== false || strpos($st_lower, 'lulus') !== false || strpos($st_lower, 'acc') !== false || strpos($st_lower, 'selesai') !== false) {
        return '<span class="badge badge-success px-3 py-2" style="border-radius: 20px; font-weight: 600;">Disetujui Verifikator</span>';
    } 
    if (strpos($st_lower, 'perbaikan') !== false || strpos($st_lower, 'revisi') !== false || strpos($st_lower, 'dikembalikan') !== false || strpos($st_lower, 'ditolak') !== false) {
        return '<span class="badge badge-danger px-3 py-2" style="border-radius: 20px; font-weight: 600;">Perlu Perbaikan</span>';
    }
    if (strpos($st_lower, 'verifikasi') !== false || strpos($st_lower, 'proses') !== false) {
        return '<span class="badge badge-info px-3 py-2" style="border-radius: 20px; font-weight: 600;">' . htmlspecialchars(ucwords($st)) . '</span>';
    }
    if (!empty($st) && $st !== '-') {
        return '<span class="badge badge-secondary px-3 py-2" style="border-radius: 20px; font-weight: 600;">' . htmlspecialchars(ucwords($st)) . '</span>';
    }
    return '<span class="badge badge-warning text-dark px-3 py-2" style="border-radius: 20px; font-weight: 600;">Belum Diverifikasi</span>';
}

function renderJenisPengajuanBadge($jenis) {
    $jenis_trim = trim((string)$jenis);
    if (stripos($jenis_trim, 'Perpindahan') !== false) {
        return '<span class="badge badge-primary px-2 py-1" style="font-size: 11px;"><i class="fas fa-exchange-alt mr-1"></i> Perpindahan Jabatan</span>';
    } elseif (stripos($jenis_trim, 'Kenaikan') !== false) {
        return '<span class="badge badge-info px-2 py-1" style="font-size: 11px; background-color: #17a2b8;"><i class="fas fa-arrow-up mr-1"></i> Kenaikan Jabatan</span>';
    }
    return '<span class="badge badge-secondary px-2 py-1" style="font-size: 11px;">' . htmlspecialchars($jenis_trim ?: '-') . '</span>';
}

function extractBirthDate($nip) {
    $nip = trim((string)$nip);
    if (strlen($nip) < 8) return null;
    $y = (int)substr($nip, 0, 4);
    $m = (int)substr($nip, 4, 2);
    $d = (int)substr($nip, 6, 2);
    if (!checkdate($m, $d, $y)) return null;
    return sprintf("%04d-%02d-%02d", $y, $m, $d);
}

function calculateDetailedAge($birthDateStr, $refDateStr = null) {
    if (!$birthDateStr) return null;
    $birth = new DateTime($birthDateStr);
    $ref   = (!empty($refDateStr) && $refDateStr !== '0000-00-00 00:00:00') ? new DateTime($refDateStr) : new DateTime();
    $diff = $birth->diff($ref);
    return [
        'years'  => $diff->y,
        'months' => $diff->m,
        'days'   => $diff->d,
        'text'   => "{$diff->y} Thn {$diff->m} Bln {$diff->d} Hri"
    ];
}

function calculateDetailedSelisih($birthDateStr, $maxAge, $refDateStr = null) {
    if (!$birthDateStr) return null;
    $birth   = new DateTime($birthDateStr);
    $refDate = (!empty($refDateStr) && $refDateStr !== '0000-00-00 00:00:00') ? new DateTime($refDateStr) : new DateTime();
    $maxAgeDate = clone $birth;
    $maxAgeDate->modify("+{$maxAge} years");
    
    if ($refDate > $maxAgeDate) {
        $diff = $maxAgeDate->diff($refDate);
        return [
            'is_passed' => true,
            'years'     => $diff->y,
            'months'    => $diff->m,
            'days'      => $diff->d,
            'text'      => "Lewat {$diff->y} Thn {$diff->m} Bln {$diff->d} Hri"
        ];
    } else {
        $diff = $refDate->diff($maxAgeDate);
        return [
            'is_passed' => false,
            'years'     => $diff->y,
            'months'    => $diff->m,
            'days'      => $diff->d,
            'text'      => "Sisa {$diff->y} Thn {$diff->m} Bln {$diff->d} Hri"
        ];
    }
}

function getMaxAgeByJenjang($jf_tujuan, $jenis_pengajuan = '') {
    $j = strtolower((string)$jf_tujuan);
    $is_kenaikan = (stripos((string)$jenis_pengajuan, 'kenaikan') !== false);

    if (strpos($j, 'utama') !== false) return $is_kenaikan ? 65 : 60;
    if (strpos($j, 'madya') !== false) return $is_kenaikan ? 60 : 55;
    if (strpos($j, 'muda') !== false) return $is_kenaikan ? 58 : 53;
    if (strpos($j, 'pertama') !== false) return $is_kenaikan ? 58 : 53;
    return $is_kenaikan ? 58 : 53;
}

function buildStatusBadgeWithReason($id_pengajuan, $nama_pengusul, $priorityLevel, $alasan_db = '') {
    $alasan_escaped = htmlspecialchars($alasan_db ?? '', ENT_QUOTES);
    $nama_escaped   = htmlspecialchars($nama_pengusul ?? '', ENT_QUOTES);

    if ($priorityLevel == 4) {
        return '<span class="badge badge-super p-2"><i class="fas fa-fire mr-1"></i> SUPER URGENT</span>';
    } elseif ($priorityLevel == 3) {
        return '<span class="badge badge-urgent p-2"><i class="fas fa-exclamation-triangle mr-1"></i> URGENT</span>';
    } elseif ($priorityLevel == 2) {
        return '<span class="badge badge-normal p-2"><i class="fas fa-check mr-1"></i> MEMENUHI</span>';
    } else {
        $html = '<div class="d-flex align-items-center justify-content-center flex-wrap" style="gap: 5px;">';
        $html .= '<span class="badge badge-low p-2"><i class="fas fa-times mr-1"></i> TIDAK ELIGIBLE</span>';
        $html .= '<button type="button" class="btn btn-dark btn-edit-inline shadow-sm btn-alasan-ineligible" ';
        $html .= 'title="Input / Edit Alasan Tidak Eligible" ';
        $html .= 'data-id="' . $id_pengajuan . '" ';
        $html .= 'data-nama="' . $nama_escaped . '" ';
        $html .= 'data-alasan="' . $alasan_escaped . '" ';
        $html .= 'onclick="openModalEditAlasan(this)">';
        $html .= '<i class="fas fa-comment-dots text-warning"></i>';
        $html .= '</button>';
        $html .= '</div>';
        
        if (!empty($alasan_db)) {
            $html .= '<div class="mt-1 small text-left text-danger font-italic font-weight-bold cell-alasan-text" style="line-height: 1.2;">';
            $html .= '<i class="fas fa-info-circle mr-1"></i>' . htmlspecialchars($alasan_db);
            $html .= '</div>';
        } else {
            $html .= '<div class="mt-1 small text-left text-muted font-italic cell-alasan-text" style="line-height: 1.2;">';
            $html .= '<i class="fas fa-exclamation-circle mr-1"></i><span class="text-secondary">Alasan belum diisi</span>';
            $html .= '</div>';
        }
        return $html;
    }
}

// ==================================================================================
// AJAX UPDATE HANDLER
// ==================================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // 1. UPDATE ALASAN TIDAK ELIGIBLE
    if ($_POST['action'] === 'update_alasan_ineligible') {
        $id_pengajuan = intval($_POST['id_pengajuan'] ?? 0);
        $alasan       = trim($_POST['alasan_tidak_eligible'] ?? '');

        if ($id_pengajuan > 0) {
            $stmt = $conn->prepare("UPDATE pengajuan_ujikom SET alasan_tidak_eligible = ? WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("si", $alasan, $id_pengajuan);
                if ($stmt->execute()) {
                    $stmt->close();
                    ob_clean();
                    header('Content-Type: application/json');
                    echo json_encode([
                        'status'       => 'success',
                        'message'      => 'Alasan Tidak Eligible berhasil disimpan!',
                        'id_pengajuan' => $id_pengajuan,
                        'alasan'       => htmlspecialchars($alasan)
                    ]);
                    exit;
                } else {
                    $err_msg = $stmt->error;
                    $stmt->close();
                    ob_clean();
                    header('Content-Type: application/json', true, 500);
                    echo json_encode(['status' => 'error', 'message' => 'Gagal Eksekusi DB: ' . $err_msg]);
                    exit;
                }
            } else {
                ob_clean();
                header('Content-Type: application/json', true, 500);
                echo json_encode(['status' => 'error', 'message' => 'Gagal Prepare DB: ' . $conn->error]);
                exit;
            }
        }
    }

    // 2. UPDATE TANGGAL & JF TUJUAN
    if ($_POST['action'] === 'update_tanggal' || $_POST['action'] === 'update_jf') {
        $id_pengajuan = intval($_POST['id_pengajuan'] ?? 0);
        
        if ($id_pengajuan > 0) {
            if ($_POST['action'] === 'update_tanggal') {
                $tgl_baru = trim($_POST['tanggal_pengajuan_baru'] ?? '');
                $tgl_formatted_str = str_replace('T', ' ', $tgl_baru);
                $timestamp_tgl = !empty($tgl_formatted_str) ? strtotime($tgl_formatted_str) : false;
                $formatted_date = ($timestamp_tgl) ? date('Y-m-d H:i:s', $timestamp_tgl) : date('Y-m-d H:i:s');

                $stmt = $conn->prepare("UPDATE pengajuan_ujikom SET tanggal_pengajuan = ? WHERE id = ?");
                $stmt->bind_param("si", $formatted_date, $id_pengajuan);
            } else {
                $jf_baru = trim($_POST['jf_tujuan_baru'] ?? '');
                $stmt = $conn->prepare("UPDATE pengajuan_ujikom SET jf_pkp_tujuan = ? WHERE id = ?");
                $stmt->bind_param("si", $jf_baru, $id_pengajuan);
            }

            if ($stmt->execute()) {
                $stmt->close();
                
                $stmt_fetch = $conn->prepare("SELECT nama, nip, jf_pkp_tujuan, jabatan_saat_ini, jenis_pengajuan, tanggal_pengajuan, alasan_tidak_eligible FROM pengajuan_ujikom WHERE id = ?");
                $stmt_fetch->bind_param("i", $id_pengajuan);
                $stmt_fetch->execute();
                $updated_row = $stmt_fetch->get_result()->fetch_assoc();
                $stmt_fetch->close();

                $nama = $updated_row['nama'] ?? '';
                $nip = $updated_row['nip'] ?? '';
                $jf = $updated_row['jf_pkp_tujuan'] ?? ($updated_row['jabatan_saat_ini'] ?? '');
                $jenis_pengajuan = $updated_row['jenis_pengajuan'] ?? '';
                $tgl_pengajuan_raw = $updated_row['tanggal_pengajuan'] ?? null;
                $alasan_db = $updated_row['alasan_tidak_eligible'] ?? '';

                $birthStr = extractBirthDate($nip);
                if ($birthStr) {
                    $ageDetail    = calculateDetailedAge($birthStr, $tgl_pengajuan_raw);
                    $maxAge       = getMaxAgeByJenjang($jf, $jenis_pengajuan);
                    $selisihDetail = calculateDetailedSelisih($birthStr, $maxAge, $tgl_pengajuan_raw);

                    if ($selisihDetail['is_passed']) {
                        $priorityLevel = 0;
                        $sel_class     = 'selisih-none';
                        $row_class     = 'low-row';
                    } else {
                        $remainingYears = $selisihDetail['years'];
                        if ($remainingYears == 0) {
                            $priorityLevel = 4;
                            $sel_class     = 'selisih-zero';
                            $row_class     = 'super-urgent-row';
                        } elseif ($remainingYears == 1) {
                            $priorityLevel = 3;
                            $sel_class     = 'selisih-one';
                            $row_class     = 'urgent-row';
                        } else {
                            $priorityLevel = 2;
                            $sel_class     = 'selisih-safe';
                            $row_class     = 'normal-row';
                        }
                    }

                    $usia_display = '<strong>' . htmlspecialchars($ageDetail['text']) . '</strong><br><small class="text-muted">(Max ' . $maxAge . ' Thn)</small>';
                    $selisih_text = $selisihDetail['text'];
                } else {
                    $priorityLevel = 0;
                    $usia_display = '<span class="text-danger font-weight-bold">NIP Tidak Valid</span>';
                    $selisih_text = '—';
                    $sel_class    = 'selisih-none';
                    $row_class    = 'low-row';
                }

                $status_badge  = buildStatusBadgeWithReason($id_pengajuan, $nama, $priorityLevel, $alasan_db);
                $tgl_display   = !empty($tgl_pengajuan_raw) ? date('d/m/Y H:i', strtotime($tgl_pengajuan_raw)) : '—';
                $tgl_val_input = (!empty($tgl_pengajuan_raw) && $tgl_pengajuan_raw !== '0000-00-00 00:00:00') ? date('Y-m-d\TH:i', strtotime($tgl_pengajuan_raw)) : '';

                ob_clean();
                header('Content-Type: application/json');
                echo json_encode([
                    'status'        => 'success',
                    'message'       => 'Data berhasil diperbarui secara instan!',
                    'id_pengajuan'  => $id_pengajuan,
                    'jf_tujuan'     => htmlspecialchars($jf),
                    'usia_display'  => $usia_display,
                    'selisih_text'  => htmlspecialchars($selisih_text),
                    'sel_class'     => $sel_class,
                    'row_class'     => $row_class,
                    'tgl_display'   => $tgl_display,
                    'tgl_val_input' => $tgl_val_input,
                    'status_badge'  => $status_badge
                ]);
                exit;
            } else {
                $err_msg = $stmt->error;
                $stmt->close();
                ob_clean();
                header('Content-Type: application/json', true, 500);
                echo json_encode(['status' => 'error', 'message' => 'Gagal memperbarui database: ' . $err_msg]);
                exit;
            }
        }
    }
}

$page_title = "Olah Prioritas Jafung";

// 1. AMBIL DAFTAR VERIFIKATOR
$sql_verifikator = "SELECT id, nama FROM users WHERE role = 'user_verifikator'";
$res_verif = $conn->query($sql_verifikator);
$list_verifikator = ($res_verif) ? $res_verif->fetch_all(MYSQLI_ASSOC) : [];

// 2. AMBIL DAFTAR GELOMBANG
$sql_gelombang = "SELECT id, gelombang, bln_gelombang FROM tb_gelombang ORDER BY id DESC";
$res_gel = $conn->query($sql_gelombang);
$list_gelombang = ($res_gel) ? $res_gel->fetch_all(MYSQLI_ASSOC) : [];

// 3. AMBIL DATA UTAMA DARI DATABASE
$sql = "SELECT p.*, 
               u.email AS user_email, 
               v.nama AS nama_verifikator, 
               g.gelombang AS nama_gelombang, 
               g.bln_gelombang
        FROM pengajuan_ujikom p
        LEFT JOIN users u ON p.nip COLLATE utf8mb4_general_ci = u.nip_user COLLATE utf8mb4_general_ci
        LEFT JOIN users v ON p.verifikator_id = v.id
        LEFT JOIN tb_gelombang g ON p.gelombang = g.id
        GROUP BY p.id
        ORDER BY p.tanggal_pengajuan ASC";

$result = $conn->query($sql);
if (!$result) {
    die("Query Error: " . $conn->error);
}
$raw_data = $result->fetch_all(MYSQLI_ASSOC);

$processed_data = [];
foreach ($raw_data as $idx => $row) {
    $nip = $row['nip'] ?? '';
    $jf = $row['jf_pkp_tujuan'] ?? ($row['jabatan_saat_ini'] ?? '');
    $jenis_pengajuan = $row['jenis_pengajuan'] ?? '';
    $tgl_pengajuan_raw = $row['tanggal_pengajuan'] ?? null;
    
    $birthStr = extractBirthDate($nip);
    
    if ($birthStr) {
        $ageDetail    = calculateDetailedAge($birthStr, $tgl_pengajuan_raw);
        $effectiveAge = $ageDetail['years'];
        $maxAge       = getMaxAgeByJenjang($jf, $jenis_pengajuan);
        $selisihDetail = calculateDetailedSelisih($birthStr, $maxAge, $tgl_pengajuan_raw);

        if ($selisihDetail['is_passed']) {
            $eligible      = false;
            $priorityLevel = 0;
        } else {
            $eligible       = true;
            $remainingYears = $selisihDetail['years']; 

            if ($remainingYears == 0) {
                $priorityLevel = 4;
            } elseif ($remainingYears == 1) {
                $priorityLevel = 3;
            } else {
                $priorityLevel = 2;
            }
        }
        $selisih = $maxAge - $effectiveAge;
    } else {
        $ageDetail     = null;
        $selisihDetail = null;
        $effectiveAge  = null;
        $maxAge        = getMaxAgeByJenjang($jf, $jenis_pengajuan);
        $eligible      = false;
        $selisih       = null;
        $priorityLevel = 0;
    }

    $timestamp = !empty($row['tanggal_pengajuan']) ? strtotime($row['tanggal_pengajuan']) : 0;

    $processed_data[] = array_merge($row, [
        'birth_date'     => $birthStr,
        'age_detail'     => $ageDetail,
        'selisih_detail' => $selisihDetail,
        'effective_age'  => $effectiveAge,
        'max_age'        => $maxAge,
        'eligible'       => $eligible,
        'selisih'        => $selisih,
        'priority_level' => $priorityLevel,
        'timestamp'      => $timestamp,
        'orig_index'     => $idx
    ]);
}

usort($processed_data, function($a, $b) {
    if ($a['priority_level'] !== $b['priority_level']) {
        return $b['priority_level'] <=> $a['priority_level'];
    }
    return $a['timestamp'] <=> $b['timestamp'];
});

$stat_super    = 0;
$stat_urgent   = 0;
$stat_normal   = 0;
$stat_cadangan = 0;

foreach ($processed_data as $p) {
    if ($p['priority_level'] == 4) $stat_super++;
    elseif ($p['priority_level'] == 3) $stat_urgent++;
    elseif ($p['priority_level'] == 2) $stat_normal++;
    else $stat_cadangan++;
}

$status_options = [
    'Menunggu Disposisi',
    'Menunggu Verifikasi',
    'Proses Verifikasi',
    'Verifikasi Dokumen',
    'Perlu Perbaikan',
    'Disetujui Verifikator'
];

// Bersihkan output buffer sebelum memuat template HTML halaman utama
ob_end_clean();

require_once 'template/header.php'; 
require_once 'template/navbar.php'; 
require_once 'template/sidebar.php'; 
?>

<style>
    .content-wrapper { background-color: #f4f7f6; }
    .main-card { border: none; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); }
    .info-rule { background: #eef7fc; border-left: 5px solid #17a2b8; border-radius: 8px; padding: 15px; margin-bottom: 20px; }
    .filter-card { background: #fff; border-radius: 10px; margin-bottom: 15px; border-left: 5px solid #2c7873; }
    
    .stat-card-prio { border-radius: 12px; padding: 15px; color: white; transition: transform 0.2s; }
    .stat-card-prio:hover { transform: translateY(-3px); }
    
    tr.super-urgent-row { background-color: #f8d7da !important; } 
    tr.urgent-row { background-color: #fff3cd !important; }       
    tr.normal-row { background-color: #ffffff !important; }       
    tr.low-row { background-color: #e2e3e5 !important; }          

    .badge-super { background-color: #dc3545; color: white; font-weight: bold; }
    .badge-urgent { background-color: #fd7e14; color: white; font-weight: bold; }
    .badge-normal { background-color: #28a745; color: white; font-weight: bold; }
    .badge-low { background-color: #6c757d; color: white; font-weight: bold; }

    .selisih-pill { font-size: 11px; font-weight: 700; padding: 5px 10px; border-radius: 20px; display: inline-block; white-space: nowrap; }
    .selisih-zero { background-color: #dc3545; color: white; }
    .selisih-one { background-color: #fd7e14; color: white; }
    .selisih-safe { background-color: #28a745; color: white; }
    .selisih-none { background-color: #6c757d; color: white; }

    .badge-verif-none { background-color: #dc3545 !important; color: white !important; font-weight: 700 !important; padding: 4px 8px; border-radius: 4px; font-size: 10px; }
    
    .btn-edit-inline {
        padding: 2px 6px;
        font-size: 10px;
        border-radius: 4px;
        margin-left: 4px;
        cursor: pointer;
    }

    #ajaxToast {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        display: none;
    }
</style>

<!-- TOAST NOTIFIKASI SUKSES AJAX -->
<div id="ajaxToast" class="alert alert-success alert-dismissible fade show shadow-lg font-weight-bold" role="alert">
    <i class="fas fa-check-circle mr-2"></i> <span id="ajaxToastText">Perubahan berhasil disimpan!</span>
    <button type="button" class="close" onclick="$('#ajaxToast').fadeOut()">
        <span aria-hidden="true">&times;</span>
    </button>
</div>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-3 mt-3 align-items-center">
                <div class="col-sm-6">
                    <h1 class="font-weight-bold text-dark">
                        <i class="fas fa-sort-amount-down text-pink mr-2" style="color: #e83e8c;"></i> Olah Prioritas Jafung
                    </h1>
                </div>
                <div class="col-sm-6 text-right">
                    <a href="index_kasubdit.php" class="btn btn-secondary elevation-1 font-weight-bold">
                        <i class="fas fa-arrow-left mr-1"></i> Kembali ke Monitoring
                    </a>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">

            <!-- RINGKASAN ATURAN -->
            <div class="info-rule shadow-sm">
                <div class="d-flex align-items-center mb-2">
                    <i class="fas fa-info-circle fa-2x text-info mr-3"></i>
                    <h5 class="font-weight-bold mb-0 text-dark">Rule Prioritas Otomatis & Ketentuan Batas Usia Max</h5>
                </div>
                <div class="row mt-3 text-dark">
                    <div class="col-md-3 mb-2">
                        <span class="badge badge-super p-2 mr-1">Sisa 0 Thn</span> <strong>SUPER URGENT</strong><br>
                        <small class="text-muted">Memasuki tahun batas usia maksimal saat pengajuan.</small>
                    </div>
                    <div class="col-md-3 mb-2">
                        <span class="badge badge-urgent p-2 mr-1">Sisa 1 Thn</span> <strong>URGENT</strong><br>
                        <small class="text-muted">Sisa 1 tahun sebelum batas usia.</small>
                    </div>
                    <div class="col-md-3 mb-2">
                        <span class="badge badge-normal p-2 mr-1">Sisa ≥ 2 Thn</span> <strong>MEMENUHI</strong><br>
                        <small class="text-muted">Usia aman pada tanggal pengajuan.</small>
                    </div>
                    <div class="col-md-3 mb-2">
                        <span class="badge badge-low p-2 mr-1">Lewat Max</span> <strong>TIDAK ELIGIBLE</strong><br>
                        <small class="text-muted">Melewati batas usia / NIP tidak valid.</small>
                    </div>
                </div>
            </div>

            <!-- STATISTIK RINGKAS -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stat-card-prio bg-danger shadow-sm">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="font-weight-bold mb-0"><?= $stat_super; ?></h3>
                                <span>Super Urgent (Sisa 0 Thn)</span>
                            </div>
                            <i class="fas fa-exclamation-circle fa-2x opacity-5"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card-prio shadow-sm" style="background-color: #fd7e14 !important;">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="font-weight-bold mb-0"><?= $stat_urgent; ?></h3>
                                <span>Urgent (Sisa 1 Thn)</span>
                            </div>
                            <i class="fas fa-clock fa-2x opacity-5"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card-prio bg-success shadow-sm">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="font-weight-bold mb-0"><?= $stat_normal; ?></h3>
                                <span>Memenuhi (Normal)</span>
                            </div>
                            <i class="fas fa-check-circle fa-2x opacity-5"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card-prio bg-secondary shadow-sm">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="font-weight-bold mb-0"><?= $stat_cadangan; ?></h3>
                                <span>Tidak Eligible</span>
                            </div>
                            <i class="fas fa-user-times fa-2x opacity-5"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CARD FILTER -->
            <div class="card filter-card elevation-1">
                <div class="card-body py-3">
                    <div class="row align-items-center">
                        <div class="col-md-1 text-muted font-weight-bold">FILTER:</div>
                        <div class="col-md-2">
                            <select id="filterJenis" class="form-control form-control-sm border-info">
                                <option value="">-- Semua Jenis --</option>
                                <option value="Perpindahan Jabatan">Perpindahan Jabatan</option>
                                <option value="Kenaikan Jabatan">Kenaikan Jabatan</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select id="filterGelombang" class="form-control form-control-sm border-secondary">
                                <option value="">-- Semua Gelombang --</option>
                                <?php foreach($list_gelombang as $g): 
                                    $label_gel = htmlspecialchars($g['gelombang']) . (!empty($g['bln_gelombang']) ? " - " . htmlspecialchars($g['bln_gelombang']) : "");
                                ?>
                                    <option value="<?= htmlspecialchars($g['gelombang']); ?>"><?= $label_gel; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select id="filterStatus" class="form-control form-control-sm border-success">
                                <option value="">-- Semua Status --</option>
                                <?php foreach($status_options as $st_opt): ?>
                                    <option value="<?= $st_opt; ?>"><?= $st_opt; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select id="filterVerifikator" class="form-control form-control-sm border-primary">
                                <option value="">-- Semua Verifikator --</option>
                                <option value="BELUM DISPOSISI">BELUM DISPOSISI</option>
                                <?php foreach($list_verifikator as $v): ?>
                                    <option value="<?= htmlspecialchars($v['nama']); ?>"><?= htmlspecialchars($v['nama']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 text-right">
                            <button class="btn btn-sm btn-outline-secondary font-weight-bold" onclick="resetFilter()"><i class="fas fa-undo mr-1"></i> Reset</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TABEL UTAMA -->
            <div class="card main-card">
                <div class="card-header bg-white py-3">
                    <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap: 15px;">
                        <h3 class="card-title font-weight-bold mb-0 text-dark">
                            <i class="fas fa-list-ol mr-2 text-primary"></i> Daftar Hasil Pengurutan Prioritas
                        </h3>
                        <div class="d-flex align-items-center" style="gap: 10px;">
                            <label class="mb-0 font-weight-bold text-muted" style="white-space: nowrap;">Tampilkan Teratas:</label>
                            <select id="limitSelect" class="form-control form-control-sm border-primary" style="width: 100px;">
                                <option value="10">10 Data</option>
                                <option value="25">25 Data</option>
                                <option value="50">50 Data</option>
                                <option value="100">100 Data</option>
                                <option value="all" selected>Semua (<?= count($processed_data); ?>)</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="tablePrioritas" class="table table-bordered table-hover">
                            <thead class="bg-dark text-white">
                                <tr>
                                    <th width="50" class="text-center">Rank</th>
                                    <th>Nama Pengusul / NIP</th>
                                    <th class="text-center">Jenis</th>
                                    <th>Gelombang</th>
                                    <th>JF Tujuan</th>
                                    <th class="text-center" style="min-width: 160px;">Usia Efektif (Tgl Pengajuan)</th>
                                    <th class="text-center" style="min-width: 170px;">Selisih Usia Detail</th>
                                    <th>Tanggal Pengajuan</th>
                                    <th>Verifikator</th>
                                    <th class="text-center">Status Pengajuan</th>
                                    <th class="text-center" style="min-width: 180px;">Status Prioritas</th>
                                    <th class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $rank = 1;
                                foreach ($processed_data as $row): 
                                    $row_id = $row['id'];
                                    $prio   = $row['priority_level'];
                                    $nama_pengusul = $row['nama'] ?? '-';
                                    $alasan_db = $row['alasan_tidak_eligible'] ?? '';
                                    
                                    if ($prio == 4) {
                                        $row_class = 'super-urgent-row';
                                        $sel_class = 'selisih-zero';
                                    } elseif ($prio == 3) {
                                        $row_class = 'urgent-row';
                                        $sel_class = 'selisih-one';
                                    } elseif ($prio == 2) {
                                        $row_class = 'normal-row';
                                        $sel_class = 'selisih-safe';
                                    } else {
                                        $row_class = 'low-row';
                                        $sel_class = 'selisih-none';
                                    }

                                    $status_badge = buildStatusBadgeWithReason($row_id, $nama_pengusul, $prio, $alasan_db);

                                    $disp_gel = !empty($row['nama_gelombang']) ? $row['nama_gelombang'] : ($row['gelombang'] ?? '');
                                    $disp_bln = !empty($row['bln_gelombang']) ? " - " . $row['bln_gelombang'] : "";
                                    $tampil_gelombang = $disp_gel . $disp_bln;

                                    $raw_tgl = !empty($row['tanggal_pengajuan']) ? $row['tanggal_pengajuan'] : '';
                                    $tgl_pengajuan = !empty($raw_tgl) ? date('d/m/Y H:i', strtotime($raw_tgl)) : '—';
                                    $tgl_val_input = (!empty($raw_tgl) && $raw_tgl !== '0000-00-00 00:00:00') ? date('Y-m-d\TH:i', strtotime($raw_tgl)) : '';

                                    $jf_tujuan_val = $row['jf_pkp_tujuan'] ?? ($row['jabatan_saat_ini'] ?? '-');
                                    $selisih_text = ($row['selisih_detail'] !== null) ? $row['selisih_detail']['text'] : '—';
                                    
                                    if ($row['age_detail'] !== null) {
                                        $usia_display = '<strong>' . htmlspecialchars($row['age_detail']['text']) . '</strong><br><small class="text-muted">(Max ' . $row['max_age'] . ' Thn)</small>';
                                    } else {
                                        $usia_display = '<span class="text-danger font-weight-bold">NIP Tidak Valid</span>';
                                    }

                                    $badge_st_pengajuan = renderStatusPengajuanBadge($row);
                                    $badge_jenis        = renderJenisPengajuanBadge($row['jenis_pengajuan'] ?? '');

                                    $url_detail = (($row['jenis_pengajuan'] ?? '') == 'Perpindahan Jabatan') 
                                        ? "detail_verif_perpindahan.php?id=" . $row_id 
                                        : "detail_verif_kenaikan.php?id=" . $row_id;
                                ?>
                                <tr id="row_<?= $row_id; ?>" class="<?= $row_class; ?>">
                                    <td class="text-center font-weight-bold" style="font-size: 16px;"><?= $rank++; ?></td>
                                    <td>
                                        <strong class="text-dark"><?= htmlspecialchars($nama_pengusul); ?></strong>
                                        <br><small class="text-muted"><i class="far fa-id-card mr-1"></i><?= htmlspecialchars($row['nip'] ?? '-'); ?></small>
                                    </td>
                                    <td class="text-center"><?= $badge_jenis; ?></td>
                                    <td> 
                                        <?php if(!empty($disp_gel)): ?>
                                            <span class="badge" style="border: 1px solid #ccc; color: #444; background: #fff;"><i class="fas fa-tag mr-1"></i> <?= htmlspecialchars($tampil_gelombang); ?></span>
                                        <?php else: ?>
                                            <small class="text-muted">-</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center justify-content-between">
                                            <small class="font-weight-bold text-secondary cell-jf-val"><?= htmlspecialchars($jf_tujuan_val); ?></small>
                                            <button type="button" class="btn btn-info btn-edit-inline shadow-sm" title="Edit JF Tujuan"
                                                    onclick="openModalEditJF(<?= $row_id; ?>, '<?= htmlspecialchars($nama_pengusul, ENT_QUOTES); ?>', '<?= htmlspecialchars($jf_tujuan_val, ENT_QUOTES); ?>')">
                                                <i class="fas fa-pencil-alt text-white"></i>
                                            </button>
                                        </div>
                                    </td>
                                    <td class="text-center text-dark cell-usia-val" style="white-space: nowrap;"><?= $usia_display; ?></td>
                                    <td class="text-center"><span class="selisih-pill cell-selisih-val <?= $sel_class; ?>"><?= htmlspecialchars($selisih_text); ?></span></td>
                                    <td>
                                        <div class="d-flex align-items-center justify-content-between">
                                            <small class="text-dark font-weight-bold"><i class="far fa-clock mr-1"></i><span class="cell-tgl-val"><?= $tgl_pengajuan; ?></span></small>
                                            <button type="button" class="btn btn-warning btn-edit-inline shadow-sm btn-edit-tgl" title="Edit Tanggal"
                                                    data-tglinput="<?= $tgl_val_input; ?>"
                                                    onclick="openModalEditTgl(<?= $row_id; ?>, '<?= htmlspecialchars($nama_pengusul, ENT_QUOTES); ?>', this)">
                                                <i class="fas fa-pencil-alt text-dark"></i>
                                            </button>
                                        </div>
                                    </td>
                                    <td><?= !empty($row['verifikator_id']) ? '<span class="badge badge-info p-1">'.htmlspecialchars($row['nama_verifikator'] ?? '').'</span>' : '<span class="badge-verif-none">BELUM DISPOSISI</span>'; ?></td>
                                    <td class="text-center"><?= $badge_st_pengajuan; ?></td>
                                    <td class="text-center cell-prio-val"><?= $status_badge; ?></td>
                                    <td class="text-center">
                                        <a href="<?= $url_detail; ?>" class="btn btn-sm btn-info font-weight-bold elevation-1"><i class="fas fa-search mr-1"></i> Detail</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </section>
</div>

<!-- MODAL EDIT TANGGAL -->
<div class="modal fade" id="modalEditTanggal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <form id="formEditTanggal" method="POST">
                <input type="hidden" name="action" value="update_tanggal">
                <input type="hidden" name="is_ajax" value="1">
                <input type="hidden" name="id_pengajuan" id="edit_id_pengajuan">
                
                <div class="modal-header bg-warning text-dark" style="border-top-left-radius: 12px; border-top-right-radius: 12px;">
                    <h5 class="modal-title font-weight-bold"><i class="fas fa-edit mr-2"></i> Edit Tanggal Pengajuan</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body py-4">
                    <div class="form-group mb-3">
                        <label class="text-muted small font-weight-bold">Nama Pengusul:</label>
                        <input type="text" id="edit_nama_pengusul" class="form-control font-weight-bold bg-light" readonly>
                    </div>
                    <div class="form-group mb-0">
                        <label class="text-dark font-weight-bold">Tanggal & Waktu Pengajuan Baru:</label>
                        <input type="datetime-local" name="tanggal_pengajuan_baru" id="edit_tanggal_pengajuan" class="form-control border-warning" required style="border-width: 2px;">
                    </div>
                </div>
                <div class="modal-footer bg-light" style="border-bottom-left-radius: 12px; border-bottom-right-radius: 12px;">
                    <button type="button" class="btn btn-secondary font-weight-bold" data-dismiss="modal">Batal</button>
                    <button type="submit" id="btnSaveTanggal" class="btn btn-warning font-weight-bold shadow-sm"><i class="fas fa-save mr-1"></i> Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL EDIT JF TUJUAN -->
<div class="modal fade" id="modalEditJF" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <form id="formEditJF" method="POST">
                <input type="hidden" name="action" value="update_jf">
                <input type="hidden" name="is_ajax" value="1">
                <input type="hidden" name="id_pengajuan" id="edit_jf_id_pengajuan">
                
                <div class="modal-header bg-info text-white" style="border-top-left-radius: 12px; border-top-right-radius: 12px;">
                    <h5 class="modal-title font-weight-bold"><i class="fas fa-briefcase mr-2"></i> Edit JF Tujuan</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body py-4">
                    <div class="form-group mb-3">
                        <label class="text-muted small font-weight-bold">Nama Pengusul:</label>
                        <input type="text" id="edit_jf_nama_pengusul" class="form-control font-weight-bold bg-light" readonly>
                    </div>
                    <div class="form-group mb-0">
                        <label class="text-dark font-weight-bold">Pilih Jabatan Fungsional Tujuan:</label>
                        <select name="jf_tujuan_baru" id="edit_jf_tujuan_select" class="form-control border-info" required style="border-width: 2px;">
                            <option value="">-- Pilih JF Tujuan --</option>
                            <option value="Penata Kelola Perumahan Ahli Pertama">Penata Kelola Perumahan Ahli Pertama</option>
                            <option value="Penata Kelola Perumahan Ahli Muda">Penata Kelola Perumahan Ahli Muda</option>
                            <option value="Penata Kelola Perumahan Ahli Madya">Penata Kelola Perumahan Ahli Madya</option>
                            <option value="Penata Kelola Perumahan Ahli Utama">Penata Kelola Perumahan Ahli Utama</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer bg-light" style="border-bottom-left-radius: 12px; border-bottom-right-radius: 12px;">
                    <button type="button" class="btn btn-secondary font-weight-bold" data-dismiss="modal">Batal</button>
                    <button type="submit" id="btnSaveJF" class="btn btn-info font-weight-bold shadow-sm"><i class="fas fa-save mr-1"></i> Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL ALASAN TIDAK ELIGIBLE -->
<div class="modal fade" id="modalEditAlasan" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <form id="formEditAlasan" method="POST">
                <input type="hidden" name="action" value="update_alasan_ineligible">
                <input type="hidden" name="is_ajax" value="1">
                <input type="hidden" name="id_pengajuan" id="edit_alasan_id_pengajuan">
                
                <div class="modal-header bg-dark text-white" style="border-top-left-radius: 12px; border-top-right-radius: 12px;">
                    <h5 class="modal-title font-weight-bold"><i class="fas fa-comment-dots text-warning mr-2"></i> Alasan Tidak Eligible</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body py-4">
                    <div class="form-group mb-3">
                        <label class="text-muted small font-weight-bold">Nama Pengusul:</label>
                        <input type="text" id="edit_alasan_nama_pengusul" class="form-control font-weight-bold bg-light" readonly>
                    </div>
                    <div class="form-group mb-0">
                        <label class="text-dark font-weight-bold">Keterangan / Alasan Tidak Eligible:</label>
                        <textarea name="alasan_tidak_eligible" id="edit_alasan_text" class="form-control border-dark" rows="4" required style="border-width: 2px;"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light" style="border-bottom-left-radius: 12px; border-bottom-right-radius: 12px;">
                    <button type="button" class="btn btn-secondary font-weight-bold" data-dismiss="modal">Batal</button>
                    <button type="submit" id="btnSaveAlasan" class="btn btn-dark font-weight-bold text-warning shadow-sm"><i class="fas fa-save mr-1"></i> Simpan Alasan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'template/footer.php'; ?>

<script>
$(document).ready(function() {
    var table = $('#tablePrioritas').DataTable({
        "responsive": true,
        "autoWidth": false,
        "ordering": false,
        "language": {
            "search": "Cari Cepat:",
            "lengthMenu": "Tampilkan _MENU_ baris",
            "info": "Menampilkan _START_ sampai _END_ dari _TOTAL_ data prioritas"
        }
    });

    $('#filterJenis').on('change', function() { table.column(2).search(this.value).draw(); });
    $('#filterGelombang').on('change', function() { table.column(3).search(this.value).draw(); });
    $('#filterVerifikator').on('change', function() { table.column(8).search(this.value).draw(); });
    $('#filterStatus').on('change', function() { table.column(9).search(this.value).draw(); });

    window.resetFilter = function() {
        $('#filterJenis, #filterGelombang, #filterStatus, #filterVerifikator').val('');
        table.column(2).search('').column(3).search('').column(8).search('').column(9).search('').draw();
    };

    $('#limitSelect').on('change', function() {
        var val = $(this).val();
        table.page.len(val === 'all' ? -1 : parseInt(val)).draw();
    });

    // AJAX TANGGAL
    $('#formEditTanggal').on('submit', function(e) {
        e.preventDefault();
        var $btn = $('#btnSaveTanggal');
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Menyimpan...');

        $.ajax({
            url: 'olah_prioritas.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(res) {
                $btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Simpan Perubahan');
                if (res.status === 'success') {
                    $('#modalEditTanggal').modal('hide');
                    updateRowDOM(res);
                    showToast(res.message);
                } else {
                    alert('Gagal: ' + res.message);
                }
            },
            error: function(xhr, status, err) {
                $btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Simpan Perubahan');
                // Debugging error asli server:
                console.error(xhr.responseText);
                alert('Server Error:\n' + (xhr.responseText.trim() || err || 'Terjadi kesalahan jaringan/server.'));
            }
        });
    });

    // AJAX JF TUJUAN
    $('#formEditJF').on('submit', function(e) {
        e.preventDefault();
        var $btn = $('#btnSaveJF');
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Menyimpan...');

        $.ajax({
            url: 'olah_prioritas.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(res) {
                $btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Simpan Perubahan');
                if (res.status === 'success') {
                    $('#modalEditJF').modal('hide');
                    updateRowDOM(res);
                    showToast(res.message);
                } else {
                    alert('Gagal: ' + res.message);
                }
            },
            error: function(xhr, status, err) {
                $btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Simpan Perubahan');
                console.error(xhr.responseText);
                alert('Server Error:\n' + (xhr.responseText.trim() || err || 'Terjadi kesalahan jaringan/server.'));
            }
        });
    });

    // AJAX ALASAN
    $('#formEditAlasan').on('submit', function(e) {
        e.preventDefault();
        var $btn = $('#btnSaveAlasan');
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Menyimpan...');

        $.ajax({
            url: 'olah_prioritas.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(res) {
                $btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Simpan Alasan');
                if (res.status === 'success') {
                    $('#modalEditAlasan').modal('hide');
                    var $row = $('#row_' + res.id_pengajuan);
                    if ($row.length) {
                        $row.find('.btn-alasan-ineligible').attr('data-alasan', res.alasan);
                        var $alasanText = $row.find('.cell-alasan-text');
                        if (res.alasan.trim() !== '') {
                            $alasanText.removeClass('text-muted').addClass('text-danger font-weight-bold')
                                       .html('<i class="fas fa-info-circle mr-1"></i>' + res.alasan);
                        } else {
                            $alasanText.removeClass('text-danger font-weight-bold').addClass('text-muted')
                                       .html('<i class="fas fa-exclamation-circle mr-1"></i><span class="text-secondary">Alasan belum diisi</span>');
                        }
                    }
                    showToast(res.message);
                } else {
                    alert('Gagal: ' + res.message);
                }
            },
            error: function(xhr, status, err) {
                $btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Simpan Alasan');
                console.error(xhr.responseText);
                alert('Server Error:\n' + (xhr.responseText.trim() || err || 'Terjadi kesalahan jaringan/server.'));
            }
        });
    });

    function updateRowDOM(res) {
        var $row = $('#row_' + res.id_pengajuan);
        if ($row.length) {
            $row.removeClass('super-urgent-row urgent-row normal-row low-row').addClass(res.row_class);
            $row.find('.cell-jf-val').text(res.jf_tujuan);
            $row.find('.cell-usia-val').html(res.usia_display);
            var $selPill = $row.find('.cell-selisih-val');
            $selPill.removeClass('selisih-zero selisih-one selisih-safe selisih-none').addClass(res.sel_class).text(res.selisih_text);
            $row.find('.cell-tgl-val').text(res.tgl_display);
            $row.find('.btn-edit-tgl').attr('data-tglinput', res.tgl_val_input);
            $row.find('.cell-prio-val').html(res.status_badge);
        }
    }

    function showToast(msg) {
        $('#ajaxToastText').text(msg);
        $('#ajaxToast').fadeIn().delay(3000).fadeOut();
    }
});

function openModalEditTgl(id, nama, btnElem) {
    $('#edit_id_pengajuan').val(id);
    $('#edit_nama_pengusul').val(nama);
    $('#edit_tanggal_pengajuan').val($(btnElem).attr('data-tglinput') || '');
    $('#modalEditTanggal').modal('show');
}

function openModalEditJF(id, nama, currentJF) {
    $('#edit_jf_id_pengajuan').val(id);
    $('#edit_jf_nama_pengusul').val(nama);
    $('#edit_jf_tujuan_select').val(currentJF);
    $('#modalEditJF').modal('show');
}

function openModalEditAlasan(btnElem) {
    $('#edit_alasan_id_pengajuan').val($(btnElem).attr('data-id'));
    $('#edit_alasan_nama_pengusul').val($(btnElem).attr('data-nama'));
    $('#edit_alasan_text').val($(btnElem).attr('data-alasan') || '');
    $('#modalEditAlasan').modal('show');
}
</script>