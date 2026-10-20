<?php
require_once __DIR__ . '/../config.php';

require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/mailer.php';

date_default_timezone_set('Europe/Lisbon');

function e(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function fmtDatePt(string $ymd): string {
  try { return (new DateTime($ymd))->format('d/m/Y'); } catch(Throwable $e) { return $ymd; }
}
function fmtTime(string $his): string {
  return substr($his, 0, 5);
}

$action = (string)($_GET['action'] ?? '');
$id = (int)($_GET['id'] ?? 0);
$token = trim((string)($_GET['token'] ?? ''));

$allowed = ['accept', 'decline', 'reschedule'];
if (!in_array($action, $allowed, true)) $action = '';

$title = "Gerir marcação - Sónia Cabeleireiro";
require_once ROOT_PATH . '/inc/header.php';
?>
<main class="container">
<?php
if ($id <= 0 || $token === '' || $action === ''): ?>
  <div class="card">
    <h1>Link inválido</h1>
    <p>Este link não é válido.</p>
  </div>
</main>
<?php require_once ROOT_PATH . '/inc/footer.php'; exit; ?>
<?php endif; ?>

<?php
$notice = null;
$noticeType = 'ok'; // ok | warn

try {
  $pdo = db();

  $stmt = $pdo->prepare("SELECT * FROM booking_requests WHERE id = :id AND action_token = :token LIMIT 1");
  $stmt->execute([':id' => $id, ':token' => $token]);
  $req = $stmt->fetch();

  if (!$req) {
    throw new RuntimeException('Pedido não encontrado (ou token inválido).');
  }

  $now = new DateTime();
  $exp = new DateTime($req['token_expires_at']);
  if ($now > $exp) {
    throw new RuntimeException('Este link expirou.');
  }

  $estado = (string)$req['estado'];

  // POST -> confirmar ação
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = (string)($_POST['action'] ?? '');
    if ($postAction !== $action) {
      throw new RuntimeException('Ação inválida.');
    }

    if ($estado !== 'pendente') {
      throw new RuntimeException('Este pedido já não está pendente (estado atual: ' . $estado . ').');
    }

    // Vamos determinar o "novo estado" e os dados finais para usar no email ao cliente
    $newEstado = $estado;
    $finalDate = $req['data_final'] ?: $req['data_pretendida'];
    $finalTime = $req['hora_final'] ?: $req['hora_pretendida'];

    if ($action === 'accept') {
      $up = $pdo->prepare("
        UPDATE booking_requests
        SET estado='aceite',
            data_final = data_pretendida,
            hora_final = hora_pretendida,
            token_expires_at = NOW()
        WHERE id = :id AND action_token = :token
      ");
      $up->execute([':id' => $id, ':token' => $token]);

      $newEstado = 'aceite';
      $finalDate = $req['data_pretendida'];
      $finalTime = $req['hora_pretendida'];
    }

    if ($action === 'decline') {
      $up = $pdo->prepare("
        UPDATE booking_requests
        SET estado='recusado',
            token_expires_at = NOW()
        WHERE id = :id AND action_token = :token
      ");
      $up->execute([':id' => $id, ':token' => $token]);

      $newEstado = 'recusado';
      $finalDate = $req['data_pretendida'];
      $finalTime = $req['hora_pretendida'];
    }

    if ($action === 'reschedule') {
      $dataNova = trim((string)($_POST['data_nova'] ?? ''));
      $horaNova = trim((string)($_POST['hora_nova'] ?? ''));

      if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataNova)) throw new RuntimeException('Data nova inválida.');
      if (!preg_match('/^\d{2}:\d{2}$/', $horaNova)) throw new RuntimeException('Hora nova inválida.');

      $up = $pdo->prepare("
        UPDATE booking_requests
        SET estado='remarcado',
            data_final = :data_final,
            hora_final = :hora_final,
            token_expires_at = NOW()
        WHERE id = :id AND action_token = :token
      ");
      $up->execute([
        ':data_final' => $dataNova,
        ':hora_final' => $horaNova,
        ':id' => $id,
        ':token' => $token
      ]);

      $newEstado = 'remarcado';
      $finalDate = $dataNova;
      $finalTime = $horaNova;
    }

    // Recarregar dados atuais do pedido (já com estado final)
    $stmt = $pdo->prepare("SELECT * FROM booking_requests WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $req = $stmt->fetch();
    $estado = (string)$req['estado'];

    // Enviar email ao cliente (se tiver email)
    $clientEmail = trim((string)($req['email'] ?? ''));
    $pedidoNum = str_pad((string)$req['id'], 6, '0', STR_PAD_LEFT);

    if ($clientEmail !== '') {
      $nomeCliente = (string)$req['nome'];
      $servico = (string)$req['servico'];

      // dados finais (para o email)
      $emailDate = $req['data_final'] ?: $req['data_pretendida'];
      $emailTime = $req['hora_final'] ?: $req['hora_pretendida'];

      $dataFmt = fmtDatePt((string)$emailDate);
      $horaFmt = fmtTime((string)$emailTime);

      if ($newEstado === 'aceite') {
        $subject = "Marcação confirmada #{$pedidoNum} — {$dataFmt} às {$horaFmt}";
        $headline = "Marcação confirmada";
        $msg = "A tua marcação foi <strong>confirmada</strong>.";
        $badgeBg = "#16a34a";
      } elseif ($newEstado === 'recusado') {
        $subject = "Marcação recusada #{$pedidoNum}";
        $headline = "Marcação recusada";
        $msg = "Infelizmente, não foi possível confirmar a tua marcação para esta data/hora.";
        $badgeBg = "#dc2626";
      } else { // remarcado
        $subject = "Marcação remarcada #{$pedidoNum} — {$dataFmt} às {$horaFmt}";
        $headline = "Marcação remarcada";
        $msg = "A tua marcação foi <strong>remarcada</strong>. Verifica os novos detalhes abaixo.";
        $badgeBg = "#111827";
      }

      $html = '
      <div style="font-family:Arial, sans-serif; background:#f6f6f6; padding:22px;">
        <div style="max-width:720px; margin:0 auto; background:#ffffff; border:1px solid #eee; border-radius:12px; overflow:hidden;">
          <div style="padding:18px 20px; background:#111; color:#fff;">
            <div style="font-size:14px; opacity:.9;">Sónia Cabeleireiro</div>
            <div style="font-size:20px; font-weight:700; margin-top:6px;">' . e($headline) . ' #' . e($pedidoNum) . '</div>
          </div>

          <div style="padding:18px 20px; color:#111;">
            <div style="display:inline-block; background:' . e($badgeBg) . '; color:#fff; padding:6px 10px; border-radius:999px; font-weight:700; font-size:12px;">
              ' . e(mb_strtoupper($newEstado)) . '
            </div>

            <p style="margin:12px 0 0 0; line-height:1.6;">
              Olá <strong>' . e($nomeCliente) . '</strong>,<br>
              ' . $msg . '
            </p>

            <div style="margin-top:14px;">
              <table style="width:100%; border-collapse:collapse; font-size:14px;">
                <tr>
                  <td style="padding:8px 0; color:#666; width:170px;">Serviço</td>
                  <td style="padding:8px 0; font-weight:700;">' . e($servico) . '</td>
                </tr>
                <tr>
                  <td style="padding:8px 0; color:#666;">Data/Hora</td>
                  <td style="padding:8px 0; font-weight:700;">' . e($dataFmt) . ' às ' . e($horaFmt) . '</td>
                </tr>
              </table>
            </div>

            <div style="margin-top:14px; padding-top:14px; border-top:1px solid #eee; font-size:13px; color:#555; line-height:1.6;">
              Se precisares de alterar ou cancelar, por favor contacta-nos:<br>
              <strong>+351 933 148 593</strong> (chamada para a rede móvel nacional)
            </div>
          </div>
        </div>
      </div>';

      try {
        send_mail_html($clientEmail, $subject, $html);
        $notice = "O cliente foi notificado por email: " . $clientEmail;
        $noticeType = 'ok';
      } catch (Throwable $mailEx) {
        // A ação já ficou guardada na BD. Só avisamos que o email falhou.
        $notice = "A ação foi registada, mas falhou o envio do email ao cliente (" . $clientEmail . "): " . $mailEx->getMessage();
        $noticeType = 'warn';
      }

    } else {
      $notice = "A ação foi registada, mas o cliente não indicou email (não foi possível enviar notificação).";
      $noticeType = 'warn';
    }
  }

} catch (Throwable $ex) {
  ?>
  <div class="card">
    <h1>Não foi possível abrir o pedido</h1>
    <p><?= e($ex->getMessage()) ?></p>
  </div>
</main>
<?php require_once ROOT_PATH . '/inc/footer.php'; exit; ?>
<?php } ?>

<?php if ($notice): ?>
  <div class="card" style="margin-bottom:18px; border-left:6px solid <?= $noticeType === 'ok' ? '#16a34a' : '#dc2626' ?>;">
    <strong><?= $noticeType === 'ok' ? 'Sucesso:' : 'Aviso:' ?></strong>
    <div style="margin-top:6px; color: var(--muted); line-height:1.6;"><?= e($notice) ?></div>
  </div>
<?php endif; ?>

<div class="card">
  <h1>Pedido #<?= e(str_pad((string)$req['id'], 6, '0', STR_PAD_LEFT)) ?></h1>
  <p style="color:var(--muted); margin-top:6px;">Estado atual: <strong><?= e((string)$req['estado']) ?></strong></p>

  <div style="margin-top:12px; line-height:1.8;">
    <div><strong>Nome:</strong> <?= e((string)$req['nome']) ?></div>
    <div><strong>Telemóvel:</strong> <a href="tel:<?= e((string)$req['telemovel']) ?>"><?= e((string)$req['telemovel']) ?></a></div>
    <div><strong>Email:</strong> <?= $req['email'] ? e((string)$req['email']) : '<span style="color:var(--muted)">(não indicado)</span>' ?></div>
    <div><strong>Serviço:</strong> <?= e((string)$req['servico']) ?></div>
    <div><strong>Data/Hora pretendida:</strong> <?= e((string)$req['data_pretendida']) ?> às <?= e(fmtTime((string)$req['hora_pretendida'])) ?></div>

    <?php if (!empty($req['data_final']) && !empty($req['hora_final'])): ?>
      <div><strong>Data/Hora final:</strong> <?= e((string)$req['data_final']) ?> às <?= e(fmtTime((string)$req['hora_final'])) ?></div>
    <?php endif; ?>

    <div><strong>Observações:</strong> <?= $req['obs'] ? nl2br(e((string)$req['obs'])) : '<span style="color:var(--muted)">(sem observações)</span>' ?></div>
  </div>

  <hr style="border:0; border-top:1px solid var(--border); margin:16px 0;">

  <?php if ((string)$req['estado'] !== 'pendente'): ?>
    <p>Este pedido já foi tratado.</p>
  <?php else: ?>

    <?php if ($action === 'accept'): ?>
      <form method="post">
        <input type="hidden" name="action" value="accept">
        <button class="btn btn-primary" type="submit">Confirmar ACEITAR</button>
      </form>
    <?php endif; ?>

    <?php if ($action === 'decline'): ?>
      <form method="post">
        <input type="hidden" name="action" value="decline">
        <button class="btn btn-dark" type="submit">Confirmar RECUSAR</button>
      </form>
    <?php endif; ?>

    <?php if ($action === 'reschedule'): ?>
      <form method="post">
        <input type="hidden" name="action" value="reschedule">
        <div class="form-grid" style="margin-top:10px;">
          <div>
            <label>Nova data</label>
            <input type="date" name="data_nova" required>
          </div>
          <div>
            <label>Nova hora</label>
            <input type="time" name="hora_nova" required>
          </div>
          <div class="full">
            <button class="btn btn-primary" type="submit">Confirmar REMARCAR</button>
          </div>
        </div>
      </form>
    <?php endif; ?>

  <?php endif; ?>
</div>

</main>
<?php require_once ROOT_PATH . '/inc/footer.php'; ?>