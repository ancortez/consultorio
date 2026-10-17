-- =====================================================================
--  SISTEMA CONSULTORIO DENTAL - ESQUEMA DE BASE DE DATOS
--  Version: 1.0.0
--  Motor:   MySQL 8.0 / InnoDB / utf8mb4
--
--  Este archivo reemplaza y corrige el modelo original (modelo-er-bd.mwb).
--  Los cambios respecto al original estan documentados en
--  docs/Propuesta-Mejoras-Consultorio-Dental.docx
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET time_zone = '-06:00';

CREATE DATABASE IF NOT EXISTS consultorio_dental
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE consultorio_dental;

-- =====================================================================
-- SECCION 1 : CATALOGOS GEOGRAFICOS
-- =====================================================================

CREATE TABLE estado (
  id_estado       SMALLINT UNSIGNED NOT NULL,
  nombre_estado   VARCHAR(60)  NOT NULL,
  abreviatura     VARCHAR(10)  NULL,
  clave_inegi     CHAR(2)      NULL,
  PRIMARY KEY (id_estado),
  UNIQUE KEY uq_estado_nombre (nombre_estado)
) ENGINE=InnoDB;

CREATE TABLE municipio (
  id_municipio      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_estado         SMALLINT UNSIGNED NOT NULL,
  nombre_municipio  VARCHAR(80) NOT NULL,
  clave_inegi       CHAR(3)     NULL,
  PRIMARY KEY (id_municipio),
  UNIQUE KEY uq_municipio (id_estado, nombre_municipio),
  KEY ix_municipio_estado (id_estado),
  CONSTRAINT fk_municipio_estado FOREIGN KEY (id_estado)
    REFERENCES estado (id_estado) ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Tipos de asentamiento (colonia, fraccionamiento, ejido...).
-- Antes era texto libre dentro de colonia, lo que permitia que la misma
-- cosa se escribiera de tres formas distintas. Como catalogo, la lista
-- se administra en un solo lugar y los combos salen de aqui.
CREATE TABLE tipo_asenta (
  id_tipo_asenta     SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre_tipo_asenta VARCHAR(40) NOT NULL,
  estatus            TINYINT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (id_tipo_asenta),
  UNIQUE KEY uq_tipo_asenta (nombre_tipo_asenta)
) ENGINE=InnoDB;

CREATE TABLE colonia (
  id_colonia      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_municipio    INT UNSIGNED NOT NULL,
  nombre_colonia  VARCHAR(120) NOT NULL,
  codigo_postal   CHAR(5)      NOT NULL,
  id_tipo_asenta  SMALLINT UNSIGNED NULL,
  PRIMARY KEY (id_colonia),
  KEY ix_colonia_municipio (id_municipio),
  KEY ix_colonia_cp (codigo_postal),
  KEY ix_colonia_tipo (id_tipo_asenta),
  CONSTRAINT fk_colonia_municipio FOREIGN KEY (id_municipio)
    REFERENCES municipio (id_municipio) ON UPDATE CASCADE,
  CONSTRAINT fk_colonia_tipo FOREIGN KEY (id_tipo_asenta)
    REFERENCES tipo_asenta (id_tipo_asenta) ON UPDATE CASCADE
) ENGINE=InnoDB;

-- MEJORA: el domicilio ya no cuelga de persona (1:1). Es una entidad
-- independiente que se relaciona N:M con persona via persona_domicilio.
-- Solo se guarda id_colonia; estado y municipio se derivan por JOIN.
CREATE TABLE domicilio (
  id_domicilio    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_colonia      INT UNSIGNED NULL,
  calle           VARCHAR(150) NULL,
  num_ext         VARCHAR(20)  NULL,
  num_int         VARCHAR(20)  NULL,
  entre_calle_1   VARCHAR(150) NULL,
  entre_calle_2   VARCHAR(150) NULL,
  referencia      TEXT         NULL,
  -- respaldo textual por si la colonia no existe en el catalogo
  colonia_libre   VARCHAR(120) NULL,
  cp_libre        CHAR(5)      NULL,
  estatus         TINYINT UNSIGNED NOT NULL DEFAULT 1,
  fecha_registro  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_domicilio),
  KEY ix_domicilio_colonia (id_colonia),
  CONSTRAINT fk_domicilio_colonia FOREIGN KEY (id_colonia)
    REFERENCES colonia (id_colonia) ON UPDATE CASCADE
) ENGINE=InnoDB;

-- =====================================================================
-- SECCION 2 : PERSONAS
-- =====================================================================

CREATE TABLE genero (
  id_genero      TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre_genero  VARCHAR(30) NOT NULL,
  PRIMARY KEY (id_genero),
  UNIQUE KEY uq_genero (nombre_genero)
) ENGINE=InnoDB;

CREATE TABLE tipo_persona (
  id_tipo_persona TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tipo_persona    VARCHAR(30) NOT NULL,
  PRIMARY KEY (id_tipo_persona),
  UNIQUE KEY uq_tipo_persona (tipo_persona)
) ENGINE=InnoDB;

CREATE TABLE parentesco (
  id_parentesco     TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre_parentesco VARCHAR(40) NOT NULL,
  PRIMARY KEY (id_parentesco),
  UNIQUE KEY uq_parentesco (nombre_parentesco)
) ENGINE=InnoDB;

-- Catalogo de ocupaciones. Se liga a persona (no al expediente) porque
-- los tutores tambien tienen ocupacion y no tienen expediente propio.
CREATE TABLE ocupacion (
  id_ocupacion     SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  -- clave estable para las reglas del sistema (NINGUNA, ESTUDIANTE).
  -- El codigo nunca depende de un id que podria cambiar.
  clave            VARCHAR(20) NULL,
  nombre_ocupacion VARCHAR(80) NOT NULL,
  estatus          TINYINT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (id_ocupacion),
  UNIQUE KEY uq_ocupacion (nombre_ocupacion),
  KEY ix_ocupacion_clave (clave),
  KEY ix_ocupacion_nombre (nombre_ocupacion)
) ENGINE=InnoDB;

CREATE TABLE medio_contacto (
  id_medio_contacto     TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre_medio_contacto VARCHAR(50) NOT NULL,
  descripcion           VARCHAR(150) NULL,
  -- 'tel' | 'email' | 'url' | 'texto' : para validar del lado del front
  tipo_dato             VARCHAR(10) NOT NULL DEFAULT 'texto',
  PRIMARY KEY (id_medio_contacto),
  UNIQUE KEY uq_medio_contacto (nombre_medio_contacto)
) ENGINE=InnoDB;

-- MEJORA: se elimina persona.id_domicilio y persona.id_registro_paciente.
-- Una persona existe por si misma; sus vinculos van en tablas puente.
CREATE TABLE persona (
  id_persona       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombres          VARCHAR(80) NOT NULL,
  ap_paterno       VARCHAR(60) NOT NULL,
  ap_materno       VARCHAR(60) NULL,
  fecha_nacim      DATE        NULL,
  id_genero        TINYINT UNSIGNED NULL,
  id_tipo_persona  TINYINT UNSIGNED NOT NULL,
  id_ocupacion     SMALLINT UNSIGNED NULL,
  rfc              VARCHAR(13) NULL,
  curp             CHAR(18)    NULL,
  foto             VARCHAR(255) NULL,
  estatus          TINYINT UNSIGNED NOT NULL DEFAULT 1,
  fecha_registro   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_modif      DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_persona),
  KEY ix_persona_tipo (id_tipo_persona),
  KEY ix_persona_genero (id_genero),
  -- indice clave para la busqueda de pacientes por nombre
  KEY ix_persona_nombre (ap_paterno, ap_materno, nombres),
  KEY ix_persona_rfc (rfc),
  KEY ix_persona_ocupacion (id_ocupacion),
  CONSTRAINT fk_persona_genero FOREIGN KEY (id_genero)
    REFERENCES genero (id_genero) ON UPDATE CASCADE,
  CONSTRAINT fk_persona_tipo FOREIGN KEY (id_tipo_persona)
    REFERENCES tipo_persona (id_tipo_persona) ON UPDATE CASCADE,
  CONSTRAINT fk_persona_ocupacion FOREIGN KEY (id_ocupacion)
    REFERENCES ocupacion (id_ocupacion) ON UPDATE CASCADE
) ENGINE=InnoDB;

-- MEJORA: una persona puede tener N domicilios (la vista detalle_paciente
-- pide una tabla de "domicilios existentes de este paciente").
CREATE TABLE persona_domicilio (
  id_persona_domicilio INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_persona           INT UNSIGNED NOT NULL,
  id_domicilio         INT UNSIGNED NOT NULL,
  es_principal         TINYINT UNSIGNED NOT NULL DEFAULT 0,
  estatus              TINYINT UNSIGNED NOT NULL DEFAULT 1,
  fecha_registro       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_persona_domicilio),
  UNIQUE KEY uq_persona_domicilio (id_persona, id_domicilio),
  KEY ix_pd_domicilio (id_domicilio),
  CONSTRAINT fk_pd_persona FOREIGN KEY (id_persona)
    REFERENCES persona (id_persona),
  CONSTRAINT fk_pd_domicilio FOREIGN KEY (id_domicilio)
    REFERENCES domicilio (id_domicilio)
) ENGINE=InnoDB;

-- MEJORA: renombrada desde contacto_cliente y eliminada la columna
-- id_medio_contacto duplicada del modelo original.
CREATE TABLE contacto_persona (
  id_contacto_persona INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_persona          INT UNSIGNED NOT NULL,
  id_medio_contacto   TINYINT UNSIGNED NOT NULL,
  valor_contacto      VARCHAR(150) NOT NULL,
  es_principal        TINYINT UNSIGNED NOT NULL DEFAULT 0,
  estatus             TINYINT UNSIGNED NOT NULL DEFAULT 1,
  fecha_registro      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_contacto_persona),
  KEY ix_cp_persona (id_persona),
  KEY ix_cp_medio (id_medio_contacto),
  KEY ix_cp_valor (valor_contacto),
  CONSTRAINT fk_cp_persona FOREIGN KEY (id_persona)
    REFERENCES persona (id_persona),
  CONSTRAINT fk_cp_medio FOREIGN KEY (id_medio_contacto)
    REFERENCES medio_contacto (id_medio_contacto) ON UPDATE CASCADE
) ENGINE=InnoDB;

-- =====================================================================
-- SECCION 3 : EXPEDIENTE DEL PACIENTE
-- =====================================================================

-- MEJORA: registro_paciente en el modelo original solo tenia id y estatus.
-- Aqui se convierte en el EXPEDIENTE: apunta al paciente titular, lleva
-- folio legible, fecha de apertura y los datos clinicos generales.
CREATE TABLE registro_paciente (
  id_registro_paciente INT UNSIGNED NOT NULL AUTO_INCREMENT,
  num_expediente       VARCHAR(20)  NOT NULL,
  id_persona           INT UNSIGNED NOT NULL,
  fecha_apertura       DATE NOT NULL,
  -- La ocupacion vive en persona, no aqui: los tutores tambien la tienen
  -- y no tienen expediente.
  motivo_primera_visita TEXT        NULL,
  -- Nanai atiende ninios y adultos. NULL = todavia nadie ha dicho si
  -- este paciente lleva tutor; 1 = lo lleva; 0 = responde por si mismo.
  -- La edad de la decision permite volver a preguntar al llegar a 24
  -- sin preguntar en cada visita.
  requiere_tutor       TINYINT UNSIGNED NULL,
  tutor_decidido_edad  TINYINT UNSIGNED NULL,
  observaciones        TEXT         NULL,
  estatus              TINYINT UNSIGNED NOT NULL DEFAULT 1,
  fecha_registro       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_registro_paciente),
  UNIQUE KEY uq_expediente_folio (num_expediente),
  UNIQUE KEY uq_expediente_persona (id_persona),
  CONSTRAINT fk_expediente_persona FOREIGN KEY (id_persona)
    REFERENCES persona (id_persona)
) ENGINE=InnoDB;

