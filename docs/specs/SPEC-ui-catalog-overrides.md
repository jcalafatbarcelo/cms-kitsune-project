# SPEC: Overrides de UI catalogs

- **Estado:** Completada
- **Perfil:** feature
- **Origen de la planificación:** Incremento LOC-02 del
  [roadmap de localización](../architecture/localization-roadmap.md), seleccionado
  tras completar LOC-01, las rutas públicas localizadas y el backoffice de Pages y
  Menus.
- **Spec relacionada:** [SPEC-static-language-foundation](SPEC-static-language-foundation.md),
  [SPEC-template-foundation](SPEC-template-foundation.md),
  [SPEC-template-presentation-resolution-foundation](SPEC-template-presentation-resolution-foundation.md)
  y [SPEC-admin-audit-foundation](SPEC-admin-audit-foundation.md), completadas.

## 1. Objetivo o problema

Entregar el primer incremento de overrides de `UI catalogs`: permitir que un valor
persistido sustituya o aporte la traducción de una clave válida para un locale
instalado, con validación estructural contra el `en` de su propietario, resolución
previa al catálogo localizado, interruptor global, actor histórico y coherencia de
caché.

En este incremento, la gestión será exclusivamente por Artisan. La interfaz web,
la autorización de backoffice y la auditoría durable de overrides quedan para
LOC-04, después de completar LOC-03. Este incremento no convierte las claves de
interfaz en contenido editorial ni modifica `PageTranslation`, Media o
PageBuilder.

## 2. Contexto y evidencia

Core ya registra idiomas, mantiene los predeterminados globales y resuelve textos
de interfaz desde `UI catalogs` JSON. La resolución actual recorre el catálogo del
locale solicitado y, en modo `base`, el catálogo `en`; en modo `key` devuelve la
clave literal cuando no existe una línea válida
(`docs/specs/SPEC-static-language-foundation.md`).

Los textos visibles se resuelven por dos caminos:

- `Modules\Core\Localization\Services\UiCatalogResolver` para claves `core::*`.
- `Modules\Core\Template\Services\TemplateUiCatalogs` para claves de CMS Templates,
  incluido `base::*`, con fallback entre el template efectivo y su presentación.

El catálogo Core contiene una única clave y el template Base contiene el inventario
principal de la interfaz. Ambos distribuyen `en` y `es_ES`. Instalar un segundo
idioma para pruebas E2E no requiere inventar contenido editorial: solo hace falta
un manifiesto temporal con los metadatos del locale.

Los propietarios actuales son Core y los templates registrados en `cms_templates`,
cuyo `identifier` es único. `UiCatalogOwnerRegistry` solo registra Core; los
templates se resuelven por su identificador y su directorio. El template `base` no
puede desactivarse.

`AdminAuditLogger` cubre Pages, Menus y publicación, pero no idiomas ni overrides.
El roadmap de calidad mantiene esa cobertura diferida a LOC-04, por lo que este
incremento puede mutar overrides por Artisan pero no exponerlos en la web.

No existe todavía tabla, modelo, servicio, comando ni prueba de overrides. La
precedencia documentada sin overrides es:

```text
UI catalog del locale solicitado
-> UI catalog en, solo en modo base
-> clave literal
```

## 3. Alcance

- Persistir overrides por locale y clave, con propietario derivado de la clave.
- Validar locale instalado, propietario resoluble, clave existente en el `en` del
  propietario, valor y compatibilidad de placeholders y pluralización.
- Resolver primero el override y después el catálogo localizado, tanto en Core
  como en CMS Templates.
- Permitir overrides aunque el propietario no distribuya el locale solicitado.
- Ofrecer un interruptor global que ignore los overrides sin borrarlos.
- Registrar el actor creador y el último modificador como snapshots históricos,
  sin claves foráneas hacia `users`.
- Invalidar la caché de resolución tras cada commit.
- Gestionar altas, bajas, listado, activación, desactivación y validación mediante
  Artisan.
