<?php
/**
 * ==================================================================================
 * FILE: bimtek_suket.php
 * DESKRIPSI: Rekapitulasi, Tambah, Edit & Cetak Suket Bimtek (Peserta, Panitia, Moderator, Narasumber)
 * TABEL UTAMA: tb_bimtek_peserta
 * ==================================================================================
 */

ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

require_once 'auth_guard.php'; 
require_once 'koneksi.php';    

// 1. CEK HAK AKSES ROLE
$user_role = $_SESSION['user_role_sesi'] ?? $_SESSION['role'] ?? $_SESSION['level'] ?? '';
$allowed_roles = ['user_super_admin', 'user_verifikator', 'user_kasubdit'];

if (!in_array($user_role, $allowed_roles)) {
    $redirect_target = ($user_role === 'user_kasubdit') ? 'index_kasubdit.php' : 'index_asli.php';
    echo "<script>
            alert('Anda tidak memiliki akses ke halaman ini!'); 
            window.location='" . $redirect_target . "';
          </script>";
    exit;
}

// 2. PROSES SIMPAN DATA BARU (POST: tambah_peserta)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_peserta'])) {
    $nama_peserta      = trim($_POST['nama_peserta'] ?? '');
    $nip               = trim($_POST['nip'] ?? '');
    $no_suket          = trim($_POST['no_suket'] ?? '');
    $jabatan           = trim($_POST['jabatan'] ?? '');
    $instansi          = trim($_POST['instansi'] ?? '');
    $unit_kerja        = trim($_POST['unit_kerja'] ?? '');
    $metode_kehadiran  = trim($_POST['metode_kehadiran'] ?? 'Online');
    $peran             = trim($_POST['peran'] ?? 'Peserta');
    $absen_1           = intval($_POST['absen_1'] ?? 1);
    $absen_2           = intval($_POST['absen_2'] ?? 1);
    $nilai_pretest     = intval($_POST['nilai_pretest'] ?? 0);
    $nilai_posttest    = intval($_POST['nilai_posttest'] ?? 0);
    $total_nilai       = $nilai_pretest + $nilai_posttest;
    $sertif            = trim($_POST['sertif'] ?? 'YA');
    $keterangan_input  = trim($_POST['keterangan'] ?? '');

    if ($peran !== 'Peserta' && !empty($peran)) {
        $keterangan = !empty($keterangan_input) ? strtoupper($peran) . " - " . $keterangan_input : strtoupper($peran);
    } else {
        $keterangan = $keterangan_input;
    }

    try {
        $stmt = $conn->prepare("INSERT INTO tb_bimtek_peserta 
            (nama_peserta, nip, no_suket, jabatan, instansi, unit_kerja, metode_kehadiran, absen_23_juli, absen_24_juli, nilai_pretest, nilai_posttest, total_nilai, sertif, keterangan) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->bind_param("sssssssiiiisss", 
            $nama_peserta, $nip, $no_suket, $jabatan, $instansi, $unit_kerja, 
            $metode_kehadiran, $absen_1, $absen_2, $nilai_pretest, $nilai_posttest, 
            $total_nilai, $sertif, $keterangan
        );

        if ($stmt->execute()) {
            echo "<script>
                    alert('Data berhasil ditambahkan!');
                    window.location='bimtek_suket.php';
                  </script>";
            exit;
        }
    } catch (Exception $e) {
        echo "<script>alert('Error: " . addslashes($e->getMessage()) . "');</script>";
    }
}

