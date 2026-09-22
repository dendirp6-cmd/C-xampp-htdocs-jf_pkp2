<?php
// FILE: verify.php - Menangani Verifikasi Token
// PASTIKAN TIDAK ADA SPASI ATAU BARIS KOSONG SEBELUM TAG INI

session_start();
// Aktifkan output buffering untuk memastikan header() dan ob_end_clean() bekerja optimal
ob_start();

require_once 'koneksi.php'; 

// --- PENTING: PASTIKAN NAMA TABEL USER DIDEFINISIKAN ---
if (!isset($NAMA_TABEL_USERS)) {
    // Sesuaikan nama tabel ini jika berbeda di database Anda
    $NAMA_TABEL_USERS = "users"; 
}

// --- LOGIKA MENDAPATKAN BASE URL APLIKASI SECARA DINAMIS ---
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$folder_path = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
if ($folder_path === '/' || $folder_path === '\\') {
    $folder_path = '';
}
$base_url = "{$protocol}://{$host}{$folder_path}/";
$redirect_url = $base_url . 'login.php';
// -------------------------------------------------------------

$error_message = null;
$token = null;

// --- MENGAMBIL DAN MEMBERSIHKAN TOKEN ---
// Prioritas 1: Cek parameter 'token' standar
if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = $_GET['token'];
} 
// Prioritas 2: Cek parameter 'q' jika link dibungkus oleh Google/Gmail
elseif (isset($_GET['q'])) {
    $wrapped_url = $_GET['q'];
    
    // DECODE DULU URL WRAPPER untuk memastikan semua encoding hilang
    $decoded_url = urldecode($wrapped_url);
    
    // Mencari token dari URL yang sudah di-decode (token=[nilai])
    if (preg_match('/token=([a-fA-F0-9]+)/', $decoded_url, $matches)) {
        $token = $matches[1]; // Ambil nilai token
    }
}
// -----------------------------------------

if ($token) {
    // 1. Cek token di database
    $stmt = mysqli_prepare($conn, "SELECT id, nama FROM {$NAMA_TABEL_USERS} WHERE verification_token = ? AND is_verified = 0");
    mysqli_stmt_bind_param($stmt, "s", $token);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);

    if (mysqli_stmt_num_rows($stmt) == 1) {
        // 2. Token ditemukan, lakukan update
        $stmt_update = mysqli_prepare($conn, "UPDATE {$NAMA_TABEL_USERS} SET is_verified = 1, verification_token = NULL WHERE verification_token = ?");
        mysqli_stmt_bind_param($stmt_update, "s", $token);
        
        if (mysqli_stmt_execute($stmt_update)) {
            // --- VERIFIKASI SUKSES ---
            $_SESSION['popup_type'] = 'success';
            $_SESSION['popup_message'] = "✅ Akun Anda berhasil diverifikasi! Silakan login untuk masuk ke sistem.";
            
            // Tutup semua sumber daya sebelum redirect
            if (isset($stmt_update)) mysqli_stmt_close($stmt_update);
            if (isset($stmt)) mysqli_stmt_close($stmt);
            if (isset($conn) && $conn) mysqli_close($conn);

            // Bersihkan buffer dan kirim header
            ob_end_clean();
            header('Location: ' . $redirect_url);
            exit; 
        } else {
            $error_message = "Gagal mengaktifkan akun. Silakan hubungi administrator.";
        }
        if (isset($stmt_update)) mysqli_stmt_close($stmt_update);
    } else {
        $error_message = "Tautan verifikasi tidak valid, sudah kedaluwarsa, atau akun Anda sudah aktif sebelumnya.";
    }
    
    if (isset($stmt)) mysqli_stmt_close($stmt);

} else {
    $error_message = "Token verifikasi tidak ditemukan.";
}
// Tutup koneksi database
if (isset($conn) && $conn) {
    mysqli_close($conn);
}


// --- PENGALIHAN ERROR MENGGUNAKAN META REFRESH (Paling Mulus) ---

// Jika kode mencapai bagian ini, berarti ada $error_message yang terisi
if ($error_message) {
    // Simpan pesan error di sesi
    $_SESSION['popup_type'] = 'error';
    $_SESSION['popup_message'] = "❌ Gagal Verifikasi. " . $error_message;

    // Bersihkan buffer (penting!) dan kirim Meta Refresh
    ob_end_clean();
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta http-equiv="refresh" content="0; url=<?php echo htmlspecialchars($redirect_url); ?>">
        <title>Mengalihkan...</title>
    </head>
    <body>
        <p>Verifikasi gagal. Anda akan dialihkan secara otomatis...</p>
        <script>
            // Skrip cadangan jika meta refresh diblokir
            window.location.href = "<?php echo htmlspecialchars($redirect_url); ?>";
        </script>
    </body>
    </html>
    <?php
    exit;
}

// Jika tidak ada token sama sekali, alihkan juga ke login
ob_end_clean();
header('Location: ' . $redirect_url);
exit;

?>