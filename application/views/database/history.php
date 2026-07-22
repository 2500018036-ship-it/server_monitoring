<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="content-wrapper">
	<section class="content-header">
		<div class="container-fluid">
			<div class="row mb-2">
				<div class="col-sm-6"><h1><?php echo e($page_title); ?></h1></div>
				<div class="col-sm-6 text-right">
					<a href="<?php echo site_url('database'); ?>" class="btn btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Database</a>
				</div>
			</div>
		</div>
	</section>
	<section class="content">
		<div class="container-fluid">
			<div class="card">
				<div class="card-header"><h3 class="card-title"><i class="fas fa-history mr-2"></i>Backup History</h3></div>
				<div class="card-body table-responsive">
					<table class="table table-bordered table-striped datatable datatable-export">
						<thead>
							<tr>
								<th>Nama File</th>
								<th>Database</th>
								<th>Ukuran</th>
								<th>Dibuat Oleh</th>
								<th>Tanggal</th>
								<th>Status</th>
								<th>Action</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($history as $row): ?>
								<tr>
									<td><?php echo e($row->file_name ?: '-'); ?></td>
									<td><?php echo e($row->database_name ?: 'all-databases'); ?></td>
									<td><?php echo $row->file_size_bytes ? e(number_format($row->file_size_bytes / 1024 / 1024, 2)).' MB' : '-'; ?></td>
									<td><?php echo e($row->fullname ?: $row->username ?: '-'); ?></td>
									<td><?php echo e($row->completed_at ?: $row->created_at); ?></td>
									<td>
										<span class="badge badge-<?php echo $row->status === 'success' ? 'success' : ($row->status === 'failed' ? 'danger' : 'warning'); ?>">
											<?php echo e(ucfirst($row->status)); ?>
										</span>
									</td>
									<td class="text-nowrap">
										<?php if ($row->status === 'success' && $row->local_path): ?>
											<a href="<?php echo site_url('database/download/'.$row->id); ?>" class="btn btn-sm btn-primary" title="Download"><i class="fas fa-download"></i></a>
											<?php echo form_open('database/restore/'.$row->id, array('class' => 'd-inline confirm-form', 'data-confirm' => 'Restore backup ini? Data database dapat berubah.')); ?>
												<button class="btn btn-sm btn-warning" title="Restore"><i class="fas fa-undo"></i></button>
											<?php echo form_close(); ?>
										<?php endif; ?>
										<?php echo form_open('database/delete-backup/'.$row->id, array('class' => 'd-inline confirm-form', 'data-confirm' => 'Hapus backup ini?')); ?>
											<button class="btn btn-sm btn-danger" title="Delete"><i class="fas fa-trash"></i></button>
										<?php echo form_close(); ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</section>
</div>
