<?php
require_once __DIR__ . '/functions.php';
unset($_SESSION['user']);
header('Location: index.php');
exit;
