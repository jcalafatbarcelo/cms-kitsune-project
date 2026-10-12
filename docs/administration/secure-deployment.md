# Despliegue seguro

## Objetivo

Este documento describe el transporte seguro obligatorio del CMS y la
configuración de proxies y cookies necesaria antes de exponer tráfico. Está
dirigido a responsables de despliegue. Los valores concretos se obtienen del
gestor de secretos del entorno y no se versionan.

## HTTPS en toda la web

`local` y `testing` permiten HTTP para facilitar el desarrollo sin certificados.
En cualquier otro entorno la aplicación aplica esta política a **todas** las
rutas, incluidas las públicas y el backoffice:

| Método entrante por HTTP | Respuesta |
| :--- | :--- |
| `GET` y `HEAD` | `308` a la misma URL en HTTPS, conservando ruta y query. |
| Cualquier otro método | `400`, sin procesar cuerpo ni credenciales. |

El `308` conserva el método, por lo que nunca se usa para peticiones con cuerpo:
un `POST` con credenciales se rechaza antes de leer su contenido. La autoridad de
destino del redirect procede de `APP_URL`, no del `Host` recibido.

`APP_URL` debe ser el host HTTPS canónico del sitio. Configurarlo con el valor
real del entorno fuera de Git.

## Cookies de sesión

Fuera de `local` y `testing`, la aplicación fuerza estos atributos aunque la
configuración intente desactivarlos:

```dotenv
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
```

`Secure` impide enviar la cookie por HTTP, `HttpOnly` la oculta a JavaScript del
navegador y `SameSite=Lax` limita su envío en peticiones cruzadas. Mantener la
coherencia de la política de sesión del
[ADR-0003](../adr/ADR-0003-baseline-moderna-de-bases-de-datos.md) no es
suficiente por sí solo: estos atributos son parte del contrato de seguridad del
backoffice.

## Proxies de confianza

Si un proxy o balanceador termina TLS, declarar únicamente sus direcciones:

```dotenv
TRUSTED_PROXIES=192.0.2.10,2001:db8::/64
```

Reglas:

- La lista admite IP y rangos CIDR explícitos y separados por comas.
- Si está vacía, no se confía en ninguna cabecera `X-Forwarded-*`.
- Una entrada inválida no se acepta nunca: la primera petición responde `500` en
  lugar de confiar implícitamente. Corregir el valor y reiniciar; la aplicación
  no valida `TRUSTED_PROXIES` en el arranque.
- El proxy debe sanear `X-Forwarded-For`, `X-Forwarded-Proto` y
  `X-Forwarded-Port`; nunca reenviar valores enviados por el cliente.
- No se admite confiar en todos los proxies ni inferirlos por el hostname.
- Los rangos universales `0.0.0.0/0` y `::/0` se rechazan; no representan un
  proxy concreto.

Confiar en un proxy incorrecto permite falsear el esquema o la IP de origen, lo
que afecta a la redirección HTTPS y al límite de intentos de acceso.

## Orden de puesta en marcha

```shell
php artisan migrate --force
php artisan cms:template:sync
php artisan cms:admin:create
php artisan config:cache
```

`cms:template:sync` es obligatorio tras modificar el `template.json` de un
template desplegado; sin él, la resolución de presentaciones falla y toda la web
responde `503`. Crear el superadministrador antes de publicar `/admin` y ejecutar
la validación de catálogos si el despliegue cambia textos estáticos.

## Locks del límite de acceso

El límite de cinco fallos de acceso por minuto usa locks atómicos del store de
caché configurado. En `production`, `CACHE_STORE` no puede usar los drivers
`array`, `null`, `session` ni `failover`; el store debe compartir locks entre
todos los procesos PHP que sirven el backoffice. `failover` se rechaza porque su
respaldo puede degradar a un store local y dejar de compartir locks. `database` y
`file` siguen siendo válidos en hosting común de un solo host. La topología de un
solo host o el almacenamiento compartido corresponde al despliegue.

El lock de admisión dura al menos 60 segundos y se amplía cinco segundos sobre
el límite finito `max_execution_time` de PHP cuando este es mayor. El responsable
operativo debe configurar un límite finito para las peticiones PHP: la aplicación
rechaza procesar un login en `production` si es ilimitado. Esta comprobación no
se aplica a Artisan, cuyo tiempo de ejecución no representa una petición HTTP.

Antes de abrir `/admin`, el responsable de despliegue debe verificar esa
propiedad desde dos procesos independientes con la misma configuración efectiva.
En una topología con varias instancias, cada proceso debe ejecutarse en una
instancia distinta. En el primero, ejecutar:

```shell
php artisan admin:verify-login-rate-limit-lock --hold=10
```

Mientras informa que retiene el lock, ejecutar en el segundo:

```shell
php artisan admin:verify-login-rate-limit-lock
```

El primer proceso debe terminar con código `0` e informar que adquirió y liberó
el lock. El segundo debe terminar con código `1` e informar que el lock ya está
retenido. Cualquier otro resultado impide abrir el backoffice hasta corregir la
configuración de caché. Sin `--hold`, el comando adquiere y libera el lock de
verificación y termina con código `0`, o termina con código `1` si otro proceso
lo retiene. `--hold` acepta de 0 a 30 segundos. El comando no autentica usuarios
ni conserva datos de acceso. La condición de cierre es registrar ambos comandos,
sus códigos y las instancias desde las que se ejecutaron en la evidencia del
despliegue; la prueba SQLite local no acredita una topología multiinstancia.

## Modo diagnóstico

`CMS_ERROR_DETAILS` puede mostrar detalle de excepciones 5XX en entornos de
prueba. En `production` se ignora, pero no debe dejarse activada. Mientras está
activa, el panel muestra un aviso persistente. Véase
[Detalle de errores de diagnóstico](diagnostic-error-details.md).

Consultar [Instalación](../getting-started/installation.md) y
[Primer superadministrador](first-superadministrator.md).

## Comprobaciones

- `curl -I http://<host>/admin/login` responde `308` hacia `https://`.
- `curl -I https://<host>/admin/login` responde `200` con el formulario de acceso.
- Un `POST` por HTTP recibe `400` sin procesar credenciales.
- La cookie de sesión observada en HTTPS incluye `Secure`, `HttpOnly` y
  `SameSite=Lax`.
- Un `TRUSTED_PROXIES` inválido provoca `500` en la primera petición; la
  aplicación no valida la lista en el arranque.
- El preflight de locks ejecutado desde dos procesos obtiene los códigos `0` y
  `1` esperados antes de abrir el backoffice.
