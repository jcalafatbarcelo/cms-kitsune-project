# Roadmap de localización

## Propósito y estado

Este documento conserva la secuencia prevista para idiomas, `UI catalogs`,
overrides y contenido localizado. Está dirigido a contributors del Core,
desarrolladores de extensiones y agentes que necesiten continuar el trabajo sin
mezclar incrementos ni presentar capacidades previstas como disponibles.

> [!IMPORTANT]
> El módulo Core ya proporciona el registro de idiomas, los predeterminados
> globales, los `UI catalogs` estáticos, sus prefijos URL y la operación mediante
> Artisan. LOC-02 añade overrides gestionados por Artisan en esta fase y resueltos
> en Core y CMS Templates; la gestión ordinaria de idiomas y overrides se
> incorporará al backoffice en LOC-04. El contenido editorial `rich-text` de Pages
> ya admite valores por traducción con fallback. La pantalla de backoffice para
> idiomas y overrides, su auditoría, Media localizada y PageBuilder traducible
> siguen pendientes.

Fuentes prescriptivas relacionadas:

- [SDD inicial](../context/SDD_Inicial.md): visión y reglas de negocio.
- [ADR-0002](../adr/ADR-0002-ui-catalogs-json-modulares.md): formato y propiedad
  de los `UI catalogs`.
- [ADR-0003](../adr/ADR-0003-baseline-moderna-de-bases-de-datos.md): motores,
  versiones mínimas y política de soporte de persistencia.
- [SPEC de fundación estática](../specs/SPEC-static-language-foundation.md):
  contrato completado del primer incremento.
- [SPEC de distribución solo en inglés](../specs/SPEC-english-only-ui-catalogs.md):
  corrección del alcance de catálogos distribuidos.
- [Roadmap de calidad](quality-roadmap.md): momento de evaluación de auditoría,
  seguridad y observabilidad.

Este roadmap ordena trabajo; no aprueba Specs ni sustituye sus criterios.

## Vocabulario

| Término | Significado |
| :--- | :--- |
| Locale instalado | Idioma conocido por el CMS, como `en` o `es_ES`. |
| `UI catalog` | Diccionario JSON clave-valor de textos estáticos de interfaz. |
| `UI catalog` base | `UI catalog` `en` obligatorio de cada propietario. |
| `UI catalog` localizado | `UI catalog` completo de un propietario para otro locale. |
| Propietario | Core, módulo o template que declara las claves. |
| Override | Valor persistido que sustituye o aporta una traducción de una clave base válida. |
| Paquete de idioma | Artefacto futuro que distribuye manifiesto y uno o más `UI catalogs`. |
| Contenido traducible | Datos editoriales de páginas, assets o PageBuilder; no es un `UI catalog`. |

En conversación puede usarse “catálogo” o “catálogo de interfaz”, pero la
documentación técnica empleará siempre `UI catalog`.

## Estado de avance

| ID | Incremento | Estado | Entrada | Resultado esperado |
| :--- | :--- | :--- | :--- | :--- |
| LOC-00 | Planificación y decisión de `UI catalogs` | Completado | Fundación modular disponible | ADR aceptado, vocabulario y secuencia documentados |
| LOC-01 | Fundación de idiomas estáticos | Completado | LOC-00 completado y Spec aprobada | Core, `en`, registro, predeterminados, JSON, fallback y Artisan |
| LOC-02 | Overrides | Completado | LOC-01 completado | Sustituciones y traducciones locales validadas, con actor y caché |
| LOC-03 | Auditoría administrativa | En curso: fundación de Pages y Navigation completada | Antes del primer backoffice mutable | Historial durable independiente de logs operativos |
| LOC-04 | Backoffice de idiomas y overrides | Pendiente | LOC-01, LOC-02 y garantías de LOC-03 | Gestión autorizada, protegida y auditable |
| LOC-05 | Selección temporal y negociación HTTP | Completado parcialmente | Registro de idiomas estable | Sesión y primera visita por navegador; cookie consentida diferida |
| LOC-06 | URL amigables, idioma e items de menú | Completado | Modelo de páginas y menús especificado | Rutas canónicas, precedencia URL/locale y árboles de Navigation localizados |
| LOC-07 | Contenido y PageBuilder traducibles | Parcial: `rich-text` de Pages disponible; PageBuilder pendiente | Entidades y schemas aprobados | Valores por locale e instancia, separados de la UI |
| LOC-08 | Paquetes dinámicos de idioma | Fuera del MVP/TFM | Distribución real y auditoría disponibles | Instalación segura sin ejecutar código del paquete |

