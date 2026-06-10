<?php
$existingBodyClass = page_body_class();
$GLOBALS['page_body_class'] = trim('setup-page ' . $existingBodyClass);

require __DIR__ . '/document_start.php';
?>
<main class="container py-5" style="max-width: 860px;">
    <div class="setup-brand-hero mb-4 text-center">
        <img src="/assets/img/hourwise-logo.jpg" alt="HourWise by Jim Kulakowski" class="setup-brand-logo img-fluid">
    </div>
