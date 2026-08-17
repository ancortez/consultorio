# MinIO compartido

Almacenamiento de archivos para todos tus sistemas. Se levanta **una sola vez**
y cada sistema guarda en su propio bucket.

Esta carpeta es autocontenida: puedes moverla a `~/Proyectos/minio` y sigue
funcionando igual.

## Primera vez

```bash
docker network create sistemas
```

Después, doble clic en `iniciar-minio.command`. El script crea la red solo si
no existe, así que si se te olvida el comando de arriba tampoco pasa nada.

| Qué | Dónde |
|---|---|
| Consola web | http://localhost:9101 |
| API (desde tu Mac) | http://localhost:9100 |
| API (desde otro contenedor) | http://minio:9000 |

Usuario y contraseña salen del `.env`.

## Agregar otro sistema

1. Añade su bucket a `MINIO_BUCKETS` en el `.env`, separado por comas.
2. Vuelve a correr `iniciar-minio.command`.
3. En el `docker-compose.yml` de ese sistema, cuelga el servicio de la red:

```yaml
services:
  app:
    networks: [predeterminada, sistemas]

networks:
  predeterminada:
  sistemas:
    external: true
```

4. Y pásale estas variables de entorno:

```yaml
MINIO_ENDPOINT: http://minio:9000
MINIO_BUCKET:   nombre-de-su-bucket
MINIO_LLAVE:    minioadmin
MINIO_SECRETO:  minioadmin123
```

## Por qué dos redes

El sistema se queda en su red propia para hablar con su base de datos, y se
cuelga además de `sistemas` solo para alcanzar MinIO. Así los sistemas no se
ven entre ellos: el consultorio no puede tocar la base de datos de otro
proyecto solo por compartir el almacenamiento.

## Antes de producción

- [ ] Cambiar usuario y contraseña en el `.env`
- [ ] Crear una llave de acceso por sistema en la consola, en vez de usar
      la cuenta raíz para todo
- [ ] Respaldo del volumen `minio_data_compartido`
- [ ] TLS si MinIO va a salir de la máquina
