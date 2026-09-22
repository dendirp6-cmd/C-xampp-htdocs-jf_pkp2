<?php
// 1. Inisialisasi Session & Koneksi
session_start();
require_once 'koneksi.php';

// Proteksi akses: Hanya menerima POST atau GET khusus hapus gelombang
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !isset($_GET['delete_gelombang'])) {
    header("Location: admin_update.php");
    exit;
}

// Konfigurasi Path Folder Upload
$path_pengumuman = "uploads/pengumuman/";
if (!file_exists($path_pengumuman)) {
    mkdir($path_pengumuman, 0777, true);
}

// ==========================================
// A. LOGIKA KHUSUS HAPUS GELOMBANG (GET)
// ==========================================
if (isset($_GET['delete_gelombang'])) {
    $id_hapus = mysqli_real_escape_string($conn, $_GET['delete_gelombang']);
    
    // Ambil data file sebelum dihapus dari database
    $q_file = mysqli_query($conn, "SELECT surat_gelombang FROM tb_gelombang WHERE id = '$id_hapus'");
    $d_file = mysqli_fetch_assoc($q_file);
    
    if ($d_file) {
        // Hapus file fisik jika ada
        if (!empty($d_file['surat_gelombang']) && file_exists($path_pengumuman . $d_file['surat_gelombang'])) {
            unlink($path_pengumuman . $d_file['surat_gelombang']);
        }
        // Hapus data dari database
        mysqli_query($conn, "DELETE FROM tb_gelombang WHERE id = '$id_hapus'");
    }
    
    header("Location: admin_update.php?status=success");
    exit;
}