- Degradar de forma segura si el almacén de overrides no está disponible.
- Documentar el contrato, la operación y el impacto en la resolución.

### Fuera de alcance

- Interfaz web, rutas, controladores, formularios, Vue y autorización de
  backoffice.
- Eventos de auditoría, historial, retención e intentos denegados.
- Overrides de módulos custom y política de colisiones entre identificadores de
  módulos y templates.
- Instalación de paquetes de idioma, rutas externas, descarga, subida o extracción.
- `PageTranslation`, contenido editorial, Media, PageBuilder y sus valores por
  locale.
- Etiquetas BCP 47 de contenido, `<html lang>` y `hreflang`.
- Edición, recarga o publicación de catálogos JSON desde la web.
- Importación o exportación masiva de overrides.
- La creación automática de overrides globales como efecto de instalar, activar o
  sincronizar un CMS Template. Un template puede documentar cambios recomendados,
  pero el administrador los aplica explícitamente por Artisan o, en el futuro,
  desde el backoffice autorizado.

### Alcance diferido

- Los propietarios de módulos se incorporarán cuando exista el primer módulo
  custom con `UI catalog` y se haya definido su ciclo de vida. La ampliación no
  migrará filas, comandos ni formato de claves: añadirá su localizador al mismo
  contrato de propietario.
- La pantalla de administración, la autorización y la auditoría de overrides se
  retomarán en LOC-04, con LOC-03 completado.
- La importación o exportación masiva se retomará si una necesidad operativa
  reproducible lo justifica.
- La política global de colisiones entre propietarios de módulos y templates se
  aplicará antes de registrar el primer propietario de módulo. Los propietarios
  externos deberán usar una convención `<vendor>-<capacidad>` en kebab-case, por
  ejemplo `acme-media`; `core` y `base` permanecen reservados. El registro global
  seguirá rechazando coincidencias entre Core, templates y módulos.
- El ajuste fino de TTL, métricas y almacenamiento de caché se retomará si el
  volumen de overrides o de tráfico lo exige.

## 4. *Clash check*

- SDD inicial: los overrides son texto de interfaz. No alteran la separación
  `Page`/`PageTranslation`, la publicación en cascada, los menús, Media ni el
  PageBuilder. No hay conflicto.
- ADR-0002: la decisión 7 permite que los overrides traduzcan claves válidas y
  prohíbe crear claves ausentes del `en` del propietario. La decisión 3 fija el
  formato `<owner>::<group>.<item>`. La decisión 9 mantiene los modos `base` y
  `key`. No hay conflicto.
- ADR-0003: la migración, la unicidad y la resolución se verifican en SQLite,
  MySQL y MariaDB. No hay conflicto.
- ADR-0004 y ADR-0006: no cambian la identidad de los templates, la selección de
  presentaciones ni el fallback de Blades. Los overrides solo sustituyen líneas de
  catálogos ya resueltos. No hay conflicto.
- `SPEC-static-language-foundation`, completada: difería los overrides y fijaba
  que una clave ausente de `en` nunca existe. Este incremento respeta ese
  contrato y añade una capa de resolución, sin modificar archivos ni completitud.
- `SPEC-template-foundation` y
  `SPEC-template-presentation-resolution-foundation`, completadas: no se altera el
  manifiesto, el ciclo de vida, la presentación efectiva ni la protección de Base.
- `SPEC-admin-audit-foundation`, completada: excluía expresamente los overrides.
  Este incremento no escribe eventos de auditoría; solo conserva snapshots de
  actor en la propia entidad, como anticipa LOC-02.
- `SPEC-public-localized-routes`, completada: no cambia la selección HTTP de
  locale, los prefijos, las redirecciones ni la canonicidad.
- Roadmap de localización: LOC-02 queda cubierto por esta Spec. LOC-03 y LOC-04
  mantienen su estado y sus dependencias.
- Roadmap de calidad: las mutaciones por Artisan están permitidas antes de LOC-03;
  la exposición web sigue bloqueada hasta LOC-04.

No se detectan conflictos sin resolver.

