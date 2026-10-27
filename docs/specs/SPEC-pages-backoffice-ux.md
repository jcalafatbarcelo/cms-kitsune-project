# SPEC: Identidad interna de Pages y UX del backoffice

- **Estado:** Aprobada
- **Perfil:** feature
- **Origen de la planificación:** Petición del responsable del proyecto para
  diferenciar la identidad interna de una Page de sus traducciones y mejorar la
  usabilidad del backoffice de Pages y Menus.
- **Spec relacionada:** [SPEC-pages-backoffice](SPEC-pages-backoffice.md),
  completada.

## 1. Objetivo o problema

El backoffice identifica actualmente las Pages por su ID. Este dato no permite
reconocer con facilidad el destino de un ítem de menú ni el padre de una Page.
Además, las pantallas administrativas son HTML sin una capa visual común y sus
CTA contienen etiquetas genéricas o ambiguas.

El incremento introduce una identidad interna legible y única para cada Page,
independiente de los títulos y slugs traducibles, y aplica una interfaz de
backoffice accesible, responsiva y consistente para las pantallas ya
disponibles.

## 2. Contexto y evidencia

`pages` contiene identidad estructural, jerarquía, template y tipo de contenido,
mientras `page_translations` conserva `title` y `slug` por idioma. El
`PageManager` y el comando `cms:page:create` solo reciben locale, slug y título;
las vistas administrativas muestran `#id` en los selectores de Page y padre.

Las vistas Blade de dashboard, Pages, Menus y edición de Page no cargan
`resources/css/app.css`. El CSS existente solo inicializa Tailwind; Vite ya
declara ese stylesheet como entrada. `SPEC-pages-backoffice` establece que la
gestión sigue protegida por superadministrador y CSRF, sin API JSON pública.

## 3. Alcance

- Añadir a cada Page un `name` interno, legible, obligatorio, no traducible y
  único sin distinguir mayúsculas de minúsculas, con un máximo de 150 caracteres
  Unicode.
- Solicitar el nombre interno al crear una Page desde el backoffice y adaptar la
  creación programática y el comando Artisan para que toda Page nueva lo reciba.
- Mostrar solo `Page.name` en los selectores de Page de Menus y en los selectores
  de padre de Pages; no combinarlo con `title` o `slug` traducibles.
- Presentar una opción explícita y localizada para no asignar padre.
- Sustituir CTA ambiguos por acciones localizadas y específicas, incluido
  `Add item` / `Añadir elemento` para crear ítems de menú.
- Cargar una capa de estilos común para dashboard, listado y creación de Pages,
  listado y creación de Menus y edición de Page. Debe ser mobile-first,
  responsive, usable con teclado y conforme con WCAG AA.
- Conservar rutas, autorización, CSRF, persistencia de relaciones, reglas de
  publicación, home, jerarquía y las operaciones de dominio existentes.

### Fuera de alcance

- Cambiar títulos o slugs desde una pantalla distinta de la edición localizada
  existente.
- Usar `title` o `slug` como identificador interno, o exponer `Page.name` en
  rutas, navegación pública, HTML público, contenido editorial o API.
- PageBuilder, Vue, SPA, Inertia, búsqueda, filtrado, paginación, Media y
  rediseño de pantallas administrativas que no sean dashboard, Pages, Menus y
  edición de Page.
- Cambiar los contratos funcionales de Menus, la selección de home o la auditoría
  existente salvo incluir el nombre en los estados de auditoría cuando la creación
  ya la registra.

### Alcance diferido

- La edición o renombrado de `Page.name` se evaluará cuando exista una necesidad
  operativa verificable. Mientras no se especifique, el nombre se establece al
  crear la Page y no se expone una mutación posterior.
- La optimización visual para grandes catálogos de Pages, como búsqueda remota o
  selectores enriquecidos, se retomará cuando el número de opciones demuestre que
  el selector HTML nativo deja de ser usable.

## 4. *Clash check*

- **SDD inicial:** concreta el patrón Entity-Translation: `name` pertenece a la
  identidad estable `Page`, mientras `title` y `slug` permanecen en
  `PageTranslation`. No afecta a las URLs canónicas ni a los árboles visuales de
  Menus.
- **SPEC-pages-foundation:** amplía el esquema de `pages` sin trasladar título o
  slug fuera de las traducciones. El comando de creación cambia porque una nueva
  identidad obligatoria debe recibirse en todos los orígenes de creación.
- **SPEC-pages-backoffice:** conserva las rutas, el acceso de superadministrador,
  CSRF, las validaciones de dominio y la auditoría transaccional. La mejora de
  presentación concreta sus pantallas sin alterar sus reglas.
- **ADR-0001:** mantiene Blade como base. La interfaz no añade Vue, SPA ni
  Inertia; Vite carga únicamente el stylesheet ya declarado para las vistas que
  lo necesitan.
- **Código y pruebas:** `PageManager::create()` centraliza la creación y genera
  stubs de traducción. Los tests y comandos existentes deberán adaptarse al nuevo
  argumento obligatorio. No hay conflicto irresoluble.

