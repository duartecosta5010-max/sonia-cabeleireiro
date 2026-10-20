<?php
require_once __DIR__ . '/../config.php';
require_once ROOT_PATH . '/inc/db.php';
require_once ROOT_PATH . '/inc/session.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header("Location: " . BASE_URL . "/index.php");
  exit;
}

$nome = trim($_POST['nome'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$redirect = $_POST['redirect'] ?? (BASE_URL . '/index.php');

if (!str_starts_with($redirect, BASE_URL)) {
  $redirect = BASE_URL . '/index.php';
}

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6) {
  header("Location: " . $redirect . (str_contains($redirect, '?') ? '&' : '?') . "auth=register_error");
  exit;
}

try {
  $hash = password_hash($password, PASSWORD_DEFAULT);

  $stmt = $pdo->prepare("INSERT INTO clients (email, nome, password_hash) VALUES (:e, :n, :h)");
  $stmt->execute([
    ':e' => $email,
    ':n' => ($nome === '' ? null : $nome),
    ':h' => $hash
  ]);

  $_SESSION['client_id'] = (int)$pdo->lastInsertId();
  $_SESSION['client_email'] = $email;
  $_SESSION['client_nome'] = $nome;

  header("Location: " . $redirect);
  exit;

} catch (PDOException $e) {
  header("Location: " . $redirect . (str_contains($redirect, '?') ? '&' : '?') . "auth=email_exists");
  exit;
}