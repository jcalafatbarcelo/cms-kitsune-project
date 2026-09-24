# SPEC: Fundación de Pages

- **Estado:** Aprobada
- **Perfil:** feature
- **Origen de la planificación:** Segundo incremento de la secuencia definida en
  el [roadmap de contenido, templates y navegación](../architecture/content-delivery-roadmap.md).
- **Spec relacionada:** [SPEC-template-foundation](SPEC-template-foundation.md), completada.

## 1. Objetivo o problema

Entregar el primer incremento vertical de Pages: un módulo `Pages` que separe la
identidad estable de una Page de sus variaciones por idioma, mantenga una
jerarquía y aplique la publicación en cascada. Debe poder resolver una primera
Page publicable con el CMS Template Base y la presentación
`public.page.standard`, sin acoplar datos a rutas Blade.

Esta Spec aprobada autoriza exclusivamente la implementación de este incremento.

## 2. Contexto y evidencia

Core ya registra idiomas activos y CMS Templates desplegados. Base está activo y
predeterminado, y declara `public.page.standard`; no hay módulo `Pages`, tablas
de contenido, rutas públicas ni PageBuilder. El SDD exige el patrón
Entity-Translation y que una traducción solo sea pública cuando su Page, idioma y
todas sus Pages ascendentes estén publicadas y activas para ese idioma.

ADR-0004 reserva para Pages la selección de template por herencia o referencia
explícita y prohíbe almacenar rutas Blade, clases o assets en contenido. Base aún
no aporta UI catalogs, pero SPEC-template-foundation difiere su integración al
primer uso de textos estáticos por una presentación. El roadmap reserva las URL
localizadas, la selección HTTP de locale y PageBuilder a incrementos posteriores.

## 3. Alcance

- Crear el módulo de dominio `Pages` mediante `nwidart/laravel-modules`.
- Persistir Page y PageTranslation como entidades separadas, vinculando cada
  traducción a un idioma instalado.
- Mantener una jerarquía de Pages sin ciclos y evaluar la publicación pública en
  cascada por idioma.
- Mantener una home por idioma, asignándola automáticamente a la primera Page
  dada de alta para ese idioma cuando no exista una referencia previa.
- Instalar una home publicada para el idioma integrado `en`, con la estructura de
  Base y un mensaje estático de página en construcción.
- Integrar la selección de CMS Template efectiva y la clave de presentación con
  los contratos de Core y ADR-0004.
- Resolver los textos estáticos de `public.page.standard` desde el UI catalog del
  template efectivo mediante claves semánticas sin propietario persistido.
- Renderizar una Page pública mínima mediante el Blade convencional de Base, sin
  permitir que datos editoriales resuelvan una vista arbitraria.
- Proporcionar una vía reproducible y autorizable para crear, traducir y publicar
  la primera Page mediante los comandos Artisan definidos en esta Spec.

### Fuera de alcance

- Selección HTTP de locale, prefijos, URL canónicas localizadas, redirecciones y
  selector de idioma.
- Menús, items de navegación, PageBuilder, bloques y schemas JSON.
- Backoffice, autenticación, autorización de usuarios y auditoría administrativa.
- Assets, SEO avanzado, sitemap, búsqueda, revisiones editoriales, programación
  de publicación y papelera.
- Assets propios de CMS Templates.

### Alcance diferido

- Las rutas públicas localizadas se retomarán en el incremento 3 del roadmap,
  después de estabilizar las entidades y el contrato de publicación de Pages.
- Navigation consumirá la disponibilidad pública localizada cuando existan rutas
  localizadas y destinos resolubles.
- PageBuilder sustituirá o ampliará el contenido editorial mínimo solo tras
  aprobar su propio schema, persistencia y contrato de renderizado.
- El contenido editorial se evaluará en un incremento posterior. Una candidata
  es `page_translation_contents`, con `page_translation_id`, `key` y `value`, y
  unicidad por `(page_translation_id, key)`. Su clave usará segmentos ASCII en
  minúscula separados por puntos, sin `::`, y no repetirá `language_id`, que ya
  determina la traducción. Esta candidata no fija la persistencia futura de
  PageBuilder ni admite contenido en el incremento actual.
