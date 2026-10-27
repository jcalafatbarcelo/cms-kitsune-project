# SPEC: Backoffice de Pages y Menus, tipos de contenido y edición de texto enriquecido

- **Estado:** Completada
- **Perfil:** feature
- **Origen de la planificación:** Petición del responsable del proyecto para
  combinar la gestión de Pages y Menus antes del PageBuilder, trasladar la
  selección de home a un ítem de menú e introducir el primer tipo de contenido
  editorial con TinyMCE. Concretada el 2026-10-17.
- **Spec relacionada:** [SPEC-pages-foundation](SPEC-pages-foundation.md),
  [SPEC-navigation-foundation](SPEC-navigation-foundation.md),
  [SPEC-public-localized-routes](SPEC-public-localized-routes.md),
  [SPEC-admin-audit-foundation](SPEC-admin-audit-foundation.md) y
  [SPEC-minimal-admin-panel](SPEC-minimal-admin-panel.md), completadas.

## 1. Objetivo o problema

Entregar la gestión web de Pages y Menus desde el backoffice protegido, con un
primer tipo de página de texto libre editado mediante TinyMCE. La home deja de
seleccionarse desde Pages y pasa a marcarse desde un ítem de menú, uno por idioma
en todo el sistema, sin menú principal.

El incremento define una frontera de extensión para que módulos externos al Core
aporten tipos de contenido y elementos insertables, y para que la configuración de
TinyMCE pueda evolucionar a una configuración externa por tipo sin reestructurar el
editor. No se implementa ahora un sistema general de administración de TinyMCE: la
integración inicial valida y sanitiza en servidor el HTML según la política del
tipo, sin confiar en la configuración ejecutada por el navegador.

## 2. Contexto y evidencia

El módulo `Pages` persiste `Page` y `PageTranslation`, jerarquía, slugs,
publicación y template; `PageManager` expone las operaciones de dominio y las
instrumenta con auditoría durable. La home se resuelve hoy en
`page_language_homes` y la primera traducción de un idioma se publica y se
convierte en home automáticamente. `Navigation` mantiene menús como árboles
visuales independientes y obtiene las URLs canónicas de Pages mediante
`PublicPageUrlResolver` (ADR-0005). No existe modelo de tipo de contenido ni
contenido editorial persistido.

El backoffice mínimo autentica un superadministrador por sesión; la fundación de
auditoría registra las mutaciones existentes de Pages y Menus. No hay pantallas de
gestión de Pages ni de Menus. El SDD exige separar Page y PageTranslation,
contenido y UI catalogs, y renderizar el contenido público con Blade. ADR-0004 y
ADR-0006 reservan las presentaciones visibles a claves semánticas resueltas por
Core/templates. TinyMCE no está instalado como dependencia identificada.

## 3. Alcance

- Gestionar Pages y Menus desde el backoffice existente, reutilizando las reglas
  de dominio en lugar de duplicarlas en controladores.
- Crear, editar y organizar Pages y sus traducciones; gestionar menús e ítems;
  marcar una única home por idioma en todo el sistema; publicar y despublicar;
  mover Pages; y borrar Pages bajo las restricciones definidas.
- Entregar el primer tipo de contenido de texto libre `rich-text`, cuyo editor de
  backoffice usa TinyMCE y cuya clave editorial es `body.content`.
- Definir un contrato de registro para que módulos instalados aporten tipos de
  contenido y elementos insertables, sin que los datos editoriales seleccionen
  código, clases, rutas Blade o imports.
- Validar y sanitizar en servidor el HTML antes de persistirlo, según la política
  efectiva del tipo; la configuración JavaScript del editor no es autoridad de
  seguridad.
- Definir el contrato de elementos insertables (embeds) dentro de esta Spec, sin
  redactar un ADR hasta que Media lo consuma.
- Aplicar fallback de contenido por clave usando el idioma predeterminado de
  frontend y el modo global de UI catalogs.
- Registrar mediante la auditoría existente las mutaciones web, de forma
  transaccional, sin conservar el HTML completo ni versiones recuperables.

### Fuera de alcance

- PageBuilder, bloques, schemas JSON de PageBuilder o editor visual de
  composición.
