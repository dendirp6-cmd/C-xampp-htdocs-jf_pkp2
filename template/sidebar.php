<?php
/**
 * FILE: sidebar.php
 * DESKRIPSI: Sidebar Navigasi Transparan (Glassmorphism) terintegrasi background_config.php
 */

// =========================================================
// 1. KONEKSI BACKGROUND CONFIG & PENGATURAN SESI
// =========================================================
if (file_exists('background_config.php')) {
    include_once 'background_config.php';
}

$user_role = $_SESSION['user_role_sesi'] ?? 'Guest'; 

// Cek Role spesifik
$is_pengusul         = ($user_role === 'user_pengusul'); 
$is_verifikator_only = ($user_role === 'user_verifikator');
$is_kasubdit         = ($user_role === 'user_kasubdit'); 
$is_direktur         = ($user_role === 'user_direktur'); 
$is_super_admin      = ($user_role === 'user_super_admin'); 
$is_admin            = ($user_role === 'user_admin'); 
$is_evaluator        = ($user_role === 'user_evaluator'); 
$is_ppsdm            = ($user_role === 'user_ppsdm');

$is_admin_verifikator = (
    $is_admin || 
    $is_verifikator_only || 
    $is_super_admin
);

/**
 * PENGATURAN HAK AKSES MENU
 */
// Menu Bimtek disembunyikan untuk pengusul, direktur, dan ppsdm
$can_access_bimtek           = (!$is_pengusul && !$is_direktur && !$is_ppsdm); 
$can_access_rekomendasi      = (!$is_pengusul && !$is_direktur && !$is_ppsdm); 
// Menu Ujikom disembunyikan untuk direktur dan ppsdm
$show_ujikom_menu            = (!$is_direktur && !$is_ppsdm); 
$can_access_pengajuan_ujikom = $is_admin_verifikator || $is_pengusul;
$can_access_log_update       = ($is_super_admin || $is_admin);

// =========================================================
// 2. LOGIKA PARAMETER URL & AUTO-HIDE MENU
// =========================================================
$current_page = basename($_SERVER['PHP_SELF']);

$hide_perpindahan = isset($_GET['hide_perpindahan']) && $_GET['hide_perpindahan'] == '1';
$hide_kenaikan    = isset($_GET['hide_kenaikan']) && $_GET['hide_kenaikan'] == '1';

$pages_to_hide_kenaikan = [
    'form_perpindahan_jabatan.php', 
    'detail_isian.php', 
    'edit_isian.php'
];

if (in_array($current_page, $pages_to_hide_kenaikan)) {
    $hide_kenaikan = true;
} elseif ($current_page == 'form_kenaikan_jabatan.php') {
    $hide_perpindahan = true;
}

$append_url = '';
if ($hide_perpindahan) {
    $append_url = '?hide_perpindahan=1';
} elseif ($hide_kenaikan) {
    $append_url = '?hide_kenaikan=1';
}

// =========================================================
// 3. LOGIKA AKTIVITAS MENU (HIGHLIGHTING)
// =========================================================
$page     = $page ?? ''; 
$sub_page = $sub_page ?? ''; 

$is_dashboard_active = (
    in_array($current_page, [
        'index_asli.php',
        'index_verifikator.php',
        'index_pengusul.php',
        'index_evaluator.php',
        'index_kasubdit.php',
        'index_direktur.php',
        'index_ppsdm.php'
    ]) || $page == 'dashboard'
);

// Logika Menu Database (Khusus Super Admin)
$database_pages = ['database.php', 'daftar_pegawai.php'];
$is_database_active = in_array($current_page, $database_pages) || $page == 'pegawai';
$database_menu_open = $is_database_active ? 'menu-open' : '';

// Logika Menu Rekomendasi Formasi
$rekomendasi_pages     = ['rekomendasi', 'input_rekom', 'evaluasi', 'list_rekom', 'list_rekom_pengusul'];
$rekomendasi_files     = ['input_rekom.php', 'list_rekom.php', 'list_rekom_pengusul.php'];
$is_rekomendasi_active = in_array($page, $rekomendasi_pages) || in_array($sub_page, $rekomendasi_pages) || in_array($current_page, $rekomendasi_files);
$rekomendasi_menu_open = $is_rekomendasi_active ? 'menu-open' : '';

