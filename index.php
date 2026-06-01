<?php
require __DIR__ . '/middleware.php';
if (!empty($_SESSION['user'])) { header('Location: /dashboard.php'); exit; }
header('Location: /auth/login.php');