// ==========================================
// B. LOGIKA TAMBAH / EDIT GELOMBANG (MODAL)
// ==========================================
if (isset($_POST['aksi_gelombang'])) {
    $aksi = $_POST['aksi_gelombang'];
    $gelombang = mysqli_real_escape_string($conn, $_POST['gelombang']);
    $bulan = mysqli_real_escape_string($conn, $_POST['bln_gelombang']);
    $jenis_pengajuan = isset($_POST['jenis_pengajuan']) ? mysqli_real_escape_string($conn, $_POST['jenis_pengajuan']) : '';
    $id_gel = isset($_POST['gelombang_id']) ? mysqli_real_escape_string($conn, $_POST['gelombang_id']) : '';

    $new_file_name = "";
    if (!empty($_FILES['surat_gelombang']['name'])) {
        // Jika edit, hapus file lama terlebih dahulu
        if ($aksi == 'edit' && !empty($id_gel)) {
            $old = mysqli_fetch_assoc(mysqli_query($conn, "SELECT surat_gelombang FROM tb_gelombang WHERE id='$id_gel'"));
            if (!empty($old['surat_gelombang']) && file_exists($path_pengumuman . $old['surat_gelombang'])) {
                unlink($path_pengumuman . $old['surat_gelombang']);
            }
        }

        $ext = pathinfo($_FILES['surat_gelombang']['name'], PATHINFO_EXTENSION);
        $new_file_name = "surat_" . time() . "_" . rand(10,99) . "." . $ext;
        move_uploaded_file($_FILES['surat_gelombang']['tmp_name'], $path_pengumuman . $new_file_name);
    }

    if ($aksi == 'tambah') {
        mysqli_query($conn, "INSERT INTO tb_gelombang (gelombang, bln_gelombang, jenis_pengajuan, surat_gelombang) 
                            VALUES ('$gelombang', '$bulan', '$jenis_pengajuan', '$new_file_name')");
    } else if ($aksi == 'edit') {
        $sql_file_gel = (!empty($new_file_name)) ? ", surat_gelombang='$new_file_name'" : "";
        mysqli_query($conn, "UPDATE tb_gelombang SET gelombang='$gelombang', bln_gelombang='$bulan', jenis_pengajuan='$jenis_pengajuan' $sql_file_gel WHERE id='$id_gel'");
    }
    
    header("Location: admin_update.php?status=success");
    exit;
}

// ==========================================
// C. LOGIKA UPDATE HASIL PESERTA MASSAL (AJAX)
// ==========================================
if (isset($_POST['aksi']) && $_POST['aksi'] == 'update_hasil_massal') {
    header('Content-Type: application/json');

    $ids = $_POST['id_peserta'] ?? [];
    $hasil_list = $_POST['hasil_ujikom'] ?? [];
    $catatan = isset($_POST['catatan_lulus']) ? mysqli_real_escape_string($conn, $_POST['catatan_lulus']) : '';
    
    $count = 0;

    foreach ($ids as $key => $id) {
        if (!isset($hasil_list[$key]) || empty($hasil_list[$key])) continue;

        $hasil = mysqli_real_escape_string($conn, $hasil_list[$key]);
        $id_clean = mysqli_real_escape_string($conn, $id);

        if ($hasil == 'Lulus') {
            $sql_update = "UPDATE pengajuan_ujikom 
                           SET hasil_ujikom = 'Lulus', 
                               status_pengajuan = 'Lulus', 
                               catatan_lulus = '$catatan' 
                           WHERE id = '$id_clean'";
        } else {
            $sql_update = "UPDATE pengajuan_ujikom 
                           SET hasil_ujikom = 'Tidak Lulus', 
                               status_pengajuan = 'Tidak Lulus', 
                               catatan_lulus = NULL 
                           WHERE id = '$id_clean'";
        }

        if (mysqli_query($conn, $sql_update)) {
            $count++;
        }
    }

    if ($count > 0) {
        echo json_encode(['status' => 'success', 'message' => "Berhasil memperbarui $count data peserta."]);
    } else {
        echo json_encode(['status' => 'error', 'message' => "Tidak ada data yang diubah."]);
    }
    exit;
}

// ==========================================
// D. LOGIKA UPDATE KONTEN UTAMA & BERITA
// ==========================================
if (isset($_POST['update_all'])) {
    
    // 1. Bersihkan berita tambahan lama (id > 1) beserta filenya
    // VALIDASI: Hanya hapus berita jika form dikirim beserta data array kategori (mencegah data terhapus jika diakses via Dashboard)
    if (isset($_POST['kategori'])) {
        $query_file_tambahan = mysqli_query($conn, "SELECT id, berita_cover FROM tb_admin_update WHERE id > 1");
        while ($row_file = mysqli_fetch_assoc($query_file_tambahan)) {
            if (!empty($row_file['berita_cover']) && file_exists($path_pengumuman . $row_file['berita_cover'])) {
                unlink($path_pengumuman . $row_file['berita_cover']);
            }
            $id_del = $row_file['id'];
            mysqli_query($conn, "DELETE FROM tb_admin_update WHERE id = '$id_del'");
        }
    }

    // 2. Ambil data lama secara utuh sebagai Fallback (Pencegahan Data Hilang)
    $data_lama = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM tb_admin_update WHERE id = 1"));
    
    // Sanitisasi Input Utama
    $judul      = mysqli_real_escape_string($conn, $_POST['judul'] ?? '');
    $kj_daftar  = mysqli_real_escape_string($conn, $_POST['kj_daftar'] ?? '');
    $kj_ujian   = mysqli_real_escape_string($conn, $_POST['kj_ujian'] ?? '');
    $pj_daftar  = mysqli_real_escape_string($conn, $_POST['pj_daftar'] ?? '');
    $pj_ujian   = mysqli_real_escape_string($conn, $_POST['pj_ujian'] ?? '');

    // Status switch On/Off
    $status_pj = isset($_POST['status_pj']) ? 1 : 0;

    // VALIDASI: Cegah Error Undefined Index/Null pada Baris 150-152
    // Jika data Array kosong (Submit via Dashboard), otomatis panggil kembali data yang sudah ada di Database
    $kategori_utama = isset($_POST['kategori'][0]) ? mysqli_real_escape_string($conn, $_POST['kategori'][0]) : mysqli_real_escape_string($conn, $data_lama['berita_kategori'] ?? '');
    $judul_utama    = isset($_POST['judul_berita'][0]) ? mysqli_real_escape_string($conn, $_POST['judul_berita'][0]) : mysqli_real_escape_string($conn, $data_lama['berita_judul'] ?? '');
    $isi_utama      = isset($_POST['ringkasan'][0]) ? mysqli_real_escape_string($conn, $_POST['ringkasan'][0]) : mysqli_real_escape_string($conn, $data_lama['berita_isi'] ?? '');

    // --- PROSES FILE COVER UTAMA ---
    $sql_cover_utama = "";
    if (!empty($_FILES['cover']['name'][0])) {
        if (!empty($data_lama['berita_cover']) && file_exists($path_pengumuman . $data_lama['berita_cover'])) {
            unlink($path_pengumuman . $data_lama['berita_cover']);
        }
        $ext_img = pathinfo($_FILES['cover']['name'][0], PATHINFO_EXTENSION);
        $new_img = "img_" . time() . "_main." . $ext_img;
        move_uploaded_file($_FILES['cover']['tmp_name'][0], $path_pengumuman . $new_img);
        $sql_cover_utama = ", berita_cover='$new_img'";
    }

    // --- PROSES FILE SURAT PENGUMUMAN (BARU) ---
    $sql_file_pengumuman = "";
    if (!empty($_FILES['file_pengumuman']['name'])) {
        // Hapus file lama jika ada
        if (!empty($data_lama['file_pengumuman']) && file_exists($path_pengumuman . $data_lama['file_pengumuman'])) {
            unlink($path_pengumuman . $data_lama['file_pengumuman']);
        }
        $ext_pdf = pathinfo($_FILES['file_pengumuman']['name'], PATHINFO_EXTENSION);
        $new_pdf = "pengumuman_" . time() . "." . $ext_pdf;
        move_uploaded_file($_FILES['file_pengumuman']['tmp_name'], $path_pengumuman . $new_pdf);
        $sql_file_pengumuman = ", file_pengumuman='$new_pdf'";
    }

    // Update Baris Utama (ID 1)
    mysqli_query($conn, "UPDATE tb_admin_update SET 
                judul_pengumuman = '$judul', 
                kj_tgl_daftar = '$kj_daftar', 
                kj_tgl_ujian = '$kj_ujian',
                pj_tgl_daftar = '$pj_daftar', 
                pj_tgl_ujian = '$pj_ujian', 
                status_form_pj = '$status_pj',
                berita_kategori = '$kategori_utama',
                berita_judul = '$judul_utama', 
                berita_isi = '$isi_utama' 
                $sql_cover_utama 
                $sql_file_pengumuman
                WHERE id = 1");

    // 3. Tambah Berita Tambahan (Jika Ada)
    // VALIDASI: Memastikan array terdefinisi dengan ?? [], mencegah Fatal Error pada Baris 195-196 saat count()
    $judul_berita_arr = $_POST['judul_berita'] ?? []; 
    
    if (is_array($judul_berita_arr) && count($judul_berita_arr) > 1) {
        for ($i = 1; $i < count($judul_berita_arr); $i++) {
            if(empty($judul_berita_arr[$i])) continue;

            $kat  = mysqli_real_escape_string($conn, $_POST['kategori'][$i]);
            $jud  = mysqli_real_escape_string($conn, $judul_berita_arr[$i]);
            $ring = mysqli_real_escape_string($conn, $_POST['ringkasan'][$i]);
            
            $img_db = "";
            if (!empty($_FILES['cover']['name'][$i])) {
                $ext_t = pathinfo($_FILES['cover']['name'][$i], PATHINFO_EXTENSION);
                $img_name = "img_" . time() . "_$i." . $ext_t;
                move_uploaded_file($_FILES['cover']['tmp_name'][$i], $path_pengumuman . $img_name);
                $img_db = $img_name;
            }

            mysqli_query($conn, "INSERT INTO tb_admin_update 
                (berita_kategori, berita_judul, berita_isi, berita_cover, status_form_pj) 
                VALUES ('$kat', '$jud', '$ring', '$img_db', '0')");
        }
    }

    // Mengembalikan user ke halaman sebelumnya secara dinamis (Dashboard atau Form Admin Update)
    $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'admin_update.php';
    $redirect_base = explode('?', $referer)[0]; 
    
    header("Location: " . $redirect_base . "?status=success");
    exit();
}
?>