// Logika Menu Uji Kompetensi
$ujikom_files = ['form_perpindahan_jabatan.php', 'form_kenaikan_jabatan.php', 'list_perpindahan_pengusul.php', 'detail_isian.php', 'edit_isian.php', 'rekap_ujikom.php', 'list_perpindahan.php', 'list_kenaikan.php'];
$is_ujikom_active = (
    $page == 'ujikom' || 
    in_array($sub_page, ['perpindahan_jabatan', 'form_kenaikan', 'list_perpindahan', 'list_kenaikan', 'list_perpindahan_pengusul', 'rekap_ujikom']) ||
    in_array($current_page, $ujikom_files)
) && !$is_dashboard_active;

$ujikom_menu_open = $is_ujikom_active ? 'menu-open' : '';

$data_ujikom_files = ['list_perpindahan.php', 'list_kenaikan.php', 'rekap_ujikom.php'];
$is_data_ujikom_active = (in_array($sub_page, ['list_perpindahan', 'list_kenaikan', 'rekap_ujikom']) || in_array($current_page, $data_ujikom_files)) && !$is_dashboard_active;
$data_ujikom_menu_open = $is_data_ujikom_active ? 'menu-open' : '';

// Logika Menu Bimtek
$bimtek_pages = [
    'daftar_peserta_bimtek.php',
    'bimtek_daftar.php', 
    'dokumentasi.php', 
    'materi_bimtek.php', 
    'pertanggungjawaban.php', 
    'realisasi.php',
    'bimtek_progres.php', 
    'bimtek_suket.php'
];
$is_bimtek_active = in_array($current_page, $bimtek_pages) || $page == 'bimtek';
$bimtek_menu_open = $is_bimtek_active ? 'menu-open' : '';

$list_rekom_text = $is_pengusul ? 'Rekomendasi Saya' : 'List Rekomendasi';
$list_rekom_url  = $is_pengusul ? 'list_rekom_pengusul.php' : 'list_rekom.php';
?>

<!-- ========================================================= -->
<!-- 4. OVERRIDE CSS SPESIFIK UNTUK SIDEBAR TRANSPARAN          -->
<!-- ========================================================= -->
<style>
    /* Mengatasi warna bawaan AdminLTE dengan spesifisitas tinggi */
    html body .main-sidebar,
    html body .main-sidebar.sidebar-dark-primary,
    html body .sidebar-dark-primary .sidebar {
        background-color: rgba(15, 23, 42, 0.55) !important;
        background-image: none !important;
        backdrop-filter: blur(12px) !important;
        -webkit-backdrop-filter: blur(12px) !important;
        border-right: 1px solid rgba(255, 255, 255, 0.12) !important;
        box-shadow: none !important;
    }

    /* Area Logo / Brand Link */
    html body .main-sidebar .brand-link,
    html body .sidebar-dark-primary .brand-link {
        background-color: transparent !important;
        border-bottom: 1px solid rgba(255, 255, 255, 0.12) !important;
    }

    /* Submenu Dropdown Area */
    html body .main-sidebar .nav-treeview {
        background-color: rgba(0, 0, 0, 0.25) !important;
        border-radius: 6px;
    }

    /* Hover & Active States */
    html body .main-sidebar .nav-link:hover {
        background-color: rgba(255, 255, 255, 0.12) !important;
        border-radius: 4px;
    }

    html body .main-sidebar .nav-link.active,
    html body .sidebar-dark-primary .nav-sidebar > .nav-item > .nav-link.active {
        background-color: rgba(255, 255, 255, 0.22) !important;
        color: #ffffff !important;
        border-radius: 4px;
        box-shadow: none !important;
    }

    /* Warna Teks & Icon */
    html body .main-sidebar .nav-link,
    html body .main-sidebar .nav-link p,
    html body .main-sidebar .nav-header {
        color: #e2e8f0 !important;
    }
</style>

