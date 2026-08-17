# Sistema Consultorio Dental

Sistema de escritorio (web) para la administración de un consultorio dental:
expediente de pacientes, agenda, consultas, planes de tratamiento, caja y
facturación.

- **Stack:** PHP 8.2 · MySQL 8.0 · Bootstrap 5 · SweetAlert2 · Docker
- **Resolución objetivo:** 1280 px o más (escritorio, no responsivo)

---

## Levantar el proyecto

```bash
cp .env.example .env      # ajusta puertos y contraseñas si hace falta
docker compose up -d --build
```

| Servicio | URL | Notas |
|---|---|---|
| Sistema | http://localhost:8090 | |
| phpMyAdmin | http://localhost:8091 | usuario `root`, contraseña la de `DB_ROOT_PASS` |
| MySQL | localhost:3310 | por si quieres conectar un cliente externo |

**Puertos.** Se eligieron 8090, 8091 y 3310 en vez de los típicos 8080 y 3306
para no chocar con otros contenedores. Si aun así están ocupados,
`iniciar.command` busca el siguiente puerto libre y lo guarda en el `.env`.
Para fijar uno concreto, edítalo ahí.

La primera vez que arranca, MySQL ejecuta en orden los archivos de `db/`:
esquema, catálogos y datos de demostración.

### Usuarios de prueba

| Usuario | Rol | Contraseña |
|---|---|---|
| `admin` | Administrador | `Admin123!` |
| `dr.mendoza` | Dentista | `Admin123!` |
| `recepcion` | Recepción | `Admin123!` |

### Recargar la base desde cero

```bash
docker compose down -v && docker compose up -d
```

El `-v` borra el volumen; sin él los scripts de `db/` **no** se vuelven a ejecutar.

---

## Estructura

```
consultorio/
├── index.php              Plantilla general y router de vistas
├── login.php  logout.php  Acceso al sistema
├── assets/                CSS, JS e imágenes
│   ├── css/estilo.css     Toda la paleta vive en las variables del :root
│   └── js/                app.js (común) + un archivo por módulo
├── config/                Configuración y conexión PDO
├── includes/              Backend
│   ├── core/              bootstrap, auth, sesión, CSRF, bitácora
│   ├── _endpoint.php      Preámbulo de seguridad de todo endpoint AJAX
│   ├── paciente/  cajas/  consultas/  reportes/  configuracion/  facturacion/
│   └── geo/               Catálogos encadenados estado → municipio → colonia
├── vistas/                HTML de cada pantalla
│   ├── layout/            nav.php y footer.php
│   └── paciente/          buscar_paciente, detalle_paciente y sus modales
├── db/                    Scripts SQL (se ejecutan solos al crear el contenedor)
├── docker/php/            Dockerfile, php.ini y vhost
├── storage/               Subidas y logs (fuera del control de versiones)
└── docs/                  Documentación del proyecto
```

---

## Convenciones

Cuatro reglas que mantienen el código consistente conforme crece:

1. **Todo archivo PHP empieza igual.** Las vistas se cargan desde `index.php`;
   los endpoints arrancan con `require_once __DIR__ . '/../_endpoint.php';` y
   `endpoint('modulo.permiso');`, que valida método, sesión, token CSRF y
   permiso en una sola línea.
2. **Nada de SQL concatenado.** Siempre `Conexion::consulta()` con parámetros.
3. **Nada se imprime sin escapar.** Toda variable que llegue al HTML pasa por
   `e()`; en JavaScript, por `App.esc()`.
4. **No se borra, se da de baja.** Los registros llevan `estatus`; eliminar
   significa ponerlo en `0`.

## Estado del desarrollo

| Módulo | Estado |
|---|---|
| Acceso, roles y permisos | Funcional |
| Plantilla general (nav, footer, router) | Funcional |
| Tablero de inicio | Funcional |
| Paciente — buscar, alta, detalle (4 pestañas) | Funcional |
| Configuración — Usuarios y roles | Funcional |
| Configuración — Colonias | Funcional |
| Consultas, Cajas, Reportes, Facturación | Pendientes — el modelo de datos ya los contempla |

## Reglas de captura

- **Todo en MAYÚSCULAS.** Se aplica en tres niveles: CSS para que el usuario
  lo vea al teclear, JavaScript para el texto pegado, y PHP con `mayus()`
  antes de guardar. El servidor es el único que manda. Se exceptúan correos
  (se guardan en minúsculas) y contraseñas.
- **La ocupación se resuelve por edad.** Menos de 3 años, `NINGUNA`; de 3 a
  15, `ESTUDIANTE`; de 16 en adelante se captura. La regla vive en
  `ocupacionPorEdad()` y busca por clave, no por id.
- **Los campos del domicilio se desbloquean al elegir colonia.** Capturar la
  calle antes que la colonia lleva a domicilios sin código postal.

## Verificar el proyecto

```bash
python3 tools/verificar.py
```

Revisa el SQL contra el esquema, las rutas AJAX contra los archivos, los
permisos contra el catálogo y la sintaxis del JavaScript. Conviene correrlo
antes de cada entrega.

## Antes de producción

- [ ] Cargar el catálogo completo de colonias de SEPOMEX (`db/02_catalogos.sql`
      solo trae una muestra para la demo)
- [ ] **No** ejecutar `db/03_datos_demo.sql`
- [ ] `APP_ENV=produccion` en el `.env` y contraseñas reales
- [ ] Servir por HTTPS y activar `session.cookie_secure`
- [ ] Respaldo automático diario de la base de datos
- [ ] Descargar Bootstrap y SweetAlert2 a `assets/vendor/` si el consultorio
      puede quedarse sin internet