- La pantalla administrativa para gestionar configuraciones externas de TinyMCE.
- Roles, permisos granulares, gestión de usuarios o de idiomas/overrides.
- Tipos de página de login, logout, perfil de usuario u otras vistas de sistema.
- Instalación dinámica de módulos desde el backoffice.
- Redirecciones de URLs antiguas tras cambiar slug o mover una Page.
- Versionado de contenido recuperable.
- Permitir que contenido persistido indique una clase, ruta Blade, plugin,
  configuración JavaScript arbitraria o código de sanitización ejecutable.

### Alcance diferido

- La configuración externa de TinyMCE por tipo y su interfaz administrativa se
  retomarán cuando se concrete qué actor puede cambiarla y cuál es su fuente
  administrable. El diseño debe permitir sustituir la configuración suministrada
  por código por una fuente externa validada, sin alterar la persistencia ni el
  contrato de Pages.
- El sistema de redirecciones para URLs `404` (que registre rutas fallidas y
  permita al administrador aplicar redirecciones) se planificará en un incremento
  posterior. Al registrarlas se evitará conservar query strings con datos
  sensibles.
- El contrato de embeds se consumirá por primera vez con Media. En ese momento se
  redactará el ADR que fije la decisión, ajustando lo necesario de esta Spec.
- El permiso de `iframe` y sus orígenes, junto con la gestión de roles y la
  ampliación de capacidades por permiso, se retomarán fuera del MVP.
- La integración del PageBuilder se retomará después de estabilizar la gestión de
  Pages y Menus y el contrato de tipo/contenido.
- El versionado de contenido editorial queda fuera del MVP.

## 4. *Clash check*

- **SDD inicial:** respeta Entity-Translation, publicación en cascada y menús
  independientes de las URLs canónicas. La home pasa a marcarse desde un ítem de
  menú, pero Pages sigue siendo la autoridad de disponibilidad y URL canónica. El
  contenido editorial exige sanitización; no se altera la cascada. No hay
  contradicción.
- **SPEC-pages-foundation:** completada; estableció la home en
  `page_language_homes` y la auto-publicación de la primera traducción. Esta Spec
  las sustituye: la home se marca desde Navigation y no hay auto-publicación. Es un
  cambio de alcance explícito cubierto por esta Spec, no una ampliación implícita.
- **SPEC-navigation-foundation:** completada; los ítems referencian una Page y su
  URL se obtiene de Pages. Esta Spec añade el marcado de home por ítem y una
  relación home-por-idioma, sin convertir Navigation en router ni en dueña de las
  URLs. No hay conflicto irresoluble.
- **SPEC-public-localized-routes:** completada; la raíz resuelve la home del
  idioma efectivo. Esta Spec conserva la respuesta `404` sin home y la raíz
  localizada, pero cambia el origen del destino a un ítem de Navigation. Pages
  seguirá resolviendo idioma y validando disponibilidad.
- **SPEC-minimal-admin-panel:** completada; se reutiliza autenticación de sesión y
  acceso único de superadministrador. No se añaden roles ni gestión de usuarios.
- **SPEC-admin-audit-foundation:** completada; las nuevas mutaciones se registran
  en la misma transacción con campos permitidos, sin volcar HTML completo.
- **ADR-0001:** Blade como base del backoffice y renderizado público; el editor
  TinyMCE carga bajo demanda solo en las pantallas que lo necesitan. No se adopta
  SPA ni Inertia.
- **ADR-0002:** el contenido de Pages no es UI catalog ni override. El fallback
  editorial reutiliza el modo global, pero no convierte claves editoriales en
  claves de UI. No hay conflicto.
- **ADR-0004 y ADR-0006:** la Page sigue solicitando claves de presentación
  semánticas; los tipos y embeds no seleccionan Blade ni cambian el propietario de
  la apariencia. El contrato de embeds extiende la resolución de presentaciones con
  un mecanismo específico y validado, sin abrir el catálogo cerrado de claves de
  ADR-0006. No hay conflicto.
- **ADR-0005:** Pages conserva la autoridad sobre la URL canónica y Navigation
  consume `PublicPageUrlResolver` para los destinos de ítems. Esta Spec introduce
  una dependencia inversa acotada: Pages consulta a Navigation qué Page está
  marcada como home para un idioma. No contradice la autoridad de URL de Pages;
  es un contrato nuevo y limitado a la selección del destino de la raíz.
