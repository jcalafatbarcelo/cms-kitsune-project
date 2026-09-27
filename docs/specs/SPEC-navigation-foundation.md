# SPEC: Fundación de Navigation

- **Estado:** Propuesta
- **Perfil:** feature
- **Origen de la planificación:** Cuarto incremento del
  [roadmap de contenido, templates y navegación](../architecture/content-delivery-roadmap.md).
- **Spec relacionada:** [SPEC-pages-foundation](SPEC-pages-foundation.md) y
  [SPEC-public-localized-routes](SPEC-public-localized-routes.md), completadas.

## 1. Objetivo o problema

Entregar el primer incremento vertical de Navigation: un módulo que mantenga
menús como árboles visuales independientes de la jerarquía de Pages, con ítems
localizados que solo ofrezcan destinos de Page públicamente disponibles en el
idioma efectivo.

La Spec no autoriza implementación hasta que sea aprobada explícitamente y el
ADR que gobierna el contrato entre Pages y Navigation esté aceptado.

## 2. Contexto y evidencia

Pages ya separa identidad y traducciones, resuelve la disponibilidad en cascada
y publica rutas canónicas localizadas. El resolvedor de rutas conserva la
autoridad sobre la URL y no expone todavía un contrato público para que otro
módulo obtenga la URL canónica de una PageTranslation. No existe módulo
Navigation, persistencia de menús, ítems localizados ni presentación pública
de menús.

El SDD exige que el árbol de navegación visual sea independiente del árbol de
URLs canónicas. El roadmap exige ítems traducibles y visibilidad según
disponibilidad pública, sin otorgar a Navigation propiedad sobre slugs, locale,
selección HTTP o publicación de Pages.

## 3. Alcance

- Crear el módulo de dominio `Navigation` mediante `nwidart/laravel-modules`.
- Persistir menús y un árbol visual de ítems separado de la jerarquía de Pages.
- Identificar cada menú mediante una clave técnica única e inmutable, para que un
  template Blade solicite explícitamente el menú que necesita sin imponer una
  posición visual global.
- Asociar cada ítem, su etiqueta, posición y relación padre-hijo a un idioma
  instalado, permitiendo árboles distintos para cada idioma de un mismo menú.
- Cada ítem referencia una Page estable y obtiene su URL canónica localizada
  exclusivamente a través de un contrato controlado por Pages.
- Resolver un menú para un idioma efectivo, omitiendo ítems cuyo destino de Page
  no sea públicamente disponible en ese idioma.
- Proporcionar operaciones Artisan no interactivas y reproducibles para el ciclo
  de vida que se apruebe en esta Spec.
- Renderizar la primera presentación pública de menú con Blade, sin cargar Vue ni
  alterar el resolvedor de rutas de Pages.

### Fuera de alcance

- Cambiar la jerarquía, slugs, disponibilidad, canonicidad, prefijos, sesión o
  negociación HTTP de Pages.
- Selector visual de idioma, redirección entre traducciones, cookies persistentes
  o preferencias de usuario.
- Backoffice, autenticación, autorización web y auditoría administrativa durable.
- PageBuilder, bloques, assets, componentes Vue, APIs JSON, sitemap, SEO,
  analítica y menús gestionados por CMS Templates.
- Destinos externos, URLs arbitrarias, HTML enriquecido en etiquetas y cualquier
  contenido editorial adicional hasta que se especifique de forma independiente.

### Alcance diferido

- El selector inteligente de idioma y la navegación entre traducciones requerirán
  una Spec posterior, con reglas de fallback y consentimiento aplicables.
- Los destinos externos, anclas, rutas internas no pertenecientes a Pages y los
  atributos de enlace requerirán un modelo de seguridad y validación propio.
- Las pantallas de gestión requerirán LOC-03 y LOC-04 antes de exponer mutaciones
  web autorizadas y auditables.

## 4. *Clash check*

- SDD inicial: concreta que los menús son árboles visuales independientes de las
  URLs y que la publicación de Pages es una condición de accesibilidad. Esta Spec
  consume ambas reglas sin convertir la navegación en dueña de la jerarquía. No
  hay conflicto.