## 5. Requisitos y bloques técnicos aplicables

### Dominio y precedencia

- Un override es un valor persistido para una clave y un locale. La clave incluye
  el propietario y debe existir en el `UI catalog` `en` de ese propietario.
- Un override no crea claves, no modifica el inventario `en` y no cambia la
  validación de completitud de los catálogos.
- LOC-02 cubre los propietarios `core` y los CMS Templates registrados. La futura
  incorporación de módulos reutilizará la misma identidad `<owner>::<group>.<item>`
  y el mismo almacenamiento; no añadirá un tipo de propietario persistido ni una
  migración de overrides.
- La resolución efectiva de una línea es:

```text
effective(locale) = override(locale) si el interruptor está activo y el override es válido
effective(locale) = UI catalog(locale) en caso contrario

effective(locale)
-> effective(en), solo en modo base y cuando locale no sea en
-> clave literal
```

- La cadena anterior sustituye la precedencia documentada sin overrides. El modo
  `key` sigue sin consultar `en`.
- Un override puede aportar una traducción para un locale que el propietario no
  distribuya. Si el catálogo localizado no existe, el override se aplica igualmente
  antes de evaluar el fallback.
- Los overrides de `en` se permiten. `en.json` sigue siendo la autoridad de claves,
  placeholders y pluralización; el override solo cambia la línea efectiva.
- `choice()` aplica el selector de plurales de Laravel sobre la línea efectiva,
  incluida la procedente de un override.
- Una línea inválida en tiempo de lectura se ignora y se registra un aviso
  acotado. No se interrumpe el renderizado.
- El interruptor global desactiva la capa de overrides completa sin borrar filas.

### Datos y migración

- Tabla `ui_catalog_overrides`:
  - `id` incremental.
  - `locale` de hasta 35 caracteres, con clave foránea a `languages.locale` y
    `restrictOnDelete`.
  - `key` de hasta 191 bytes.
  - `value` de tipo texto, con un máximo validado de 16.384 bytes.
  - `created_by` y `updated_by` de hasta 191 caracteres.
  - `created_at` y `updated_at`.
  - Restricción única `(locale, key)` e índice por `locale`.
- Tabla `ui_catalog_override_state`:
  - Fila única con `enabled`, `version` y `updated_by`.
  - `version` es un entero creciente que identifica el estado global.
  - La migración crea la fila con overrides habilitados y versión inicial.
- No existe clave foránea hacia `users`. Los códigos de actor son snapshots y no
  datos personales.
- La migración no requiere backfill. Un despliegue existente ejecuta
  `php artisan migrate` antes de usar overrides.
- El rollback elimina ambas tablas y, con ellas, los overrides. La pérdida de datos
  se documenta como consecuencia aceptada de una reversión.
- La migración y las restricciones se prueban en SQLite, MySQL y MariaDB.

### Propietarios, claves y validación de escritura

- El locale debe cumplir el formato de locale del CMS y existir en `languages`.
  Puede estar inactivo: se permite preparar traducciones antes de activarlo.
- La clave debe cumplir `<owner>::<group>.<item>`, con propietario normalizado.
  Un propietario con otra capitalización se rechaza en lugar de normalizarse de
  forma implícita.
- Resolución del propietario:
  - `core` se resuelve contra el catálogo Core.
  - Un identificador presente en `cms_templates` se resuelve contra el catálogo
    `en` de ese template.
  - Cualquier otro propietario se rechaza.
- El owner `core` queda reservado para Core. La ampliación posterior a módulos
  registrará propietarios externos con la convención
  `<vendor>-<capacidad>` en kebab-case y rechazará cualquier coincidencia con Core,
  un template o un módulo ya registrado. No se usarán prefijos de tipo como
  `mod::` ni `mod_`.
- El template debe disponer de un `en.json` válido. Un template sin catálogo no
  puede ser propietario de overrides.
- La clave debe existir exactamente en el `en` del propietario. No se aceptan
  claves huérfanas ni claves ausentes.
