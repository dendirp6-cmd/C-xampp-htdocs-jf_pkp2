<?php
// FILE: template/footer.php - Bagian Footer dan Skrip Penutup AdminLTE
?>

  <!-- Main Footer -->
  <footer class="main-footer">
    <strong>Copyright &copy; <?php echo date('Y'); ?> <a href="#">Instansi Pembina JF</a>.</strong>
    All rights reserved.
    <div class="float-right d-none d-sm-inline">
      <b>Version</b> 3.2.0
    </div>
  </footer>

</div>
<!-- ./wrapper -->

<!-- ========================================== -->
<!-- DAFTAR SCRIPT JS (Hanya menggunakan CDN & 1 Versi jQuery) -->
<!-- ========================================== -->

<!-- 1. jQuery (Hanya 1 sumber utama di atas) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- 2. Bootstrap 4 Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- 3. AdminLTE App -->
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>

<!-- 4. DataTables & Extensions JS -->
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap4.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.2.9/js/responsive.bootstrap4.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.bootstrap4.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.print.min.js"></script>

<!-- 5. Fix tambahan dropdown -->
<script>
$(document).ready(function () {
    $('.dropdown-toggle').dropdown();
});
</script>

</body>
</html>