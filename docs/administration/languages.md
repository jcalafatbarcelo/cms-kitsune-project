# Administración de idiomas

## Alcance disponible

Core permite instalar manualmente idiomas ya desplegados, validarlos, activarlos,
desactivarlos y cambiar los predeterminados globales mediante Artisan. No existe
todavía interfaz de backoffice, descarga remota, subida ni instalación dinámica
de paquetes.

`en` se instala con las migraciones y nunca se desinstala. Debe existir
exactamente un idioma predeterminado para frontend y otro para backoffice; pueden
ser el mismo idioma o distintos, pero ambos deben estar instalados y activos.
Siempre debe quedar al menos un idioma activo, y un idioma predeterminado no
puede desactivarse.

## Consultar y validar

```shell
php artisan cms:language:list
php artisan cms:language:validate
php artisan cms:language:validate es_ES
```

La validación sin argumento comprueba todos los idiomas instalados. La salida
muestra el locale y el SHA-256 de los bytes validados, pero no imprime el
contenido completo del `UI catalog`.

## Instalar manualmente

1. Crear el `UI catalog` completo en
   `Modules/Core/Resources/lang/<locale>.json`.
2. Ejecutar `php artisan cms:language:validate en` para confirmar que el catálogo
   base sigue siendo válido.
3. Crear un manifiesto local como el siguiente:

```json
{
  "schema_version": 1,
  "locale": "es_ES",
  "name": "Spanish (Spain)",
  "native_name": "Español (España)",
  "text_direction": "ltr",
  "catalogs": {
    "core": true
  }
}
```

4. Instalarlo y validarlo:

```shell
php artisan cms:language:install /ruta/segura/es_ES-manifest.json
php artisan cms:language:validate es_ES
```

El idioma queda inactivo. Repetir la instalación falla sin modificar datos. El
manifiesto debe ser un archivo regular JSON de hasta 64 KiB y no puede ser un
enlace simbólico. El directorio de `UI catalogs` y los manifiestos preparados
para instalar deben admitir escritura solo de administradores de despliegue; no
se deben validar mientras otro proceso los sustituye.

## Activar y cambiar predeterminados

```shell
php artisan cms:language:activate es_ES
php artisan cms:language:set-default frontend es_ES
php artisan cms:language:set-default backoffice es_ES
```

Cada contexto conserva exactamente un predeterminado. Solo un idioma instalado y
activo puede convertirse en predeterminado, sustituyendo el anterior de ese
contexto. `context` admite exactamente `frontend` o `backoffice`; ambos contextos
pueden apuntar al mismo idioma o a idiomas distintos.

## Desactivar

```shell
php artisan cms:language:disable en
```

La operación se rechaza si el idioma ya está inactivo, es predeterminado en
algún contexto o dejaría el sistema sin idiomas activos. `en` puede quedar
inactivo después de mover ambos predeterminados, pero continúa siendo el
`UI catalog` base para el modo `base`.

## Diagnóstico

Los comandos exitosos terminan con código `0`; una entrada inválida, un catálogo
incompatible o una transición prohibida terminan con un código distinto de `0`
y un mensaje accionable.

Una clave ausente o un catálogo alterado no interrumpe por sí solo el renderizado:
el resolver usa `en` en modo `base` o devuelve la clave en modo `key`. El log
diagnóstico se deduplica e incluye clave, locale y propietario cuando aplica,
pero no valores traducidos, replacements, sesiones ni datos personales.

No corregir una validación editando datos de `languages` o `language_settings`
directamente. Restaurar un `UI catalog` completo, volver a validarlo y utilizar
los comandos de Core para modificar el estado.
