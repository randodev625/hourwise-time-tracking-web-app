<?php
require __DIR__ . '/../../core/middleware.php';
require_login();

render_layout_header('auth');
?>
<p class="mb-4">
    Your HourWise installation is ready, and your admin account has been created successfully.
</p>
<a href="<?= h(route_url('dashboard')) ?>" class="btn btn-primary">Continue to Dashboard</a>
<?php render_layout_footer('auth'); ?>