## 5. Requisitos y bloques técnicos aplicables

### Dominio, datos y migración

- `pages` incorpora `name`, texto interno obligatorio de hasta 150 caracteres
  Unicode y distinto de los campos traducibles, junto con `name_key`, clave
  interna normalizada no editable. Ninguno representa una ruta, un slug, una
  vista, una clave de UI ni una clave de contenido.
- Antes de validar o persistir, el valor elimina espacios iniciales y finales.
  Debe ser UTF-8, no vacío y no contener caracteres de control
  (`\x00-\x1F\x7F`).
- El valor no puede contener ninguno de estos caracteres:
  `!?:.,<>=()[]{}/+*|@#~$%&\ºª€`.
- `name_key` se calcula en el servidor a partir de `name` ya recortado aplicando
  primero normalización Unicode NFC (`Normalizer::normalize` con `FORM_C`) y
  después minúsculas Unicode (`mb_strtolower` con UTF-8). Los nombres que solo se
  distingan por mayúsculas o minúsculas se consideran el mismo nombre. `name_key`
  usa una columna de 600 caracteres para reservar margen ante la expansión de la
  forma normalizada. Un índice único sobre `name_key` mantiene la unicidad bajo
  concurrencia y en SQLite, MySQL 8.4 y MariaDB 11.4, sin depender de una
  collation implícita distinta por motor.
- La migración añade `name` y `name_key` como obligatorios. La Page de sistema
  "Under construction" recibe el nombre interno "Under construction" y su
  `name_key` calculada. En desarrollo se puede reconstruir el entorno con
  `migrate:fresh`; no se exige backfill de datos reales ni una ruta de
  actualización, por lo que este cambio no es un bloqueante.
- La migración debe tener rollback y preservar las restricciones existentes de
  `pages`, `page_translations`, home, Menus y templates.

### Contratos de creación y auditoría

- Toda creación de Page, incluida backoffice, Artisan, seed y uso de
  `PageManager`, exige el nombre interno validado antes de crear registros o
  eventos de auditoría.
- El contrato de `cms:page:create` pasa a ser
  `{name} {locale} {slug} {title} {--parent=} {--template=} {--type=rich-text}`.
  El argumento `name` es obligatorio y sigue las mismas reglas de validación que
  el backoffice.
- La creación rechaza un nombre inválido o duplicado con un error controlado y
  no persiste Page, traducciones, contenidos ni auditoría parcial.
- El estado de auditoría de creación identifica el nombre interno, sin registrar
  contenido editorial ni datos adicionales sensibles.
- `name` pasa a formar parte del estado de identidad auditado. La inclusión
  cambia la forma de `before_state`/`after_state` en los eventos de creación y
  movimiento; las pruebas de auditoría existentes se adaptan a ese contrato.

### Backoffice y presentación

- El formulario de creación de Pages muestra un campo de nombre interno
  claramente diferenciado de título y slug, con ayuda que explique que no se
  traduce ni se usa en la URL pública.
- El selector de padre en creación y movimiento de Page muestra `Page.name` y
  una opción localizada equivalente a `-- None --` para la raíz. El selector de
  movimiento excluye la propia Page y sus descendientes; el de creación lista
  todas las Pages existentes.
- El selector de destino en la creación y edición de ítems de menú muestra
  `Page.name`. No muestra IDs, títulos ni slugs como etiqueta visible.
- Las páginas administrativas cargan el stylesheet común mediante Vite. El
  editor TinyMCE conserva su entrada aislada y no se carga en páginas que no lo
  requieren.
- El rediseño usa Blade y CSS de proyecto siguiendo BEM + OOCSS con namespaces,
  mobile-first y WCAG AA. Incluye jerarquía semántica, labels asociados, foco
  visible, contraste suficiente, mensajes accesibles, tablas navegables y una
  alternativa responsive para tablas anchas.
- Todas las cadenas visibles de las vistas administrativas de Base usan claves
  de UI catalog, sin texto incrustado en Blade. Esto incluye las etiquetas y
  acciones actualmente literales (`Identifier`, `Menu`, `Label`, `Page`,
  `Parent`, `Position`, `ID`, `Save`, `Move`, `Remove`) además de las claves
  nuevas de nombre interno, ayuda, opción sin padre y CTA. Los catálogos `en` y
  `es_ES` quedan completos.

### API, PageBuilder, asincronía e integraciones

No aplican. El incremento no crea rutas JSON, schemas JSON de PageBuilder, colas
ni integraciones externas.

## 6. Calidad, seguridad y deuda

### Garantías obligatorias

- Aplicar TDD cuando aporte ventaja a la validación y unicidad de `Page.name`.
- Probar en integración la persistencia, normalización, caracteres prohibidos,
  duplicado que solo difiere por mayúsculas, rollback y concurrencia relevante en
  SQLite, MySQL 8.4 y MariaDB 11.4.
- Probar por HTTP que solo el superadministrador ve y usa el campo, que los
  selectores muestran nombres internos, que las CTA son específicas y que la
  opción sin padre conserva una Page raíz.
