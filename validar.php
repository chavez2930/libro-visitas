<?php
// Valida un mensaje. Devuelve [datos limpios, lista de errores].
// Sin mbstring a propósito: usa preg con /u, que viene en el PHP base.

function utf8_valido(string $s): bool { return preg_match('//u', $s) === 1; }
function largo(string $s): int { return preg_match_all('/./su', $s); }

function validar_mensaje(string $nombre, string $texto): array {
  if (!utf8_valido($nombre) || !utf8_valido($texto)) {
    return [null, ['El texto tiene caracteres no válidos.']];
  }

  // Quita caracteres de control (deja saltos de línea en el mensaje)
  $nombre = trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $nombre));
  $texto  = trim(preg_replace('/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]/u', '', $texto));
  $nombre = preg_replace('/\s+/u', ' ', $nombre);

  $errores = [];
  $ln = largo($nombre);
  if ($ln < 2 || $ln > 50) {
    $errores[] = 'El nombre debe tener entre 2 y 50 caracteres.';
  } elseif (!preg_match("/^[\p{L}\p{N} .'-]+$/u", $nombre)) {
    $errores[] = 'El nombre solo puede tener letras, números, espacios, puntos, guiones y apóstrofes.';
  }

  $lt = largo($texto);
  if ($lt < 3 || $lt > 300) {
    $errores[] = 'El mensaje debe tener entre 3 y 300 caracteres.';
  }
  if (preg_match('~(https?://|www\.)~i', $texto)) {
    $errores[] = 'No se permiten enlaces en el mensaje.';
  }

  return [$errores ? null : ['nombre' => $nombre, 'texto' => $texto], $errores];
}
