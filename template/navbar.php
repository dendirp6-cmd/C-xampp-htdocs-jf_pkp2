<?php
// FILE: template/navbar.php - Bagian Navbar (Header Atas) AdminLTE
// UPDATE: Menampilkan Nama Gelombang secara dinamis dan Notifikasi PPSDM

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../koneksi.php';

// 1. Ambil Data dari Session
$nama_user = htmlspecialchars($_SESSION['user_nama_sesi'] ?? 'Pengguna');
$role_user = $_SESSION['user_role_sesi'] ?? 'Guest';
$role_upper = strtoupper($role_user);

$user_email = $_SESSION['email'] ?? $_SESSION['user_email_sesi'] ?? $_SESSION['user_email'] ?? null;
$user_id    = $_SESSION['user_id_sesi'] ?? 0; 
$foto_session = $_SESSION['foto_user_sesi'] ?? ''; 

// --- LOGIKA PENENTUAN LINK BERANDA ---
$link_beranda = "index_asli.php";
if (strpos($role_upper, 'PENGUSUL') !== false) {
    $link_beranda = "index_pengusul.php";
} elseif (strpos($role_upper, 'VERIFIKATOR') !== false) {
    $link_beranda = "index_verifikator.php";
} elseif (strpos($role_upper, 'KASUBDIT') !== false) {
    $link_beranda = "index_kasubdit.php";
} elseif (strpos($role_upper, 'DIREKTUR') !== false) {
    $link_beranda = "index_direktur.php";
} elseif (strpos($role_upper, 'PPSDM') !== false) {
    $link_beranda = "index_ppsdm.php";
}

// 2. Inisialisasi Notifikasi
$notif_count = 0;
$notif_items = [];

// --- A. LOGIKA NOTIFIKASI KHUSUS PENGUSUL ---
if (strpos($role_upper, 'PENGUSUL') !== false && !empty($user_email)) {
    $sql_notif = "SELECT id, jenis_pengajuan, catatan_evaluasi, catatan_evaluator, catatan_ppsdm 
                  FROM pengajuan_ujikom  
                  WHERE email = ? 
                  AND is_read_notif = 0 
                  AND (
                      (catatan_evaluasi IS NOT NULL AND catatan_evaluasi != '' AND catatan_evaluasi != '-') OR 
                      (catatan_evaluator IS NOT NULL AND catatan_evaluator != '' AND catatan_evaluator != '-') OR 
                      (catatan_ppsdm IS NOT NULL AND catatan_ppsdm != '' AND catatan_ppsdm != '-')
                  )
                  ORDER BY id DESC LIMIT 5";
    
    $stmt_notif = $conn->prepare($sql_notif);
    if ($stmt_notif) {
        $stmt_notif->bind_param("s", $user_email);
        $stmt_notif->execute();
        $res_notif = $stmt_notif->get_result();
        
        while ($row_n = $res_notif->fetch_assoc()) {
            $list_pesan = [];
            $target_cols = ['catatan_evaluasi' => 'Sekretariat', 'catatan_evaluator' => 'Evaluator', 'catatan_ppsdm' => 'PPSDM'];

            foreach ($target_cols as $col => $label) {
                if (!empty($row_n[$col]) && $row_n[$col] != '-') {
                    $parts = explode('•', $row_n[$col]);
                    $latest_note = end($parts);
                    $clean_note = trim(preg_replace('/\[.*?\]/', '', $latest_note));
                    if (!empty($clean_note)) {
                        $list_pesan[] = "<strong>$label:</strong> " . $clean_note;
                    }
                }
            }
            $pesan_raw = implode("<br>", $list_pesan);
            
            // --- PERBAIKAN DI SINI: Logika Link Detail Notifikasi ---
            $link_detail_notif = "detail_isian.php?id="; // Default untuk Perpindahan Jabatan
            if (stripos($row_n['jenis_pengajuan'], 'Kenaikan') !== false) {
                $link_detail_notif = "detail_isian_kenaikan.php?id="; // Untuk Kenaikan Jabatan
            }

            $notif_count++;
            $notif_items[] = [
                'id' => $row_n['id'],
                'judul' => "Catatan Baru: " . $row_n['jenis_pengajuan'],
                'pesan' => str_replace(["'", '"', "\r", "\n"], ["\'", '\"', "", ""], $pesan_raw),
                'icon' => "fas fa-comment-dots text-danger",
                'link' => $link_detail_notif, // Menggunakan link dinamis hasil pengecekan
                'mode' => 'redirect'
            ];
        }
        $stmt_notif->close();
    }
}