Estados de LOC-01:

- [x] Fundación de `nwidart/laravel-modules` instalada y verificada.
- [x] Problema, fases y primer incremento seleccionados.
- [x] Idioma base `en` y predeterminados globales decididos.
- [x] Modos de fallback `base` y `key` decididos.
- [x] Contrato arquitectónico de `UI catalogs` registrado en ADR-0002.
- [x] Primera Spec creada y validada estructuralmente.
- [x] Revisión semántica y aprobación explícita de la Spec.
- [x] Baseline moderna de SQLite, MySQL y MariaDB decidida.
- [x] Implementación TDD de LOC-01.
- [x] Matriz CI multi-motor con verificación de versiones y restricciones.
- [x] Documentación de administración y extensibilidad basada en código real.
- [x] Cierre de criterios, suite, quality gate y estado `Completada`.

## Reglas transversales decididas

### Idioma base y predeterminados

- Inglés neutro `en` es el idioma base integrado y nunca se desinstala.
- `en` comienza activo y como predeterminado global de frontend y backoffice.
- Debe existir exactamente un predeterminado por contexto. Los predeterminados
  pueden coincidir o ser distintos, y siempre apuntan a idiomas activos.
- Un idioma predeterminado no puede desactivarse.
- Siempre debe permanecer al menos un idioma instalado y activo.
- `en` puede quedar inactivo si no es predeterminado y existe otro idioma activo;
  sigue disponible como `UI catalog` base cuando el modo de fallback es `base`.

### Propiedad y completitud de UI catalogs

- Un `UI catalog` pertenece a Core, a un módulo o a un template.
- Todo propietario declara en `en` la totalidad de sus claves.
- Las claves localizadas ausentes de `en` se ignoran y la validación las trata
  como error.
- Core proporciona un `UI catalog` completo para cada idioma que declare instalado.
- Un módulo o template puede distribuir solo `en`.
- Si un módulo o template declara otro locale, debe cubrir todas sus claves `en`.
- Un override futuro puede traducir claves válidas cuando el propietario no
  distribuya ese locale.

Los módulos usarán `Resources/lang`. Los templates deberán funcionar de forma
equivalente, registrar sus rutas en el traductor mediante su ciclo de carga y
conservar `Resources/lang` bajo su raíz. Una ruta conceptual es:

```text
<template-root>/<TemplateName>/Resources/lang/en.json
```

No se decide todavía si la raíz o el directorio llevarán prefijos como
`template_` o `tmp_`: esa convención pertenece a la futura arquitectura de
templates y no cambia el formato del `UI catalog`.

### Fallback

Modo `base`:

```text
override del locale
-> UI catalog del locale
-> UI catalog en
-> clave literal
```

Modo `key`:

```text
override del locale
-> UI catalog del locale
-> clave literal
```

Los overrides no existían en LOC-01. LOC-02 incorpora la primera precedencia
documentada; el valor por defecto será `base` en producción y `key` en desarrollo,
testing y E2E; una configuración explícita válida podrá cambiarlo en cualquier
entorno.

## Detalle de incrementos

### LOC-02: overrides

`SPEC-ui-catalog-overrides` definió e implementó este incremento. En esta fase, la
gestión disponible es exclusivamente por Artisan, como vía técnica de bootstrap,
automatización y recuperación; la pantalla, la autorización y la auditoría de
overrides se retomarán en LOC-04 después de completar LOC-03.

El producto distribuye solo el catálogo `en` de Core y del template Base. Los
catálogos de otros locales son artefactos de despliegue o fixtures de prueba. Un
paquete de idioma debe aportar el catálogo Core del locale, y un template puede
aportar los suyos; el manifiesto actual no instala catálogos de templates.

La Spec de overrides definió:

- clave con propietario y locale instalado;
- referencia obligatoria a una clave existente en el `UI catalog` `en`;
- validación de placeholders, pluralización, UTF-8, tamaño y contenido;
- precedencia sobre el `UI catalog` localizado;
- interruptor global que permita ignorarlos sin borrarlos;
- invalidación de caché tras commit;
- código del usuario creador y del último modificador, almacenados sin claves
  foráneas hacia `users`;
