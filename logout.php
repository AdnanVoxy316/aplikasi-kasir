<?php
require_once __DIR__ . '/config/auth.php';

logoutCashierSession();
header('Location: /aplikasi-kasir-copy/auth/login/login_cashier.php');
exit;