- **Roadmap de contenido:** esta Spec combina el Backoffice de Pages y el de Menus,
  adelantando la gestión de Menus para que la home sea seleccionable desde un ítem.
  El roadmap deberá actualizarse para reflejar que ambos incrementos se entregan
  juntos. No hay conflicto con la secuencia previa de PageBuilder.
- **Código y pruebas:** `PageManager` centraliza creación, traducción, publicación
  y auditoría, y auto-publica la primera traducción; `page_language_homes` existe.
  `MenuManager` y `PublicMenuResolver` gestionan árboles localizados. Estas
  piezas se modifican conforme a esta Spec. No hay datos reales que migrar en
  desarrollo.

## 5. Requisitos y bloques técnicos aplicables

### Dominio: tipos y contenido

- El tipo de contenido pertenece a la `Page`; todas sus traducciones comparten el
  mismo tipo. El tipo es inmutable durante el MVP.
- El tipo se identifica mediante un valor estable y validado, que no es una ruta,
  clase ni archivo. Los tipos disponibles proceden de módulos instalados y no de
  una lista administrativa persistida ni de instalación dinámica.
- Un tipo determina el editor de backoffice, las reglas de validación y la
  política de sanitización. El tipo inicial editorial es `rich-text`; la Page
  inicial "Under construction" usa un tipo de sistema estático, no seleccionable
  al crear Pages editoriales y sin editor.
- La ausencia, desactivación o incompatibilidad de un tipo ya utilizado produce un
  error controlado; no se reinterpreta silenciosamente el contenido ni se elige un
  tipo alternativo.
- Cada `PageTranslation` puede tener cero o más contenidos identificados por clave.
  En este incremento todo valor de contenido es HTML de un único TinyMCE; la
  persistencia de otros formatos se evolucionará cuando lo requiera PageBuilder.
- Guardar contenido no publica una Page automáticamente. Ninguna Page creada por
  el usuario se publica sola; la publicación por idioma es siempre manual. La
  única excepción es la Page inicial "Under construction", instalada publicada.
- Al crear una Page se crean `PageTranslation` para todos los idiomas instalados,
  incluidos los inactivos, como stubs. Al instalar un idioma nuevo se crean stubs
  para las Pages existentes. Una traducción queda incompleta y no es publicable
  hasta tener `title` y `slug` válidos.

### Dominio: home por ítem de menú

- Existe como máximo una home por idioma en todo el sistema, con independencia del
  menú al que pertenezca el ítem. Un idioma puede tener una Page de inicio
  distinta de otro idioma.
- La home se marca desde un ítem de menú. La interfaz presenta la acción "Marcar
  como home"; la representación es una relación home-por-idioma que referencia el
  ítem seleccionado.
- Al recibir una petición de raíz, Pages resuelve el idioma efectivo y consulta a
  Navigation la Page marcada como home para ese idioma; Navigation busca el ítem y
  devuelve su Page de destino; Pages valida y sirve la Page conforme a sus reglas.
  Para rutas con slugs, Navigation no interviene.
- Marcar un ítem como home exige que su Page tenga una traducción pública y
  disponible para el idioma del ítem, incluidos sus ancestros.
- Despublicar la traducción de la home, despublicar un ancestro que la haga
  inaccesible o borrar la Page home se bloquea.
- Dejar un idioma sin home es una acción explícita, con confirmación que advierte
  que la raíz de ese idioma responderá `404`. Cancelar no altera la asignación.
- Si un idioma no tiene home, su raíz responde `404`. El login administrativo
  permanece separado bajo `/admin/login`.
- La home puede ser distinta por idioma, como ya permitía el modelo anterior.

### Dominio: jerarquía, slugs y borrado

- El slug de una traducción puede cambiarse incluso después de publicar; la URL
  anterior puede responder `404` hasta que exista el sistema de redirecciones.
- Una Page publicada puede moverse a otro padre. El movimiento es atómico, cambia
  los paths de la Page y sus descendientes, y se rechaza si deja inaccesible
  alguna traducción que ya estaba publicada.
- Una Page no puede borrarse si tiene descendientes, está marcada como home o es
  referenciada por cualquier ítem de menú. El backoffice indica la referencia que
  bloquea el borrado.
