<?php
/**
 * FILE: proses_sertifikat.php
 * UPDATE: Penanganan dinamis data angka_kredit_sk, tmt_jabatan, sk_jabatan,
 * serta integrasi Upload, Edit, dan Hapus Surat Hasil Ujikom.
 */

ob_start();
session_start();
require_once 'koneksi.php';

error_reporting(0);
header('Content-Type: application/json');

// --- 1. LOGIKA PESERTA (UPLOAD BERKAS TAMBAHAN SERTIFIKAT) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'simpan_formulir_sertifikat') {
    
    $id_pengajuan = mysqli_real_escape_string($conn, $_POST['id_pengajuan']);
    
    // Menangkap input teks/tanggal baru
    $jabatan         = mysqli_real_escape_string($conn, $_POST['jabatan_sebelum_jafung'] ?? '');
    $tmt_jabatan     = mysqli_real_escape_string($conn, $_POST['tmt_jabatan'] ?? '');
    $angka_kredit_sk = mysqli_real_escape_string($conn, $_POST['angka_kredit_sk'] ?? '');
    
    $target_dir = "uploads/perpindahan/";
    if (!is_dir($target_dir)) { mkdir($target_dir, 0777, true); }

    // Memasukkan input teks ke dalam array query (hanya jika form tersebut muncul dan ada isinya)
    $sql_parts = [];
    if (!empty($jabatan)) $sql_parts[] = "jabatan_sebelum_jafung = '$jabatan'";
    if (!empty($tmt_jabatan)) $sql_parts[] = "tmt_jabatan = '$tmt_jabatan'";
    if (!empty($angka_kredit_sk)) $sql_parts[] = "angka_kredit_sk = '$angka_kredit_sk'";

    // Proses SK Pencantuman Gelar (Hanya diupload jika formnya muncul)
    if (!empty($_FILES['f_sk_gelar']['name'])) {
        $ext = pathinfo($_FILES['f_sk_gelar']['name'], PATHINFO_EXTENSION);
        $file_name_save = "SK_GELAR_" . $id_pengajuan . "_" . time() . "." . $ext;
        if (move_uploaded_file($_FILES['f_sk_gelar']['tmp_name'], $target_dir . $file_name_save)) {
            $sql_parts[] = "sk_pencantuman_gelar = '$file_name_save'";
        }
    }

    // Proses SKP Lengkap
    if (!empty($_FILES['f_skp_lengkap']['name'])) {
        $ext = pathinfo($_FILES['f_skp_lengkap']['name'], PATHINFO_EXTENSION);
        $file_name_save = "SKP_" . $id_pengajuan . "_" . time() . "." . $ext;
        if (move_uploaded_file($_FILES['f_skp_lengkap']['tmp_name'], $target_dir . $file_name_save)) {
            $sql_parts[] = "skp_lengkap = '$file_name_save'";
        }
    }

    // Proses Berkas Lainnya
    if (!empty($_FILES['f_berkas_lainnya']['name'])) {
        $ext = pathinfo($_FILES['f_berkas_lainnya']['name'], PATHINFO_EXTENSION);
        $file_name_save = "BERKAS_LAINNYA_" . $id_pengajuan . "_" . time() . "." . $ext;
        if (move_uploaded_file($_FILES['f_berkas_lainnya']['tmp_name'], $target_dir . $file_name_save)) {
            $sql_parts[] = "f_berkas_lainnya = '$file_name_save'";
        }
    }

    // Proses SK Jabatan (Hanya diupload jika asal jabatan = Fungsional)
    if (!empty($_FILES['f_sk_jabatan']['name'])) {
        $ext = pathinfo($_FILES['f_sk_jabatan']['name'], PATHINFO_EXTENSION);
        $file_name_save = "SK_JABATAN_" . $id_pengajuan . "_" . time() . "." . $ext;
        if (move_uploaded_file($_FILES['f_sk_jabatan']['tmp_name'], $target_dir . $file_name_save)) {
            $sql_parts[] = "sk_jabatan = '$file_name_save'";
        }
    }

    // Pastikan ada data yang diupdate untuk menghindari error sintaks SQL kosong
    if (count($sql_parts) > 0) {
        $query_update = "UPDATE pengajuan_ujikom SET " . implode(', ', $sql_parts) . " WHERE id = '$id_pengajuan'";
        
        ob_clean(); 
        if (mysqli_query($conn, $query_update)) {
            echo json_encode(['status' => 'success', 'message' => 'Data formulir berhasil disimpan.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Database error: ' . mysqli_error($conn)]);
        }
    } else {
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'Tidak ada data yang dikirimkan untuk disimpan.']);
    }
    exit;
}

