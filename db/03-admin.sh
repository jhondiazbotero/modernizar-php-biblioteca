#!/bin/bash
# Crea el bibliotecario inicial con la clave de DEMO_PASSWORD (md5, como el resto del sistema)
set -e

clave=$(printf '%s' "$DEMO_PASSWORD" | md5sum | cut -d' ' -f1)

mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" <<SQL
INSERT INTO bibliotecarios (usuario, clave, nombre) VALUES ('admin', '$clave', 'Administrador');
SQL
