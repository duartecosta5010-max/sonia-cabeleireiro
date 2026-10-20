<?php
// inc/mail_config.php
// Preenche com os dados do Gmail (idealmente com App Password)

return [
  // Para enviar (SMTP)
  'smtp_host' => 'smtp.gmail.com',
  'smtp_port' => 587,
  'smtp_secure' => 'tls', // 'tls' (587) ou 'ssl' (465)

  'smtp_user' => 'duartecosta5010@gmail.com',
  'smtp_pass' => 'htgs mubz bocv gjzv',

  // Remetente (normalmente igual ao smtp_user)
  'from_email' => 'duartecosta5010@gmail.com',
  'from_name'  => 'Sónia Cabeleireiro',

  // Para onde a minha mãe recebe os pedidos
  'notify_to'  => 'duartecosta5011@gmail.com',
];