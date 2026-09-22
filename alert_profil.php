<?php if (isset($profileStatus) && $profileStatus['needs_update']): ?>
    <div class="alert alert-warning alert-dismissible shadow-sm border-0 mb-4" style="border-radius: 12px; background: #fff3cd; color: #856404;">
        <div class="d-flex align-items-center">
            <div class="mr-3"><i class="fas fa-exclamation-triangle fa-2x"></i></div>
            <div>
                <h5 class="mb-1 font-weight-bold">Perhatian: Profil Anda Belum Lengkap!</h5>
                <p class="mb-2">Halo <strong><?= htmlspecialchars($user_nama); ?></strong>, data NIP Anda masih kosong. Mohon lengkapi profil Anda sekarang.</p>
                <button type="button" class="btn btn-warning btn-sm font-weight-bold shadow-sm" style="border-radius: 8px;" data-toggle="modal" data-target="#modalUpdateProfil">
                    <i class="fas fa-user-edit mr-1"></i> Lengkapi Data Profil Sekarang
                </button>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalUpdateProfil" data-backdrop="static" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content shadow-lg" style="border-radius: 20px; border: none;">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title font-weight-bold"><i class="fas fa-id-card-alt mr-2"></i> Update Profil</h5>
                </div>
                <div class="modal-body p-4">
                    <form id="formGlobalUpdate">
                        <input type="hidden" id="update_uid" value="<?= $session_user_id; ?>">
                        <input type="hidden" id="update_instansi" value="<?= htmlspecialchars($instansi); ?>">
                        
                        <div class="form-group mb-4">
                            <label class="font-weight-bold">NIP (18 Digit)</label>
                            <input type="text" id="update_nip" class="form-control" 
                                   value="<?= ($user_nip != '-') ? htmlspecialchars($user_nip) : ''; ?>" 
                                   required maxlength="18" placeholder="Masukkan 18 digit NIP">
                            <small class="text-muted">Pastikan NIP yang Anda masukkan benar.</small>
                        </div>
                        
                        <button type="submit" id="btnSimpanProfil" class="btn btn-primary btn-block font-weight-bold py-2 shadow" style="border-radius:10px;">
                            <i class="fas fa-save mr-1"></i> Simpan & Perbarui
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
    $(document).ready(function() {
        // Munculkan modal otomatis
        $('#modalUpdateProfil').modal('show');

        $('#formGlobalUpdate').on('submit', function(e) {
            e.preventDefault();
            
            const btn = $('#btnSimpanProfil');
            const originalText = btn.html();
            
            btn.html('<i class="fas fa-spinner fa-spin"></i> Memproses...').attr('disabled', true);

            $.ajax({
                url: 'update_data.php',
                type: 'POST',
                data: {
                    action: 'update_profile',
                    id: $('#update_uid').val(),
                    nip: $('#update_nip').val(),
                    instansi: $('#update_instansi').val() // Mengirim nilai instansi yang sudah ada
                },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        alert(response.message);
                        location.reload();
                    } else {
                        alert('Gagal: ' + response.message);
                        btn.html(originalText).attr('disabled', false);
                    }
                },
                error: function() {
                    alert('Terjadi kesalahan pada server. Pastikan update_data.php sudah benar.');
                    btn.html(originalText).attr('disabled', false);
                }
            });
        });
    });
    </script>
<?php endif; ?>