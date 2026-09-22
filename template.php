<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $page_title ?? 'Dashboard'; ?> | Instansi Pembina JF</title>

    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    
    <style>
        .brand-link { background-color: #111827; }
        .brand-link .logo-pupr i { color: #0f62fe; }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
            </li>
        </ul>

        <ul class="navbar-nav ml-auto">
             <li class="nav-item">
                <div style="padding-top: 8px; text-align: right;">
                    Hi, selamat datang kembali!<br>
                    <span class="text-muted" style="font-size:13px">Sistem informasi jabatan fungsional</span>
                </div>
            </li>
        </ul>
    </nav>
    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <a href="index.php" class="brand-link">
            <div class="logo-pupr brand-image img-circle elevation-3" style="opacity: .8; background: #fff; padding: 4px; width: 44px; height: 44px; display: flex; align-items: center; justify-content: center;"><i class="fas fa-building fa-lg"></i></div>
            <span class="brand-text font-weight-light">Instansi Pembina JF</span>
        </a>

        <div class="sidebar">
            <div class="user-panel mt-3 pb-3 mb-3 d-flex">
                <div class="image">
                    <img src="https://i.pravatar.cc/36" class="img-circle elevation-2" alt="User Image">
                </div>
                <div class="info">
                    <a href="#" class="d-block">Admin</a>
                    <span style="font-size:12px;color:#9ca3af; display:block;">Admin</span>
                </div>
            </div>

            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                    <li class="nav-item">
                        <a href="index.php" class="nav-link <?php echo ($page == 'dashboard') ? 'active' : ''; ?>">
                            <i class="nav-icon fas fa-tachometer-alt"></i><p>Dashboard</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="database.php" class="nav-link <?php echo ($page == 'database') ? 'active' : ''; ?>">
                            <i class="nav-icon fas fa-database"></i><p>Database</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="ujikom.php" class="nav-link <?php echo ($page == 'ujikom') ? 'active' : ''; ?>">
                            <i class="nav-icon fas fa-clipboard-list"></i><p>Uji Kompetensi</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="rekomendasi.php" class="nav-link <?php echo ($page == 'rekomendasi') ? 'active' : ''; ?>">
                            <i class="nav-icon fas fa-chart-line"></i><p>Rekomendasi Formasi</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="peraturan.php" class="nav-link <?php echo ($page == 'peraturan') ? 'active' : ''; ?>">
                            <i class="nav-icon fas fa-book"></i><p>Peraturan</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="pengaturan.php" class="nav-link <?php echo ($page == 'pengaturan') ? 'active' : ''; ?>">
                            <i class="nav-icon fas fa-cog"></i><p>Pengaturan</p>
                        </a>
                    </li>
                </ul>
            </nav>
            </div>
        </aside>

    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0"><?php echo $page_title ?? 'Dashboard'; ?></h1>
                    </div>
                </div>
            </div>
        </div>
        <div class="content">
            <div class="container-fluid">
                ```

***

### 📄 File 2: `template/footer.php`

File ini berisi `Footer`, penutup struktur HTML, dan pemanggilan semua *script* JavaScript yang diperlukan.

```php
                </div></div>
        </div>
    <footer class="main-footer">
        <div class="float-right d-none d-sm-inline">
            Jabatan Fungsional Penata Kelola Perumahan
        </div>
        <strong>© 2025 Instansi Pembina JF</strong> — Semua Hak Dilindungi.
    </footer>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.0/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>

</body>
</html>