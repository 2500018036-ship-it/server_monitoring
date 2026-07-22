<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$columns = isset($result['columns']) ? $result['columns'] : array();
$rows = isset($result['rows']) ? $result['rows'] : array();
$total = isset($result['total']) ? (int) $result['total'] : 0;
$limit = isset($result['limit']) ? (int) $result['limit'] : 100;
$total_pages = max((int) ceil($total / max($limit, 1)), 1);
$base_query = 'database='.rawurlencode($database).'&table='.rawurlencode($table).'&ssh_config_id='.(int) ($selected_config ? $selected_config->id : 0).'&search='.rawurlencode($search);
?>
<div class="content-wrapper">
	<section class="content-header">
		<div class="container-fluid">
			<div class="row mb-2">
				<div class="col-sm-8"><h1>Data: <?php echo e($database.'.'.$table); ?></h1></div>
				<div class="col-sm-4 text-right">
					<a href="<?php echo site_url('database/tables?database='.rawurlencode($database).'&ssh_config_id='.(int) ($selected_config ? $selected_config->id : 0)); ?>" class="btn btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Tables</a>
				</div>
			</div>
		</div>
	</section>
	<section class="content">
		<div class="container-fluid">
			<div class="card">
				<div class="card-header"><h3 class="card-title"><i class="fas fa-eye mr-2"></i>Data Viewer</h3></div>
				<div class="card-body">
					<form method="get" action="<?php echo site_url('database/data'); ?>" class="mb-3">
						<input type="hidden" name="database" value="<?php echo e($database); ?>">
						<input type="hidden" name="table" value="<?php echo e($table); ?>">
						<input type="hidden" name="ssh_config_id" value="<?php echo (int) ($selected_config ? $selected_config->id : 0); ?>">
						<div class="input-group">
							<input type="text" name="search" class="form-control" value="<?php echo e($search); ?>" placeholder="Search data">
							<div class="input-group-append"><button class="btn btn-primary"><i class="fas fa-search"></i></button></div>
						</div>
					</form>
					<?php if (empty($result['ok'])): ?>
						<div class="alert alert-danger"><?php echo e(isset($result['message']) ? $result['message'] : 'Data tidak bisa dibaca.'); ?></div>
					<?php endif; ?>
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
					<div class="d-flex justify-content-between align-items-center mt-3">
						<div class="text-muted">Menampilkan <?php echo count($rows); ?> dari <?php echo $total; ?> record.</div>
						<div>
							<a class="btn btn-sm btn-outline-secondary <?php echo $page <= 1 ? 'disabled' : ''; ?>" href="<?php echo site_url('database/data?'.$base_query.'&page='.max($page - 1, 1)); ?>">Prev</a>
							<span class="mx-2">Page <?php echo $page; ?> / <?php echo $total_pages; ?></span>
							<a class="btn btn-sm btn-outline-secondary <?php echo $page >= $total_pages ? 'disabled' : ''; ?>" href="<?php echo site_url('database/data?'.$base_query.'&page='.min($page + 1, $total_pages)); ?>">Next</a>
						</div>
					</div>
				</div>
			</div>
		</div>
	</section>
</div>
