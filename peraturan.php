<?php
// FILE: peraturan.php - Halaman Daftar Peraturan (AdminLTE)

// =========================================================
// 1. PENGATURAN AWAL: SESSION & AUTORISASI
// =========================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// -------------------------------------------------------------
// --- PENTING: DEKLARASI VARIABEL UNTUK HIGHLIGHT MENU SIDEBAR ---
$page = 'peraturan';
$sub_page = '';
$page_title = 'Peraturan Jabatan Fungsional';
// -------------------------------------------------------------

require_once 'auth_guard.php';
require_once 'koneksi.php';

// --- Cek Koneksi ---
if (!isset($conn) || !$conn) {
    die("Fatal Error: Koneksi database tidak tersedia. Mohon cek koneksi.php.");
}

// --- DEKLARASI VARIABEL ---
$message = '';
$is_error = false;
$NAMA_TABEL_PERATURAN = "peraturan_jf_pkp";
$NAMA_TABEL_PEGAWAI = "pegawai";

// Variabel sesi pengguna
$user_nip_sesi   = $_SESSION['user_nip_sesi'] ?? '';
$user_nama_sesi  = $_SESSION['user_nama_sesi'] ?? 'Pengguna JF';
$user_role_sesi  = $_SESSION['user_role_sesi'] ?? 'User';
$user_email_sesi = $_SESSION['user_email_sesi'] ?? 'user@instansi.go.id';

// Data default user
$user_data = [
    'nama'        => $user_nama_sesi,
    'role'        => $user_role_sesi,
    'email'       => $user_email_sesi,
    'join_date'   => $_SESSION['join_date'] ?? 'Maret 2023',
    'nip'         => $user_nip_sesi,
    'foto_profil' => 'default.png'
];

// =========================================================
// FETCH DATA PROFIL USER
// =========================================================
if (!empty($user_nip_sesi)) {
    try {
        $sql_user = "
            SELECT nama, role, email, foto_profil
            FROM {$NAMA_TABEL_PEGAWAI}
            WHERE nip = ?
            LIMIT 1
        ";

        if ($stmt_user = $conn->prepare($sql_user)) {
            $stmt_user->bind_param("s", $user_nip_sesi);
            $stmt_user->execute();
            $result_user = $stmt_user->get_result();

            if ($row_user = $result_user->fetch_assoc()) {
                $user_data['nama']        = htmlspecialchars($row_user['nama']);
                $user_data['role']        = htmlspecialchars($row_user['role']);
                $user_data['email']       = htmlspecialchars($row_user['email']);
                $user_data['foto_profil'] = htmlspecialchars($row_user['foto_profil'] ?: 'default.png');
            }
            $stmt_user->close();
        }
    } catch (Exception $e) {
        // Abaikan error DB
    }
}

// =========================================================
// LOGIKA FORM UPLOAD PERATURAN
// =========================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_peraturan'])) {

    if ($user_data['role'] === 'Admin' || $user_data['role'] === 'Pimpinan') {

        $judul_peraturan  = trim(mysqli_real_escape_string($conn, $_POST['judul_peraturan'] ?? ''));
        $nomor_peraturan  = trim(mysqli_real_escape_string($conn, $_POST['nomor_peraturan'] ?? ''));
        $tahun            = trim(mysqli_real_escape_string($conn, $_POST['tahun'] ?? ''));
        $link_dokumen     = trim(mysqli_real_escape_string($conn, $_POST['link_dokumen'] ?? ''));
        $tanggal_input    = date('Y-m-d H:i:s');

        if (empty($judul_peraturan) || empty($nomor_peraturan) || empty($tahun) || empty($link_dokumen)) {
            $is_error = true;
            $message = "Semua kolom wajib harus diisi.";
        } else {
            $sql_insert = "
                INSERT INTO {$NAMA_TABEL_PERATURAN} 
                (judul, nomor_peraturan, tahun, link_dokumen, tanggal_input)
                VALUES (?, ?, ?, ?, ?)
            ";

            if ($stmt = $conn->prepare($sql_insert)) {
                $stmt->bind_param(
                    "sssss",
                    $judul_peraturan,
                    $nomor_peraturan,
                    $tahun,
                    $link_dokumen,
                    $tanggal_input
                );

                if ($stmt->execute()) {
                    $is_error = false;
                    $message = "Peraturan <strong>{$nomor_peraturan}</strong> berhasil ditambahkan.";
                    unset($_POST);
                } else {
                    $is_error = true;
                    $message = "Gagal menyimpan data: " . mysqli_error($conn);
                }

                $stmt->close();
            } else {
                $is_error = true;
                $message = "Gagal menyiapkan query: " . mysqli_error($conn);
            }
        }

    } else {
        $is_error = true;
        $message = "Anda tidak memiliki hak akses untuk menambahkan peraturan.";
    }
}

