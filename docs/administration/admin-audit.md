# Auditoría administrativa

## Alcance disponible

El historial de `/admin/audit` conserva de forma durable las mutaciones de
Pages y Navigation realizadas mediante sus comandos Artisan. Solo el único
superadministrador puede consultarlo. La pantalla es de solo lectura: no hay
edición, borrado, exportación ni API de eventos.

Cada evento registra la operación, entidad e identificador, instante, actor
histórico, origen y los campos auditables antes y después del cambio. La
retención no tiene caducidad automática en esta versión.

## Actor y privacidad

Una operación desde el backoffice se atribuye al superadministrador con
`user:<id>`. Una operación Artisan usa `console` y una operación sin actor
humano usa `system`. La auditoría conserva el identificador y esa etiqueta,
pero no el nombre ni el email del usuario. No existe clave foránea hacia
`users`, por lo que eliminar un usuario no altera los eventos históricos.

No se registran direcciones IP, cabeceras, contraseñas, hashes, tokens ni
contenido editorial innecesario.

## Consultar el historial

Iniciar sesión como superadministrador y abrir `/admin/audit`. Se puede filtrar
por operación, entidad y rango temporal. La paginación se limita a 100 eventos
por página.

Los datos mostrados proceden de una lista permitida: identificadores,
jerarquía, idioma, slug, título, publicación, posición, etiqueta y referencia a
Page. No se debe usar esta pantalla como mecanismo para investigar datos no
incluidos en ese contrato.

## Garantías operativas

La mutación de dominio y su evento se escriben en la misma transacción. Si no
se puede registrar el evento, la mutación se revierte. La tabla
`admin_audit_events` es insert-only: triggers de SQLite, MySQL y MariaDB
rechazan `UPDATE` y `DELETE`, incluso fuera de la aplicación. En producción, el
usuario de ejecución no debe disponer de permisos DDL que le permitan alterar o
eliminar esos triggers.

Aplicar las migraciones antes de ejecutar comandos de Pages o Navigation en un
despliegue existente:

```shell
php artisan migrate --force
```

Tras desplegar el cambio de Base, sincronizar el manifiesto para registrar la
presentación cerrada `system.admin.audit`:

```shell
php artisan cms:template:sync
```

La cobertura de idiomas, overrides, intentos denegados y futuras mutaciones web
requiere Specs posteriores.