- El SEO editorial se retomará en un incremento posterior sin mover datos de
  `Page` a `PageTranslation`: `title` ya representa el elemento HTML `title`; la
  migración aditiva añadirá `meta_description`, canónica, Open Graph y robots a la
  traducción. La canónica explícita referenciará otra `PageTranslation` publicada,
  accesible y del mismo idioma, mientras que `null` significará la propia
  traducción. Cuando exista Media, `og_media_id` referenciará su entidad y perfil
  OpenGraph; nunca almacenará una ruta. Robots usará `robots_index` y
  `robots_follow`, ambos con valor predeterminado `true`, para representar las
  cuatro combinaciones `index`/`noindex` y `follow`/`nofollow`.

## 4. *Clash check*

- SDD inicial: concreta `Page`/`PageTranslation`, jerarquía y publicación en
  cascada; no fija campos editoriales ni una ruta pública inicial. No hay
  contradicción.
- ADR-0001: el renderizado esencial será Blade; este incremento no incorpora Vue,
  SPA ni PageBuilder. No hay conflicto.
- ADR-0004: la selección se limita a claves de presentación declaradas y a
  templates registrados; herencia y referencia explícita se aplican sin rutas
  Blade almacenadas. No hay conflicto.
- SPEC-template-foundation: consume Base y `public.page.standard` sin ampliar el
  ciclo de vida de paquetes ni habilitar instalación dinámica. No hay conflicto.
- SPEC-static-language-foundation y roadmap de localización: consume idiomas
  instalados, pero no introduce negociación HTTP, prefijos ni overrides. No hay
  conflicto.
- ADR-0002 y SPEC-template-foundation: Pages activa la carga diferida de UI
  catalogs propios de templates para su primera presentación estática. Conserva
  el propietario del catálogo y separa su resolución del contenido editorial.
  No hay conflicto.
- Código, pruebas y esquema actual: no existe módulo ni persistencia de Pages;
  las nuevas tablas no requieren migración de datos. No hay conflicto irresoluble.

## 5. Requisitos y bloques técnicos aplicables

### Dominio e invariantes

- Una `Page` es la identidad estable de contenido y puede tener una Page padre o
  ser raíz. No puede ser su propia ancestro ni formar ciclos.
- Una `PageTranslation` pertenece a una única Page y a un idioma instalado. Solo
  puede existir una traducción por par Page/idioma.
- La disponibilidad pública de una traducción exige simultáneamente: idioma
  activo, Page publicada, traducción publicada y cada ancestro publicado con una
  traducción publicada en el mismo idioma.
- Cada idioma puede referenciar una única PageTranslation como home. La
  traducción referenciada debe pertenecer al mismo idioma; no puede ser sustituida
  por una traducción ausente o no publicable.
- La primera PageTranslation dada de alta para un idioma se asigna como su home
  cuando aún no existe una y se publica junto con su Page en la misma transacción.
- La migración instala una Page y PageTranslation publicadas para `en`, las
  asigna como home y usa `public.page.standard`. Su título inicial es `Under
  construction`; el mensaje visible procede del UI catalog de Base.
- Una home no puede despublicarse o deshabilitarse por sí sola. Debe seleccionarse
  otra PageTranslation pública del mismo idioma en la misma transacción antes de
  retirar la disponibilidad de la home anterior.
- El acceso al dominio sin slug resuelve la home del idioma efectivo. Si la
  referencia no existe o no es públicamente disponible, la respuesta es `404`.
- La Page solicita una clave de presentación; el template efectivo debe estar
  registrado, activo y declarar esa clave. Un template explícito no disponible o
  incompleto se rechaza; no hay fallback silencioso.
- `uses_explicit_template = false` representa `inherits_default` y usa el
  template predeterminado global de Core. El valor `true` representa
  `explicit_template` y conserva una referencia a un template concreto. Cambiar
  el predeterminado solo puede afectar a las Pages que heredan.
