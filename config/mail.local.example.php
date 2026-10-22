<?php
// Copiar a mail.local.php SOLO EN EL VPS. NO subir este archivo real a GitHub.
return [
    'host' => 'smtp.ionos.es',
    'port' => 465,
    'encryption' => 'smtps', // smtps/465 o starttls/587
    'username' => 'info@proyectakonscio.org',
    'password' => 'PON_AQUI_LA_CLAVE_REAL_SOLO_EN_EL_VPS',
    'from_email' => 'info@proyectakonscio.org',
    'from_name' => 'Konscio Housing',
    'to_email' => 'info@proyectakonscio.org',
];