// 3. PROSES EDIT / UPDATE DATA (POST: edit_peserta)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_peserta'])) {
    $id_peserta        = intval($_POST['id_peserta'] ?? 0);
    $nama_peserta      = trim($_POST['nama_peserta'] ?? '');
    $nip               = trim($_POST['nip'] ?? '');
    $no_suket          = trim($_POST['no_suket'] ?? '');
    $jabatan           = trim($_POST['jabatan'] ?? '');
    $instansi          = trim($_POST['instansi'] ?? '');
    $unit_kerja        = trim($_POST['unit_kerja'] ?? '');
    $metode_kehadiran  = trim($_POST['metode_kehadiran'] ?? 'Online');
    $absen_1           = intval($_POST['absen_1'] ?? 0);
    $absen_2           = intval($_POST['absen_2'] ?? 0);
    $nilai_pretest     = intval($_POST['nilai_pretest'] ?? 0);
    $nilai_posttest    = intval($_POST['nilai_posttest'] ?? 0);
    $total_nilai       = $nilai_pretest + $nilai_posttest;
    $sertif            = trim($_POST['sertif'] ?? 'YA');
    $keterangan        = trim($_POST['keterangan'] ?? '');

    try {
        $stmt = $conn->prepare("UPDATE tb_bimtek_peserta SET 
            nama_peserta = ?, nip = ?, no_suket = ?, jabatan = ?, instansi = ?, 
            unit_kerja = ?, metode_kehadiran = ?, absen_23_juli = ?, absen_24_juli = ?, 
            nilai_pretest = ?, nilai_posttest = ?, total_nilai = ?, sertif = ?, keterangan = ? 
            WHERE id = ?");
        
        $stmt->bind_param("sssssssiiiisssi", 
            $nama_peserta, $nip, $no_suket, $jabatan, $instansi, $unit_kerja, 
            $metode_kehadiran, $absen_1, $absen_2, $nilai_pretest, $nilai_posttest, 
            $total_nilai, $sertif, $keterangan, $id_peserta
        );

        if ($stmt->execute()) {
            echo "<script>
                    alert('Data berhasil diperbarui!');
                    window.location='bimtek_suket.php';
                  </script>";
            exit;
        }
    } catch (Exception $e) {
        echo "<script>alert('Error Update: " . addslashes($e->getMessage()) . "');</script>";
    }
}

// 4. AMBIL DATA PESERTA
$peserta_list = [];
$total_peserta = 0;
try {
    $sql = "SELECT * FROM tb_bimtek_peserta ORDER BY id ASC";
    $result = $conn->query($sql);
    if ($result) {
        $peserta_list = $result->fetch_all(MYSQLI_ASSOC);
        $total_peserta = count($peserta_list);
    }
} catch (mysqli_sql_exception $e) {}

require_once 'template/header.php'; 
require_once 'template/navbar.php'; 
require_once 'template/sidebar.php'; 
?>

