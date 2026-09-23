# Módulos Citas y Cajas

Consultorio Dental CDEO Adriana López Ruelas — 19/09/2026

> **Estado: construido en la versión 2.0.0.** Este documento se
> conserva como la decisión de diseño. Abajo, al final, quedan las
> respuestas que diste y cómo se implementó cada una.

Este documento es para que lo revises y me digas qué se queda, qué se
quita y qué falta, **antes** de que lo programe. Los dos módulos ya
tienen tablas en la base de datos, así que lo que sigue es la pantalla y
las reglas.

---

## 1. Citas

### 1.1 Qué ya existe

- Tablas `cita`, `estado_cita` y `consultorio_sala`.
- Agenda del día con espacios de 30 minutos y el aviso de "por
  confirmar" en el tablero de inicio.
- Seis estados: Pendiente, Confirmada, En sala, Atendida, Cancelada y
  No asistió.

Lo que falta es todo lo que pasa **alrededor** de agendar.

### 1.2 Lo que propongo agregar

**Vista de semana.** Hoy solo se ve un día. En un consultorio con dos
sillones y dos doctoras, la pregunta diaria no es "qué hay hoy" sino
"cuándo la puedo meter". Una rejilla de lunes a sábado con las columnas
por doctora y los huecos libres en blanco responde eso de un vistazo.

**Bloqueos de agenda.** Vacaciones, un congreso, la comida. Sin esto el
sistema ofrece horarios en los que nadie va a estar y alguien acaba
agendando ahí. Serían renglones de "no disponible" con motivo, que
tapan los espacios.

**Horario de atención por doctora.** Hoy los espacios de 30 minutos
salen de un rango fijo. Debería salir del horario real de cada quien:
si la doctora entra a las 2 los martes, el sistema no tiene por qué
ofrecer las 9 de la mañana.

**Duración según el procedimiento.** El catálogo de procedimientos ya
guarda `duracion_min`. Al agendar, elegir "resina en dos órganos" debería
apartar el tiempo que de verdad lleva, no 30 minutos por omisión. Es la
diferencia entre una agenda que se cumple y una que se retrasa desde la
tercera cita.

**Recordatorio por WhatsApp.** No automático: un botón que arma el
mensaje con el nombre, el día y la hora, y lo abre en WhatsApp Web para
que recepción lo revise y lo mande. Mandar solo es fácil de programar y
difícil de arreglar cuando sale mal.

**De la cita a la consulta.** Al marcar una cita como "En sala",
ofrecer crear la consulta ya ligada a esa cita y al plan del paciente.
Hoy esos dos momentos están desconectados y alguien tiene que volver a
teclear los mismos datos.

**Reagendar arrastrando.** En la vista de semana, mover la cita de un
hueco a otro. Con confirmación, porque arrastrar sin querer es fácil.

**Lista de espera.** Pacientes que quieren "lo que salga antes". Cuando
se cancela una cita, el sistema muestra quién estaba esperando ese
horario. Es la funcionalidad que más recupera dinero perdido de todas
las de este documento.

### 1.3 Lo que no propongo

- **Confirmación automática por SMS o correo.** Cuesta dinero mensual y
  el consultorio ya usa WhatsApp.
- **Que el paciente agende solo desde internet.** Implica publicar el
  sistema, y hoy está pensado para correr en la red del consultorio.
  Es un proyecto aparte, no una funcionalidad de este módulo.

---

## 2. Cajas

### 2.1 Qué ya existe

Tablas completas y sin usar: `caja`, `corte_caja`, `forma_pago`,
`concepto_movimiento`, `pago`, `pago_forma`, `pago_aplicacion` y
`movimiento_caja`. También la vista `v_plan_saldo`, que ya calcula
cuánto debe cada plan.

### 2.2 Cómo propongo que funcione

**Apertura y cierre de turno.** Se abre caja con el efectivo con que se
empieza, y al cerrar el sistema dice cuánto debería haber. La diferencia
se captura y se explica. Sin apertura no se puede cobrar: es lo que
hace que el corte signifique algo.

**Cobro contra el plan de tratamiento.** El caso normal del consultorio
no es "pagar una consulta", es ir abonando a un plan de $12,000. La
pantalla de cobro debería:

1. Buscar al paciente.
2. Mostrarle sus planes con saldo, no una lista de conceptos sueltos.
3. Recibir el monto y repartirlo contra el plan (`pago_aplicacion` ya
   está para eso).
4. Aceptar pago mixto: $500 en efectivo y $1,000 con tarjeta en un solo
   movimiento (`pago_forma` ya lo soporta).
5. Imprimir el recibo.

**Recibo.** Mismo criterio que el consentimiento y las recetas: hoja
carta, se imprime, se firma si aplica. Con folio consecutivo.

**Egresos.** Compra de material, pago de laboratorio, gasolina. Sin
esto el corte nunca cuadra y se acaba llevando el control en un
cuaderno, que es justo lo que se quiere evitar.