<aside class="main-sidebar sidebar-dark-primary elevation-4">
    <a href="index_asli.php" class="brand-link" style="pointer-events: none; cursor: default;">
        <img src="assets/logo_instansi.jpeg" alt="Logo" class="brand-image img-circle elevation-3" style="opacity: .8">
        <span class="brand-text font-weight-light">Instansi Pembina JF</span>
    </a>

    <div class="sidebar">
        <nav class="mt-2">
            <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                
                <!-- DASHBOARD -->
                <li class="nav-item">
                    <a href="<?php 
                        if ($is_pengusul) echo 'index_pengusul.php' . $append_url;
                        elseif ($is_verifikator_only) echo 'index_verifikator.php';
                        elseif ($is_evaluator) echo 'index_evaluator.php';
                        elseif ($is_kasubdit) echo 'index_kasubdit.php';
                        elseif ($is_direktur) echo 'index_direktur.php';
                        elseif ($is_ppsdm) echo 'index_ppsdm.php';
                        else echo 'index_asli.php' . $append_url;
                        ?>" 
                       class="nav-link <?php echo $is_dashboard_active ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-tachometer-alt"></i>
                        <p>Dashboard</p>
                    </a>
                </li>

                <!-- DATABASE (SUBMENU KHUSUS SUPER ADMIN) -->
                <?php if ($is_super_admin): ?>
                <li class="nav-item <?php echo $database_menu_open; ?>">
                    <a href="#" class="nav-link <?php echo $is_database_active ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-database text-info"></i>
                        <p>
                            Database
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="database.php" class="nav-link <?php echo ($current_page == 'database.php') ? 'active' : ''; ?>">
                                <i class="fas fa-server nav-icon text-primary"></i>
                                <p>Data Peg PKP & Daerah</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="daftar_pegawai.php" class="nav-link <?php echo ($current_page == 'daftar_pegawai.php' || $page == 'pegawai') ? 'active' : ''; ?>">
                                <i class="fas fa-users-cog nav-icon text-success"></i>
                                <p>Data Seluruh Peg PKP</p>
                            </a>
                        </li>
                    </ul>
                </li>
                <?php endif; ?>
                
                <li class="nav-header">MENU UTAMA</li>

                <!-- 1. MENU UJIKOM -->
                <?php if ($show_ujikom_menu): ?>
                <li class="nav-item <?php echo $ujikom_menu_open; ?>">
                    <a href="#" class="nav-link <?php echo $is_ujikom_active ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-clipboard-list text-primary"></i>
                        <p>
                            Menu Ujikom
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <?php if ($is_pengusul): ?>
                            
                            <?php if (!$hide_perpindahan): ?>
                            <li class="nav-item">
                                <a href="form_perpindahan_jabatan.php<?php echo $append_url; ?>" class="nav-link <?php echo ($current_page == 'form_perpindahan_jabatan.php') ? 'active' : ''; ?>">
                                    <i class="fas fa-exchange-alt nav-icon text-primary"></i>
                                    <p>Perpindahan Jabatan</p>
                                </a>
                            </li>
                            <?php endif; ?>

                            <?php if (!$hide_kenaikan): ?>
                            <li class="nav-item">
                                <a href="form_kenaikan_jabatan.php<?php echo $append_url; ?>" class="nav-link <?php echo ($current_page == 'form_kenaikan_jabatan.php') ? 'active' : ''; ?>">
                                    <i class="fas fa-level-up-alt nav-icon text-success"></i>
                                    <p>Kenaikan Jabatan</p>
                                </a>
                            </li>
                            <?php endif; ?>

                            <li class="nav-item">
                                <a href="list_perpindahan_pengusul.php<?php echo $append_url; ?>" class="nav-link <?php echo ($current_page == 'list_perpindahan_pengusul.php') ? 'active' : ''; ?>">
                                    <i class="fas fa-user-check nav-icon text-info"></i>
                                    <p>Daftar Pengajuan Saya</p>
                                </a>
                            </li>

                        <?php else: ?>
                            
                            <li class="nav-item">
                                <a href="form_perpindahan_jabatan.php" class="nav-link <?php echo ($current_page == 'form_perpindahan_jabatan.php') ? 'active' : ''; ?>">
                                    <i class="fas fa-exchange-alt nav-icon text-primary"></i><p>Perpindahan Jabatan</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="form_kenaikan_jabatan.php" class="nav-link <?php echo ($current_page == 'form_kenaikan_jabatan.php') ? 'active' : ''; ?>">
                                    <i class="fas fa-level-up-alt nav-icon text-success"></i><p>Kenaikan Jabatan</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="list_perpindahan.php" class="nav-link <?php echo ($current_page == 'list_perpindahan.php') ? 'active' : ''; ?>">
                                    <i class="fas fa-list-ul nav-icon text-primary"></i><p>List Perpindahan</p>
                                </a>
                            </li>
                            <li class="nav-item"> 
                                <a href="list_kenaikan.php" class="nav-link <?php echo ($current_page == 'list_kenaikan.php') ? 'active' : ''; ?>">
                                    <i class="fas fa-list-ol nav-icon text-success"></i><p>List Kenaikan</p>
                                </a>
                            </li>
                            <li class="nav-item"> 
                                <a href="rekap_ujikom.php" class="nav-link <?php echo ($current_page == 'rekap_ujikom.php') ? 'active' : ''; ?>">
                                    <i class="fas fa-file-invoice nav-icon text-warning"></i><p>Rekap Peserta Ujikom</p>
                                </a>
                            </li>

                        <?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>

                <!-- 2. MENU BIMTEK -->
                <?php if ($can_access_bimtek): ?>
                <li class="nav-item <?php echo $bimtek_menu_open; ?>">
                    <a href="#" class="nav-link <?php echo $is_bimtek_active ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-chalkboard-teacher text-success"></i>
                        <p>
                            Menu Bimtek
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="daftar_peserta_bimtek.php" class="nav-link <?php echo ($current_page == 'daftar_peserta_bimtek.php') ? 'active' : ''; ?>">
                                <i class="fas fa-users nav-icon text-primary"></i>
                                <p>Daftar Peserta Bimtek</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="bimtek_suket.php" class="nav-link <?php echo ($current_page == 'bimtek_suket.php') ? 'active' : ''; ?>">
                                <i class="fas fa-award nav-icon text-warning"></i>
                                <p>
                                    Surat Keterangan
                                    <span class="badge badge-success right">Otomatis</span>
                                </p>
                            </a>
                        </li>
                    </ul>
                </li>
                <?php endif; ?>

                <!-- 3. MENU REKOMENDASI FORMASI -->
                <?php if ($can_access_rekomendasi): ?>
                <li class="nav-item <?php echo $rekomendasi_menu_open; ?>">
                    <a href="#" class="nav-link <?php echo $is_rekomendasi_active ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-chart-line text-warning"></i>
                        <p>
                            Menu Rekomendasi Formasi
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <?php if (!$is_pengusul): ?>
                        <li class="nav-item">
                            <a href="input_rekom.php" class="nav-link <?php echo ($current_page == 'input_rekom.php') ? 'active' : ''; ?>">
                                <i class="fas fa-plus-circle nav-icon text-success"></i>
                                <p>Input Rekomendasi</p>
                            </a>
                        </li>
                        <?php endif; ?>
                        <li class="nav-item">
                            <a href="<?php echo $list_rekom_url; ?>" class="nav-link <?php echo ($current_page == $list_rekom_url) ? 'active' : ''; ?>">
                                <i class="fas fa-tasks nav-icon text-info"></i>
                                <p><?php echo $list_rekom_text; ?></p>
                            </a>
                        </li>
                    </ul>
                </li>
                <?php endif; ?>

                <li class="nav-header">LAINNYA</li>

                <!-- PERATURAN -->
                <?php if ($is_admin_verifikator): ?>
                <li class="nav-item">
                    <a href="peraturan.php" class="nav-link <?php echo ($current_page == 'peraturan.php') ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-book"></i>
                        <p>Peraturan</p>
                    </a>
                </li>
                <?php endif; ?>

                <!-- PENGATURAN -->
                <li class="nav-item">
                    <a href="pengaturan.php<?php echo $append_url; ?>" class="nav-link <?php echo ($current_page == 'pengaturan.php') ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-cog"></i>
                        <p>Pengaturan</p>
                    </a>
                </li>

                <!-- LOG UPDATE -->
                <?php if ($can_access_log_update): ?>
                <li class="nav-item">
                    <a href="catatan_update.php<?php echo $append_url; ?>" class="nav-link <?php echo ($current_page == 'catatan_update.php') ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-history text-info"></i>
                        <p>Log Update</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="admin_update.php<?php echo $append_url; ?>" class="nav-link <?php echo ($current_page == 'admin_update.php') ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-tools text-warning"></i>
                        <p>Admin Update</p>
                    </a>
                </li>
                <?php endif; ?>
                
                <!-- LOGOUT -->
                <li class="nav-item">
                    <a href="logout.php" class="nav-link">
                        <i class="nav-icon fas fa-sign-out-alt text-danger"></i>
                        <p>Logout</p>
                    </a>
                </li>
            </ul>
        </nav>
    </div>
</aside>