// --- 2. LOGIKA ADMIN (UPLOAD SERTIFIKAT & ANGKA KREDIT) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'upload_sertifikat_single') {
    
    $id_peserta = mysqli_real_escape_string($conn, $_POST['id_peserta_sertifikat']);
    $angka_kredit = mysqli_real_escape_string($conn, $_POST['angka_kredit']);
    
    $target_dir_sertifikat = "uploads/sertifikat/";
    if (!is_dir($target_dir_sertifikat)) { mkdir($target_dir_sertifikat, 0777, true); }

    $q_old = mysqli_query($conn, "SELECT file_sertifikat FROM pengajuan_ujikom WHERE id = '$id_peserta'");
    $d_old = mysqli_fetch_assoc($q_old);

    if (!empty($_FILES['file_sertifikat']['name'])) {
        $ext = strtolower(pathinfo($_FILES['file_sertifikat']['name'], PATHINFO_EXTENSION));
        
        if ($ext !== 'pdf') {
            echo json_encode(['status' => 'error', 'message' => 'Format file harus PDF!']);
            exit;
        }

        $new_file_name = "SERTIFIKAT_" . $id_peserta . "_" . time() . ".pdf";
        
        if (move_uploaded_file($_FILES['file_sertifikat']['tmp_name'], $target_dir_sertifikat . $new_file_name)) {
            if (!empty($d_old['file_sertifikat']) && file_exists($target_dir_sertifikat . $d_old['file_sertifikat'])) {
                unlink($target_dir_sertifikat . $d_old['file_sertifikat']);
            }
            $sql = "UPDATE pengajuan_ujikom SET file_sertifikat = '$new_file_name', angka_kredit = '$angka_kredit' WHERE id = '$id_peserta'";
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Gagal mengunggah file ke server.']);
            exit;
        }
    } else {
        $sql = "UPDATE pengajuan_ujikom SET angka_kredit = '$angka_kredit' WHERE id = '$id_peserta'";
    }

    ob_clean();
    if (mysqli_query($conn, $sql)) {
        echo json_encode(['status' => 'success', 'message' => 'Sertifikat dan Angka Kredit berhasil diperbarui.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal memperbarui database.']);
    }
    exit;
}

// --- 3. LOGIKA ADMIN (UPDATE HASIL MASSAL / SIMPAN SEMUA) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'update_hasil_massal') {
    
    $ids = isset($_POST['id_peserta']) ? $_POST['id_peserta'] : [];
    $hasils = isset($_POST['hasil_ujikom']) ? $_POST['hasil_ujikom'] : [];
    
    // Menangkap data tanggal rentang
    $tgl_mulai = isset($_POST['tgl_tidak_lulus_mulai']) ? $_POST['tgl_tidak_lulus_mulai'] : [];
    $tgl_selesai = isset($_POST['tgl_tidak_lulus_selesai']) ? $_POST['tgl_tidak_lulus_selesai'] : [];
    
    $catatan = mysqli_real_escape_string($conn, $_POST['catatan_lulus'] ?? '');

    if (empty($ids)) {
        echo json_encode(['status' => 'error', 'message' => 'Tidak ada data peserta yang diterima.']);
        exit;
    }

    $success_count = 0;

    foreach ($ids as $index => $id) {
        $id_p = mysqli_real_escape_string($conn, $id);
        $hasil = mysqli_real_escape_string($conn, $hasils[$index]);

        if (!empty($hasil)) {
            // Inisialisasi variabel tanggal
            $val_tgl_mulai = "NULL";
            $val_tgl_selesai = "NULL";

            // Jika hasil 'Tidak Lulus', gunakan tanggal dari input form
            if ($hasil === 'Tidak Lulus') {
                $m = mysqli_real_escape_string($conn, $tgl_mulai[$index]);
                $s = mysqli_real_escape_string($conn, $tgl_selesai[$index]);
                
                // Jika input tanggal kosong, default ke NOW()
                $val_tgl_mulai = !empty($m) ? "'$m'" : "NOW()";
                $val_tgl_selesai = !empty($s) ? "'$s'" : "NULL";
            }

            $query = "UPDATE pengajuan_ujikom SET 
                      hasil_ujikom = '$hasil', 
                      status_pengajuan = '$hasil',
                      catatan_lulus = '$catatan',
                      tanggal_tidak_lulus = $val_tgl_mulai,
                      tanggal_re_registrasi = $val_tgl_selesai 
                      WHERE id = '$id_p'";
            
            if (mysqli_query($conn, $query)) {
                $success_count++;
            }
        }
    }

    ob_clean();
    if ($success_count > 0) {
        echo json_encode(['status' => 'success', 'message' => $success_count . ' data hasil peserta berhasil diperbarui.']);
    } else {
        echo json_encode(['status' => 'info', 'message' => 'Tidak ada perubahan data yang disimpan.']);
    }
    exit;
}

