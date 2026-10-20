<?php
require_once __DIR__ . '/../config.php';
$title = "Contactos - Sónia Cabeleireiro";
require_once ROOT_PATH . '/inc/header.php';

// Para vir pré-selecionado (ex.: contactos.php?servico=Corte)
$servico_pre = $_GET['servico'] ?? '';
$servico_pre = trim($servico_pre);

// Lista de serviços que aparecem no select
$servicos = [
  "Corte",
  "Brushing / Finalização",
  "Coloração",
  "Madeixas / Balayage",
  "Tratamentos",
  "Penteados / Eventos",
];
?>

<h1 class="section-title reveal">Contactos / Marcações</h1>

<section class="contact-grid">
  <div class="card reveal">
    <h2>Contactos & Horário</h2>

    <h3>Morada</h3>
    <p>
      Rua D. Manuel Correia Ramalho, Lote 6, Loja N<br>
      2070-095 Cartaxo
    </p>

    <h3>Contacto</h3>
    <p>
      <a href="tel:+351933148593">+351 933 148 593</a><br>
      <a href="mailto:soniarego78@hotmail.com">soniarego78@hotmail.com</a>
    </p>

    <h3>Horário</h3>
    <ul class="clean-list">
      <li><strong>Ter–Sex:</strong> 09:00 – 18:00</li>
      <li><strong>Sábado:</strong> 09:00 – 13:00</li>
      <li><strong>Domingo:</strong> Encerrado</li>
      <li><strong>Segunda:</strong> Encerrado</li>
    </ul>
  </div>

  <div class="card reveal">
    <h2>Localização</h2>

    <iframe
      class="map-embed"
      src="https://www.google.com/maps/embed?pb=!3m2!1spt-PT!2spt!4v1791487772362!5m2!1spt-PT!2spt!6m8!1m7!1svnHjkTjgJCFxwB1yYEv0OQ!2m2!1d39.15791861924367!2d-8.791702111921085!3f256.9679806459595!4f-17.638796860629!5f0.7820865974627469"
      allowfullscreen=""
      loading="lazy"
      referrerpolicy="strict-origin-when-cross-origin">
    </iframe>
  </div>
</section>

<section class="card reveal" id="marcacao" style="margin-top:18px;">
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