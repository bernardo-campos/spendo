# Importación de recibos por correo

## Estado actual

La aplicación puede recibir un mensaje entregado por la entrada estándar de
`php artisan receipts:ingest` y guardar el correo original en almacenamiento
privado. Cada usuario puede generar su dirección
`importar+TOKEN@recibos.spendo.bernu.dev` en **Configuración → Importar recibos**.
En esa pantalla puede copiarla o regenerarla; la dirección anterior deja de
funcionar inmediatamente después de regenerarla. Un administrador también
puede obtenerla con `php artisan receipts:address`. El mensaje original se
guarda en privado.

Para los correos HTML de Uber con las marcas reconocidas, tanto originales
como reenviados manualmente en el formato probado, también se extraen
`currency`, `amount`, `origin_address` y `destination_address`. Por ejemplo,
`ARS 2,348.00` se guarda como moneda `ARS` e importe decimal `2348.00`.
El origen y el destino se conservan como direcciones completas. Si el correo
tiene una fecha válida en la cabecera `Date`, una moneda admitida por Spendo y
el enlace «Descargar PDF» de Uber, se crea automáticamente un egreso:

- `purchase_date` y `payment_date`: fecha de la cabecera `Date` del mensaje
  recibido. En un reenvío es la fecha del reenvío.
- Medio de pago: Efectivo. Si se pagó de otra forma, se edita el egreso.
- Descripción: `Viaje Uber: origen → destino`. Las direcciones completas se
  agregan a las notas.
- Estado del recibo: `imported`, con `transaction_id` apuntando al egreso.

Para evitar duplicados se usa una huella del enlace «Descargar PDF», que se
conservó igual en el correo original y el reenvío probados. La combinación
usuario + huella es única. También se detecta la repetición exacta de un
mensaje por su SHA-256. No se guarda el enlace privado en la base de datos.
Si faltan los datos necesarios, el correo queda en estado `pending` y no crea
un gasto.

El reenvío probado llega como `multipart/alternative`: el parser lee su parte
HTML y reconoce las clases que Gmail conserva con un prefijo. Otros clientes
de correo pueden generar formatos distintos. La prueba local siguiente simula
la entrega de Postfix al comando de Laravel, sin enviar un correo real ni
modificar el servidor. La configuración de un servidor está más abajo.
El campo `From` del ejemplo se conserva dentro del mensaje, pero aún no se usa
para identificar al usuario.

## Configurar un dominio receptor y Postfix en Linux

Este ejemplo usa `recibos.ejemplo.com` para las direcciones de importación y
`mail.ejemplo.com` como servidor de correo. Sustituir ambos nombres, la IP, el
usuario de Linux y la ruta de la aplicación por los propios. No sustituir la
configuración de otros dominios que ya atienda el mismo Postfix: incorporar
este dominio a los parámetros correspondientes.

1. Crear los registros DNS y permitir conexiones TCP entrantes al puerto 25
   en el firewall del proveedor y del servidor:

   ```dns
   mail.ejemplo.com.     IN A  203.0.113.10
   recibos.ejemplo.com.  IN MX 10 mail.ejemplo.com.
   ```

   Si se publica un registro `AAAA` para `mail.ejemplo.com`, IPv6 también
   debe llegar al servidor. Comprobar la resolución desde fuera con
   `dig MX recibos.ejemplo.com` y `dig A mail.ejemplo.com`.

2. Definir `RECEIPT_IMPORT_DOMAIN=recibos.ejemplo.com` en el `.env` de
   Laravel. Ese valor determina el dominio de las direcciones que muestra la
   interfaz y acepta `receipts:ingest`. Si la configuración de Laravel está
   cacheada, ejecutar `php artisan config:cache` desde la aplicación.

