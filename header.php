<?php
$isManageActive = route_is(['clients', 'client_edit', 'projects', 'project_edit', 'categories', 'category_edit']);
$user = current_user();
$avatarUrl = avatar_url($user['avatar_path'] ?? '');
$displayName = user_display_name($user);
$initials = user_initials($user);
$isAccountActive = route_is('account');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? htmlspecialchars($page_title) . ' – James June HourWise' : 'HourWise' ?></title>

    <!-- Bootstrap CSS (self-hosted) -->
    <link href="/assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome (self-hosted) -->
    <link href="/assets/vendor/fontawesome/css/all.min.css" rel="stylesheet">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="/assets/style.css">

    <link rel="icon" type="image/png" sizes="32x32" href="/assets/img/favicon_io/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/img/favicon_io/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/img/favicon_io/apple-touch-icon.png">
    <link rel="manifest" href="/assets/img/favicon_io/site.webmanifest">
    <link rel="shortcut icon" href="/assets/img/favicon_io/favicon.ico">
    <meta name="theme-color" content="#ffffff">
</head>
<body class="container pt-5">
    <nav class="navbar navbar-expand-lg navbar-light mb-5">
        <div class="container-fluid">
            <a class="navbar-brand me-3" href="<?= h(route_url('dashboard')) ?>">
                <img src="/assets/img/hourwise-logo.jpg" alt="James June Media Logo">
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-lg-center">
                    <li class="nav-item me-3">
                        <a class="nav-link <?= route_is('dashboard') ? 'active' : '' ?>" href="<?= h(route_url('dashboard')) ?>">Dashboard</a>
                    </li>
                    <li class="nav-item me-3">
                        <a class="nav-link <?= route_is('track') ? 'active' : '' ?>" href="<?= h(route_url('track')) ?>">Track Time</a>
                    </li>
                    <li class="nav-item me-3">
                        <a class="nav-link <?= route_is(['entries', 'entry_edit']) ? 'active' : '' ?>" href="<?= h(route_url('entries')) ?>">Entries</a>
                    </li>
                    <li class="nav-item dropdown me-3">
                        <a class="nav-link dropdown-toggle <?= $isManageActive ? 'active' : '' ?>" href="#" id="manageDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Manage
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="manageDropdown">
                            <li><a class="dropdown-item <?= route_is(['clients', 'client_edit']) ? 'active' : '' ?>" href="<?= h(route_url('clients')) ?>">Clients</a></li>
                            <li><a class="dropdown-item <?= route_is(['projects', 'project_edit']) ? 'active' : '' ?>" href="<?= h(route_url('projects')) ?>">Projects</a></li>
                            <li><a class="dropdown-item <?= route_is(['categories', 'category_edit']) ? 'active' : '' ?>" href="<?= h(route_url('categories')) ?>">Project Categories</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle account-toggle <?= $isAccountActive ? 'active' : '' ?>" href="#" id="accountDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <?php if ($avatarUrl): ?>
                                <img src="<?= h($avatarUrl) ?>" alt="<?= h($displayName) ?>" class="nav-avatar-img">
                            <?php else: ?>
                                <span class="nav-avatar-placeholder"><?= h($initials) ?></span>
                            <?php endif; ?>
                            <span class="d-none d-lg-inline ms-2"><?= h($displayName) ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="accountDropdown">
                            <li><a class="dropdown-item <?= $isAccountActive ? 'active' : '' ?>" href="<?= h(route_url('account')) ?>">Manage Account</a></li>
                            <?php if (is_admin_user()): ?>
                                <li><a class="dropdown-item <?= route_is('admin_settings') ? 'active' : '' ?>" href="<?= h(route_url('admin_settings')) ?>">Admin Settings</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?= h(route_url('logout')) ?>">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <script src="/assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