- Probar que títulos y slugs siguen localizados y que el nombre interno no se
  filtra a rutas ni a la web pública.
- Verificar que los estilos se cargan solo en las vistas administrativas
  afectadas, que el build frontend pasa y que la navegación por teclado y los
  mensajes de error conservan su semántica accesible.
- Las pruebas HTTP que renderizan vistas administrativas aíslan Vite
  (`withoutVite`) para no depender del manifest; esta restricción se documenta en
  la guía de desarrollo de pruebas.

### Riesgos aceptados

- Un selector HTML nativo puede resultar poco práctico con catálogos muy grandes.
  Se acepta para el MVP porque no existe evidencia de ese volumen; se revisará al
  superar un límite de uso observable.

### Deuda técnica

- No aplica. La estrategia de unicidad portable, la validación y las pruebas
  multi-motor son obligatorias antes de completar el incremento.

## 7. Criterios de aceptación

- **CA-01:** Cada Page dispone de un nombre interno obligatorio, legible y no
  traducible; nombres que difieren solo por mayúsculas/minúsculas no pueden
  coexistir.
- **CA-02:** El nombre rechaza texto vacío tras trim, caracteres de control
  (`\x00-\x1F\x7F`) y los caracteres restringidos
  `!?:.,<>=()[]{}/+*|@#~$%&\ºª€`; un rechazo no deja persistencia ni auditoría
  parcial.
- **CA-03:** Crear una Page desde el backoffice o Artisan exige el nombre interno;
  la Page de sistema instalada recibe uno explícito y las pruebas/factories no
  crean Pages sin nombre.
- **CA-04:** Los selectores de Page en creación y edición de ítems de menú y los
  selectores de padre de Page muestran solo el nombre interno. La opción de raíz
  es explícita, localizada y conserva la jerarquía válida; el selector de
  movimiento no ofrece la propia Page ni sus descendientes.
- **CA-05:** Las CTA y etiquetas visibles del backoffice describen la acción y
  están localizadas. La creación de un ítem usa `Add item` en `en` y `Añadir
  elemento` en `es_ES`; no quedan cadenas visibles incrustadas en las vistas de
  Base y ambos catálogos están completos.
- **CA-06:** Dashboard, Pages, Menus y edición de Page comparten una interfaz
  administrativa responsive y accesible, con carga correcta del stylesheet,
  formularios legibles, foco visible, contraste WCAG AA y tablas usables en
  móvil.
- **CA-07:** La URL pública, la disponibilidad, publicación, home, traducciones,
  contenido y autorización existentes conservan su comportamiento.

## 8. Trazabilidad de pruebas y documentación

| Criterio | Riesgo cubierto | Nivel de prueba | Evidencia esperada | Impacto documental |
| :--- | :--- | :--- | :--- | :--- |
| CA-01 | Identidad ambigua o única solo en un motor | Integración multi-motor | Nombre persistido y duplicado case-insensitive rechazado | `docs/developers/pages.md` |
| CA-02 | Datos inválidos o operación parcial | Integración | Rechazos, trim y rollback verificados | `docs/developers/pages.md` |
| CA-03 | Origen de creación sin identidad | Integración y Artisan | Servicio, comando, seed y pruebas crean Pages válidas | `docs/developers/pages.md` |
| CA-04 | Selección equivocada de destino o padre | HTTP | Opciones visibles por nombre y raíz preservada | `docs/administration/pages-and-menus.md` |
| CA-05 | CTA ambigua o catálogo incompleto | HTTP e integración de catálogos | Texto localizado visible en ambos locales | `docs/administration/pages-and-menus.md` |
| CA-06 | Backoffice no usable o inaccesible | HTTP, build y revisión de marcado/CSS | Assets, estructura semántica y build verificados | `docs/user-guide/backoffice-access.md` y `docs/administration/pages-and-menus.md` |
| CA-07 | Regresión de rutas o reglas de dominio | Integración y HTTP | Suites existentes de Pages y Navigation en verde | No aplica; comportamiento ya documentado |

## 9. Plan de implementación

1. Escribir pruebas de identidad interna y extender el esquema, el servicio,
   seed y Artisan hasta que la creación sea atómica y consistente en los tres
   motores.
2. Adaptar controladores, catálogos y vistas de Pages/Menus para solicitar y
   mostrar el nombre interno, verificando los flujos HTTP de creación, edición y
   jerarquía.
3. Cargar el stylesheet común y construir los componentes de backoffice con la
   metodología CSS, verificando responsividad, accesibilidad y aislamiento del
   editor.
4. Actualizar guías de administración y desarrollo, ejecutar pruebas enfocadas,
   suite afectada, Pint, build frontend, matriz multi-motor y revisar el diff.

## 10. Decisiones abiertas

No aplica. `Page.name` tiene un máximo de 150 caracteres Unicode y permanece
inmutable durante este incremento. `name_key` persiste la forma NFC y en
minúsculas para imponer unicidad portable sin distinguir mayúsculas/minúsculas;
la Page de sistema recibe el nombre "Under construction".