**Estado de cuenta del paciente.** Una hoja con lo que se le ha cobrado,
lo que ha pagado y lo que debe. Es lo primero que pide un tutor cuando
hay dudas, y hoy la respuesta sale de la memoria de recepción.

**Corte de caja imprimible.** Desglosado por forma de pago, con la
lista de movimientos del turno.

### 2.3 Decisiones que necesito que tomes

Estas cambian bastante el trabajo, por eso las pregunto antes:

1. **¿Una caja o varias?** Las tablas soportan varias. Si es una sola,
   la pantalla se simplifica mucho.
2. **¿Se permite cobrar sin plan de tratamiento?** Una consulta de
   valoración suelta, por ejemplo. Yo diría que sí, cobrando contra la
   consulta.
3. **¿Quién puede cancelar un pago ya registrado?** Mi recomendación:
   nadie lo borra; se registra una cancelación que deja rastro de los
   dos movimientos. Es más trabajo pero es lo correcto para dinero.
4. **¿El recibo lleva folio fiscal?** Si más adelante se va a facturar,
   conviene que el folio del recibo y el de la factura no se confundan
   desde ahora.
5. **¿Hay descuentos autorizados?** Por ejemplo, hermanos o
   trabajadores. Si los hay, ¿los aplica cualquiera o necesitan permiso?

---

## 3. Orden que sugiero

Cajas antes que Citas. La agenda ya funciona para lo esencial —se puede
agendar y ver el día—, mientras que el dinero hoy no está en el sistema
en absoluto. Cada semana que pasa sin el módulo de cobros es una semana
de movimientos que después nadie va a capturar hacia atrás.

Dentro de Cajas, este orden:

1. Apertura y cierre de turno
2. Cobro contra plan, con pago mixto
3. Recibo impreso
4. Egresos
5. Corte imprimible
6. Estado de cuenta del paciente

Los primeros tres ya sirven para trabajar; los otros tres se pueden ir
agregando sin rehacer nada.

---

## 4. Lo que sigue pendiente de antes

Para que quede en un solo lugar:

- **Odontograma interactivo.** El esquema de dientes clickeable con las
  condiciones por cara. Las tablas `odontograma` y `odontograma_detalle`
  ya existen. Ahora que el selector de órganos dentarios está hecho,
  buena parte del dibujo se puede reaprovechar.
- **Facturación (CFDI 4.0).** Tablas listas. Necesita decidir con qué
  proveedor de timbrado se trabaja, que es una decisión de negocio más
  que técnica.
- **Reportes.** Hoy es una pantalla en construcción.


---

# Cómo quedó (versión 2.0.0)

## Tus decisiones

| Pregunta | Tu respuesta | Cómo quedó |
|---|---|---|
| ¿Una caja o varias? | Varias | Tres: Recepción, Consultorio 1 y Consultorio 2. Cada una abre, cierra y corta por su lado. |
| ¿Cobrar sin plan? | Sí | Todo lo cobrable es un *cargo*, venga de un plan, de una consulta o suelto. |
| ¿Quién cancela un pago? | Con permiso, sin borrar | Permiso `cajas.cancelar`. El pago cambia de estatus y se registra el reverso. |
| ¿Cuándo se puede cancelar? | El mismo día | Mismo día **y** con el turno abierto. Ver la nota de abajo. |
| ¿Folio fiscal? | Me tomo tu recomendación | Series separadas. Ver la nota de abajo. |
| ¿Descuentos autorizados? | Con permiso | Permiso `cajas.descuento`. Queda registrado quién lo autorizó y por qué. |

## Dos notas sobre lo que decidí por ti

**La cancelación.** Escribiste "con la intención de afectar el corte de
caja que ya se haya realizado". Lo leí al revés: que la cancelación **no**
debe tocar un corte ya cerrado. Por eso la regla quedó en dos partes —
mismo día *y* turno todavía abierto. Un corte cerrado ya se imprimió y se
firmó; si se pudiera modificar hacia atrás, no habría servido de nada
cerrarlo. Para un cobro de un turno ya cortado, el camino es registrar
una devolución, que sí queda en el corte del día en que se hace. Si lo
que querías era lo contrario, dímelo y lo cambio.

**El folio fiscal.** Mi recomendación, ya implementada: **dos series que
nunca se mezclan.** El recibo interno lleva `RC-0001` y dice en el pie
"documento interno, no es comprobante fiscal". Cuando entre la
facturación, el CFDI tendrá su propia serie, su propio folio y su UUID,
en la tabla `factura` que ya existe. El error caro es reusar el folio del
recibo como folio fiscal: si después cancelas una factura o timbras
fuera de orden, los números dejan de corresponder y reconstruirlo es un
trabajo de días. Separarlos desde ahora no cuesta nada.

## Lo que quedó fuera

- **Horarios de atención por doctor**, como pediste. La agenda sigue
  usando el horario general del consultorio (9 a 20, sábados hasta las
  14). Los bloqueos cubren el caso de "hoy no vengo".
