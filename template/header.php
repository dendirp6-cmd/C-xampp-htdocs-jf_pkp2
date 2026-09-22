<?php
/**
 * ==================================================================================
 * FILE: template/header.php
 * DESKRIPSI: Header Global AdminLTE + Integrasi Background Dinamis Global
 * ==================================================================================
 */

// Konfigurasi dan Pengecekan Background Global
$bg_setting_file = __DIR__ . '/../assets/image/active_bg.txt'; 
$current_bg = 'assets/image/bg_tes1.jpg'; // Default background

if (file_exists($bg_setting_file)) {
    $saved_bg = trim(file_get_contents($bg_setting_file));
    // Jika file gambar yang disimpan benar-benar ada di direktori
    if (!empty($saved_bg) && file_exists(__DIR__ . '/../' . $saved_bg)) {
        $current_bg = $saved_bg;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $page_title ?? 'Sistem Informasi JF'; ?></title>

  <!-- SweetAlert2 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <!-- Google Font: Source Sans Pro -->
  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
  
  <!-- 1. FONT AWESOME (ONLINE CDN) -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <!-- 2. ADMINLTE CSS (ONLINE CDN) -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
  
  <!-- 3. DATATABLES CSS -->
  <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap4.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.bootstrap4.min.css">
  
  <style>
      /* Perbaikan kecil agar layout tidak berantakan saat loading */
      .wrapper { min-height: 100vh; }

      /* ==============================================================================
         PENGATURAN BACKGROUND GLOBAL UNTUK SELURUH HALAMAN
         ============================================================================== */
      .content-wrapper { 
          background-image: url('<?= htmlspecialchars($current_bg); ?>') !important;
          background-size: cover !important;
          background-position: center !important;
          background-repeat: no-repeat !important;
          background-attachment: fixed !important;
      }

      /* Efek semi-transparan pada kartu-kartu konten agar teks tetap sangat mudah dibaca */
      .filter-card, .main-card, .rekap-card, .bg-upload-card, .card {
          background-color: rgba(255, 255, 255, 0.95) !important;
          backdrop-filter: blur(3px);
      }
  </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">