<style>
    body, .wrapper, .content-wrapper { background-color: #f4f6f9 !important; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
    .card-custom { background: #ffffff; border: 1px solid #dee2e6; border-radius: 6px; box-shadow: 0 0 1px rgba(0,0,0,.125), 0 1px 3px rgba(0,0,0,.1); }
    .filter-card { background: #ffffff; border-radius: 6px; border-left: 4px solid #17a2b8; border-top: 1px solid #dee2e6; border-right: 1px solid #dee2e6; border-bottom: 1px solid #dee2e6; margin-bottom: 15px; box-shadow: 0 1px 3px rgba(0,0,0,.05); }
    .table-rekap { width: 100% !important; border-collapse: collapse; }
    .table-rekap thead th { background-color: #343a40 !important; color: #ffffff !important; font-weight: 600; font-size: 0.85rem; padding: 12px 10px; vertical-align: middle; border: 1px solid #454d55; }
    .table-rekap tbody td { padding: 8px 10px; vertical-align: middle; border-bottom: 1px solid #dee2e6; font-size: 0.85rem; }
    .table-rekap tbody tr:hover { background-color: rgba(0,0,0,.025); }
    .badge-metode-online { background-color: #17a2b8; color: #ffffff; border-radius: 4px; padding: 4px 8px; font-size: 0.75rem; font-weight: 600; }
    .badge-metode-offline { background-color: #6c757d; color: #ffffff; border-radius: 4px; padding: 4px 8px; font-size: 0.75rem; font-weight: 600; }
    .icon-box-success { color: #28a745; font-size: 1.15rem; }
    .icon-box-muted { color: #dc3545; font-size: 1.15rem; }
    .btn-sertif-ya { background-color: #28a745 !important; color: #ffffff !important; border-radius: 50rem !important; padding: 2px 8px !important; font-weight: 700 !important; font-size: 0.75rem !important; border: none !important; }
    .badge-sertif-tidak { background-color: #6c757d !important; color: #ffffff; border-radius: 50rem; padding: 2px 8px; font-weight: 700; font-size: 0.75rem; }
    .badge-peran { font-size: 0.7rem; padding: 2px 6px; border-radius: 3px; font-weight: 600; text-transform: uppercase; }
</style>

<div class="content-wrapper">
    <section class="content-header pt-3 pb-2">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-4">
                    <h4 class="font-weight-bold text-dark mb-0">
                        <i class="fas fa-th-list text-primary mr-2"></i> Rekapitulasi Data Bimtek
                    </h4>
                </div>
                <div class="col-sm-8 text-sm-right mt-2 mt-sm-0">
                    <button type="button" class="btn btn-primary btn-sm font-weight-bold mr-2 shadow-sm" data-toggle="modal" data-target="#modalTambahPeserta">
                        <i class="fas fa-user-plus mr-1"></i> Tambah Data
                    </button>
                    <button type="button" onclick="downloadAllSuket()" class="btn btn-success btn-sm font-weight-bold mr-2 shadow-sm">
                        <i class="fas fa-file-archive mr-1"></i> Download ZIP
                    </button>
                    <span class="badge badge-secondary p-2" style="font-size: 0.85rem;">
                        Total: <strong><?= $total_peserta; ?> Data</strong>
                    </span>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <!-- CARD FILTER -->
            <div class="card filter-card p-3">
                <div class="row align-items-center">
                    <div class="col-md-1 text-muted font-weight-bold small"><i class="fas fa-filter mr-1"></i> FILTER:</div>
                    <div class="col-md-2 mb-2 mb-md-0">
                        <select id="filterMetode" class="form-control form-control-sm">
                            <option value="">-- Semua Metode --</option>
                            <option value="Online">Online</option>
                            <option value="Offline">Offline</option>
                        </select>
                    </div>
                    <div class="col-md-2 mb-2 mb-md-0">
                        <select id="filterAbsen1" class="form-control form-control-sm">
                            <option value="">-- Absen 1 --</option>
                            <option value="Hadir">Hadir</option>
                            <option value="Tidak Hadir">Tidak Hadir</option>
                        </select>
                    </div>
                    <div class="col-md-2 mb-2 mb-md-0">
                        <select id="filterAbsen2" class="form-control form-control-sm">
                            <option value="">-- Absen 2 --</option>
                            <option value="Hadir">Hadir</option>
                            <option value="Tidak Hadir">Tidak Hadir</option>
                        </select>
                    </div>
                    <div class="col-md-2 mb-2 mb-md-0">
                        <select id="filterSertif" class="form-control form-control-sm">
                            <option value="">-- Status Sertifikat --</option>
                            <option value="YA">Tersedia (YA)</option>
                            <option value="TIDAK">Tidak (TIDAK)</option>
                        </select>
                    </div>
                    <div class="col-md-3 text-right">
                        <button type="button" class="btn btn-sm btn-outline-secondary font-weight-bold" onclick="resetFilter()">
                            <i class="fas fa-undo mr-1"></i> Reset
                        </button>
                    </div>
                </div>
            </div>

            <!-- TABEL UTAMA -->
            <div class="card card-custom p-3 mb-4">
                <div class="table-responsive">
                    <table class="table table-rekap align-middle mb-0" id="tableBimtek">
                        <thead>
                            <tr>
                                <th class="text-center" width="40">No</th>
                                <th>Nama Peserta / NIP</th>
                                <th>No Suket</th>
                                <th>Jabatan</th>
                                <th>Instansi / Unit Kerja</th>
                                <th class="text-center">Metode</th>
                                <th class="text-center">Absen 1</th>
                                <th class="text-center">Absen 2</th>
                                <th class="text-center">Pre</th>
                                <th class="text-center">Post</th>
                                <th class="text-center">Total</th>
                                <th class="text-center">Sertif</th>
                                <th>Keterangan / Peran</th>
                                <th class="text-center" width="60">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($peserta_list)): ?>
                                <?php 
                                $no = 1; 
                                foreach ($peserta_list as $row): 
                                    $id_peserta     = $row['id'];
                                    $absen1         = intval($row['absen_23_juli'] ?? $row['absen_1'] ?? 0);
                                    $absen2         = intval($row['absen_24_juli'] ?? $row['absen_2'] ?? 0);
                                    $metode_val     = trim($row['metode_kehadiran'] ?? 'Online');
                                    $is_online      = (strtolower($metode_val) === 'online');
                                    $is_sertif_ya   = (strtoupper(trim($row['sertif'] ?? '')) === 'YA');
                                    $ket_val        = trim($row['keterangan'] ?? '');
                                ?>
                                <tr>
                                    <td class="text-center font-weight-bold"><?= $no; ?></td>
                                    <td>
                                        <div class="font-weight-bold text-dark"><?= htmlspecialchars($row['nama_peserta'] ?? '-'); ?></div>
                                        <div class="text-muted small"><i class="far fa-id-card mr-1"></i><?= htmlspecialchars($row['nip'] ?? '-'); ?></div>
                                    </td>
                                    <td>
                                        <?= !empty($row['no_suket']) ? '<span class="badge badge-light border text-primary font-weight-bold">'.htmlspecialchars($row['no_suket']).'</span>' : '-' ?>
                                    </td>
                                    <td><small><?= htmlspecialchars($row['jabatan'] ?? '-'); ?></small></td>
                                    <td>
                                        <div class="font-weight-bold small"><?= htmlspecialchars($row['instansi'] ?? '-'); ?></div>
                                        <div class="text-muted small"><?= htmlspecialchars($row['unit_kerja'] ?? '-'); ?></div>
                                    </td>
                                    <td class="text-center" data-search="<?= htmlspecialchars($metode_val); ?>">
                                        <span class="<?= $is_online ? 'badge-metode-online' : 'badge-metode-offline'; ?>">
                                            <?= htmlspecialchars($metode_val); ?>
                                        </span>
                                    </td>
                                    <td class="text-center" data-search="<?= ($absen1 === 1) ? 'Hadir' : 'Tidak Hadir'; ?>">
                                        <i class="fas <?= ($absen1 === 1) ? 'fa-check-square icon-box-success' : 'fa-window-close icon-box-muted'; ?>"></i>
                                    </td>
                                    <td class="text-center" data-search="<?= ($absen2 === 1) ? 'Hadir' : 'Tidak Hadir'; ?>">
                                        <i class="fas <?= ($absen2 === 1) ? 'fa-check-square icon-box-success' : 'fa-window-close icon-box-muted'; ?>"></i>
                                    </td>
                                    <td class="text-center font-weight-bold"><?= htmlspecialchars($row['nilai_pretest'] ?? '0'); ?></td>
                                    <td class="text-center font-weight-bold"><?= htmlspecialchars($row['nilai_posttest'] ?? '0'); ?></td>
                                    <td class="text-center font-weight-bold text-primary"><?= htmlspecialchars($row['total_nilai'] ?? '0'); ?></td>
                                    <td class="text-center" data-search="<?= $is_sertif_ya ? 'YA' : 'TIDAK'; ?>">
                                        <?php if ($is_sertif_ya): ?>
                                            <a href="cetak_suket.php?id=<?= $id_peserta; ?>" target="_blank" class="btn-sertif-ya">YA</a>
                                        <?php else: ?>
                                            <span class="badge-sertif-tidak">TIDAK</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (stripos($ket_val, 'PANITIA') !== false): ?>
                                            <span class="badge badge-warning text-dark badge-peran">PANITIA</span>
                                        <?php elseif (stripos($ket_val, 'MODERATOR') !== false): ?>
                                            <span class="badge badge-info badge-peran">MODERATOR</span>
                                        <?php elseif (stripos($ket_val, 'NARASUMBER') !== false): ?>
                                            <span class="badge badge-danger badge-peran">NARASUMBER</span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary badge-peran">PESERTA</span>
                                        <?php endif; ?>
                                        <small><?= htmlspecialchars($ket_val ?: '-'); ?></small>
                                    </td>
                                    <!-- TOMBOL EDIT -->
                                    <td class="text-center">
                                        <button type="button" class="btn btn-warning btn-xs font-weight-bold text-white shadow-sm btn-edit" 
                                                data-id="<?= $id_peserta; ?>"
                                                data-nama="<?= htmlspecialchars($row['nama_peserta']); ?>"
                                                data-nip="<?= htmlspecialchars($row['nip']); ?>"
                                                data-nosuket="<?= htmlspecialchars($row['no_suket']); ?>"
                                                data-jabatan="<?= htmlspecialchars($row['jabatan']); ?>"
                                                data-instansi="<?= htmlspecialchars($row['instansi']); ?>"
                                                data-unitkerja="<?= htmlspecialchars($row['unit_kerja']); ?>"
                                                data-metode="<?= htmlspecialchars($metode_val); ?>"
                                                data-absen1="<?= $absen1; ?>"
                                                data-absen2="<?= $absen2; ?>"
                                                data-pre="<?= $row['nilai_pretest']; ?>"
                                                data-post="<?= $row['nilai_posttest']; ?>"
                                                data-sertif="<?= $row['sertif']; ?>"
                                                data-keterangan="<?= htmlspecialchars($row['keterangan']); ?>"
                                                title="Edit Data">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php $no++; endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- MODAL TAMBAH DATA -->
<div class="modal fade" id="modalTambahPeserta" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title font-weight-bold"><i class="fas fa-user-plus mr-2"></i>Tambah Data Baru</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-12 form-group">
                            <label class="font-weight-bold small text-primary">Kategori Peran *</label>
                            <select name="peran" id="inputPeran" class="form-control form-control-sm font-weight-bold" onchange="togglePeranFormTambah()">
                                <option value="Peserta">Peserta (Mengikuti Pre/Post Test)</option>
                                <option value="Panitia">Panitia</option>
                                <option value="Moderator">Moderator</option>
                                <option value="Narasumber">Narasumber</option>
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold small">Nama Lengkap *</label>
                            <input type="text" name="nama_peserta" class="form-control form-control-sm" required placeholder="Masukkan Nama & Gelar">
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold small">NIP</label>
                            <input type="text" name="nip" class="form-control form-control-sm" placeholder="Contoh: 199003022024211006">
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold small">No Suket</label>
                            <input type="text" name="no_suket" class="form-control form-control-sm" placeholder="Nomor Suket">
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold small">Jabatan</label>
                            <input type="text" name="jabatan" class="form-control form-control-sm" placeholder="Jabatan">
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold small">Instansi</label>
                            <input type="text" name="instansi" class="form-control form-control-sm" placeholder="Instansi">
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold small">Unit Kerja</label>
                            <input type="text" name="unit_kerja" class="form-control form-control-sm" placeholder="Unit Kerja">
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="font-weight-bold small">Metode</label>
                            <select name="metode_kehadiran" class="form-control form-control-sm">
                                <option value="Online">Online</option>
                                <option value="Offline">Offline</option>
                            </select>
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="font-weight-bold small">Absen Hari 1</label>
                            <select name="absen_1" class="form-control form-control-sm">
                                <option value="1" selected>Hadir (✔)</option>
                                <option value="0">Tidak Hadir (✖)</option>
                            </select>
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="font-weight-bold small">Absen Hari 2</label>
                            <select name="absen_2" class="form-control form-control-sm">
                                <option value="1" selected>Hadir (✔)</option>
                                <option value="0">Tidak Hadir (✖)</option>
                            </select>
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="font-weight-bold small">Nilai Pretest</label>
                            <input type="number" id="inputPre" name="nilai_pretest" class="form-control form-control-sm" value="0" min="0" oninput="hitungTotalTambah()">
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="font-weight-bold small">Nilai Posttest</label>
                            <input type="number" id="inputPost" name="nilai_posttest" class="form-control form-control-sm" value="0" min="0" oninput="hitungTotalTambah()">
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="font-weight-bold small">Total Nilai</label>
                            <input type="number" id="inputTotal" name="total_nilai" class="form-control form-control-sm bg-light" value="0" readonly>
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="font-weight-bold small">Status Sertifikat</label>
                            <select name="sertif" id="inputSertif" class="form-control form-control-sm">
                                <option value="YA" selected>YA (Tersedia)</option>
                                <option value="TIDAK">TIDAK (Belum Memenuhi)</option>
                            </select>
                        </div>
                        <div class="col-md-8 form-group">
                            <label class="font-weight-bold small">Keterangan Tambahan</label>
                            <input type="text" name="keterangan" class="form-control form-control-sm" placeholder="Catatan tambahan">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                    <button type="submit" name="tambah_peserta" class="btn btn-primary btn-sm font-weight-bold"><i class="fas fa-save mr-1"></i> Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL EDIT DATA -->
<div class="modal fade" id="modalEditPeserta" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-warning text-white">
                <h5 class="modal-title font-weight-bold"><i class="fas fa-edit mr-2"></i>Edit Data Peserta / Panitia</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="id_peserta" id="edit_id">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold small">Nama Lengkap *</label>
                            <input type="text" name="nama_peserta" id="edit_nama" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold small">NIP</label>
                            <input type="text" name="nip" id="edit_nip" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold small">No Suket</label>
                            <input type="text" name="no_suket" id="edit_nosuket" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold small">Jabatan</label>
                            <input type="text" name="jabatan" id="edit_jabatan" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold small">Instansi</label>
                            <input type="text" name="instansi" id="edit_instansi" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold small">Unit Kerja</label>
                            <input type="text" name="unit_kerja" id="edit_unitkerja" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="font-weight-bold small">Metode Kehadiran</label>
                            <select name="metode_kehadiran" id="edit_metode" class="form-control form-control-sm">
                                <option value="Online">Online</option>
                                <option value="Offline">Offline</option>
                            </select>
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="font-weight-bold small">Absen Hari 1</label>
                            <select name="absen_1" id="edit_absen1" class="form-control form-control-sm">
                                <option value="1">Hadir (✔)</option>
                                <option value="0">Tidak Hadir (✖)</option>
                            </select>
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="font-weight-bold small">Absen Hari 2</label>
                            <select name="absen_2" id="edit_absen2" class="form-control form-control-sm">
                                <option value="1">Hadir (✔)</option>
                                <option value="0">Tidak Hadir (✖)</option>
                            </select>
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="font-weight-bold small">Nilai Pretest</label>
                            <input type="number" id="edit_pre" name="nilai_pretest" class="form-control form-control-sm" min="0" oninput="hitungTotalEdit()">
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="font-weight-bold small">Nilai Posttest</label>
                            <input type="number" id="edit_post" name="nilai_posttest" class="form-control form-control-sm" min="0" oninput="hitungTotalEdit()">
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="font-weight-bold small">Total Nilai</label>
                            <input type="number" id="edit_total" name="total_nilai" class="form-control form-control-sm bg-light" readonly>
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="font-weight-bold small">Status Sertifikat</label>
                            <select name="sertif" id="edit_sertif" class="form-control form-control-sm">
                                <option value="YA">YA (Tersedia)</option>
                                <option value="TIDAK">TIDAK (Belum Memenuhi)</option>
                            </select>
                        </div>
                        <!-- DROPDOWN PERAN / KETERANGAN PADA EDIT -->
                        <div class="col-md-8 form-group">
                            <label class="font-weight-bold small text-primary">Keterangan / Peran</label>
                            <select name="keterangan" id="edit_keterangan" class="form-control form-control-sm font-weight-bold" onchange="togglePeranFormEdit()">
                                <option value="">Peserta</option>
                                <option value="PANITIA">PANITIA</option>
                                <option value="MODERATOR">MODERATOR</option>
                                <option value="NARASUMBER">NARASUMBER</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                    <button type="submit" name="edit_peserta" class="btn btn-warning btn-sm font-weight-bold text-white"><i class="fas fa-save mr-1"></i> Update Data</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'template/footer.php'; ?>

<script>
$(document).ready(function() {
    var table = $('#tableBimtek').DataTable({
        "destroy": true,
        "paging": true,
        "pageLength": 10,
        "lengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Semua"]],
        "searching": true,
        "ordering": true,
        "info": true,
        "autoWidth": false,
        "language": {
            "search": "Cari Cepat:",
            "zeroRecords": "Tidak ada data yang cocok",
            "info": "Menampilkan _START_ sampai _END_ dari _TOTAL_ data"
        }
    });

    $('#filterMetode').on('change', function() { table.column(5).search(this.value ? '^' + this.value + '$' : '', true, false).draw(); });
    $('#filterAbsen1').on('change', function() { table.column(6).search(this.value ? '^' + this.value + '$' : '', true, false).draw(); });
    $('#filterAbsen2').on('change', function() { table.column(7).search(this.value ? '^' + this.value + '$' : '', true, false).draw(); });
    $('#filterSertif').on('change', function() { table.column(11).search(this.value ? '^' + this.value + '$' : '', true, false).draw(); });

    window.resetFilter = function() {
        $('#filterMetode, #filterAbsen1, #filterAbsen2, #filterSertif').val('');
        table.columns().search('').draw();
    };

    // OTOMATIS MAP DATA KE MODAL EDIT
    $(document).on('click', '.btn-edit', function() {
        $('#edit_id').val($(this).data('id'));
        $('#edit_nama').val($(this).data('nama'));
        $('#edit_nip').val($(this).data('nip'));
        $('#edit_nosuket').val($(this).data('nosuket'));
        $('#edit_jabatan').val($(this).data('jabatan'));
        $('#edit_instansi').val($(this).data('instansi'));
        $('#edit_unitkerja').val($(this).data('unitkerja'));
        $('#edit_metode').val($(this).data('metode'));
        $('#edit_absen1').val($(this).data('absen1'));
        $('#edit_absen2').val($(this).data('absen2'));
        $('#edit_pre').val($(this).data('pre'));
        $('#edit_post').val($(this).data('post'));
        $('#edit_sertif').val($(this).data('sertif'));

        // Penanganan dropdown keterangan peran di edit
        var ket = $(this).data('keterangan').toUpperCase();
        if (ket.includes('PANITIA')) {
            $('#edit_keterangan').val('PANITIA');
        } else if (ket.includes('MODERATOR')) {
            $('#edit_keterangan').val('MODERATOR');
        } else if (ket.includes('NARASUMBER')) {
            $('#edit_keterangan').val('NARASUMBER');
        } else {
            $('#edit_keterangan').val('');
        }

        hitungTotalEdit();
        $('#modalEditPeserta').modal('show');
    });
});

function togglePeranFormTambah() {
    if ($('#inputPeran').val() !== 'Peserta') {
        $('#inputPre, #inputPost, #inputTotal').val(0);
        $('#inputSertif').val('YA');
    }
}

function togglePeranFormEdit() {
    if ($('#edit_keterangan').val() !== '') {
        $('#edit_pre, #edit_post, #edit_total').val(0);
        $('#edit_sertif').val('YA');
    }
}

function hitungTotalTambah() {
    var pre = parseInt($('#inputPre').val()) || 0;
    var post = parseInt($('#inputPost').val()) || 0;
    $('#inputTotal').val(pre + post);
}

function hitungTotalEdit() {
    var pre = parseInt($('#edit_pre').val()) || 0;
    var post = parseInt($('#edit_post').val()) || 0;
    $('#edit_total').val(pre + post);
}

function downloadAllSuket() {
    if (confirm('Sistem akan mengompilasi seluruh Surat Keterangan peserta berstatus YA ke dalam ZIP. Lanjutkan?')) {
        window.location.href = 'download_all_suket.php';
    }
}
</script>