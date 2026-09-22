<?php
// update_data.php
require_once 'koneksi.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * FUNGSI: Mengecek kelengkapan profil user
 */
function checkUserProfile($conn, $user_id) {
    $data = [
        'needs_update' => false,
        'nama' => '', 'nip' => '', 'instansi' => '', 'email' => '', 'role' => ''
    ];

    $sql = "SELECT id, nama, nip_user, email, instansi, role FROM users WHERE id = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($user = $res->fetch_assoc()) {
            $data['nama']     = $user['nama'];
            $data['nip']      = (!empty($user['nip_user']) && $user['nip_user'] != '-') ? $user['nip_user'] : '-';
            $data['instansi'] = (!empty($user['instansi']) && $user['instansi'] != '-') ? $user['instansi'] : '-';
            $data['email']    = $user['email'];
            $data['role']     = $user['role'];

            // Jika NIP atau Instansi kosong/strip, tandai perlu update
            if ($data['nip'] == '-' || $data['instansi'] == '-') {
                $data['needs_update'] = true;
            }
        }
        $stmt->close();
    }
    return $data;
}

/**
 * HANDLER: Memproses Update Profile via AJAX
 */
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_profile') {
    header('Content-Type: application/json');
    
    $uid      = $_POST['id'];
    $nip      = $_POST['nip'];
    $instansi = $_POST['instansi'];

    $sql_update = "UPDATE users SET nip_user = ?, instansi = ? WHERE id = ?";
    $stmt = $conn->prepare($sql_update);
    $stmt->bind_param("ssi", $nip, $instansi, $uid);

    if ($stmt->execute()) {
        // Update session juga agar perubahan langsung terasa
        $_SESSION['nip'] = $nip;
        $_SESSION['instansi'] = $instansi;
        echo json_encode(['status' => 'success', 'message' => 'Profil berhasil diperbarui!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal memperbarui database.']);
    }
    $stmt->close();
    exit;
}