- El borrado permitido es físico y atómico: elimina la Page, sus traducciones y
  sus contenidos, y queda auditado.
- No se puede eliminar una traducción individualmente; la Page es la unidad de
  borrado cuando se permite.

### Dominio: fallback de contenido

- Una clave de contenido ausente o cuyo HTML queda vacío tras sanitizar se
  considera sin traducción y usa el fallback configurado. No existe aún una señal
  editorial explícita de "sin contenido"; se retomará con PageBuilder.
- El fallback usa el idioma predeterminado de frontend, es decir, el idioma que no
  necesita prefijo en la URL, y sigue dinámicamente su valor si cambia.
- El fallback reutiliza el modo global de UI catalogs: en modo `base`, se resuelve
  la clave en el idioma solicitado, después en el idioma predeterminado de
  frontend y por último se muestra la clave literal; en modo `key`, se muestra la
  clave literal cuando falta el valor solicitado.
- Una traducción incompleta (sin `title` o `slug` válidos) no es publicable y no
  participa en rutas públicas, con independencia de su contenido.

### Datos y migraciones

El esquema resultante es:

```text
pages
- (existente) id, parent_id, uses_explicit_template, explicit_template_id,
  presentation_key, is_published
- content_type: varchar(100), not null
- created_at, updated_at

page_translations
- (existente) id, page_id, language_id, is_published
- title: varchar(255), nullable
- slug: varchar(100), nullable
- created_at, updated_at

page_translation_contents
- id: bigint, PK
- page_translation_id: bigint, FK page_translations.id, restrict, not null
- key: varchar(255), not null
- content: longtext, not null
- created_at, updated_at
- unique (page_translation_id, key)
```

- `content_type` es obligatorio y se valida contra el registro de tipos. No es
  clave foránea hacia una tabla de tipos, porque los tipos los aportan módulos
  instalados.
- `title` y `slug` pasan a ser anulables para permitir stubs incompletos. Una
  traducción solo es válida y publicable cuando ambos son no nulos y cumplen la
  gramática vigente de slugs. La restricción única sobre
  `page_translations(language_id, slug)` se conserva y aplica a los slugs no
  nulos.
- La clave editorial usa segmentos ASCII en minúscula separados por puntos, sin
  `::` y sin segmentos vacíos ni puntos iniciales/finales; máximo 255 caracteres.
  La unicidad es por `(page_translation_id, key)`. La clave completa es jerárquica
  (p. ej. `body.content`, `body.header.texto`); no hay relación padre-hijo entre
  registros.
- `content` es texto que puede contener HTML ya sanitizado; no es JSON ni un
  payload estructurado. Se persiste únicamente el HTML resultante de la
  sanitización en servidor; no se conserva el HTML original.
- El límite de aplicación para `body.content` es 4 MiB medidos en bytes y
  validados antes de sanitizar. La columna usa `longtext`, sin usar el máximo del
  tipo como límite editorial.
- La home por idioma se representa en una tabla que referencia un ítem de menú:

```text
menu_item_homes
- language_id: bigint, PK, FK languages.id, restrict
- menu_item_id: bigint, unique, FK menu_items.id, restrict
- created_at, updated_at
```

  La clave primaria sobre `language_id` impone una única home por idioma; la
  unicidad sobre `menu_item_id` impide que un ítem sea home de dos idiomas. El
  servicio de dominio comprueba que el ítem y su idioma coinciden.

- La migración elimina `page_language_homes`, crea `menu_item_homes`, añade
  `content_type` a `pages`, hace anulables `title` y `slug`, y crea
  `page_translation_contents`. No hay backfill de contenido personalizado en
  desarrollo; la instalación siembra la home inglesa mediante un menú y un ítem.
- La migración debe ser compatible con SQLite, MySQL 8.4 y MariaDB 11.4.
- No se almacena configuración ejecutable, código TinyMCE ni rutas de filesystem
  en el contenido de Page.

### Contrato de módulos, editor y TinyMCE

- Pages ofrece un registro de tipos alimentado por los módulos instalados durante
  el arranque. Un módulo registra su tipo con un identificador estable y un
  proveedor confiable; dos módulos no pueden registrar el mismo identificador.
- El módulo del tipo declara el identificador del editor que necesita; el módulo
  del editor se registra por separado. La selección se hace entre capacidades
  registradas por código, nunca desde un nombre de componente aportado por el
  contenido.
