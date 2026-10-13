<?php
/**
 * Consentimiento bajo informacion, prellenado y listo para imprimir.
 *
 * Se abre en una pestania nueva con el dialogo de impresion arriba: de
 * ahi sale en papel para firmar, o en PDF con "Guardar como PDF".
 *
 * Al abrirlo se crea el pendiente de digitalizacion, porque a partir de
 * ese momento existe una hoja que alguien tiene que escanear de regreso.
 *
 * El consentimiento pertenece a un plan de tratamiento: es el permiso
 * para ESE tratamiento, no un permiso general de por vida. Por eso se
 * pide con el plan y queda guardado dentro de su bloque en el expediente.
 *
 * Uso:
 *   /consentimiento.php?id=EXPEDIENTE
 *                      &plan=ID_PLAN_TRATAMIENTO
 *                      &tutor=ID_EXPEDIENTE_TUTOR
 *                      &domicilio=ID_DOMICILIO
 *                      &dentista=ID_USUARIO
 */

require_once __DIR__ . '/includes/core/bootstrap.php';

Auth::exigirLogin();
Auth::exigirPermiso('paciente.ver');

$idExpediente = (int) ($_GET['id'] ?? 0);
$idPlan       = (int) ($_GET['plan'] ?? 0);
$idTutor      = (int) ($_GET['tutor'] ?? 0);
$idDomicilio  = (int) ($_GET['domicilio'] ?? 0);
$idDentista   = (int) ($_GET['dentista'] ?? 0);

if ($idExpediente <= 0) {
    http_response_code(400);
    exit('Falta el expediente.');
}

// El plan debe ser de este paciente. Si no coincide se ignora y el
// documento queda como general, en vez de colgarlo del expediente ajeno.
$plan = $idPlan > 0
    ? Conexion::uno(
        'SELECT id_plan_tratamiento, folio, fecha, id_usuario_dentista
           FROM plan_tratamiento
          WHERE id_plan_tratamiento = :p AND id_registro_paciente = :r',
        [':p' => $idPlan, ':r' => $idExpediente])
    : null;

if ($plan === null) {
    $idPlan = 0;
}

// ---------------------------------------------------------------------
// Datos
// ---------------------------------------------------------------------
$paciente = Conexion::uno(
    'SELECT vp.*, rp.requiere_tutor
       FROM v_paciente vp
       JOIN registro_paciente rp
         ON rp.id_registro_paciente = vp.id_registro_paciente
      WHERE vp.id_registro_paciente = :r',
    [':r' => $idExpediente]
);

if ($paciente === null) {
    http_response_code(404);
    exit('El paciente no existe.');
}

/*
   ¿Quien firma: el tutor o el propio paciente?

   Nanai atiende ninios y adultos. Un adulto que respondio que actua por
   su cuenta firma el, y sacar el documento con una linea para "padre o
   tutor" delante de alguien de cuarenta anios es, ademas de raro, un
   papel que no sirve: lo tiene que firmar quien consiente.

   La decision sale de Tutoria, la misma regla que usa la ficha. Nunca
   de mirar la edad aqui: dos sitios calculando lo mismo acaban
   discrepando, y el sintoma seria un consentimiento que pide una firma
   distinta de la que la pantalla dice que hace falta.
*/
$firmaElPaciente = !Tutoria::llevaTutor(
    $paciente['edad'] !== null ? (int) $paciente['edad'] : null,
    $paciente['requiere_tutor'] !== null
        ? (int) $paciente['requiere_tutor'] : null
);

// Tutor: el que pidieron, o el responsable de pago si no especificaron.
$tutor = Conexion::uno(
    'SELECT et.id_expediente_tutor, et.es_responsable_pago,
            p.id_persona,
            CONCAT_WS(" ", p.nombres, p.ap_paterno, p.ap_materno) AS nombre_completo,
            pa.nombre_parentesco
       FROM expediente_tutor et
       JOIN persona p ON p.id_persona = et.id_persona
       LEFT JOIN parentesco pa ON pa.id_parentesco = et.id_parentesco
      WHERE et.id_registro_paciente = :r AND et.estatus = 1
        AND (:t = 0 OR et.id_expediente_tutor = :t2)
      ORDER BY et.es_responsable_pago DESC, et.id_expediente_tutor
      LIMIT 1',
    [':r' => $idExpediente, ':t' => $idTutor, ':t2' => $idTutor]
);

