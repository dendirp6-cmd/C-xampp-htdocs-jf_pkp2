<?php
// FILE: aksi_user.php - Menangani aksi CRUD untuk pengguna (Admin Only)

// --- AKTIFKAN PELAPORAN ERROR ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- AUTH GUARD DAN KONEKSI ---
require_once 'auth_guard.php'; // Memuat $user_role_sesi dll.
require_once 'koneksi.php'; // Memuat $conn

// =========================================================================
// PERBAIKAN DI SINI: Sesuaikan pengecekan role dengan 'user_super_admin'
// =========================================================================
if (($user_role_sesi ?? '') !== 'user_super_admin' && ($user_role_sesi ?? '') !== 'admin') {
    die("Akses ditolak. Anda tidak memiliki izin untuk melakukan aksi ini. Role Anda saat ini: " . htmlspecialchars($user_role_sesi));
}

if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $user_id = (int)$_GET['id'];
    $redirect_to = 'pengaturan.php';
    $message = '';
    $is_error = false;

    // Periksa apakah ID valid
    if ($user_id <= 0) {
        $message = "❌ Error: ID Pengguna tidak valid.";
        $is_error = true;
    } else {
        // Hindari admin menghapus dirinya sendiri
        if ($user_id === ($user_id_sesi ?? 0) && $action === 'delete') {
             $message = "❌ Error: Anda tidak dapat menghapus akun Anda sendiri.";
             $is_error = true;
        } else {
            $stmt = null;
            $new_status = '';

            try {
                switch ($action) {
                    case 'approve':
                        $new_status = 'active';
                        $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
                        $stmt->bind_param("si", $new_status, $user_id);
                        $message = "✅ Pengguna ID {$user_id} berhasil disetujui!";
                        break;

                    case 'reject':
                        $new_status = 'rejected';
                        $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
                        $stmt->bind_param("si", $new_status, $user_id);
                        $message = "✅ Pengguna ID {$user_id} berhasil ditolak.";
                        break;
                        
                    case 'delete':
                        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                        $stmt->bind_param("i", $user_id);
                        $message = "✅ Pengguna ID {$user_id} berhasil dihapus.";
                        break;
                        
                    default:
                        $message = "❌ Aksi tidak dikenali.";
                        $is_error = true;
                        break;
                }

                if ($stmt && !$is_error) {
                    if ($stmt->execute()) {
                        if ($stmt->affected_rows === 0) {
                            $message = "⚠️ Peringatan: Tidak ada perubahan data.";
                        }
                    } else {
                        throw new Exception("Error: " . $stmt->error);
                    }
                    $stmt->close();
                }

            } catch (Exception $e) {
                $message = "❌ Error Database: " . $e->getMessage();
                $is_error = true;
                if ($stmt) $stmt->close();
            }
        }
    }

    // Redirect kembali ke pengaturan.php
    $status_param = $is_error ? 'error' : 'success';
    header("Location: {$redirect_to}?status={$status_param}&msg=" . urlencode($message));
    exit();

} else {
    header("Location: pengaturan.php?status=error&msg=" . urlencode("Akses tidak sah."));
    exit();
}
?>