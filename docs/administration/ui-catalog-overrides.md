# Administración de overrides de UI catalogs

## Alcance disponible

Core permite sustituir o aportar la traducción de una clave válida de un
`UI catalog` mediante un valor persistido. En esta fase, la gestión se realiza
exclusivamente con Artisan; todavía no existe pantalla de backoffice, autorización
web ni historial de auditoría para overrides. Estas capacidades se retomarán en
`LOC-04` después de completar la auditoría administrativa.

Los propietarios admitidos en este incremento son Core y los CMS Templates
registrados en `cms_templates`. Los módulos custom quedan fuera hasta que exista
su primer `UI catalog` y se defina su ciclo de vida. Un template debe distribuir
un `en.json` válido para poder ser propietario de overrides.

No se permite crear claves nuevas: toda clave debe existir en el `en` de su
propietario. `en.json` sigue siendo la autoridad de claves, placeholders y
pluralización. El producto solo distribuye `en`; los overrides pueden aportar
traducciones de locales no distribuidos, pero no crear claves.

## Vía de gestión

Artisan es la vía disponible en este incremento y la vía técnica de bootstrap,
automatización, CI y recuperación. No es la experiencia administrativa final: la
gestión ordinaria de idiomas y overrides se hará desde el backoffice cuando
LOC-04 esté disponible. Una futura API reutilizará los mismos servicios de
dominio, no ejecutará comandos Artisan.

## Preparar la base de datos

Los overrides necesitan dos tablas nuevas. Un despliegue existente debe aplicar
la migración antes de usarlos:

```shell
php artisan migrate --force
```

`ui_catalog_overrides` conserva los valores por locale y clave.
`ui_catalog_override_state` mantiene el interruptor global y la versión que
invalida la caché. La migración crea el estado habilitado con versión inicial.

## Precedencia

La resolución efectiva de una línea es:

```text
override(locale), si el interruptor está activo y el override es válido
-> UI catalog(locale)
-> UI catalog en, solo en modo base y cuando locale no sea en
-> clave literal
```

El modo `key` no consulta `en`. Un override puede aportar una traducción para un
locale que el propietario no distribuya. Los overrides de `en` también están
permitidos, pero no cambian el inventario ni las reglas de la línea base.

## Crear o actualizar un override

```shell
php artisan cms:ui-catalog:override:set "base::admin.login.title" es_ES --value="Acceso al panel"
```

`--value` es obligatorio. El comando no admite stdin, archivos, editor
interactivo ni importación masiva. Se valida antes de persistir:

- el locale debe tener formato válido y estar instalado; puede estar inactivo;
- la clave debe usar `<owner>::<group>.<item>` con propietario normalizado;
- el propietario debe ser `core` o un template registrado con `en.json` válido;
- la clave debe existir exactamente en el `en` del propietario;
- el valor debe ser un string UTF-8 no vacío, de hasta 16.384 bytes, sin
  caracteres de control Unicode (`\p{Cc}`), sin controles bidireccionales de
  embedding/override `U+202A`–`U+202E` y sin `<` ni `>`; las marcas direccionales
  legítimas y los isolates están permitidos;
- los placeholders deben coincidir con la línea `en`, sin distinguir la
  capitalización admitida por Laravel;
- la presencia de pluralización debe coincidir con la línea `en`; un valor plural
  debe incluir `:count` y segmentos válidos.

Una escritura inválida no persiste ninguna fila ni incrementa la versión. Si la
combinación ya existe, `set` actualiza `value` y `updated_by`, conserva
`created_by` y `created_at` e incrementa la versión global.

## Listar

```shell
php artisan cms:ui-catalog:override:list
php artisan cms:ui-catalog:override:list --locale=es_ES
php artisan cms:ui-catalog:override:list --owner=base
php artisan cms:ui-catalog:override:list --locale=es_ES --owner=base --with-values
```

La salida muestra metadatos y no imprime el valor salvo que se solicite
explícitamente `--with-values`. No se muestran secretos ni se leen valores desde
otras fuentes.

## Eliminar

```shell
php artisan cms:ui-catalog:override:remove "base::admin.login.title" es_ES
```

Elimina la fila existente e incrementa la versión. Si la fila no existe, el
comando falla con código distinto de cero.

## Activar y desactivar

