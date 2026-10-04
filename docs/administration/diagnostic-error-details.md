# Detalle de errores de diagnóstico

## Objetivo

`CMS_ERROR_DETAILS` permite ver el detalle de las excepciones 5XX durante el
desarrollo y las pruebas, y recordar en el panel que ese modo está activo. No
sustituye a un servicio de observabilidad ni debe usarse en un sitio público.

## Comportamiento

| `CMS_ERROR_DETAILS` | Entorno | Respuesta 5XX |
| :--- | :--- | :--- |
| `false` (por defecto) | Cualquiera | Página de error genérica. |
| `true` | `local`, `testing` u otro no productivo | Página de diagnóstico con estado, clase, mensaje, `archivo:línea` y traza. |
| `true` | `production` | Se ignora: página genérica y una advertencia como máximo una vez por hora. |

La página de diagnóstico se muestra a cualquier petición, incluido el login, para
poder localizar fallos anteriores a la autenticación. No renderiza variables de
entorno, `APP_KEY`, cookies, cabeceras, cuerpo de la petición ni argumentos de las
llamadas de la traza.

## Activar el modo

```dotenv
CMS_ERROR_DETAILS=true
```

Guardar la configuración y limpiar la caché:

```shell
php artisan config:clear
```

Mientras esté activo, el panel de administración muestra el aviso persistente
`Modo diagnóstico activo`. No activarlo nunca en producción.

## Desactivar el modo

```dotenv
CMS_ERROR_DETAILS=false
```

El aviso desaparece del panel y las respuestas 5XX vuelven a ser genéricas.

## Caso habitual: 503 por manifiesto sin sincronizar

Si se modifica un manifiesto `template.json` de un template desplegado sin
re-sincronizar, la resolución de presentaciones falla y el sitio responde `503`.
Además de activar el detalle de errores, el paso correcto es:

```shell
php artisan cms:template:sync
```

Este comando actualiza el hash del manifiesto registrado. Ejecutarlo tras cada
cambio de `template.json` o de sus presentaciones declaradas.

## Límites

- No agrupa, alerta ni retiene errores: no es un sistema de observabilidad.
- No redacta automáticamente datos que una excepción incluya en su mensaje.
- No habilita `APP_DEBUG` ni muestra variables de entorno.
- La opción de usar este modo en producción se decidirá en una Spec futura con
  controles adicionales.
