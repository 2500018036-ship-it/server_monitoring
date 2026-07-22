<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="content-wrapper">
	<section class="content-header">
		<div class="container-fluid">
			<div class="row mb-2">
				<div class="col-sm-6">
					<h1><?php echo e($page_title); ?></h1>
				</div>
			</div>
		</div>
	</section>

	<section class="content">
		<div class="container-fluid">
			<div class="card">
				<div class="card-body empty-state">
					<i class="<?php echo e($icon); ?>"></i>
					<h3><?php echo e($page_title); ?></h3>
					<p><?php echo e($description); ?></p>
				</div>
			</div>
		</div>
	</section>
</div>