3. En `/etc/postfix/main.cf`, configurar el dominio virtual, la lista de
   destinatarios válidos y la ruta de entrega:

   ```ini
   virtual_mailbox_domains = recibos.ejemplo.com
   virtual_mailbox_maps = regexp:/etc/postfix/spendo_recipients
   transport_maps = hash:/etc/postfix/spendo_transport
   spendoreceipts_destination_recipient_limit = 1
   ```

   No incluir `recibos.ejemplo.com` en `mydestination` ni en
   `virtual_alias_domains`. `virtual_mailbox_maps` se usa aquí para validar
   destinatarios; la entrega final la hará el transporte `spendoreceipts`.
   Si hay otros mapas o dominios configurados, conservarlos al añadir estos
   valores.

4. Crear `/etc/postfix/spendo_recipients` con una regla para el formato que
   genera Laravel:

   ```text
   /^importar\+[0-9a-f]{40}@recibos\.ejemplo\.com$/ OK
   ```

   Este mapa `regexp:` no requiere compilarse con `postmap`. Acepta cualquier
   dirección con un token de 40 caracteres hexadecimales; no comprueba si el
   token existe en `users`. Una dirección que no coincide normalmente se
   rechaza durante `RCPT TO`, antes de recibir el cuerpo del correo. Una que
   coincide pero tiene un token desconocido se rechaza después, cuando Laravel
   intenta identificar al usuario.

5. Crear `/etc/postfix/spendo_transport` para dirigir el dominio al transporte:

   ```text
   recibos.ejemplo.com spendoreceipts:
   ```

   Compilar este mapa `hash:` con `sudo postmap /etc/postfix/spendo_transport`.
   Repetir ese comando cada vez que se modifique el archivo.

6. Añadir a `/etc/postfix/master.cf` el transporte que ejecuta Laravel. La
   segunda línea debe comenzar con espacios y `appuser` debe ser un usuario
   existente con acceso de lectura a la aplicación y de escritura a su
   almacenamiento y caché:

   ```text
   spendoreceipts unix - n n - - pipe
     flags=Rq user=appuser:appuser argv=/usr/bin/php /srv/spendo/artisan receipts:ingest ${recipient}
   ```

   Postfix reemplaza `${recipient}` por el destinatario del sobre SMTP y lo
   pasa como argumento. El mensaje completo entra por `STDIN`; no se lee una
   casilla IMAP ni se crea un buzón por usuario. El límite de destinatarios del
   paso 3 hace una ejecución del comando por destinatario.

7. Comprobar la configuración y recargar Postfix:

   ```bash
   sudo postmap -q 'importar+0000000000000000000000000000000000000000@recibos.ejemplo.com' regexp:/etc/postfix/spendo_recipients
   sudo postmap -q 'otro@recibos.ejemplo.com' regexp:/etc/postfix/spendo_recipients
   sudo postmap -q 'recibos.ejemplo.com' hash:/etc/postfix/spendo_transport
   sudo postconf -M spendoreceipts/unix
   sudo postfix check
   sudo postfix reload
   ```

   La primera consulta debe devolver `OK`, la segunda no debe devolver nada y
   la tercera debe devolver `spendoreceipts:`. La consulta a `postconf` debe
   mostrar el servicio `pipe` del paso 6. Finalmente, enviar un correo desde
   una cuenta externa a una dirección generada en la interfaz, comprobar el
   registro en `inbound_receipts` y consultar los registros de correo y
   `mailq` si falla. El destinatario se toma del sobre SMTP, no de la cabecera
   `To:` ni del remitente `From:`.

