<?php
$existingBodyClass = page_body_class();
$GLOBALS['page_body_class'] = trim('bg-light ' . $existingBodyClass);
$errorTitle = page_heading();
$errorMessage = page_message();

require __DIR__ . '/document_start.php';
?>
<main class="container auth-page">
    <div class="card auth-card shadow mx-auto">
        <div class="card-body text-center">
            <div class="auth-brand pb-3">
                <img src="/assets/img/favicon_io/android-chrome-512x512.png" alt="HourWise Logo" class="auth-logo img-fluid">
                <?php if ($errorTitle !== ''): ?>
                    <h1 class="auth-title"><?= h($errorTitle) ?></h1>
                <?php endif; ?>
                <?php if ($errorMessage !== ''): ?>
                    <p class="auth-subtitle text-muted px-2"><?= h($errorMessage) ?></p>
                <?php endif; ?>
            </div>