- TinyMCE queda fuera de Core. Un módulo de tipo `rich-text` solicita el editor
  `tinymce`; un módulo TinyMCE aporta el editor y la sanitización. La política de
  HTML permitida la declara el tipo y la aplica el módulo del editor.
- La distribución autocontenida de TinyMCE se configura explícitamente en modo
  GPL; no requiere ni admite una clave comercial o una API key en el CMS.
- Si la interfaz de TinyMCE no está disponible en el navegador, el editor se
  degrada a un `textarea`, manteniendo la misma validación y sanitización en
  servidor. Si el código servidor que sanitiza no está disponible, el guardado se
  bloquea; no se acepta HTML sin procesar.
- La configuración de cliente es una ayuda de edición, no una frontera de
  seguridad. El servidor aplica las reglas efectivas al recibir cada mutación.
- El editor y sus assets se cargan únicamente en las pantallas que los necesitan;
  la web pública no descarga TinyMCE.
- La política del tipo define el comportamiento de partida. La configuración por
  tipo puede exponer capacidades adicionales y los permisos del rol determinarán
  cuáles usa cada usuario, aplicándose también al guardar. No se establece una
  prohibición estructural de ampliar permisos; el permiso de `iframe` queda
  diferido. La elección de la integración de TinyMCE (Vue 3 Composition API o
  montaje independiente), su edición y distribución se revisan conforme a
  `docs/architecture/dependency-security.md`.

### Contrato de elementos insertables (embeds)

- Un elemento insertado desde otro módulo se representa dentro del HTML como un
  marcador tipado, conservando su posición en el texto. El contenido almacena una
  referencia tipada, no un fragmento HTML ejecutable ni una ruta Blade.
- El marcador usa un elemento neutro con atributos de gramática validada: tipo
  registrado, referencia opaca al elemento y variante de diseño opcional. El
  contenido no puede indicar de dónde procede el Blade.
- El tipo de elemento se registra mediante un manifiesto declarativo validado por
  hash y un resolver de dominio en código. El manifiesto describe el identificador,
  las variantes de diseño, sus claves de UI y la forma del payload; el resolver
  valida la referencia y produce un payload tipado.
- La variante de diseño es una clave semántica del conjunto declarado por el tipo,
  no una ruta de Blade. Si se omite, se usa la variante por defecto. Si el template
  efectivo no la implementa, hereda de Base; si la variante desaparece, se degrada
  a la variante por defecto de Base.
- El módulo valida y resuelve los datos; el CMS Template efectivo es propietario
  del marcado visible y recibe el payload tipado. La precedencia de UI catalogs
  sigue el patrón de ADR-0006.
- Un elemento con tipo desconocido o referencia no resoluble no ejecuta nada y se
  resuelve conforme a la regla de degradación definida en la implementación, sin
  exponer datos internos.
- El contrato de embeds no se materializa como ADR en este incremento; se fijará al
  consumirlo Media.

### Backoffice, HTTP y auditoría

- Las rutas de gestión quedan bajo el área administrativa, requieren sesión de
  superadministrador verificada en servidor y protección CSRF para mutaciones.
- El backoffice permite crear, editar, mover, cambiar template, publicar,
  despublicar y borrar (bajo restricciones) Pages; gestionar menús e ítems; y
  marcar o retirar la home.
- Los errores de validación conservan el estado existente y no exponen datos
  internos ni contenido sensible.
- Cada mutación con efecto durable produce los eventos correspondientes en la misma
  transacción. El evento registra Page, traducción y claves afectadas, actor,
  origen y momento, sin copiar el HTML completo ni crear versiones recuperables.
- No se expone API JSON pública ni se cambia el contrato de rutas públicas.

### Presentación y renderizado

- El backoffice obtiene el editor asociado al tipo registrado; no construye
  nombres de componente o imports desde datos de Page.
- La página pública usa una clave de presentación aprobada y un Blade resuelto por
  Core/CMS Template según ADR-0006. El tipo y los embeds entregan datos tipados y
  validados; no escogen la vista pública.
- El texto enriquecido se renderiza desde el HTML sanitizado, sin desactivar el
  escape de Blade de forma indiscriminada. Los embeds se resuelven en el punto de
  renderizado a través del contrato de presentación del template.
