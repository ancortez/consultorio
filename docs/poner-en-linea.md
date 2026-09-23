# Poner el sistema en línea

Sistema Consultorio Dental — guía de instalación en servidor

Esta guía va de cero a tener el sistema funcionando en internet con
candado de seguridad. Está escrita para seguirse sin saber de
servidores: si algo pide que escribas un comando, es copiar y pegar.

Tiempo: **una hora la primera vez.** De ahí en adelante, actualizar es
un comando.

---

## Primero, dos cosas que se confunden seguido

### El servidor y el dominio son dos compras distintas

| Qué | Dónde se compra | Qué te dan | Cuánto |
|---|---|---|---|
| **Servidor** | Contabo | Una computadora prendida y una IP (`194.35.12.88`) | ~$7 USD/mes |
| **Dominio** | Cloudflare, Namecheap, Porkbun | Un nombre (`consultorio.tudominio.com`) | ~$180 MXN/año |

Contabo no vende nombres y el registrador no vende servidores. Después
le dices al registrador que ese nombre apunta a esa IP, y ya. Eso es
todo lo que hace el dominio: traducir un nombre que la gente recuerda a
un número que la gente no.

### El dominio es la puerta principal, no un túnel

El dominio es la **dirección pública del sistema**: lo que la doctora
teclea en su navegador para entrar a trabajar, desde el consultorio,
desde su casa o desde el celular. Eso sí está abierto a internet, y por
eso lleva HTTPS.

El túnel SSH es otra cosa distinta, para otra gente y con otro fin:

| | Quién entra | Desde dónde | Cómo |
|---|---|---|---|
| **El sistema** | La doctora, recepción, tú | Cualquier lugar | `https://consultorio.tudominio.com` |
| **phpMyAdmin** | Solo tú | Solo tu computadora | Túnel SSH |
| **Consola de MinIO** | Solo tú | Solo tu computadora | Túnel SSH |

Compáralo con lo que ya corres en local. Ahí abres tres direcciones:

| En tu computadora | En el servidor |
|---|---|
| `localhost:8090` — el sistema | `https://consultorio.tudominio.com` |
| `localhost:8091` — phpMyAdmin | túnel, y luego `localhost:8081` |
| `localhost:9101` — MinIO | túnel, y luego `localhost:9101` |

Es lo mismo. Solo que la primera se vuelve pública con su dominio y las
otras dos siguen siendo tuyas.

**¿Por qué esa diferencia?** Porque phpMyAdmin y la consola de MinIO son
herramientas de administración. Con phpMyAdmin se borra la base entera
en dos clics; con la consola de MinIO se descargan todas las
radiografías de todos los pacientes. Nadie del consultorio las
necesita. Publicarlas sería poner dos puertas más a la calle para que
las use una sola persona que además ya tiene llave de la puerta de
servicio.

---

## Lo que vas a tener al final

```
   La doctora, recepción                      Tú, administrando
   (desde donde sea)                          (desde tu computadora)
          │                                            │
          │  https://consultorio.tudominio.com         │  ssh -L ...
          ▼                                            ▼
   ╔══════════════════╗                        ╔═══════════════╗
   ║   PUERTA 443     ║                        ║   PUERTA 22   ║
   ║   abierta a      ║                        ║   solo con    ║
   ║   internet       ║                        ║   tu llave    ║
   ╚════════╤═════════╝                        ╚═══════╤═══════╝
            │                                          │
   ┌────────▼─────────┐                                │
   │      Caddy       │  ← el HTTPS lo consigue solo   │
   └────────┬─────────┘                                │
            │                                          │
   ┌────────▼─────────┐        ┌──────────────┐        │
   │   Sistema PHP    │───────▶│    MinIO     │◀───────┤
   └────────┬─────────┘        │  (archivos)  │        │
            │                  └──────────────┘        │
   ┌────────▼─────────┐        ┌──────────────┐        │
   │      MySQL       │◀───────│  phpMyAdmin  │◀───────┘
   └──────────────────┘        └──────────────┘

   ─────────────────────────────────────────────────────────
   Abierto a internet:  solo el puerto 443 (y el 80, que
                        redirige al 443) y el 22 para SSH.
   Cerrado:             MySQL, phpMyAdmin y la consola de
                        MinIO. Existen, pero solo dentro del
                        servidor.
```

Dicho de otra forma: el sistema tiene una puerta a la calle con su
dirección y su cerradura. Las herramientas de administración están en
un cuarto interior, y a ese cuarto se entra por la puerta de servicio,
con tu llave.

---

## Antes de empezar

Necesitas tres cosas:

1. **Una tarjeta** para el servidor (~$7 USD al mes) y el dominio
   (~$150 MXN al año).
