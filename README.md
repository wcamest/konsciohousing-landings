# KonscioHousing — landings independientes

Proyecto separado de `wcamest/konsciohousing`, preparado a partir del ZIP
«lanzamiento-ionos-konscio.zip» entregado por el cliente.

## Rutas

- `https://konsciohousing.org/terrenos/`
- `https://konsciohousing.org/socios-industriales/`
- `https://konsciohousing.org/politica-de-privacidad.html`
- `https://konsciohousing.org/enviar.php` (solo POST, PHPMailer + SMTP)

La landing `/participa/` **NO venía en el ZIP**. No se ha inventado ni incluido.

## Estructura

- `public/`: los únicos archivos que Apache debe publicar (via Alias).
- `config/mail.local.example.php`: ejemplo de credenciales SMTP.
- `config/mail.local.php`: configuración PRIVADA, ignorada por Git.
- `apache/`: ejemplo de directivas para AÑADIR al VirtualHost principal.
- `docs/`: integración de SEO con la web principal.

El ZIP de origen incluía `google-ads/`, un LEEME y `robots.txt/sitemap.xml`.
No forman parte de este repositorio publicable: las campañas no deben
subirse a un repositorio público ni a la carpeta web; robots/sitemap
ya están gestionados por el repositorio principal.

## Instalación de dependencias

```bash
composer install --no-dev --optimize-autoloader
```

Si no existe composer.lock, Composer lo generará en la primera instalación.
Una vez generado, se recomienda confirmarlo en Git para despliegues reproducibles.

## Correo

```bash
cp config/mail.local.example.php config/mail.local.php
# editar mail.local.php con contraseña real, sólo en VPS
chmod 640 config/mail.local.php
```

Si usa IONOS España: `smtp.ionos.es`, puerto `465`/`smtps` o
`587`/`starttls` según las recomendaciones oficiales.

## Importante

- No reemplaza ni mezcla el repositorio `wcamest/konsciohousing`.
- No modifica `robots.txt`, `sitemap.xml` o `.htaccess` del principal.
- Las URLs canónicas fueron normalizadas a `https://konsciohousing.org`
  porque el `.htaccess` del principal redirige `www` a sin `www`.
- Validar las condiciones legales y las afirmaciones publicitarias con el cliente.
- **Ver el paso a paso completo en `docs/DESPLIEGUE-VPS.md`**.
