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

> **Si compraste un dominio `.dev`** (o `.app`, o `.page`): todo ese
> grupo de dominios está en la lista de *HSTS preload*, lo que
> significa que los navegadores **solo** los abren por HTTPS, siempre.
> Es más seguro, pero tiene una consecuencia: mientras Caddy no haya
> emitido el certificado, el navegador no va a mostrar un aviso que
> puedas saltarte — simplemente no va a conectar. No es que algo esté
> roto; es que todavía no hay certificado. Espera un minuto y recarga.

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

**Si no usas git todavía**, hay un guion que lo sube por ti. En **otra
ventana** de Terminal, en tu computadora:

```bash
cd ~/Proyectos/consultorio
./subir-al-servidor.command
```

La primera vez te pide la dirección del servidor y la recuerda. Sube
todo respetando la estructura de carpetas, y al terminar **comprueba
que los archivos clave llegaron a su lugar** y que la versión del
instalador de allá es la misma de acá.

Existe por una razón concreta: escribir las rutas a mano es fácil de
equivocar, y un archivo que aterriza en la carpeta de al lado deja el
servidor corriendo la versión vieja sin que nada avise. El síntoma es
desconcertante — arreglas algo, lo subes, y el error es idéntico.

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

## Si el servidor se instaló a mano y quieres enlazarlo con git

Pasa cuando la primera instalación se subió con `rsync` o
`subir-al-servidor.command`: en el servidor están los archivos, pero no
el repositorio. `desplegar.sh` necesita un `git pull` para traer los
cambios, y sin repositorio se salta ese paso y avisa que hay que
subirlos a mano.

Para averiguar en cuál de los dos casos estás:

```bash
ssh root@TU_SERVIDOR
cd /opt/consultorio
git remote -v        # si no dice nada, no está enlazado
```

Para enlazarlo, **sin perder nada de lo que hay en el servidor**:

```bash
cd /opt/consultorio/infra/produccion
./enlazar-git.sh
```

Respalda primero, crea el repositorio, baja el código y vuelve a dejar
los permisos como Apache los necesita.

**Lo que no toca:** `git reset --hard` solo pisa archivos que el
repositorio conoce, y los importantes están todos en `.gitignore`.
Sobreviven los dos `.env` —con las contraseñas de la base—, `storage/`
con los archivos subidos, y los respaldos. El guion los lista al
terminar para que lo veas, y además deja una copia aparte de los
`.env`.

### Si el repositorio es privado

`git` va a pedir credenciales y el guion se detiene explicando esto
mismo. La forma limpia en un servidor es una **llave de despliegue**:
es de solo lectura, vale únicamente para ese repositorio y se revoca
desde GitHub sin tocar tu cuenta.

```bash
# 1. En el servidor
ssh-keygen -t ed25519 -C 'vps-consultorio' -f ~/.ssh/github -N ''
cat ~/.ssh/github.pub
```

```
# 2. En GitHub
   El repositorio > Settings > Deploy keys > Add deploy key
   Pega lo que salió. DEJA SIN MARCAR "Allow write access":
   el servidor solo necesita leer.
```

```bash
# 3. De vuelta en el servidor
printf 'Host github.com\n  IdentityFile ~/.ssh/github\n' >> ~/.ssh/config
cd /opt/consultorio/infra/produccion
REPO=git@github.com:ancortez/consultorio.git ./enlazar-git.sh
```

No uses tu contraseña de GitHub ni la dejes escrita en el servidor: si
alguien entra al VPS, se lleva el acceso a todos tus repositorios. La
llave de despliegue solo abre este.

---

## Cómo se usa de aquí en adelante

### Subir cambios

**Sin git**, desde tu Mac:

```bash
./subir-al-servidor.command
```

**Con git**, haces `git push`. Después, en el servidor:

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

### ¿Se están haciendo?

Esa es la pregunta importante y la respuesta no debería requerir
mirar seis sitios. Un comando la contesta:

```bash
bash revisar-respaldos.sh
```

