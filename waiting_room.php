<?php
session_start();

// Cek apakah user sudah login. Jika belum, lempar ke login.php
if (!isset($_SESSION['user_id_sesi'])) {
    header("Location: login.php");
    exit;
}

// Tambahkan pengecekan otomatis ke database (Opsional tapi sangat disarankan)
// Agar jika Admin sudah mengubah role, user tidak perlu logout-login lagi.
require_once 'koneksi.php';
$user_id = $_SESSION['user_id_sesi'];
$query = mysqli_query($conn, "SELECT role FROM users WHERE id = '$user_id'");
$data = mysqli_fetch_assoc($query);

if (!empty($data['role']) && $data['role'] !== 'Guest' && $data['role'] !== '-') {
    $_SESSION['user_role_sesi'] = $data['role'];
    header("Location: login.php"); // Biarkan login.php yang mengarahkan ke dashboard yang sesuai
    exit;
}

$nama_user = $_SESSION['user_nama_sesi'] ?? 'Pengguna';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menunggu Verifikasi | E-Ujikom</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    
    <style>
        body {
            background: linear-gradient(135deg, #e0f2fe 0%, #f8fafc 100%);
            font-family: 'Source Sans Pro', sans-serif;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            overflow: hidden;
        }
        
        .waiting-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 50px 40px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.08);
            max-width: 550px;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.3);
            position: relative;
        }

        /* Lingkaran Dekorasi Background */
        .waiting-card::before {
            content: '';
            position: absolute;
            top: -20px;
            right: -20px;
            width: 80px;
            height: 80px;
            background: #3b82f6;
            opacity: 0.1;
            border-radius: 50%;
            z-index: -1;
        }

        .icon-wrapper {
            width: 100px;
            height: 100px;
            background: #dbeafe;
            color: #2563eb;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 30px;
            margin: 0 auto 30px;
            font-size: 45px;
            transform: rotate(-10deg);
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0) rotate(-10deg); }
            50% { transform: translateY(-10px) rotate(-5deg); }
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            background: #fef3c7;
            color: #92400e;
            padding: 8px 20px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.85rem;
            margin-bottom: 25px;
            letter-spacing: 0.5px;
        }

        h2 { color: #0f172a; font-weight: 800; margin-bottom: 15px; letter-spacing: -0.5px; }
        p { color: #475569; font-size: 1.05rem; line-height: 1.7; }

        /* Step Progress Visual */
        .step-container {
            display: flex;
            justify-content: space-between;
            margin: 30px 0;
            position: relative;
        }
        .step-item { flex: 1; text-align: center; z-index: 1; }
        .step-dot { width: 12px; height: 12px; border-radius: 50%; background: #cbd5e1; margin: 0 auto 8px; }
        .step-dot.active { background: #3b82f6; box-shadow: 0 0 0 4px #dbeafe; }
        .step-text { font-size: 11px; color: #94a3b8; text-transform: uppercase; font-weight: bold; }
        .step-text.active { color: #3b82f6; }
        .step-line { position: absolute; top: 5px; left: 15%; right: 15%; height: 2px; background: #e2e8f0; z-index: 0; }

        .contact-box {
            background: #f1f5f9;
            border-radius: 16px;
            padding: 18px;
            margin-top: 25px;
            border: 1px solid #e2e8f0;
        }

        .btn-refresh {
            background: #2563eb;
            color: white;
            border-radius: 12px;
            padding: 12px;
            font-weight: 700;
            transition: all 0.3s;
            border: none;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
        }
        .btn-refresh:hover {
            background: #1d4ed8;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(37, 99, 235, 0.3);
            color: white;
        }

        .btn-logout {
            margin-top: 25px;
            color: #64748b;
            font-size: 0.9rem;
            text-decoration: none;
            display: inline-block;
            transition: 0.3s;
        }
        .btn-logout:hover { color: #ef4444; }
    </style>
</head>
<body>

<div class="container">
    <div class="waiting-card mx-auto">
        <div class="icon-wrapper">
            <i class="fas fa-user-shield"></i>
        </div>
        
        <div class="status-badge">
            <i class="fas fa-hourglass-half fa-spin mr-2"></i> AKUN SEDANG DITINJAU
        </div>
        
        <h2>Selamat Datang, <?= htmlspecialchars($nama_user); ?>!</h2>
        <p>
            Akun Anda berhasil dibuat. Tim Admin kami sedang melakukan verifikasi data untuk menetapkan <strong>Role Akses</strong> yang sesuai untuk Anda.
        </p>
        <div class="contact-box">
            <p class="mb-2" style="font-size: 0.9rem; font-weight: 500;">Perlu bantuan atau akses mendesak?</p>
            <a href="https://wa.me/6282169441633" target="_blank" class="btn btn-success btn-sm px-4 shadow-sm" style="border-radius: 8px;">
                <i class="fab fa-whatsapp mr-1"></i> Chat Helpdesk
            </a>
        </div>

        <div class="mt-4">
            <a href="waiting_room.php" class="btn btn-refresh btn-block">
                <i class="fas fa-sync-alt mr-2"></i> Periksa Status Terbaru
            </a>
            <a href="logout.php" class="btn-logout">
                <i class="fas fa-power-off mr-1"></i> Keluar dari Sesi
            </a>
        </div>
        
        <p class="mt-4 small text-muted">
            <i class="fas fa-info-circle mr-1"></i> Halaman ini akan diperbarui otomatis setelah Admin memberikan akses.
        </p>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // Fitur Auto Refresh setiap 30 detik untuk kenyamanan user
    setTimeout(function(){
       location.reload();
    }, 30000);
</script>

</body>
</html>