- actor `console` o `system` cuando no exista usuario autenticado;
- comportamiento al desactivar o retirar el propietario: la fila se conserva, se
  ignora en resolución y la validación la reporta como huérfana.

Estados de LOC-02:

- [x] Tablas, restricciones, modelos y validación de escritura.
- [x] Resolución en Core y CMS Templates con modos `base` y `key`.
- [x] Interruptor global, versión, caché y degradación segura.
- [x] Actores históricos y comandos Artisan de alta, baja, listado, validación y
      conmutación.
- [x] Documentación de administración, desarrollo y changelog.
- [x] Pruebas enfocadas, suite afectada, formato, frontend y matriz de motores.
- [x] Auditoría independiente y cierre formal del estado de la Spec.

Los códigos de actor son snapshots históricos y no deben reutilizarse. Antes de
implementar borrado de usuarios se comparará conservar el snapshot en auditoría
con mantener un registro mínimo de identificadores eliminados. No se duplicarán
datos personales ni se fijará retención sin una necesidad aprobada.

### LOC-03: auditoría administrativa

Los campos de creador y modificador no sustituyen un historial. Antes de las
primeras pantallas administrativas mutables se especificará una auditoría durable
que cubra, como mínimo:

- instalación, activación y desactivación de idiomas;
- cambios de predeterminados;
- creación, modificación y eliminación de overrides;
- activación global de overrides;
- intentos denegados sobre idioma base o predeterminados;
- actor, origen, operación, entidad y momento;
- retención, minimización, acceso y tratamiento de actores eliminados.

La auditoría no dependerá de logs operativos rotatorios ni de claves foráneas que
puedan borrar o bloquear el histórico.

LOC-01 permite mutaciones por Artisan antes de implementar LOC-03. Se acepta que
esas operaciones privilegiadas solo tengan logs operativos y no se reconstruirá
su historial con un backfill ficticio. La auditoría durable comenzará cuando se
instale LOC-03 y será obligatoria antes de exponer mutaciones en el backoffice.

Un intento denegado es un evento de seguridad y puede auditarse sin estado
anterior/posterior. La mutación y su evento durable compartirán transacción. Si
la auditoría reside en otro almacenamiento, la transacción de negocio persistirá
un outbox o handoff durable con entrega idempotente y reintentos. Una transacción
fallida no debe registrarse como cambio realizado.

`SPEC-admin-audit-foundation` concreta el mecanismo de auditoría y lo aplica a las
mutaciones de Pages, Menus y publicación; la cobertura de idiomas, overrides e
intentos denegados se completará con LOC-04.

### LOC-04: backoffice de idiomas y overrides

LOC-04 requerirá una Spec propia y no está implementado. Su entrada exige LOC-01,
LOC-02 y las garantías de auditoría de LOC-03. El objetivo es trasladar la gestión
ordinaria de idiomas y overrides al backoffice sin convertir Artisan en la vía
administrativa final.

Alcance previsto:

- pantallas autorizadas para idiomas y overrides, con CSRF y superadministrador;
- auditoría durable de cada mutación e intento denegado;
- uso de los mismos servicios de dominio, sin ejecutar comandos Artisan desde la
  web;
- mensajes de presentación en `base::admin.languages.*` y
  `base::admin.overrides.*`;
- mensajes de resultado de dominio en `core::language.*`;
- mapeo controlado de excepciones a claves estables y mensaje genérico para
  errores desconocidos;
- reutilización de `core::language.installed`;
- pruebas de resolución en `en` y `es_ES`, fallback y errores;
- Artisan conservado para bootstrap, automatización, CI y recuperación.

Fuera de alcance: API pública, roles granulares, propietarios de módulos y paquetes
dinámicos de idioma.

### LOC-05: selección temporal y navegador

`SPEC-public-localized-routes` completó la selección por sesión y la negociación
de `Accept-Language` limitada a `/`. La sesión guarda solo un locale interno
activo; URL explícita, sesión, navegador y predeterminado aplican esa precedencia.
Las redirecciones temporales no son compartibles por caché. La cookie persistente
sigue diferida y requerirá una Spec de consentimiento.