- SPEC-pages-foundation: Pages mantiene identidad, traducciones, publicación y
  templates; Navigation no añade campos ni modifica sus invariantes. No hay
  conflicto.
- SPEC-public-localized-routes: Pages conserva la resolución de locale y la URL
  canónica. Navigation deberá consumir un contrato de URL, nunca componer paths
  desde slugs o prefijos. No hay conflicto.
- ADR-0001: la primera salida pública será Blade y no carga Vue ni una SPA. No
  hay conflicto.
- Roadmap de localización: Navigation completa la parte diferida de LOC-06 sin
  declarar LOC-02, LOC-03, LOC-04 o LOC-07 como disponibles. No hay conflicto.
- Código, datos y pruebas: no existe módulo Navigation ni datos que migrar. El
  contrato intermodular se propone en ADR-0005 y requiere aceptación antes de
  implementar. No hay conflicto irresoluble.

## 5. Requisitos y bloques técnicos aplicables

### Dominio e invariantes

- Un menú es una estructura de navegación visual y no representa una Page, una
  URL ni un idioma.
- Un ítem de menú no modifica la jerarquía, publicación, traducción ni URL de su
  Page destino.
- La posición y la relación padre-hijo de los ítems pertenecen a Navigation; no
  pueden crear ciclos.
- Los hermanos de un mismo menú, idioma y padre usan posiciones enteras
  contiguas desde `1`. Crear, mover o retirar un ítem reindexa atómicamente los
  grupos de hermanos afectados.
- Un ítem pertenece a un único idioma instalado. Su etiqueta es contenido
  localizado del propio ítem y no tiene fallback editorial desde `en`.
- Un ítem padre y todos sus hijos pertenecen al mismo idioma y al mismo menú.
- Los árboles de ítems de idiomas distintos pueden tener estructura, orden y
  destinos diferentes dentro del mismo menú.
- Intentar asignar un padre de otro idioma o de otro menú se rechaza de forma
  atómica, sin modificar el árbol existente.
- Un ítem destinado a una Page solo se incluye si Pages confirma que la
  traducción destino es públicamente disponible para el idioma efectivo.
- Si un ítem se omite por destino no disponible, todos sus descendientes también
  se omiten; no se promocionan a la raíz ni se reasignan a otro padre.
- Navigation no construye URLs desde `slug`, `url_prefix`, locale ni segmentos;
  consume una URL ya canónica del contrato de Pages.

### Entidades y migraciones

El modelo del incremento es:

```text
menus
- id: bigint, PK
- identifier: varchar(100), unique, not null, inmutable
- created_at, updated_at

menu_items
- id: bigint, PK
- menu_id: bigint, FK menus.id, restrict
- language_id: bigint, FK languages.id, restrict
- parent_id: bigint, FK menu_items.id, nullable, restrict
- page_id: bigint, FK pages.id, restrict
- label: varchar(255), not null
- position: unsigned integer, not null
- created_at, updated_at
```

- `identifier` usa entre 1 y 100 caracteres ASCII en minúscula, números y
  guiones simples; empieza y termina por un carácter alfanumérico y no admite
  guiones consecutivos. No se puede modificar después de crear el menú.
- `label` es obligatorio, UTF-8, de hasta 255 caracteres y no admite caracteres
  de control.
- `position` es mayor o igual que `1`. La unicidad y contigüidad de posiciones
  entre hermanos, y la coincidencia de menú e idioma entre padre e hijo, se
  imponen mediante transacciones, bloqueos y pruebas de integración porque no son
  expresables de forma portable con una restricción de base de datos.
- Tras excluir temporalmente el ítem que se mueve, una inserción o movimiento en
  un conjunto de `n` hermanos admite solo posiciones de `1` a `n + 1`. Un valor
  fuera de rango se rechaza de forma atómica, sin reindexado parcial.
- Retirar un ítem que tiene hijos se rechaza; los hijos deben moverse o retirarse
  explícitamente antes de eliminar su padre.
