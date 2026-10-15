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
de correo pueden generar formatos distintos. Esta etapa todavía no configura
Postfix. La pantalla permite obtener la dirección, pero el envío real comenzará
a funcionar cuando Postfix entregue los mensajes al comando de Laravel. La
prueba siguiente simula localmente la entrega
de Postfix al comando de Laravel; no necesita enviar un correo real ni
modificar el servidor.
El campo `From` del ejemplo se conserva dentro del mensaje, pero aún no se usa
para identificar al usuario.

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