- El valor debe ser un string no vacío, UTF-8, sin caracteres de control, sin `<`
  ni `>`, y con los mismos límites de tamaño que un catálogo.
- Los placeholders deben coincidir con los de la línea `en` del propietario, con
  la misma tolerancia de capitalización que el catálogo.
- La presencia de pluralización debe coincidir con la línea `en`. Un valor plural
  debe incluir `:count` y segmentos válidos.
- Una escritura inválida no persiste ninguna fila y no incrementa la versión.
- Un `set` sobre una combinación existente actualiza `value` y `updated_by`;
  conserva `created_by` y `created_at`.
- Si el propietario o la clave desaparecen después del alta, la fila se conserva,
  se ignora en resolución y la validación la reporta como huérfana o inválida. No
  se borra automáticamente.

### Resolución en Core y CMS Templates

- `UiCatalogResolver` consulta la capa de overrides antes de resolver la línea del
  catálogo Core.
- `TemplateUiCatalogs` consulta la capa de overrides para la clave completa del
  propietario antes de leer su archivo.
- La consulta de overrides no altera el fallback entre template efectivo y
  presentación: cada paso usa el propietario que le corresponde.
- Un override de `base::…` no sustituye una clave propia de otro template.
- La ausencia de un catálogo localizado no impide aplicar el override.
- La capa de overrides no modifica el comportamiento de una clave ausente: si no
  hay override ni línea válida, se devuelve la clave literal.
- La resolución no ejecuta HTML, PHP, JavaScript ni plantillas. Blade conserva el
  escape por defecto.

### Actor e identidad histórica

- El actor se resuelve en el momento de la mutación:
  - superadministrador autenticado en web: `user:<id>`;
  - Artisan: `console`;
  - sin actor humano: `system`.
- `created_by` se fija en el alta y no cambia en actualizaciones.
- `updated_by` se actualiza en cada mutación efectiva.
- Los códigos son snapshots históricos, no claves foráneas, y no contienen nombre,
  correo ni otro dato personal.
- El cambio de interruptor también registra `updated_by` y `updated_at` en la fila
  de estado.

### Caché e invalidación

- Cada petición o proceso consulta el estado global para conocer `enabled` y
  `version`.
- Las líneas validadas se cachean por locale con una clave que incluye la versión
  global y un TTL acotado.
- La mutación de un override y el incremento de versión comparten transacción. Si
  el incremento falla, la mutación se revierte.
- Tras el commit, el proceso que mutó descarta su snapshot en memoria.
- Una versión anterior deja de consultarse inmediatamente porque la clave de caché
  cambia; sus entradas expiran por TTL.
- Un fallo de caché no impide resolver: se intenta la lectura directa.
- Un fallo del almacén de overrides no impide renderizar: se resuelve sin
  overrides y se registra un aviso acotado y deduplicado.
- La caché no se usa para ocultar un fallo de base de datos ni para servir un
  estado anterior al commit.

### API Artisan

- `cms:ui-catalog:override:set {key} {locale} {--value=}`: crea o actualiza. La
  opción `--value` es obligatoria y valida antes de persistir.
- `cms:ui-catalog:override:remove {key} {locale}`: elimina una fila existente. Si
  no existe, falla con código distinto de cero.
- `cms:ui-catalog:override:list {--locale=} {--owner=} {--with-values}`: lista
  metadatos; los valores solo se muestran con `--with-values`.
- `cms:ui-catalog:override:enable` y `cms:ui-catalog:override:disable`: cambian el
  interruptor global. Si ya está en el estado solicitado, informan y no
  incrementan la versión.
- `cms:ui-catalog:override:validate`: comprueba todas las filas contra los
  catálogos actuales, informa de huérfanas o inválidas y devuelve código distinto
  de cero si hay alguna. No muta datos.
- Los comandos no muestran valores completos por defecto, no imprimen secretos y
  no dependen de interacción. `set` recibe el valor exclusivamente mediante
  `--value`; no incorpora stdin, archivos, editor interactivo ni importación
  masiva en este incremento.