// --- B. LOGIKA NOTIFIKASI KHUSUS KASUBDIT ---
if (strpos($role_upper, 'KASUBDIT') !== false) {
    $sql_k = "SELECT id, nama, jenis_pengajuan FROM pengajuan_ujikom 
              WHERE (verifikator_id IS NULL OR verifikator_id = 0)
              AND (is_read_kasubdit = 0 OR is_read_kasubdit IS NULL)
              ORDER BY id DESC";
    
    $res_k = $conn->query($sql_k);
    if ($res_k && $res_k->num_rows > 0) {
        $count_data = $res_k->num_rows;
        $notif_count += 1; 
        
        $ids = [];
        $daftar_nama = [];
        while ($row_k = $res_k->fetch_assoc()) {
            $ids[] = $row_k['id'];
            $daftar_nama[] = "• " . htmlspecialchars($row_k['nama']) . " (" . $row_k['jenis_pengajuan'] . ")";
        }
        
        $pesan_gabungan = "Terdapat <strong>$count_data pengajuan baru</strong> yang perlu didisposisikan:<br><br>" . implode("<br>", array_slice($daftar_nama, 0, 5));
        if ($count_data > 5) $pesan_gabungan .= "<br>...dan " . ($count_data - 5) . " lainnya.";

        $notif_items[] = [
            'id' => implode(',', $ids),
            'judul' => "Pemberitahuan Disposisi",
            'pesan' => str_replace(["'", '"'], ["\'", '\"'], $pesan_gabungan),
            'icon' => "fas fa-envelope text-warning",
            'link' => "index_kasubdit.php?update_notif_id=",
            'mode' => 'dismiss' 
        ];
    }
}

// --- C. LOGIKA NOTIFIKASI KHUSUS VERIFIKATOR ---
if (strpos($role_upper, 'VERIFIKATOR') !== false) {
    $sql_v = "SELECT id, nama, jenis_pengajuan FROM pengajuan_ujikom 
              WHERE verifikator_id = ? 
              AND status_pengajuan = 'Verifikasi Dokumen'
              AND (is_read_verif = 0 OR is_read_verif IS NULL)
              ORDER BY id DESC";
    
    $stmt_v = $conn->prepare($sql_v);
    if ($stmt_v) {
        $stmt_v->bind_param("i", $user_id);
        $stmt_v->execute();
        $res_v = $stmt_v->get_result();
        
        if ($res_v->num_rows > 0) {
            $count_v = $res_v->num_rows;
            $notif_count += 1; 
            
            $ids_v = [];
            $daftar_nama_v = [];
            while ($row_v = $res_v->fetch_assoc()) {
                $ids_v[] = $row_v['id'];
                $daftar_nama_v[] = "• " . htmlspecialchars($row_v['nama']) . " (" . $row_v['jenis_pengajuan'] . ")";
            }
            
            $pesan_v = "Anda menerima <strong>$count_v disposisi verifikasi</strong> baru:<br><br>" . implode("<br>", array_slice($daftar_nama_v, 0, 5));
            if ($count_v > 5) $pesan_v .= "<br>...dan " . ($count_v - 5) . " lainnya.";

            $notif_items[] = [
                'id' => implode(',', $ids_v),
                'judul' => "Tugas Verifikasi Baru",
                'pesan' => str_replace(["'", '"'], ["\'", '\"'], $pesan_v),
                'icon' => "fas fa-file-signature text-primary",
                'link' => "index_verifikator.php?update_read_v=", 
                'mode' => 'dismiss' 
            ];
        }
        $stmt_v->close();
    }
}

