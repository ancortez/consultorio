# El almacenamiento de archivos: qué pasó con MinIO y qué sigue

Sistema Consultorio Dental — 22/09/2026

---

## Lo que pasó

El sistema guarda las radiografías, las fotos clínicas y los escaneos
firmados en **MinIO**, un almacén de archivos que habla el protocolo S3
de Amazon pero corre en tu propio servidor.

Durante la instalación apareció este error:

```
pull access denied for minio/minio,
repository does not exist or may require 'docker login'
```

No es un error de configuración. Es que **MinIO borró sus imágenes de
Docker Hub el 11 de septiembre de 2026**. Por eso en tu computadora
seguía funcionando —ya tenías la imagen bajada de antes— y en un
servidor nuevo falla.

Y hay algo más grande detrás. La secuencia completa:

| Cuándo | Qué pasó |
|---|---|
| Mediados de 2025 | Quitan la consola de administración de la edición comunitaria |
| Octubre 2025 | Dejan de publicar binarios e imágenes oficiales |
| Diciembre 2025 | El repositorio pasa a "modo mantenimiento" |
| Febrero 2026 | Pasa a "sin mantenimiento" |
| Abril 2026 | El repositorio queda **archivado**, solo lectura |
| Septiembre 2026 | Borran las imágenes de Docker Hub |

MinIO movió su esfuerzo a **AIStor**, su producto comercial de pago. La
edición comunitaria sigue siendo código abierto bajo AGPLv3 y sigue
funcionando, pero **no va a recibir más arreglos, incluidos los de
seguridad**.

---

## El parche: quay.io

MinIO todavía publica en `quay.io`, que es otro registro de imágenes.
Cambiar de origen es una línea y te deja trabajando hoy:

```yaml
image: ${MINIO_IMAGEN:-quay.io/minio/minio:latest}
```

Ya está hecho en los dos archivos (`infra/minio/docker-compose.yml` y
`docker-compose.produccion.yml`).

### Fija la versión

Deja de usar `:latest`. Con `:latest`, un reinicio cualquiera puede
traerte una versión distinta a la que probaste, un martes a las tres de
la tarde, sin que nadie haya tocado nada.

Para ver qué versiones hay, **desde tu servidor**:

```bash
curl -s 'https://quay.io/api/v1/repository/minio/minio/tag/?limit=10&onlyActiveTags=true' \
  | python3 -c 'import sys,json; [print(t["name"]) for t in json.load(sys.stdin)["tags"]]'
```

Y pon la que elijas en `infra/minio/.env`:

```
MINIO_IMAGEN=quay.io/minio/minio:RELEASE.2025-XX-XXTXX-XX-XXZ
MINIO_IMAGEN_MC=quay.io/minio/mc:RELEASE.2025-XX-XXTXX-XX-XXZ
```

---

## Por qué esto es un parche y no la solución

Apuntar a quay.io te resuelve hoy. Pero el software que vas a correr:

- No va a recibir parches de seguridad.
- Va a quedarse atrás en compatibilidad conforme cambien las cosas
  alrededor.
- Depende de que MinIO mantenga quay.io abierto, cosa que nadie les
  obliga a hacer.

Para un sistema que va a guardar **expedientes clínicos de menores de
edad durante años**, eso no es una situación en la que quieras quedarte
indefinidamente. No es urgente —lo que corre, corre— pero tiene fecha.

---

## La salida: Garage

La buena noticia es que cambiarlo va a ser más fácil de lo que parece,
por una decisión que se tomó al principio del proyecto por otra razón.

El cliente de almacenamiento del sistema (`includes/core/almacen.php`)
**implementa la firma AWS Signature V4 a mano**, sin usar el SDK de
Amazon ni Composer. Eso se hizo para no meter dependencias en un
proyecto que corre sobre PHP y Apache a secas.

El efecto secundario es que el sistema no habla "con MinIO": habla **el
protocolo S3**. Cualquier almacén que entienda S3 le sirve igual.

### Qué se recomienda

| Opción | Cómo es | Para este caso |
|---|---|---|
| **Garage** | Un solo binario, escrito en Rust, ligero. Pensado justo para instalaciones chicas. | **La recomendada.** Un consultorio maneja miles de archivos, no miles de millones. |
| **SeaweedFS** | Maduro, doce años, cientos de colaboradores. Resuelve bien el problema de miles de millones de archivos pequeños. | Sólido, pero sobredimensionado aquí. |
| **Ceph** | De escala empresarial. | Demasiado para un servidor de 8 GB. |
| **Cloudflare R2** | Administrado, sin costo de salida de datos. | Sacaría los expedientes de tu servidor. Implicaciones legales que revisar antes. |

### Qué implicaría el cambio

1. Cambiar el `docker-compose.yml` de `infra/minio/` por uno de Garage.
2. Crear el bucket y las credenciales.
3. Poner las credenciales nuevas en los dos `.env`.
4. Copiar los archivos de un almacén al otro (`mc mirror` o `rclone`).
5. Probar que un documento del expediente se vea.

**Nada de código PHP cambia.** El endpoint, la llave y el secreto ya
son variables de entorno.

Es un trabajo de una tarde, no de una semana. Cuando quieras hacerlo,
dime y lo preparamos con calma: se puede montar Garage al lado, copiar
los archivos, comprobar que todo se ve, y recién entonces apagar MinIO.
Sin ventana de caída.

---

## Mientras tanto

Lo que corre hoy, corre. MinIO no se va a apagar solo. Pero:

- **Fija la versión** en el `.env`, en lugar de `:latest`.
- **Los respaldos importan más que antes.** Ya están hechos y se pueden
  probar con `./restaurar.sh --probar`.
- **No expongas la consola de MinIO a internet.** Es software sin
  parches; entre menos superficie tenga a la calle, mejor. Por eso el
  instalador la deja detrás del túnel SSH por omisión.

---

Sources:

- [MinIO's community edition is archived — what still runs in 2026](https://stormdevelopments.ca/blog/minio-s-community-edition-is-archived-what-still-runs-in-2026/)
- [MinIO Ends Community Development, Positions AIStor as the Future](https://faun.dev/co/news/devopslinks/minio-ends-community-development-positions-aistor-as-the-future/)
- [Self-hosted S3 after MinIO: lightweight alternatives for 2026](https://productimpossible.com/articles/self-hosted-s3-after-minio/)
- [MinIO Alternatives Compared: RustFS, SeaweedFS, and Garage](https://lowcloud.io/en/blog/minio-alternatives)