- La Page inicial "Under construction" sigue siendo una presentación estática
  integrada en Base; no es contenido TinyMCE.

### Seguridad y validación

- Validar en servidor tipo, locale, límites de entrada y reglas propias del
  contenido antes de persistir.
- Sanitizar el HTML de forma determinista en servidor conforme a la política
  explícita del tipo. La configuración del editor no amplía por sí sola los
  elementos o atributos permitidos.
- La política inicial de `rich-text` permite estructura y formato semánticos,
  incluidas tablas (`table`, `thead`, `tbody`, `tr`, `th`, `td`, `caption`),
  `pre`, `blockquote`, `div`, `section`, `article`, párrafos, encabezados,
  énfasis, listas, citas, separadores y enlaces con protocolos seguros. No se
  admiten `script`, `iframe`, objetos incrustados, SVG, atributos de eventos,
  `style` ni clases arbitrarias. Las imágenes se integrarán después mediante Media.
- Probar entradas hostiles representativas: scripts, atributos de eventos, URLs
  peligrosas, HTML malformado y payload sobredimensionado.
- Las mutaciones requieren autorización y CSRF; no se confía en botones ocultos,
  estado de Vue ni valores de configuración enviados por el cliente.
- No se registran secretos ni contenido HTML completo en logs ni en auditoría.
- El contenido, el tipo y los embeds no pueden seleccionar vistas Blade, clases,
  imports, scripts arbitrarios ni rutas de filesystem.

### Entrega y documentación

- La integración de TinyMCE como dependencia frontend revisa cambios directos y
  transitivos, licencia, compatibilidad, instalación reproducible, auditoría,
  pruebas y build conforme a `docs/architecture/dependency-security.md`.
- El entorno debe admitir al menos 4 MiB de contenido por petición; 16 MiB es la
  capacidad de petición recomendada del servidor, no el límite editorial.
- Actualizar las guías de administración de Pages y Menus, desarrollo/extensión de
  tipos y configuración del editor, el changelog y los roadmaps afectados.
- La matriz multi-motor SQLite, MySQL y MariaDB es obligatoria por las migraciones
  de esquema.

## 6. Calidad, seguridad y deuda

### Garantías obligatorias

- Aplicar pruebas HTTP a autorización del superadministrador, CSRF, flujos de
  gestión y errores de validación.
- Aplicar pruebas de integración a persistencia, operaciones de dominio, auditoría
  transaccional, registro de tipos y configuración del editor.
- Probar la home por ítem: una por idioma en todo el sistema, distinta por idioma,
  bloqueos de despublicación/borrado, confirmación al dejarla sin home y `404` sin
  home.
- Probar el borrado de Pages bloqueado por descendientes, home o referencias de
  menú, y el borrado atómico cuando se permite.
- Probar la creación de stubs para todos los idiomas instalados y al instalar un
  idioma nuevo, y que una traducción incompleta no es publicable.
- Probar la ausencia de auto-publicación y que la única Page publicada por
  instalación es "Under construction" con su ítem de home.
- Probar el fallback de contenido por clave según el modo global y el idioma
  predeterminado de frontend, incluido el cambio dinámico de ese idioma.
- Probar que un tipo externo puede integrarse desde un módulo instalado sin que
  Pages dependa de sus clases privadas o rutas internas, y que identificadores
  duplicados o proveedores inválidos fallan de forma controlada.
- Probar la sanitización en servidor con contenido válido e inputs hostiles;
  verificar que enviar HTML peligroso directamente no elude la política y que el
  fallback `textarea` conserva la sanitización.
- Probar que contenido, tipo y embeds no pueden seleccionar vistas Blade, clases,
  imports, scripts o rutas de filesystem.
- Probar que una falla de auditoría revierte la mutación y que los eventos no
  contienen el HTML completo.
- Probar el contrato de embeds con un proveedor de ejemplo: marcador válido,
  referencia resuelta, variante de diseño, fallback de template y degradación
  controlada.
- Ejecutar pruebas enfocadas antes de la suite afectada, Pint, build frontend y
  validaciones configuradas; ejecutar la matriz SQLite, MySQL 8.4 y MariaDB 11.4.

### Riesgos aceptados