// --- D. LOGIKA NOTIFIKASI KHUSUS DIREKTUR ---
if (strpos($role_upper, 'DIREKTUR') !== false) {
    $sql_d = "SELECT p.id, p.nama, p.jenis_pengajuan, g.gelombang 
              FROM pengajuan_ujikom p
              LEFT JOIN tb_gelombang g ON p.gelombang = g.id
              WHERE p.status_pengajuan = 'Proses Direktur' 
              AND (p.is_read_direktur = 0 OR p.is_read_direktur IS NULL)
              ORDER BY p.id DESC";
    
    $res_d = $conn->query($sql_d);
    if ($res_d && $res_d->num_rows > 0) {
        $count_d = $res_d->num_rows;
        $notif_count += 1; 

        $ids_d = [];
        $daftar_nama_d = [];
        $nama_gel_notif_d = "";

        while ($row_d = $res_d->fetch_assoc()) {
            $ids_d[] = $row_d['id'];
            $daftar_nama_d[] = "• " . htmlspecialchars($row_d['nama']) . " (" . $row_d['jenis_pengajuan'] . ")";
            if(empty($nama_gel_notif_d)) $nama_gel_notif_d = $row_d['gelombang'];
        }

        $pesan_d = "Terdapat <strong>$count_d pengajuan</strong> yang memerlukan validasi Anda:<br><br>" . implode("<br>", array_slice($daftar_nama_d, 0, 5));
        if ($count_d > 5) $pesan_d .= "<br>...dan " . ($count_d - 5) . " lainnya.";

        $notif_items[] = [
            'id' => implode(',', $ids_d),
            'judul' => "Usulan Baru: " . ($nama_gel_notif_d ?? 'Gelombang Unknown'),
            'pesan' => str_replace(["'", '"'], ["\'", '\"'], $pesan_d),
            'icon' => "fas fa-user-check text-success",
            'link' => "index_direktur.php?update_read_d=",
            'mode' => 'dismiss'
        ];
    }
}

// --- E. LOGIKA NOTIFIKASI KHUSUS PPSDM (Fixed & Updated) ---
if (strpos($role_upper, 'PPSDM') !== false) {
    // Join dengan tb_gelombang agar judul notifikasi lebih informatif
    $sql_p = "SELECT p.id, p.nama, p.jenis_pengajuan, g.gelombang 
              FROM pengajuan_ujikom p
              LEFT JOIN tb_gelombang g ON p.gelombang = g.id
              WHERE p.status_pengajuan = 'Proses PPSDM' 
              AND (p.is_read_ppsdm = 0 OR p.is_read_ppsdm IS NULL)
              ORDER BY p.id DESC";
    
    $res_p = $conn->query($sql_p);
    if ($res_p && $res_p->num_rows > 0) {
        $count_p = $res_p->num_rows;
        $notif_count += 1;

        $ids_p = [];
        $daftar_nama_p = [];
        $nama_gel_notif_p = "";

        while ($row_p = $res_p->fetch_assoc()) {
            $ids_p[] = $row_p['id'];
            $daftar_nama_p[] = "• " . htmlspecialchars($row_p['nama']) . " (" . $row_p['jenis_pengajuan'] . ")";
            if(empty($nama_gel_notif_p)) $nama_gel_notif_p = $row_p['gelombang'];
        }

        $pesan_p = "Terdapat <strong>$count_p berkas masuk</strong> dari Direktur untuk dievaluasi:<br><br>" . implode("<br>", array_slice($daftar_nama_p, 0, 5));
        if ($count_p > 5) $pesan_p .= "<br>...dan " . ($count_p - 5) . " lainnya.";

        $notif_items[] = [
            'id' => implode(',', $ids_p),
            'judul' => "Evaluasi Baru: " . ($nama_gel_notif_p ?? 'Gelombang Unknown'),
            'pesan' => str_replace(["'", '"'], ["\'", '\"'], $pesan_p),
            'icon' => "fas fa-sync-alt text-info",
            'link' => "index_ppsdm.php?update_read_p=", // Mengarah ke parameter update_read_p
            'mode' => 'dismiss'
        ];
    }
}

// 3. Definisi Path Foto Profil
$path_foto_asli = "assets/profile/" . $foto_session;
$url_foto_final = (empty($foto_session) || !file_exists(__DIR__ . "/../assets/profile/" . $foto_session)) 
    ? "https://ui-avatars.com/api/?name=" . urlencode($nama_user) . "&background=0D8ABC&color=fff&size=128"
    : $path_foto_asli . "?t=" . time();
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
.badge-notif-animation { animation: pulse-red 2s infinite; box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7); }
@keyframes pulse-red {
    0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7); }
    70% { transform: scale(1.1); box-shadow: 0 0 0 10px rgba(220, 53, 69, 0); }
    100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
}
.dropdown-item { white-space: normal !important; }
</style>

