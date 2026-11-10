# SPEC: Identidad interna, recorridos y UX del backoffice de Pages y Menus

- **Estado:** Revisión
- **Perfil:** feature
- **Origen de la planificación:** Petición del responsable del proyecto para
  diferenciar la identidad interna de una Page de sus traducciones y mejorar la
  usabilidad del backoffice de Pages y Menus.
- **Spec relacionada:** [SPEC-pages-backoffice](SPEC-pages-backoffice.md),
  completada.

## 1. Objetivo o problema

El backoffice identificaba las Pages por su ID. Este dato no permitía reconocer
con facilidad el destino de un ítem de menú ni el padre de una Page. Además, las
pantallas administrativas reunían en una misma vista el listado y todos los
formularios de creación o gestión, dificultando localizar la acción deseada. La
edición de una Page muestra una sección por traducción e inicializa todos los
editores TinyMCE al cargar la pantalla, un coste que crece linealmente con los
idiomas y con los campos editoriales de cada tipo.

La pantalla de acceso tampoco carga el stylesheet administrativo ni aplica los
componentes visuales del backoffice. Los assets actuales usan nombres genéricos
aunque solo se consumen desde administración, dificultando garantizar que el
sitio público no descargue CSS o JavaScript administrativo.

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
- Mostrar mensajes flash de éxito, aviso y error con código de semáforo, y
  redirigir a la lista del mismo tipo tras crear o guardar un elemento.
- Marcar visualmente Pages despublicadas y, en Menus, el ítem home y los
  destinos cuyo Page o traducción del idioma no están publicados.
- Separar el listado, la creación y la edición de Pages en `/admin/pages`,
  `/admin/pages/new` y `/admin/pages/{page}` respectivamente.
- Separar el listado, la creación y el detalle de Menus en `/admin/menus`,
  `/admin/menus/new` y `/admin/menus/{menu}` respectivamente.
- Organizar en pestañas accesibles, sin recarga al cambiar de pestaña, los
  ítems existentes, el formulario de creación de ítem y la retirada de home del
  detalle de un Menu. Las mutaciones mantienen el ciclo HTTP normal.
- Mostrar el árbol administrativo de cada idioma en orden jerárquico, con
  hermanos ordenados por posición, profundidad visual y la Page destino como
  información secundaria del label.
- Sustituir la posición numérica libre de los formularios web de Menu por un
  selector de huecos de inserción válidos para el idioma y padre elegidos. Al
  cambiar el destino, la interfaz selecciona el último hueco por defecto; el
  servidor sigue recibiendo y validando `position` como entero.
- Mostrar los candidatos a padre como `Label (#ID)` y permitir elegir padre y
  posición también al crear un ítem.
- Presentar como insignias separadas los locales para los que una Page es home.
- Organizar las traducciones de una Page en `/admin/pages/{page}` mediante
  pestañas cuando haya entre uno y cinco idiomas instalados, o mediante selector
  filtrable cuando haya seis o más.
- Mostrar el estado publicado o despublicado de la traducción activa y evitar
  repetir visualmente su locale bajo el selector de idioma cuando la interfaz
  esté mejorada con JavaScript, conservando una identificación legible de cada
  traducción en el fallback sin JavaScript.
- Inicializar TinyMCE únicamente para la traducción de Page activa y destruir sus
  instancias al cambiar de traducción, manteniendo el contenido sin guardar en
  los `textarea` de origen.
- Aplicar la capa visual administrativa a la pantalla de acceso, conservando su
  autenticación, CSRF, rate limiting, mensajes y rutas.
- Separar las entradas de Vite por área: CSS administrativo y CSS público; los
  módulos JavaScript administrativos quedan bajo un namespace administrativo y
  no se cargan en presentaciones públicas.
- Integrar la presentación de historial de auditoría en la capa visual y de
  navegación común del backoffice, sin cambiar su consulta, filtros, paginación,
  autorización ni retención de datos.
- Cargar una capa de estilos común para dashboard, listado y creación de Pages,
  listado y creación de Menus y edición de Page. Debe ser mobile-first,
  responsive, usable con teclado y conforme con WCAG AA.