### Seguridad

- No se añaden rutas, controladores ni formularios web. La autorización es el acceso
  al entorno de consola.
- Los valores se validan como texto no ejecutable y se escapan al renderizar.
- Los avisos y errores incluyen clave, locale y propietario, pero no valores,
  replacements, sesión ni datos personales.
- La validación no imprime el contenido completo de los catálogos.
- Las claves foráneas y la unicidad impiden duplicados y locales inexistentes.
- Los códigos de actor no permiten reconstruir datos personales.

### Módulos, Vue, PageBuilder, asincronía e integraciones

No aplicable: no se añaden módulos, componentes Vue, bloques de PageBuilder, colas,
trabajos, reintentos ni integraciones externas. La resolución es síncrona y
server-side.

### Entrega y compatibilidad

- No cambia el contrato de los catálogos, los manifiestos de idioma, las rutas
  públicas, las presentaciones ni los datos editoriales.
- Los despliegues existentes deben ejecutar la migración antes de usar overrides.
- La configuración de fallback sigue funcionando con `config:cache`; no se lee
  `env()` fuera de archivos de configuración.
- Los comandos nuevos son aditivos y no sustituyen los de idiomas.
- La documentación de administración y desarrollo se actualiza en el mismo
  incremento.

## 6. Calidad, seguridad y deuda

### Garantías obligatorias

- Probar que un override válido se persiste y que locale, propietario, clave,
  valor, placeholders o pluralización inválidos se rechazan sin escribir.
- Probar la precedencia completa en modos `base` y `key`, con y sin catálogo
  localizado, para Core y Base.
- Probar que el interruptor desactiva y restaura sin borrar filas.
- Probar `choice()` con un override plural y rechazo de plurales inválidos.
- Probar los snapshots de actor y la conservación de `created_by`.
- Probar que una mutación confirmada se observa en el mismo proceso y en uno nuevo,
  y que la caché no sirve un estado anterior al commit.
- Probar los seis comandos, sus códigos de salida y la ausencia de persistencia
  ante errores.
- Probar la degradación segura con tablas ausentes o almacén no disponible.
- Probar la migración, unicidad, versión y resolución en SQLite, MySQL y MariaDB.
- Ejecutar pruebas enfocadas, suite afectada, Pint, build de Vite y la matriz de
  motores.

### Riesgos aceptados

- La calidad semántica de una traducción no se valida automáticamente; solo su
  estructura. Un valor válido puede ser una traducción incorrecta y requiere
  revisión humana.
- Hasta LOC-04 no existe historial de overrides ni de intentos denegados. Solo se
  conservan los snapshots de creador y último modificador.
- Los overrides pueden divergir del catálogo versionado. Es su finalidad, pero
  exige disciplina operativa y validación periódica.
- Las entradas de caché de versiones anteriores permanecen hasta su TTL. Se acepta
  por el tamaño reducido previsto.
- Una reversión de la migración elimina los overrides. Se documenta y se acepta
  para este incremento.

### Deuda técnica

No aplica: los aplazamientos tienen condición de revisión y no introducen deuda
oculta en el alcance entregado.

## 7. Criterios de aceptación

- **CA-01:** Un override válido para un locale instalado y una clave existente en
  el `en` de su propietario se persiste; cualquier dato inválido se rechaza sin
  escribir ni incrementar la versión.
- **CA-02:** La resolución aplica override, catálogo localizado, `en` en modo
  `base` y clave literal en ese orden; en modo `key` no consulta `en`; funciona
  para Core y Base y cuando el catálogo localizado no existe.
- **CA-03:** El interruptor global ignora todos los overrides sin borrarlos y los
  restaura al reactivarse.
- **CA-04:** `choice()` usa el override y aplica pluralización; un override plural
  inválido se rechaza.
- **CA-05:** Cada mutación registra `created_by` y `updated_by` con los códigos
  `user:<id>`, `console` o `system`, sin clave foránea ni datos personales, y
  conserva `created_by` al actualizar.
