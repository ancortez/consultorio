<?php
/**
 * Resumen de contacto, tutor y domicilio.
 *
 * Tres bloques que se LEEN en la ficha y se ADMINISTRAN en su modal.
 *
 * Cada uno tiene dos caras:
 *   - con datos:  el resumen y un lapiz que abre la lista completa
 *   - sin datos:  el aviso de que falta y un "+" que va DIRECTO al
 *                 alta, sin pasar por una lista vacia
 *
 * Lo segundo importa mas de lo que parece. Mandar a alguien a una
 * lista vacia para que ahi pique "agregar" es un clic de mas y una
 * pantalla que no dice nada. Si no hay nada, lo unico que se puede
 * hacer es crear: hay que llevarlo ahi.
 *
 * El amarillo no es decoracion. Un paciente menor de edad sin tutor
 * registrado no se puede atender legalmente, y un expediente sin
 * domicilio incumple la NOM del expediente clinico. Son huecos que
 * hay que cerrar, no datos opcionales que faltan.
 *
 * Los tres bloques ya no van juntos en una columna: cada uno se pide
 * por separado con $bloquesResumen, porque en la rejilla nueva caen en
 * sitios distintos —el contacto a la derecha de los datos, el tutor
 * como un renglon mas de los datos, y el domicilio abajo a todo lo
 * ancho, que es donde una direccion se lee sin cortarse—. Sin el
 * filtro habria que duplicar el archivo tres veces para mover de sitio
 * lo mismo.
 */

// Quien no diga nada los quiere los tres: asi el archivo sigue
// sirviendo tal cual en cualquier otra pantalla.
$bloquesResumen = $bloquesResumen ?? ['contacto', 'tutor', 'domicilio'];
$toca = static fn (string $b): bool => in_array($b, $bloquesResumen, true);

// ---------------------------------------------------------------------
// Tutor: se revisa que este completo, no solo que exista
// ---------------------------------------------------------------------
$tutorPrincipal = $tutores[0] ?? null;

$faltaTutor = [];
if ($tutorPrincipal !== null) {
    if (empty($tutorPrincipal['nombre_parentesco'])) {
        $faltaTutor[] = 'parentesco';
    }
    if (empty($tutorPrincipal['domicilio'])) {
        $faltaTutor[] = 'domicilio';
    }
}

// Un paciente menor sin tutor es el hueco mas grave de todos.
$esMenor = $pac['edad'] !== null && (int) $pac['edad'] < 18;

/*
   Si el paciente respondio que actua por su cuenta, el bloque del tutor
   no se pinta en absoluto.

   No se pinta vacio ni con un aviso: desaparece. Un adulto que firma
   por si mismo no tiene un "hueco" donde deberia ir su tutor, y dejar
   el bloque en amarillo diciendo que falta algo seria pedirle a
   recepcion que capture un dato que no existe.

   Quien llame a este archivo sin definirlo —otra pantalla, una
   impresion— se comporta como siempre: con tutor.
*/
$llevaTutor = $llevaTutor ?? true;
?>