Dice, en una pantalla: si la tarea automática está puesta y a qué hora,
cuándo fue el último respaldo de la base y cuánto pesa, cuándo el de los
archivos del expediente, si van cifrados, si queda espacio para el
siguiente, qué dijo el último intento, y si existe una copia fuera del
servidor. Devuelve 0 si todo está bien y 1 si hay algo que atender, así
que también sirve para encadenarlo.

**Córrelo la primera vez en cuanto el servidor esté en pie.** El caso
que de verdad pasa no es que los respaldos fallen: es que la tarea
automática nunca se instaló —porque el servidor se montó a mano, o
porque `instalar-servidor.sh` se cortó a la mitad— y nadie se enteró,
porque el sistema funciona exactamente igual de bien sin respaldar.

Si falta la tarea, el propio guion la pone:

```bash
sudo bash revisar-respaldos.sh --instalar-cron
```

Después, con correrlo una vez al mes basta. `desplegar.sh` también avisa
si no encuentra la tarea.

### Desde el sistema: Configuración > Respaldos

Hay una pantalla para esto, y solo la ve el **administrador**. Desde ahí
se puede:

- **ver** lo que hay, con su fecha, su tamaño y si ya lo bajaste;
- **crear** un respaldo de la base en el momento;
- **descargar** cualquiera con un clic;
- **restaurar** uno de la base.

Dos cosas que esa pantalla *no* hace, y no son olvidos. No borra
respaldos: la rotación la sigue haciendo `respaldar.sh`, que sabe
cuántos conservar de cada tipo, y un botón de borrar al lado de uno de
restaurar es la clase de vecindad que acaba en un clic equivocado sobre
lo único que quedaba. Y no respalda los **archivos del expediente**:
pesan gigabytes y viven en el volumen de MinIO, al que el contenedor de
la aplicación no tiene acceso ni debe tenerlo. Esos se ven y se
descargan desde ahí, pero para hacer uno nuevo hay que correr
`./respaldar.sh --completo` en el servidor.

**Restaurar reemplaza TODOS los expedientes** por los de la fecha de ese
respaldo. Lo que se capturó después se pierde. Por eso pide escribir
`RESTAURAR` a mano —una confirmación de "¿seguro?" se contesta que sí
sin leer— y antes de tocar nada guarda un respaldo de lo que hay, cuyo
nombre te dice al terminar.

---

## Si se pierde el servidor

Es la pregunta importante y los respaldos de arriba no la contestan:
viven en el **mismo disco** que el sistema. Si el proveedor tiene un
problema serio, si una factura se queda sin pagar, si el disco muere, se
van los dos juntos. Un respaldo al lado de lo que respalda no es un
respaldo, es una copia.

Lo que lo arregla es tener las copias en otro sitio físico, y que no
dependa de acordarse. Para eso hay un guion que corre en **tu
computadora**:

```bash
./bajar-respaldos.command              # baja lo que falte, ahora
./bajar-respaldos.command --instalar   # que corra solo cada día a las 20:30
./bajar-respaldos.command --ver        # ¿está puesto? ¿qué hay bajado?
./bajar-respaldos.command --quitar     # dejar de correr solo
```

Las copias quedan en `~/Respaldos-Nanai`, fuera de la carpeta del
proyecto: el proyecto está en git y un respaldo con el expediente
clínico completo no puede acabar en un repositorio por un `git add -A`
distraído.

### Por qué la computadora JALA y el servidor no EMPUJA

Es la decisión de fondo de todo esto. Si el servidor empujara las copias
hacia tu Mac, necesitaría una llave para entrar en ella. Entonces quien
tome el control del servidor —y es la máquina expuesta a internet— entra
también en tu computadora, y lo primero que hace cualquier programa de
secuestro de datos es buscar los respaldos y cifrarlos. Las copias de
fuera dejarían de ser copias de fuera.

Jalando, la llave vive en tu Mac y apunta hacia el servidor. El servidor
no sabe que tu computadora existe y no puede tocar nada de este lado. Lo
peor que puede pasar es que un día no haya nada que bajar.

