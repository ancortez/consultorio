<?php
/**
 * Buscar paciente: filtro + lista de los ultimos 25 pacientes atendidos.
 *
 * MEJORA: el filtro no recarga la pagina. Escribe y la tabla se actualiza
 * sola (con retardo de 350 ms para no golpear la base en cada tecla).
 * Tambien busca por telefono, correo y numero de expediente, no solo por
 * nombre: en recepcion muchas veces lo unico que traen es el telefono.
 */
$limite = (int) parametro('pacientes_lista_inicio', (string) PACIENTES_INICIO);
?>
<div class="row g-3">
    <div class="col-auto" style="width:250px">
        <?php require __DIR__ . '/_menu_lateral.php'; ?>
    </div>

    <div class="col">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h1 class="titulo-vista">Buscar paciente</h1>
                <p class="subtitulo-vista">
                    &Uacute;ltimos <?= (int) $limite ?> pacientes atendidos
                </p>
            </div>
            <?php if (Auth::puede('paciente.crear')): ?>
            <button class="btn btn-morado" data-accion="nuevo-paciente">
                <i class="bi bi-person-plus me-1"></i> Nuevo paciente
            </button>
            <?php endif; ?>
        </div>

        <div class="card mb-3">
            <div class="card-body py-3">
                <div class="row g-2 align-items-end">
                    <div class="col-6">
                        <label class="form-label" for="filtroBusqueda">Filtro de b&uacute;squeda</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                            <input type="text" class="form-control" id="filtroBusqueda"
                                   placeholder="Nombre, apellido, tel&eacute;fono, correo o expediente"
                                   autocomplete="off">
                            <button class="btn btn-outline-secondary" type="button" id="limpiarFiltro"
                                    title="Limpiar">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-3">
                        <label class="form-label" for="filtroEstatus">Estatus</label>
                        <select class="form-select" id="filtroEstatus">
                            <option value="1">Activos</option>
                            <option value="0">Dados de baja</option>
                            <option value="">Todos</option>
                        </select>
                    </div>
                    <?php /* El orden lo decide el servidor, no la pantalla.

                             Ordenar aqui ordenaria solo los 25 que se
                             trajeron, y la lista viene recortada: pedir
                             "por apellido" acomodaria alfabeticamente a
                             los 25 mas recientes, que no son los 25
                             primeros por apellido. Eso se ve bien y
                             contesta otra pregunta.

                             Nombres y apellidos van separados porque son
                             dos busquedas distintas y las dos se hacen:
                             "a ver los Guzman" y "¿como se llamaba el
                             Mateo?". El nombre completo empieza por los
                             nombres, asi que una sola opcion solo sirve
                             para una de las dos. */ ?>
                    <div class="col-3">
                        <label class="form-label" for="filtroOrden">Ordenar por</label>
                        <div class="input-group">
                            <select class="form-select" id="filtroOrden">
                                <option value="ultima_consulta">&Uacute;ltima consulta</option>
                                <option value="alta">Fecha de alta</option>
                                <option value="nombres">Nombres</option>
                                <option value="apellidos">Apellidos</option>
                            </select>
                            <?php /* El sentido como boton y no como un
                                     segundo combo: son dos opciones y la
                                     flecha dice en que sentido esta
                                     ahora mismo. */ ?>
                            <button class="btn btn-outline-secondary" type="button"
                                    id="filtroSentido" data-baja="1"
                                    title="Cambiar el sentido del orden">
                                <i class="bi bi-sort-down"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-12 text-end">
                        <span class="text-secondary small" id="contadorResultados"></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="tabla-con-menu">
                <table class="table tabla-sistema align-middle">
                    <thead>
                        <tr>
                            <th style="width:22%">Nombre completo</th>
                            <th>Domicilio</th>
                            <th style="width:120px">Tel&eacute;fono</th>
                            <th style="width:170px">Correo electr&oacute;nico</th>
                            <?php /* Las dos fechas juntas y al final.

                                     Contestan "¿es paciente nuevo?" y
                                     "¿hace cuanto que no viene?", que
                                     son la misma mirada: se leen una al
                                     lado de la otra o no se leen. */ ?>
                            <th style="width:100px">Alta</th>
                            <th style="width:110px">&Uacute;lt. consulta</th>
                            <th class="col-acciones"></th>
                        </tr>
                    </thead>
                    <tbody id="tablaPacientes">
                        <tr class="fila-vacia">
                            <td colspan="7">
                                <div class="spinner-border spinner-border-sm me-2"
                                     style="color:var(--morado-600)"></div>
                                Cargando pacientes...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/_modales.php'; ?>
