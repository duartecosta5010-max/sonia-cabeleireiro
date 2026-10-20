<?php
// inc/session.php

// Garante que tens BASE_URL/ROOT_PATH
if (!defined('ROOT_PATH')) {
  require_once __DIR__ . '/../config.php';
}

/**
 * Quanto tempo queres manter o login depois de fechar o browser?
 * (ex.: 30 dias)
 */
$days = 30;
$lifetime = 60 * 60 * 24 * $days;

// Evita conflitos com outras apps no localhost
session_name('SONIASESSID');

// Reforços e tempo de vida
ini_set('session.use_strict_mode', '1');
ini_set('session.gc_maxlifetime', (string)$lifetime);
ini_set('session.cookie_lifetime', (string)$lifetime);

// Cookie da sessão: faz persistir mesmo após fechar o browser
$cookieParams = session_get_cookie_params();

// IMPORTANTE: path no teu projeto (subpasta /soniacabeleireiro)
$path = '/';
if (defined('BASE_URL') && is_string(BASE_URL) && BASE_URL !== '') {
  $path = rtrim(BASE_URL, '/') . '/';
}

$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

session_set_cookie_params([
  'lifetime' => $lifetime,
  'path'     => $path,
  'domain'   => $cookieParams['domain'] ?? '',
  'secure'   => $secure,
  'httponly' => true,
  'samesite' => 'Lax',
]);

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

// Regenera o ID uma vez por sessão (boa prática)
if (!isset($_SESSION['__initiated'])) {
  session_regenerate_id(true);
  $_SESSION['__initiated'] = time();
}