Referencias: [dominios y destinatarios virtuales](https://www.postfix.org/VIRTUAL_README.html),
[mapas de expresiones regulares](https://www.postfix.org/regexp_table.5.html),
[mapas de transporte](https://www.postfix.org/transport.5.html) y
[entrega mediante `pipe`](https://www.postfix.org/pipe.8.html).

## Prueba manual local en PowerShell

Ejecutar los comandos desde la raíz del proyecto. Antes de empezar, comprobar
que `.env` apunta a una **base de datos local**. `migrate` modificará esa base de
datos y `receipts:address` guardará un token en el usuario elegido.

1. Aplicar las migraciones locales:

   ```powershell
   php artisan migrate
   ```

2. Elegir el ID de un usuario de esa base de datos y obtener su dirección. En
   este ejemplo, reemplazar `1` si corresponde:

   ```powershell
   $userId = 1
   $address = (php artisan receipts:address $userId).Trim()
   $address
   ```

   La salida esperada tiene la forma
   `importar+<40 caracteres hexadecimales>@recibos.spendo.bernu.dev`.

3. Crear un correo de prueba en un archivo temporal. El identificador aleatorio
   permite repetir toda la prueba en otro momento con un mensaje nuevo:

   ```powershell
   $sample = Join-Path $env:TEMP 'spendo-recibo.eml'
   $nonce = [guid]::NewGuid().ToString()
   @"
   From: prueba@example.com
   To: $address
   Subject: Recibo Uber de prueba
   Message-ID: <$nonce@local.test>

   Prueba local $nonce
   "@ | Set-Content -LiteralPath $sample -Encoding utf8
   ```

4. Entregar el contenido del archivo al comando por entrada estándar:

   ```powershell
   Get-Content -LiteralPath $sample -Raw | php artisan receipts:ingest $address
   $LASTEXITCODE
   ```

   El código de salida esperado es `0`. El comando no muestra un mensaje de
   éxito; comunica los errores por pantalla y con un código distinto de cero.

5. Consultar el registro creado. Abrir `php artisan tinker` y ejecutar:

   ```php
   DB::table('inbound_receipts')->where('user_id', 1)->latest('id')->first();
   ```

   Reemplazar `1` por el ID elegido. El registro debe tener `status = pending`,
   la dirección utilizada en `recipient` y una ruta `.eml` en `storage_path`.
   Como el mensaje de prueba no tiene el HTML de Uber, `currency`, `amount`,
   `origin_address` y `destination_address` quedan vacíos.
   El archivo se encuentra bajo
   `storage/app/private/inbound-receipts/<ID del usuario>/`; también puede
   comprobarse desde PowerShell:

   ```powershell
   Get-ChildItem ".\storage\app\private\inbound-receipts\$userId"
   ```

6. Repetir el comando del paso 4 **sin recrear el archivo**. Debe volver a
   terminar con código `0`. En Tinker, consultar la cantidad de registros:

   ```php
   DB::table('inbound_receipts')->where('user_id', 1)->count();
   ```

   La cantidad debe ser la misma antes y después de esta segunda entrega. El
   mensaje de prueba no incluye un recibo de Uber reconocible, por lo que
   tampoco se crea un egreso.

Como comprobación automatizada del mismo recorrido, se pueden ejecutar:

```powershell
php artisan test --compact tests/Feature/ReceiptImportTest.php tests/Feature/ReceiptImportCommandTest.php tests/Feature/UberReceiptParserTest.php
```

Estas pruebas no requieren configurar Postfix ni enviar un correo real. La
prueba del comando crea una base SQLite temporal y elimina el correo de prueba
al terminar.

## Probar con un recibo de Uber o con su reenvío

Después de completar los pasos 1 y 2, se puede entregar un archivo `.eml` o
un correo bruto reenviado guardado como `.txt`, sin copiarlo al repositorio.
Reemplazar la ruta por la ubicación local del archivo:

```powershell
$uberEml = 'C:\ruta\al\recibo-uber.eml'
$delivery = Start-Process -FilePath 'php' -ArgumentList @('artisan', 'receipts:ingest', $address) -RedirectStandardInput $uberEml -NoNewWindow -Wait -PassThru
$delivery.ExitCode
```

El código esperado es `0`. En Tinker, consultar el último registro del
usuario y el egreso asociado:

```php
$receipt = DB::table('inbound_receipts')->where('user_id', 1)->latest('id')->first();
$receipt;
App\Models\Transaction::find($receipt->transaction_id);
```

Con el formato de correo probado, el importe se guarda como `2348.00` y la
moneda como `ARS`; origen y destino contienen las direcciones completas.
El egreso debe figurar como Efectivo y usar la fecha de la cabecera `Date`.
Si se vuelve a entregar el mismo archivo, la cantidad de egresos no aumenta.
El archivo del correo puede incluir datos personales y enlaces privados:
conservarlo fuera del repositorio.
