<?php
// inc/db.php
require_once __DIR__ . '/../config.php';

/**
 * Devolve uma ligação PDO (singleton) à base de dados.
 */
function db(): PDO {
  static $pdo = null;

  if ($pdo instanceof PDO) {
    return $pdo;
  }

  // Se no config.php já tens DB_HOST/DB_NAME/DB_USER/DB_PASS, usa isso.
  // Caso não existam (por alguma razão), cai para defaults típicos de XAMPP.
  $host = defined('DB_HOST') ? DB_HOST : '127.0.0.1';
  $name = defined('DB_NAME') ? DB_NAME : 'sonia_cabeleireiro';
  $user = defined('DB_USER') ? DB_USER : 'root';
  $pass = defined('DB_PASS') ? DB_PASS : '';

  $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";

  $options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ];

  $pdo = new PDO($dsn, $user, $pass, $options);

  // Compatibilidade: se algum ficheiro antigo usa $pdo diretamente,
  // isto garante que a variável $pdo também fica disponível.
  $GLOBALS['pdo'] = $pdo;

  return $pdo;
}

// Opcional: cria logo $pdo ao incluir o ficheiro (para código antigo).
// Se não quiseres, podes remover estas 2 linhas.
$pdo = db();