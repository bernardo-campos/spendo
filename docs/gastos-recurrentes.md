# Gastos recurrentes

## Objetivo

Registrar gastos que se repiten cada mes sin crear transacciones para todos los meses futuros. Cada regla define el gasto habitual; el listado de Egresos calcula los cargos del período seleccionado al consultarlo.

## Uso desde la interfaz

En el menú lateral, **Gastos recurrentes** muestra las reglas vigentes y el botón **Agregar gasto recurrente**. La regla incluye descripción, lugar y categoría opcionales, medio de pago, tarjeta cuando corresponde, moneda, día del cargo, fecha de inicio y fecha de fin opcional. Puede pausarse con **Recurrencia activa**.

Si se indican inicio y fin, el formulario muestra una vista previa de los cargos incluidos en ese rango: fecha de cada cargo, fecha estimada o real del vencimiento si se usa tarjeta, importe fijo o variable y cantidad total de repeticiones. Se pueden desplegar las fechas en bloques de 60. Si el día elegido no existe en un mes, se muestra el último día de ese mes. El rango debe incluir al menos un cargo.

La regla admite una nota opcional. Con fecha de fin se puede activar **Agregar la numeración a la nota de cada cargo**. La nota de cada mes añade una línea como `1 de 10`, `2 de 10`, etc. La numeración cuenta los cargos previstos en el rango, aunque se omita individualmente alguno de esos meses. Si se cambia la regla desde un mes posterior, los números continúan dentro de la misma serie. Sin fecha de fin no se ofrece numeración.

Hay dos tipos de importe:

| Tipo | Comportamiento en Egresos |
| --- | --- |
| Fijo | Se muestra automáticamente con el importe de la regla. No requiere confirmación. |
| Variable | Se muestra con `≈` antes de la moneda y el importe. Sugiere el último importe confirmado de la misma serie de reglas; si no existe, muestra `≈ AR$ —` o `≈ USD$ —`. |

Al seleccionar un cargo recurrente en Egresos se abre la edición **de ese mes**. Guardar un importe variable lo confirma. También se puede cambiar el importe o la descripción de un fijo solo para ese mes, o elegir **Eliminar este mes** para omitir el cargo. Estas acciones no modifican la regla ni los demás meses.

Cuando el período seleccionado contiene variables sin confirmar, Egresos muestra **«Tiene importes sin confirmar»** y el botón **Ver sin confirmar**. El mismo botón pasa a **Quitar filtro** al activarse. El dashboard avisa sobre importes variables sin confirmar cuya fecha de cargo ya llegó.

Los importes variables aproximados no se suman a los totales hasta confirmarse. Los fijos visibles y los variables confirmados sí se incluyen.

## Períodos y tarjetas

La fecha del cargo usa el día configurado en la regla. Si el mes no tiene ese día, se utiliza su último día. La regla solo aplica entre su inicio y su fin, mientras esté activa.

Un gasto recurrente con tarjeta se muestra en **el mes del vencimiento de la tarjeta**, igual que un gasto manual con crédito. La fecha del cargo se conserva y se indica en la fila. Cuando se guarda una excepción o se confirma un variable, la transacción conserva la fecha del cargo como `purchase_date` y la fecha del vencimiento como `payment_date`. La edición individual sigue identificando el cargo por su mes de origen, aunque aparezca en otro mes del listado. El listado de transacciones ordinarias excluye esa transacción para evitar duplicados.

No hay un proceso diario ni una cantidad fija de meses generados por adelantado. La vista previa de cada mes se calcula al abrir ese período y no persiste cargos por sí sola.

## Persistencia y cambios de reglas

- `recurring_expenses` guarda la regla: tipo de importe, importe fijo cuando corresponde, moneda, día, vigencia, medio de pago, tarjeta, nota y opción de numeración.
- `recurring_expense_occurrences` guarda únicamente los meses con una decisión individual: `confirmed` o `skipped`. Cada combinación de regla y período es única. Una ocurrencia confirmada puede apuntar a una transacción; una omitida no tiene transacción.
- Un fijo sin cambios individuales se muestra desde la regla y no necesita una transacción persistida. Editarlo crea o actualiza la transacción de ese mes. Omitirlo guarda la excepción para que no vuelva a aparecer.
- Confirmar un variable crea o actualiza su transacción. El último importe confirmado se usa solo como sugerencia para los meses siguientes.
- Cambiar una regla desde un período posterior crea una nueva versión vinculada por `series_key`. Los importes ya confirmados permanecen en sus transacciones. Si ya hay meses confirmados u omitidos desde el período elegido, la actualización de la regla se rechaza y debe elegirse otro período.

## Endpoints

Las rutas web autenticadas están disponibles también bajo `/api/v1`:

| Método | Ruta | Uso |
| --- | --- | --- |
| `GET` | `/recurring-expenses` | Listar reglas vigentes. |
| `POST` | `/recurring-expenses` | Crear una regla. |
| `PUT` | `/recurring-expenses/{id}` | Cambiar una regla desde `effective_period`. |
| `GET` | `/recurring-expenses/preview?period=YYYY-MM` | Calcular los cargos del mes sin persistirlos. |
| `PUT` | `/recurring-expenses/{id}/decisions` | Guardar, volver a editar u omitir un mes mediante `period`, `status`, `amount` y, opcionalmente, `description`. |
| `POST` | `/recurring-expenses/{id}/decisions` | Registrar por primera vez una confirmación u omisión; rechaza decisiones repetidas. |

La confirmación de un variable antes de su fecha de cargo se rechaza. Todas las operaciones verifican que la regla pertenezca al usuario autenticado.