- Conservar autorización, CSRF, persistencia de relaciones, reglas de
  publicación, home, jerarquía y las operaciones de dominio existentes.

### Fuera de alcance

- Cambiar títulos o slugs desde una pantalla distinta de la edición localizada
  existente.
- Usar `title` o `slug` como identificador interno, o exponer `Page.name` en
  rutas, navegación pública, HTML público, contenido editorial o API.
- PageBuilder, Vue, SPA, Inertia, Media y rediseño de pantallas administrativas
  que no sean dashboard, Login, auditoría, Pages, Menus y edición de Page.
- Crear rutas de edición independientes para cada ítem de Menu. La edición,
  movimiento, borrado y marcado de home de un ítem continúan en el detalle de su
  Menu.
- Cambiar los endpoints `POST` existentes, su autorización, sus validaciones o
  sus reglas de dominio.
- Aceptar o corregir silenciosamente en servidor una posición fuera del rango
  válido. El dominio conserva el rechazo atómico prescrito por Navigation.
- Añadir dependencias frontend, Vue, SPA, Inertia, API JSON o persistencia cliente
  para seleccionar una traducción o gestionar TinyMCE.
- Rediseñar el contenido, estructura o comportamiento público más allá de cargar
  su stylesheet propio sin assets administrativos.
- Cambiar los contratos funcionales de Menus, la selección de home o la auditoría
  existente salvo incluir el nombre en los estados de auditoría cuando la creación
  ya la registra y mejorar su presentación.

### Alcance diferido

- La edición o renombrado de `Page.name` se evaluará cuando exista una necesidad
  operativa verificable. Mientras no se especifique, el nombre se establece al
  crear la Page y no se expone una mutación posterior.
- La búsqueda remota y los selectores enriquecidos para catálogos de Pages se
  retomarán si el selector filtrable nativo de traducciones deja de ser usable.

## 4. *Clash check*

- **SDD inicial:** concreta el patrón Entity-Translation: `name` pertenece a la
  identidad estable `Page`, mientras `title` y `slug` permanecen en
  `PageTranslation`. No afecta a las URLs canónicas ni a los árboles visuales de
  Menus.
- **SPEC-pages-foundation:** amplía el esquema de `pages` sin trasladar título o
  slug fuera de las traducciones. El comando de creación cambia porque una nueva
  identidad obligatoria debe recibirse en todos los orígenes de creación.
- **SPEC-pages-backoffice:** conserva el acceso de superadministrador, CSRF, las
  validaciones de dominio y la auditoría transaccional. Esta ampliación sustituye
  la composición de las pantallas administrativas y añade rutas `GET` de
  navegación; conserva los endpoints `POST` y no altera reglas de dominio.
- **SPEC-navigation-foundation:** conserva posiciones contiguas entre hermanos y
  el rechazo atómico de posiciones fuera de rango. El selector web representa
  huecos válidos como enteros y deja que `MenuManager` siga reindexando; no
  introduce una posición global ni autoajuste silencioso en servidor.
- **ADR-0001:** mantiene Blade como base. La interfaz no añade Vue, SPA ni
  Inertia; un módulo JavaScript nativo y acotado activa las pestañas solo en el
  detalle de Menu y otro coordina la selección de traducción y TinyMCE en la
  edición de Page. Vite carga cada módulo únicamente en la vista que lo necesita.
- **SPEC-pages-backoffice:** conserva que TinyMCE se carga solo en la pantalla
  administrativa que lo requiere y se degrada a `textarea` sin JavaScript. Esta
  ampliación concreta su ciclo de vida para no inicializar editores ocultos; no
  cambia la sanitización ni la validación del servidor.
- **Código y assets:** el stylesheet actual es administrativo de facto y la
  presentación pública no carga assets. Esta ampliación los separa por entradas
  explícitas sin añadir dependencias, runtime público ni cambios en los contratos
  de renderizado.
