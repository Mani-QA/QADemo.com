<?php
require_once 'includes/auth.php';

$auth = new Auth(null);
$auth->logout();

header('Location: index.php');
exit;
?> 