- Cambiar slug o mover una Page publicada puede dejar la URL anterior con `404`
  hasta que exista el sistema de redirecciones. Se acepta por simplicidad del MVP,
  con la condición de planificar ese sistema en un incremento posterior.
- La configuración externa de TinyMCE no tiene interfaz administrativa en esta
  fase. El riesgo residual es mantener una configuración provista por código; se
  acepta solo si el contrato permite sustituirla por configuración externa por
  tipo sin alterar la persistencia ni el contrato de Pages.
- El contrato de embeds se define en esta Spec sin ADR; puede requerir ajustes al
  consumirlo Media, que se fijarán entonces.
- Durante desarrollo, las modificaciones de esquema de este incremento se
  verifican reconstruyendo la base local con `migrate:fresh`; no se conserva una
  ruta de actualización de datos. Esta excepción se revisará antes de persistir
  datos reales o desplegar fuera de desarrollo.

### Deuda técnica

- No aceptar deuda en autorización, auditoría, integridad o sanitización. La
  gestión externa de configuración, el sistema de redirecciones, el versionado y
  el permiso de `iframe` se difieren como capacidades independientes con condición
  de retomarse.

## 7. Criterios de aceptación

- **CA-01:** Solo el superadministrador accede a las pantallas de gestión de Pages
  y Menus; invitados y usuarios sin privilegios reciben el comportamiento de
  acceso establecido. Las mutaciones rechazan CSRF inválido.
- **CA-02:** La home se marca desde un ítem de menú; existe como máximo una por
  idioma en todo el sistema y puede ser distinta por idioma. Marcar un ítem exige
  Page pública y disponible en el idioma del ítem; despublicar la traducción, un
  ancestro o borrar la Page home se bloquea. Dejar un idioma sin home exige
  confirmación explícita y su raíz responde `404`.
- **CA-03:** Al recibir la raíz, Pages resuelve el idioma y consulta a Navigation
  la Page de home; Navigation devuelve el destino y Pages valida y sirve la Page.
  Para rutas con slugs, Navigation no interviene.
- **CA-04:** Una Page no se borra si tiene descendientes, está marcada como home o
  es referenciada por un ítem de menú. El borrado permitido es físico y atómico,
  elimina Page, traducciones y contenidos, y queda auditado. No se puede eliminar
  una traducción individualmente.
- **CA-05:** El tipo pertenece a la Page, es inmutable y compartido por todas sus
  traducciones. Un identificador desconocido, duplicado o un proveedor inválido
  falla de forma controlada sin reinterpretar el contenido.
- **CA-06:** Al crear una Page se generan stubs para todos los idiomas instalados;
  al instalar un idioma nuevo se crean stubs para las Pages existentes. Una
  traducción sin `title` o `slug` válidos no es publicable.
- **CA-07:** Ninguna Page creada por el usuario se publica automáticamente; la
  publicación es manual. La instalación publica solo "Under construction" con un
  menú y un ítem de home apuntándola.
- **CA-08:** `PageTranslationContent` guarda clave y contenido con unicidad por
  traducción y clave; el contenido es HTML sanitizado en servidor, limitado a 4 MiB
  y almacenado en `longtext`. El HTML original no se conserva.
- **CA-09:** Una clave ausente o vacía usa el fallback: en modo `base`, el valor
  del idioma predeterminado de frontend y después la clave literal; en modo `key`,
  la clave literal. El idioma de fallback sigue dinámicamente el predeterminado de
  frontend.
- **CA-10:** El módulo TinyMCE queda separado de Pages y del tipo; el tipo declara
  el editor y el módulo del editor aplica la política. Si la interfaz falla, se
  usa `textarea` con la misma sanitización; si falta el sanitizador servidor, el
  guardado se bloquea.
- **CA-11:** El servidor valida y sanitiza el HTML según la política del tipo;
  payloads enviados sin TinyMCE no eluden la política. No se admiten `script`,
  `iframe`, SVG, atributos de eventos, `style` ni clases arbitrarias.
- **CA-12:** Los embeds usan un marcador tipado con tipo registrado, referencia
  validada y variante de diseño validada; el contenido no selecciona Blade, clase,
  ruta ni import. El template efectivo es propietario del marcado y se aplica
  fallback a Base.
