<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$servers = isset($monitoring['servers']) ? $monitoring['servers'] : array();
$selected_server_id = isset($monitoring['selected_server_id']) ? (int) $monitoring['selected_server_id'] : 0;
?>
<div class="content-wrapper">
	<section class="content-header">
		<div class="container-fluid">
			<div class="row mb-2 align-items-center">
				<div class="col-sm-6"><h1><?php echo e($page_title); ?></h1></div>
				<div class="col-sm-6 text-sm-right">
					<a href="<?php echo site_url('logs/activity'); ?>" class="btn btn-outline-secondary">
						<i class="fas fa-history mr-1"></i> Activity Logs
					</a>
				</div>
			</div>
		</div>
	</section>

	<section class="content" id="logViewer">
		<div class="container-fluid">
			<div class="card">
				<div class="card-header">
					<h3 class="card-title"><i class="fas fa-stream mr-2"></i>Realtime Server Logs</h3>
				</div>
				<div class="card-body">
					<div class="row">
						<div class="col-md-3">
							<div class="form-group">
								<label>Server</label>
								<select id="logServerSelector" class="form-control">
									<?php foreach ($servers as $server): ?>
										<option value="<?php echo (int) $server->id; ?>" <?php echo (int) $server->id === $selected_server_id ? 'selected' : ''; ?>>
											<?php echo e($server->server_name); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</div>
						</div>
						<div class="col-md-2">
							<div class="form-group">
								<label>Level</label>
								<select id="logLevelFilter" class="form-control">
									<option value="">All</option>
									<option value="debug">Debug</option>
									<option value="info">Info</option>
									<option value="warning">Warning</option>
									<option value="error">Error</option>
									<option value="critical">Critical</option>
								</select>
							</div>
						</div>
						<div class="col-md-2">
							<div class="form-group">
								<label>Type</label>
								<select id="logTypeFilter" class="form-control">
									<option value="">All</option>
									<option value="system">System</option>
									<option value="journalctl">Journalctl</option>
									<option value="nginx">Nginx</option>
									<option value="apache">Apache</option>
									<option value="php">PHP</option>
									<option value="mysql">MySQL</option>
									<option value="docker">Docker</option>
									<option value="ssh">SSH</option>
									<option value="kernel">Kernel</option>
									<option value="cron">Cron</option>
									<option value="firewall">Firewall</option>
								</select>
							</div>
						</div>
						<div class="col-md-2">
							<div class="form-group">
								<label>Date</label>
								<input type="date" id="logDateFilter" class="form-control">
							</div>
						</div>
						<div class="col-md-3">
							<div class="form-group">
								<label>Search</label>
								<input type="text" id="logSearch" class="form-control" placeholder="Search log message">
							</div>
						</div>
					</div>

					<div class="d-flex align-items-center mb-3">
						<div class="custom-control custom-switch mr-3">
							<input type="checkbox" class="custom-control-input" id="autoScrollLogs" checked>
							<label class="custom-control-label" for="autoScrollLogs">Auto Scroll</label>
						</div>
						<button type="button" id="copyLogs" class="btn btn-sm btn-outline-primary mr-2">
							<i class="fas fa-copy mr-1"></i> Copy
						</button>
						<button type="button" id="downloadLogs" class="btn btn-sm btn-outline-success">
							<i class="fas fa-download mr-1"></i> Download
						</button>
					</div>

					<div class="table-responsive log-viewer-panel">
						<table class="table table-sm table-striped realtime-table">
							<thead>
								<tr>
									<th>Time</th>
									<th>Type</th>
									<th>Level</th>
									<th>Source</th>
									<th>Message</th>
								</tr>
							</thead>
							<tbody id="logViewerRows">
								<tr><td colspan="5" class="text-center text-muted">Menunggu data log dari agent.</td></tr>
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>
	</section>
</div>
