<?php
// background_config.php

$bg_setting_file = __DIR__ . '/assets/image/active_bg.txt'; 
$current_bg = 'assets/image/bg_tes1.jpg';        // Default background

if (file_exists($bg_setting_file)) {
    $saved_bg = trim(file_get_contents($bg_setting_file));
    if (!empty($saved_bg) && file_exists(__DIR__ . '/' . $saved_bg)) {
        $current_bg = $saved_bg;
    }
}
?>
<!-- CSS Global yang akan otomatis berlaku di semua halaman -->
<style>
    .content-wrapper { 
        background-image: url('<?= htmlspecialchars($current_bg); ?>') !important;
        background-size: cover !important;
        background-position: center !important;
        background-repeat: no-repeat !important;
        background-attachment: fixed !important;
    }

    /* Elemen kartu semi-transparan agar seragam di semua halaman */
    .filter-card, .main-card, .rekap-card, .bg-upload-card {
        background-color: rgba(255, 255, 255, 0.95) !important;
        backdrop-filter: blur(3px);
    }
</style>