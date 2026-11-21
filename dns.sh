#!/usr/bin/env bash
# Servidor DNS (BIND9) para el dominio local visitas.test -> IP de esta Kali,
# más un VirtualHost de Apache con ese nombre.
# Uso: sudo ./dns.sh
set -euo pipefail

if [ "$(id -u)" -ne 0 ]; then
  echo "Ejecútalo con sudo: sudo ./dns.sh" >&2
  exit 1
fi

DOMINIO=visitas.test
IP="$(ip -4 route get 1.1.1.1 | awk '{for(i=1;i<=NF;i++) if($i=="src") print $(i+1)}')"
[ -n "$IP" ] || { echo "No pude detectar la IP de la red" >&2; exit 1; }
SERIAL="$(date +%Y%m%d%H)"

echo "==> Instalando BIND9..."
apt-get install -y bind9 bind9-dnsutils

echo "==> Configurando zona $DOMINIO -> $IP"
cat > /etc/bind/named.conf.options <<EOF
options {
  directory "/var/cache/bind";
  listen-on { any; };
  listen-on-v6 { none; };
  allow-query { localhost; localnets; };      // solo tu red local
  recursion yes;
  allow-recursion { localhost; localnets; };  // no ser resolvedor abierto
  forwarders { 8.8.8.8; 1.1.1.1; };           // el resto de dominios
  dnssec-validation auto;
};
EOF

cat > /etc/bind/named.conf.local <<EOF
zone "$DOMINIO" {
  type master;
  file "/etc/bind/db.$DOMINIO";
};
EOF

cat > "/etc/bind/db.$DOMINIO" <<EOF
\$TTL 300
@   IN SOA ns.$DOMINIO. admin.$DOMINIO. ( $SERIAL 3600 600 86400 300 )
@   IN NS  ns.$DOMINIO.
@   IN A   $IP
ns  IN A   $IP
www IN A   $IP
EOF

named-checkconf
named-checkzone "$DOMINIO" "/etc/bind/db.$DOMINIO"
systemctl enable named >/dev/null 2>&1 || true
systemctl restart named

echo "==> Configurando Apache para $DOMINIO"
cat > /etc/apache2/sites-available/visitas.conf <<EOF
<VirtualHost *:80>
  ServerName www.$DOMINIO
  ServerAlias $DOMINIO
  DocumentRoot /var/www/html
  ErrorLog \${APACHE_LOG_DIR}/visitas-error.log
  CustomLog \${APACHE_LOG_DIR}/visitas-access.log combined
</VirtualHost>
EOF
a2ensite -q visitas
apache2ctl configtest
systemctl reload apache2

echo "==> Comprobando..."
R="$(dig @127.0.0.1 "www.$DOMINIO" +short)"
if [ "$R" = "$IP" ] && curl -fsS --resolve "www.$DOMINIO:80:127.0.0.1" "http://www.$DOMINIO/" | grep -q "Libro de visitas"; then
  echo "==> Listo: http://www.$DOMINIO -> $IP (DNS en $IP)"
else
  echo "==> Algo falló (dig devolvió: '$R')" >&2
  exit 1
fi
