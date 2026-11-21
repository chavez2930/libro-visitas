#!/usr/bin/env bash
# Instala el libro de visitas en Kali: Apache + PHP + MariaDB (usa los que Kali ya trae).
# Uso: sudo ./install.sh
set -euo pipefail

if [ "$(id -u)" -ne 0 ]; then
  echo "Ejecútalo con sudo: sudo ./install.sh" >&2
  exit 1
fi

DIR="$(cd "$(dirname "$0")" && pwd)"
WEB=/var/www/html
DB_NAME=visitas
DB_USER=web
DB_PASS="$(openssl rand -hex 16)"  # contraseña aleatoria, solo vive en config.php

echo "==> Revisando paquetes..."
FALTAN=""
for p in apache2 php libapache2-mod-php php-mysql mariadb-server openssl curl; do
  dpkg-query -W -f='${Status}' "$p" 2>/dev/null | grep -q 'install ok installed' || FALTAN="$FALTAN $p"
done
if [ -n "$FALTAN" ]; then
  echo "    Instalando:$FALTAN"
  apt-get update
  apt-get install -y $FALTAN
else
  echo "    Ya están todos instalados (Kali los trae por defecto)."
fi
systemctl enable --now apache2 mariadb

echo "==> Creando base de datos..."
mariadb <<SQL
CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4;
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
ALTER USER '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
GRANT SELECT, INSERT ON $DB_NAME.* TO '$DB_USER'@'localhost';
CREATE TABLE IF NOT EXISTS $DB_NAME.mensajes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(50) NOT NULL CHECK (CHAR_LENGTH(nombre) >= 2),
  texto VARCHAR(300) NOT NULL CHECK (CHAR_LENGTH(texto) >= 3),
  fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
SQL

echo "==> Copiando la página..."
rm -f "$WEB/index.html"
install -m 644 "$DIR/index.php" "$DIR/validar.php" "$WEB/"
cat > "$WEB/config.php" <<PHP
<?php
\$DB_NAME = '$DB_NAME';
\$DB_USER = '$DB_USER';
\$DB_PASS = '$DB_PASS';
PHP
chown root:www-data "$WEB/config.php"
chmod 640 "$WEB/config.php"

systemctl restart apache2

if curl -fsS http://localhost/ | grep -q "Libro de visitas"; then
  echo "==> Listo. Ábrelo en http://localhost"
else
  echo "==> Algo falló: revisa 'sudo tail /var/log/apache2/error.log'" >&2
  exit 1
fi