// Domicilio: el que pidieron, o el principal del tutor.
$domicilio = null;
if ($tutor !== null) {
    $domicilio = Conexion::uno(
        'SELECT vd.* FROM v_domicilio vd
          WHERE vd.id_persona = :p AND vd.estatus = 1
            AND (:d = 0 OR vd.id_domicilio = :d2)
          ORDER BY vd.es_principal DESC, vd.id_domicilio
          LIMIT 1',
        [':p' => $tutor['id_persona'], ':d' => $idDomicilio, ':d2' => $idDomicilio]
    );
}

/*
   Quien firma como dentista, en tres intentos y en este orden.

     1. El que digan en la direccion. Es el caso del expediente, donde
        hay un selector para elegirlo.
     2. EL DEL PLAN. Un consentimiento de un plan de tratamiento lo
        firma quien propuso ese plan, y el plan ya lo sabe.
     3. Quien esta capturando, si es dentista.

   El paso 2 faltaba, y el sintoma era exactamente este: imprimiendo el
   consentimiento desde el plan, el documento salia sin nombre ni cedula
   en la linea de la firma. Desde el expediente si salia, porque esa
   pantalla manda el parametro. Dos caminos al mismo papel y solo uno lo
   llenaba.

   El orden tambien importa. Teniendo al dentista del plan, poner al
   usuario que esta en la computadora seria peor que dejarlo en blanco:
   un consentimiento con el nombre de recepcion en la linea del
   profesional es un documento que afirma algo falso, y se firma.
*/
$dentista = Conexion::uno(
    'SELECT nombre_completo, cedula_prof FROM usuario
      WHERE id_usuario = :u AND estatus = 1',
    [':u' => $idDentista
        ?: (int) ($plan['id_usuario_dentista'] ?? 0)
        ?: (Auth::usuario()['es_dentista'] ? Auth::id() : 0)]
);

$negocio = Conexion::uno(
    'SELECT c.nombre_comercial, c.telefono,
            CONCAT_WS(", ", CONCAT_WS(" ", d.calle, d.num_ext),
                      col.nombre_colonia,
                      CONCAT("CP ", col.codigo_postal),
                      mun.nombre_municipio, est.nombre_estado) AS domicilio
       FROM consultorio c
       LEFT JOIN domicilio d   ON d.id_domicilio   = c.id_domicilio
       LEFT JOIN colonia   col ON col.id_colonia   = d.id_colonia
       LEFT JOIN municipio mun ON mun.id_municipio = col.id_municipio
       LEFT JOIN estado    est ON est.id_estado    = mun.id_estado
      WHERE c.id_consultorio = 1'
);

// ---------------------------------------------------------------------
// Pendiente de digitalizacion
//
// El consentimiento se firma una sola vez por plan. crearPendiente
// devuelve null si ya existe uno (pendiente o ya digitalizado), asi que
// reimprimirlo o volver a consultarlo no acumula faltantes.
// ---------------------------------------------------------------------
$pendiente = Documentos::crearPendiente(
    $idExpediente,
    'CONSENTIMIENTO',
    $plan
        ? 'CONSENTIMIENTO DEL PLAN ' . strtoupper((string) $plan['folio'])
        : 'CONSENTIMIENTO IMPRESO EL ' . strtoupper(fechaLarga()),
    'plan_tratamiento',
    $idPlan ?: null,
    $idPlan ?: null
);

Bitacora::registrar('paciente', 'IMPRIME', 'consentimiento', (string) $idExpediente, [
    'plan'     => $plan['folio'] ?? null,
    'tutor'    => $tutor['nombre_completo'] ?? null,
    'dentista' => $dentista['nombre_completo'] ?? null,
]);

// Linea del domicilio en un solo renglon
$lineaDomicilio = $domicilio
    ? implode(', ', array_filter([
        $domicilio['calle_numero'],
        $domicilio['colonia'],
        $domicilio['codigo_postal'] ? 'C.P. ' . $domicilio['codigo_postal'] : null,
        $domicilio['nombre_municipio'],
        $domicilio['nombre_estado'],
      ]))
    : '';