Para que corra sin pedir contraseña hace falta una llave ssh. Si nunca
la has puesto:

```bash
ssh-keygen -t ed25519        # Enter a todo
ssh-copy-id root@TU_IP
```

**Y un paso más que ningún guion puede hacer por ti:** dos copias en la
misma casa son una sola copia frente a un robo o un incendio. Arrastra
`~/Respaldos-Nanai` a un disco externo o a tu nube de vez en cuando.

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
| El navegador ni siquiera conecta (dominio `.dev`) | Ver la nota de abajo | Espera a que Caddy emita el certificado y recarga |
| "No encontré el sistema" al instalar | El proyecto no está en `/opt/consultorio` | Corre `./subir-al-servidor.command` desde tu Mac |
| Arreglas algo, lo subes, y **el error es idéntico** | El archivo no llegó a su carpeta | Fíjate en la `Version:` que imprime el instalador al arrancar. Si no es la de tu Mac, usa `./subir-al-servidor.command` |
| El sitio no carga | Algo se cayó | `docker compose -f docker-compose.produccion.yml ps`. Lo que no diga "Up", revisa sus logs. |
| Error 500 | Falla de PHP | `docker compose -f docker-compose.produccion.yml logs --tail 50 app` |
| No suben documentos | MinIO caído | `cd ../minio && docker compose -f docker-compose.produccion.yml ps` |
| `pull access denied for minio/minio` | Docker Hub borró esas imágenes en sept. 2026 | Ya está corregido: el compose apunta a `quay.io`. Ver `docs/almacenamiento-de-archivos.md` |
| `container consultorio_db is unhealthy` en el primer arranque | MySQL está armando las 69 tablas y todavía no termina | No falló: le faltó tiempo. Espera 2 minutos y `docker compose -f docker-compose.produccion.yml up -d` |
| `Permission denied` sobre un archivo que sí existe | Ver la nota de abajo | Vuelve a correr el instalador, o el arreglo de mano |
| Todo va lento | Poca RAM | `free -h` y `docker stats`. Si la RAM está al tope, sube de plan. |

### "Permission denied" sobre un archivo que está perfectamente ahí

El síntoma es desconcertante porque el mensaje señala un archivo que
puedes ver con `ls`:

```
Warning: require_once(/var/www/html/includes/core/bootstrap.php):
Failed to open stream: Permission denied
```

La cadena es esta. En tu Mac, los archivos del proyecto suelen quedar
con permiso `600`: solo el dueño los lee. Al subirlos, `rsync -a`
**conserva** esos permisos, porque `-a` incluye `-p`. Del otro lado, el
servidor web corre como un usuario distinto (`www-data`), que no es el
dueño: no puede abrir ni `index.php`. Un `chown` no alcanza, porque el
problema no es *de quién* es el archivo sino que **nadie más puede
leerlo**.

Ya está resuelto en dos lugares: el guion de subida normaliza los
permisos al subir (`--chmod=D755,F644`) y el instalador los vuelve a
normalizar al instalar, sin importar cómo llegaron. Si te lo topas en
un servidor que ya estaba armado, el arreglo de mano:

```bash
cd /opt/consultorio
sudo find . -type d -exec chmod 755 {} +
sudo find . -type f -exec chmod 644 {} +
sudo chmod +x infra/produccion/*.sh
sudo chmod 600 infra/produccion/.env infra/minio/.env
sudo chown -R 33:33 storage && sudo chmod -R 775 storage
```

No hace falta reiniciar nada: el código está montado desde el disco, así
que el cambio se ve al recargar la página.

Las dos líneas del final tienen su razón. Los `.env` vuelven a `600`
porque el `chmod` general los dejó legibles para todos, y ahí viven las
contraseñas de la base. Y `storage/` se le da a `33:33` porque es la
única carpeta donde la aplicación **escribe**, y quien escribe ahí es el
servidor web, no tú.

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
