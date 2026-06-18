<?php
/**
 * CineGX — Oturum Kapatma
 * Session'ı tamamen temizler ve login sayfasına yönlendirir.
 */
require_once __DIR__ . '/includes/auth_helper.php';

logoutUser();

header('Location: login.php');
exit;
