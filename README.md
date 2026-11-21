# Libro de visitas

Página web con base de datos para Kali Linux: Apache + PHP + MariaDB.
La gente deja un mensaje y se guarda en la base de datos.

## Instalar (dentro de Kali)

```bash
git clone https://github.com/chavez2930/libro-visitas.git
cd libro-visitas
chmod +x install.sh
sudo ./install.sh
```

Pruébalo en Firefox dentro de Kali: http://localhost

## Publicarlo a internet

Con un túnel de Cloudflare (no necesitas cuenta ni abrir puertos):

```bash
wget https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-amd64.deb
sudo dpkg -i cloudflared-linux-amd64.deb
cloudflared tunnel --url http://localhost:80
```

Copia el enlace `https://....trycloudflare.com` que aparece: ese es el público.
Funciona mientras esa terminal esté abierta y cambia cada vez que lo vuelves a abrir.

## Seguridad

- La contraseña de la base de datos se genera al instalar y solo se guarda en `/var/www/html/config.php` (no está en este repo).
- El usuario `web` solo puede leer y agregar mensajes.
- Consultas preparadas (sin inyección SQL) y texto escapado (sin XSS).
- La base de datos no se expone a internet; el túnel solo publica la página.