<div class="bloque-resumen">

    <?php /* ---------- Canales de contacto ---------- */ ?>
    <?php if ($toca('contacto')): ?>
    <div class="resumen-dato <?= $contactos ? '' : 'falta' ?>">
        <div class="cabeza">
            <i class="bi bi-chat-dots"></i>
            <span>Canales de contacto</span>

            <?php if ($contactos): ?>
            <button type="button" class="lapiz" data-abrir="contactos"
                    title="Administrar los canales de contacto">
                <i class="bi bi-pencil"></i>
            </button>
            <?php else: ?>
            <button type="button" class="mas" data-nuevo="contacto"
                    title="Registrar un canal de contacto">
                <i class="bi bi-plus-lg"></i>
            </button>
            <?php endif; ?>
        </div>

        <?php if (!$contactos): ?>
            <div class="vacio">Sin canales de contacto registrados.</div>
        <?php else: ?>
            <?php
            // Agrupados por medio: tres telefonos del mismo tipo se leen
            // mejor juntos que como tres renglones repitiendo la etiqueta.
            $porMedio = [];
            foreach ($contactos as $c) {
                // La columna se llama valor_contacto, no valor. Escribirla
                // corta no truena nada: PHP avisa y pinta una cadena vacia,
                // asi que el telefono simplemente no aparece.
                $porMedio[$c['nombre_medio_contacto']][] = $c['valor_contacto'];
            }
            ?>
            <div class="rejilla">
                <?php foreach ($porMedio as $medio => $valores): ?>
                <div class="par">
                    <span class="et"><?= e($medio) ?></span>
                    <span class="va">
                        <?php foreach ($valores as $v): ?>
                        <span class="d-block"><?= e($v) ?></span>
                        <?php endforeach; ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php /* ---------- Tutor ---------- */ ?>
    <?php if ($toca('tutor') && $llevaTutor): ?>
    <div class="resumen-dato <?= $tutorPrincipal === null
            ? ($esMenor ? 'falta' : '')
            : ($faltaTutor ? 'incompleto' : '') ?>">
        <div class="cabeza">
            <i class="bi bi-people"></i>
            <span>Tutor</span>

            <?php if ($tutorPrincipal !== null): ?>
            <button type="button" class="lapiz" data-abrir="tutores"
                    title="Administrar los tutores">
                <i class="bi bi-pencil"></i>
            </button>
            <?php else: ?>
            <button type="button" class="mas" data-nuevo="tutor"
                    title="Registrar un tutor">
                <i class="bi bi-plus-lg"></i>
            </button>
            <?php endif; ?>
        </div>

        <?php if ($tutorPrincipal === null): ?>
            <div class="vacio">
                <?= $esMenor
                    ? 'Sin tutor registrado. Un paciente menor de edad lo necesita.'
                    : 'Sin tutor registrado.' ?>
            </div>
        <?php else: ?>
            <div class="linea">
                <b><?= e(nombreCompleto($tutorPrincipal['nombres'],
                        $tutorPrincipal['ap_paterno'],
                        $tutorPrincipal['ap_materno'])) ?></b>
                <?php if ($tutorPrincipal['nombre_parentesco']): ?>
                    &middot; <?= e($tutorPrincipal['nombre_parentesco']) ?>
                <?php endif; ?>
                <?php if ($tutorPrincipal['domicilio']): ?>
                    &middot; <?= e($tutorPrincipal['domicilio']) ?>
                <?php endif; ?>
                <?php if (count($tutores) > 1): ?>
                    <span class="mas-tutores">
                        +<?= count($tutores) - 1 ?> m&aacute;s
                    </span>
                <?php endif; ?>
            </div>

            <?php if ($faltaTutor): ?>
            <div class="aviso-falta">
                <i class="bi bi-exclamation-triangle-fill"></i>
                Falta el <?= e(implode(' y el ', $faltaTutor)) ?> del tutor.
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php /* ---------- Domicilio ---------- */ ?>
    <?php if ($toca('domicilio')): ?>
    <div class="resumen-dato <?= $domPre ? '' : 'falta' ?>">
        <div class="cabeza">
            <i class="bi bi-geo-alt"></i>
            <span>Domicilio</span>

            <?php if ($domPre): ?>
            <button type="button" class="lapiz" data-abrir="domicilio"
                    title="Modificar el domicilio">
                <i class="bi bi-pencil"></i>
            </button>
            <?php else: ?>
            <button type="button" class="mas" data-nuevo="domicilio"
                    title="Registrar el domicilio">
                <i class="bi bi-plus-lg"></i>
            </button>
            <?php endif; ?>
        </div>

        <?php if (!$domPre): ?>
            <div class="vacio">
                Sin domicilio registrado. El expediente cl&iacute;nico lo
                requiere.
            </div>
        <?php else: ?>
            <?php
            /*
               Dos renglones y en este orden: primero donde vive, luego
               como se llega.

               La primera linea es la direccion postal de lo general a
               lo particular, como se dicta en voz alta. La segunda es
               lo que sirve para encontrar la casa, y solo aparece si
               hay algo que decir.
            */
            $dondeVive = array_filter([
                $domPre['nombre_estado']     ?? null,
                $domPre['nombre_municipio']  ?? null,
                $domPre['colonia']           ?? null,
                !empty($domPre['codigo_postal'])
                    ? 'C.P. ' . $domPre['codigo_postal'] : null,
                $domPre['calle_numero']      ?? null,
            ]);

            $entre = array_filter([
                $domPre['entre_calle_1'] ?? null,
                $domPre['entre_calle_2'] ?? null,
            ]);

            $comoLlegar = array_filter([
                $entre ? 'Entre ' . implode(' y ', $entre) : null,
                $domPre['referencia'] ?? null,
            ]);
            ?>
            <div class="linea"><?= e(implode(', ', $dondeVive)) ?></div>
            <?php if ($comoLlegar): ?>
            <div class="linea secundaria"><?= e(implode('. ', $comoLlegar)) ?></div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>
