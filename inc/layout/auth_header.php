<?php
$existingBodyClass = page_body_class();
$GLOBALS['page_body_class'] = trim('bg-light ' . $existingBodyClass);
$authTitle = page_heading();
$authSubtitle = page_subtitle();

require __DIR__ . '/document_start.php';
?>
<main class="container auth-page">
    <div class="card auth-card shadow mx-auto">
        <div class="card-body">
            <div class="auth-brand text-center pb-3">
                <img src="/assets/img/favicon_io/android-chrome-512x512.png" alt="HourWise Logo" class="auth-logo img-fluid">
                <h1 class="auth-title">HourWise</h1>
                <?php if ($authSubtitle !== ''): ?>
                    <p class="auth-subtitle text-muted px-2"><?= h($authSubtitle) ?></p>
                <?php endif; ?>
            </div>

            <?php if ($authTitle !== ''): ?>
                <h2 class="card-title auth-form-title"><?= h($authTitle) ?></h2>
            <?php endif; ?>
