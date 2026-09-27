# SPEC: Fundación de Navigation

- **Estado:** Borrador
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

La Spec no autoriza implementación hasta cerrar las decisiones de modelo,
destinos y contrato entre Navigation y Pages indicadas en la sección 10.

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
- Asociar cada ítem, su etiqueta, posición y relación padre-hijo a un idioma
  instalado, permitiendo árboles distintos para cada idioma de un mismo menú.
- Permitir que un ítem destinado a una Page obtenga la URL canónica localizada
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
- Código, datos y pruebas: no existe módulo Navigation ni datos que migrar. No
  hay conflicto irresoluble, pero el contrato intermodular queda pendiente de
  aprobación.

## 5. Requisitos y bloques técnicos aplicables

### Dominio e invariantes

- Un menú es una estructura de navegación visual y no representa una Page, una
  URL ni un idioma.
- Un ítem de menú no modifica la jerarquía, publicación, traducción ni URL de su
  Page destino.
- La posición y la relación padre-hijo de los ítems pertenecen a Navigation; no
  pueden crear ciclos.
- Un ítem pertenece a un único idioma instalado. Su etiqueta es contenido
  localizado del propio ítem y no tiene fallback editorial desde `en`.
- Un ítem padre y todos sus hijos pertenecen al mismo idioma y al mismo menú.
- Los árboles de ítems de idiomas distintos pueden tener estructura, orden y
  destinos diferentes dentro del mismo menú.
- Un ítem destinado a una Page solo se incluye si Pages confirma que la
  traducción destino es públicamente disponible para el idioma efectivo.
- Navigation no construye URLs desde `slug`, `url_prefix`, locale ni segmentos;
  consume una URL ya canónica del contrato de Pages.

### Entidades y migraciones

Aplicable, pendiente de decisión antes de aprobar esta Spec. El modelo deberá
definir como mínimo:

```text
menus
- identidad estable y clave técnica única del menú

menu_items
- identidad estable, menú propietario, idioma, padre opcional y orden visual
- etiqueta visible localizada
- tipo de destino aprobado y referencia controlada a su destino
```

- Las tablas, claves foráneas, índices, nulabilidad, límites de longitud y la
  estrategia portable de orden se fijarán tras elegir el tipo de destino inicial.
- La migración deberá ser compatible con SQLite, MySQL 8.4 y MariaDB 11.4. No hay
  backfill porque no existen menús previos.

### Contrato entre módulos y HTTP

- Pages deberá exponer un contrato explícito, de solo lectura y comprobable para
  resolver una PageTranslation públicamente disponible y su URL canónica para un
  locale dado.
- El contrato no devuelve Blade, rutas de filesystem, un template ni segmentos
  sin validar; Navigation no depende de `PublicPageResolver` ni interpreta la
  petición HTTP.
- Navigation recibe el idioma efectivo ya decidido por la capa HTTP y resuelve
  exclusivamente el árbol de ítems de ese idioma; no consulta la sesión ni
  `Accept-Language`.
- La primera integración pública recibe una estructura de menú ya filtrada y la
  renderiza con Blade. El punto de inserción de esa presentación y su marcado
  semántico requieren decisión antes de aprobación.

### Comandos y contratos públicos

Aplicable, pendiente de decisión. Antes de aprobar deberán concretarse nombres,
firmas, opciones, operaciones atómicas y errores de los comandos Artisan para
crear menús, añadir, localizar, reordenar y retirar ítems. No se expondrá una
mutación HTTP en este incremento.

### Seguridad y validación

- Las etiquetas son texto UTF-8 acotado y sin caracteres de control; se escapan
  por defecto en Blade.
- Las referencias de destino se validan contra entidades existentes y no permiten
  introducir URLs, nombres de rutas, vistas, clases, HTML o JavaScript.
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
  distintos, destino ausente, idioma inactivo, Page no pública, ancestro no
  publicable y árbol cíclico, según las decisiones aprobadas.
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
  de Pages y rechaza ciclos o relaciones entre menús no autorizadas.
- **CA-02:** Cada ítem pertenece a un único idioma instalado y el menú resuelto
  para ese idioma puede tener un árbol, etiquetas, orden y destinos distintos de
  los de otro idioma, sin fallback editorial entre ellos.
- **CA-03:** Un ítem de Page se muestra solo cuando Pages confirma una traducción
  pública y proporciona su URL canónica localizada; Navigation no compone paths.
- **CA-04:** El menú público se renderiza con Blade desde una estructura filtrada
  y no expone destinos o etiquetas no disponibles.
- **CA-05:** Las operaciones aprobadas son atómicas, no interactivas y preservan
  integridad de árbol, orden, traducciones y destinos.

## 8. Trazabilidad de pruebas y documentación

| Criterio | Riesgo cubierto | Nivel de prueba | Evidencia esperada | Impacto documental |
| :--- | :--- | :--- | :--- | :--- |
| CA-01 | Acoplamiento o ciclo visual | Integración/BD | Árbol válido e independencia de Pages | Módulo Navigation |
| CA-02 | Árbol o etiqueta en idioma incorrecto | Integración | Árbol localizado aislado | Administración de menús |
| CA-03 | Enlace a contenido inaccesible o URL duplicada | Integración/HTTP | Disponibilidad y URL de Pages consumidas | Rutas y navegación |
| CA-04 | Exposición pública o dependencia de JavaScript | HTTP | Blade solo recibe ítems filtrados | Uso público |
| CA-05 | Estado parcial o carrera de edición | Integración/BD | Operación y fallo atómicos | Operación por Artisan |

## 9. Plan de implementación

1. Aprobar las decisiones de la sección 10 y fijar el esquema, contrato de Pages
   y comandos antes de escribir pruebas o código funcional.
2. Crear pruebas rojas de persistencia, árbol localizado e invariantes;
   implementar el módulo Navigation, migraciones y operaciones Artisan hasta
   CA-01, CA-02 y CA-05.
3. Crear pruebas rojas del contrato con Pages y del filtrado localizado;
   implementar el resolvedor de menú hasta CA-03.
4. Crear pruebas HTTP del renderizado Blade y ejecutar matriz multi-motor,
   quality gates y documentación de uso al completar CA-04.

## 10. Decisiones abiertas

- **Modelo de destino inicial:** confirmar si el incremento solo admite destinos
  a `Page` o si incorpora otros tipos. Recomendación: solo `Page`, para conservar
  un primer contrato cerrado y evitar URLs arbitrarias.
- **Identidad y número de menús:** decidir la clave técnica, si habrá un único
  menú inicial o varios menús nombrados, y dónde se inserta su presentación.
  Recomendación: varios menús por clave técnica única, sin fijar aún una posición
  visual global.
- **Orden y operaciones Artisan:** decidir la representación de orden y las
  firmas de creación, traducción, movimiento y retirada. Recomendación: definir
  operaciones explícitas y atómicas tras seleccionar la estrategia portable de
  orden.
- **Contrato de Pages:** decidir la clase o servicio público que devuelve destino
  canónico y disponibilidad, sin acoplar Navigation al resolvedor HTTP.
  Recomendación: un servicio de lectura de Pages con una firma tipada y datos
  mínimos.