- **Auditoría administrativa:** la vista actual carga el stylesheet administrativo
  pero no incluye la navbar ni los componentes comunes. Esta ampliación alinea su
  marcado con el resto del backoffice sin alterar `AuditController`, sus filtros
  `GET`, su paginación ni el acceso de superadministrador.
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
- `/admin/pages` muestra exclusivamente el listado y su acción enlaza a
  `/admin/pages/new`. `/admin/pages/new` contiene el formulario de creación y
  `/admin/pages/{page}` conserva la edición localizada existente.
- `/admin/menus` muestra exclusivamente el listado y sus acciones enlazan a
  `/admin/menus/new` y al detalle de cada Menu. `/admin/menus/new` contiene el
  formulario de creación. `/admin/menus/{menu}` muestra exclusivamente los ítems
  del Menu seleccionado y sus acciones por ítem.
- El detalle de Menu muestra tres paneles de pestaña en este orden: `Items` /
  `Elementos`, seleccionado inicialmente; `Add item` / `Añadir elemento`; y
  `Remove home` / `Quitar inicio`. Cada pestaña usa botones con roles `tab`,
  `tablist` y `tabpanel`, atributos ARIA sincronizados y navegación por teclado
  con flechas, Inicio y Fin. Sin JavaScript, la lista y ambos formularios
  permanecen utilizables y visibles.
- El cambio de pestaña no envía una petición HTTP ni recarga la página. El envío
  de un formulario conserva las mutaciones y redirecciones HTTP del servidor.
- Los selectores de padre de creación y movimiento muestran cada candidato como
  `Label (#ID)`, con un espacio antes del paréntesis. Solo ofrecen ítems del mismo
  Menu e idioma; el movimiento excluye el propio ítem y sus descendientes. La
  opción raíz continúa localizada.
- La creación web permite elegir `parent_id` y `position` mediante los campos
  opcionales ya aceptados por el endpoint. Cambiar el idioma filtra los padres y
  huecos disponibles antes de enviar el formulario.
- La posición web es un selector de huecos de inserción calculados sobre los
  hermanos del padre e idioma elegidos, excluyendo el propio ítem al mover. Las
  opciones identifican el primer hueco, los huecos posteriores a un hermano por
  `Label (#ID)` y el último hueco. Al cambiar idioma o padre con JavaScript se
  reconstruyen las opciones y se selecciona el último hueco. El valor enviado en
  `position` sigue siendo el entero entre `1` y `n + 1` que consume
  `MenuManager`; una entrada manipulada fuera de rango se rechaza, no se ajusta.
- Sin JavaScript, los formularios siguen enviando los contratos HTTP existentes
  y ofrecen una opción válida inicial; la seguridad e integridad no dependen del
  filtrado cliente.
- El listado administrativo agrupa por idioma y recorre cada árbol en profundidad:
  cada padre precede a sus descendientes y los hermanos se ordenan por la
  `position` persistida. Una marca o sangría no textual refleja la profundidad
  sin sustituir el label accesible.
- Las columnas del listado se ordenan como `Position`, `Label`, `Language` y
  `Actions`. La celda de label conserva las insignias de home y disponibilidad y
  muestra debajo la Page destino como metadato compacto; el ID deja de ser una
  columna independiente, pero permanece visible donde identifica padres y
  acciones.
- La edición de Page agrupa los formularios de sus traducciones en un único
  selector de idioma. Con uno a cinco idiomas instalados muestra pestañas; con
  seis o más muestra la etiqueta localizada `Current language:` / `Idioma
  actual:`, un campo de búsqueda y un selector HTML nativo filtrado por locale y
  nombre del idioma. No se introduce un combobox ni dependencia externa.
- La traducción activa inicial es la del locale de backoffice cuando exista; tras
  un error de guardado se activa la traducción del locale enviado. Cambiar de
  idioma no persiste contenido ni envía una petición HTTP.
- Cada panel de traducción muestra una insignia localizada `Published` /
  `Publicada` o `Unpublished` / `Despublicada` según
  `PageTranslation.is_published`. El indicador representa la traducción del
  idioma seleccionado, no el estado estructural global de `Page`; al cambiar de
  panel cambia con ella sin realizar una petición adicional.
