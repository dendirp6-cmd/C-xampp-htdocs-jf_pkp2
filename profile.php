<?php
/**
 * ==================================================================================
 * FILE: profile.php
 * DESKRIPSI: Halaman Profil User (Update Foto & Informasi) - Modern Identity PKP
 * FITUR TAMBAHAN: Auto-delete file lama untuk optimasi penyimpanan
 * ==================================================================================
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'auth_guard.php'; 
require_once 'koneksi.php';    

if (!isset($conn) || !$conn) {
    die("Fatal Error: Koneksi database tidak tersedia.");
}

$session_user_id = $_SESSION['user_id_sesi'] ?? 0;
$message = "";
$message_type = "";

// 1. PROSES UPDATE FOTO
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['foto_profil'])) {
    $target_dir = "assets/profile/"; 
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    $file_extension = strtolower(pathinfo($_FILES["foto_profil"]["name"], PATHINFO_EXTENSION));
    $new_filename = "user_" . $session_user_id . "_" . time() . "." . $file_extension;
    $target_file = $target_dir . $new_filename;
    $uploadOk = 1;

    // Validasi Gambar
    $check = getimagesize($_FILES["foto_profil"]["tmp_name"]);
    if($check === false) {
        $message = "File bukan gambar."; $uploadOk = 0;
        $message_type = "danger";
    }

    if ($uploadOk == 1) {
        // --- LOGIKA HAPUS FOTO LAMA ---
        // Ambil nama file lama dari database sebelum diupdate
        $sql_old = "SELECT foto FROM users WHERE id = ?";
        $stmt_old = $conn->prepare($sql_old);
        $stmt_old->bind_param("i", $session_user_id);
        $stmt_old->execute();
        $res_old = $stmt_old->get_result();
        if ($old_data = $res_old->fetch_assoc()) {
            $old_file = $old_data['foto'];
            $full_path_old = $target_dir . $old_file;
            
            // Hapus jika file ada dan bukan folder
            if (!empty($old_file) && file_exists($full_path_old) && is_file($full_path_old)) {
                unlink($full_path_old);
            }
        }
        // --- END LOGIKA HAPUS ---

        if (move_uploaded_file($_FILES["foto_profil"]["tmp_name"], $target_file)) {
            $sql_update = "UPDATE users SET foto = ? WHERE id = ?";
            $stmt = $conn->prepare($sql_update);
            $stmt->bind_param("si", $new_filename, $session_user_id);
            
            if ($stmt->execute()) {
                $_SESSION['foto_user_sesi'] = $new_filename;
                $message = "Foto profil berhasil diperbarui!";
                $message_type = "success";
            } else {
                $message = "Gagal memperbarui database.";
                $message_type = "danger";
            }
        } else {
            $message = "Terjadi kesalahan saat upload file.";
            $message_type = "danger";
        }
    }
}

// 2. AMBIL DATA USER TERBARU
$user_nama = ""; $user_nip = ""; $user_role = ""; $raw_foto = "";

$sql_user = "SELECT nama, nip_user, role, foto FROM users WHERE id = ?"; 
if ($stmt_user = $conn->prepare($sql_user)) {
    $stmt_user->bind_param("i", $session_user_id);
    $stmt_user->execute();
    $res_user = $stmt_user->get_result();
    if ($user_data = $res_user->fetch_assoc()) {
        $user_nama = $user_data['nama'];
        $user_nip  = $user_data['nip_user'];
        $user_role = $user_data['role'];
        $raw_foto = $user_data['foto'];
    }
}

// Logika Fallback Foto
$path_foto = "assets/profile/" . $raw_foto;
if (!empty($raw_foto) && file_exists(__DIR__ . "/" . $path_foto)) {
    $url_foto_final = $path_foto . "?t=" . time();
} else {
    $url_foto_final = "https://ui-avatars.com/api/?name=" . urlencode($user_nama) . "&background=0f172a&color=fff&size=256";
}

$page_title = "Profil Akun";
require_once 'template/header.php'; 
require_once 'template/navbar.php'; 
require_once 'template/sidebar.php'; 
?>

<style>
    :root { 
        --primary-dark: #0f172a; 
        --accent-color: #3b82f6;
        --bg-soft: #f1f5f9;
    }
    
    .content-wrapper { background-color: var(--bg-soft); }
    
    .modern-profile-card {
        border: none;
        border-radius: 24px;
        background: #ffffff;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.05), 0 10px 10px -5px rgba(0, 0, 0, 0.02);
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .card-banner {
        height: 140px;
        background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
        position: relative;
    }

    .card-banner::after {
        content: "";
        position: absolute;
        width: 100%;
        height: 100%;
        top: 0; left: 0;
        background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%239C92AC' fill-opacity='0.12'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2v-4h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2v-4h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
    }

    .avatar-section {
        margin-top: -70px;
        position: relative;
        z-index: 5;
    }

    .profile-main-img {
        width: 140px;
        height: 140px;
        object-fit: cover;
        border-radius: 30px;
        border: 6px solid #ffffff;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        background: #fff;
    }

    .upload-badge {
        position: absolute;
        bottom: 5px;
        right: calc(50% - 75px);
        background: var(--accent-color);
        color: white;
        width: 40px;
        height: 40px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        border: 4px solid #fff;
        transition: 0.2s;
    }
    .upload-badge:hover { transform: scale(1.1); background: #2563eb; }

    .info-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 16px;
        padding: 20px;
    }

    .info-tile {
        padding: 16px;
        background: #f8fafc;
        border-radius: 16px;
        border: 1px solid #f1f5f9;
        display: flex;
        align-items: center;
        transition: 0.3s;
    }
    .info-tile:hover { background: #fff; border-color: var(--accent-color); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }

    .tile-icon {
        width: 45px;
        height: 45px;
        background: #fff;
        color: var(--accent-color);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        margin-right: 16px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }

    .tile-label {
        font-size: 0.75rem;
        font-weight: 700;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin: 0;
    }

    .tile-value {
        font-size: 1rem;
        font-weight: 600;
        color: var(--primary-dark);
        margin: 0;
    }

    .role-tag {
        background: #eff6ff;
        color: #1d4ed8;
        padding: 4px 16px;
        border-radius: 999px;
        font-size: 0.85rem;
        font-weight: 700;
    }

    #preview-container { display: none; margin-top: 15px; }
    
    .btn-save-modern {
        background: var(--primary-dark);
        color: white;
        border-radius: 12px;
        padding: 8px 24px;
        font-weight: 600;
        border: none;
    }
</style>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-3">
                <div class="col-sm-6">
                    <h2 class="font-weight-bold" style="color: var(--primary-dark); letter-spacing: -0.02em;">Data Personel</h2>
                </div>
            </div>
        </div>
    </section>

    <section class="content pb-5">
        <div class="container-fluid">
            <div class="row justify-content-center">
                <div class="col-xl-4 col-lg-6 col-md-8">
                    
                    <?php if($message): ?>
                        <div class="alert alert-<?= $message_type; ?> border-0 shadow-sm animate__animated animate__fadeInDown" style="border-radius: 16px;">
                            <div class="d-flex align-items: center;">
                                <i class="fas <?= $message_type == 'success' ? 'fa-check-circle' : 'fa-info-circle'; ?> mr-3 fa-lg"></i>
                                <div><b>Sistem:</b> <?= $message; ?></div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="card modern-profile-card">
                        <div class="card-banner"></div>
                        
                        <div class="card-body p-0 text-center">
                            <div class="avatar-section">
                                <form action="" method="POST" enctype="multipart/form-data" id="formFoto">
                                    <div class="position-relative d-inline-block">
                                        <img src="<?= $url_foto_final; ?>" id="profile-display" class="profile-main-img" alt="Foto Profil">
                                        <label for="foto_profil" class="upload-badge shadow">
                                            <i class="fas fa-camera-retro"></i>
                                        </label>
                                        <input type="file" name="foto_profil" id="foto_profil" style="display:none;" accept="image/*">
                                    </div>

                                    <div id="preview-container" class="animate__animated animate__fadeInUp">
                                        <button type="submit" class="btn btn-save-modern shadow-sm">
                                            <i class="fas fa-cloud-upload-alt mr-2"></i>Terapkan Foto
                                        </button>
                                        <button type="button" onclick="location.reload();" class="btn btn-link btn-sm text-muted">Batal</button>
                                    </div>
                                </form>
                            </div>

                            <div class="mt-3 px-4">
                                <h3 class="font-weight-bold mb-1" style="color: var(--primary-dark);"><?= htmlspecialchars($user_nama); ?></h3>
                                <div class="d-flex justify-content-center align-items-center mb-4">
                                    <span class="role-tag"><i class="fas fa-shield-alt mr-1"></i> <?= strtoupper($user_role); ?></span>
                                </div>
                            </div>

                            <div class="info-grid mt-2">
                                <div class="info-tile">
                                    <div class="tile-icon"><i class="fas fa-fingerprint"></i></div>
                                    <div class="text-left">
                                        <p class="tile-label">Nomor Induk Pegawai</p>
                                        <p class="tile-value"><?= htmlspecialchars($user_nip); ?></p>
                                    </div>
                                </div>

                                <div class="info-tile">
                                    <div class="tile-icon"><i class="fas fa-hotel"></i></div>
                                    <div class="text-left">
                                        <p class="tile-label">Instansi Satker</p>
                                        <p class="tile-value"><?= $_SESSION['user_instansi_sesi'] ?? 'Kementerian PKP'; ?></p>
                                    </div>
                                </div>

                                <div class="info-tile">
                                    <div class="tile-icon"><i class="fas fa-user-check"></i></div>
                                    <div class="text-left">
                                        <p class="tile-label">Status Akun</p>
                                        <p class="tile-value text-success">Aktif / Terverifikasi</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card-footer bg-white border-0 py-4 text-center">
                            <p class="text-muted small mb-0">
                                <i class="fas fa-lock mr-1"></i> Data profil dienkripsi oleh sistem kementerian.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<?php require_once 'template/footer.php'; ?>

<script>
$(document).ready(function() {
    $("#foto_profil").change(function() {
        if (this.files && this.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                $('#profile-display').attr('src', e.target.result);
                $('#preview-container').fadeIn().css('display', 'block');
            }
            reader.readAsDataURL(this.files[0]);
        }
    });
});
</script>