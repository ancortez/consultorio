<?php
/**
 * Pestania Odontograma del paciente.
 *
 * La foto de como esta la boca HOY. Lo pidio la doctora y el sentido es
 * que ese dato no se puede reconstruir: esta repartido entre cuatro
 * planes de tratamiento, doce consultas y los hallazgos de cada una.
 * Para decidir que hacer hace falta verlo de un golpe.
 *
 * Se pican uno o varios dientes y se les pone su condicion con un clic.
 * De varios en varios porque es como se dicta: "del 14 al 17,
 * amalgama". Uno por uno serian cuatro ventanas para lo mismo.
 *
 * El dibujo es el mismo de Odontograma, el que usa el selector de
 * organo dentario del plan. No es una copia parecida: es el mismo
 * codigo, asi que las dos pantallas no se pueden separar.
 */
$estadoOdo      = Odontograma::estadoDelPaciente($idExpediente);
$condicionesOdo = Odontograma::condiciones();

// Cuando se reviso por ultima vez. Sirve para saber si lo que se ve es
// de hoy o de hace dos anios, que cambia por completo como se lee.
$fechaOdo = Conexion::valor(
    'SELECT fecha FROM odontograma
      WHERE id_registro_paciente = :r AND tipo = :t
      ORDER BY id_odontograma LIMIT 1',
    [':r' => $idExpediente, ':t' => Odontograma::TIPO_ACTUAL]
);
?>
<div id="odoPaciente" data-expediente="<?= (int) $idExpediente ?>"
     data-puede="<?= Auth::puede('paciente.editar') ? '1' : '0' ?>">

    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between">
            <span><i class="bi bi-database-fill-gear me-2"></i>Odontograma</span>
            <span class="text-secondary" style="font-size:11.5px" id="odoFecha">
                <?php if ($fechaOdo): ?>
                    Actualizado el <?= e(fecha($fechaOdo)) ?>
                <?php else: ?>
                    Sin capturar
                <?php endif; ?>
            </span>
        </div>

        <div class="card-body">

            <?php if (!Auth::puede('paciente.editar')): ?>
            <div class="alert alert-light border py-2 small">
                <i class="bi bi-eye me-1"></i>
                Puedes consultarlo, pero no cambiarlo.
            </div>
            <?php endif; ?>

            <ul class="nav nav-tabs nav-tabs-morado mb-3" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active" data-bs-toggle="tab"
                            data-bs-target="#odo-permanente" type="button">
                        Dentici&oacute;n permanente
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab"
                            data-bs-target="#odo-temporal" type="button">
                        Dentici&oacute;n temporal
                    </button>
                </li>
            </ul>

            <div class="tab-content">
                <?php foreach ([['odo-permanente', Odontograma::PERMANENTES, 'permanente', true],
                                ['odo-temporal',   Odontograma::TEMPORALES,  'temporal',   false]] as
                                [$id, $arcada, $clase, $activa]): ?>
                <div class="tab-pane fade <?= $activa ? 'show active' : '' ?>"
                     id="<?= $id ?>">
                    <?= Odontograma::arcada($arcada, $clase, $estadoOdo) ?>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if (Auth::puede('paciente.editar')): ?>
            <?php /* La paleta de condiciones, abajo del dibujo.

                     Abajo y no arriba porque el orden de las dos
                     decisiones es ese: primero se pican los dientes
                     —mirando la boca del paciente— y despues se dice
                     que tienen. Con la paleta arriba hay que subir la
                     vista para lo segundo.

                     Las trece condiciones salen del catalogo, no de una
                     lista aqui: el consultorio puede agregar una y no
                     va a venir a buscarla en este archivo. */ ?>
            <div class="paleta-odontograma mt-3" id="odoPaleta">
                <div class="po-aviso" id="odoAviso">
                    Pica los dientes que quieras marcar.
                </div>

                <div class="po-botones">
                    <?php foreach ($condicionesOdo as $c): ?>
                    <button type="button" class="chip-condicion elegible"
                            data-condicion="<?= (int) $c['id_condicion'] ?>"
                            title="Marcar los dientes elegidos como <?= e($c['nombre_condicion']) ?>">
                        <i style="background:<?= e((string) $c['color']) ?>"></i>
                        <?= e($c['nombre_condicion']) ?>
                    </button>
                    <?php endforeach; ?>

                    <?php /* Quitar NO es lo mismo que marcar "sano".

                             Quitar deja el diente sin nada dicho, que es
                             lo que hace falta para corregir un error de
                             captura. "Sano" es una afirmacion de la
                             doctora: dice que ese diente se reviso y
                             esta bien. Confundirlas convertiria el
                             odontograma en algo que no se puede
                             deshacer. */ ?>
                    <button type="button" class="chip-condicion elegible quitar"
                            data-condicion="0"
                            title="Dejar los dientes elegidos sin nada marcado">
                        <i class="vacio"></i>
                        Quitar la marca
                    </button>
                </div>
            </div>
            <?php endif; ?>

            <?php /* La leyenda: solo las condiciones que el paciente
                     tiene. Con las trece ocuparia mas que la dentadura y
                     la mayoria no estaria en el dibujo. */ ?>
            <div class="leyenda-odontograma mt-3" id="odoLeyenda"></div>
        </div>
    </div>
</div>