- La migración deberá ser compatible con SQLite, MySQL 8.4 y MariaDB 11.4. No hay
  backfill porque no existen menús previos.

### Contrato entre módulos y HTTP

- Pages deberá exponer el servicio de solo lectura
  `PublicPageUrlResolver::forPage(int $pageId, string $locale): ?string`. Devuelve
  la URL canónica de una Page públicamente disponible para ese locale o `null` si
  la Page, su traducción o alguno de sus ancestros no está disponible.
- El contrato no devuelve Blade, rutas de filesystem, un template ni segmentos
  sin validar; Navigation no depende de `PublicPageResolver` ni interpreta la
  petición HTTP.
- Navigation recibe el idioma efectivo ya decidido por la capa HTTP y resuelve
  exclusivamente el árbol de ítems de ese idioma; no consulta la sesión ni
  `Accept-Language`.
- Navigation verifica que el idioma recibido existe y está activo. Un idioma
  desconocido o inactivo es una entrada inválida y provoca una excepción de
  dominio de Navigation; la capa llamadora conserva la decisión sobre su
  respuesta HTTP.
- Navigation registra un componente Blade que recibe el `identifier` del menú y
  el locale efectivo. El template que lo necesite lo invoca explícitamente; este
  incremento no altera ni presupone una posición global en Base u otro template.

### Comandos y contratos públicos

Los contratos Artisan son:

```text
cms:menu:create {identifier}
cms:menu:item:create {menu} {locale} {page} {label} {--parent=} {--position=}
cms:menu:item:update {item} {page} {label}
cms:menu:item:move {item} {position} {--parent=}
cms:menu:item:remove {item}
```

- `create` registra un menú vacío. `item:create` añade el ítem al final de sus
  hermanos si omite `--position`; si lo proporciona, inserta en esa posición y
  reindexa sus hermanos. Una posición fuera del rango permitido se rechaza.
- `item:update` cambia atómicamente la Page destino y la etiqueta localizada de
  un ítem, sin modificar su idioma, padre ni posición.
- `item:move` asigna explícitamente el padre indicado por `--parent` o convierte
  el ítem en raíz si omite la opción; después lo coloca en la posición indicada y
  reindexa origen y destino. El movimiento conserva el subárbol del ítem.
- `item:remove` se rechaza si el ítem tiene hijos. Todos los comandos son
  atómicos, deterministas y no interactivos. No se expone una mutación HTTP en
  este incremento.

### Seguridad y validación

- Las etiquetas son texto UTF-8 acotado y sin caracteres de control; se escapan
  por defecto en Blade.
- La referencia obligatoria a Page se valida contra una entidad existente y no
  permite introducir URLs, nombres de rutas, vistas, clases, HTML o JavaScript.
- La resolución pública no revela etiquetas ni destinos de traducciones de Page
  ausentes, inactivas o no publicables.
- Toda mutación que afecte al árbol, orden, idioma o destino debe ser
  atómica y proteger las invariantes concurrentes aplicables.

### Vue 3, PageBuilder, JSON e integración externa

No aplicable: este incremento no introduce Vue, PageBuilder, schemas JSON, APIs,
colas ni servicios externos.

## 6. Calidad, seguridad y deuda

### Garantías obligatorias

- Aplicar TDD al árbol visual localizado, aislamiento de Pages y filtrado por
  disponibilidad pública.
- Probar que Navigation no puede alterar la jerarquía ni la URL canónica de una
  Page.
- Probar árboles diferentes por idioma, relaciones padre-hijo entre idiomas
  distintos rechazadas, relaciones válidas dentro del mismo idioma, aislamiento
  entre árboles, destino ausente, idioma inactivo, Page no pública, ancestro no
  publicable y árbol cíclico, según las decisiones aprobadas.
- Probar que filtrar un ítem por disponibilidad de destino filtra también todo su
  subárbol, sin promocionar descendientes.
- Probar inserción, movimiento, reindexado y retirada denegada de ítems con hijos
  en árboles con varias raíces y niveles.
- Probar actualización atómica de etiqueta y destino, y posiciones fuera de rango
  rechazadas sin alterar el árbol.
