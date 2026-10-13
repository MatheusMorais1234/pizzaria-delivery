<?php
session_start();
session_destroy();
header('Location: /pizzaria/admin/login.php');
exit;