-- MEJORA: un tutor puede serlo de varios pacientes (hermanos) y un
-- paciente puede tener varios tutores. El modelo original lo impedia.
CREATE TABLE expediente_tutor (
  id_expediente_tutor  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_registro_paciente INT UNSIGNED NOT NULL,
  id_persona           INT UNSIGNED NOT NULL,  -- persona tipo Tutor
  id_parentesco        TINYINT UNSIGNED NULL,
  es_responsable_pago  TINYINT UNSIGNED NOT NULL DEFAULT 0,
  estatus              TINYINT UNSIGNED NOT NULL DEFAULT 1,
  fecha_registro       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_expediente_tutor),
  UNIQUE KEY uq_expediente_tutor (id_registro_paciente, id_persona),
  KEY ix_et_persona (id_persona),
  CONSTRAINT fk_et_expediente FOREIGN KEY (id_registro_paciente)
    REFERENCES registro_paciente (id_registro_paciente),
  CONSTRAINT fk_et_persona FOREIGN KEY (id_persona)
    REFERENCES persona (id_persona),
  CONSTRAINT fk_et_parentesco FOREIGN KEY (id_parentesco)
    REFERENCES parentesco (id_parentesco) ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Identificacion oficial del tutor.
--
-- Va en persona y no en expediente_tutor: la credencial es de la persona,
-- no del vinculo con un paciente. Si el mismo tutor lleva a dos hijos, su
-- INE se captura una sola vez.
CREATE TABLE persona_identificacion (
  id_persona_identificacion INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_persona                INT UNSIGNED NOT NULL,
  id_tipo_identificacion    TINYINT UNSIGNED NOT NULL,
  numero_identificacion     VARCHAR(60) NOT NULL,
  fecha_emision             DATE NULL,
  fecha_vigencia            DATE NULL,
  estatus                   TINYINT UNSIGNED NOT NULL DEFAULT 1,
  fecha_registro            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_persona_identificacion),
  KEY ix_pi_persona (id_persona),
  KEY ix_pi_vigencia (fecha_vigencia),
  CONSTRAINT fk_pi_persona FOREIGN KEY (id_persona)
    REFERENCES persona (id_persona),
  CONSTRAINT fk_pi_tipo FOREIGN KEY (id_tipo_identificacion)
    REFERENCES tipo_identificacion (id_tipo_identificacion) ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- EXPEDIENTE ELECTRONICO
--
-- El consultorio imprime, firma y escanea varios documentos. Aqui se
-- lleva el control de cuales faltan y donde quedo guardado cada uno.
--
-- Los archivos NO viven en la base: se guardan en MinIO y aqui solo se
-- anota la ruta. Meter PDFs y fotos en MySQL infla los respaldos hasta
-- volverlos inmanejables y hace lenta cualquier consulta.
-- ---------------------------------------------------------------------

CREATE TABLE tipo_documento (
  id_tipo_documento TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  clave             VARCHAR(30) NOT NULL,
  nombre_documento  VARCHAR(80) NOT NULL,
  descripcion       VARCHAR(200) NULL,
  icono             VARCHAR(40) NULL,
  -- 1 = al crearse el paciente se genera solo el pendiente
  pendiente_al_alta TINYINT UNSIGNED NOT NULL DEFAULT 0,
  -- 1 = se puede volver a pedir varias veces (planes, consentimientos)
  permite_varios    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  -- Donde vive el documento dentro del expediente:
  --   PACIENTE = arriba, junto a la foto y el historial
  --   PLAN     = dentro del bloque del plan de tratamiento al que pertenece
  -- Con PLAN, "unico" significa unico POR PLAN, no por paciente.
  ambito            VARCHAR(10) NOT NULL DEFAULT 'PACIENTE',
  -- extensiones aceptadas, separadas por coma
  formatos          VARCHAR(80) NOT NULL DEFAULT 'pdf,jpg,jpeg,png',
  orden             TINYINT UNSIGNED NOT NULL DEFAULT 0,
  estatus           TINYINT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (id_tipo_documento),
  UNIQUE KEY uq_tipo_documento (clave)
) ENGINE=InnoDB;

-- Un renglon por documento del expediente. Nace como pendiente y pasa a
-- digitalizado cuando alguien sube el archivo.
CREATE TABLE documento (
  id_documento         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_registro_paciente INT UNSIGNED NOT NULL,
  id_tipo_documento    TINYINT UNSIGNED NOT NULL,

  -- 0 = pendiente de digitalizar | 1 = digitalizado | 2 = no aplica
  estado               TINYINT UNSIGNED NOT NULL DEFAULT 0,

  -- Donde vive el documento dentro del expediente. Se lee en cascada:
  --   id_consulta          -> cuelga de esa consulta
  --   id_plan_tratamiento  -> cuelga del plan
  --   ninguno de los dos   -> documento general del paciente
  -- Una consulta que pertenece a un plan arrastra ahi sus documentos,
  -- por eso no hace falta llenar los dos campos.
  -- Las llaves foraneas se agregan al final del archivo, cuando las
  -- tablas apuntadas ya existen.
  id_plan_tratamiento  INT UNSIGNED NULL,
  id_consulta          INT UNSIGNED NULL,
  -- Hallazgo del que salio la imagen, si salio de uno. Un hallazgo
  -- puede tener varias: "fractura del 11" se documenta con la foto
  -- intraoral y con dos periapicales desde angulos distintos. La
  -- llave foranea se agrega al final del archivo, porque hallazgo se
  -- declara despues.
  id_hallazgo          INT UNSIGNED NULL,

  -- Referencia a lo que origino el pendiente (un plan, un tutor...).
  -- Sirve para saber a que se refiere sin tener que adivinar.
  origen_tabla         VARCHAR(40) NULL,
  origen_id            INT UNSIGNED NULL,
  descripcion          VARCHAR(200) NULL,

  -- Datos del archivo en MinIO. Vacios mientras esta pendiente.
  ruta_archivo         VARCHAR(255) NULL,
  nombre_original      VARCHAR(200) NULL,
  tipo_mime            VARCHAR(80)  NULL,
  tamano_bytes         INT UNSIGNED NULL,
  -- huella del contenido: detecta si alguien reemplazo el archivo
  hash_sha256          CHAR(64)     NULL,

  id_usuario_solicita  INT UNSIGNED NULL,
  fecha_solicitud      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  id_usuario_sube      INT UNSIGNED NULL,
  fecha_digitalizacion DATETIME NULL,

  estatus              TINYINT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (id_documento),
  KEY ix_doc_expediente (id_registro_paciente, estado),
  KEY ix_doc_plan (id_plan_tratamiento),
  KEY ix_doc_consulta (id_consulta),
  KEY ix_doc_hallazgo (id_hallazgo),
  KEY ix_doc_tipo (id_tipo_documento),
  KEY ix_doc_origen (origen_tabla, origen_id),
  CONSTRAINT fk_doc_expediente FOREIGN KEY (id_registro_paciente)
    REFERENCES registro_paciente (id_registro_paciente),
  CONSTRAINT fk_doc_tipo FOREIGN KEY (id_tipo_documento)
    REFERENCES tipo_documento (id_tipo_documento) ON UPDATE CASCADE,
  CONSTRAINT fk_doc_usuario_sol FOREIGN KEY (id_usuario_solicita)
    REFERENCES usuario (id_usuario),
  CONSTRAINT fk_doc_usuario_sub FOREIGN KEY (id_usuario_sube)
    REFERENCES usuario (id_usuario)
) ENGINE=InnoDB;

-- ---------- Identificacion oficial del tutor ----------

CREATE TABLE tipo_identificacion (
  id_tipo_identificacion TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre_identificacion  VARCHAR(60) NOT NULL,
  -- 1 = el sistema pide fecha de vigencia
  tiene_vigencia         TINYINT UNSIGNED NOT NULL DEFAULT 1,
  estatus                TINYINT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (id_tipo_identificacion),
  UNIQUE KEY uq_tipo_identificacion (nombre_identificacion)
) ENGINE=InnoDB;

-- ---------- Historia clinica ----------

CREATE TABLE tipo_antecedente (
  id_tipo_antecedente TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre_tipo         VARCHAR(50) NOT NULL,   -- Patologico, Alergia, Habito...
  PRIMARY KEY (id_tipo_antecedente)
) ENGINE=InnoDB;

CREATE TABLE antecedente (
  id_antecedente      SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_tipo_antecedente TINYINT UNSIGNED NOT NULL,
  nombre_antecedente  VARCHAR(100) NOT NULL,
  requiere_alerta     TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id_antecedente),
  KEY ix_ant_tipo (id_tipo_antecedente),
  CONSTRAINT fk_ant_tipo FOREIGN KEY (id_tipo_antecedente)
    REFERENCES tipo_antecedente (id_tipo_antecedente) ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE expediente_antecedente (
  id_expediente_antecedente INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_registro_paciente      INT UNSIGNED NOT NULL,
  id_antecedente            SMALLINT UNSIGNED NOT NULL,
  detalle                   VARCHAR(255) NULL,
  estatus                   TINYINT UNSIGNED NOT NULL DEFAULT 1,
  fecha_registro            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_expediente_antecedente),
  UNIQUE KEY uq_exp_ant (id_registro_paciente, id_antecedente),
  KEY ix_ea_antecedente (id_antecedente),
  CONSTRAINT fk_ea_expediente FOREIGN KEY (id_registro_paciente)
    REFERENCES registro_paciente (id_registro_paciente),
  CONSTRAINT fk_ea_antecedente FOREIGN KEY (id_antecedente)
    REFERENCES antecedente (id_antecedente)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- HISTORIA CLINICA DE ODONTOPEDIATRIA
--
-- Sigue las dos hojas que usa el consultorio en papel. Se parte en dos
-- tablas y no en una sola de sesenta columnas:
--   historia_clinica  -> los datos que son uno por paciente
--   expediente_habito / expediente_hallazgo -> las casillas marcables
--
-- Asi agregar una casilla nueva es un renglon de catalogo, no un ALTER
-- TABLE cada vez que la doctora quiera preguntar algo mas.
-- ---------------------------------------------------------------------

