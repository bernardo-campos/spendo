# Documento de Especificacion de Requerimientos Funcionales (Usuario Final)

## 1. Objetivo
Describir las funcionalidades actualmente implementadas en Spendo para el usuario final, segun el comportamiento observable en la interfaz y en los flujos de negocio activos.

## 2. Alcance
Esta version permite a cada usuario autenticado gestionar su informacion financiera personal:
- Ingresos.
- Egresos.
- Tarjetas de credito/efectivo (catalogo de tarjetas).
- Ciclos reales de facturacion de tarjetas.
- Categorias.
- Tags.
- Visualizacion de resumen mensual.

No se cubren funcionalidades de colaboracion multiusuario, presupuestos, exportaciones ni reportes avanzados.

## 3. Perfiles de usuario
- Visitante (no autenticado): puede registrarse e iniciar sesion.
- Usuario autenticado: puede operar todas las pantallas del sistema y cerrar sesion.

## 4. Funcionalidades implementadas

### RF-01 Registro de cuenta
- El visitante puede crear una cuenta con nombre, email, contrasena y confirmacion.
- Al registrarse correctamente, el sistema inicia sesion automaticamente y redirige a la aplicacion principal.
- El registro exige completar el desafio visible Cloudflare Turnstile antes de crear la cuenta.
- Si el desafio no puede validarse, se conservan nombre y email, se muestra un mensaje generico y no se crea ningun usuario.
- Tras el registro se envia una notificacion para verificar el email. El acceso a la aplicacion requiere que el email este verificado.
- Validaciones:
  - `name` obligatorio, maximo 255.
  - `email` obligatorio, formato email, unico.
  - `password` obligatorio, minimo 8, confirmada.
  - `cf-turnstile-response` obligatorio, maximo 2048 caracteres y validado remotamente.

### RF-02 Inicio de sesion
- El visitante puede iniciar sesion con email y contrasena.
- Opcion de recordar sesion (`remember`).
- Si las credenciales son invalidas, se informa error y se conserva el email cargado.

### RF-03 Cierre de sesion
- El usuario autenticado puede cerrar sesion desde el menu de usuario.
- El sistema invalida la sesion y regenera token CSRF.

### RF-03A Recuperacion y confirmacion de contrasena
- El visitante puede solicitar un enlace de recuperacion por email.
- El usuario puede restablecer la contrasena con un token valido.
- Las operaciones sensibles pueden solicitar la confirmacion de la contrasena actual.

### RF-04 Navegacion principal
- Pantallas principales por tabs:
  - Dashboard.
  - Ingresos.
  - Egresos.
- Menu de usuario:
  - Tarjetas.
  - Categorias.
  - Tags.
  - Cerrar sesion.

### RF-05 Dashboard mensual
- El usuario selecciona un periodo mensual (`YYYY-MM`).
- El sistema muestra:
  - Total de ingresos del periodo.
  - Total de egresos del periodo.
  - Saldo neto del periodo.
- Muestra ultimos movimientos combinando ingresos y egresos, ordenados por fecha.

### RF-06 Gestion de ingresos
- El usuario puede registrar ingresos con:
  - Monto.
  - Descripcion.
  - Categoria (opcional).
  - Tags (opcionales, seleccion multiple).
  - Fecha.
  - Notas (opcional).
- El listado de ingresos se filtra por periodo mensual seleccionado.

### RF-07 Gestion de egresos
- El usuario puede registrar egresos con:
  - Monto.
  - Descripcion.
  - Categoria (opcional).
  - Tags (opcionales).
  - Fecha de compra.
  - Forma de pago: efectivo o credito.
  - Tarjeta (obligatoria si es credito).
  - Cantidad de cuotas (si es credito).
  - Notas (opcional).
- El listado de egresos se filtra por periodo mensual y contempla:
  - Egreso sin cuotas: se usa `payment_date` (fallback `purchase_date`).
  - Egreso en cuotas: se muestran las cuotas cuyo `due_date` cae en el periodo.

### RF-08 Calculo de fecha de pago para tarjeta de credito
- Si el egreso es con credito, el sistema calcula la fecha de pago:
  - Prioriza ciclos reales cargados en la tarjeta.
  - Si no hay ciclos reales aplicables, calcula fecha estimada segun `closing_day` y `due_day`.
