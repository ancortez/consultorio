<?php
/**
 * Alta y modificacion de un plan de tratamiento.
 *
 * El plan es la hoja que se le presenta al tutor: diagnostico arriba y
 * abajo el desglose de procedimientos con su costo. Al guardarlo se
 * genera el pendiente de escanearlo firmado.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('consultas.plan');

$idPlan       = paramInt('id_plan_tratamiento');
$idExpediente = paramInt('id_registro_paciente');
$idDentista   = paramInt('id_usuario_dentista');
$fecha        = trim((string) param('fecha', date('Y-m-d')));
$idEstado     = paramInt('id_estado_plan') ?? 1;
$descuento    = (float) str_replace(',', '', (string) param('descuento', '0'));

$diagnostico  = limpiarEspacios(paramMayus('diagnostico'));
$oclusion     = limpiarEspacios(paramMayus('oclusion'));
$traumatismos = limpiarEspacios(paramMayus('traumatismos'));
$secuelas     = limpiarEspacios(paramMayus('secuelas_traumatismos'));
$observaciones = limpiarEspacios(paramMayus('observaciones'));
$descripcion   = limpiarEspacios(paramMayus('descripcion'));

// Sin descripcion el plan se vuelve "PT-0007" a secas en las listas.
// Se rellena con la misma frase que propone el formulario.
if ($descripcion === null || $descripcion === '') {
    $descripcion = 'PLAN DE TRATAMIENTO ' . strtoupper(fecha($fecha));
}

// Los renglones vienen como JSON: son una lista de longitud variable y
// mandarlos como campos sueltos obligaria a inventar nombres numerados.
$renglones = json_decode((string) param('renglones', '[]'), true);
if (!is_array($renglones)) {
    $renglones = [];
}

$errores = [];
if (!$idExpediente) { $errores['id_registro_paciente'] = 'Falta el paciente.'; }
if (!$idDentista)   { $errores['id_usuario_dentista']  = 'Selecciona el dentista.'; }
if (!$renglones)    { $errores['renglones'] = 'Agrega al menos un procedimiento.'; }
if ($descuento < 0) { $errores['descuento'] = 'El descuento no puede ser negativo.'; }

$d = DateTime::createFromFormat('Y-m-d', $fecha);
if (!$d || $d->format('Y-m-d') !== $fecha) {
    $errores['fecha'] = 'La fecha no es valida.';
}

if ($errores) { Respuesta::validacion($errores); }

// ---------------------------------------------------------------------
// Los importes se recalculan aqui, nunca se confia en los que manda el
// navegador: cualquiera puede editarlos antes de enviarlos.
// ---------------------------------------------------------------------
$subtotal = 0.0;
$limpios  = [];

// Catalogo de organos dentarios. Se lee una vez y sirve de filtro: un
// numero que no este aqui simplemente no se guarda.
$catalogoDientes = [];
foreach (Conexion::todos('SELECT id_diente FROM diente') as $d) {
    $catalogoDientes[(int) $d['id_diente']] = true;
}

foreach ($renglones as $r) {
    $idProc = (int) ($r['id_procedimiento'] ?? 0);
    if (!$idProc) {
        continue;
    }

    $proc = Conexion::uno(
        'SELECT id_procedimiento, id_tratamiento, costo_sugerido, aplica_por_diente
           FROM procedimiento WHERE id_procedimiento = :p AND estatus = 1',
        [':p' => $idProc]
    );
    if ($proc === null) {
        continue;
    }

    $cantidad = max(1, (int) ($r['cantidad'] ?? 1));

    // El precio si se toma del formulario: la doctora puede ajustarlo
    // por caso. Lo que no se acepta es el importe ya calculado.
    $precio   = round((float) ($r['precio_unitario'] ?? $proc['costo_sugerido']), 2);
    $descLin  = round((float) ($r['descuento'] ?? 0), 2);

    if ($precio < 0)  { $precio = 0; }
    if ($descLin < 0) { $descLin = 0; }

    $importe = round($precio * $cantidad - $descLin, 2);
    if ($importe < 0) { $importe = 0; }

    $subtotal += $importe;

    // Organos dentarios. Llegan como arreglo desde el selector de la
    // arcada. Se filtra contra el catalogo para que un numero inventado
    // no entre a la base, y se guarda la lista ordenada.
    $dientes = [];
    if ((int) $proc['aplica_por_diente'] === 1 && !empty($r['dientes'])) {
        foreach ((array) $r['dientes'] as $n) {
            $n = (int) $n;
            if (isset($catalogoDientes[$n])) { $dientes[$n] = true; }
        }
        $dientes = array_keys($dientes);
        sort($dientes);
    }

    $limpios[] = [
        'id_procedimiento' => $idProc,
        'id_tratamiento'   => $proc['id_tratamiento'],
        'dientes'          => $dientes ? implode(',', $dientes) : null,
        // Se conserva el primero para el odontograma y los reportes,
        // que lo consultan por llave foranea.
        'id_diente'        => $dientes ? $dientes[0] : null,
        'cara'             => !empty($r['cara']) ? mb_substr((string) $r['cara'], 0, 1) : null,
        'cantidad'         => $cantidad,
        'precio_unitario'  => $precio,
        'descuento'        => $descLin,
        'importe'          => $importe,
        'fecha_programada' => !empty($r['fecha_programada']) ? $r['fecha_programada'] : null,
        'proxima_cita'     => !empty($r['proxima_cita']) ? $r['proxima_cita'] : null,
        'notas'            => !empty($r['notas'])
                              ? limpiarEspacios(mayus((string) $r['notas'])) : null,
    ];
}

if (!$limpios) {
    Respuesta::validacion(['renglones' => 'Ningun procedimiento valido.']);
}

$total = round(max(0, $subtotal - $descuento), 2);

$pdo = bd();
try {
    $pdo->beginTransaction();

    if ($idPlan) {
        Conexion::consulta(
            'UPDATE plan_tratamiento
                SET id_usuario_dentista = :d, fecha = :f, id_estado_plan = :e,
                    descripcion = :descr,
                    descuento = :desc, subtotal = :sub, total = :tot, saldo = :tot2,
                    diagnostico = :diag, oclusion = :ocl,
                    traumatismos = :tra, secuelas_traumatismos = :sec,
                    observaciones = :obs
              WHERE id_plan_tratamiento = :id',
            [':d' => $idDentista, ':f' => $fecha, ':e' => $idEstado,
             ':descr' => $descripcion,
             ':desc' => $descuento, ':sub' => $subtotal,
             ':tot' => $total, ':tot2' => $total,
             ':diag' => $diagnostico, ':ocl' => $oclusion,
             ':tra' => $traumatismos, ':sec' => $secuelas,
             ':obs' => $observaciones, ':id' => $idPlan]
        );

        // Los renglones se reemplazan completos. Solo se permite mientras
        // nada se haya ejecutado: rehacer un plan con trabajo ya hecho
        // borraria el avance registrado.
        $ejecutados = (int) Conexion::valor(
            'SELECT COUNT(*) FROM plan_tratamiento_detalle
              WHERE id_plan_tratamiento = :p AND estado_avance > 0',
            [':p' => $idPlan]
        );
        if ($ejecutados > 0) {
            $pdo->rollBack();
            Respuesta::error(
                "Este plan ya tiene $ejecutados procedimiento(s) iniciados. "
                . 'Crea un plan nuevo en lugar de modificar este.');
        }

        // Lo mismo por el lado del dinero: si ya le abonaron a algun
        // concepto, rehacer el plan dejaria pagos apuntando a renglones
        // que dejaron de existir.
        $conAbono = (int) Conexion::valor(
            'SELECT COUNT(*) FROM v_cargo_saldo
              WHERE id_plan_tratamiento = :p AND aplicado > 0',
            [':p' => $idPlan]
        );
        if ($conAbono > 0) {
            $pdo->rollBack();
            Respuesta::error(
                "Este plan ya tiene pagos aplicados en $conAbono concepto(s). "
                . 'Crea un plan nuevo en lugar de modificar este.');
        }

        // Los cargos sin abonos se retiran antes de borrar los
        // renglones: al borrarlos, la llave del cargo se quedaria en
        // NULL y la sincronizacion los volveria a crear duplicados.
        foreach (Conexion::todos(
            'SELECT id_cargo FROM v_cargo_saldo WHERE id_plan_tratamiento = :p',
            [':p' => $idPlan]) as $c) {
            Cargos::cancelar((int) $c['id_cargo'], 'El plan se volvio a capturar.');
        }

        Conexion::consulta(
            'DELETE FROM plan_tratamiento_detalle WHERE id_plan_tratamiento = :p',
            [':p' => $idPlan]
        );
        $accion = 'CAMBIO';
    } else {
        $folio = siguienteFolio('plan_tratamiento', 'folio', 'PT');

        Conexion::consulta(
            'INSERT INTO plan_tratamiento
                (id_registro_paciente, id_usuario_dentista, folio, descripcion,
                 fecha, id_estado_plan, descuento, subtotal, total, saldo,
                 diagnostico, oclusion, traumatismos, secuelas_traumatismos,
                 observaciones)
             VALUES (:r, :d, :fo, :descr, :f, :e, :desc, :sub, :tot, :tot2,
                     :diag, :ocl, :tra, :sec, :obs)',
            [':r' => $idExpediente, ':d' => $idDentista, ':fo' => $folio,
             ':descr' => $descripcion,
             ':f' => $fecha, ':e' => $idEstado, ':desc' => $descuento,
             ':sub' => $subtotal, ':tot' => $total, ':tot2' => $total,
             ':diag' => $diagnostico, ':ocl' => $oclusion,
             ':tra' => $traumatismos, ':sec' => $secuelas, ':obs' => $observaciones]
        );
        $idPlan = Conexion::ultimoId();
        $accion = 'ALTA';
    }

    $orden = 1;
    foreach ($limpios as $r) {
        Conexion::consulta(
            'INSERT INTO plan_tratamiento_detalle
                (id_plan_tratamiento, id_tratamiento, id_procedimiento,
                 dientes, id_diente, cara, cantidad, precio_unitario, descuento,
                 importe, orden, fecha_programada, proxima_cita, notas)
             VALUES (:p, :t, :proc, :dts, :die, :cara, :cant, :pre, :desc,
                     :imp, :ord, :fp, :px, :nt)',
            [':p' => $idPlan, ':t' => $r['id_tratamiento'], ':proc' => $r['id_procedimiento'],
             ':dts' => $r['dientes'],
             ':die' => $r['id_diente'], ':cara' => $r['cara'], ':cant' => $r['cantidad'],
             ':pre' => $r['precio_unitario'], ':desc' => $r['descuento'],
             ':imp' => $r['importe'], ':ord' => $orden++,
             ':fp' => $r['fecha_programada'], ':px' => $r['proxima_cita'],
             ':nt' => $r['notas']]
        );
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log('[PLAN/GUARDAR] ' . $e->getMessage());
    Respuesta::error('No se pudo guardar el plan de tratamiento.', 500);
}

// Los cargos del plan se ponen al dia. Solo se cobran si el plan ya
// esta aceptado: mientras es una propuesta, no hay nada que deber.
$cargos = Cargos::sincronizarPlan($idPlan);

// El plan se imprime y se firma, asi que hay que escanearlo de regreso.
// El pendiente cuelga del plan: en el expediente aparece dentro de su
// bloque, junto al consentimiento y las radiografias del mismo plan.
$pendiente = Documentos::crearPendiente(
    $idExpediente,
    'PLAN_TRATAMIENTO',
    $descripcion,
    'plan_tratamiento',
    $idPlan,
    $idPlan
);

Bitacora::registrar('consultas', $accion, 'plan_tratamiento', (string) $idPlan, [
    'cargos'      => $cargos,
    'expediente'  => $idExpediente,
    'total'       => $total,
    'renglones'   => count($limpios),
]);

Respuesta::ok([
    'id_plan_tratamiento' => $idPlan,
    'total'               => $total,
    'pendiente'           => $pendiente !== null,
], 'Plan de tratamiento guardado.');