- Toda mutación que pueda violar la jerarquía, disponibilidad o referencias de
  templates será atómica y bloqueará los registros implicados cuando el motor lo
  permita.

### Entidades y migraciones

Este incremento no persiste contenido editorial. Su esquema es:

```text
pages
- id: bigint, PK
- parent_id: bigint, FK pages.id, nullable, restrict
- uses_explicit_template: boolean, not null, default false
- explicit_template_id: bigint, FK cms_templates.id, nullable, restrict
- presentation_key: varchar(100), not null
- is_published: boolean, not null, default false
- created_at, updated_at

page_translations
- id: bigint, PK
- page_id: bigint, FK pages.id, restrict
- language_id: bigint, FK languages.id, restrict
- title: varchar(255), not null; valor del elemento HTML `title`
- slug: varchar(100), not null
- is_published: boolean, not null, default false
- created_at, updated_at

page_language_homes
- language_id: bigint, PK y FK languages.id, restrict
- page_translation_id: bigint, unique, FK page_translations.id, restrict
- created_at, updated_at
```

- Una restricción única sobre `page_translations(page_id, language_id)` es
  obligatoria.
- Una restricción única sobre `page_translations(language_id, slug)` es
  obligatoria. El slug usa entre 1 y 100 caracteres ASCII en minúscula, números
  y guiones simples: empieza y termina por un carácter alfanumérico y no admite
  guiones consecutivos. Incluye un único segmento, también para home; las futuras
  URL derivan sus ancestros sin persistir su path completo.
- Una restricción `CHECK` portable debe exigir que
  `uses_explicit_template = 0` implique `explicit_template_id IS NULL`, y que
  `uses_explicit_template = 1` implique `explicit_template_id IS NOT NULL`.
- La PK de `page_language_homes.language_id` impone una única home por idioma. El
  servicio de dominio debe comprobar que `page_translation_id` pertenece a ese
  mismo idioma antes de persistir la referencia.
- La migración debe ser compatible con SQLite, MySQL 8.4 y MariaDB 11.4; no hay
  backfill porque no existen Pages previas.
- La integridad que un motor no pueda expresar portablemente, como la ausencia de
  ciclos transitivos o la correspondencia de publicación de ancestros por idioma,
  se impondrá en el servicio de dominio y mediante pruebas de integración.
- Las tablas, nulabilidad y límites de este incremento quedan fijados por este
  esquema: `title` es obligatorio con un máximo de 255 caracteres, `slug` es
  obligatorio con un máximo de 100 caracteres y ambos estados de publicación son
  booleanos con valor predeterminado `false`.

### Renderizado, HTTP y contratos

- El renderizador recibe una PageTranslation pública y una presentación ya
  validada; resuelve el Blade del template por la convención controlada de Core,
  nunca desde una ruta, namespace o nombre de archivo persistido.
- Un Blade de template solicita un texto estático por una clave semántica
  `<group>.<item>`. Para `public.page.standard` son obligatorias
  `page.home.under-construction.heading` y
  `page.home.under-construction.message`. El resolvedor añade el propietario del
  template efectivo y consulta `<template>::<group>.<item>`;
  `::` queda reservado para UI catalogs y nunca se persiste en Page o
  PageTranslation.
- Todo template que declare `public.page.standard` debe aportar ambas claves en
  su UI catalog completo `en`. La ausencia del catálogo o de una clave hace el
  paquete inválido para esa presentación durante sincronización o activación; no
  hay fallback desde un template alternativo hacia Base. Los locales ausentes usan
  únicamente el fallback al `en` del mismo propietario ya definido por Core.
- La resolución de contenido editorial será independiente de la UI: un futuro
  resolver de contenido recibirá claves sin `::` y una PageTranslation concreta.
  No habrá una función que infiera el origen de una clave.