- Con JavaScript, la pestaña o el selector nativo ya identifica el idioma activo
  y no se repite inmediatamente debajo como encabezado visible. Sin JavaScript,
  todos los paneles conservan un encabezado visible y legible con el nombre del
  idioma y su locale para distinguir cada formulario.
- Sin JavaScript, todos los formularios de traducción permanecen visibles y
  utilizables como `textarea`. Con JavaScript, los paneles no activos se ocultan
  y no contienen una instancia TinyMCE activa.
- El módulo de edición de traducciones inicializa TinyMCE solo dentro del panel
  activo. Antes de ocultar un panel, sincroniza cada editor con su `textarea` y
  destruye sus instancias; al activarlo de nuevo las inicializa a partir de esos
  valores. Así, el máximo de instancias activas equivale a los campos editoriales
  visibles de una única traducción, no al número total de idiomas.
- La coordinación de pestañas y editor se concentra en un único punto de entrada
  de Vite para la edición de Page, evitando que un inicializador genérico cree
  TinyMCE antes de ocultar las traducciones inactivas.
- La pantalla de acceso carga el stylesheet administrativo y usa componentes
  semánticos de formulario y alerta ya disponibles o específicos del acceso. Es
  responsive, mantiene labels asociados, foco visible, contraste WCAG AA y roles
  de alerta para credenciales inválidas o rate limiting.
- La presentación de auditoría incluye la navbar compartida y usa `c-adminshell`,
  `c-adminform`, `c-adminalert` y `c-admintable` o componentes equivalentes de la
  misma metodología. Conserva los filtros por operación, entidad y fechas, sus
  valores tras un error y la paginación, sin transformar la consulta `GET` en una
  mutación.
- Vite expone `resources/css/admin.css` para las presentaciones administrativas y
  `resources/css/public.css` para las públicas. Los módulos `menu-tabs`, la
  selección de traducciones y TinyMCE se ubican bajo `resources/js/admin/` y se
  cargan solo en sus vistas administrativas. No se crea una entrada JavaScript
  pública vacía mientras no exista interactividad pública.
- Las presentaciones adicionales se declaran en `CmsPresentation`, el manifiesto
  del template Base y su validador de UI catalogs. No se usa una vista elegida a
  partir de datos persistidos.

### Feedback y redirecciones

- Las mutaciones correctas emiten un mensaje flash tipado (`success`, `warning`
  o `error`) cuya clave se resuelve desde el UI catalog del template efectivo.
- `success` usa `role="status"`; `warning` y `error` usan `role="alert"`.
- Cada tipo se distingue por color de semáforo, borde y texto; la información no
  depende solo del color y mantiene contraste WCAG AA.
- Los errores de validación y de dominio se muestran como alerta de error,
  sustituyendo los avisos inline duplicados.
- Tras crear, guardar o editar un elemento, la respuesta redirige a la lista del
  mismo tipo: Pages a `/admin/pages` y Menus a `/admin/menus`. Los errores
  conservan la pantalla actual para corregir.
- Crear una Page o un Menu correcto redirige a su listado. Crear, guardar, mover,
  borrar o cambiar la home de un ítem redirige al detalle de su Menu; los errores
  de esos formularios conservan ese detalle. Esta regla sustituye la redirección
  previa de las mutaciones de ítem al listado global de Menus.
- Despublicar una Page y retirar una home emiten un aviso que explica el `404`
  temporal hasta republicar o asignar otra home.

### Marcas de estado

- Una Page despublicada muestra una insignia textual `Unpublished` /
  `Despublicada` junto a su nombre en el listado.
- La edición de Page muestra una insignia textual `Published` / `Publicada` o
  `Unpublished` / `Despublicada` dentro de cada panel según el estado de esa
  traducción. La insignia activa cambia al seleccionar otro idioma porque forma
  parte del panel seleccionado.
- En el listado de Menus, el ítem marcado como home muestra una insignia
  `Home` / `Inicio`.
- Un ítem de menú muestra `Unpublished` / `Despublicada` cuando su Page no está
  publicada o la traducción de ese idioma no está publicada o no existe.
- Las insignias usan `c-adminbadge`, combinan texto y color y mantienen contraste
  WCAG AA; no dependen solo del color.
