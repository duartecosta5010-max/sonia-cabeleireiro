<?php
require_once __DIR__ . '/../config.php';
$title = "Marcações - Sónia Cabeleireiro";
require_once ROOT_PATH . '/inc/header.php';

$servico_pre = $_GET['servico'] ?? '';
$servico_pre = trim($servico_pre);

$servicos = [
  "Corte",
  "Brushing / Finalização",
  "Coloração",
  "Madeixas / Balayage",
  "Tratamentos",
  "Penteados / Eventos",
];
?>

<h1 class="section-title reveal">Marcações Online</h1>

<section class="card reveal">
  <p style="margin:0;color:var(--muted);line-height:1.7;">
    Preenche o formulário abaixo para fazeres o teu pedido de marcação.
    Entraremos em contacto para confirmar a disponibilidade.
  </p>
</section>

<section class="card reveal" style="margin-top:18px;">
  <h2>Pedido de marcação</h2>

  <form method="post" action="<?= BASE_URL ?>/pages/enviar_marcacao.php">
    <div class="form-grid">
      <div>
        <label>Nome</label>
        <input type="text" name="nome" required>
      </div>

      <div>
        <label>Telemóvel</label>
        <input type="text" name="telemovel" required>
      </div>

      <div>
        <label>Email (opcional)</label>
        <input type="email" name="email" placeholder="ex.: nome@email.com">
      </div>

      <div>
        <label>Serviço</label>
        <select name="servico" required>
          <option value="">-- escolher --</option>
          <?php foreach ($servicos as $s): ?>
            <option value="<?= htmlspecialchars($s) ?>" <?= ($servico_pre === $s) ? 'selected' : '' ?>>
              <?= htmlspecialchars($s) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label>Data pretendida</label>
        <input type="date" name="data" required>
      </div>

      <div>
        <label>Hora pretendida</label>
        <input type="time" name="hora" required>
      </div>

      <div class="full">
        <label>Observações</label>
        <textarea name="obs" rows="4"></textarea>
      </div>

      <div class="full">
        <button class="btn btn-primary" type="submit">Enviar pedido</button>
      </div>
    </div>
  </form>
</section>

<?php require_once ROOT_PATH . '/inc/footer.php'; ?>