CREATE TABLE historia_clinica (
  id_historia_clinica  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_registro_paciente INT UNSIGNED NOT NULL,

  -- ---- Ficha de identificacion ----
  apodo                VARCHAR(60)  NULL,   -- "le gusta que le llamen"
  -- numero_hijo y estatura_cm salieron de la pantalla en octubre 2026
  -- (la doctora no los usaba). Las columnas se conservan: lo que se
  -- capturo en su momento es parte del expediente de ese paciente y no
  -- se borra porque el formulario haya cambiado.
  numero_hijo          TINYINT UNSIGNED NULL,
  estatura_cm          SMALLINT UNSIGNED NULL,
  peso_kg              DECIMAL(5,2) NULL,
  escuela              VARCHAR(120) NULL,
  grado_escolar        VARCHAR(40)  NULL,
  personaje_favorito   VARCHAR(80)  NULL,
  quien_recomendo      VARCHAR(120) NULL,
  primera_visita_dentista TINYINT UNSIGNED NULL,   -- 1 si | 0 no
  ultima_visita_dentista  VARCHAR(120) NULL,
  motivo_consulta      TEXT NULL,

  -- ---- Antecedentes no patologicos: embarazo y nacimiento ----
  embarazo_normal      TINYINT UNSIGNED NULL,
  -- 1 a termino | 2 prematuro | 3 post-termino
  tipo_nacimiento      TINYINT UNSIGNED NULL,
  -- 1 natural | 2 cesarea
  tipo_parto           TINYINT UNSIGNED NULL,
  complicaciones_parto TINYINT UNSIGNED NULL,
  complicaciones_detalle VARCHAR(255) NULL,

  -- ---- Alimentacion ----
  lactancia_materna    TINYINT UNSIGNED NULL,
  lactancia_hasta      VARCHAR(60) NULL,
  formula              TINYINT UNSIGNED NULL,
  formula_hasta        VARCHAR(60) NULL,
  inicio_alimentacion_complementaria VARCHAR(60) NULL,
  -- 1 completa | 0 incompleta
  dieta_completa       TINYINT UNSIGNED NULL,
  dieta_porque         VARCHAR(255) NULL,
  -- momentos_azucar_dia pedia un numero ("¿cuantas veces al dia?") y
  -- nadie sabe contestarlo. Se reemplazo por una frecuencia, que es
  -- como la gente si piensa el asunto. La columna vieja se conserva
  -- con lo ya capturado.
  momentos_azucar_dia  TINYINT UNSIGNED NULL,
  -- REGULAR | DIARIO | EVENTUALMENTE | NO CONSUME
  momentos_azucar      VARCHAR(20) NULL,
  alimentos_azucar     TEXT NULL,

  -- ---- Antecedentes patologicos ----
  -- 1 bueno | 2 regular | 3 malo
  estado_salud         TINYINT UNSIGNED NULL,
  enfermedad_cronica   TINYINT UNSIGNED NULL,
  enfermedad_detalle   VARCHAR(255) NULL,
  discapacidad         TINYINT UNSIGNED NULL,
  discapacidad_detalle VARCHAR(255) NULL,
  alergia              TINYINT UNSIGNED NULL,
  alergia_detalle      VARCHAR(255) NULL,
  tratamiento_medico   TINYINT UNSIGNED NULL,
  tratamiento_detalle  VARCHAR(255) NULL,
  cirugias             TINYINT UNSIGNED NULL,
  cirugias_detalle     VARCHAR(255) NULL,
  medico_nombre        VARCHAR(120) NULL,
  medico_telefono      VARCHAR(30)  NULL,

  -- ---- Antecedentes odontologicos ----
  experiencia_previa   TINYINT UNSIGNED NULL,
  recibio_prevencion   TINYINT UNSIGNED NULL,
  usa_pasta_dental     TINYINT UNSIGNED NULL,
  -- En palabras, no un numero. Lo que contesta el tutor es "en la
  -- maniana y en la noche" o "solo cuando se acuerda", y de eso un
  -- entero se queda con la mitad: un "1" no distingue "uno al dia,
  -- siempre" de "uno, cuando se acuerda", que para decidir si hay que
  -- insistir con la higiene es justo la diferencia.
  cepillados_dia       VARCHAR(100) NULL,
  otro_higiene         TINYINT UNSIGNED NULL,
  otro_higiene_detalle VARCHAR(255) NULL,

  -- ---- Examen bucal: tejidos blandos ----
  -- 1 competentes | 2 incompetentes
  labios               TINYINT UNSIGNED NULL,
  labios_lesiones      VARCHAR(255) NULL,
  -- 1 normales | 2 anormales
  frenillos            TINYINT UNSIGNED NULL,
  -- 1 normal | 2 macroglosia | 3 geografica | 4 escrotal
  lengua               TINYINT UNSIGNED NULL,
  mucosa               VARCHAR(255) NULL,
  -- ¿El examen bucal salio normal?
  --
  -- 1 normal | 0 anormal. Con los tres hallazgos escritos no se podia
  -- distinguir un examen normal de un examen que no se hizo: los dos
  -- dejaban los campos en blanco, y en una hoja firmada son dos cosas
  -- muy distintas. Asi "NORMAL" es una afirmacion.
  --
  -- NULL en las historias capturadas antes de que existiera: de esas no
  -- se sabe, y poner 1 seria afirmar en nombre de la doctora.
  examen_bucal_normal  TINYINT UNSIGNED NULL DEFAULT 1,
  -- Octubre 2026: el examen bucal se redujo a tres hallazgos escritos.
  -- Los campos de opciones (labios, frenillos, lengua) dejaron de
  -- usarse; sus columnas se conservan con lo ya capturado.
  frenillo_detalle     VARCHAR(255) NULL,
  lengua_detalle       VARCHAR(255) NULL,
  lesiones_gingival    VARCHAR(255) NULL,
  observaciones_blandos TEXT NULL,

  -- ---- Examen bucal: tejidos duros ----
  -- 1 temporal | 2 mixta | 3 permanente
  tipo_denticion       TINYINT UNSIGNED NULL,
  observaciones_duros  TEXT NULL,

  -- ---- Control ----
  id_usuario_captura   INT UNSIGNED NULL,
  fecha_captura        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_modif          DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id_historia_clinica),
  UNIQUE KEY uq_historia_expediente (id_registro_paciente),
  CONSTRAINT fk_hc_expediente FOREIGN KEY (id_registro_paciente)
    REFERENCES registro_paciente (id_registro_paciente),
  CONSTRAINT fk_hc_usuario FOREIGN KEY (id_usuario_captura)
    REFERENCES usuario (id_usuario)
) ENGINE=InnoDB;

-- Casillas marcables de la historia clinica: habitos y anomalias.
-- Se agrupan por seccion para pintarlas en bloques.
CREATE TABLE catalogo_clinico (
  id_catalogo_clinico SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  -- HABITO | ANOM_NUMERO | ANOM_TAMANO | ANOM_ESTRUCTURA | ANOM_FORMA
  seccion             VARCHAR(20) NOT NULL,
  clave               VARCHAR(40) NOT NULL,
  nombre              VARCHAR(80) NOT NULL,
  -- 1 = al marcarla se pide detalle
  pide_detalle        TINYINT UNSIGNED NOT NULL DEFAULT 0,
  orden               TINYINT UNSIGNED NOT NULL DEFAULT 0,
  estatus             TINYINT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (id_catalogo_clinico),
  UNIQUE KEY uq_catalogo_clinico (clave),
  KEY ix_catalogo_seccion (seccion, orden)
) ENGINE=InnoDB;

CREATE TABLE historia_marca (
  id_historia_marca   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_historia_clinica INT UNSIGNED NOT NULL,
  id_catalogo_clinico SMALLINT UNSIGNED NOT NULL,
  detalle             VARCHAR(255) NULL,
  PRIMARY KEY (id_historia_marca),
  UNIQUE KEY uq_historia_marca (id_historia_clinica, id_catalogo_clinico),
  KEY ix_hm_catalogo (id_catalogo_clinico),
  CONSTRAINT fk_hm_historia FOREIGN KEY (id_historia_clinica)
    REFERENCES historia_clinica (id_historia_clinica) ON DELETE CASCADE,
  CONSTRAINT fk_hm_catalogo FOREIGN KEY (id_catalogo_clinico)
    REFERENCES catalogo_clinico (id_catalogo_clinico)
) ENGINE=InnoDB;

-- =====================================================================
-- SECCION 4 : SEGURIDAD Y CONFIGURACION
-- =====================================================================

CREATE TABLE rol (
  id_rol      TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre_rol  VARCHAR(40) NOT NULL,
  descripcion TEXT NULL,
  estatus     TINYINT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (id_rol),
  UNIQUE KEY uq_rol (nombre_rol)
) ENGINE=InnoDB;

CREATE TABLE modulo (
  id_modulo    TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  clave        VARCHAR(30) NOT NULL,   -- paciente, cajas, consultas...
  nombre       VARCHAR(50) NOT NULL,
  icono        VARCHAR(40) NULL,
  orden        TINYINT UNSIGNED NOT NULL DEFAULT 0,
  estatus      TINYINT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (id_modulo),
  UNIQUE KEY uq_modulo_clave (clave)
) ENGINE=InnoDB;

CREATE TABLE permiso (
  id_permiso  SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_modulo   TINYINT UNSIGNED NOT NULL,
  clave       VARCHAR(60) NOT NULL,   -- paciente.crear, cajas.corte...
  nombre      VARCHAR(80) NOT NULL,
  PRIMARY KEY (id_permiso),
  UNIQUE KEY uq_permiso_clave (clave),
  KEY ix_permiso_modulo (id_modulo),
  CONSTRAINT fk_permiso_modulo FOREIGN KEY (id_modulo)
    REFERENCES modulo (id_modulo) ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE rol_permiso (
  id_rol     TINYINT UNSIGNED NOT NULL,
  id_permiso SMALLINT UNSIGNED NOT NULL,
  PRIMARY KEY (id_rol, id_permiso),
  KEY ix_rp_permiso (id_permiso),
  CONSTRAINT fk_rp_rol FOREIGN KEY (id_rol)
    REFERENCES rol (id_rol) ON DELETE CASCADE,
  CONSTRAINT fk_rp_permiso FOREIGN KEY (id_permiso)
    REFERENCES permiso (id_permiso) ON DELETE CASCADE
) ENGINE=InnoDB;

-- MEJORA: usuario se liga a persona (el dentista es una persona del
-- sistema) y se agregan campos de control de acceso.
CREATE TABLE usuario (
  id_usuario       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_persona       INT UNSIGNED NULL,
  id_rol           TINYINT UNSIGNED NOT NULL,
  nombre_usuario   VARCHAR(40)  NOT NULL,
  nombre_completo  VARCHAR(120) NOT NULL,
  contrasena       VARCHAR(255) NOT NULL,   -- password_hash() bcrypt
  correo           VARCHAR(120) NULL,
  cedula_prof      VARCHAR(30)  NULL,       -- para el personal clinico
  es_dentista      TINYINT UNSIGNED NOT NULL DEFAULT 0,
  intentos_fallidos TINYINT UNSIGNED NOT NULL DEFAULT 0,
  bloqueado_hasta  DATETIME NULL,
  ultimo_acceso    DATETIME NULL,
  requiere_cambio_pass TINYINT UNSIGNED NOT NULL DEFAULT 0,
  estatus          TINYINT UNSIGNED NOT NULL DEFAULT 1,
  fecha_creacion   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_usuario),
  UNIQUE KEY uq_usuario_nombre (nombre_usuario),
  KEY ix_usuario_rol (id_rol),
  KEY ix_usuario_persona (id_persona),
  CONSTRAINT fk_usuario_rol FOREIGN KEY (id_rol)
    REFERENCES rol (id_rol) ON UPDATE CASCADE,
  CONSTRAINT fk_usuario_persona FOREIGN KEY (id_persona)
    REFERENCES persona (id_persona)
) ENGINE=InnoDB;

-- MEJORA: datos del consultorio parametrizables (footer, tickets, CFDI)
CREATE TABLE consultorio (
  id_consultorio  TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre_comercial VARCHAR(120) NOT NULL,
  razon_social    VARCHAR(150) NULL,
  rfc             VARCHAR(13)  NULL,
  id_domicilio    INT UNSIGNED NULL,
  telefono        VARCHAR(30)  NULL,
  correo          VARCHAR(120) NULL,
  logo            VARCHAR(255) NULL,
  PRIMARY KEY (id_consultorio),
  CONSTRAINT fk_consultorio_domicilio FOREIGN KEY (id_domicilio)
    REFERENCES domicilio (id_domicilio)
) ENGINE=InnoDB;