<script>
function handleNotif(id, judul, pesan, targetLink, mode) {
    if (mode === 'dismiss') {
        Swal.fire({
            title: '<strong>' + judul + '</strong>',
            icon: 'info',
            html: '<div style="text-align: left; background: #f8f9fa; padding: 15px; border-radius: 5px; border-left: 4px solid #f39c12;">' + pesan + '</div>',
            confirmButtonText: 'OK, Saya Mengerti',
            confirmButtonColor: '#3085d6'
        }).then((result) => {
            if (result.isConfirmed) {
                // Eksekusi update is_read dan reload
                fetch(targetLink + id).then(() => { location.reload(); });
            }
        });
    } else {
        Swal.fire({
            title: '<strong>DETAIL NOTIFIKASI</strong>',
            icon: 'info',
            html: '<div style="text-align: left; background: #f8f9fa; padding: 15px; border-radius: 5px; border-left: 4px solid #3085d6;">' + 
                  '<h5 style="font-size:16px;">' + judul + '</h5><hr>' + pesan + '</div>',
            showCloseButton: true,
            confirmButtonText: '<i class="fas fa-external-link-alt"></i> Buka Data',
            confirmButtonColor: '#3085d6',
            showCancelButton: true,
            cancelButtonText: 'Tutup'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = targetLink + id;
            }
        });
    }
}
</script>

<nav class="main-header navbar navbar-expand navbar-white navbar-light">
    <ul class="navbar-nav">
        <li class="nav-item">
            <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
        </li>
    </ul>

    <ul class="navbar-nav ml-auto">
        <li class="nav-item d-none d-sm-inline-block">
             <div style="padding-top: 8px; text-align: right; padding-right: 15px;">
                 <span class="text-dark font-weight-bold">Halo, <?= $nama_user; ?>!</span><br>
                 <span class="text-muted" style="font-size:13px">Sistem Informasi Jabatan Fungsional</span>
             </div>
        </li>

        <li class="nav-item dropdown">
            <a class="nav-link" data-toggle="dropdown" href="#">
                <i class="far fa-bell"></i>
                <?php if ($notif_count > 0): ?>
                    <span class="badge badge-danger navbar-badge badge-notif-animation"><?= $notif_count; ?></span>
                <?php endif; ?>
            </a>
            <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                <span class="dropdown-item dropdown-header"><?= ($notif_count > 0) ? 'Terdapat ' . $notif_count : 'Tidak ada'; ?> Notifikasi Baru</span>
                <div class="dropdown-divider"></div>
                
                <?php if (!empty($notif_items)): ?>
                    <?php foreach ($notif_items as $item): ?>
                        <a href="javascript:void(0);" 
                           onclick="handleNotif('<?= $item['id']; ?>', '<?= htmlspecialchars($item['judul']); ?>', '<?= $item['pesan']; ?>', '<?= $item['link']; ?>', '<?= $item['mode']; ?>')" 
                           class="dropdown-item">
                            <i class="<?= $item['icon']; ?> mr-2"></i> 
                            <span style="font-size: 13px; font-weight: 600;"><?= htmlspecialchars($item['judul']); ?></span>
                        </a>
                        <div class="dropdown-divider"></div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="dropdown-item text-center text-muted small">Tidak ada pemberitahuan baru</div>
                    <div class="dropdown-divider"></div>
                <?php endif; ?>

                <a href="<?= $link_beranda; ?>" class="dropdown-item dropdown-footer">Lihat Dashboard Utama</a>
            </div>
        </li>

        <li class="nav-item dropdown user-menu ml-3">
            <a href="#" class="nav-link dropdown-toggle" data-toggle="dropdown">
                <img src="<?= $url_foto_final; ?>" class="user-image img-circle elevation-1" style="object-fit: cover; width: 30px; height: 30px;" alt="User Image">
                <span class="d-none d-md-inline ml-1"><?= $nama_user; ?></span>
            </a>
            
            <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                <li class="user-header bg-primary">
                    <img src="<?= $url_foto_final; ?>" class="img-circle elevation-2" style="object-fit: cover; width: 90px; height: 90px; background: white;" alt="User Image">
                    <p>
                        <?= $nama_user; ?>
                        <small><?= strtoupper(str_replace('user_', '', $role_user)); ?></small>
                    </p>
                </li>
                
                <li class="user-footer d-flex justify-content-between p-2">
                    <a href="profile.php" class="btn btn-sm btn-default btn-flat"><i class="fas fa-user-circle"></i> Profil</a>
                    <a href="pengaturan.php" class="btn btn-sm btn-default btn-flat mx-1"><i class="fas fa-cog"></i> Pengaturan</a>
                    <a href="logout.php" class="btn btn-sm btn-default btn-flat text-danger"><i class="fas fa-sign-out-alt"></i> Sign out</a>
                </li>
            </ul>
        </li>
    </ul>
</nav>