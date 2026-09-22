<?php
// detail_ujikom.php - Halaman Detail dan Edit Peserta Uji Kompetensi

require_once 'koneksi.php'; 

// --- FUNGSI HELPER UNTUK STATUS ---
function get_status_class_ujikom($status) {
    $status_lower = strtolower(trim($status));
    if (strpos($status_lower, 'terverifikasi') !== false || strpos($status_lower, 'lulus') !== false) {
        return 'green';
    } elseif (strpos($status_lower, 'perbaikan') !== false) {
        return 'orange'; 
    } else {
        return 'blue'; // Menunggu Verifikasi
    }
}

$message = '';
$is_error = false;
$data_ditemukan = false;
$id_peserta = null;

// Pastikan ID diterima
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id_peserta = $_GET['id'];
} else {
    $is_error = true;
    $message = "ID Peserta tidak valid atau tidak ditemukan.";
}

// --- LOGIKA UPDATE DATA (SETELAH ADMIN MENGUBAH) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_peserta']) && $id_peserta) {
    
    // Ambil dan Bersihkan Data dari Form
    $nip = mysqli_real_escape_string($conn, $_POST['nip']);
    $nama_lengkap_gelar = mysqli_real_escape_string($conn, $_POST['nama_lengkap_gelar']);
    $no_wa = mysqli_real_escape_string($conn, $_POST['no_wa']);
    $instansi = mysqli_real_escape_string($conn, $_POST['instansi']);
    $unit_organisasi = mysqli_real_escape_string($conn, $_POST['unit_organisasi']);
    $unit_kerja = mysqli_real_escape_string($conn, $_POST['unit_kerja']);
    $status_baru = mysqli_real_escape_string($conn, $_POST['status_verifikasi']); // Status Verifikasi

    // Query Update
    $sql_update = "UPDATE {$NAMA_TABEL_UJIKOM} SET 
                   nip=?, nama_lengkap_gelar=?, no_wa=?, instansi=?, 
                   unit_organisasi=?, unit_kerja=?, status_verifikasi=? 
                   WHERE id=?";
    
    $stmt = mysqli_prepare($conn, $sql_update);
    
    // Asumsi tabel ujikom memiliki kolom status_verifikasi
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "sssssssi", 
            $nip, $nama_lengkap_gelar, $no_wa, $instansi, 
            $unit_organisasi, $unit_kerja, $status_baru, $id_peserta);
        
        if (mysqli_stmt_execute($stmt)) {
            $is_error = false;
            $message = "✅ Data peserta NIP **{$nip}** berhasil diperbarui. Status: **{$status_baru}**.";
        } else {
            $is_error = true;
            $message = "❌ Gagal memperbarui data: " . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
    } else {
        $is_error = true;
        $message = "❌ Gagal menyiapkan query update: " . mysqli_error($conn);
    }
    // Setelah update, kita akan ambil ulang data di bawah
}

// Pastikan kolom 'status_verifikasi' ada di tabel ujikom. Jika belum, tambahkan:
// ALTER TABLE `nama_tabel_ujikom` ADD COLUMN `status_verifikasi` VARCHAR(50) DEFAULT 'Menunggu Verifikasi';