CREATE TABLE parametro (
  clave       VARCHAR(50)  NOT NULL,
  valor       VARCHAR(255) NULL,
  descripcion VARCHAR(255) NULL,
  PRIMARY KEY (clave)
) ENGINE=InnoDB;

-- MEJORA: bitacora de auditoria. Obligatoria en un sistema con datos
-- clinicos y movimientos de dinero.
CREATE TABLE bitacora (
  id_bitacora   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_usuario    INT UNSIGNED NULL,
  fecha_hora    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  modulo        VARCHAR(30)  NULL,
  accion        VARCHAR(30)  NOT NULL,   -- LOGIN, ALTA, CAMBIO, BAJA...
  tabla_afectada VARCHAR(50) NULL,
  id_registro   VARCHAR(40)  NULL,
  detalle       JSON         NULL,
  ip            VARCHAR(45)  NULL,
  PRIMARY KEY (id_bitacora),
  KEY ix_bitacora_usuario (id_usuario),
  KEY ix_bitacora_fecha (fecha_hora),
  KEY ix_bitacora_tabla (tabla_afectada, id_registro)
) ENGINE=InnoDB;

-- =====================================================================
-- SECCION 5 : AGENDA Y CONSULTAS
-- =====================================================================

CREATE TABLE estado_cita (
  id_estado_cita TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre_estado  VARCHAR(30) NOT NULL,
  color          VARCHAR(9)  NULL,
  PRIMARY KEY (id_estado_cita)
) ENGINE=InnoDB;

CREATE TABLE consultorio_sala (
  id_sala     TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre_sala VARCHAR(40) NOT NULL,
  estatus     TINYINT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (id_sala)
) ENGINE=InnoDB;

CREATE TABLE cita (
  id_cita              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_registro_paciente INT UNSIGNED NOT NULL,
  id_usuario_dentista  INT UNSIGNED NOT NULL,
  id_sala              TINYINT UNSIGNED NULL,
  id_estado_cita       TINYINT UNSIGNED NOT NULL DEFAULT 1,
  -- Procedimiento previsto. De el sale la duracion al agendar, en vez
  -- de apartar media hora para todo: una resina y una revision no
  -- ocupan el sillon el mismo tiempo.
  id_procedimiento     INT UNSIGNED NULL,
  fecha_inicio         DATETIME NOT NULL,
  fecha_fin            DATETIME NOT NULL,
  -- Cita apartada para el dia, sin hora todavia.
  --
  -- El de ortodoncia se cita a seis semanas y en ese momento nadie sabe
  -- como va a quedar esa agenda. Elegir una hora solo para poder
  -- guardar aparta un sillon que no hace falta y le promete al paciente
  -- un horario que va a cambiar.
  --
  -- Con sin_hora = 1 las dos fechas quedan a las 00:00 del dia: la cita
  -- no ocupa ningun rango, asi que no choca con nada sin necesidad de
  -- excepciones en la comprobacion de empalmes. El dato que manda es
  -- esta columna y no las horas: deducirlo de "las dos fechas son
  -- iguales" se romperia el dia que algo mas guarde una cita de cero
  -- minutos.
  sin_hora             TINYINT UNSIGNED NOT NULL DEFAULT 0,
  motivo               VARCHAR(255) NULL,
  notas                TEXT NULL,
  recordatorio_enviado TINYINT UNSIGNED NOT NULL DEFAULT 0,
  id_usuario_registro  INT UNSIGNED NULL,
  fecha_registro       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_cita),
  KEY ix_cita_expediente (id_registro_paciente),
  KEY ix_cita_dentista_fecha (id_usuario_dentista, fecha_inicio),
  KEY ix_cita_fecha (fecha_inicio),
  KEY ix_cita_procedimiento (id_procedimiento),
  CONSTRAINT fk_cita_expediente FOREIGN KEY (id_registro_paciente)
    REFERENCES registro_paciente (id_registro_paciente),
  CONSTRAINT fk_cita_dentista FOREIGN KEY (id_usuario_dentista)
    REFERENCES usuario (id_usuario),
  CONSTRAINT fk_cita_sala FOREIGN KEY (id_sala)
    REFERENCES consultorio_sala (id_sala),
  CONSTRAINT fk_cita_estado FOREIGN KEY (id_estado_cita)
    REFERENCES estado_cita (id_estado_cita)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Bloqueos de agenda
--
-- Vacaciones, un congreso, la comida. Sin esto el sistema ofrece
-- horarios en los que no hay nadie y alguien acaba agendando ahi.
-- id_usuario en NULL = cierra el consultorio completo.
-- ---------------------------------------------------------------------
CREATE TABLE agenda_bloqueo (
  id_bloqueo     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_usuario     INT UNSIGNED NULL,
  fecha_inicio   DATETIME NOT NULL,
  fecha_fin      DATETIME NOT NULL,
  motivo         VARCHAR(150) NOT NULL,
  id_usuario_registro INT UNSIGNED NOT NULL,
  fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  estatus        TINYINT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (id_bloqueo),
  KEY ix_bloqueo_fecha (fecha_inicio, fecha_fin),
  KEY ix_bloqueo_usuario (id_usuario),
  CONSTRAINT fk_bloqueo_usuario FOREIGN KEY (id_usuario)
    REFERENCES usuario (id_usuario),
  CONSTRAINT fk_bloqueo_registro FOREIGN KEY (id_usuario_registro)
    REFERENCES usuario (id_usuario)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Lista de espera
--
-- Pacientes que quieren "lo que salga antes". Cuando se cancela una
-- cita, el sistema puede decir quien estaba esperando ese hueco.
-- ---------------------------------------------------------------------
CREATE TABLE lista_espera (
  id_espera            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_registro_paciente INT UNSIGNED NOT NULL,
  id_usuario_dentista  INT UNSIGNED NULL,   -- NULL = cualquiera
  motivo               VARCHAR(255) NULL,
  -- Franja que le acomoda: 1 maniana | 2 tarde | 3 cualquiera
  preferencia_horario  TINYINT UNSIGNED NOT NULL DEFAULT 3,
  duracion_min         SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  notas                VARCHAR(255) NULL,
  -- 1 esperando | 2 ya se agendo | 0 se dio de baja
  estado               TINYINT UNSIGNED NOT NULL DEFAULT 1,
  id_cita              INT UNSIGNED NULL,   -- con la que se resolvio
  id_usuario_registro  INT UNSIGNED NOT NULL,
  fecha_registro       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_espera),
  KEY ix_espera_estado (estado, fecha_registro),
  CONSTRAINT fk_espera_expediente FOREIGN KEY (id_registro_paciente)
    REFERENCES registro_paciente (id_registro_paciente),
  CONSTRAINT fk_espera_dentista FOREIGN KEY (id_usuario_dentista)
    REFERENCES usuario (id_usuario),
  CONSTRAINT fk_espera_registro FOREIGN KEY (id_usuario_registro)
    REFERENCES usuario (id_usuario)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Recordatorios de cita
--
-- Las reglas dicen cuantos avisos se le hacen al paciente y con cuanta
-- anticipacion. El registro dice cuales ya se hicieron, para que el
-- tablero no vuelva a pedir el mismo.
-- ---------------------------------------------------------------------
CREATE TABLE recordatorio_regla (
  id_regla    TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre      VARCHAR(60) NOT NULL,
  horas_antes SMALLINT UNSIGNED NOT NULL,
  orden       TINYINT UNSIGNED NOT NULL DEFAULT 1,
  estatus     TINYINT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (id_regla),
  UNIQUE KEY uq_regla_horas (horas_antes)
) ENGINE=InnoDB;

CREATE TABLE cita_recordatorio (
  id_recordatorio INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_cita         INT UNSIGNED NOT NULL,
  id_regla        TINYINT UNSIGNED NOT NULL,
  -- 1 enviado | 2 no se pudo contactar | 3 omitido a proposito
  resultado       TINYINT UNSIGNED NOT NULL DEFAULT 1,
  nota            VARCHAR(150) NULL,
  id_usuario      INT UNSIGNED NOT NULL,
  fecha_registro  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_recordatorio),
  UNIQUE KEY uq_cita_regla (id_cita, id_regla),
  CONSTRAINT fk_rec_cita FOREIGN KEY (id_cita)
    REFERENCES cita (id_cita) ON DELETE CASCADE,
  CONSTRAINT fk_rec_regla FOREIGN KEY (id_regla)
    REFERENCES recordatorio_regla (id_regla),
  CONSTRAINT fk_rec_usuario FOREIGN KEY (id_usuario)
    REFERENCES usuario (id_usuario)
) ENGINE=InnoDB;

CREATE TABLE consulta (
  id_consulta          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_registro_paciente INT UNSIGNED NOT NULL,
  -- Folio visible (CN-0001). Es con lo que se le habla a una consulta
  -- en las listas, en las recetas y en el expediente; el id interno no
  -- se le ensenia a nadie.
  folio                VARCHAR(20) NOT NULL,
  id_cita              INT UNSIGNED NULL,
  -- Plan de tratamiento del que forma parte la consulta. NULL = consulta
  -- suelta (revision, urgencia, valoracion). El expediente las separa
  -- justo por esto. La llave foranea se agrega al final del archivo.
  id_plan_tratamiento  INT UNSIGNED NULL,
  id_usuario_dentista  INT UNSIGNED NOT NULL,
  fecha_hora           DATETIME NOT NULL,
  motivo               VARCHAR(255) NULL,
  padecimiento_actual  TEXT NULL,
  -- Octubre 2026: la exploracion dejo de ser una caja de texto y pasó
  -- a la tabla hallazgo, uno por uno. El diagnostico salio de la
  -- consulta: vive en el plan de tratamiento, que es donde la doctora
  -- lo razona. Las dos columnas se conservan con lo ya capturado.
  exploracion          TEXT NULL,
  diagnostico          TEXT NULL,
  plan_indicaciones    TEXT NULL,
  -- signos vitales: salieron de la pantalla en octubre 2026 (en
  -- odontopediatria no se tomaban). Se conservan con lo ya capturado.
  presion_arterial     VARCHAR(15) NULL,
  frecuencia_cardiaca  SMALLINT UNSIGNED NULL,
  temperatura          DECIMAL(4,1) NULL,
  -- una vez cerrada la consulta ya no se edita (integridad clinica)
  cerrada              TINYINT UNSIGNED NOT NULL DEFAULT 0,
  fecha_cierre         DATETIME NULL,
  estatus              TINYINT UNSIGNED NOT NULL DEFAULT 1,
  fecha_registro       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_consulta),
  UNIQUE KEY uq_consulta_folio (folio),
  KEY ix_consulta_expediente (id_registro_paciente, fecha_hora),
  KEY ix_consulta_dentista (id_usuario_dentista),
  KEY ix_consulta_cita (id_cita),
  KEY ix_consulta_plan (id_plan_tratamiento),
  CONSTRAINT fk_consulta_expediente FOREIGN KEY (id_registro_paciente)
    REFERENCES registro_paciente (id_registro_paciente),
  CONSTRAINT fk_consulta_cita FOREIGN KEY (id_cita)
    REFERENCES cita (id_cita),
  CONSTRAINT fk_consulta_dentista FOREIGN KEY (id_usuario_dentista)
    REFERENCES usuario (id_usuario)
) ENGINE=InnoDB;