No se añadirá una preferencia de locale al perfil persistente de un usuario
registrado dentro del TFM. La selección será propia del navegador:

- Si la política aplicable permite una cookie persistente y el visitante la
  acepta, recordar la selección durante un plazo configurable y razonable.
- Sin esa aceptación, conservarla en la sesión del servidor. La futura Spec
  decidirá si el identificador técnico de esa sesión usa una cookie de sesión y
  cómo se integra con la política de cookies aplicable.
- Validar siempre que el locale recordado continúa instalado y activo.
- En la primera visita sin elección previa, poder negociar `Accept-Language` y
  usar la mejor coincidencia solo si está instalada y activa.
- Si no existe coincidencia válida, usar el predeterminado global del frontend.
- Tratar una elección explícita posterior como superior a la detección automática.

La Spec HTTP decidirá la precedencia definitiva. La candidata que debe evaluarse
es: locale explícito de URL, elección persistente válida, sesión de servidor válida,
negociación del navegador solo en primera visita y predeterminado global. También
deberá resolver redirecciones, caché HTTP, SEO, privacidad y consentimiento.

### LOC-06 y LOC-07: URL y contenido

`SPEC-public-localized-routes` completó las rutas jerárquicas de Pages y sus
prefijos canónicos. `SPEC-navigation-foundation` completó los árboles visuales
localizados, sin fallbacks editoriales y sin permitir que Navigation construya
URLs o decida el locale HTTP.

La localización de frontend usa URLs amigables e items de menú. El idioma
predeterminado de frontend no lleva prefijo y los idiomas secundarios usan sus
prefijos canónicos, según la
[SPEC-public-localized-routes](../specs/SPEC-public-localized-routes.md).

`SPEC-pages-backoffice` trasladó la selección de home a un ítem de menú (una por
idioma) y eliminó la auto-publicación de la primera traducción. El contenido
editorial `rich-text` aplica fallback de contenido por clave reutilizando el modo
global de `UI catalogs` y el idioma predeterminado de frontend, sin convertir las
claves editoriales en claves de interfaz.

El contenido `rich-text` de Pages ya está disponible por `PageTranslation` y
aplica el fallback descrito. PageBuilder no forma parte de ese estado: sus
bloques, schemas, valores por instancia y Media localizada siguen pendientes de
sus propias Specs.

El contenido de páginas y PageBuilder se mantendrá separado de los `UI catalogs`.
Una plantilla del PageBuilder define claves de campos; cada instancia mantiene
sus propios valores por locale. Compartir una clave de campo no comparte el
contenido entre instancias.

### LOC-08: paquetes dinámicos

La futura instalación desde el CMS queda fuera del MVP/TFM. Antes de habilitarla
deberán existir autorización, auditoría, gestión del ciclo de vida y un formato
versionado. El instalador deberá validar schema, compatibilidad, integridad,
tamaño, rutas y propietarios; impedir path traversal y archivos ejecutables; y
aplicar instalación atómica con rollback.

## Cómo reanudar el trabajo

Antes de continuar cualquier incremento:

1. Verificar el estado real en código, Git, pruebas, Specs y ADR; no inferirlo de
   esta tabla únicamente.
2. Seleccionar una sola fila `LOC-*` cuya entrada esté satisfecha.
3. Leer solo la Spec y los ADR relacionados con ese incremento.
4. Realizar clash check contra SDD, Specs, ADR, código y documentación vigentes.
5. Crear o actualizar la Spec y obtener aprobación antes de modificar
   comportamiento funcional.
6. Mantener esta tabla y los checkboxes en el mismo cambio que altere el estado.
7. No marcar un incremento como completado hasta cerrar aceptación, pruebas,
   documentación y quality gate.

El siguiente paso de la línea de localización es cerrar LOC-03 y abordar LOC-04,
que requiere las garantías de auditoría y las dependencias ya satisfechas por
LOC-01 y LOC-02. La implementación de overrides no autoriza por sí sola su
exposición web ni la auditoría durable. La secuencia independiente de CMS
Templates, Pages, navegación y PageBuilder se coordina en el
[roadmap de contenido, templates y navegación](content-delivery-roadmap.md) y no
altera por sí misma el estado de ninguna fila `LOC-*`.