/** Imprime un dato sobre una linea punteada; si falta, deja el espacio. */
function campo(?string $valor, int $anchoMinimo = 200): string
{
    return '<span class="campo" style="min-width:' . $anchoMinimo . 'px">'
        . e($valor ?: '') . '</span>';
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Consentimiento bajo informaci&oacute;n &middot; <?= e($paciente['nombre_completo']) ?></title>
<style>
    /* =================================================================
       Hoja carta. Los colores y proporciones siguen el formato impreso
       que ya usa el consultorio, para que el documento se vea igual.
       ================================================================= */
    :root {
        --morado: #8b5a96;
        --morado-oscuro: #6b4574;
        --texto: #5a3d63;
    }

    * { box-sizing: border-box; }

    body {
        margin: 0;
        background: #e9e4ec;
        font-family: "Segoe UI", "Helvetica Neue", Arial, sans-serif;
        color: var(--texto);
    }

    /* Hoja carta exacta: 21.6 x 27.9 cm.
       El alto es fijo, no minimo: asi se ve de una vez si el contenido
       se sale, en lugar de descubrirlo hasta imprimir. */
    .hoja {
        width: 21.6cm;
        height: 27.9cm;
        margin: 1rem auto;
        background: #fdfbfd;
        box-shadow: 0 6px 30px rgba(0,0,0,.18);
        position: relative;
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }

    /* ---------- Encabezado ---------- */
    .cinta {
        background: var(--morado);
        height: 2.4cm;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 1.4cm 0 0;
    }
    .marca {
        background: #fdfbfd;
        border-radius: 0 14px 14px 0;
        padding: .45cm .9cm .45cm 1.2cm;
        margin-top: -.2cm;
    }
    .marca .nombre {
        font-size: 15pt;
        font-weight: 700;
        color: var(--morado-oscuro);
        line-height: 1.1;
    }
    .marca .especialidad {
        font-size: 7.5pt;
        letter-spacing: .09em;
        color: var(--morado);
        text-transform: uppercase;
        margin-top: 2px;
    }
    .titulo-doc {
        color: #fff;
        font-size: 14pt;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
    }

    .datos-clinica {
        font-size: 7.5pt;
        letter-spacing: .04em;
        color: var(--texto);
        padding: .18cm 1.4cm;
        display: flex;
        justify-content: space-between;
        border-bottom: 1px solid #ddd3e0;
    }

    /* ---------- Cuerpo ---------- */
    /* El interlineado va holgado para que quede espacio de escribir a
       mano sobre las lineas, pero no tanto como para brincar de hoja. */
    .cuerpo {
        padding: .8cm 1.5cm 0;
        font-size: 10pt;
        line-height: 1.95;
        flex: 1;
    }

    .fecha-linea {
        text-align: center;
        margin-bottom: .8cm;
        font-size: 10.5pt;
    }

    p { margin: 0 0 .45cm; text-align: justify; }

    /* Los datos prellenados van subrayados, como en el formato de papel:
       se distingue a simple vista lo que imprimio el sistema. */
    .campo {
        display: inline-block;
        border-bottom: 1px solid var(--morado);
        padding: 0 .18cm;
        font-weight: 600;
        color: var(--morado-oscuro);
        text-align: center;
        line-height: 1.4;
    }
    .campo:empty::after { content: "\00a0"; }

    /* ---------- Firmas ---------- */
    .firmas {
        display: flex;
        justify-content: space-around;
        gap: 2cm;
        padding: 0 1.5cm 1.4cm;
        margin-top: auto;   /* pegadas al pie, no a lo que sigue arriba */
    }
    .firma { flex: 1; text-align: center; }
    .firma .linea {
        border-top: 1px solid var(--morado);
        margin-bottom: .2cm;
        height: 1.4cm;
    }
    .firma .nombre {
        font-weight: 600;
        color: var(--morado-oscuro);
        font-size: 10pt;
        min-height: 1.2em;
    }
    .firma .cargo { font-size: 8.5pt; color: var(--texto); }
    .firma .cedula { font-size: 7.5pt; color: #8a7a90; margin-top: 2px; }

    .pie {
        position: absolute;
        bottom: .5cm; left: 1.5cm; right: 1.5cm;
        font-size: 7pt;
        color: #a094a5;
        display: flex;
        justify-content: space-between;
        border-top: 1px solid #e5dde8;
        padding-top: .2cm;
    }

    /* ---------- Barra de control (no se imprime) ---------- */
    .barra {
        position: sticky;
        top: 0;
        background: #fff;
        border-bottom: 1px solid #ddd;
        padding: .7rem 1.2rem;
        display: flex;
        align-items: center;
        gap: .8rem;
        box-shadow: 0 2px 8px rgba(0,0,0,.08);
        z-index: 10;
        font-size: 13px;
    }
    .barra button, .barra a {
        border: 0;
        border-radius: 8px;
        padding: .5rem .9rem;
        font-size: 13px;
        font-weight: 500;
        cursor: pointer;
        text-decoration: none;
    }
    .barra .principal { background: #7b2d8e; color: #fff; }
    .barra .secundario { background: #f0ecf2; color: #5a3d63; }
    .barra .aviso { color: #7a5a12; background: #fff6e5;
                    border: 1px solid #f0d9a8; border-radius: 8px;
                    padding: .4rem .7rem; font-size: 12px; }

    /* ---------- Impresion ---------- */
    @page { size: letter; margin: 0; }

    @media print {
        body { background: #fff; }
        .barra { display: none !important; }

        .hoja {
            margin: 0;
            box-shadow: none;
            width: 21.6cm;
            height: 27.9cm;
            /* Que nada parta el documento en dos hojas */
            page-break-inside: avoid;
            break-inside: avoid;
        }

        /* Ni los parrafos ni las firmas se cortan a media pagina */
        p, .firmas, .cinta { page-break-inside: avoid; break-inside: avoid; }

        /* Sin esto el navegador quita los fondos de color y la cinta
           morada sale en blanco. */
        * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }

        /* Evita que una hoja en blanco salga al final */
        html, body { height: auto; }
    }
</style>
</head>
<body>

<div class="barra">
    <button class="principal" onclick="window.print()">Imprimir o guardar como PDF</button>
    <?php $volverA = URL_BASE . '/index.php?modulo=paciente&vista=expediente&id='
                     . $idExpediente; ?>
    <?php require __DIR__ . '/includes/_boton_cerrar.php'; ?>
    <?php if ($pendiente !== null): ?>
        <span class="aviso">
            Se agreg&oacute; el pendiente de escanear este documento al expediente.
        </span>
    <?php else: ?>
        <span class="aviso" style="background:#eef7f2;border-color:#bfe6d1;color:#1f6b48">
            Este consentimiento ya estaba registrado en el expediente<?=
                $plan ? ', dentro del plan ' . e($plan['folio']) : '' ?>.
        </span>
    <?php endif; ?>
    <?php if ($tutor === null && !$firmaElPaciente): ?>
        <span class="aviso">
            Este paciente no tiene tutor registrado: el documento sale sin nombre.
        </span>
    <?php endif; ?>
</div>

<div class="hoja">
    <div class="cinta">
        <div class="marca">
            <div class="nombre"><?= e($negocio['nombre_comercial'] ?? 'Consultorio Dental') ?></div>
            <div class="especialidad">Odontopediatr&iacute;a y ortopedia maxilar</div>
        </div>
        <div class="titulo-doc">Consentimiento bajo informaci&oacute;n</div>
    </div>

    <div class="datos-clinica">
        <span>DOMICILIO: <?= e($negocio['domicilio'] ?? '') ?></span>
        <span>TEL&Eacute;FONO: <?= e($negocio['telefono'] ?? '') ?></span>
    </div>

    <div class="cuerpo">

        <div class="fecha-linea">
            Fecha <?= campo(fechaLarga() . ', ' . date('H:i') . ' h', 260) ?>
        </div>

        <?php /* El texto cambia segun quien firme.

                 No es un detalle de estilo: un consentimiento que habla
                 de "el menor de edad" y "el representante legal" puesto
                 delante de un adulto que firma por si mismo describe
                 una situacion que no es la suya, y lo que se firma
                 tiene que decir lo que pasa. */ ?>
        <?php if ($firmaElPaciente): ?>
        <p>
            Por la presente se hace saber a usted que tiene derecho a conocer el
            procedimiento al que va a ser sometido y las complicaciones
            m&aacute;s frecuentes que ocurren. Este documento explica todas
            estas cuestiones, l&eacute;alo atentamente y consulte todas las dudas
            que se le planteen. Le recordamos que por imperativo legal,
            tendr&aacute; que firmar el consentimiento informado para que
            pueda realizarse dicho procedimiento. A prop&oacute;sito declaro
            haber sido informado y haber comprendido acabadamente el objetivo
            del tratamiento a realizar.
        </p>
        <?php else: ?>
        <p>
            Por la presente se hace saber a usted que tiene derecho a conocer el
            procedimiento al que va a ser sometido el menor de edad y las
            complicaciones m&aacute;s frecuentes que ocurren. Este documento explica
            todas estas cuestiones, l&eacute;alo atentamente y consulte todas las dudas
            que se le planteen. Le recordamos que por imperativo legal, tendr&aacute;
            que firmar, el representante legal, el consentimiento informado para que
            pueda realizarse dicho procedimiento. A prop&oacute;sito declaro haber sido
            informado y haber comprendido acabadamente el objetivo del tratamiento
            a realizar.
        </p>
        <?php endif; ?>

        <p>
            Yo, <?= campo($firmaElPaciente
                    ? $paciente['nombre_completo']
                    : ($tutor['nombre_completo'] ?? null), 300) ?>
            domiciliado en <?= campo($lineaDomicilio, 340) ?>
            <?= $firmaElPaciente
                ? ''
                : 'como representante legal de '
                  . campo($paciente['nombre_completo'], 300) ?>
            he sido informado/a por el Dr. / Dra.
            <?= campo($dentista['nombre_completo'] ?? null, 280) ?>
            de los procedimientos cl&iacute;nicos que constan en el plan de
            tratamiento, otorgando mi consentimiento para realizar
            las pr&aacute;cticas necesarias al caso cl&iacute;nico. Estoy de acuerdo
            <?= $firmaElPaciente
                ? 'en ser sometido/a'
                : 'a que el ni&ntilde;o sea sometido' ?>
            a anestesia local en caso que fuera necesario,
            sabiendo los riesgos que ello implica, delegando al odont&oacute;logo la
            elecci&oacute;n del tipo de anestesia.
        </p>

        <p>
            Se me ha explicado el diagn&oacute;stico, la naturaleza de la enfermedad
            que <?= $firmaElPaciente ? 'padezco' : 'padece mi representado' ?>
            y su evoluci&oacute;n natural, objetivos del
            tratamiento propuesto, as&iacute; como las alternativas del tratamiento que
            pueden ser practicadas, descripci&oacute;n de las consecuencias derivadas
            del tratamiento o intervenci&oacute;n, beneficios y complicaciones comunes
            que se pueden desencadenar durante o despu&eacute;s del mismo, riesgos
            personales y entendiendo que ante alguna manifestaci&oacute;n de
            complicaciones deber&eacute; acudir nuevamente al profesional tratante.
        </p>
    </div>

    <div class="firmas">
        <div class="firma">
            <div class="linea"></div>
            <div class="nombre"><?= e($firmaElPaciente
                ? $paciente['nombre_completo']
                : ($tutor['nombre_completo'] ?? '')) ?></div>
            <div class="cargo"><?= $firmaElPaciente
                ? 'Nombre y firma del paciente'
                : 'Nombre y firma de padre o tutor' ?></div>
            <?php if (!$firmaElPaciente && !empty($tutor['nombre_parentesco'])): ?>
                <div class="cedula"><?= e($tutor['nombre_parentesco']) ?></div>
            <?php endif; ?>
        </div>

        <div class="firma">
            <div class="linea"></div>
            <div class="nombre"><?= e($dentista['nombre_completo'] ?? '') ?></div>
            <div class="cargo">Nombre y firma del doctor/a</div>
            <?php if (!empty($dentista['cedula_prof'])): ?>
                <div class="cedula">C&eacute;d. Prof. <?= e($dentista['cedula_prof']) ?></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="pie">
        <span>Expediente <?= e($paciente['num_expediente']) ?></span>
        <span><?= e(APP_NOMBRE) ?> v<?= e(APP_VERSION) ?></span>
    </div>
</div>

<script>
window.addEventListener('load', () => {
    // Si un nombre o un domicilio muy largos empujan el contenido fuera
    // de la hoja, es mejor avisarlo que dejar que salga cortado al
    // imprimir. El aviso solo se ve en pantalla, no en el papel.
    const hoja   = document.querySelector('.hoja');
    const cuerpo = document.querySelector('.cuerpo');

    if (cuerpo.scrollHeight > cuerpo.clientHeight) {
        const aviso = document.createElement('span');
        aviso.className = 'aviso';
        aviso.textContent =
            'El contenido no cabe en una hoja. Revisa antes de imprimir.';
        document.querySelector('.barra').appendChild(aviso);
    }

    // El dialogo de impresion se abre solo, pero despues de que el
    // navegador termino de acomodar la hoja. Sin la espera sale con el
    // formato a medias.
    setTimeout(() => window.print(), 700);
});
</script>
</body>
</html>