-- Notas de evolucion.
--
-- No son un documento que se escanee: se escriben en el sistema y ahi
-- se quedan. Por eso viven en su propia tabla y no en 'documento', que
-- es la lista de papeles que falta digitalizar.
CREATE TABLE hallazgo (
  id_hallazgo       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_consulta       INT UNSIGNED NOT NULL,
  -- CLINICO = lo que se ve en boca.  RADIOGRAFICO = lo que se ve en la
  -- placa. Son dos miradas distintas sobre el mismo paciente y la
  -- doctora las separa al dictarlas, asi que se guardan separadas.
  tipo              VARCHAR(14) NOT NULL,
  descripcion       TEXT NOT NULL,
  -- SIN USO desde octubre 2026. Aqui vivia la unica imagen que podia
  -- tener un hallazgo. Al permitir varias, el enlace se invirtio: ahora
  -- es documento.id_hallazgo el que apunta para aca. La columna se
  -- conserva con lo que se hubiera capturado antes del cambio.
  id_documento      INT UNSIGNED NULL,
  orden             SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  id_usuario        INT UNSIGNED NOT NULL,
  fecha_registro    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  estatus           TINYINT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (id_hallazgo),
  KEY ix_hallazgo_consulta (id_consulta, orden),
  KEY ix_hallazgo_documento (id_documento),
  CONSTRAINT fk_hallazgo_consulta FOREIGN KEY (id_consulta)
    REFERENCES consulta (id_consulta) ON DELETE CASCADE,
  -- SET NULL y no CASCADE: si alguien borra la imagen, el hallazgo
  -- escrito tiene que sobrevivir. Lo que la doctora observo no deja de
  -- ser cierto porque se haya perdido la foto.
  CONSTRAINT fk_hallazgo_documento FOREIGN KEY (id_documento)
    REFERENCES documento (id_documento) ON DELETE SET NULL,
  CONSTRAINT fk_hallazgo_usuario FOREIGN KEY (id_usuario)
    REFERENCES usuario (id_usuario)
) ENGINE=InnoDB;

CREATE TABLE plan_traumatismo (
  id_plan_traumatismo INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_plan_tratamiento INT UNSIGNED NOT NULL,
  -- El golpe: "CAIDA A LOS 4 ANOS, FRACTURA DE 51".
  descripcion         VARCHAR(255) NOT NULL,
  -- Lo que dejo: "NECROSIS PULPAR", "DISCROMIA". Puede ir vacio: un
  -- traumatismo reciente todavia no tiene secuela, y eso no lo
  -- convierte en un dato incompleto sino en un dato de hoy.
  secuela             VARCHAR(255) NULL,
  orden               SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  estatus             TINYINT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (id_plan_traumatismo),
  KEY ix_traumatismo_plan (id_plan_tratamiento, orden),
  CONSTRAINT fk_traumatismo_plan FOREIGN KEY (id_plan_tratamiento)
    REFERENCES plan_tratamiento (id_plan_tratamiento) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE nota_evolucion (
  id_nota_evolucion INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_consulta       INT UNSIGNED NOT NULL,
  texto             TEXT NOT NULL,
  -- DOS fechas, y la separacion es el punto.
  --
  -- fecha_nota es A QUE DIA se refiere la nota, y se puede corregir:
  -- una nota escrita el martes puede hablar de lo que paso el viernes
  -- anterior. Es el dato clinico.
  --
  -- fecha_registro es CUANDO se teclo, y no se toca nunca. Es el rastro
  -- de cuando entro al sistema. Si la fecha editable fuera esta,
  -- corregir una nota borraria la unica prueba de cuando se escribio de
  -- verdad, y en un expediente la diferencia entre "lo anoto ese dia" y
  -- "lo anoto tres meses despues" es la diferencia entre un registro y
  -- una reconstruccion.
  fecha_nota        DATETIME NULL,
  id_usuario        INT UNSIGNED NOT NULL,
  fecha_registro    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  -- NULL = nunca se corrigio. La pantalla lo dice cuando no lo es: una
  -- nota corregida en silencio es peor que una nota con un dedazo.
  fecha_modificacion  DATETIME NULL,
  id_usuario_modifica INT UNSIGNED NULL,
  estatus           TINYINT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (id_nota_evolucion),
  KEY ix_nota_consulta (id_consulta, fecha_registro),
  KEY ix_nota_consulta_fecha (id_consulta, fecha_nota),
  CONSTRAINT fk_nota_consulta FOREIGN KEY (id_consulta)
    REFERENCES consulta (id_consulta) ON DELETE CASCADE,
  CONSTRAINT fk_nota_usuario FOREIGN KEY (id_usuario)
    REFERENCES usuario (id_usuario),
  CONSTRAINT fk_nota_modifica FOREIGN KEY (id_usuario_modifica)
    REFERENCES usuario (id_usuario)
) ENGINE=InnoDB;

-- Recetas.
--
-- El consultorio ya tiene el recetario impreso, con su membrete y su
-- pie. El sistema guarda lo que se receto y al imprimir manda SOLO los
-- datos, calzados en los espacios en blanco de esa hoja. Por eso la
-- receta se guarda entera como texto: es lo que se escribe a mano en
-- el papel, renglon por renglon.
CREATE TABLE receta (
  id_receta           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_consulta         INT UNSIGNED NOT NULL,
  folio               VARCHAR(20) NOT NULL,
  fecha               DATE NOT NULL,
  id_usuario_dentista INT UNSIGNED NOT NULL,
  indicaciones        TEXT NOT NULL,
  -- Documento del expediente que guarda la receta firmada escaneada.
  id_documento        INT UNSIGNED NULL,
  estatus             TINYINT UNSIGNED NOT NULL DEFAULT 1,
  fecha_registro      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_receta),
  UNIQUE KEY uq_receta_folio (folio),
  KEY ix_receta_consulta (id_consulta),
  CONSTRAINT fk_receta_consulta FOREIGN KEY (id_consulta)
    REFERENCES consulta (id_consulta) ON DELETE CASCADE,
  CONSTRAINT fk_receta_dentista FOREIGN KEY (id_usuario_dentista)
    REFERENCES usuario (id_usuario),
  CONSTRAINT fk_receta_documento FOREIGN KEY (id_documento)
    REFERENCES documento (id_documento) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE consulta_archivo (
  id_consulta_archivo INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_consulta         INT UNSIGNED NOT NULL,
  tipo_archivo        VARCHAR(20) NOT NULL,  -- radiografia, foto, pdf
  ruta                VARCHAR(255) NOT NULL,
  descripcion         VARCHAR(150) NULL,
  fecha_registro      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_consulta_archivo),
  KEY ix_ca_consulta (id_consulta),
  CONSTRAINT fk_ca_consulta FOREIGN KEY (id_consulta)
    REFERENCES consulta (id_consulta)
) ENGINE=InnoDB;

-- =====================================================================
-- SECCION 6 : ODONTOGRAMA
-- =====================================================================

CREATE TABLE diente (
  id_diente     TINYINT UNSIGNED NOT NULL,   -- notacion FDI (11..48, 51..85)
  nombre_diente VARCHAR(50) NOT NULL,
  cuadrante     TINYINT UNSIGNED NOT NULL,
  es_temporal   TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id_diente)
) ENGINE=InnoDB;

CREATE TABLE condicion_dental (
  id_condicion    TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre_condicion VARCHAR(60) NOT NULL,
  color           VARCHAR(9) NULL,
  -- 0 = hallazgo (rojo), 1 = tratamiento hecho (azul)
  es_tratamiento  TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id_condicion)
) ENGINE=InnoDB;

CREATE TABLE odontograma (
  id_odontograma       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_registro_paciente INT UNSIGNED NOT NULL,
  id_consulta          INT UNSIGNED NULL,
  fecha                DATE NOT NULL,
  tipo                 VARCHAR(15) NOT NULL DEFAULT 'inicial', -- inicial|evolucion
  observaciones        TEXT NULL,
  PRIMARY KEY (id_odontograma),
  KEY ix_odo_expediente (id_registro_paciente, fecha),
  -- Un solo odontograma "actual" por paciente.
  --
  -- Sobre una EXPRESION y no sobre las dos columnas: asi acota el
  -- 'actual' a uno y deja libres los demas tipos, por si algun dia se
  -- guarda la historia por fechas. En MySQL los NULL no chocan entre
  -- si, asi que los otros tipos —que dan NULL aqui— no se limitan.
  --
  -- Sin esta llave, dos pestanias abiertas a la vez pueden crear dos
  -- odontogramas del mismo paciente —las dos consultan, las dos no
  -- encuentran, las dos insertan— y la mitad de las marcas acabaria en
  -- uno y la mitad en el otro: dientes que se despintan solos.
  UNIQUE KEY uq_odo_actual
    ((IF(tipo = 'actual', id_registro_paciente, NULL))),
  CONSTRAINT fk_odo_expediente FOREIGN KEY (id_registro_paciente)
    REFERENCES registro_paciente (id_registro_paciente),
  CONSTRAINT fk_odo_consulta FOREIGN KEY (id_consulta)
    REFERENCES consulta (id_consulta)
) ENGINE=InnoDB;

CREATE TABLE odontograma_detalle (
  id_odontograma_detalle INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_odontograma         INT UNSIGNED NOT NULL,
  id_diente              TINYINT UNSIGNED NOT NULL,
  -- O,V,L,M,D (oclusal, vestibular, lingual, mesial, distal) o NULL = todo
  cara                   CHAR(1) NULL,
  id_condicion           TINYINT UNSIGNED NOT NULL,
  nota                   VARCHAR(150) NULL,
  PRIMARY KEY (id_odontograma_detalle),
  KEY ix_od_odontograma (id_odontograma),
  KEY ix_od_diente (id_diente),
  CONSTRAINT fk_od_odontograma FOREIGN KEY (id_odontograma)
    REFERENCES odontograma (id_odontograma) ON DELETE CASCADE,
  CONSTRAINT fk_od_diente FOREIGN KEY (id_diente)
    REFERENCES diente (id_diente),
  CONSTRAINT fk_od_condicion FOREIGN KEY (id_condicion)
    REFERENCES condicion_dental (id_condicion)
) ENGINE=InnoDB;

-- =====================================================================
-- SECCION 7 : TRATAMIENTOS Y PLANES
-- =====================================================================

CREATE TABLE categoria_tratamiento (
  id_categoria     TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre_categoria VARCHAR(60) NOT NULL,
  PRIMARY KEY (id_categoria)
) ENGINE=InnoDB;

