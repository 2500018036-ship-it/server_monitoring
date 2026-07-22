<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="content-wrapper">
	<section class="content-header">
		<div class="container-fluid">
			<div class="row mb-2">
				<div class="col-sm-6"><h1><?php echo e($page_title); ?></h1></div>
			</div>
		</div>
	</section>

	<section class="content" id="fileManager">
		<div class="container-fluid">
			<?php if ( ! $phpseclib_available): ?>
				<div class="alert alert-danger">phpseclib belum tersedia. File Manager membutuhkan Composer autoload.</div>
			<?php endif; ?>
			<div class="card">
				<div class="card-header d-flex align-items-center">
					<h3 class="card-title"><i class="fas fa-folder-open mr-2"></i>Remote Files</h3>
					<div class="ml-auto">
						<button type="button" id="fileUploadBtn" class="btn btn-sm btn-outline-primary"><i class="fas fa-upload"></i></button>
						<button type="button" id="fileMkdirBtn" class="btn btn-sm btn-outline-success"><i class="fas fa-folder-plus"></i></button>
						<input type="file" id="fileUploadInput" class="d-none">
					</div>
				</div>
				<div class="card-body">
					<div class="row mb-3">
						<div class="col-md-4">
							<select id="fileSshConfig" class="form-control">
								<?php foreach ($configs as $config): ?>
									<option value="<?php echo (int) $config->id; ?>"><?php echo e($config->name); ?> (<?php echo e($config->host); ?>)</option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="col-md-5">
							<input type="text" id="fileCurrentPath" class="form-control" value="/">
						</div>
						<div class="col-md-3">
							<div class="input-group">
								<input type="text" id="fileSearch" class="form-control" placeholder="Search">
								<div class="input-group-append">
									<button id="fileSearchBtn" class="btn btn-outline-secondary"><i class="fas fa-search"></i></button>
								</div>
							</div>
						</div>
					</div>
					<div class="table-responsive">
						<table class="table table-bordered table-hover">
							<thead>
								<tr>
									<th>Name</th>
									<th>Permission</th>
									<th>Owner</th>
									<th>Size</th>
									<th>Last Modified</th>
									<th style="width: 210px;">Action</th>
								</tr>
							</thead>
							<tbody id="fileRows">
								<tr><td colspan="6" class="text-center text-muted">Pilih SSH config untuk browse folder.</td></tr>
							</tbody>
						</table>
					</div>
					<pre id="fileSearchResult" class="terminal-surface mt-3 d-none"></pre>
				</div>
			</div>
		</div>
	</section>
</div>

<div class="modal fade" id="fileEditorModal" tabindex="-1" role="dialog">
	<div class="modal-dialog modal-xl" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title"><i class="fas fa-code mr-2"></i><span id="fileEditorTitle">File Editor</span></h5>
				<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
			</div>
			<div class="modal-body">
				<textarea id="fileEditorContent"></textarea>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
				<button type="button" id="fileEditorSave" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Save</button>
			</div>
		</div>
	</div>
</div>
