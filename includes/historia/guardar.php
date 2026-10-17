<?php
/**
 * Guarda la historia clinica completa.
 *
 * Es un formulario largo con muchos campos opcionales, asi que en vez de
 * enumerarlos uno por uno se recorre una lista con su tipo. Agregar una
 * pregunta nueva es un renglon aqui y una columna en la tabla, no
 * cincuenta lineas de validacion repetida.
 */
require_once __DIR__ . '/../_endpoint.php';
endpoint('paciente.historia');

$idExpediente = paramInt('id_registro_paciente');
if (!$idExpediente) {
    Respuesta::error('Falta el expediente.');
}

// ---------------------------------------------------------------------
// Definicion de campos: nombre => tipo
//   txt  = texto en MAYUSCULAS
//   num  = entero
//   dec  = decimal
//   bool = 1 / 0 / null (las preguntas de si-no sin responder van null)
//   opc  = opcion numerada de un grupo de botones
// ---------------------------------------------------------------------
$campos = [
    // Ficha de identificacion
    'apodo'                   => 'txt',
    'peso_kg'                 => 'dec',
    'escuela'                 => 'txt',
    'grado_escolar'           => 'txt',
    'personaje_favorito'      => 'txt',
    'quien_recomendo'         => 'txt',
    'primera_visita_dentista' => 'bool',
    'ultima_visita_dentista'  => 'txt',
    'motivo_consulta'         => 'txt',

    // Embarazo y nacimiento
    'embarazo_normal'         => 'bool',
    'tipo_nacimiento'         => 'opc',
    'tipo_parto'              => 'opc',
    'complicaciones_parto'    => 'bool',
    'complicaciones_detalle'  => 'txt',

    // Alimentacion
    'lactancia_materna'       => 'bool',
    'lactancia_hasta'         => 'txt',
    'formula'                 => 'bool',
    'formula_hasta'           => 'txt',
    'inicio_alimentacion_complementaria' => 'txt',
    'dieta_completa'          => 'bool',
    'dieta_porque'            => 'txt',
    // Antes era un numero ("¿cuantas veces al dia?"). Nadie sabe
    // contestar eso; todos saben decir "a diario". La columna vieja
    // sigue en la base con lo ya capturado, pero ya no se escribe.
    'momentos_azucar'         => 'txt',
    'alimentos_azucar'        => 'txt',

    // Antecedentes patologicos
    'estado_salud'            => 'opc',
    'enfermedad_cronica'      => 'bool',
    'enfermedad_detalle'      => 'txt',
    'discapacidad'            => 'bool',
    'discapacidad_detalle'    => 'txt',
    'alergia'                 => 'bool',
    'alergia_detalle'         => 'txt',
    'tratamiento_medico'      => 'bool',
    'tratamiento_detalle'     => 'txt',
    'cirugias'                => 'bool',
    'cirugias_detalle'        => 'txt',
    'medico_nombre'           => 'txt',
    'medico_telefono'         => 'txt',

    // Antecedentes odontologicos
    'experiencia_previa'      => 'bool',
    'recibio_prevencion'      => 'bool',
    'usa_pasta_dental'        => 'bool',
    'cepillados_dia'          => 'txt',
    'otro_higiene'            => 'bool',
    'otro_higiene_detalle'    => 'txt',

    // Examen bucal
    //
    // Se redujo a tres hallazgos en texto libre. Los campos de opciones
    // (labios, frenillos, lengua) y los de observaciones salieron de la
    // pantalla; sus columnas siguen en la base con lo ya capturado,
    // pero dejan de escribirse.
    // Normal o anormal, antes de los hallazgos: con solo los tres
    // campos escritos no se podia distinguir un examen normal de uno
    // que no se hizo, porque los dos los dejan en blanco.
    'examen_bucal_normal'     => 'opc',
    'frenillo_detalle'        => 'txt',
    'mucosa'                  => 'txt',
    'lengua_detalle'          => 'txt',
    'tipo_denticion'          => 'opc',
];

$valores = [];
$errores = [];

foreach ($campos as $campo => $tipo) {
    $bruto = param($campo);

    if ($bruto === null || $bruto === '') {
        $valores[$campo] = null;
        continue;
    }

    switch ($tipo) {
        case 'txt':
            $valores[$campo] = limpiarEspacios(mayus((string) $bruto));
            break;

        case 'num':
            $valores[$campo] = (int) $bruto;
            break;

        case 'dec':
            $valores[$campo] = round((float) str_replace(',', '.', (string) $bruto), 2);
            break;

        case 'bool':
        case 'opc':
            $valores[$campo] = (int) $bruto;
            break;
    }
}

