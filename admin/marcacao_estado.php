<?php
require_once __DIR__ . '/../config.php';
require_once ROOT_PATH . '/inc/db.php';
require_once ROOT_PATH . '/inc/admin_auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header("Location: " . BASE_URL . "/admin/marcacoes.php");
  exit;
}

$id = (int)($_POST['id'] ?? 0);
$estado = $_POST['estado'] ?? '';

$permitidos = ['pendente','aceite','recusado'];
if ($id <= 0 || !in_array($estado, $permitidos, true)) {
  header("Location: " . BASE_URL . "/admin/marcacoes.php");
  exit;
}

$stmt = $pdo->prepare("UPDATE appointments SET estado = :e WHERE id = :id");
$stmt->execute([':e' => $estado, ':id' => $id]);

header("Location: " . BASE_URL . "/admin/marcacoes.php");
exit;