<?php
/**
 * Vista detalle_paciente.
 * Card de identificacion a la izquierda + card grande con pestanias:
 * Identificacion, Canales de contacto, Domicilio y Tutor.
 */

$idExpediente = paramInt('id');

$pac = $idExpediente
    ? Conexion::uno(
        'SELECT vp.*, rp.fecha_apertura, rp.observaciones,
                rp.requiere_tutor, rp.tutor_decidido_edad,
                p.id_genero, p.curp, p.id_tipo_persona
           FROM v_paciente vp
           JOIN registro_paciente rp ON rp.id_registro_paciente = vp.id_registro_paciente
           JOIN persona p ON p.id_persona = vp.id_persona
          WHERE vp.id_registro_paciente = :id',
        [':id' => $idExpediente])
    : null;

if ($pac === null) {
    echo '<div class="alert alert-warning d-flex align-items-center gap-2">'
       . '<i class="bi bi-exclamation-triangle"></i>'
       . 'No se encontr&oacute; el expediente solicitado. '
       . '<a href="' . URL_BASE . '/index.php?modulo=paciente&vista=buscar_paciente">Volver a la b&uacute;squeda</a>'
       . '</div>';
    return;
}

$idPersona = (int) $pac['id_persona'];

/*
   ¿Este paciente lleva tutor, y hay que preguntarlo?

   Las dos respuestas salen de Tutoria y no se calculan aqui: la misma
   regla la usan el guardado, los documentos impresos y esta pantalla, y
   repartida acabarian discrepando sobre si toca preguntar. El sintoma
   seria un aviso que sale y no se puede quitar.
*/
$edadPac       = $pac['edad'] !== null ? (int) $pac['edad'] : null;
$requiereTutor = $pac['requiere_tutor'] !== null
    ? (int) $pac['requiere_tutor'] : null;

$llevaTutor   = Tutoria::llevaTutor($edadPac, $requiereTutor);
$preguntarTutor = Tutoria::hayQuePreguntar(
    $edadPac, $requiereTutor,
    $pac['tutor_decidido_edad'] !== null
        ? (int) $pac['tutor_decidido_edad'] : null);

$contactos = Conexion::todos(
    'SELECT cp.*, mc.nombre_medio_contacto, mc.tipo_dato
       FROM contacto_persona cp
       JOIN medio_contacto mc ON mc.id_medio_contacto = cp.id_medio_contacto
      WHERE cp.id_persona = :p AND cp.estatus = 1
      ORDER BY cp.es_principal DESC, cp.id_contacto_persona',
    [':p' => $idPersona]
);

$domicilios = Conexion::todos(
    'SELECT * FROM v_domicilio WHERE id_persona = :p AND estatus = 1
      ORDER BY es_principal DESC, id_domicilio',
    [':p' => $idPersona]
);

$tutores = Conexion::todos(
    'SELECT et.id_expediente_tutor, et.id_parentesco, et.es_responsable_pago,
            p.id_persona, p.nombres, p.ap_paterno, p.ap_materno, p.rfc,
            pa.nombre_parentesco,
            (SELECT CONCAT_WS(", ", vd.calle_numero, vd.colonia, vd.nombre_municipio)
               FROM v_domicilio vd
              WHERE vd.id_persona = p.id_persona AND vd.estatus = 1
              ORDER BY vd.es_principal DESC LIMIT 1) AS domicilio
       FROM expediente_tutor et
       JOIN persona p ON p.id_persona = et.id_persona
       LEFT JOIN parentesco pa ON pa.id_parentesco = et.id_parentesco
      WHERE et.id_registro_paciente = :r AND et.estatus = 1',
    [':r' => $idExpediente]
);

// Mismo origen que el plan y la consulta: la historia clinica. Ver el
// comentario en AlertasClinicas sobre por que no expediente_antecedente.
$alertas = AlertasClinicas::de($idExpediente);

// La discapacidad, aparte y en rosa: no es una alerta mas. Cambia como
// se maneja la cita entera —cuanto dura, quien acompaniia, como se
// explica el procedimiento— y quien abre la ficha tiene que verla sin
// buscarla.
$discapacidad = null;
foreach ($alertas as $al) {
    if ($al['clave'] === 'discapacidad') {
        $discapacidad = $al['detalle'] !== ''
            ? $al['detalle'] : 'Sin especificar';
    }
}

// Cuantos documentos le faltan por escanear. Decide si la pestania del
// expediente se resalta.
$pendientes = Documentos::cuantosPendientes($idExpediente);

// El saldo se muestra en la tarjeta: en recepcion es lo primero que se
// pregunta y no tiene caso mandarlos a otra pantalla por un numero.
$saldo = Auth::puede('cajas.ver')
    ? Conexion::uno(
        'SELECT * FROM v_paciente_saldo WHERE id_registro_paciente = :r',
        [':r' => $idExpediente])
    : null;

