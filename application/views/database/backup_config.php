<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="content-wrapper">
	<section class="content-header">
		<div class="container-fluid">
			<div class="row mb-2">
				<div class="col-sm-6"><h1><?php echo e($page_title); ?></h1></div>
				<div class="col-sm-6 text-right"><a href="<?php echo site_url('database'); ?>" class="btn btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Database</a></div>
			</div>
		</div>
	</section>
	<section class="content">
		<div class="container-fluid">
			<div class="card">
				<div class="card-header"><h3 class="card-title"><i class="fas fa-cog mr-2"></i>Backup Configuration</h3></div>
				<div class="card-body">
					<?php echo form_open('database/update-backup-config'); ?>
						<div class="form-group">
							<label>Folder Penyimpanan Backup</label>
							<input type="text" name="storage_path" class="form-control" value="<?php echo e($settings->storage_path); ?>" required>
						</div>
						<div class="form-group">
							<label>Format Backup</label>
							<input type="text" class="form-control" value=".sql" readonly>
						</div>
						<div class="form-group">
							<div class="custom-control custom-switch">
								<input type="checkbox" name="compression_zip" value="1" class="custom-control-input" id="compressionZip" <?php echo (int) $settings->compression_zip === 1 ? 'checked' : ''; ?>>
								<label class="custom-control-label" for="compressionZip">Kompresi ZIP untuk Backup Manual</label>
							</div>
						</div>
						<div class="form-group">
							<label>Maksimal Jumlah Backup yang Disimpan</label>
							<input type="number" name="max_backups" class="form-control" min="1" value="<?php echo (int) $settings->max_backups; ?>" required>
						</div>
						<div class="form-group">
							<div class="custom-control custom-switch">
								<input type="checkbox" name="auto_delete_old" value="1" class="custom-control-input" id="autoDeleteOld" <?php echo (int) $settings->auto_delete_old === 1 ? 'checked' : ''; ?>>
								<label class="custom-control-label" for="autoDeleteOld">Auto Hapus Backup Lama</label>
							</div>
						</div>
						<button class="btn btn-primary"><i class="fas fa-save mr-1"></i> Simpan</button>
						<a href="<?php echo site_url('database'); ?>" class="btn btn-outline-secondary"><i class="fas fa-database mr-1"></i> Backup Manual</a>
					<?php echo form_close(); ?>
				</div>
			</div>
		</div>
	</section>
</div>
