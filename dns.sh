#!/usr/bin/env bash
# Servidor DNS (dnsmasq) para el dominio local visitas.test -> IP de esta Kali,
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

if ! command -v dnsmasq >/dev/null; then
  echo "==> Instalando dnsmasq..."
  apt-get update && apt-get install -y dnsmasq-base
fi

echo "==> Configurando DNS: $DOMINIO -> $IP"
cat > /etc/visitas-dns.conf <<EOF
# Responde visitas.test (y www., ns., etc.) con la IP de esta Kali
address=/$DOMINIO/$IP
listen-address=127.0.0.1,$IP
bind-interfaces
local-service          # solo contesta a equipos de tu red local
no-resolv
server=8.8.8.8         # el resto de dominios se reenvía
server=1.1.1.1
domain-needed
bogus-priv
EOF
dnsmasq --test -C /etc/visitas-dns.conf

cat > /etc/systemd/system/visitas-dns.service <<EOF
[Unit]
Description=DNS local para $DOMINIO (dnsmasq)
After=network-online.target
Wants=network-online.target

[Service]
ExecStart=$(command -v dnsmasq) -k -C /etc/visitas-dns.conf
Restart=on-failure

[Install]
WantedBy=multi-user.target
EOF
systemctl daemon-reload
systemctl enable --now visitas-dns
systemctl restart visitas-dns

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
sleep 1
R="$(dig @127.0.0.1 "www.$DOMINIO" +short 2>/dev/null || nslookup "www.$DOMINIO" 127.0.0.1 | awk '/^Address: /{print $2}' | tail -1)"
if [ "$R" = "$IP" ] && curl -fsS --resolve "www.$DOMINIO:80:127.0.0.1" "http://www.$DOMINIO/" | grep -q "Libro de visitas"; then
  echo "==> Listo: http://www.$DOMINIO -> $IP (servidor DNS en $IP)"
else
  echo "==> Algo falló (el DNS devolvió: '$R')" >&2
  exit 1
fi