$tiposIdentificacion = Conexion::todos(
    'SELECT * FROM tipo_identificacion WHERE estatus = 1
      ORDER BY nombre_identificacion');

$generos    = Conexion::todos('SELECT * FROM genero ORDER BY id_genero');
$medios     = Conexion::todos('SELECT * FROM medio_contacto ORDER BY id_medio_contacto');
$parentescos = Conexion::todos('SELECT * FROM parentesco ORDER BY id_parentesco');
$estados    = Conexion::todos('SELECT * FROM estado ORDER BY nombre_estado');
$estadoDef  = (int) parametro('estado_default', (string) ESTADO_DEFAULT);

// Si ya hay un domicilio, la parte superior del formulario se prellena
// con el primero encontrado (tal como pide la composicion).
$domPre = $domicilios[0] ?? null;

// ---------------------------------------------------------------------
// Lo que alimenta las tarjetas de los lados
// ---------------------------------------------------------------------
// Todas con limite. Un paciente de anios acumula decenas de citas y de
// consultas, y la ficha es un resumen: lo que quiere quien la abre es
// "¿como va este paciente?", no el historial completo. Para eso estan
// las pantallas de cada modulo.
// Sin "AND estatus = 1": la tabla cita NO tiene esa columna, al reves
// que casi todas las demas. Una cita no se da de baja, cambia de
// estado: cancelada y no-asistio son informacion clinica y de agenda
// que hay que poder ver, no filas que esconder.
$citas = Conexion::todos(
    'SELECT c.id_cita, c.fecha_inicio, c.fecha_fin, c.motivo, c.notas,
            c.id_estado_cita, c.id_usuario_dentista, c.id_sala,
            ec.nombre_estado, ec.color, u.nombre_completo AS dentista,
            s.nombre_sala
       FROM cita c
       JOIN estado_cita ec ON ec.id_estado_cita = c.id_estado_cita
       JOIN usuario u ON u.id_usuario = c.id_usuario_dentista
       LEFT JOIN consultorio_sala s ON s.id_sala = c.id_sala
      WHERE c.id_registro_paciente = :r
      ORDER BY c.fecha_inicio DESC
      LIMIT 200',
    [':r' => $idExpediente]
);

/*
   Como le ha ido a este paciente con sus citas.

   Son cuatro numeros que contestan de un golpe una pregunta que en
   recepcion se hace mucho: "¿este niño viene o no viene?". Tres faltas
   sin avisar cambian como se le agenda la siguiente.

   Se cuenta sobre lo que ya se trajo en vez de pedir cinco COUNT mas a
   la base: la lista completa ya esta aqui, y sumar en PHP sobre un
   arreglo de doscientos renglones no cuesta nada.
*/
$citasPorEstado = ['pendientes' => 0, 'atendidas' => 0,
                   'canceladas' => 0, 'no_asistio' => 0];

foreach ($citas as $ci) {
    switch ((int) $ci['id_estado_cita']) {
        // 1 pendiente, 2 confirmada y 3 en sala son la misma cosa para
        // quien mira la ficha: citas que todavia no se han resuelto.
        case 1: case 2: case 3: $citasPorEstado['pendientes']++; break;
        case 4: $citasPorEstado['atendidas']++;  break;
        case 5: $citasPorEstado['canceladas']++; break;
        case 6: $citasPorEstado['no_asistio']++; break;
    }
}

$cuantasCitas = count($citas);

// El tope de cinco y el total. El enlace de "ver todos" solo aparece
// si de verdad hay mas: con tres planes, ya se ven los tres.
$cuantosPlanes = (int) Conexion::valor(
    'SELECT COUNT(*) FROM plan_tratamiento WHERE id_registro_paciente = :r',
    [':r' => $idExpediente]);

$planes = Conexion::todos(
    'SELECT pt.id_plan_tratamiento, pt.folio, pt.descripcion, pt.fecha,
            pt.total, pt.id_estado_plan, ep.nombre_estado
       FROM plan_tratamiento pt
       JOIN estado_plan ep ON ep.id_estado_plan = pt.id_estado_plan
      WHERE pt.id_registro_paciente = :r
      ORDER BY pt.fecha DESC, pt.id_plan_tratamiento DESC
      LIMIT 5',
    [':r' => $idExpediente]
);

/*
   Como va cada plan en dinero.

   Va DESPUES de la consulta que llena $planes. Estaba antes, y PHP no
   se queja de recorrer una variable que no existe: avisa y sigue con un
   arreglo vacio. Resultado: dos Warnings arriba de la pantalla y, lo
   que importa mas, ningun plan con su avance en dinero... sin que nada
   dijera que faltaba. El aviso amarillo se lee como adorno; la columna
   vacia se lee como "este paciente no debe nada".

   Se calcula aqui, junto a los planes, y no dentro del bucle que los
   pinta: asi el renglon del plan solo decide COMO se ve, no de donde
   salen los numeros. Y si manana hace falta el mismo dato en otra
   pantalla, se pide a Cargos::avancePlan y no se copia esta vista.
*/
$avancePlan = [];
if (Auth::puede('cajas.ver')) {
    foreach ($planes as $pl) {
        $avancePlan[(int) $pl['id_plan_tratamiento']] =
            Cargos::avancePlan((int) $pl['id_plan_tratamiento']);
    }
}

$cuantasConsultas = (int) Conexion::valor(
    'SELECT COUNT(*) FROM consulta
      WHERE id_registro_paciente = :r AND estatus = 1',
    [':r' => $idExpediente]);

$consultas = Conexion::todos(
    'SELECT c.id_consulta, c.folio, c.fecha_hora, c.motivo, c.cerrada,
            pt.folio AS plan_folio
       FROM consulta c
       LEFT JOIN plan_tratamiento pt
              ON pt.id_plan_tratamiento = c.id_plan_tratamiento
      WHERE c.id_registro_paciente = :r AND c.estatus = 1
      ORDER BY c.fecha_hora DESC
      LIMIT 5',
    [':r' => $idExpediente]
);

// Los pendientes de digitalizar: aqui si conviene verlos todos, porque
// la tarjeta existe para que alguien los resuelva, no para informar.
$porEscanear = Conexion::todos(
    'SELECT d.id_documento, d.descripcion, d.fecha_solicitud,
            td.nombre_documento, td.icono, td.formatos
       FROM documento d
       JOIN tipo_documento td ON td.id_tipo_documento = d.id_tipo_documento
      WHERE d.id_registro_paciente = :r AND d.estatus = 1 AND d.estado = 0
      ORDER BY d.fecha_solicitud
      LIMIT 12',
    [':r' => $idExpediente]
);

// Cuando se actualizo la historia clinica. La tarjeta lo muestra para
// que se vea de un golpe si esta al dia o si nunca se capturo.
$historia = Conexion::uno(
    'SELECT fecha_captura, fecha_modif
       FROM historia_clinica WHERE id_registro_paciente = :r',
    [':r' => $idExpediente]
);

// Colores del estatus del plan, los mismos que usa el expediente.
$colorPlan = [1 => 'plan-propuesto', 2 => 'plan-aceptado', 3 => 'plan-proceso',
              4 => 'plan-terminado', 5 => 'plan-cancelado'];
?>

<div id="pacienteContexto" class="d-none"
     data-id-expediente="<?= $idExpediente ?>"
     data-id-persona="<?= $idPersona ?>"
     data-edad="<?= $edadPac ?? '' ?>"
     <?php /* La pregunta la decide PHP y no el navegador leyendo la
              edad: la regla vive en un solo sitio, y asi el aviso no
              puede aparecer por un calculo distinto del que usa el
              guardado. */ ?>
     data-preguntar-tutor="<?= $preguntarTutor ? '1' : '' ?>"></div>

<?php /* Sin menu de modulo a la izquierda.

         Tenia tres enlaces —buscar paciente, nuevo paciente, agenda,
         cobro— que ya estan en la barra de arriba, y se llevaba 250
         pixeles de ancho en la pantalla donde mas datos hay que
         acomodar. */ ?>
<div class="row g-3">
    <div class="col-12">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <nav style="font-size:12px" class="mb-1">
                    <a href="<?= URL_BASE ?>/index.php?modulo=paciente&vista=buscar_paciente"
                       class="text-decoration-none">
                        <i class="bi bi-arrow-left"></i> Buscar paciente
                    </a>
                </nav>
                <h1 class="titulo-vista">Detalle del paciente</h1>
            </div>
            <div class="d-flex gap-2">
                <?php /* El consentimiento ya no vive aqui: pertenece a un plan
                         de tratamiento y se pide desde el bloque del plan, en
                         la pestania Expediente electronico. */ ?>
                <?php if (Auth::puede('consultas.crear')): ?>
                <button type="button" class="btn btn-outline-morado"
                        data-nueva-consulta="<?= $idExpediente ?>">
                    <i class="bi bi-clipboard2-plus me-1"></i> Nueva consulta
                </button>
                <?php endif; ?>
                <?php /* Ventana centrada, no pestania nueva.

                         target="_blank" abre una pestania mas, y con
                         cuatro pestanias identicas del mismo sistema
                         nadie sabe cual es cual. Las ventanas de
                         impresion se abren al centro y con su propio
                         tamanio, como las demas del sistema. */ ?>
                <?php if (Auth::puede('cajas.estado_cuenta')): ?>
                <button type="button" class="btn btn-outline-morado"
                        data-estado-cuenta="<?= $idExpediente ?>">
                    <i class="bi bi-cash-coin me-1"></i> Estado de cuenta
                </button>
                <?php endif; ?>
                <?php if (Auth::puede('consultas.plan')): ?>
                <a class="btn btn-morado"
                   href="<?= URL_BASE ?>/index.php?modulo=consultas&vista=nuevo_plan_tratamiento&id=<?= $idExpediente ?>">
                    <i class="bi bi-list-check me-1"></i> Nuevo plan de tratamiento
                </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="row g-3">
            <?php /* Tarjeta de identidad compacta.

                     Antes era una columna alta con la foto en circulo
                     y los datos en renglones. Ocupaba el alto de media
                     pantalla para decir cuatro cosas, y empujaba hacia
                     abajo lo que de verdad se consulta.

                     Ahora es una franja: foto a la izquierda, lo que se
                     pregunta en recepcion a la derecha. El boton de
                     cambiar fotografia desaparecio; en su lugar hay un
                     lapiz sobre la foto, que es donde uno lo busca. */ ?>
            <div class="col-12">
                <div class="card tarjeta-identidad-compacta">
                    <div class="card-body">

                        <div class="retrato">
                            <?php /* La foto se abre en grande al darle clic.
                                     En el circulo mide 64 pixeles y lo que se
                                     quiere comprobar —que es el paciente
                                     correcto— no se ve a ese tamanio. */ ?>
                            <img class="<?= empty($pac['foto']) ? 'd-none' : '' ?>"
                                 id="fotoPaciente"
                                 src="<?= URL_BASE ?>/avatar.php?p=<?= $idPersona ?>"
                                 data-ver-foto="<?= $idPersona ?>"
                                 title="Ver la fotograf&iacute;a en grande"
                                 alt="Fotograf&iacute;a del paciente">
                            <div class="iniciales <?= empty($pac['foto']) ? '' : 'd-none' ?>"
                                 id="inicialesPaciente"><?= e(iniciales($pac['nombre_completo'])) ?></div>

                            <?php if (Auth::puede('paciente.editar')): ?>
                            <button type="button" class="lapiz-foto" data-cambiar-foto="1"
                                    title="<?= empty($pac['foto'])
                                        ? 'Subir fotografía' : 'Cambiar fotografía' ?>">
                                <i class="bi bi-pencil-fill"></i>
                            </button>
                            <?php endif; ?>
                        </div>

                        <div class="identidad">
                            <div class="nombre"><?= e($pac['nombre_completo']) ?></div>
                            <div class="renglones">
                                <span><i class="bi bi-person-badge"></i>
                                      <?= e($pac['num_expediente']) ?></span>
                                <?php if ($pac['edad'] !== null): ?>
                                <span><i class="bi bi-cake2"></i>
                                      <?= (int) $pac['edad'] ?> a&ntilde;os</span>
                                <?php endif; ?>
                                <span><i class="bi bi-telephone"></i>
                                      <?= e($pac['telefono'] ?: 'Sin teléfono') ?></span>
                                <?php
                                // Domicilio resumido: calle, colonia y
                                // municipio. El estado y el codigo postal
                                // no ayudan a nadie en recepcion.
                                $dom = $domPre
                                    ? implode(', ', array_filter([
                                        $domPre['calle_numero'] ?? null,
                                        $domPre['colonia'] ?? null,
                                        $domPre['nombre_municipio'] ?? null]))
                                    : '';
                                ?>
                                <?php if ($dom !== ''): ?>
                                <span title="<?= e($dom) ?>">
                                    <i class="bi bi-geo-alt"></i> <?= e($dom) ?>
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($alertas): ?>
                        <div class="alertas-identidad">
                            <?php foreach ($alertas as $a): ?>
                            <?php /* La discapacidad lleva su propio
                                     color y su icono, y dice el
                                     detalle completo en vez de
                                     esconderlo en el titulo: es el
                                     dato que cambia como se atiende a
                                     esta persona. */ ?>
                            <?php if ($a['clave'] === 'discapacidad'): ?>
                            <span class="chip-estado chip-discapacidad">
                                <i class="bi bi-person-wheelchair"></i>
                                Paciente con discapacidad<?= $a['detalle'] !== ''
                                    ? ': ' . e($a['detalle']) : '' ?>
                            </span>
                            <?php else: ?>
                            <span class="chip-estado chip-alerta-clinica"
                                  title="<?= e($a['detalle']) ?>">
                                <?= e($a['titulo']) ?><?= $a['detalle'] !== ''
                                    ? ': ' . e($a['detalle']) : '' ?>
                            </span>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php /* Tres pestanias.

                     Historia y expediente habian salido a pantalla
                     propia, pero en el uso diario obligaban a ir y
                     volver por el camino largo. Siguen existiendo como
                     direccion propia —sirven para enlazar desde una
                     impresion, por ejemplo— pero el camino normal
                     vuelve a ser la pestania.

                     Lo que NO vuelve es meterlas en una columna
                     angosta: la pestania les da el ancho entero. */ ?>
            <div class="col-12">
                <ul class="nav nav-tabs nav-tabs-morado mb-3" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" data-bs-toggle="tab"
                                data-bs-target="#tab-datos" type="button">
                            <i class="bi bi-person-vcard me-1"></i> Datos generales
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab"
                                data-bs-target="#tab-historia" type="button"
                                id="btnTabHistoria">
                            <i class="bi bi-clipboard2-pulse me-1"></i> Historia cl&iacute;nica
                            <?php /* El aviso lleva id y sale siempre en el HTML,
                                     escondido si no hace falta.

                                     Antes solo existia cuando faltaba la
                                     historia, asi que al guardarla el aviso
                                     seguia ahi: la pestania decia "pendiente"
                                     sobre una historia ya capturada, y solo se
                                     quitaba al recargar la pagina. Un aviso que
                                     miente es peor que no tenerlo, porque
                                     ensenia a ignorar los avisos.

                                     Estando siempre en el HTML, el JavaScript
                                     lo puede quitar en el momento en que la
                                     historia se guarda, sin recargar. */ ?>
                            <span class="badge badge-pendiente ms-1<?= $historia === null ? '' : ' d-none' ?>"
                                  id="avisoTabHistoria">!</span>
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab"
                                data-bs-target="#tab-expediente" type="button"
                                id="btnTabExpediente">
                            <i class="bi bi-folder2-open me-1"></i> Expediente electr&oacute;nico
                            <?php if ($pendientes > 0): ?>
                            <span class="badge badge-pendiente ms-1"><?= $pendientes ?></span>
                            <?php endif; ?>
                        </button>
                    </li>
                </ul>
            </div>

            <?php /* Las pestanias cambian SOLO esta columna.

                     Antes el contenido de la pestania envolvia tambien
                     las tarjetas de la derecha, asi que al pasar a
                     historia clinica desaparecian los planes, las
                     consultas y el saldo. Eso obligaba a volver a datos
                     generales para mirar un numero, y despues regresar.

                     Las tarjetas dicen COMO VA el paciente; las
                     pestanias, QUE se esta mirando de el. Son dos
                     preguntas distintas y no tienen por que taparse. */ ?>
            <div class="col">
              <div class="tab-content">
                <div class="tab-pane fade show active" id="tab-datos">
                <?php /* Datos planos con su lapicito, no un formulario.

                         El 95% de las veces nadie entra aqui a EDITAR:
                         entra a LEER —"¿cual es el telefono?",
                         "¿cuantos anios tiene?"— y treinta cajas de
                         captura estorban para eso. Un dato plano se lee
                         de un vistazo.

                         El lapiz abre UN campo, no la seccion entera:
                         lo que se corrige es un dato suelto, y abrir
                         doce para tocar uno obliga a revisar los once
                         que no querias mover. */ ?>
                <div class="card ficha">
                    <div class="card-body">
                      <div class="row g-0">

                        <?php /* Dos columnas dentro de la misma tarjeta.

                                 Los campos de identificacion ocupaban el
                                 ancho entero y dejaban media tarjeta en
                                 blanco, mientras contacto, tutor y
                                 domicilio vivian escondidos en
                                 colapsables que nadie abria.

                                 Juntos se ve de un golpe quien es el
                                 paciente Y como se le localiza, que son
                                 las dos preguntas de recepcion. */ ?>
                        <div class="col-7 pe-3">
                        <div class="ficha-seccion">
                            <i class="bi bi-person-vcard"></i>
                            <span>Datos generales</span>
                        </div>
                        <form id="formIdentificacion" autocomplete="off"
                              data-endpoint="paciente/actualizar_identificacion.php">
                            <input type="hidden" name="id_persona" value="<?= $idPersona ?>">

                            <div class="row g-0">
                                <?php /* Tres filas, en el orden en que se
                                         dictan los datos:

                                         nombre completo y nacimiento
                                         genero, RFC, ocupacion, alta
                                         tutor

                                         Cuatro campos por fila = col-3.
                                         El tutor va solo porque su
                                         nombre es largo y partirlo en
                                         dos renglones se lee peor que
                                         dejarle la fila entera. */ ?>

                                <?= Ficha::campo('nombres', 'Nombres', $pac['nombres'],
                                      ['requerido' => true, 'maxlength' => 80, 'ancho' => 4]) ?>
                                <?= Ficha::campo('ap_paterno', 'Apellido paterno',
                                      $pac['ap_paterno'],
                                      ['requerido' => true, 'maxlength' => 60, 'ancho' => 4]) ?>
                                <?= Ficha::campo('ap_materno', 'Apellido materno',
                                      $pac['ap_materno'], ['maxlength' => 60, 'ancho' => 4]) ?>

                                <?= Ficha::campo('fecha_nacim', 'Fecha de nacimiento',
                                      $pac['fecha_nacim'],
                                      ['tipo' => 'date', 'ancho' => 4,
                                       'mostrado' => $pac['fecha_nacim']
                                           ? fecha($pac['fecha_nacim'])
                                             . ($pac['edad'] !== null
                                                 ? ' · ' . (int) $pac['edad'] . ' años' : '')
                                           : null]) ?>

                                <?php
                                $listaGeneros = ['' => 'Seleccionar...'];
                                foreach ($generos as $g) {
                                    $listaGeneros[(int) $g['id_genero']] = $g['nombre_genero'];
                                }
                                ?>
                                <?= Ficha::campo('id_genero', 'G&eacute;nero',
                                      (string) ($pac['id_genero'] ?? ''),
                                      ['lista' => $listaGeneros, 'ancho' => 4,
                                       'mostrado' => $listaGeneros[(int) ($pac['id_genero'] ?? 0)] ?? null]) ?>

                                <?= Ficha::campo('rfc', 'RFC', $pac['rfc'],
                                      ['maxlength' => 13, 'ancho' => 4]) ?>

                                <?php /* La ocupacion se elige en un modal con
                                         buscador: son cientos y una lista
                                         desplegable no se puede recorrer. Por eso
                                         va fija aqui y se edita desde su boton. */ ?>
                                <div class="col-4">
                                    <div class="campo-ficha" data-campo="ocupacion">
                                        <span class="cf-etiqueta">Ocupaci&oacute;n</span>
                                        <div class="cf-fila">
                                            <span class="cf-valor <?= $pac['nombre_ocupacion'] ? '' : 'vacio' ?>"
                                                  id="ocupacionTexto"
                                                  data-selector="ocupacion"
                                                  data-destino="ocupacionId">
                                                <?= e($pac['nombre_ocupacion'] ?: 'Sin capturar') ?>
                                            </span>
                                            <?php if (Auth::puede('paciente.editar')): ?>
                                            <button type="button" class="cf-lapiz selector-modal"
                                                    data-selector="ocupacion"
                                                    data-destino="ocupacionId"
                                                    title="Cambiar ocupación">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                        <input type="hidden" name="id_ocupacion" id="ocupacionId"
                                               value="<?= e((string) ($pac['id_ocupacion'] ?? '')) ?>">
                                        <div class="form-text" id="ocupacionAviso"></div>
                                    </div>
                                </div>

                                <?= Ficha::campo('expediente_abierto', 'Expediente abierto el',
                                      $pac['fecha_apertura'],
                                      ['editable' => false, 'ancho' => 4,
                                       'mostrado' => fecha($pac['fecha_apertura'])]) ?>

                                <?php /* Requiere tutor, como un campo mas.

                                         Solo para mayores de edad: en un
                                         menor no hay nada que decidir y
                                         ofrecer el interruptor invitaria a
                                         apagarlo por error, dejando un
                                         expediente de un ninio sin quien
                                         firme por el. */ ?>
                                <?php if ($edadPac !== null
                                          && $edadPac >= Tutoria::MAYORIA_EDAD): ?>
                                <div class="col-4">
                                    <?= Ficha::campo('requiere_tutor', 'Requiere tutor',
                                          $requiereTutor === null
                                              ? '' : (string) $requiereTutor,
                                          ['lista' => ['' => 'Sin definir',
                                                       '1' => 'Si, lleva tutor',
                                                       '0' => 'No, responde por si mismo'],
                                           'mostrado' => $requiereTutor === null
                                               ? null
                                               : ($requiereTutor === 1
                                                   ? 'Si, lleva tutor'
                                                   : 'No, responde por si mismo')]) ?>
                                </div>
                                <?php endif; ?>

                                <?php /* La fotografia, como un campo mas.

                                         Vivia solo en la tarjeta de arriba, y
                                         ahi no hay forma de descargarla ni de
                                         quitarla: el lapiz solo reemplaza. Como
                                         campo tiene las cuatro acciones que
                                         tiene cualquier otro documento del
                                         expediente, que es lo que es. */ ?>
                                <div class="col-4">
                                    <?php require __DIR__ . '/_campo_fotografia.php'; ?>
                                </div>

                            </div>
                        </form>

                        <?php /* El tutor cierra los datos generales en vez de
                                 vivir en la columna de al lado: es un dato de
                                 la persona, igual que el genero o la
                                 ocupacion, y leerlo aqui ahorra cruzar la
                                 vista. */ ?>
                        <?php $bloquesResumen = ['tutor']; ?>
                        <?php require __DIR__ . '/_resumen_contacto.php'; ?>

                        <?php /* El domicilio, bajo el tutor y a su mismo ancho.

                                 Estaba a todo lo ancho, al fondo de la
                                 tarjeta, y era un muro: nada podia ir debajo
                                 sin empujarlo. Al mismo ancho que el tutor
                                 cabe igual —es una direccion, no un parrafo—
                                 y libera la columna de al lado para que los
                                 pendientes crezcan hacia abajo. */ ?>
                        <?php $bloquesResumen = ['domicilio']; ?>
                        <?php require __DIR__ . '/_resumen_contacto.php'; ?>
                        </div>

                        <div class="col-5 ps-3 borde-izq">
                            <?php $bloquesResumen = ['contacto']; ?>
                            <?php require __DIR__ . '/_resumen_contacto.php'; ?>

                            <?php /* Los pendientes de digitalizar, aqui.

                                     Vivian abajo a la derecha, en la quinta
                                     tarjeta de la rejilla, y ahi llegaban
                                     tarde: son la lista de lo que hay que
                                     pedirle al tutor que TRAIGA, y el momento
                                     de leerla es mientras se le tiene
                                     enfrente mirando sus datos, no al final
                                     de la pantalla.

                                     Debajo de los canales de contacto por lo
                                     mismo: ahi es donde se mira para decir
                                     "¿le mando el recordatorio por WhatsApp?",
                                     y en ese mismo gesto se ve que falta la
                                     credencial del tutor.

                                     Crece hacia abajo sin chocar con nada,
                                     que es lo que no podia hacer cuando el
                                     domicilio ocupaba el ancho entero. */ ?>
                            <?php require __DIR__ . '/_pendientes_ficha.php'; ?>
                        </div>
                      </div><!-- /row g-0 -->
                    </div><!-- /card-body -->
                </div><!-- /card ficha -->
                </div><!-- /tab-datos -->

                <div class="tab-pane fade" id="tab-historia">
                    <?php require __DIR__ . '/_tab_historia.php'; ?>
                </div>

                <div class="tab-pane fade" id="tab-expediente">
                    <?php require __DIR__ . '/_tab_expediente.php'; ?>
                </div>
              </div><!-- /tab-content -->
            </div><!-- /col: pestanias y su contenido -->

            <!-- ============ Derecha: siempre visible ============ -->
            <?php /* Dos columnas, no una.

                     En una sola columna las cinco tarjetas se apilaban y
                     las dos ultimas quedaban abajo del doblez: habia que
                     bajar para ver las consultas, que es justo lo que se
                     abre la ficha para ver. En dos columnas caben las
                     cinco de un vistazo y la pantalla deja de tener
                     contenido escondido.

                     Lo que se empareja tampoco es casual: planes con
                     estado de cuenta (lo que se le propuso y lo que
                     debe) y consultas con citas (lo que ya paso y lo que
                     viene). Los pendientes van abajo a todo lo ancho
                     porque son renglones largos. */ ?>
            <div class="col-auto tarjetas-ficha">
              <div class="row g-3">

                <div class="col-6">

                <?php /* Planes de tratamiento */ ?>
                <div class="card tarjeta-ficha mb-3">
                    <div class="card-header">
                        <span><i class="bi bi-list-check me-2"></i>Planes de tratamiento</span>
                        <?php if (Auth::puede('consultas.plan')): ?>
                        <a class="btn-mas"
                           href="<?= URL_BASE ?>/index.php?modulo=consultas&vista=nuevo_plan_tratamiento&id=<?= $idExpediente ?>"
                           title="Nuevo plan de tratamiento">
                            <i class="bi bi-plus-lg"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body py-2">
                        <?php if (!$planes): ?>
                            <div class="exp-vacio">Sin planes de tratamiento.</div>
                        <?php else: foreach ($planes as $pl): ?>
                        <a class="renglon-ficha"
                           href="<?= URL_BASE ?>/index.php?modulo=consultas&vista=nuevo_plan_tratamiento&id=<?= $idExpediente ?>&plan=<?= (int) $pl['id_plan_tratamiento'] ?>">
                            <div style="min-width:0">
                                <div class="rf-titulo"><?= e($pl['folio']) ?></div>
                                <div class="rf-sub"><?= e(fecha($pl['fecha'])) ?>
                                    &middot; <?= e(money($pl['total'])) ?></div>
                                <?php if ($pl['descripcion']): ?>
                                <div class="rf-sub"><?= e($pl['descripcion']) ?></div>
                                <?php endif; ?>

                                <?php /* El anticipo, si lo hay.

                                         Es el dato que evita la discusion
                                         en el mostrador: "dejo dos mil a
                                         cuenta de su ortodoncia". Se ve
                                         sin abrir el plan porque la
                                         pregunta se hace mientras el
                                         paciente esta de pie enfrente. */ ?>
                                <?php
                                $av = $avancePlan[(int) $pl['id_plan_tratamiento']] ?? null;
                                ?>
                                <?php if ($av && $av['anticipado'] > 0.009): ?>
                                <div class="rf-anticipo">
                                    <i class="bi bi-piggy-bank"></i>
                                    <?= e(money($av['anticipado'])) ?> a cuenta
                                </div>
                                <?php endif; ?>
                            </div>
                            <span class="badge badge-plan <?= $colorPlan[(int) $pl['id_estado_plan']] ?? 'plan-propuesto' ?>">
                                <?= e($pl['nombre_estado']) ?>
                            </span>
                        </a>
                        <?php endforeach; endif; ?>
                        <?php /* "Ver todos" solo cuando hay MAS de cinco.
                                 Con cinco exactos ya se ven todos y el
                                 enlace solo seria ruido.

                                 Filtra por numero de expediente y no por
                                 nombre: es unico, no tiene acentos y no
                                 se repite entre hermanos. */ ?>
                        <?php if ($cuantosPlanes > 5): ?>
                        <a class="ver-mas"
                           href="<?= URL_BASE ?>/index.php?modulo=consultas&vista=planes&buscar=<?= urlencode($pac['num_expediente']) ?>">
                            Ver todos <i class="bi bi-arrow-right"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>

                <?php /* Consultas */ ?>
                <div class="card tarjeta-ficha mb-3">
                    <div class="card-header">
                        <span><i class="bi bi-clipboard2-pulse me-2"></i>Consultas</span>
                        <?php if (Auth::puede('consultas.crear')): ?>
                        <button type="button" class="btn-mas"
                                data-nueva-consulta="<?= $idExpediente ?>"
                                title="Nueva consulta">
                            <i class="bi bi-plus-lg"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                    <div class="card-body py-2">
                        <?php if (!$consultas): ?>
                            <div class="exp-vacio">Sin consultas.</div>
                        <?php else: foreach ($consultas as $cs): ?>
                        <a class="renglon-ficha"
                           href="<?= URL_BASE ?>/index.php?modulo=consultas&vista=consulta&id=<?= (int) $cs['id_consulta'] ?>">
                            <div style="min-width:0">
                                <div class="rf-titulo">
                                    <?= e($cs['folio']) ?>
                                    <?php if ($cs['plan_folio']): ?>
                                    <span class="rf-plan" title="Pertenece a un plan">
                                        <?= e($cs['plan_folio']) ?>
                                    </span>
                                    <?php endif; ?>
                                </div>
                                <div class="rf-sub"><?= e(fechaHora($cs['fecha_hora'])) ?></div>
                                <?php if ($cs['motivo']): ?>
                                <div class="rf-sub"><?= e($cs['motivo']) ?></div>
                                <?php endif; ?>
                            </div>
                            <span class="chip-estado <?= (int) $cs['cerrada'] === 1
                                ? 'badge-suave' : 'badge-pendiente' ?>">
                                <?= (int) $cs['cerrada'] === 1 ? 'CERRADA' : 'ABIERTA' ?>
                            </span>
                        </a>
                        <?php endforeach; endif; ?>
                        <?php if ($cuantasConsultas > 5): ?>
                        <a class="ver-mas"
                           href="<?= URL_BASE ?>/index.php?modulo=consultas&vista=consultas&buscar=<?= urlencode($pac['num_expediente']) ?>">
                            Ver todos <i class="bi bi-arrow-right"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>

                </div>

                <div class="col-6">

<?php if (Auth::puede('cajas.ver')): ?>
                <?php
                // El mismo calculo que usa el estado de cuenta impreso.
                // Tenerlo aqui aparte garantizaba que tarde o temprano
                // la pantalla y el papel dijeran numeros distintos.
                $cuenta = Cargos::estadoDeCuenta($idExpediente);
                $debe   = (float) ($cuenta['resumen']['saldo'] ?? 0);
                $aFavor = (float) ($cuenta['resumen']['a_favor'] ?? 0);
                ?>
                <div class="card tarjeta-ficha mb-3">
                    <div class="card-header">
                        <span><i class="bi bi-cash-coin me-2"></i>Estado de cuenta</span>
                        <?php if (Auth::puede('cajas.cobrar')): ?>
                        <a class="btn-mas" title="Registrar un cobro"
                           href="<?= URL_BASE ?>/index.php?modulo=cajas&vista=cobrar&paciente=<?= $idExpediente ?>">
                            <i class="bi bi-plus-lg"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body py-2">
                        <?php /* Rojo si debe, verde si tiene a favor, gris en
                                 ceros. En recepcion es lo primero que se
                                 pregunta y tiene que leerse sin interpretar
                                 un numero. */ ?>
                        <div class="saldo-ficha <?= $debe > 0.009 ? 'debe'
                                                   : ($aFavor > 0.009 ? 'favor' : 'cero') ?>">
                            <div class="monto">
                                <?= e(money($debe > 0.009 ? $debe : $aFavor)) ?>
                            </div>
                            <div class="leyenda">
                                <?= $debe > 0.009 ? 'Saldo pendiente'
                                    : ($aFavor > 0.009 ? 'A favor del paciente'
                                                       : 'Sin adeudos') ?>
                            </div>
                        </div>
                        <button type="button" class="renglon-ficha"
                                data-estado-cuenta="<?= $idExpediente ?>">
                            <i class="bi bi-file-earmark-text"></i>
                            <div style="min-width:0">
                                <div class="rf-titulo">Ver el estado de cuenta</div>
                                <div class="rf-sub">Cargos, pagos y saldo</div>
                            </div>
                            <i class="bi bi-chevron-right"></i>
                        </button>
                    </div>
                </div>
                <?php endif; ?>

                <?php /* Citas */ ?>
                <div class="card tarjeta-ficha mb-3">
                    <div class="card-header">
                        <span><i class="bi bi-calendar-event me-2"></i>Citas</span>
                        <?php if (Auth::puede('consultas.crear')): ?>
                        <?php /* Lleva a la agenda con el paciente ya puesto:
                                 el dato lo sabemos y pedirlo de nuevo es
                                 hacer repetir trabajo a quien esta en
                                 recepcion con el tutor enfrente. */ ?>
                        <a class="btn-mas" title="Agendar una cita"
                           href="<?= URL_BASE ?>/index.php?modulo=consultas&vista=agenda&paciente=<?= $idExpediente ?>">
                            <i class="bi bi-plus-lg"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body py-2">
                        <?php if (!$citas): ?>
                            <div class="exp-vacio">Sin citas registradas.</div>
                        <?php else: ?>

                        <?php /* Los cuatro numeros, arriba de la lista.

                                 Contestan de un golpe la pregunta de
                                 recepcion: "¿este niño viene o no
                                 viene?". Tres faltas sin avisar cambian
                                 como se le agenda la siguiente, y eso no
                                 se deduce mirando las ultimas cinco. */ ?>
                        <div class="cuenta-citas">
                            <?php
                            $marcadores = [
                                ['pendientes', 'Pendientes', 'pend'],
                                ['atendidas',  'Atendidas',  'aten'],
                                ['canceladas', 'Canceladas', 'canc'],
                                ['no_asistio', 'No asisti&oacute;', 'falta'],
                            ];
                            foreach ($marcadores as [$clave, $texto, $tono]):
                                $cuantas = $citasPorEstado[$clave];
                            ?>
                            <?php /* Los que estan en cero se ven apagados
                                     en vez de esconderse: que un paciente
                                     tenga CERO faltas es justo lo que se
                                     quiere ver, y si el numero no sale no
                                     se distingue de que no se midio. */ ?>
                            <div class="cc <?= e($tono) ?> <?= $cuantas ? '' : 'vacia' ?>">
                                <div class="n"><?= $cuantas ?></div>
                                <div class="t"><?= $texto ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <?php foreach (array_slice($citas, 0, 5) as $ci): ?>
                        <?php /* Picar la cita abre su detalle, el mismo
                                 modal de la agenda. El dato viaja en el
                                 atributo para no pedirlo otra vez. */ ?>
                        <button type="button" class="renglon-ficha"
                                data-cita-ficha='<?= e(json_encode($ci, JSON_UNESCAPED_UNICODE)) ?>'>
                            <div style="min-width:0">
                                <div class="rf-titulo"><?= e(fechaHora($ci['fecha_inicio'])) ?></div>
                                <div class="rf-sub"><?= e($ci['dentista']) ?></div>
                                <?php if ($ci['motivo']): ?>
                                <div class="rf-sub"><?= e($ci['motivo']) ?></div>
                                <?php endif; ?>
                            </div>
                            <?php /* El estatus con su nombre, no solo un
                                     punto de color. Un punto obliga a
                                     pasar el raton por encima para saber
                                     que significa, y el estatus de la
                                     cita es el dato de la tarjeta. */ ?>
                            <span class="chip-estado"
                                  style="background:<?= e($ci['color'] ?: '#999') ?>1f;
                                         color:<?= e($ci['color'] ?: '#999') ?>">
                                <?= e($ci['nombre_estado']) ?>
                            </span>
                        </button>
                        <?php endforeach; ?>

                        <?php if ($cuantasCitas > 5): ?>
                        <button type="button" class="ver-mas w-100 border-0 bg-transparent"
                                data-bs-toggle="modal" data-bs-target="#modalCitasPaciente">
                            Ver m&aacute;s (<?= $cuantasCitas ?>)
                            <i class="bi bi-arrow-right"></i>
                        </button>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

                </div>

                <?php /* Aqui estaban los pendientes de digitalizar, a todo
                         lo ancho y al final de la rejilla.

                         Se fueron a la tarjeta de datos, debajo de los
                         canales de contacto. La razon es cuando se leen: son
                         la lista de lo que hay que pedirle al tutor que
                         TRAIGA, y el momento de verla es mientras se le tiene
                         enfrente, no al final de la pantalla. */ ?>

              </div><!-- /row de las dos columnas -->
            </div><!-- /col-auto: tarjetas -->
        </div><!-- /row interna -->
    </div><!-- /col-12 que envuelve todo -->
</div><!-- /row principal -->

<?php /* Campo de archivo escondido para los pendientes de la tarjeta. */ ?>
<input type="file" id="fichaArchivoPendiente" class="d-none">

<?php /* Todas las citas del paciente.

         Vive aqui, al final, y no dentro de la tarjeta: un modal
         metido en una columna de 300 pixeles hereda su ancho y sale
         estrujado contra un lado de la pantalla. */ ?>
<?php if ($cuantasCitas > 5): ?>
<div class="modal fade" id="modalCitasPaciente" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bi bi-calendar-event me-2"></i>Citas de
          <?= e($pac['nombre_completo']) ?>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">

        <div class="cuenta-citas mb-3">
            <?php foreach ($marcadores as [$clave, $texto, $tono]): ?>
            <div class="cc <?= e($tono) ?> <?= $citasPorEstado[$clave] ? '' : 'vacia' ?>">
                <div class="n"><?= $citasPorEstado[$clave] ?></div>
                <div class="t"><?= $texto ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <table class="table tabla-sistema mb-0">
          <thead><tr>
            <th style="width:150px">Cu&aacute;ndo</th>
            <th>Motivo y nota</th>
            <th style="width:180px">Con</th>
            <th style="width:120px">Estatus</th>
          </tr></thead>
          <tbody>
            <?php foreach ($citas as $ci): ?>
            <tr>
              <td class="fw-semibold"><?= e(fechaHora($ci['fecha_inicio'])) ?></td>
              <td>
                <?= e($ci['motivo'] ?: '-') ?>
                <?php if (!empty($ci['notas'])): ?>
                <div class="nota-cita"><i class="bi bi-sticky"></i>
                    <?= e($ci['notas']) ?></div>
                <?php endif; ?>
              </td>
              <td class="text-secondary" style="font-size:12.5px">
                <?= e($ci['dentista']) ?>
                <?php if (!empty($ci['nombre_sala'])): ?>
                <div style="font-size:11px"><?= e($ci['nombre_sala']) ?></div>
                <?php endif; ?>
              </td>
              <td>
                <span class="chip-estado"
                      style="background:<?= e($ci['color'] ?: '#999') ?>1f;
                             color:<?= e($ci['color'] ?: '#999') ?>">
                    <?= e($ci['nombre_estado']) ?>
                </span>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php /* El MISMO modal que usan las dos agendas, no una copia.

         Copiarlo aqui habria sido mas rapido y habria dejado dos
         bloques de HTML con los mismos ids que a la larga ensenian
         cosas distintas. Es el error que ya me costo la nota de la
         cita esta misma ronda. */ ?>
<?php require __DIR__ . '/../consultas/_modal_detalle_cita.php'; ?>
<?php require __DIR__ . '/_modales_ficha.php'; ?>
<?php require __DIR__ . '/_modales.php'; ?>
<?php require __DIR__ . '/_modal_tutor.php'; ?>
<?php require __DIR__ . '/_modal_contacto.php'; ?>
<?php require __DIR__ . '/_modal_tutor_editar.php'; ?>
<?php require __DIR__ . '/_modal_consentimiento.php'; ?>