CREATE TABLE tratamiento (
  id_tratamiento  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_categoria    TINYINT UNSIGNED NOT NULL,
  clave           VARCHAR(20) NULL,
  nombre_tratamiento VARCHAR(120) NOT NULL,
  descripcion     TEXT NULL,
  precio_lista    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  duracion_min    SMALLINT UNSIGNED NULL,
  aplica_por_diente TINYINT UNSIGNED NOT NULL DEFAULT 1,
  clave_prod_serv_sat VARCHAR(10) NULL,   -- para CFDI
  estatus         TINYINT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (id_tratamiento),
  KEY ix_trat_categoria (id_categoria),
  KEY ix_trat_nombre (nombre_tratamiento),
  CONSTRAINT fk_trat_categoria FOREIGN KEY (id_categoria)
    REFERENCES categoria_tratamiento (id_categoria) ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Procedimientos que se anotan en el plan de tratamiento.
--
-- Es distinto de 'tratamiento': ahi vive el tarifario con precios, y
-- aqui la lista corta de lo que la doctora escribe en la hoja. Se dejan
-- ligados para que al elegir un procedimiento el precio salga solo, pero
-- se pueden usar por separado: hay procedimientos sin costo (revision de
-- control) y conceptos de cobro que no son procedimiento (radiografia
-- externa).
CREATE TABLE procedimiento (
  id_procedimiento   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_categoria       TINYINT UNSIGNED NULL,
  clave              VARCHAR(20) NULL,
  nombre_procedimiento VARCHAR(120) NOT NULL,
  descripcion        TEXT NULL,
  -- tratamiento del tarifario del que toma el precio, si aplica
  id_tratamiento     INT UNSIGNED NULL,
  costo_sugerido     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  duracion_min       SMALLINT UNSIGNED NULL,
  -- 1 = se registra sobre un diente concreto
  aplica_por_diente  TINYINT UNSIGNED NOT NULL DEFAULT 1,
  -- 1 = requiere anestesia (se avisa si hay alergia registrada)
  requiere_anestesia TINYINT UNSIGNED NOT NULL DEFAULT 0,
  orden              SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  estatus            TINYINT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (id_procedimiento),
  UNIQUE KEY uq_procedimiento (nombre_procedimiento),
  KEY ix_proc_categoria (id_categoria),
  KEY ix_proc_tratamiento (id_tratamiento),
  CONSTRAINT fk_proc_categoria FOREIGN KEY (id_categoria)
    REFERENCES categoria_tratamiento (id_categoria) ON UPDATE CASCADE,
  CONSTRAINT fk_proc_tratamiento FOREIGN KEY (id_tratamiento)
    REFERENCES tratamiento (id_tratamiento) ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE estado_plan (
  id_estado_plan TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre_estado  VARCHAR(30) NOT NULL,
  PRIMARY KEY (id_estado_plan)
) ENGINE=InnoDB;

CREATE TABLE plan_tratamiento (
  id_plan_tratamiento  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_registro_paciente INT UNSIGNED NOT NULL,
  id_usuario_dentista  INT UNSIGNED NOT NULL,
  folio                VARCHAR(20) NOT NULL,
  -- Como le dicen al plan en el consultorio ("ORTOPEDIA ETAPA 1").
  -- El folio sirve para buscarlo; la descripcion, para reconocerlo.
  descripcion          VARCHAR(150) NULL,
  fecha                DATE NOT NULL,
  id_estado_plan       TINYINT UNSIGNED NOT NULL DEFAULT 1,
  -- Terminado y Cancelado son finales: de ellos no se sale. Ver la
  -- clase Planes, que es donde vive esa regla.
  motivo_cancelacion   VARCHAR(255) NULL,
  fecha_cancelacion    DATETIME NULL,
  fecha_terminado      DATETIME NULL,
  descuento            DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  subtotal             DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  total                DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  saldo                DECIMAL(12,2) NOT NULL DEFAULT 0.00,

  -- Datos que trae la hoja impresa del plan de tratamiento
  diagnostico          TEXT NULL,
  oclusion             VARCHAR(150) NULL,
  traumatismos         VARCHAR(255) NULL,
  secuelas_traumatismos VARCHAR(255) NULL,

  observaciones        TEXT NULL,
  fecha_registro       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_plan_tratamiento),
  UNIQUE KEY uq_plan_folio (folio),
  KEY ix_plan_expediente (id_registro_paciente),
  CONSTRAINT fk_plan_expediente FOREIGN KEY (id_registro_paciente)
    REFERENCES registro_paciente (id_registro_paciente),
  CONSTRAINT fk_plan_dentista FOREIGN KEY (id_usuario_dentista)
    REFERENCES usuario (id_usuario),
  CONSTRAINT fk_plan_estado FOREIGN KEY (id_estado_plan)
    REFERENCES estado_plan (id_estado_plan)
) ENGINE=InnoDB;

CREATE TABLE plan_tratamiento_detalle (
  id_plan_detalle      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_plan_tratamiento  INT UNSIGNED NOT NULL,
  id_tratamiento       INT UNSIGNED NULL,
  -- El procedimiento es lo que se escribe en la hoja; el tratamiento es
  -- de donde sale el precio. Casi siempre van juntos, pero no siempre.
  id_procedimiento     INT UNSIGNED NULL,
  -- Organos dentarios del renglon.
  --
  -- 'dientes' es la lista real y manda: "16" para uno solo, "16,26,36,46"
  -- para varios, NULL cuando el procedimiento no aplica a un diente.
  -- id_diente se conserva con el primero de la lista porque el
  -- odontograma y los reportes lo consultan con una llave foranea, que
  -- una lista de texto no puede dar.
  dientes              VARCHAR(160) NULL,
  id_diente            TINYINT UNSIGNED NULL,
  cara                 CHAR(1) NULL,
  cantidad             SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  -- precio congelado al momento de cotizar (no se lee de tratamiento)
  precio_unitario      DECIMAL(10,2) NOT NULL,
  descuento            DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  importe              DECIMAL(12,2) NOT NULL,
  orden                SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  -- 0 pendiente | 1 en proceso | 2 terminado | 3 cancelado
  estado_avance        TINYINT UNSIGNED NOT NULL DEFAULT 0,
  id_consulta_ejecucion INT UNSIGNED NULL,
  fecha_ejecucion      DATETIME NULL,
  -- La hoja de papel tiene columnas Fecha y Prox. cita por renglon
  fecha_programada     DATE NULL,
  proxima_cita         DATE NULL,
  notas                VARCHAR(255) NULL,
  PRIMARY KEY (id_plan_detalle),
  KEY ix_pd_plan (id_plan_tratamiento),
  KEY ix_pd_tratamiento (id_tratamiento),
  KEY ix_pd_procedimiento (id_procedimiento),
  CONSTRAINT fk_pd_plan FOREIGN KEY (id_plan_tratamiento)
    REFERENCES plan_tratamiento (id_plan_tratamiento) ON DELETE CASCADE,
  CONSTRAINT fk_pd_tratamiento FOREIGN KEY (id_tratamiento)
    REFERENCES tratamiento (id_tratamiento),
  CONSTRAINT fk_pd_procedimiento FOREIGN KEY (id_procedimiento)
    REFERENCES procedimiento (id_procedimiento),
  CONSTRAINT fk_pd_diente FOREIGN KEY (id_diente)
    REFERENCES diente (id_diente),
  CONSTRAINT fk_pd_consulta FOREIGN KEY (id_consulta_ejecucion)
    REFERENCES consulta (id_consulta)
) ENGINE=InnoDB;

-- =====================================================================
-- SECCION 8 : CAJA Y PAGOS
-- =====================================================================

CREATE TABLE caja (
  id_caja     TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre_caja VARCHAR(40) NOT NULL,
  estatus     TINYINT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (id_caja)
) ENGINE=InnoDB;

-- MEJORA: apertura/cierre de caja. Sin esto no hay corte confiable.
CREATE TABLE corte_caja (
  id_corte_caja     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_caja           TINYINT UNSIGNED NOT NULL,
  id_usuario_apertura INT UNSIGNED NOT NULL,
  id_usuario_cierre INT UNSIGNED NULL,
  fecha_apertura    DATETIME NOT NULL,
  fecha_cierre      DATETIME NULL,
  monto_inicial     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  total_ingresos    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  total_egresos     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  monto_esperado    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  monto_contado     DECIMAL(12,2) NULL,
  diferencia        DECIMAL(12,2) NULL,
  observaciones     TEXT NULL,
  -- 1 abierto | 0 cerrado
  abierto           TINYINT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (id_corte_caja),
  KEY ix_corte_caja (id_caja, fecha_apertura),
  CONSTRAINT fk_corte_caja FOREIGN KEY (id_caja)
    REFERENCES caja (id_caja),
  CONSTRAINT fk_corte_usuario_ap FOREIGN KEY (id_usuario_apertura)
    REFERENCES usuario (id_usuario),
  CONSTRAINT fk_corte_usuario_ci FOREIGN KEY (id_usuario_cierre)
    REFERENCES usuario (id_usuario)
) ENGINE=InnoDB;

CREATE TABLE forma_pago (
  id_forma_pago    TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre_forma     VARCHAR(40) NOT NULL,
  clave_sat        CHAR(2) NULL,     -- 01 Efectivo, 04 Tarjeta credito...
  afecta_efectivo  TINYINT UNSIGNED NOT NULL DEFAULT 1,
  estatus          TINYINT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (id_forma_pago)
) ENGINE=InnoDB;

CREATE TABLE concepto_movimiento (
  id_concepto   SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre_concepto VARCHAR(80) NOT NULL,
  -- I = ingreso, E = egreso
  naturaleza    CHAR(1) NOT NULL,
  estatus       TINYINT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (id_concepto)
) ENGINE=InnoDB;

CREATE TABLE pago (
  id_pago              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  folio                VARCHAR(20) NOT NULL,
  id_registro_paciente INT UNSIGNED NOT NULL,
  id_plan_tratamiento  INT UNSIGNED NULL,
  id_corte_caja        INT UNSIGNED NOT NULL,
  id_usuario           INT UNSIGNED NOT NULL,
  fecha_hora           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  monto                DECIMAL(12,2) NOT NULL,
  observaciones        VARCHAR(255) NULL,
  -- Huella del envio que genero este cobro. La pantalla manda una
  -- distinta cada vez que se abre el formulario; si llegan dos
  -- peticiones con la misma, la segunda NO crea otro cobro: el indice
  -- unico la rechaza y el sistema devuelve el pago que ya existia.
  --
  -- Es la unica proteccion real contra el doble clic. Deshabilitar el
  -- boton en la pantalla ayuda, pero no cubre el navegador que
  -- reintenta solo, la conexion que se cae a medio envio ni al usuario
  -- que da Enter dos veces antes de que corra el JavaScript. Con
  -- dinero, el que decide tiene que ser el servidor.
  huella_envio         CHAR(36) NULL,
  -- 1 vigente | 0 cancelado
  estatus              TINYINT UNSIGNED NOT NULL DEFAULT 1,
  id_usuario_cancela   INT UNSIGNED NULL,
  fecha_cancelacion    DATETIME NULL,
  motivo_cancelacion   VARCHAR(255) NULL,
  PRIMARY KEY (id_pago),
  UNIQUE KEY uq_pago_folio (folio),
  UNIQUE KEY uq_pago_huella (huella_envio),
  KEY ix_pago_expediente (id_registro_paciente),
  KEY ix_pago_plan (id_plan_tratamiento),
  KEY ix_pago_corte (id_corte_caja),
  CONSTRAINT fk_pago_expediente FOREIGN KEY (id_registro_paciente)
    REFERENCES registro_paciente (id_registro_paciente),
  CONSTRAINT fk_pago_plan FOREIGN KEY (id_plan_tratamiento)
    REFERENCES plan_tratamiento (id_plan_tratamiento),
  CONSTRAINT fk_pago_corte FOREIGN KEY (id_corte_caja)
    REFERENCES corte_caja (id_corte_caja),
  CONSTRAINT fk_pago_usuario FOREIGN KEY (id_usuario)
    REFERENCES usuario (id_usuario)
) ENGINE=InnoDB;

-- un pago puede repartirse en varias formas (mitad efectivo, mitad tarjeta)
CREATE TABLE pago_forma (
  id_pago_forma  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_pago        INT UNSIGNED NOT NULL,
  id_forma_pago  TINYINT UNSIGNED NOT NULL,
  monto          DECIMAL(12,2) NOT NULL,
  referencia     VARCHAR(60) NULL,
  PRIMARY KEY (id_pago_forma),
  KEY ix_pf_pago (id_pago),
  CONSTRAINT fk_pf_pago FOREIGN KEY (id_pago)
    REFERENCES pago (id_pago) ON DELETE CASCADE,
  CONSTRAINT fk_pf_forma FOREIGN KEY (id_forma_pago)
    REFERENCES forma_pago (id_forma_pago)
) ENGINE=InnoDB;

-- aplicacion del pago a conceptos especificos del plan
-- ---------------------------------------------------------------------
-- cargo: lo unico que el paciente debe
--
-- Antes lo cobrable vivia solo en el plan de tratamiento, y los pagos
-- se aplicaban contra sus renglones. Eso dejaba fuera lo que sale en la
-- consulta sin estar planeado, que es la mitad del trabajo real: una
-- extraccion de urgencia, una resina que aparecio al limpiar.
--
-- Con esta tabla hay un solo lugar donde vive la deuda, venga de donde
-- venga, y una sola forma de saber cuanto se debe: importe menos lo
-- aplicado. El plan y la consulta solo dicen de donde salio el cargo.
-- ---------------------------------------------------------------------
CREATE TABLE cargo (
  id_cargo             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_registro_paciente INT UNSIGNED NOT NULL,

  -- De donde salio. Los tres pueden ser NULL: un cargo manual
  -- (un material, una reposicion) no viene de ningun lado.
  id_plan_tratamiento  INT UNSIGNED NULL,
  id_plan_detalle      INT UNSIGNED NULL,
  id_consulta          INT UNSIGNED NULL,
  id_procedimiento     INT UNSIGNED NULL,

  descripcion          VARCHAR(200) NOT NULL,
  cantidad             SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  precio_unitario      DECIMAL(10,2) NOT NULL,
  descuento            DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  importe              DECIMAL(12,2) NOT NULL,

  -- Quien autorizo el descuento. Se guarda aunque sea el mismo que
  -- cobra: en dinero, "quien lo permitio" es tan importante como
  -- "cuanto fue".
  id_usuario_descuento INT UNSIGNED NULL,
  motivo_descuento     VARCHAR(150) NULL,

  fecha                DATE NOT NULL,
  -- 1 vigente | 0 cancelado. No se borra nunca.
  estatus              TINYINT UNSIGNED NOT NULL DEFAULT 1,
  id_usuario_cancela   INT UNSIGNED NULL,
  fecha_cancelacion    DATETIME NULL,
  motivo_cancelacion   VARCHAR(255) NULL,

  id_usuario           INT UNSIGNED NOT NULL,
  fecha_registro       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (id_cargo),
  KEY ix_cargo_expediente (id_registro_paciente, estatus),
  KEY ix_cargo_plan (id_plan_tratamiento),
  KEY ix_cargo_consulta (id_consulta),
  CONSTRAINT fk_cargo_expediente FOREIGN KEY (id_registro_paciente)
    REFERENCES registro_paciente (id_registro_paciente),
  CONSTRAINT fk_cargo_plan FOREIGN KEY (id_plan_tratamiento)
    REFERENCES plan_tratamiento (id_plan_tratamiento),
  CONSTRAINT fk_cargo_detalle FOREIGN KEY (id_plan_detalle)
    REFERENCES plan_tratamiento_detalle (id_plan_detalle) ON DELETE SET NULL,
  CONSTRAINT fk_cargo_procedimiento FOREIGN KEY (id_procedimiento)
    REFERENCES procedimiento (id_procedimiento),
  CONSTRAINT fk_cargo_usuario FOREIGN KEY (id_usuario)
    REFERENCES usuario (id_usuario),
  CONSTRAINT fk_cargo_usuario_desc FOREIGN KEY (id_usuario_descuento)
    REFERENCES usuario (id_usuario),
  CONSTRAINT fk_cargo_usuario_canc FOREIGN KEY (id_usuario_cancela)
    REFERENCES usuario (id_usuario)
) ENGINE=InnoDB;

-- Que parte de un pago fue a que cargo.
--
-- Un pago puede repartirse entre varios cargos y un cargo puede
-- juntarse de varios pagos. Sin este renglon intermedio no se puede
-- responder "de que me sirvio el abono de hace tres semanas".
CREATE TABLE pago_aplicacion (
  id_pago_aplicacion INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_pago            INT UNSIGNED NOT NULL,
  id_cargo           INT UNSIGNED NOT NULL,
  -- El cobro donde se decidio gastar este saldo a favor.
  --
  -- La aplicacion se cuelga del pago que TRAJO el dinero (el anticipo),
  -- porque entro aquel dia y en aquel corte. Esta columna guarda la
  -- otra mitad: en que cobro se decidio gastarlo, que es lo que la
  -- ficha de ese cobro necesita para poder decir "se te aplicaron 400
  -- de tu saldo a favor".
  --
  -- NULL en las aplicaciones normales, donde el pago que aplica y el
  -- cobro que lo decide son el mismo.
  aplicada_en_pago   INT UNSIGNED NULL,
  monto              DECIMAL(12,2) NOT NULL,
  PRIMARY KEY (id_pago_aplicacion),
  KEY ix_pa_pago (id_pago),
  KEY ix_pa_cargo (id_cargo),
  KEY ix_pa_en_pago (aplicada_en_pago),
  CONSTRAINT fk_pa_pago FOREIGN KEY (id_pago)
    REFERENCES pago (id_pago) ON DELETE CASCADE,
  CONSTRAINT fk_pa_cargo FOREIGN KEY (id_cargo)
    REFERENCES cargo (id_cargo),
  -- SET NULL y no CASCADE: ese dinero se gasto de verdad y el cargo
  -- quedo cubierto. Borrar la aplicacion resucitaria una deuda pagada.
  CONSTRAINT fk_pa_en_pago FOREIGN KEY (aplicada_en_pago)
    REFERENCES pago (id_pago) ON DELETE SET NULL
) ENGINE=InnoDB;

-- todo lo que entra o sale de la caja, incluidos los egresos operativos
CREATE TABLE movimiento_caja (
  id_movimiento   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_corte_caja   INT UNSIGNED NOT NULL,
  id_concepto     SMALLINT UNSIGNED NOT NULL,
  id_forma_pago   TINYINT UNSIGNED NOT NULL,
  id_pago         INT UNSIGNED NULL,      -- si viene de un cobro
  id_usuario      INT UNSIGNED NOT NULL,
  fecha_hora      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  naturaleza      CHAR(1) NOT NULL,       -- I | E
  monto           DECIMAL(12,2) NOT NULL,
  descripcion     VARCHAR(255) NULL,
  estatus         TINYINT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (id_movimiento),
  KEY ix_mc_corte (id_corte_caja),
  KEY ix_mc_pago (id_pago),
  CONSTRAINT fk_mc_corte FOREIGN KEY (id_corte_caja)
    REFERENCES corte_caja (id_corte_caja),
  CONSTRAINT fk_mc_concepto FOREIGN KEY (id_concepto)
    REFERENCES concepto_movimiento (id_concepto),
  CONSTRAINT fk_mc_forma FOREIGN KEY (id_forma_pago)
    REFERENCES forma_pago (id_forma_pago),
  CONSTRAINT fk_mc_pago FOREIGN KEY (id_pago)
    REFERENCES pago (id_pago),
  CONSTRAINT fk_mc_usuario FOREIGN KEY (id_usuario)
    REFERENCES usuario (id_usuario)
) ENGINE=InnoDB;

-- =====================================================================
-- SECCION 9 : FACTURACION (CFDI 4.0)
-- =====================================================================

CREATE TABLE regimen_fiscal (
  id_regimen  SMALLINT UNSIGNED NOT NULL,   -- clave SAT (601, 605, 612...)
  descripcion VARCHAR(150) NOT NULL,
  PRIMARY KEY (id_regimen)
) ENGINE=InnoDB;

CREATE TABLE uso_cfdi (
  id_uso_cfdi CHAR(4) NOT NULL,             -- G03, D01...
  descripcion VARCHAR(150) NOT NULL,
  PRIMARY KEY (id_uso_cfdi)
) ENGINE=InnoDB;

CREATE TABLE datos_fiscales (
  id_datos_fiscales INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_persona        INT UNSIGNED NOT NULL,
  rfc               VARCHAR(13) NOT NULL,
  razon_social      VARCHAR(200) NOT NULL,
  id_regimen        SMALLINT UNSIGNED NOT NULL,
  cp_fiscal         CHAR(5) NOT NULL,
  correo_facturacion VARCHAR(120) NULL,
  id_uso_cfdi       CHAR(4) NULL,
  estatus           TINYINT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (id_datos_fiscales),
  KEY ix_df_persona (id_persona),
  KEY ix_df_rfc (rfc),
  CONSTRAINT fk_df_persona FOREIGN KEY (id_persona)
    REFERENCES persona (id_persona),
  CONSTRAINT fk_df_regimen FOREIGN KEY (id_regimen)
    REFERENCES regimen_fiscal (id_regimen),
  CONSTRAINT fk_df_uso FOREIGN KEY (id_uso_cfdi)
    REFERENCES uso_cfdi (id_uso_cfdi)
) ENGINE=InnoDB;

CREATE TABLE factura (
  id_factura        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  serie             VARCHAR(10) NOT NULL DEFAULT 'A',
  folio             INT UNSIGNED NOT NULL,
  id_registro_paciente INT UNSIGNED NOT NULL,
  id_datos_fiscales INT UNSIGNED NOT NULL,
  id_forma_pago     TINYINT UNSIGNED NOT NULL,
  metodo_pago       CHAR(3) NOT NULL DEFAULT 'PUE',
  id_uso_cfdi       CHAR(4) NOT NULL,
  fecha_emision     DATETIME NOT NULL,
  subtotal          DECIMAL(12,2) NOT NULL,
  descuento         DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  impuestos         DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  total             DECIMAL(12,2) NOT NULL,
  uuid              CHAR(36) NULL,
  fecha_timbrado    DATETIME NULL,
  ruta_xml          VARCHAR(255) NULL,
  ruta_pdf          VARCHAR(255) NULL,
  -- 0 borrador | 1 timbrada | 2 cancelada | 3 error
  estado_factura    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  mensaje_pac       TEXT NULL,
  id_usuario        INT UNSIGNED NOT NULL,
  fecha_registro    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_factura),
  UNIQUE KEY uq_factura_serie_folio (serie, folio),
  UNIQUE KEY uq_factura_uuid (uuid),
  KEY ix_factura_expediente (id_registro_paciente),
  CONSTRAINT fk_factura_expediente FOREIGN KEY (id_registro_paciente)
    REFERENCES registro_paciente (id_registro_paciente),
  CONSTRAINT fk_factura_df FOREIGN KEY (id_datos_fiscales)
    REFERENCES datos_fiscales (id_datos_fiscales),
  CONSTRAINT fk_factura_forma FOREIGN KEY (id_forma_pago)
    REFERENCES forma_pago (id_forma_pago),
  CONSTRAINT fk_factura_uso FOREIGN KEY (id_uso_cfdi)
    REFERENCES uso_cfdi (id_uso_cfdi),
  CONSTRAINT fk_factura_usuario FOREIGN KEY (id_usuario)
    REFERENCES usuario (id_usuario)
) ENGINE=InnoDB;

