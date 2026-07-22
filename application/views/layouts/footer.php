<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
	<footer class="main-footer">
		<strong>&copy; <?php echo date('Y'); ?> <?php echo e(isset($app_setting->app_name) ? $app_setting->app_name : 'Server Monitoring'); ?>.</strong>
		<span class="float-right d-none d-sm-inline">Realtime Monitoring Engine</span>
	</footer>
</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net@1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-bs4@1.13.8/js/dataTables.bootstrap4.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-buttons@2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-buttons-bs4@2.4.2/js/buttons.bootstrap4.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jszip@3.10.1/dist/jszip.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/pdfmake@0.2.7/build/pdfmake.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/pdfmake@0.2.7/build/vfs_fonts.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-buttons@2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-buttons@2.4.2/js/buttons.print.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/xterm@5.3.0/lib/xterm.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/xterm-addon-fit@0.8.0/lib/xterm-addon-fit.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/codemirror@5.65.16/lib/codemirror.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/codemirror@5.65.16/mode/meta.min.js"></script>
<script>
	window.SM_CONFIG = {
		baseUrl: <?php echo json_encode(site_url()); ?>,
		pollInterval: <?php echo isset($poll_interval) ? (int) $poll_interval * 1000 : 3000; ?>
	};
	window.SM_CSRF = {
		name: <?php echo json_encode($this->security->get_csrf_token_name()); ?>,
		hash: <?php echo json_encode($this->security->get_csrf_hash()); ?>
	};
	window.SM_FLASH = {
		success: <?php echo json_encode($this->session->flashdata('success')); ?>,
		error: <?php echo json_encode($this->session->flashdata('error')); ?>
	};
</script>
<?php $app_js_version = file_exists(FCPATH.'assets/js/app.js') ? filemtime(FCPATH.'assets/js/app.js') : time(); ?>
<script src="<?php echo base_url('assets/js/app.js?v='.$app_js_version); ?>"></script>
</body>
</html>
