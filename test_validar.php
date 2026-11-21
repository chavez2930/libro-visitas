<?php
// Prueba rápida: php test_validar.php
require __DIR__ . '/validar.php';

function ok($n, $t)  { [$d, $e] = validar_mensaje($n, $t); assert($d !== null, "debía pasar: $n | $t"); return $d; }
function mal($n, $t) { [$d, $e] = validar_mensaje($n, $t); assert($d === null && $e, "debía fallar: $n | $t"); }

ini_set('zend.assertions', 1); ini_set('assert.exception', 1);

$d = ok('  José   Pérez ', 'Hola, qué buena página');
assert($d['nombre'] === 'José Pérez');
ok("O'Brien-2", "Línea 1\nLínea 2");
mal('', 'Hola mundo');
mal('A', 'Hola mundo');
mal(str_repeat('a', 51), 'Hola mundo');
mal('<script>', 'Hola mundo');
mal('Ana', 'ok');
mal('Ana', str_repeat('x', 301));
mal('Ana', 'visita https://spam.com');
mal('Ana', 'entra a www.spam.com');
mal("Ana\xff", 'Hola mundo');
mal('Ana', "   \x00\x01  ");
ok('Ñu', str_repeat('é', 300));   // 300 letras con acento = 300, no 600 bytes
mal('Ñu', str_repeat('é', 301));

echo "Todas las pruebas pasaron\n";
