<?php
require_once __DIR__ . '/../includes/base.php';
session_start();
session_destroy();
header('Location: ' . BASE . '/admin/login.php');
exit;