- En el formulario, se informa la fecha de primera cuota y si es estimada o real.

### RF-09 Plan de cuotas automatico
- Si el egreso con credito tiene mas de 1 cuota:
  - Se crea automaticamente un plan de cuotas.
  - Se generan cuotas mensuales con reparto de montos, ajustando redondeo en la ultima cuota.
  - Las cuotas nacen con `status = pending`.

### RF-10 Gestion de categorias
- El usuario puede crear, editar y eliminar categorias propias.
- Datos por categoria:
  - Nombre.
  - Slug (autogenerado en frontend desde nombre).
  - Scope: `income`, `expense` o `both`.
- En formularios de transaccion, las categorias disponibles se filtran segun tipo de transaccion.

### RF-11 Gestion de tags
- El usuario puede crear, editar y eliminar tags propios.
- Datos por tag:
  - Nombre.
  - Slug (autogenerado en frontend).
- Los tags pueden asociarse a transacciones (relacion muchos a muchos).

### RF-12 Gestion de tarjetas
- El usuario puede crear, editar y eliminar tarjetas.
- Datos por tarjeta:
  - Nombre.
  - Ultimos 4 digitos.
  - Dia de cierre estimado.
  - Dia de vencimiento estimado.
  - Estado activo.
- Restriccion de integridad:
  - No se puede eliminar una tarjeta que tenga transacciones asociadas.

### RF-13 Gestion de ciclos de facturacion reales
- Para cada tarjeta, el usuario puede gestionar ciclos reales (alta/edicion).
- Cada ciclo incluye:
  - Fecha de cierre.
  - Fecha de vencimiento.
- Regla:
  - `due_date` debe ser posterior a `closing_date`.
- Al guardar ciclos, el sistema sincroniza planes/cuotas pendientes para convertir fechas estimadas en fechas reales cuando corresponda.

### RF-14 Actualizacion automatica de cuotas por ciclos reales
- Al cargar o editar ciclos reales de tarjeta:
  - Se actualizan cuotas pendientes con nuevos `due_date` reales.
  - Se marca `due_date_is_estimated = false` en cuotas afectadas.
  - Se actualiza `first_due_date` del plan.
  - Se actualiza `payment_date` de la transaccion asociada.

### RF-15 Mensajes de estado en UI
- La aplicacion informa estados de carga, errores y confirmaciones de guardado/eliminacion en cada modulo.

## 5. Reglas de negocio clave
- Cada usuario solo puede acceder a sus propios datos (categorias, tags, tarjetas, transacciones, planes).
- Para egresos en credito:
  - `card_id` es obligatorio.
  - `installments_count` es obligatorio en alta de transaccion.
- Formas de pago admitidas: `cash`, `credit`.
- Tipos de transaccion admitidos: `income`, `expense`.
- Montos de transaccion deben ser mayores a 0.

## 6. Requerimientos no funcionales observables
- Interfaz responsive basada en Tailwind CSS.
- Formato de montos en locale `es-AR` en frontend.
- Formato de fechas en visualizacion `dd/mm/yyyy`.
- Seguridad por sesion y CSRF en formularios y peticiones.
- Registro protegido por Cloudflare Turnstile con verificacion obligatoria en servidor.
- La validacion anti-bot bloquea el registro ante token invalido, vencido o reutilizado, hostname/accion incorrectos, configuracion incompleta o indisponibilidad del servicio.
- El secreto de Turnstile nunca se expone en el navegador.

## 7. Limites de la version actual
- No hay administracion de usuarios ni roles.
- No hay edicion/eliminacion de transacciones en la UI principal (aunque existen endpoints API para update/delete).
- No hay modulo de presupuestos, metas ni exportacion de datos.

## 8. Criterios de aceptacion globales (estado actual)
- Usuario humano puede registrarse superando Turnstile, verificar su email, iniciar sesion y operar la app sin acceder a datos de otros usuarios.
- Un registro sin validacion satisfactoria de Turnstile no crea ni autentica usuarios.
- Usuario puede crear y consultar ingresos y egresos por periodo mensual.
- Usuario puede usar pago con credito y obtener calculo de fecha de pago/plan de cuotas.
- Usuario puede mantener catalogos de categorias, tags y tarjetas.
- El sistema protege consistencia al impedir eliminar tarjetas con transacciones asociadas y al sincronizar cuotas cuando hay ciclos reales.
