<?php
$bodyClass = page_body_class();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h(document_title()) ?></title>
    <link href="/assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/vendor/fontawesome/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/style.css">
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/img/favicon_io/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/img/favicon_io/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/img/favicon_io/apple-touch-icon.png">
    <link rel="manifest" href="/assets/img/favicon_io/site.webmanifest">
    <link rel="shortcut icon" href="/assets/img/favicon_io/favicon.ico">
    <meta name="theme-color" content="#ffffff">
</head>
<body<?= $bodyClass !== '' ? ' class="' . h($bodyClass) . '"' : '' ?>>