CREATE TABLE factura_concepto (
  id_factura_concepto INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_factura          INT UNSIGNED NOT NULL,
  id_tratamiento      INT UNSIGNED NULL,
  clave_prod_serv     VARCHAR(10) NOT NULL,
  clave_unidad        VARCHAR(10) NOT NULL DEFAULT 'E48',
  descripcion         VARCHAR(255) NOT NULL,
  cantidad            DECIMAL(10,2) NOT NULL DEFAULT 1,
  valor_unitario      DECIMAL(12,2) NOT NULL,
  descuento           DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  importe             DECIMAL(12,2) NOT NULL,
  objeto_impuesto     CHAR(2) NOT NULL DEFAULT '01',
  PRIMARY KEY (id_factura_concepto),
  KEY ix_fc_factura (id_factura),
  CONSTRAINT fk_fc_factura FOREIGN KEY (id_factura)
    REFERENCES factura (id_factura) ON DELETE CASCADE,
  CONSTRAINT fk_fc_tratamiento FOREIGN KEY (id_tratamiento)
    REFERENCES tratamiento (id_tratamiento)
) ENGINE=InnoDB;

-- relacion factura <-> pagos que ampara
CREATE TABLE factura_pago (
  id_factura INT UNSIGNED NOT NULL,
  id_pago    INT UNSIGNED NOT NULL,
  PRIMARY KEY (id_factura, id_pago),
  CONSTRAINT fk_fp_factura FOREIGN KEY (id_factura)
    REFERENCES factura (id_factura) ON DELETE CASCADE,
  CONSTRAINT fk_fp_pago FOREIGN KEY (id_pago)
    REFERENCES pago (id_pago)
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- SECCION 10 : VISTAS DE APOYO
-- =====================================================================

-- Nombre completo y datos de contacto principales del paciente,
-- para la lista de "ultimos 25 pacientes atendidos".
CREATE OR REPLACE VIEW v_paciente AS
SELECT
  rp.id_registro_paciente,
  rp.num_expediente,
  p.id_persona,
  p.nombres,
  p.ap_paterno,
  p.ap_materno,
  CONCAT_WS(' ', p.nombres, p.ap_paterno, p.ap_materno) AS nombre_completo,
  p.fecha_nacim,
  TIMESTAMPDIFF(YEAR, p.fecha_nacim, CURDATE()) AS edad,
  g.nombre_genero,
  p.id_ocupacion,
  o.nombre_ocupacion,
  p.rfc,
  p.foto,
  p.estatus,
  -- La fecha de alta es la APERTURA del expediente, no el instante en
  -- que se creo el renglon: un paciente de 2019 que se paso a Nanai la
  -- semana pasada tiene fecha_registro de la semana pasada, y ordenando
  -- por ella saldria como el mas nuevo del consultorio.
  rp.fecha_apertura AS fecha_alta,
  (SELECT cp.valor_contacto FROM contacto_persona cp
     JOIN medio_contacto mc ON mc.id_medio_contacto = cp.id_medio_contacto
    WHERE cp.id_persona = p.id_persona AND cp.estatus = 1
      AND mc.tipo_dato = 'tel'
    ORDER BY cp.es_principal DESC, cp.id_contacto_persona LIMIT 1) AS telefono,
  (SELECT cp.valor_contacto FROM contacto_persona cp
     JOIN medio_contacto mc ON mc.id_medio_contacto = cp.id_medio_contacto
    WHERE cp.id_persona = p.id_persona AND cp.estatus = 1
      AND mc.tipo_dato = 'email'
    ORDER BY cp.es_principal DESC, cp.id_contacto_persona LIMIT 1) AS correo,
  (SELECT MAX(c.fecha_hora) FROM consulta c
    WHERE c.id_registro_paciente = rp.id_registro_paciente) AS ultima_consulta
FROM registro_paciente rp
JOIN persona p ON p.id_persona = rp.id_persona
LEFT JOIN genero g ON g.id_genero = p.id_genero
LEFT JOIN ocupacion o ON o.id_ocupacion = p.id_ocupacion;

-- Domicilio "aplanado" listo para pintar en la tabla de domicilios.
CREATE OR REPLACE VIEW v_domicilio AS
SELECT
  d.id_domicilio,
  pd.id_persona,
  pd.es_principal,
  d.calle, d.num_ext, d.num_int,
  d.entre_calle_1, d.entre_calle_2, d.referencia,
  COALESCE(col.nombre_colonia, d.colonia_libre) AS colonia,
  COALESCE(col.codigo_postal, d.cp_libre)       AS codigo_postal,
  mun.id_municipio, mun.nombre_municipio,
  est.id_estado,    est.nombre_estado,
  CONCAT_WS(' ', d.calle, d.num_ext,
    CASE WHEN d.num_int IS NULL OR d.num_int = '' THEN NULL
         ELSE CONCAT('Int. ', d.num_int) END)   AS calle_numero,
  -- El domicilio se ve activo solo si lo estan TANTO el domicilio como
  -- el vinculo con la persona: un domicilio compartido puede seguir vivo
  -- para el padre aunque se haya quitado del expediente del hijo.
  LEAST(d.estatus, pd.estatus) AS estatus
FROM domicilio d
JOIN persona_domicilio pd ON pd.id_domicilio = d.id_domicilio
LEFT JOIN colonia   col ON col.id_colonia   = d.id_colonia
LEFT JOIN municipio mun ON mun.id_municipio = col.id_municipio
LEFT JOIN estado    est ON est.id_estado    = mun.id_estado;

-- Pendientes de digitalizacion por expediente. La pestania Expediente
-- Electronico la usa para saber si debe resaltarse.
CREATE OR REPLACE VIEW v_pendientes_digitalizacion AS
SELECT
  d.id_registro_paciente,
  COUNT(*) AS pendientes,
  MIN(d.fecha_solicitud) AS mas_antiguo,
  GROUP_CONCAT(td.nombre_documento ORDER BY td.orden SEPARATOR ', ') AS lista
FROM documento d
JOIN tipo_documento td ON td.id_tipo_documento = d.id_tipo_documento
WHERE d.estado = 0 AND d.estatus = 1
GROUP BY d.id_registro_paciente;

-- ---------------------------------------------------------------------
-- Saldos
--
-- El saldo no se guarda en ninguna columna: se calcula. Una columna
-- 'saldo' se desincroniza en cuanto se cancela un pago y nadie se
-- entera hasta que un tutor reclama. Aqui siempre sale de sumar.
-- ---------------------------------------------------------------------

-- Cada cargo con lo que se le ha aplicado y lo que le falta.
CREATE OR REPLACE VIEW v_cargo_saldo AS
SELECT
  c.id_cargo,
  c.id_registro_paciente,
  c.id_plan_tratamiento,
  c.id_consulta,
  c.descripcion,
  c.fecha,
  c.importe,
  COALESCE(ap.aplicado, 0) AS aplicado,
  c.importe - COALESCE(ap.aplicado, 0) AS saldo
FROM cargo c
LEFT JOIN (
  SELECT pa.id_cargo, SUM(pa.monto) AS aplicado
  FROM pago_aplicacion pa
  JOIN pago p ON p.id_pago = pa.id_pago AND p.estatus = 1
  GROUP BY pa.id_cargo
) ap ON ap.id_cargo = c.id_cargo
WHERE c.estatus = 1;

-- Saldo vivo por plan de tratamiento.
CREATE OR REPLACE VIEW v_plan_saldo AS
SELECT
  pt.id_plan_tratamiento,
  pt.id_registro_paciente,
  pt.folio,
  pt.total,
  COALESCE(SUM(vcs.importe), 0)  AS cargado,
  COALESCE(SUM(vcs.aplicado), 0) AS pagado,
  COALESCE(SUM(vcs.saldo), 0)    AS saldo_actual
FROM plan_tratamiento pt
LEFT JOIN v_cargo_saldo vcs
  ON vcs.id_plan_tratamiento = pt.id_plan_tratamiento
GROUP BY pt.id_plan_tratamiento, pt.id_registro_paciente, pt.folio, pt.total;

-- Saldo total del paciente, venga del plan que venga.
CREATE OR REPLACE VIEW v_paciente_saldo AS
SELECT
  vcs.id_registro_paciente,
  COUNT(*)                    AS cargos,
  SUM(vcs.importe)            AS cargado,
  SUM(vcs.aplicado)           AS pagado,
  SUM(vcs.saldo)              AS saldo,
  SUM(CASE WHEN vcs.saldo > 0 THEN 1 ELSE 0 END) AS cargos_abiertos,
  MIN(CASE WHEN vcs.saldo > 0 THEN vcs.fecha END) AS adeudo_mas_antiguo
FROM v_cargo_saldo vcs
GROUP BY vcs.id_registro_paciente;

-- Resumen de un turno de caja, para el corte.
CREATE OR REPLACE VIEW v_corte_resumen AS
SELECT
  cc.id_corte_caja,
  fp.id_forma_pago,
  fp.nombre_forma,
  fp.afecta_efectivo,
  SUM(CASE WHEN mc.naturaleza = 'I' THEN mc.monto ELSE 0 END) AS ingresos,
  SUM(CASE WHEN mc.naturaleza = 'E' THEN mc.monto ELSE 0 END) AS egresos,
  SUM(CASE WHEN mc.naturaleza = 'I' THEN mc.monto ELSE -mc.monto END) AS neto
FROM corte_caja cc
JOIN movimiento_caja mc
  ON mc.id_corte_caja = cc.id_corte_caja AND mc.estatus = 1
JOIN forma_pago fp ON fp.id_forma_pago = mc.id_forma_pago
GROUP BY cc.id_corte_caja, fp.id_forma_pago, fp.nombre_forma, fp.afecta_efectivo;


-- =====================================================================
-- documento.id_hallazgo apunta a una tabla que se declara despues, asi
-- que su llave va aqui. SET NULL y no CASCADE: borrar el hallazgo
-- escrito no debe llevarse la radiografia, que es un documento del
-- expediente por derecho propio.
ALTER TABLE documento
  ADD CONSTRAINT fk_doc_hallazgo FOREIGN KEY (id_hallazgo)
  REFERENCES hallazgo (id_hallazgo) ON DELETE SET NULL;

-- SECCION 11 : LLAVES QUE CRUZAN SECCIONES
--
-- documento y consulta se declaran mucho antes que plan_tratamiento, y
-- MySQL exige que la tabla apuntada ya exista. Por eso estas dos llaves
-- se agregan hasta el final en lugar de ir dentro del CREATE TABLE.
-- =====================================================================

ALTER TABLE documento
  ADD CONSTRAINT fk_doc_plan FOREIGN KEY (id_plan_tratamiento)
      REFERENCES plan_tratamiento (id_plan_tratamiento) ON DELETE SET NULL;

ALTER TABLE consulta
  ADD CONSTRAINT fk_consulta_plan FOREIGN KEY (id_plan_tratamiento)
      REFERENCES plan_tratamiento (id_plan_tratamiento) ON DELETE SET NULL;

ALTER TABLE documento
  ADD CONSTRAINT fk_doc_consulta FOREIGN KEY (id_consulta)
      REFERENCES consulta (id_consulta) ON DELETE SET NULL;

ALTER TABLE cita
  ADD CONSTRAINT fk_cita_procedimiento FOREIGN KEY (id_procedimiento)
      REFERENCES procedimiento (id_procedimiento);

ALTER TABLE lista_espera
  ADD CONSTRAINT fk_espera_cita FOREIGN KEY (id_cita)
      REFERENCES cita (id_cita) ON DELETE SET NULL;

ALTER TABLE cargo
  ADD CONSTRAINT fk_cargo_consulta FOREIGN KEY (id_consulta)
      REFERENCES consulta (id_consulta) ON DELETE SET NULL;
