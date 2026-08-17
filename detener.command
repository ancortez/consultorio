#!/bin/bash
# Apaga el sistema sin borrar la informacion.
cd "$(dirname "$0")" || exit 1

echo ""
echo "Apagando el Sistema Consultorio Dental..."
docker compose down
echo ""
echo "Listo. La informacion se conserva."
echo "Para volver a levantarlo: doble clic en iniciar.command"
echo ""
read -n 1 -s -r -p "Presiona cualquier tecla para cerrar..."