- En el listado de Pages, cada locale donde la Page es home se muestra como una
  insignia independiente dentro de un grupo, en lugar de concatenarse como texto
  sin separación visual.

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
- Probar por HTTP ambos umbrales de selección de traducción, el locale inicial y
  la recuperación del locale con error. Probar el marcado y el módulo de editor
  para demostrar que la instancia activa queda limitada al panel seleccionado,
  que el cambio sincroniza el `textarea` y que el fallback sin JavaScript no
  oculta formularios. Verificar en navegador el foco, búsqueda y teclado.
- Probar por HTTP que cada panel presenta el estado real de su traducción y que
  los catálogos localizan ambos estados. Verificar en navegador que la interfaz
  mejorada no repite el locale bajo el selector y que el fallback sin JavaScript
  mantiene encabezados distinguibles.
- Probar por HTTP el texto `Label (#ID)` de los padres, la exclusión de
  descendientes, las tres pestañas, las columnas, el orden depth-first, la
  profundidad visual, el metadato de Page y las insignias de locales home.
- Probar en JavaScript que cambiar idioma o padre reconstruye únicamente huecos
  válidos, selecciona el último y mantiene `position` numérica. Probar por HTTP e
  integración que crear directamente bajo un padre usa el hueco elegido y que
  entradas manipuladas fuera de rango siguen rechazándose sin mutación parcial.
- Probar por HTTP que Login carga únicamente el stylesheet administrativo y que
  la presentación pública carga únicamente su stylesheet público, sin módulos ni
  CSS de administración. Ejecutar el build y revisar el manifest resultante.
- Probar por HTTP que Auditoría carga navbar y stylesheet administrativo, conserva
  los filtros y errores existentes y presenta su tabla de forma accesible y
  utilizable en móvil.
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
- **CA-08:** Las mutaciones correctas muestran una alerta tipada con semáforo,
  texto localizado y rol accesible; los errores de validación o dominio se
  muestran como alerta de error.
- **CA-09:** Crear, guardar o editar un elemento redirige a la lista de su tipo
  (`/admin/pages` o `/admin/menus`) y muestra el mensaje correspondiente; los
  errores conservan la pantalla actual.
- **CA-10:** El listado de Pages marca visual y textualmente las Pages
  despublicadas sin alterar sus datos ni acciones.
- **CA-11:** En Menus, el ítem home se identifica con una insignia y los ítems
  cuyo destino no es público muestran una insignia, considerando la Page y la
  traducción del idioma del ítem.
- **CA-12:** El superadministrador dispone de los recorridos `/admin/pages`,
  `/admin/pages/new` y `/admin/pages/{page}` para listar, crear y editar Pages;
  el listado no contiene el formulario de creación y los errores de creación se
  muestran en `/admin/pages/new` conservando la entrada.
- **CA-13:** El superadministrador dispone de los recorridos `/admin/menus`,
  `/admin/menus/new` y `/admin/menus/{menu}` para listar, crear y gestionar
  Menus. El detalle muestra solo los ítems del Menu seleccionado; las mutaciones
  de ítem vuelven a ese detalle sin cambiar sus reglas de dominio.
- **CA-14:** El detalle de Menu organiza los formularios genéricos de añadir ítem
  y retirar home mediante pestañas operables con teclado y lectores de pantalla.
  Cambiar de pestaña no recarga ni muta el servidor; sin JavaScript, ambos
  formularios siguen disponibles.
- **CA-15:** La edición de una Page presenta una única traducción activa. Con un
  máximo de cinco idiomas instalados usa pestañas accesibles; con seis o más usa
  una etiqueta localizada, búsqueda que filtra las opciones y selector HTML
  nativo. Se activa inicialmente el locale de backoffice o, tras un error, el
  locale enviado; cambiar de traducción no persiste ni recarga la pantalla.
- **CA-16:** En la edición de Page con JavaScript, TinyMCE se inicializa solo para
  los campos editoriales de la traducción activa. Al ocultarla, su contenido se
  copia al `textarea` y las instancias se destruyen; al reactivarla se restauran
  desde ese valor. Sin JavaScript, todos los formularios permanecen editables y
  no se pierde el comportamiento de guardado ni la validación/sanitización de
  servidor.