```shell
php artisan cms:ui-catalog:override:disable
php artisan cms:ui-catalog:override:enable
```

El interruptor es global y persistente. Al desactivarlo se ignoran todos los
overrides, pero no se borra ninguna fila; al reactivarlo se recuperan. Si el
estado solicitado ya está activo, el comando lo informa y no incrementa la
versión. El cambio registra `updated_by` y `updated_at` en la fila de estado.

## Validar

```shell
php artisan cms:ui-catalog:override:validate
```

Comprueba todas las filas contra los catálogos actuales, informa de propietarios o
claves huérfanas y de valores incompatibles, y devuelve código distinto de cero
si encuentra algún problema. No modifica datos ni elimina filas automáticamente.
Los valores completos no se imprimen.

Una fila huérfana se conserva aunque su propietario o clave desaparezcan. La
resolución la ignora y la validación la reporta. Corregir la causa o eliminarla
explícitamente con `remove` es una decisión operativa. `remove` acepta la clave
exacta con validación mínima de longitud y caracteres de control, de modo que
también puede limpiar filas con claves malformadas que `set` rechazaría; no se
relaja la validación de `set`.

## Actores y caché

Cada mutación registra un snapshot histórico:

- `user:<id>` para el superadministrador autenticado en web;
- `console` para Artisan;
- `system` cuando no hay actor humano.

Un usuario web autenticado que no sea el superadministrador no puede mutar
overrides: la operación se rechaza y no persiste cambios. Los códigos no son
claves foráneas ni contienen nombre, correo u otros datos personales. `created_by`
se fija en el alta y no cambia.

La resolución consulta el estado global y cachea las líneas validadas por locale
con la versión, un fingerprint del `en` de los propietarios afectados y un TTL
acotado. El snapshot del catálogo del propietario se reutiliza mientras sus
metadatos de archivo no cambien; un proceso nuevo o `forget()` fuerza su
relectura. Si el propietario o la clave desaparecen, o cambian placeholders o
pluralización, el override se ignora en la siguiente resolución aunque la caché
persistente esté caliente y la versión global no cambie. Una entrada de caché
malformada se trata como ausente y se valida de nuevo desde el almacén. Una
mutación confirmada incrementa la versión en la misma transacción y el proceso
que mutó descarta su snapshot en memoria. Una versión anterior deja de consultarse
porque la clave de caché cambia. Un fallo de caché no impide resolver: se intenta
la lectura directa.

## Procesos de vida larga

La reutilización del snapshot del propietario se basa en `mtime` y tamaño del
`en.json`, además de una caché de directorios de template por instancia. El estado
global, la versión y las filas por locale también se memoizan en la instancia. Un
cambio del mismo tamaño dentro de la misma granularidad temporal del sistema de
archivos, un template desregistrado, una mutación de overrides o un cambio de
interruptor realizado en otro proceso pueden no observarse en un worker de vida
larga. Tras desplegar catálogos, modificar templates o mutar overrides desde otro
proceso, reiniciar los workers o ejecutar `forget()` o una limpieza equivalente.
En PHP-FPM cada petición crea una instancia nueva y no requiere este paso.

Cualquier futura ruta web debe autorizar al superadministrador antes de invocar
el manager de overrides. El código de actor `system` no es una autorización web:
queda reservado a contextos internos de confianza. La comprobación de
autorización no debe depender del orden interno de validación de un comando.

## Degradación y reversión

Si faltan las tablas o el almacén de overrides no está disponible, el sitio sigue
renderizando con los catálogos y registra un aviso acotado y deduplicado. Los
avisos incluyen clave, locale y propietario cuando aplica, pero no valores,
replacements, sesiones ni datos personales.

La reversión de la migración elimina ambas tablas y, con ellas, todos los
overrides. Es una pérdida de datos aceptada para este incremento: exportar o
respaldar los valores antes de revertir es responsabilidad de la operación.

## Comprobación multi-motor

Las migraciones, la unicidad, el interruptor, la versión y la resolución se
verifican en SQLite, MySQL 8.4 y MariaDB 11.4. Para un despliegue de producción
seguir la política de versiones de
[Baseline moderna de bases de datos](../adr/ADR-0003-baseline-moderna-de-bases-de-datos.md)
y ejecutar la matriz de integración definida en CI.
