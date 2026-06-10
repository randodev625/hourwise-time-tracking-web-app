<?php
require __DIR__ . '/middleware.php';
require_login();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Complete - HourWise</title>
    <link href="/assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body class="bg-light">
<div class="container py-5" style="max-width: 760px;">
    <div class="card shadow-sm p-4">
        <h1 class="h3 mb-3">Setup Complete</h1>
        <p class="mb-4">
            Your HourWise installation is ready, and your admin account has been created successfully.
        </p>
        <a href="<?= h(route_url('dashboard')) ?>" class="btn btn-primary">Continue to Dashboard</a>
    </div>
</div>
</body>
</html>