- Probar el contrato entre módulos y el renderizado Blade sin JavaScript.
- Ejecutar pruebas enfocadas, suite afectada, Pint, build frontend y la matriz
  SQLite, MySQL 8.4 y MariaDB 11.4 cuando exista persistencia.

### Riesgos aceptados

- No se acepta fallback implícito de etiquetas ni construcción local de URLs;
  ambas prácticas podrían mostrar contenido o destinos en un idioma incorrecto.

### Deuda técnica

No aplica. Las decisiones pendientes bloquean la aprobación y no se entregarán
como deuda implícita.

## 7. Criterios de aceptación

- **CA-01:** Navigation mantiene un árbol de menús independiente de la jerarquía
  de Pages y rechaza ciclos o relaciones entre menús no autorizadas. Cada menú
  tiene un `identifier` técnico único e inmutable.
- **CA-02:** Cada ítem pertenece a un único idioma instalado y el menú resuelto
  para ese idioma puede tener un árbol, etiquetas, orden y destinos distintos de
  los de otro idioma, sin fallback editorial entre ellos. Las relaciones
  padre-hijo entre idiomas o menús distintos se rechazan atómicamente.
- **CA-03:** Un ítem de Page se muestra solo cuando Pages confirma una traducción
  pública y proporciona su URL canónica localizada; Navigation no compone paths.
  Si un ítem se filtra, todos sus descendientes se filtran sin cambiar la
  jerarquía del menú resultante.
- **CA-04:** El menú público se renderiza con Blade desde una estructura filtrada
  y no expone destinos o etiquetas no disponibles.
- **CA-05:** Las operaciones aprobadas son atómicas, no interactivas y preservan
  integridad de árbol, orden, idioma y destinos. Las posiciones de hermanos son
  enteros contiguos desde `1`; una posición fuera de rango y la retirada de un
  ítem con hijos se rechazan. La etiqueta y Page destino se actualizan
  atómicamente sin alterar el árbol localizado.
- **CA-06:** Un idioma inexistente o inactivo no resuelve un menú ni expone ítems
  o destinos: el resolvedor lanza una excepción de dominio y Navigation no decide
  la respuesta HTTP de la capa llamadora.

## 8. Trazabilidad de pruebas y documentación

| Criterio | Riesgo cubierto | Nivel de prueba | Evidencia esperada | Impacto documental |
| :--- | :--- | :--- | :--- | :--- |
| CA-01 | Acoplamiento, ciclo o menú ambiguo | Integración/BD | Árbol válido e identificador único | Módulo Navigation |
| CA-02 | Árbol o etiqueta en idioma incorrecto | Integración | Árbol localizado aislado | Administración de menús |
| CA-03 | Enlace a contenido inaccesible o URL duplicada | Integración/HTTP | Disponibilidad y URL de Pages consumidas | Rutas y navegación |
| CA-04 | Exposición pública o dependencia de JavaScript | HTTP | Blade solo recibe ítems filtrados | Uso público |
| CA-05 | Estado parcial, orden ambiguo o carrera de edición | Integración/BD | Reindexado, actualización y fallo atómicos | Operación por Artisan |
| CA-06 | Contenido servido para un idioma inactivo | Integración | Excepción de dominio sin ítems | Contrato de Navigation |

## 9. Plan de implementación

1. Aceptar ADR-0005 y aprobar esta Spec antes de escribir pruebas o código
   funcional.
2. Crear pruebas rojas de persistencia, árbol localizado, orden e invariantes;
   implementar el módulo Navigation, migraciones y operaciones Artisan hasta
   CA-01, CA-02 y CA-05.
3. Crear pruebas rojas del contrato con Pages y del filtrado localizado;
   implementar el resolvedor de menú hasta CA-03.
4. Crear pruebas HTTP del renderizado Blade y ejecutar matriz multi-motor,
   quality gates y documentación de uso al completar CA-04.

## 10. Decisiones abiertas

No hay decisiones funcionales abiertas. ADR-0005 permanece `Propuesto`; su
aceptación y la aprobación explícita de esta Spec son condiciones previas a la
implementación.
