<?php
require __DIR__ . '/config.php'; // generado por install.sh, nunca va en el repo

$db = new PDO("mysql:host=localhost;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $n = trim($_POST['nombre'] ?? '');
  $t = trim($_POST['texto'] ?? '');
  if ($n !== '' && $t !== '' && mb_strlen($n) <= 50 && mb_strlen($t) <= 300) {
    $db->prepare('INSERT INTO mensajes (nombre, texto) VALUES (?, ?)')->execute([$n, $t]);
  }
  header('Location: ./');
  exit;
}

$msgs = $db->query('SELECT nombre, texto, fecha FROM mensajes ORDER BY id DESC LIMIT 50')->fetchAll();
function e($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Libro de visitas</title>
  <style>
    body { font-family: system-ui, sans-serif; max-width: 640px; margin: 40px auto; padding: 0 16px; background: #0f1115; color: #e8e6e3; }
    input, textarea, button { width: 100%; box-sizing: border-box; padding: 10px; margin: 6px 0; border-radius: 8px; border: 1px solid #333; background: #1a1d23; color: inherit; font: inherit; }
    button { background: #3ddc97; color: #000; font-weight: 600; cursor: pointer; }
    .m { border-bottom: 1px solid #2a2d35; padding: 12px 0; }
    small { color: #888; }
  </style>
</head>
<body>
  <h1>📖 Libro de visitas</h1>
  <form method="post">
    <input name="nombre" placeholder="Tu nombre" maxlength="50" required>
    <textarea name="texto" placeholder="Tu mensaje" maxlength="300" required></textarea>
    <button>Enviar</button>
  </form>
  <?php if (!$msgs): ?><p><small>Todavía no hay mensajes. ¡Sé el primero!</small></p><?php endif; ?>
  <?php foreach ($msgs as $m): ?>
    <div class="m"><b><?= e($m['nombre']) ?></b> <small><?= e($m['fecha']) ?></small><br><?= e($m['texto']) ?></div>
  <?php endforeach; ?>
</body>
</html>