2. **La Terminal** de tu Mac. Está en Aplicaciones → Utilidades.
3. **El proyecto en un repositorio git** (GitHub, GitLab). Si no lo
   tienes, el paso 4 explica cómo subir los archivos sin git.

---

## Paso 1 — Contratar el servidor

Entra a [contabo.com](https://contabo.com) y contrata un **Cloud VPS**.

| Qué elegir | Valor | Por qué |
|---|---|---|
| Plan | **Cloud VPS 4** | 4 vCPU / 8 GB / 100 GB SSD. Alcanza de sobra y es el mejor precio por lo que se necesita. |
| Región | **US Central (St. Louis)** | El punto de EE.UU. con mejor ruta hacia el centro de México. |
| Imagen | **Ubuntu 24.04 LTS** | Es sobre lo que está probado el instalador. |
| Almacenamiento | **SSD** (no el "storage" barato) | Las radiografías y la base necesitan disco rápido. |
| Periodo | Mensual al principio | El precio de lista sube después de 24 meses (de $5.28 a $6.60). Aun así sigue barato. |
| Auto Backup | **Apagado** | El sistema ya trae su propio respaldo y lo puedes probar. El de Contabo es una caja negra. |
| Autenticación | **Llave SSH** | Ver abajo. Mucho más seguro que contraseña. |

### Tu llave SSH

Es como una llave de casa: la mitad se queda contigo y la otra se le da
al servidor. Nadie entra sin ella.

En tu Terminal:

```bash
# ¿Ya tienes una?
cat ~/.ssh/id_ed25519.pub

# Si dice "No such file", créala (deja todo en blanco al preguntar):
ssh-keygen -t ed25519 -C "angel-consultorio"
cat ~/.ssh/id_ed25519.pub
```

Copia todo lo que salga (empieza con `ssh-ed25519`) y pégalo donde
Contabo pida la llave pública.

> **Ojo con Contabo:** tardan de 15 minutos a unas horas en entregar el
> servidor, y a veces piden verificación de identidad en el primer
> pedido. Es el precio de que sea el más barato. Te llega un correo con
> la IP cuando está listo.

---

## Paso 2 — Comprar el dominio

Cualquier registrador sirve. Los tres que suelo recomendar:

| Dónde | Precio aprox. | Nota |
|---|---|---|
| [Cloudflare](https://www.cloudflare.com/products/registrar/) | ~$180 MXN/año | Vende al costo, sin sobreprecio en la renovación. |
| [Namecheap](https://www.namecheap.com) | ~$200 MXN/año | Panel sencillo, buen soporte. |
| [Porkbun](https://porkbun.com) | ~$190 MXN/año | Barato y con privacidad incluida. |

Compra algo como `cdeodental.com` o `sistemasancortez.com` — si lo vas
a usar para más proyectos, conviene que el dominio sea tuyo, no del
consultorio, y darle a cada sistema un subdominio.

### Apuntar el dominio al servidor

En el panel del registrador, busca **DNS** y crea un registro:

| Tipo | Nombre | Valor |
|---|---|---|
| A | `consultorio` | *(la IP que te dio Contabo)* |

Eso hace que `consultorio.tudominio.com` lleve a tu servidor. Tarda de
5 minutos a 2 horas en propagarse.

Comprueba desde tu Terminal:

```bash
dig +short consultorio.tudominio.com
```

Cuando responda tu IP, sigue. **Si todavía no responde, espera:** el
certificado HTTPS falla si el dominio no apunta aún.

---

## Paso 3 — Entrar al servidor

```bash
ssh root@TU_IP
```

La primera vez te pregunta si confías en el servidor: escribe `yes`.

---

## Paso 4 — Subir el proyecto

**Si tienes el proyecto en git** (recomendado), el instalador lo baja
solo. Solo define de dónde:

```bash
export REPO_GIT="https://github.com/tuusuario/consultorio.git"
```

**Si no usas git todavía**, súbelo desde tu Mac. En **otra ventana** de
Terminal, en tu computadora:

```bash
cd ~/Proyectos/consultorio
rsync -avz --exclude '.git' --exclude '.env' --exclude 'storage/uploads' \
      ./ root@TU_IP:/opt/consultorio/
```

---

## Paso 5 — Correr el instalador

De vuelta en la ventana del servidor:

```bash
cd /opt/consultorio/infra/produccion
bash instalar-servidor.sh
```

Te va a preguntar tres cosas:

- **Dominio** — `consultorio.tudominio.com`
- **Tu correo** — para los avisos del certificado. Usa uno que sí leas:
  es el único aviso que recibirás si el HTTPS deja de renovarse.
- **¿Publicar la consola de MinIO?** — di **no**. Esa consola deja ver y
  descargar todos los documentos del expediente. Mientras no la
  necesites desde fuera, se entra por túnel.

El instalador hace todo lo demás: actualiza el sistema, crea un usuario
sin privilegios, instala Docker, cierra el cortafuegos, **genera
contraseñas al azar**, levanta todo y programa el respaldo diario.

Tarda unos 10 minutos.

---

## Paso 6 — Primer acceso

Abre `https://consultorio.tudominio.com`.

> Si ves un aviso de seguridad, espera un minuto y recarga: el
> certificado tarda un poco en emitirse la primera vez.

Entra con **admin** / **Admin123!** y **cambia la contraseña de
inmediato**. Esa clave está escrita en el código y es pública.

### Guarda el archivo de contraseñas

```bash
cat /opt/consultorio/infra/produccion/.env
```

Cópialo a tu gestor de contraseñas (1Password, Bitwarden, el llavero de
tu Mac). **Si se pierde y no tienes respaldo, se pierde el acceso a la
base de datos.** No hay forma de recuperarlo.

---

## Cómo se usa de aquí en adelante

### Subir cambios

Desde tu Mac haces `git push`. Después, en el servidor:

```bash
ssh consultorio@TU_IP
cd /opt/consultorio/infra/produccion
./desplegar.sh
```

Respalda, baja los cambios, reconstruye y comprueba que el sistema
responde. Si algo falla, te lo dice y te da el comando para ver el
error.

### Entrar a phpMyAdmin

No está publicado. Desde tu Mac:

```bash
ssh -L 8081:localhost:8081 consultorio@TU_IP
```

Deja esa ventana abierta y abre `http://localhost:8081` en el
navegador. Al cerrar la ventana, se cierra el acceso.

### Entrar a la consola de MinIO

Igual, con otro puerto:

```bash
ssh -L 9101:localhost:9101 consultorio@TU_IP
```

Y abre `http://localhost:9101`. Usuario y contraseña están en el `.env`.

### Ver qué está pasando

```bash
cd /opt/consultorio/infra/produccion

docker compose -f docker-compose.produccion.yml ps          # qué corre
docker compose -f docker-compose.produccion.yml logs -f app # errores en vivo
```

---

## Respaldos

Se hacen solos cada noche a las 2:30, en `/var/respaldos/consultorio`.
Son **dos respaldos distintos** con ritmos distintos, y esa separación
importa:

| Qué | Cuándo | Cuántos se guardan | Por qué así |
|---|---|---|---|
| **Base de datos** | Todas las noches | 14 días | Pesa megabytes. Un cobro perdido no se recupera de ningún lado. |
| **Archivos del expediente** | Domingos | 4 copias | Pesan gigabytes. Un escaneo perdido se vuelve a escanear: el papel sigue en el consultorio. |

Si los dos fueran diarios con 14 copias, con mil pacientes serían más
de 400 GB en un disco de 100. El disco se llenaría y MySQL se caería,
que es peor que no tener respaldo. Antes de empezar, el guion revisa el
espacio libre y se detiene si queda poco.

```bash
./respaldar.sh              # base; archivos si toca
./respaldar.sh --completo   # fuerza los dos
./restaurar.sh --listar     # ver los que hay, separados por tipo
./restaurar.sh --probar     # PROBAR el último sin tocar nada
```

### Prueba el respaldo hoy, no el día que lo necesites

`--probar` abre el respaldo en una base temporal, cuenta los pacientes,
las consultas y los cobros, y borra la base de prueba. **La base real no
se toca.** Puedes correrlo un martes cualquiera sin riesgo.

Un respaldo que nunca se probó no es un respaldo: es una carpeta con
archivos que uno supone que sirven.

### Bájalos a tu computadora

Los respaldos viven en el mismo servidor. Eso no protege del caso más
probable: que el servidor se pierda. Una vez a la semana, desde tu Mac:

```bash
rsync -avz consultorio@TU_IP:/var/respaldos/consultorio/ \
           ~/Respaldos/consultorio/
```

Antes de sacarlos del servidor, ponles cifrado. Agrega al `.env`:

```
CLAVE_RESPALDO=una-frase-larga-que-solo-tu-sepas
```

Sin esa línea el respaldo sale sin cifrar, y trae el expediente clínico
completo de cada paciente.

---

## Antes de meter datos reales de pacientes

Ahora vas a usarlo con datos de demostración, y para eso lo de arriba
basta. **Cuando el consultorio empiece a capturar pacientes de verdad,
cambian las reglas.** No es una formalidad: en México los datos de salud
son *datos personales sensibles*, y la Ley Federal de Protección de
Datos Personales en Posesión de los Particulares —renovada en marzo de
2025— pide medidas reforzadas para tratarlos.

Lo que aplica directamente a lo que acabas de montar:

**Aviso de privacidad.** El consultorio debe tener uno y dárselo a los
pacientes. Ahora que el consentimiento bajo información ya se imprime
desde el sistema, ese es el lugar natural para incluirlo.

**Consentimiento expreso y por escrito.** Para datos sensibles no basta
el consentimiento tácito: hace falta firma autógrafa o electrónica. El
consentimiento que ya imprime el sistema cubre el tratamiento dental;
habría que agregar la cláusula del tratamiento de datos.

**Contrato con el proveedor.** Cuando los datos viven en la nube de un
tercero —Contabo, en este caso— la ley pide que ese proveedor garantice
protecciones equivalentes, y que quede por escrito. Hay que revisar los
términos de Contabo y, si el consultorio lo requiere, buscar un
proveedor que firme un acuerdo de tratamiento de datos.

**Medidas técnicas mínimas:** control de accesos, cifrado de la
información sensible, respaldos y bitácora de accesos. El sistema ya
trae las cuatro (usuarios con permisos, HTTPS, respaldos, tabla
`bitacora`), pero hay que activar el cifrado de respaldos y bajarlos
cifrados.

**Responsable de datos personales.** Hay que designar a alguien en el
consultorio. Suele ser la titular.

> **No soy abogado y esto no es asesoría legal.** Es lo que encontré al
> revisar la ley para poder dejarte el servidor bien configurado. Antes
> de subir el primer paciente real, vale la pena que la doctora lo
> consulte con alguien que sí lo sea: el costo de una consulta es mucho
> menor que el de una multa o el de una fuga de expedientes.

Un apunte técnico sobre esto: Contabo está en Alemania con centros de
datos en EE.UU. Si más adelante resulta que conviene tener los datos en
México, **Vultr tiene centro de datos en Ciudad de México** y migrar es
cuestión de restaurar un respaldo en otro servidor. Por eso el respaldo
y la restauración están hechos para funcionar entre máquinas distintas.

---

## Si algo sale mal

| Lo que ves | Qué pasa | Qué hacer |
|---|---|---|
| "No es seguro" en el navegador | El certificado no se emitió | Revisa que `dig +short tudominio` dé tu IP. Luego `docker compose -f docker-compose.produccion.yml logs caddy` |
| El sitio no carga | Algo se cayó | `docker compose -f docker-compose.produccion.yml ps`. Lo que no diga "Up", revisa sus logs. |
| Error 500 | Falla de PHP | `docker compose -f docker-compose.produccion.yml logs --tail 50 app` |
| No suben documentos | MinIO caído | `cd ../minio && docker compose -f docker-compose.produccion.yml ps` |
| Todo va lento | Poca RAM | `free -h` y `docker stats`. Si la RAM está al tope, sube de plan. |

### El comando que resuelve la mayoría

```bash
cd /opt/consultorio/infra/produccion
docker compose -f docker-compose.produccion.yml restart
```

### Si de verdad se rompió

```bash
./restaurar.sh --listar
./restaurar.sh /var/respaldos/consultorio/consultorio-FECHA.tar.gz
```

Antes de pisar nada, guarda una copia de lo que hay. Siempre hay camino
de vuelta.

---

## Sobre Contabo, con honestidad

Elegiste el más barato del mercado y eso tiene un costo que no aparece
en el recibo:

- **Rendimiento variable.** Compartes hardware con muchos vecinos. A
  veces va perfecto, a veces se arrastra sin razón aparente.
- **Soporte lento.** Responden por correo en horas o días, no en
  minutos.
- **Entrega lenta.** El servidor puede tardar horas en estar listo.
- **Sin respaldos automáticos del proveedor** en los planes básicos.
  Por eso el respaldo del sistema no es opcional aquí.

Para mostrar avances del proyecto está perfectamente bien y te ahorra
dinero real. Cuando el consultorio dependa del sistema para trabajar
todos los días, vale la pena moverse a algo más predecible —Hetzner por
precio, Vultr CDMX por cercanía—. Migrar es restaurar un respaldo en
otro servidor: por eso está hecho como está.

---

## Para usarlo con otros proyectos

Dijiste que después le darías más usos. La forma de crecer sin rehacer
nada:

- **MinIO ya es compartido.** Vive en su propio proyecto y en la red
  `sistemas`. Otro sistema tuyo puede guardar ahí con su propio bucket:
  agrégalo a `MINIO_BUCKETS` en `infra/minio/.env`.
- **Caddy también es compartido.** Para publicar otro sistema, agrega su
  bloque en `infra/produccion/extra/` con su propio dominio. El
  certificado se emite solo.
- **Un subdominio por sistema.** `consultorio.tudominio.com`,
  `otro.tudominio.com`. Un solo servidor, un solo pago.

Con 8 GB caben tres o cuatro sistemas chicos como este sin problema.
