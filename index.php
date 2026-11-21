<?php
session_start();
require __DIR__ . '/config.php'; // generado por install.sh, nunca va en el repo
require __DIR__ . '/validar.php';

const ESPERA_SEGUNDOS = 15; // tiempo mínimo entre mensajes de la misma persona

function e($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

try {
  $db = new PDO("mysql:host=localhost;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $ex) {
  error_log('libro-visitas: ' . $ex->getMessage()); // el detalle va al log, no al visitante
  http_response_code(503);
  exit('El servicio no está disponible en este momento. Intenta más tarde.');
}

if (empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $errores = [];
  $nombre = (string)($_POST['nombre'] ?? '');
  $texto  = (string)($_POST['texto'] ?? '');

  if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) {
    $errores[] = 'La sesión expiró. Recarga la página e intenta de nuevo.';
  } elseif (($_POST['sitio_web'] ?? '') !== '') {
    $errores[] = 'No se pudo enviar el mensaje.'; // campo trampa: solo lo llenan bots
  } elseif (time() - ($_SESSION['ultimo_envio'] ?? 0) < ESPERA_SEGUNDOS) {
    $errores[] = 'Espera unos segundos antes de enviar otro mensaje.';
  } else {
    [$datos, $errores] = validar_mensaje($nombre, $texto);
    if ($datos) {
      try {
        $db->prepare('INSERT INTO mensajes (nombre, texto) VALUES (?, ?)')
           ->execute([$datos['nombre'], $datos['texto']]);
        $_SESSION['ultimo_envio'] = time();
        $_SESSION['ok'] = '¡Gracias! Tu mensaje se guardó.';
      } catch (PDOException $ex) {
        error_log('libro-visitas: ' . $ex->getMessage());
        $errores[] = 'No se pudo guardar el mensaje. Intenta más tarde.';
      }
    }
  }

  if ($errores) {
    $_SESSION['errores'] = $errores;
    $_SESSION['previo'] = ['nombre' => $nombre, 'texto' => $texto]; // no perder lo que escribió
  }
  header('Location: ./');
  exit;
}

$errores = $_SESSION['errores'] ?? [];
$ok      = $_SESSION['ok'] ?? '';
$previo  = $_SESSION['previo'] ?? ['nombre' => '', 'texto' => ''];
unset($_SESSION['errores'], $_SESSION['ok'], $_SESSION['previo']);

$msgs = $db->query('SELECT nombre, texto, fecha FROM mensajes ORDER BY id DESC LIMIT 50')->fetchAll();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Libro de visitas</title>
  <style>
    body { font-family: system-ui, sans-serif; max-width: 640px; margin: 40px auto; padding: 0 16px; background: #0f1115; color: #e8e6e3; }
    label { display: block; margin-top: 10px; font-size: .9rem; color: #bbb; }
    input, textarea, button { width: 100%; box-sizing: border-box; padding: 10px; margin: 4px 0; border-radius: 8px; border: 1px solid #333; background: #1a1d23; color: inherit; font: inherit; }
    input:focus, textarea:focus { outline: 2px solid #3ddc97; }
    button { background: #3ddc97; color: #000; font-weight: 600; cursor: pointer; margin-top: 12px; }
    .aviso { padding: 10px 14px; border-radius: 8px; margin: 12px 0; }
    .error { background: #3a1d1d; border: 1px solid #a33; }
    .exito { background: #1d3a2b; border: 1px solid #3ddc97; }
    .aviso ul { margin: 0; padding-left: 18px; }
    .trampa { position: absolute; left: -9999px; }
    .contador { text-align: right; font-size: .8rem; color: #888; }
    .m { border-bottom: 1px solid #2a2d35; padding: 12px 0; white-space: pre-line; overflow-wrap: anywhere; }
    small { color: #888; }
  </style>
</head>
<body>
  <h1>📖 Libro de visitas</h1>

  <?php if ($errores): ?>
    <div class="aviso error" role="alert"><ul><?php foreach ($errores as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
  <?php elseif ($ok): ?>
    <div class="aviso exito" role="status"><?= e($ok) ?></div>
  <?php endif; ?>

  <form method="post" novalidate>
    <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
    <div class="trampa" aria-hidden="true">
      <label>No llenar <input name="sitio_web" tabindex="-1" autocomplete="off"></label>
    </div>

    <label for="nombre">Nombre</label>
    <input id="nombre" name="nombre" minlength="2" maxlength="50" required
           pattern="[\p{L}\p{N} .'\-]+" value="<?= e($previo['nombre']) ?>">

    <label for="texto">Mensaje</label>
    <textarea id="texto" name="texto" rows="4" minlength="3" maxlength="300" required><?= e($previo['texto']) ?></textarea>
    <div class="contador"><span id="cuenta">0</span>/300</div>

    <button>Enviar</button>
  </form>

  <?php if (!$msgs): ?><p><small>Todavía no hay mensajes. ¡Sé el primero!</small></p><?php endif; ?>
  <?php foreach ($msgs as $m): ?>
    <div class="m"><b><?= e($m['nombre']) ?></b> <small><?= e($m['fecha']) ?></small><br><?= e($m['texto']) ?></div>
  <?php endforeach; ?>

  <script>
    // Validación en el navegador solo para avisar rápido; la real está en el servidor.
    const f = document.querySelector('form'), t = document.getElementById('texto'), c = document.getElementById('cuenta');
    const contar = () => c.textContent = t.value.length;
    t.addEventListener('input', contar); contar();
    f.addEventListener('submit', ev => { if (!f.reportValidity()) ev.preventDefault(); });
  </script>
</body>
</html>
