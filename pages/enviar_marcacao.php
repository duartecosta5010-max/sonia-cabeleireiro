<<?php
require_once __DIR__ . '/../config.php';

// Includes com __DIR__ (o Intelephense consegue resolver)
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/url.php';
require_once __DIR__ . '/../inc/mailer.php';

function e(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: ' . BASE_URL . '/pages/marcacoes.php');
  exit;
}

$nome = trim((string)($_POST['nome'] ?? ''));
$telemovel = trim((string)($_POST['telemovel'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));
$servico = trim((string)($_POST['servico'] ?? ''));
$data = trim((string)($_POST['data'] ?? ''));
$hora = trim((string)($_POST['hora'] ?? ''));
$obs = trim((string)($_POST['obs'] ?? ''));

$errors = [];

if ($nome === '') $errors[] = 'Indica o nome.';
if ($telemovel === '') $errors[] = 'Indica o telemóvel.';
if ($servico === '') $errors[] = 'Escolhe o serviço.';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) $errors[] = 'Data inválida.';
if (!preg_match('/^\d{2}:\d{2}$/', $hora)) $errors[] = 'Hora inválida.';
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email inválido (ou deixa em branco).';

if ($errors) {
  $title = "Enviar marcação - Sónia Cabeleireiro";
  require_once ROOT_PATH . '/inc/header.php';
  ?>
  <main class="container">
    <div class="card">
      <h1>Não foi possível enviar o pedido</h1>
      <ul>
        <?php foreach ($errors as $er): ?>
          <li><?= e($er) ?></li>
        <?php endforeach; ?>
      </ul>
      <p><a class="btn btn-primary" href="<?= e(BASE_URL . '/pages/marcacoes.php') ?>">Voltar</a></p>
    </div>
  </main>
  <?php
  require_once ROOT_PATH . '/inc/footer.php';
  exit;
}

try {
  $pdo = db();

  $token = bin2hex(random_bytes(32));
  $expiresAt = (new DateTime('+7 days'))->format('Y-m-d H:i:s');

  $stmt = $pdo->prepare("
    INSERT INTO booking_requests
      (nome, telemovel, email, servico, data_pretendida, hora_pretendida, obs, action_token, token_expires_at)
    VALUES
      (:nome, :telemovel, :email, :servico, :data_pretendida, :hora_pretendida, :obs, :token, :expires)
  ");
  $stmt->execute([
    ':nome' => $nome,
    ':telemovel' => $telemovel,
    ':email' => ($email !== '' ? $email : null),
    ':servico' => $servico,
    ':data_pretendida' => $data,
    ':hora_pretendida' => $hora,
    ':obs' => ($obs !== '' ? $obs : null),
    ':token' => $token,
    ':expires' => $expiresAt,
  ]);

  $id = (int)$pdo->lastInsertId();
  $pedidoNum = str_pad((string)$id, 6, '0', STR_PAD_LEFT);

  $acceptUrl  = site_url("pages/marcacao_acao.php?action=accept&id={$id}&token={$token}");
  $declineUrl = site_url("pages/marcacao_acao.php?action=decline&id={$id}&token={$token}");
  $reschedUrl = site_url("pages/marcacao_acao.php?action=reschedule&id={$id}&token={$token}");

  $cfg = require ROOT_PATH . '/inc/mail_config.php';
  $to = $cfg['notify_to'];

  $safeObs = $obs !== '' ? nl2br(e($obs)) : '<span style="color:#777;">(sem observações)</span>';
  $subject = "Novo pedido de marcação #{$pedidoNum}";

  $html = '
  <div style="font-family:Arial, sans-serif; background:#f6f6f6; padding:22px;">
    <div style="max-width:720px; margin:0 auto; background:#ffffff; border:1px solid #eee; border-radius:12px; overflow:hidden;">
      <div style="padding:18px 20px; background:#111; color:#fff;">
        <div style="font-size:14px; opacity:.9;">Sónia Cabeleireiro</div>
        <div style="font-size:20px; font-weight:700; margin-top:6px;">Pedido de marcação #' . e($pedidoNum) . '</div>
      </div>

      <div style="padding:18px 20px;">
        <table style="width:100%; border-collapse:collapse; font-size:14px;">
          <tr>
            <td style="padding:8px 0; color:#666; width:170px;">Nome</td>
            <td style="padding:8px 0; font-weight:700;">' . e($nome) . '</td>
          </tr>
          <tr>
            <td style="padding:8px 0; color:#666;">Telemóvel</td>
            <td style="padding:8px 0; font-weight:700;"><a href="tel:' . e($telemovel) . '" style="color:#111; text-decoration:none;">' . e($telemovel) . '</a></td>
          </tr>
          <tr>
            <td style="padding:8px 0; color:#666;">Serviço</td>
            <td style="padding:8px 0;">' . e($servico) . '</td>
          </tr>
          <tr>
            <td style="padding:8px 0; color:#666;">Data/Hora</td>
            <td style="padding:8px 0;">' . e($data) . ' às ' . e($hora) . '</td>
          </tr>
          <tr>
            <td style="padding:8px 0; color:#666; vertical-align:top;">Observações</td>
            <td style="padding:8px 0; line-height:1.5;">' . $safeObs . '</td>
          </tr>
        </table>

        <div style="margin-top:18px; padding-top:16px; border-top:1px solid #eee;">
          <div style="font-size:13px; color:#666; margin-bottom:10px;">
            Escolhe uma opção (o link expira em 7 dias):
          </div>

          <a href="' . e($acceptUrl) . '" style="display:inline-block; margin:6px 10px 6px 0; background:#16a34a; color:#fff; text-decoration:none; padding:12px 14px; border-radius:8px; font-weight:700;">
            Aceitar
          </a>

          <a href="' . e($declineUrl) . '" style="display:inline-block; margin:6px 10px 6px 0; background:#dc2626; color:#fff; text-decoration:none; padding:12px 14px; border-radius:8px; font-weight:700;">
            Recusar
          </a>

          <a href="' . e($reschedUrl) . '" style="display:inline-block; margin:6px 10px 6px 0; background:#111827; color:#fff; text-decoration:none; padding:12px 14px; border-radius:8px; font-weight:700;">
            Remarcar
          </a>
        </div>

        <div style="margin-top:14px; font-size:12px; color:#777;">
          Se os botões não funcionarem, copia e cola no browser:<br>
          <span style="color:#111;">' . e($acceptUrl) . '</span>
        </div>
      </div>
    </div>
  </div>';

  send_mail_html($to, $subject, $html);

  header('Location: ' . BASE_URL . '/pages/marcacao_obrigado.php?id=' . urlencode((string)$id));
  exit;

} catch (Throwable $ex) {
  $title = "Enviar marcação - Sónia Cabeleireiro";
  require_once ROOT_PATH . '/inc/header.php';
  ?>
  <main class="container">
    <div class="card">
      <h1>O pedido não foi concluído</h1>
      <p>Não foi possível registar/enviar o pedido neste momento.</p>
      <p style="color: var(--muted);"><?= e($ex->getMessage()) ?></p>
      <p><a class="btn btn-primary" href="<?= e(BASE_URL . '/pages/marcacoes.php') ?>">Voltar</a></p>
    </div>
  </main>
  <?php
  require_once ROOT_PATH . '/inc/footer.php';
  exit;
}