// --- LOGIKA AMBIL DATA DETAIL ---
$data_peserta = [];
if ($id_peserta) {
    $sql_detail = "SELECT * FROM {$NAMA_TABEL_UJIKOM} WHERE id = ?";
    $stmt_detail = mysqli_prepare($conn, $sql_detail);

    if ($stmt_detail) {
        mysqli_stmt_bind_param($stmt_detail, "i", $id_peserta);
        mysqli_stmt_execute($stmt_detail);
        $result = mysqli_stmt_get_result($stmt_detail);
        
        if ($row = mysqli_fetch_assoc($result)) {
            $data_peserta = $row;
            $data_ditemukan = true;
            
            // Inisiasi status jika kolom tidak ada di database (untuk mencegah error)
            if (!isset($data_peserta['status_verifikasi'])) {
                 $data_peserta['status_verifikasi'] = 'Menunggu Verifikasi (Kolom DB Hilang)';
            }
        } else {
            $is_error = true;
            $message = "❌ Data peserta dengan ID {$id_peserta} tidak ditemukan.";
        }
        mysqli_stmt_close($stmt_detail);
    }
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Detail Peserta Uji Kompetensi #<?php echo $id_peserta ?? 'N/A'; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <style>
        /* CSS DARI HALAMAN SEBELUMNYA */
        :root {
          --primary: #0f62fe;
          --secondary: #111827;
          --accent1: #f59e0b; 
          --accent3: #3b82f6; 
          --accent4: #10b981; 
          --bg: #f4f6f9; 
          --text: #1f2937;
          --muted: #6b7280;
        }
        * { box-sizing: border-box; }
        body { font-family: 'Inter', system-ui, sans-serif; margin: 0; display: flex; background: var(--bg); color: var(--text); }
        .sidebar { width: 260px; background: var(--secondary); color: #fff; display: flex; flex-direction: column; min-height: 100vh; padding: 20px 16px; position: sticky; top: 0; left: 0; }
        .brand { display: flex; align-items: center; gap: 10px; margin-bottom: 32px; }
        .brand .logo-pupr { width: 44px; height: 44px; background: #fff; border-radius: 10px; padding: 4px; display: flex; align-items: center; justify-content: center; }
        .brand .logo-pupr i { color: var(--primary); font-size: 20px; }
        .brand h1 { font-size: 18px; margin: 0; line-height: 1.2; }
        .menu { display: flex; flex-direction: column; gap: 12px; }
        .menu a { color: #e5e7eb; text-decoration: none; padding: 10px 12px; border-radius: 8px; transition: background 0.3s; display: flex; align-items: center; gap: 10px; }
        .menu a:hover, .menu a.active { background: rgba(255, 255, 255, 0.1); color: #fff; }
        .profile { margin-top: auto; display: flex; align-items: center; gap: 12px; border-top: 1px solid rgba(255, 255, 255, 0.1); padding-top: 16px; }
        .profile img { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; }
        .profile div { font-size: 14px; }
        main { flex: 1; padding: 24px; display: flex; flex-direction: column; }
        header { display: flex; justify-content: space-between; align-items: center; background: #fff; padding: 12px 20px; border-radius: 12px; box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05); margin-bottom: 24px; }
        .container { background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05); margin-bottom: 24px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; font-size: 14px; color: var(--text); }
        .form-control { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 6px; box-sizing: border-box; font-size: 14px; }
        .btn-submit { background: var(--primary); color: #fff; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; transition: background 0.3s; }
        .btn-submit:hover { background: #0c53d1; }
        
        /* Message Box */
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        .status { font-weight: 600; padding: 5px 10px; border-radius: 4px; display: inline-block; }
        .status.blue { color: var(--primary); background: #e8f1ff; }
        .status.orange { color: var(--accent1); background: #fff7ed; }
        .status.green { color: var(--accent4); background: #e6ffed; }
        footer { text-align: center; color: var(--muted); font-size: 13px; margin-top: auto; padding: 16px 0; }
    </style>
</head>
<body>
    <aside class="sidebar">
        <div class="brand">
            <div class="logo-pupr"><i class="fas fa-building fa-lg"></i></div>
            <h1>Instansi Pembina<br>Jabatan Fungsional</h1>
        </div>
        <nav class="menu">
            <a href="index.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a>
            <a href="database.php"><i class="fas fa-database"></i> <span>Database</span></a>
            <a href="ujikom.php" class="active"><i class="fas fa-clipboard-list"></i> <span>Uji Kompetensi</span></a>
            <a href="rekomendasi.php"><i class="fas fa-chart-line"></i> <span>Rekomendasi Formasi</span></a>
            <a href="peraturan.php"><i class="fas fa-book"></i> <span>Peraturan</span></a>
            <a href="pengaturan.php"><i class="fas fa-cog"></i> <span>Pengaturan</span></a>
        </nav>
        <div class="profile">
            <img src="https://i.pravatar.cc/36" alt="User" />
            <div>Henry Klein<br><span style="font-size:12px;color:#9ca3af;">Admin</span></div>
        </div>
    </aside>

    <main>
        <header>
            <div><h2><i class="fas fa-user-check"></i> Detail Peserta Uji Kompetensi NIP: <?php echo htmlspecialchars($data_peserta['nip'] ?? 'N/A'); ?></h2></div>
            <div>
                <a href="ujikom.php" style="padding:8px 12px;border-radius:8px;border:1px solid #ccc;background:#fff;color:var(--text);text-decoration:none;"><i class="fas fa-arrow-left"></i> Kembali ke Daftar</a>
            </div>
        </header>

        <?php if ($message): ?>
            <div class="alert <?php echo $is_error ? 'alert-danger' : 'alert-success'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if ($data_ditemukan): ?>
            <div class="container">
                <h3>Informasi Peserta</h3>
                <form method="POST" action="detail_ujikom.php?id=<?php echo $id_peserta; ?>">
                    
                    <div class="form-group">
                        <label>Status Verifikasi Saat Ini</label>
                        <p><span class="status <?php echo get_status_class_ujikom($data_peserta['status_verifikasi']); ?>"><?php echo htmlspecialchars($data_peserta['status_verifikasi']); ?></span></p>
                    </div>

                    <div class="form-group">
                        <label for="nip">NIP (Nomor Induk Pegawai) *</label>
                        <input type="text" id="nip" name="nip" class="form-control" required maxlength="18" value="<?php echo htmlspecialchars($data_peserta['nip']); ?>">
                    </div>

                    <div class="form-group">
                        <label for="nama_lengkap_gelar">Nama Lengkap dan Gelar *</label>
                        <input type="text" id="nama_lengkap_gelar" name="nama_lengkap_gelar" class="form-control" required value="<?php echo htmlspecialchars($data_peserta['nama_lengkap_gelar']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="no_wa">Nomor Handphone (WA)</label>
                        <input type="text" id="no_wa" name="no_wa" class="form-control" value="<?php echo htmlspecialchars($data_peserta['no_wa']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="instansi">Instansi *</label>
                        <input type="text" id="instansi" name="instansi" class="form-control" required value="<?php echo htmlspecialchars($data_peserta['instansi']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="unit_organisasi">Nama Unit Organisasi</label>
                        <input type="text" id="unit_organisasi" name="unit_organisasi" class="form-control" value="<?php echo htmlspecialchars($data_peserta['unit_organisasi']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="unit_kerja">Nama Unit Kerja</label>
                        <input type="text" id="unit_kerja" name="unit_kerja" class="form-control" value="<?php echo htmlspecialchars($data_peserta['unit_kerja']); ?>">
                    </div>
                    
                    <h4 style="margin-top: 30px;">Update Status Verifikasi</h4>
                    <div class="form-group">
                        <label for="status_verifikasi">Ubah Status Verifikasi</label>
                        <select id="status_verifikasi" name="status_verifikasi" class="form-control">
                            <option value="Menunggu Verifikasi" <?php if ($data_peserta['status_verifikasi'] == 'Menunggu Verifikasi') echo 'selected'; ?>>Menunggu Verifikasi</option>
                            <option value="Perlu Perbaikan" <?php if ($data_peserta['status_verifikasi'] == 'Perlu Perbaikan') echo 'selected'; ?>>Perlu Perbaikan</option>
                            <option value="Terverifikasi" <?php if ($data_peserta['status_verifikasi'] == 'Terverifikasi') echo 'selected'; ?>>Terverifikasi</option>
                        </select>
                    </div>
                    
                    <button type="submit" name="update_peserta" class="btn-submit"><i class="fas fa-sync-alt"></i> Simpan Perubahan & Verifikasi</button>
                </form>
            </div>
        <?php else: ?>
            <div class="container alert alert-danger">
                <h3>Data Tidak Ditemukan</h3>
                <p>Peserta dengan ID tersebut tidak ada dalam sistem.</p>
            </div>
        <?php endif; ?>

        <footer>
          © 2025 Instansi Pembina Jabatan Fungsional Penata Kelola Perumahan — Semua Hak Dilindungi.
        </footer>
    </main>
<?php
// Tutup koneksi database
if (isset($conn) && $conn) {
    mysqli_close($conn);
}
?>
</body>
</html>