- La primera superficie HTTP será el acceso al dominio sin slug. Hasta LOC-05,
  resolverá la home del idioma predeterminado de frontend; LOC-05 decidirá el
  idioma efectivo sin alterar la unicidad de la home por idioma. No se expondrá
  una jerarquía de URL transitoria antes del incremento de rutas localizadas.
- La operación de creación, traducción, jerarquía, publicación y selección de
  template se expone exclusivamente mediante Artisan no interactivo. No se
  expondrá una mutación web sin autorización y auditoría previamente
  especificadas.
- Los contratos Artisan del incremento son:

```text
cms:page:create {locale} {slug} {title} {--parent=} {--template=}
cms:page:translate {page} {locale} {slug} {title}
cms:page:publish {page} {locale}
cms:page:unpublish {page} {locale}
cms:page:set-home {page} {locale}
```

  Omitir `--template` usa herencia; proporcionarlo selecciona un identificador
  activo que declare `public.page.standard`. Los comandos deben ser atómicos,
  deterministas y no interactivos. `create` y `translate` publican y asignan la
  home cuando son la primera traducción para el locale; en los demás casos crean
  una traducción sin publicar.
- API JSON, Vue 3, PageBuilder, colas e integraciones externas no aplican a este
  incremento.
- El futuro PageBuilder podrá componer el contenido de una Page, pero no cambia
  `uses_explicit_template`, `explicit_template_id` ni la clave de presentación.
  Cualquier nueva estrategia de selección requerirá una Spec posterior.

### Seguridad y validación

- `title` debe ser texto UTF-8 no vacío de hasta 255 caracteres y no admite
  caracteres de control. El slug cumple la gramática y el límite definidos en el
  esquema antes de persistirse.
- Los slugs no podrán convertirse en rutas de filesystem, nombres de vista ni
  identificadores de template.
- Las claves de UI se validarán conforme al contrato de UI catalogs y las claves
  editoriales futuras no admitirán el separador reservado `::`.
- El contenido se escapará por defecto en Blade. HTML enriquecido y contenido
  editorial quedan fuera de este incremento.
- No se registrarán contenidos editoriales completos en diagnósticos.

## 6. Calidad, seguridad y deuda

### Garantías obligatorias

- Aplicar TDD a la jerarquía, publicación en cascada, selección de template y
  rechazo de transiciones inválidas.
- Probar migraciones y relaciones reales en SQLite, MySQL 8.4 y MariaDB 11.4.
- Cubrir ancestros publicados/no publicados, traducciones ausentes/no publicadas,
  idiomas inactivos, ciclos, unicidad Page/idioma, home única por idioma,
  sustitución atómica de home, templates efectivos inválidos y resolución de UI
  por propietario efectivo.
- Probar la home `en` preinstalada, la primera home de un idioma añadido y la
  validación de UI catalogs de templates para `public.page.standard`.
- Añadir pruebas HTTP para el primer renderizado Blade y comprobar que una Page no
  pública no se expone.
- Ejecutar pruebas enfocadas, suite afectada, Pint, build frontend y controles de
  seguridad configurados; documentar la operación realmente disponible.

### Riesgos aceptados

- La primera superficie pública será deliberadamente menor que las rutas
  localizadas finales. Su contrato debe evitar introducir una URL transitoria que
  rompa enlaces o compita con LOC-05/LOC-06.

### Deuda técnica

- No aplica aún. Las decisiones abiertas deben resolverse antes de aprobar, no
  diferirse como deuda de una capacidad ya entregada.

## 7. Criterios de aceptación

- **CA-01:** Una instalación limpia crea las tablas de Pages y una home publicada
  para `en` con `public.page.standard`; conserva la integridad de sus relaciones
  en SQLite, MySQL 8.4 y MariaDB 11.4.
- **CA-02:** Una Page puede tener una traducción por idioma instalado; un segundo
  registro para el mismo par Page/idioma y una referencia a un idioma inexistente
  se rechazan. El slug es obligatorio, URL-friendly y único por idioma.
- **CA-03:** La primera PageTranslation de un idioma se asigna como home si no
  existe una y se publica atómicamente con su Page; solo puede existir una home
  por idioma y debe apuntar a una traducción de ese mismo idioma.
