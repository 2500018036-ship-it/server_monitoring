<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="content-wrapper">
	<section class="content-header">
		<div class="container-fluid">
			<div class="row mb-2">
				<div class="col-sm-6"><h1><?php echo e($page_title); ?></h1></div>
			</div>
		</div>
	</section>

	<section class="content" id="terminalManager">
		<div class="container-fluid">
			<?php if ( ! $phpseclib_available): ?>
				<div class="alert alert-danger">phpseclib belum tersedia. Terminal membutuhkan Composer autoload.</div>
			<?php endif; ?>
			<div class="row">
				<div class="col-lg-3">
					<div class="card">
						<div class="card-header"><h3 class="card-title"><i class="fas fa-lock mr-2"></i>SSH Session</h3></div>
						<div class="card-body">
							<div class="form-group">
								<label>SSH Config</label>
								<select id="terminalSshConfig" class="form-control">
									<?php foreach ($configs as $config): ?>
										<option value="<?php echo (int) $config->id; ?>"><?php echo e($config->name); ?> (<?php echo e($config->host); ?>)</option>
									<?php endforeach; ?>
								</select>
							</div>
							<a href="<?php echo site_url('ssh-config'); ?>" class="btn btn-outline-primary btn-block">
								<i class="fas fa-key mr-1"></i> SSH Config
							</a>
						</div>
					</div>
				</div>
				<div class="col-lg-9">
					<div class="card">
						<div class="card-header d-flex align-items-center">
							<h3 class="card-title"><i class="fas fa-terminal mr-2"></i>Web Terminal</h3>
							<div class="ml-auto">
								<button type="button" id="terminalCopy" class="btn btn-sm btn-outline-secondary"><i class="fas fa-copy"></i></button>
								<button type="button" id="terminalPaste" class="btn btn-sm btn-outline-secondary"><i class="fas fa-paste"></i></button>
								<button type="button" id="terminalClear" class="btn btn-sm btn-outline-secondary"><i class="fas fa-eraser"></i></button>
								<button type="button" id="terminalFullscreen" class="btn btn-sm btn-outline-secondary"><i class="fas fa-expand"></i></button>
							</div>
						</div>
						<div class="card-body">
							<div id="xtermContainer" class="xterm-shell"></div>
							<div class="input-group mt-3">
								<input type="text" id="terminalCommand" class="form-control" placeholder="Type command and press Enter">
								<div class="input-group-append">
									<button type="button" id="terminalRun" class="btn btn-primary"><i class="fas fa-play mr-1"></i> Run</button>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</section>
</div>
