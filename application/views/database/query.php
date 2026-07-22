<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$columns = isset($result['columns']) ? $result['columns'] : array();
$rows = isset($result['rows']) ? $result['rows'] : array();
?>
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
				<div class="card-header"><h3 class="card-title"><i class="fas fa-code mr-2"></i>SQL Query Viewer</h3></div>
				<div class="card-body">
					<?php echo form_open('database/query'); ?>
						<div class="row mb-3">
							<div class="col-md-4">
								<select name="ssh_config_id" class="form-control" required>
									<?php foreach ($configs as $config): ?>
										<option value="<?php echo (int) $config->id; ?>" <?php echo $selected_config && (int) $selected_config->id === (int) $config->id ? 'selected' : ''; ?>><?php echo e($config->name); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
							<div class="col-md-4"><input type="text" name="database" class="form-control" value="<?php echo e($database); ?>" placeholder="Database optional"></div>
							<div class="col-md-4"><button class="btn btn-primary btn-block"><i class="fas fa-play mr-1"></i> Run Query</button></div>
						</div>
						<textarea name="sql_query" class="form-control" rows="7" placeholder="SELECT * FROM users;"><?php echo e($sql_query); ?></textarea>
					<?php echo form_close(); ?>
				</div>
			</div>
			<?php if ($result !== NULL): ?>
				<div class="card">
					<div class="card-header"><h3 class="card-title"><i class="fas fa-terminal mr-2"></i>Query Result</h3></div>
					<div class="card-body">
						<?php if (empty($result['ok'])): ?>
							<div class="alert alert-danger"><?php echo e(isset($result['message']) ? $result['message'] : 'Query gagal.'); ?></div>
						<?php elseif (! empty($columns)): ?>
							<div class="table-responsive">
								<table class="table table-bordered table-striped datatable datatable-export">
									<thead><tr><?php foreach ($columns as $column): ?><th><?php echo e($column); ?></th><?php endforeach; ?></tr></thead>
									<tbody>
										<?php foreach ($rows as $row): ?>
											<tr><?php foreach ($columns as $column): ?><td><?php echo e(isset($row[$column]) ? $row[$column] : ''); ?></td><?php endforeach; ?></tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							</div>
						<?php else: ?>
							<div class="alert alert-success"><?php echo e(isset($result['affected_message']) ? $result['affected_message'] : 'Query berhasil.'); ?></div>
							<?php if (! empty($result['output'])): ?><pre class="mb-0"><?php echo e($result['output']); ?></pre><?php endif; ?>
						<?php endif; ?>
					</div>
				</div>
			<?php endif; ?>
		</div>
	</section>
</div>