// =========================================================
// AMBIL DATA PERATURAN
// =========================================================
$data_peraturan_gagal = false;
$error_database = '';
$res_data_peraturan = false;

try {
    $sql_select = "
        SELECT id, judul, nomor_peraturan, tahun, link_dokumen
        FROM {$NAMA_TABEL_PERATURAN}
        ORDER BY tahun DESC, nomor_peraturan DESC
    ";

    $res_data_peraturan = mysqli_query($conn, $sql_select);
    if ($res_data_peraturan === false) {
        throw new Exception();
    }
} catch (Exception $e) {
    $data_peraturan_gagal = true;
    $error_database = mysqli_error($conn);
}

// =========================================================
// TEMPLATE HEADER / NAVBAR / SIDEBAR
// =========================================================
require_once 'template/header.php';
require_once 'template/navbar.php';
require_once 'template/sidebar.php';
?>

<div class="content-wrapper">

    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0"><i class="fas fa-book"></i> <?= $page_title; ?></h1>
                </div>
                <div class="col-sm-6 text-right">
                    <ol class="breadcrumb float-sm-right" style="background-color:transparent;">
                        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                        <li class="breadcrumb-item active">Peraturan</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">

            <?php if ($message): ?>
            <div class="alert <?= $is_error ? 'alert-danger' : 'alert-success'; ?> alert-dismissible fade show">
                <?= $message; ?>
                <button type="button" class="close" data-dismiss="alert">
                    <span>&times;</span>
                </button>
            </div>
            <?php endif; ?>

            <?php if ($user_data['role'] === 'Admin' || $user_data['role'] === 'Pimpinan'): ?>
            <div class="card card-info collapsed-card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-upload"></i> Tambah Peraturan Baru</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-tool" data-card-widget="collapse">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                </div>

                <form method="POST" action="peraturan.php">
                    <div class="card-body">

                        <div class="form-group">
                            <label>Judul Peraturan</label>
                            <input type="text"
                                name="judul_peraturan"
                                class="form-control"
                                required
                                value="<?= htmlspecialchars($_POST['judul_peraturan'] ?? '') ?>">
                        </div>

                        <div class="row">
                            <div class="col-md-8">
                                <div class="form-group">
                                    <label>Nomor Peraturan</label>
                                    <input type="text"
                                        name="nomor_peraturan"
                                        class="form-control"
                                        required
                                        value="<?= htmlspecialchars($_POST['nomor_peraturan'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Tahun</label>
                                    <input type="number"
                                        name="tahun"
                                        class="form-control"
                                        required
                                        value="<?= htmlspecialchars($_POST['tahun'] ?? date('Y')) ?>">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Link Dokumen</label>
                            <input type="url"
                                name="link_dokumen"
                                class="form-control"
                                required
                                value="<?= htmlspecialchars($_POST['link_dokumen'] ?? '') ?>">
                        </div>

                    </div>

                    <div class="card-footer">
                        <button type="submit" name="submit_peraturan" class="btn btn-info">
                            <i class="fas fa-save"></i> Simpan Peraturan
                        </button>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <div class="card card-primary">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-list-alt"></i> Daftar Peraturan</h3>
                </div>

                <div class="card-body p-0">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th width="5%">No</th>
                                <th width="20%">Nomor & Tahun</th>
                                <th width="65%">Judul Peraturan</th>
                                <th width="10%">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>

                            <?php
                            if ($data_peraturan_gagal) {
                                echo "<tr><td colspan='4' class='text-center text-danger font-weight-bold'>
                                        Gagal memuat data: " . htmlspecialchars($error_database) . "
                                      </td></tr>";

                            } elseif ($res_data_peraturan && mysqli_num_rows($res_data_peraturan) > 0) {

                                $no = 1;
                                while ($row = mysqli_fetch_assoc($res_data_peraturan)) {
                                    echo "<tr>
                                            <td>{$no}</td>
                                            <td>" . htmlspecialchars($row['nomor_peraturan']) . " Tahun " . htmlspecialchars($row['tahun']) . "</td>
                                            <td>" . htmlspecialchars($row['judul']) . "</td>
                                            <td>
                                                <a href='" . htmlspecialchars($row['link_dokumen']) . "' target='_blank'
                                                    class='btn btn-sm btn-outline-info'>
                                                    <i class='fas fa-external-link-alt'></i> Lihat
                                                </a>
                                            </td>
                                          </tr>";
                                    $no++;
                                }

                                mysqli_free_result($res_data_peraturan);

                            } else {
                                echo "<tr><td colspan='4' class='text-center text-muted'>
                                        Belum ada peraturan yang terdaftar.
                                      </td></tr>";
                            }
                            ?>

                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </section>

</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/js/adminlte.min.js"></script>
<?php
require_once 'template/footer.php';
?>
