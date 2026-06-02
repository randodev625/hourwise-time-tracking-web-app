<?php
http_response_code(403);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Denied - HourWise</title>
    <link href="/assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/style.css">

    <link rel="icon" type="image/png" sizes="32x32" href="/assets/img/favicon_io/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/img/favicon_io/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/img/favicon_io/apple-touch-icon.png">
    <link rel="manifest" href="/assets/img/favicon_io/site.webmanifest">
    <link rel="shortcut icon" href="/assets/img/favicon_io/favicon.ico">
    <meta name="theme-color" content="#ffffff">
</head>
<body>
    <main class="container auth-page">
        <div class="card auth-card shadow mx-auto">
            <div class="card-body text-center">
                <div class="auth-brand pb-3">
                    <img src="/assets/img/favicon_io/android-chrome-512x512.png" alt="HourWise Logo" class="auth-logo img-fluid">
                    <h1 class="auth-title">Access Denied</h1>
                    <p class="auth-subtitle text-muted px-2">
                        You do not have permission to access this page.
                    </p>
                </div>

                <a href="/" class="btn btn-primary">Return to HourWise</a>
            </div>
        </div>
    </main>
</body>
</html>