- **CA-06:** Una mutación confirmada se observa en resoluciones posteriores del
  mismo proceso y de un proceso nuevo; la caché no sirve un estado anterior al
  commit.
- **CA-07:** Los comandos `set`, `remove`, `list`, `enable`, `disable` y `validate`
  validan su entrada, informan del resultado y devuelven código distinto de cero
  ante datos inválidos; `validate` detecta huérfanos sin mutar.
- **CA-08:** Si faltan las tablas o falla el almacén o la caché, el renderizado
  continúa con los catálogos, sin error 5XX y con un aviso acotado.
- **CA-09:** La migración, la unicidad, el interruptor, la versión y la resolución
  se comportan igual en SQLite, MySQL y MariaDB.

## 8. Trazabilidad de pruebas y documentación

| Criterio | Riesgo cubierto | Nivel de prueba | Evidencia esperada | Impacto documental |
| :--- | :--- | :--- | :--- | :--- |
| CA-01 | Persistencia de datos inválidos | Integración | Fila creada solo con datos válidos | Guía de overrides |
| CA-02 | Precedencia incorrecta o texto sin traducir | Integración y HTTP | Texto override visible en login y página pública | Guía de overrides y desarrollo |
| CA-03 | Imposibilidad de desactivar overrides | Integración y comando | Overrides ignorados y restaurados sin pérdida | Guía de overrides |
| CA-04 | Rotura de plurales | Unit e integración | `choice()` correcto con override | Guía de desarrollo |
| CA-05 | Pérdida de trazabilidad del actor | Integración | Snapshots correctos y `created_by` estable | Guía de overrides |
| CA-06 | UI obsoleta tras el cambio | Integración | Resolución posterior al commit y proceso nuevo | Guía de overrides |
| CA-07 | Error operativo o mutación accidental | Comando | Salidas, códigos y validación sin mutación | Guía de administración |
| CA-08 | Error 5XX durante instalación o upgrade | Integración y HTTP | Fallback sin excepción y aviso acotado | Guía de operación |
| CA-09 | Divergencia entre motores | Matriz | Mismo comportamiento en tres motores | Baseline de bases de datos |

## 9. Plan de implementación

1. Crear pruebas rojas de migración, modelo, estado global y unicidad; implementar
   las tablas y restricciones hasta CA-01 y CA-09.
2. Crear pruebas rojas de resolución de propietario, clave, locale, valor,
   placeholders y pluralización; implementar la validación hasta CA-01 y CA-04.
3. Crear pruebas rojas del manager de altas, bajas y actualizaciones, actor y
   versión; implementar la mutación transaccional hasta CA-05 y CA-06.
4. Integrar la capa de overrides en Core y en `TemplateUiCatalogs`, con interruptor,
   fallback y caché; cubrir CA-02, CA-03 y CA-06.
5. Implementar los comandos Artisan y sus pruebas hasta CA-07.
6. Añadir una prueba HTTP/E2E con `es_ES` y un override de
   `base::admin.login.title`, más la degradación segura hasta CA-08.
7. Actualizar la guía de administración, la documentación de desarrollo, el
   roadmap y el changelog; ejecutar Pint, suite afectada, build de Vite y matriz
   SQLite, MySQL y MariaDB antes de cerrar.

## 10. Decisiones abiertas

No aplica: las decisiones necesarias para este incremento están resueltas.

### Decisiones resueltas

- Los overrides de `en` están permitidos. El `en.json` distribuido mantiene la
  autoridad sobre claves, placeholders y pluralización.
- Se mantiene un interruptor global persistente en base de datos con `enabled` y
  `version`, gestionado por Artisan. La versión participa en la invalidación de
  caché; no se añaden interruptores por propietario o locale en este incremento.
- El comando `set` exige `--value`. Artisan es una vía técnica de bootstrap,
  automatización y recuperación, no la experiencia administrativa ordinaria;
  stdin, archivos, editor interactivo e importación masiva quedan fuera de este
  incremento y podrán evaluarse solo ante una necesidad técnica demostrada.
