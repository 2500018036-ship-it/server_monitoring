<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="content-wrapper">
	<section class="content-header">
		<div class="container-fluid">
			<div class="row mb-2 align-items-center">
				<div class="col-sm-6"><h1><?php echo e($page_title); ?></h1></div>
				<div class="col-sm-6 text-sm-right">
					<button class="btn btn-primary" data-toggle="modal" data-target="#modalCreateSsh">
						<i class="fas fa-plus mr-1"></i> Add SSH Config
					</button>
				</div>
			</div>
		</div>
	</section>

	<section class="content">
		<div class="container-fluid">
			<?php if ( ! $phpseclib_available): ?>
				<div class="alert alert-danger">phpseclib belum tersedia. Jalankan Composer install/update terlebih dahulu.</div>
			<?php endif; ?>
			<div class="card">
				<div class="card-header"><h3 class="card-title"><i class="fas fa-key mr-2"></i>SSH Configurations</h3></div>
				<div class="card-body table-responsive">
					<table class="table table-bordered table-hover datatable datatable-export">
						<thead>
							<tr>
								<th>Name</th>
								<th>Server</th>
								<th>Host</th>
								<th>Port</th>
								<th>Username</th>
								<th>Auth</th>
								<th>Status</th>
								<th>Last Connected</th>
								<th style="width: 230px;">Action</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($configs as $config): ?>
								<tr>
									<td><?php echo e($config->name); ?></td>
									<td><?php echo $config->server_name ? e($config->server_name) : '-'; ?></td>
									<td><?php echo e($config->host); ?></td>
									<td><?php echo (int) $config->port; ?></td>
									<td><?php echo e($config->username); ?></td>
									<td><span class="badge badge-info"><?php echo e($config->auth_type); ?></span></td>
									<td><span class="badge badge-<?php echo $config->status === 'active' ? 'success' : 'secondary'; ?>"><?php echo e($config->status); ?></span></td>
									<td><?php echo $config->last_connected_at ? e($config->last_connected_at) : '-'; ?></td>
									<td>
										<?php echo form_open('ssh_config/test/'.$config->id, array('class' => 'd-inline')); ?>
											<button class="btn btn-sm btn-success" title="Test"><i class="fas fa-plug"></i></button>
										<?php echo form_close(); ?>
										<?php echo form_open('ssh-config/install-agent/'.$config->id, array('class' => 'd-inline confirm-form', 'data-confirm' => 'Install atau repair realtime agent di VPS ini?')); ?>
											<button class="btn btn-sm btn-warning" title="Install/Repair Agent"><i class="fas fa-satellite-dish"></i></button>
										<?php echo form_close(); ?>
										<button class="btn btn-sm btn-info" data-toggle="modal" data-target="#modalEditSsh<?php echo (int) $config->id; ?>" title="Edit"><i class="fas fa-edit"></i></button>
										<?php echo form_open('ssh_config/delete/'.$config->id, array('class' => 'd-inline confirm-form', 'data-confirm' => 'Delete SSH config ini?')); ?>
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

<div class="modal fade" id="modalCreateSsh" tabindex="-1" role="dialog">
	<div class="modal-dialog modal-lg" role="document">
		<?php echo form_open('ssh_config/store'); ?>
			<?php $this->load->view('ssh_config/form', array('config' => NULL, 'servers' => $servers)); ?>
		<?php echo form_close(); ?>
	</div>
</div>

<?php foreach ($configs as $config): ?>
	<div class="modal fade" id="modalEditSsh<?php echo (int) $config->id; ?>" tabindex="-1" role="dialog">
		<div class="modal-dialog modal-lg" role="document">
			<?php echo form_open('ssh_config/update/'.$config->id); ?>
				<?php $this->load->view('ssh_config/form', array('config' => $config, 'servers' => $servers)); ?>
			<?php echo form_close(); ?>
		</div>
	</div>
<?php endforeach; ?>
