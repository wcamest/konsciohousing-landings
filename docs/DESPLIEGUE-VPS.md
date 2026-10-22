# Despliegue guiado: VPS IONOS + dos repos separados

## Fase 0. Diagnóstico SIN cambios (SSH al VPS)

```bash
sudo apache2ctl -S
sudo grep -RInE 'ServerName|ServerAlias|DocumentRoot|ProxyPass|SetHandler|FilesMatch|Alias' /etc/apache2/sites-enabled/
php -v
command -v composer || true
ls -l /run/php/ 2>/dev/null || true
```

Identificar el VirtualHost HTTPS REAL de `konsciohousing.org`, la raíz
actual de la web principal y el tipo de ejecución de PHP. Guardar copia:

```bash
sudo cp /etc/apache2/sites-available/ARCHIVO_REAL.conf /etc/apache2/sites-available/ARCHIVO_REAL.conf.bak
```

No usar literalmente ARCHIVO_REAL.conf: reemplazar por el nombre identificado.
**Nunca crear otro VirtualHost duplicado del dominio.**

## Fase 1. GitHub (equipo local)

Crear repositorio NUEVO en GitHub llamado, por ejemplo,
`wcamest/konsciohousing-landings` (sin README inicial) y publicar:

```bash
cd /ruta/local/konsciohousing-landings-ready

git init -b main
git add .
git commit -m "Landings independientes y formulario PHPMailer SMTP"
git remote add origin git@github.com:wcamest/konsciohousing-landings.git
git push -u origin main
```

Si no se tiene SSH configurado con GitHub, usar HTTPS (autenticación
vía credenciales modernas, no contraseña de la cuenta).

## Fase 2. Clonar en el VPS SIN tocar el sitio principal

```bash
sudo mkdir -p /var/www/konsciohousing-landings
sudo chown "$USER":"$USER" /var/www/konsciohousing-landings
git clone https://github.com/wcamest/konsciohousing-landings.git /var/www/konsciohousing-landings
cd /var/www/konsciohousing-landings
composer install --no-dev --optimize-autoloader
```

Si es repo privado, configurar la autenticación de GitHub y reemplazar la URL.
Si falta Composer, instalarlo por el procedimiento oficial de tu versión de
Ubuntu antes del último comando (sin instalar paquetes PHP al azar).

## Fase 3. SMTP privado en el VPS

```bash
cd /var/www/konsciohousing-landings
cp config/mail.local.example.php config/mail.local.php
nano config/mail.local.php
# Rellenar password y comprobar host/puerto.
# Permitir lectura al usuario de Apache/PHP-FPM sin dar permiso al mundo:
sudo chgrp www-data config/mail.local.php
chmod 640 config/mail.local.php
```

Comprobar que el pool de PHP realmente corre como `www-data`; de lo
contrario, adaptar el grupo. Nunca revelar credenciales por chat o Git.

## Fase 4. Alias Apache para las rutas

Abrir el VirtualHost 443 real identificado en fase 0 y añadir las directivas
de `apache/konsciohousing-landings-aliases.conf.example`, sin modificar
`DocumentRoot` ni los redirects existentes de HTTP/HTTPS o www.

Si el VirtualHost tiene un `ProxyPass / ...` global, poner EXCLUSIONES
ANTES de dicho ProxyPass, por ejemplo:

```apache
ProxyPass /terrenos !
ProxyPass /socios-industriales !
ProxyPass /politica-de-privacidad.html !
ProxyPass /enviar.php !
```

**PHP:** Antes de exponer `/enviar.php`, garantizar que el handler PHP
funciona para la ruta `public/enviar.php` (PHP-FPM o mod_php). Si el
VirtualHost limita el handler a otro `<Directory>`, ampliar con
un `<FilesMatch "\.php$"> SetHandler ... </FilesMatch>` dentro del
`<Directory>` nuevo, usando el socket REAL de `/run/php/`; no asumir
que es php8.3-fpm ni publicar código fuente PHP accidentalmente.

Revisar además que `https://www.konsciohousing.org/terrenos/` y la otra
landing redirijan a la variante sin `www`. Una redirección solamente en
el `.htaccess` del DocumentRoot original podría NO aplicarse a los Alias;
si es necesario, usar la regla de VirtualHost comentada en el ejemplo.

Después:

```bash
sudo apache2ctl configtest
sudo systemctl reload apache2
```

Si configtest NO informa `Syntax OK`, restaurar el backup y no recargar.

## Fase 5. Comprobar rutas y POST

```bash
curl -I https://konsciohousing.org/
curl -I https://konsciohousing.org/terrenos/
curl -I https://konsciohousing.org/socios-industriales/
curl -I https://konsciohousing.org/politica-de-privacidad.html
curl -i https://konsciohousing.org/enviar.php
```

El GET de `enviar.php` debe devolver **405** (PHP ejecutándose) y NUNCA
código fuente. La página principal debe responder como antes. Luego
realizar **un** envío real desde cada landing, comprobar el mensaje en
pantalla y la llegada a `info@proyectakonscio.org` (incluido spam).

## Fase 6. SEO y navegación

Editar el sitemap **del repo principal** con las entradas de
`docs/sitemap-agregar-al-principal.txt`. No reemplazar el sitemap
actual con el simplificado del ZIP. `robots.txt` permanece en el repo
principal. No publicar `/participa/` sin recibir el tercer HTML.

Actualizar en el repo principal los botones `Programa de Terrenos` y
`Socios Industriales` para apuntar a las rutas nuevas si es necesario.
Comprobar meta tags OG en el Sharing Debugger de Facebook.

## Mantenimiento habitual

```bash
cd /var/www/konsciohousing-landings
git pull --ff-only
composer install --no-dev --optimize-autoloader
```

**El archivo `config/mail.local.php` no se toca con Git.** Si cambian
solamente los HTML, un pull normalmente no requiere reload de Apache.

## Reversión de rutas

Quitar solo los cuatro `Alias` del VirtualHost (y las eventuales
exclusiones de ProxyPass y handler PHP dedicadas a estas rutas), ejecutar
`sudo apache2ctl configtest` y `sudo systemctl reload apache2`.
El sitio principal nunca se borró ni sustituyó.