- **CA-13:** El cambio de slug o el movimiento de una Page publicada se permite;
  el movimiento es atómico y se rechaza si deja inaccesible una traducción que
  estaba publicada. Las URLs anteriores pueden responder `404`.
- **CA-14:** Las mutaciones web escriben auditoría durable en la misma transacción;
  un fallo revierte el cambio y el historial no conserva el HTML completo.
- **CA-15:** Los assets de TinyMCE se cargan solo en las pantallas que los
  necesitan; la web pública no los descarga.

## 8. Trazabilidad de pruebas y documentación

| Criterio | Riesgo cubierto | Nivel de prueba | Evidencia esperada | Impacto documental |
| :--- | :--- | :--- | :--- | :--- |
| CA-01 | Acceso administrativo no autorizado | HTTP | Superadmin permitido, otros denegados y CSRF verificado | Guía de acceso al backoffice |
| CA-02 | Home duplicada, mal asignada o raíz sin destino | Integración/HTTP | Una home por idioma, bloqueos y confirmación verificados | Guía de Pages y Menus |
| CA-03 | Ruta raíz que no usa el destino del menú | Integración/HTTP | Destino de Navigation validado y servido por Pages | Arquitectura Pages/Navigation |
| CA-04 | Borrado parcial o con referencias | Integración | Bloqueos y borrado atómico verificados | Guía de Pages y Menus |
| CA-05 | Tipo ambiguo o proveedor inválido | Integración/módulos | Tipo inmutable y rechazos controlados | Guía de extensión de Pages |
| CA-06 | Traducción ausente o no publicable | Integración/BD | Stubs por idioma y traducción incompleta no publicable | Guía de Pages/localización |
| CA-07 | Publicación accidental | Integración | Sin auto-publicación y seed verificado | Guía de publicación |
| CA-08 | Contenido sin sanitizar o sin límite | Integración/seguridad | Sanitización, 4 MiB y `longtext` verificados | Guía de contenido enriquecido |
| CA-09 | Fallback en idioma incorrecto | Integración/HTTP | Modo global y predeterminado de frontend aplicados | Guía de contenido/localización |
| CA-10 | Editor acoplado o sin degradación segura | Integración/frontend | Separación, `textarea` y bloqueo sin sanitizador | Guía de extensión TinyMCE |
| CA-11 | XSS almacenado o bypass de editor | Integración/seguridad | Suite de sanitización en servidor | Guía de contenido enriquecido |
| CA-12 | Selección de código o vista desde embeds | Integración/HTTP | Marcador validado y marcado por template | Arquitectura de presentaciones |
| CA-13 | Path roto o movimiento parcial | Integración | Movimiento atómico y rechazo por inaccesibilidad | Guía de Pages |
| CA-14 | Mutación sin historial o fuga | Integración/auditoría | Rollback conjunto y minimización | Guía de auditoría |
| CA-15 | Editor descargado en la web pública | Build/frontend/HTTP | Assets solo en backoffice | Guía de Pages |

## 9. Plan de implementación

1. Probar e implementar la migración y las invariantes de dominio (tipos,
   `page_translation_contents`, stubs por idioma, ausencia de auto-publicación,
   home por ítem y bloqueos) hasta CA-04 a CA-09.
2. Probar e implementar el registro de tipos, el tipo `rich-text`, la integración
   TinyMCE separada y la sanitización en servidor, con el fallback `textarea`,
   hasta CA-05, CA-08, CA-10 y CA-11.
3. Probar e implementar el contrato de embeds con un proveedor de ejemplo y la
   resolución de presentación por template, hasta CA-12.
4. Probar e implementar las pantallas de gestión de Pages y Menus, la selección de
   home, el movimiento y el borrado, hasta CA-01 a CA-04 y CA-13.
5. Conectar auditoría transaccional sin HTML completo, verificar carga bajo demanda
   del editor, ejecutar quality gates, matriz multi-motor y actualizar la
   documentación, changelog y roadmaps, hasta CA-14 y CA-15.

## 10. Decisiones abiertas

No aplica. Las decisiones de producto y arquitectura se cerraron durante la
planificación y quedan reflejadas en los bloques técnicos. El contrato de embeds
se fijará como ADR cuando Media lo consuma, y los elementos diferidos tienen
condición de retomarse explícita.