// ---------------------------------------------------------------------
// Validaciones con sentido clinico
// ---------------------------------------------------------------------
if ($valores['peso_kg'] !== null && ($valores['peso_kg'] <= 0 || $valores['peso_kg'] > 200)) {
    $errores['peso_kg'] = 'El peso no parece correcto.';
}
// La frecuencia viene de una lista cerrada, pero el valor llega por
// POST y cualquiera puede mandar otra cosa. Se valida contra la lista.
$frecuenciasAzucar = ['REGULAR', 'DIARIO', 'EVENTUALMENTE', 'NO CONSUME'];
if ($valores['momentos_azucar'] !== null
    && !in_array($valores['momentos_azucar'], $frecuenciasAzucar, true)) {
    $errores['momentos_azucar'] = 'Elige una de las opciones de la lista.';
}
if ($valores['medico_telefono'] !== null) {
    $tel = preg_replace('/\D/', '', $valores['medico_telefono']);
    if (strlen($tel) < 10) {
        $errores['medico_telefono'] = 'El telefono debe tener 10 digitos.';
    } else {
        $valores['medico_telefono'] = $tel;
    }
}

if ($errores) {
    Respuesta::validacion($errores);
}

// Casillas marcadas: llegan como "CLAVE" o "CLAVE:detalle"
$marcas = array_filter(array_map('trim', explode('|', (string) param('marcas', ''))));

$pdo = bd();
try {
    $pdo->beginTransaction();

    $existente = Conexion::valor(
        'SELECT id_historia_clinica FROM historia_clinica
          WHERE id_registro_paciente = :r',
        [':r' => $idExpediente]
    );

    $columnas = array_keys($valores);

    if ($existente) {
        $sets = implode(', ', array_map(static fn ($c) => "$c = :$c", $columnas));
        Conexion::consulta(
            "UPDATE historia_clinica
                SET $sets, id_usuario_captura = :usr
              WHERE id_historia_clinica = :id",
            array_merge($valores, [':usr' => Auth::id(), ':id' => $existente])
        );
        $idHistoria = (int) $existente;
        $accion = 'CAMBIO';
    } else {
        $listaCols = implode(', ', $columnas);
        $listaVals = implode(', ', array_map(static fn ($c) => ":$c", $columnas));
        Conexion::consulta(
            "INSERT INTO historia_clinica
                (id_registro_paciente, $listaCols, id_usuario_captura)
             VALUES (:exp, $listaVals, :usr)",
            array_merge($valores, [':exp' => $idExpediente, ':usr' => Auth::id()])
        );
        $idHistoria = Conexion::ultimoId();
        $accion = 'ALTA';
    }

    // Las casillas se reemplazan completas: es mas simple de razonar que
    // ir comparando cual se quito y cual se agrego.
    Conexion::consulta(
        'DELETE FROM historia_marca WHERE id_historia_clinica = :h',
        [':h' => $idHistoria]
    );

    foreach ($marcas as $marca) {
        [$clave, $detalle] = array_pad(explode(':', $marca, 2), 2, null);

        $idCat = Conexion::valor(
            'SELECT id_catalogo_clinico FROM catalogo_clinico
              WHERE clave = :c AND estatus = 1',
            [':c' => $clave]
        );
        if (!$idCat) {
            continue;
        }

        Conexion::consulta(
            'INSERT INTO historia_marca (id_historia_clinica, id_catalogo_clinico, detalle)
             VALUES (:h, :c, :d)',
            [':h' => $idHistoria, ':c' => $idCat,
             ':d' => $detalle !== null ? limpiarEspacios(mayus($detalle)) : null]
        );
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[HISTORIA/GUARDAR] ' . $e->getMessage());
    Respuesta::error('No se pudo guardar la historia clinica.', 500);
}

Bitacora::registrar('paciente', $accion, 'historia_clinica', (string) $idHistoria,
    ['expediente' => $idExpediente, 'marcas' => count($marcas)]);

// El pendiente de digitalizacion nace aqui, no al registrar al
// paciente: hasta este momento no habia hoja que imprimir ni que
// firmar. Un expediente recien abierto ya no aparece con faltantes de
// papeles que todavia no existen.
$pendiente = Documentos::crearPendiente(
    $idExpediente,
    'HISTORIAL_CLINICO',
    'HISTORIA CLINICA CAPTURADA EL ' . strtoupper(fecha(date('Y-m-d')))
);

Respuesta::ok(
    ['id_historia_clinica' => $idHistoria, 'pendiente' => $pendiente],
    'Historia clinica guardada.'
);