- **CA-17:** Login usa la capa visual administrativa responsive y accesible, sin
  alterar autenticación, CSRF, rate limiting, mensajes ni rutas.
- **CA-18:** Las presentaciones administrativas cargan solo `admin.css` y sus
  módulos JavaScript específicos; las presentaciones públicas cargan solo
  `public.css` y no descargan CSS, TinyMCE ni JavaScript de administración.
- **CA-19:** La pantalla de auditoría comparte navbar, estructura y componentes
  accesibles del backoffice, sin alterar autorización, filtros `GET`, paginación,
  alertas de validación ni los eventos visibles.
- **CA-20:** La edición de Page muestra dentro del panel activo una insignia
  localizada con el estado publicado o despublicado de la traducción seleccionada
  y la sustituye al cambiar de idioma sin petición HTTP. Con JavaScript no repite
  visualmente el locale bajo las pestañas o el selector; sin JavaScript mantiene
  un encabezado legible por traducción para distinguir todos los formularios.
- **CA-21:** El detalle de Menu presenta `Elementos`, `Añadir elemento` y `Quitar
  inicio` como tres pestañas accesibles en ese orden, con `Elementos` activa al
  cargar. Cambiar de pestaña no recarga ni muta el servidor; sin JavaScript los
  tres paneles permanecen disponibles.
- **CA-22:** Los selectores de padre muestran `Label (#ID)`, se limitan al Menu e
  idioma aplicables y, al mover, no ofrecen el propio ítem ni sus descendientes.
  Crear un ítem permite elegir directamente su padre.
- **CA-23:** Crear o mover un ítem usa un selector de huecos de inserción que
  identifica primer, último y posiciones posteriores a hermanos. Al cambiar
  idioma o padre se selecciona el último hueco válido. Se sigue enviando una
  `position` entera y el servidor rechaza entradas manipuladas fuera de rango sin
  autoajustarlas ni dejar reindexado parcial.
- **CA-24:** El listado de ítems agrupa por idioma y muestra el árbol depth-first,
  con padres antes que descendientes, hermanos por posición y profundidad visual.
  Sus columnas son `Position`, `Label`, `Language` y `Actions`; la Page destino
  aparece como metadato secundario bajo el label.
