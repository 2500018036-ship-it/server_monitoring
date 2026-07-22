<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$servers = $monitoring['servers'];
$server = $monitoring['server'];
$websites = isset($monitoring['payload']['websites']) ? $monitoring['payload']['websites'] : array();
?>
<div class="content-wrapper">
	<section class="content-header">
		<div class="container-fluid">
			<div class="row mb-2 align-items-center">
				<div class="col-sm-6"><h1><?php echo e($page_title); ?></h1></div>
				<div class="col-sm-6">
					<select class="form-control realtime-server-selector" onchange="location.href='<?php echo site_url('website?server_id='); ?>' + this.value">
						<?php foreach ($servers as $item): ?>
							<option value="<?php echo (int) $item->id; ?>" <?php echo $server && (int) $server->id === (int) $item->id ? 'selected' : ''; ?>><?php echo e($item->server_name); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>
		</div>
	</section>
	<section class="content">
		<div class="container-fluid">
			<div class="card">
				<div class="card-header"><h3 class="card-title"><i class="fas fa-globe mr-2"></i>Website Monitoring</h3></div>
				<div class="card-body table-responsive">
					<table class="table table-bordered table-hover datatable datatable-export">
						<thead><tr><th>Domain</th><th>Status</th><th>HTTP Status</th><th>Response Time</th><th>SSL Expired</th><th>Ping</th><th>DNS Resolve</th><th>Last Check</th></tr></thead>
						<tbody>
							<?php foreach ($websites as $site): ?>
								<tr class="<?php echo isset($site['status']) && $site['status'] === 'offline' ? 'table-danger' : ''; ?>">
									<td><?php echo e(isset($site['domain']) ? $site['domain'] : '-'); ?></td>
									<td><span class="badge badge-<?php echo isset($site['status']) && $site['status'] === 'online' ? 'success' : 'danger'; ?>"><?php echo e(isset($site['status']) ? $site['status'] : 'unknown'); ?></span></td>
									<td><?php echo e(isset($site['http_status']) ? $site['http_status'] : '-'); ?></td>
									<td><?php echo e(isset($site['response_time_ms']) ? $site['response_time_ms'].' ms' : '-'); ?></td>
									<td><?php echo e(isset($site['ssl_expired_at']) ? $site['ssl_expired_at'] : '-'); ?></td>
									<td><?php echo e(isset($site['ping_ms']) ? $site['ping_ms'].' ms' : '-'); ?></td>
									<td><?php echo e(isset($site['dns_resolve_ms']) ? $site['dns_resolve_ms'].' ms' : '-'); ?></td>
									<td><?php echo e(isset($site['last_check']) ? $site['last_check'] : '-'); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</section>
</div>
