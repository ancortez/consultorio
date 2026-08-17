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

    $limpios[] = [
        'id_procedimiento' => $idProc,
        'id_tratamiento'   => $proc['id_tratamiento'],
        'id_diente'        => ((int) $proc['aplica_por_diente'] === 1 && !empty($r['id_diente']))
                              ? (int) $r['id_diente'] : null,
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
                    descuento = :desc, subtotal = :sub, total = :tot, saldo = :tot2,
                    diagnostico = :diag, oclusion = :ocl,
                    traumatismos = :tra, secuelas_traumatismos = :sec,
                    observaciones = :obs
              WHERE id_plan_tratamiento = :id',
            [':d' => $idDentista, ':f' => $fecha, ':e' => $idEstado,
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

        Conexion::consulta(
            'DELETE FROM plan_tratamiento_detalle WHERE id_plan_tratamiento = :p',
            [':p' => $idPlan]
        );
        $accion = 'CAMBIO';
    } else {
        $folio = siguienteFolio('plan_tratamiento', 'folio', 'PT');

        Conexion::consulta(
            'INSERT INTO plan_tratamiento
                (id_registro_paciente, id_usuario_dentista, folio, fecha,
                 id_estado_plan, descuento, subtotal, total, saldo,
                 diagnostico, oclusion, traumatismos, secuelas_traumatismos,
                 observaciones)
             VALUES (:r, :d, :fo, :f, :e, :desc, :sub, :tot, :tot2,
                     :diag, :ocl, :tra, :sec, :obs)',
            [':r' => $idExpediente, ':d' => $idDentista, ':fo' => $folio,
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
                 id_diente, cara, cantidad, precio_unitario, descuento,
                 importe, orden, fecha_programada, proxima_cita, notas)
             VALUES (:p, :t, :proc, :die, :cara, :cant, :pre, :desc,
                     :imp, :ord, :fp, :px, :nt)',
            [':p' => $idPlan, ':t' => $r['id_tratamiento'], ':proc' => $r['id_procedimiento'],
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

// El plan se imprime y se firma, asi que hay que escanearlo de regreso.
$pendiente = Documentos::crearPendiente(
    $idExpediente,
    'PLAN_TRATAMIENTO',
    'PLAN DE TRATAMIENTO DEL ' . strtoupper(fecha($fecha)),
    'plan_tratamiento',
    $idPlan
);

Bitacora::registrar('consultas', $accion, 'plan_tratamiento', (string) $idPlan, [
    'expediente'  => $idExpediente,
    'total'       => $total,
    'renglones'   => count($limpios),
]);

Respuesta::ok([
    'id_plan_tratamiento' => $idPlan,
    'total'               => $total,
    'pendiente'           => $pendiente !== null,
], 'Plan de tratamiento guardado.');