- **CA-04:** No se puede despublicar o deshabilitar la home sin sustituirla de
  forma atómica por otra traducción pública del mismo idioma.
- **CA-05:** La creación o cambio de jerarquía rechaza autociclos y ciclos de
  cualquier profundidad sin modificar parcialmente la jerarquía.
- **CA-06:** La disponibilidad de una traducción se deniega si su idioma, su Page,
  su traducción o cualquiera de sus ancestros y traducciones equivalentes no está
  publicada/activa. El acceso sin slug resuelve la home del idioma efectivo o
  devuelve `404` si no hay una disponible.
- **CA-07:** Una Page que hereda usa el template predeterminado; una explícita
  conserva su template. La base de datos rechaza una combinación incoherente de
  `uses_explicit_template` y `explicit_template_id`; ambas estrategias rechazan
  una presentación no declarada por el template efectivo.
- **CA-08:** La primera Page pública aprobada se renderiza con
  `public.page.standard` de Base sin que los datos seleccionen un Blade. Sus
  textos estáticos se resuelven desde el UI catalog de Base mediante
  `page.home.under-construction.heading` y
  `page.home.under-construction.message`, sin propietario persistido. Un template
  alternativo que declare esa presentación y no aporte ambas claves en `en` se
  rechaza durante sincronización o activación.
- **CA-09:** Las entradas inválidas y los cambios prohibidos comunican un error
  accionable, no publican contenido ni dejan relaciones inconsistentes.

## 8. Trazabilidad de pruebas y documentación

| Criterio | Riesgo cubierto | Nivel de prueba | Evidencia esperada | Impacto documental |
| :--- | :--- | :--- | :--- | :--- |
| CA-01 | Instalación sin página pública o esquema no portable | Integración/BD multi-motor | Migración, home `en` y FKs verificadas en los tres motores | Guía de instalación |
| CA-02 | Traducciones, slugs duplicados o huérfanos | Integración/BD | Unicidad, gramática y FK rechazadas | Modelo de contenido |
| CA-03 | Home ausente, duplicada o asignada al idioma incorrecto | Integración/BD | Alta/publicación automática, PK y relación de idioma verificadas | Modelo de contenido |
| CA-04 | Idioma sin portada por una mutación parcial | Integración/BD | Sustitución atómica y retirada denegada verificadas | Reglas de publicación |
| CA-05 | Árbol inconsistente | Unit e integración | Ciclos rechazados sin escritura parcial | Guía de jerarquía |
| CA-06 | Exposición de contenido no publicable o home ausente | Integración y HTTP | Matriz de publicación por ancestro e idioma y `404` | Reglas de publicación |
| CA-07 | Datos que seleccionan código o template inválido | Integración | Herencia, referencia explícita y rechazos | Integración con templates |
| CA-08 | Renderizado no controlado, roto o acoplado a Base | HTTP/integración/filesystem | Blade Base resuelve UI por propietario efectivo y los catálogos incompletos se rechazan | Primera página pública y extensibilidad de templates |
| CA-09 | Errores parciales o diagnóstico inseguro | Integración/HTTP | Estado intacto y error determinista | Operación disponible |

## 9. Plan de implementación

1. Crear pruebas rojas de datos, home por idioma, jerarquía y publicación en
   cascada; implementar módulo, migraciones y servicios mínimos hasta CA-01 a
   CA-06.
2. Crear pruebas rojas de selección de template; integrar Core y cumplir CA-07.
3. Crear el UI catalog de Base, su resolvedor contextual, la ruta y operación
   mínima aprobadas; renderizar Base y cubrir CA-08 y CA-09 mediante pruebas HTTP.
4. Documentar uso, límites y preparación para rutas localizadas; ejecutar todos
   los quality gates y actualizar los roadmaps al completar el incremento.

## 10. Decisiones abiertas

- No aplica. La home inicial, slug, publicación, operación Artisan y contrato de
  UI de `public.page.standard` están decididos en esta Spec.
