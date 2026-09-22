<?php
// Pastikan koneksi ke database sudah benar
require_once 'koneksi.php';

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proses Perbaikan Dokumen</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: 'Source Sans Pro', sans-serif; background-color: #f4f6f9; }
    </style>
</head>
<body>

<?php
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Ambil ID Pengajuan dari form
    $id = isset($_POST['id_pengajuan']) ? (int)$_POST['id_pengajuan'] : 0;
    if ($id == 0) {
        echo "<script>
            Swal.fire('Error', 'ID Pengajuan tidak valid.', 'error').then(() => { window.history.back(); });
        </script>";
        exit;
    }

    // --- LOGIKA TAMBAHAN: AMBIL DATA LAMA UNTUK PENGHAPUSAN FILE ---
    $sql_cek = "SELECT * FROM pengajuan_ujikom WHERE id = ?";
    $stmt_cek = $conn->prepare($sql_cek);
    $stmt_cek->bind_param("i", $id);
    $stmt_cek->execute();
    $result_cek = $stmt_cek->get_result();
    $data_lama = $result_cek->fetch_assoc();
    // --------------------------------------------------------------

    // Mapping input file ke kolom status verifikasi di database
    $dokumen_map = [
        'file_surat_usulan_perpindahan' => 'd1_surat_usulan_status',
        'file_dokumen_penetapan'        => 'd2_rekomendasi_formasi_status',
        'file_surat_usulan_uji'         => 'd3_usulan_ujikom_status',
        'file_portofolio'               => 'd4_portofolio_status',
        'file_sk_cpns_pns'              => 'd5_sk_cpns_pns_status',
        'file_sk_pangkat'               => 'd6_sk_pangkat_status',
        'file_sk_jabatan'               => 'd7_sk_jabatan_status',
        'file_skp'                      => 'd8_nilai_skp_status',
        'file_ijazah_transkrip'         => 'd9_ijazah_transkrip_status',
        'file_pernyataan_integritas'    => 'd10_integritas_status',
        'file_pernyataan_bersedia'      => 'd11_bersedia_status',
        'file_pernyataan_pengalaman'    => 'd12_pengalaman_2th_status',
        'file_rencana_penempatan'       => 'd13_rencana_penempatan_status'
    ];

    $update_fields = [];
    $params = [];
    $types = "";
    $upload_success_count = 0;

    // Tentukan folder tujuan penyimpanan
    $upload_dir = 'uploads/perpindahan/'; 

    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true); 
    }

    foreach ($dokumen_map as $file_key => $status_key) {
        // Cek apakah file diunggah dan tidak ada error
        if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] == 0) {
            
            // --- LOGIKA PENGHAPUSAN FILE LAMA ---
            if (!empty($data_lama[$file_key])) {
                $path_file_lama = $upload_dir . $data_lama[$file_key];
                if (file_exists($path_file_lama)) {
                    unlink($path_file_lama); // Menghapus file fisik lama
                }
            }
            // ------------------------------------

            $ext = pathinfo($_FILES[$file_key]['name'], PATHINFO_EXTENSION);
            
            // Penamaan file yang unik agar tidak menimpa file lama
            $nama_file_baru = "perbaikan_" . $file_key . "_" . $id . "_" . time() . "." . $ext;
            $target_path = $upload_dir . $nama_file_baru;

            // Proses pemindahan file ke folder tujuan
            if (move_uploaded_file($_FILES[$file_key]['tmp_name'], $target_path)) {
                // Siapkan query update untuk nama file
                $update_fields[] = "$file_key = ?";
                $params[] = $nama_file_baru;
                $types .= "s";

                // Reset status dokumen menjadi 'Menunggu' agar bisa diverifikasi ulang
                $update_fields[] = "$status_key = 'Menunggu'";
                $upload_success_count++;
            }
        }
    }

    // Jika tidak ada satu pun file yang berhasil diunggah
    if ($upload_success_count === 0) {
        echo "<script>
            Swal.fire({
                icon: 'warning',
                title: 'Tidak Ada Perubahan',
                text: 'Silakan pilih file PDF terlebih dahulu untuk melakukan perbaikan.',
                confirmButtonColor: '#f39c12'
            }).then(() => { window.history.back(); });
        </script>";
        exit;
    }

    // Update status pengajuan utama menjadi 'Menunggu Verifikasi'
    $update_fields[] = "status_pengajuan = 'Menunggu Verifikasi'";

    // Susun query SQL Update
    $sql = "UPDATE pengajuan_ujikom SET " . implode(", ", $update_fields) . " WHERE id = ?";
    $params[] = $id;
    $types .= "i";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);

    if ($stmt->execute()) {
        // Tampilan sukses modern dengan SweetAlert2
        echo "<script>
            Swal.fire({
                title: 'Berhasil Terunggah!',
                text: 'Dokumen lama telah dihapus dan diganti dengan dokumen baru. Status sekarang: Menunggu Verifikasi',
                icon: 'success',
                confirmButtonText: 'OK',
                confirmButtonColor: '#28a745',
                allowOutsideClick: false
            }).then((result) => {
                if (result.isConfirmed) {
                    if (window.top !== window.self) {
                        window.top.location.href = 'index_pengusul.php';
                    } else {
                        window.location.href = 'index_pengusul.php';
                    }
                }
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire('Gagal Database', '" . addslashes($stmt->error) . "', 'error');
        </script>";
    }
}
?>

</body>
</html>