- **CA-25:** Los locales para los que una Page es home se presentan en su listado
  como insignias independientes y accesibles.

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
| CA-08 | Falta de feedback o mensaje inaccesible | HTTP e integración de catálogos | Alerta visible, tipada, localizada y con rol correcto | `docs/administration/pages-and-menus.md` |
| CA-09 | Guardado sin retorno a la lista o mensaje ausente | HTTP | Redirect a la lista y flash visible | `docs/administration/pages-and-menus.md` |
| CA-10 | Page despublicada sin señal visual | HTTP | Insignia textual y contraste suficiente | `docs/administration/pages-and-menus.md` |
| CA-11 | Home o destino no público sin identificar | HTTP | Insignias de home y destino no público | `docs/administration/pages-and-menus.md` |
| CA-12 | Creación mezclada con el listado o datos perdidos en error | HTTP | Rutas protegidas, formulario aislado y entrada preservada | `docs/administration/pages-and-menus.md` |
| CA-13 | Gestión de ítems en el Menu equivocado o retorno desorientador | HTTP | Detalle aislado por Menu y redirects correctos | `docs/administration/pages-and-menus.md` |
| CA-14 | Pestañas inaccesibles o que envían peticiones al cambiar | HTTP, prueba de asset y revisión de teclado | Marcado ARIA, fallback y módulo aislado | `docs/administration/pages-and-menus.md` |
| CA-15 | Traducción inlocalizable o selector inusable con muchos idiomas | HTTP, prueba de asset y revisión de teclado | Umbral, locale activo, búsqueda y fallback | `docs/administration/pages-and-menus.md` |
| CA-16 | Saturación del navegador por editores ocultos o pérdida de borrador | Prueba de asset, HTTP y revisión de navegador | Inicialización/destrucción por panel y sincronización de `textarea` | `docs/administration/pages-and-menus.md` y `docs/developers/pages.md` |
| CA-17 | Login ilegible o regresión del acceso | HTTP, revisión de marcado y build | Asset administrativo, formulario y alertas accesibles | `docs/user-guide/backoffice-access.md` |
| CA-18 | Fuga de assets administrativos a la web pública | HTTP, build y revisión de manifest | Entradas separadas y carga selectiva | `docs/developers/frontend-styling.md` y `docs/developers/pages.md` |
| CA-19 | Historial aislado, sin navegación o filtros degradados | HTTP y revisión de marcado/CSS | Navbar, filtros, alertas, tabla responsive y paginación | `docs/user-guide/backoffice-access.md` y `docs/administration/admin-audit.md` |
| CA-20 | Estado editorial ambiguo o locale redundante | HTTP, catálogos y revisión de navegador | Insignia por traducción, cambio con el panel y encabezado solo como fallback | `docs/administration/pages-and-menus.md` |
| CA-21 | Lista y formularios compiten visualmente | HTTP, JavaScript y teclado | Tres pestañas, selección inicial y fallback sin JavaScript | `docs/administration/pages-and-menus.md` |
| CA-22 | Padre ambiguo o ciclo seleccionable | HTTP e integración | Label e ID visibles; candidatos válidos | `docs/administration/pages-and-menus.md` |
| CA-23 | Posición inválida o intención de orden confusa | HTTP, JavaScript e integración | Huecos válidos, último predeterminado y rechazo servidor | `docs/administration/pages-and-menus.md` |
| CA-24 | Jerarquía y orden administrativo ilegibles | HTTP y revisión responsive | Recorrido depth-first, profundidad y columnas acordadas | `docs/administration/pages-and-menus.md` |
| CA-25 | Idiomas home visualmente concatenados | HTTP y revisión de marcado/CSS | Una insignia por locale home | `docs/administration/pages-and-menus.md` |

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
4. Implementar el feedback tipado, las redirecciones a la lista y sus pruebas
   HTTP en ambos locales.
5. Añadir las insignias de estado para Pages y Menus y sus pruebas HTTP.
6. Separar las presentaciones, rutas `GET` y flujos de error de Pages y Menus;
   añadir el detalle de Menu y las pestañas progresivamente mejoradas.
7. Añadir la selección adaptativa de traducciones y el ciclo lazy de TinyMCE en
   la edición de Page, con fallback sin JavaScript y pruebas de regresión.
8. Separar las entradas de assets administrativo y público, aplicar el estilo de
   Login y comprobar la carga selectiva de cada presentación.
9. Integrar Auditoría en la presentación administrativa común y cubrir sus
   filtros, alertas, paginación, navegación y responsividad.
10. Mostrar el estado localizado de cada traducción en su panel y eliminar la
    repetición visual del locale en la interfaz mejorada, conservando el
    encabezado identificativo sin JavaScript.
11. Reorganizar el detalle de Menu en tres pestañas, representar su árbol y
    columnas, añadir selectores de padre legibles y huecos de inserción dinámicos,
    y presentar los locales home de Pages como insignias.
12. Actualizar guías de administración y desarrollo, ejecutar pruebas enfocadas,
   suite afectada, Pint, build frontend, matriz multi-motor y revisar el diff.

## 10. Decisiones abiertas

No aplica. `Page.name` tiene un máximo de 150 caracteres Unicode y permanece
inmutable durante este incremento. `name_key` persiste la forma NFC y en
minúsculas para imponer unicidad portable sin distinguir mayúsculas/minúsculas;
la Page de sistema recibe el nombre "Under construction". Las pestañas de
traducción cambian a selector filtrable al alcanzar seis idiomas instalados; el
umbral evita una fila de pestañas inmanejable sin introducir navegación cliente
entre pantallas, edición individual de ítems ni dependencia frontend adicional.
Los selectores de posición de Menu eligen el último hueco al cambiar de padre o
idioma; no corrigen silenciosamente en servidor una posición manipulada.