// --- 4. LOGIKA ADMIN (UPLOAD / EDIT SURAT HASIL UJIKOM) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'upload_surat_hasil_ujikom') {
    
    $id_peserta = mysqli_real_escape_string($conn, $_POST['id_peserta']);
    
    $target_dir_hasil = "uploads/hasil_ujikom/";
    if (!is_dir($target_dir_hasil)) { mkdir($target_dir_hasil, 0777, true); }

    // Ambil data nama berkas lama untuk proses penimpaan (jika mode edit/perbarui)
    $q_old = mysqli_query($conn, "SELECT file_hasil_ujikom FROM pengajuan_ujikom WHERE id = '$id_peserta'");
    $d_old = mysqli_fetch_assoc($q_old);

    if (!empty($_FILES['file_surat_hasil']['name'])) {
        $ext = strtolower(pathinfo($_FILES['file_surat_hasil']['name'], PATHINFO_EXTENSION));
        
        if ($ext !== 'pdf') {
            ob_clean();
            echo json_encode(['status' => 'error', 'message' => 'Format berkas harus berupa PDF!']);
            exit;
        }

        // Penamaan file baru yang unik secara sistem
        $new_file_name = "SURAT_HASIL_" . $id_peserta . "_" . time() . ".pdf";
        
        if (move_uploaded_file($_FILES['file_surat_hasil']['tmp_name'], $target_dir_hasil . $new_file_name)) {
            // Hapus berkas fisik yang lama di server jika ada (agar tidak menumpuk jadi sampah storage)
            if (!empty($d_old['file_hasil_ujikom']) && file_exists($target_dir_hasil . $d_old['file_hasil_ujikom'])) {
                unlink($target_dir_hasil . $d_old['file_hasil_ujikom']);
            }
            
            $sql = "UPDATE pengajuan_ujikom SET file_hasil_ujikom = '$new_file_name' WHERE id = '$id_peserta'";
            
            ob_clean();
            if (mysqli_query($conn, $sql)) {
                echo json_encode(['status' => 'success', 'message' => 'Surat hasil ujikom berhasil disimpan resmi ke sistem!']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Gagal memperbarui nama berkas di database.']);
            }
        } else {
            ob_clean();
            echo json_encode(['status' => 'error', 'message' => 'Gagal mengunggah file ke direktori server.']);
        }
    } else {
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'Tidak ada file berkas yang dipilih untuk diunggah.']);
    }
    exit;
}

// --- 5. LOGIKA ADMIN (HAPUS BERKAS SURAT HASIL UJIKOM) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'hapus_surat_hasil_ujikom') {
    
    $id_peserta = mysqli_real_escape_string($conn, $_POST['id_peserta']);
    
    // Ambil info nama file untuk dihapus secara fisik dari folder storage lokal
    $query = mysqli_query($conn, "SELECT file_hasil_ujikom FROM pengajuan_ujikom WHERE id = '$id_peserta'");
    $data = mysqli_fetch_assoc($query);
    
    if ($data && !empty($data['file_hasil_ujikom'])) {
        $path_file = 'uploads/hasil_ujikom/' . $data['file_hasil_ujikom'];
        if (file_exists($path_file)) {
            unlink($path_file); // Menghapus file fisik PDF dari server
        }
    }
    
    // Kosongkan nama file di database dengan menjadikannya NULL
    $update = mysqli_query($conn, "UPDATE pengajuan_ujikom SET file_hasil_ujikom = NULL WHERE id = '$id_peserta'");
    
    ob_clean();
    if ($update) {
        echo json_encode(['status' => 'success', 'message' => 'Surat Hasil berhasil dihapus permanent dari sistem!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal memperbarui status berkas di database.']);
    }
    exit;
}
?>