# ADR-0005: Contrato de URL pública entre Pages y Navigation

- **Fecha:** 2026-09-27 01:08 UTC
- **Última actualización:** 2026-09-27 01:08 UTC
- **Estado:** Propuesto
- **Autores:** Responsable del proyecto y OpenCode (asistencia de redacción)
- **Reemplaza a:** No aplica
- **Reemplazado por:** No aplica

## Ámbito e impacto transversal

Componentes afectados: Pages, Navigation, renderizado público Blade y futuros
consumidores de destinos canónicos de Pages.

Restricción transversal: Pages conserva la autoridad para determinar la
disponibilidad pública y la URL canónica localizada de una Page. Ningún módulo
consumidor consulta sus modelos internos, reutiliza su resolvedor HTTP ni compone
una URL desde slugs, prefijos o locale.

Fuera de alcance: selección HTTP de idioma, sesión, `Accept-Language`, rutas
externas, APIs JSON, sitemap, redirecciones, mutaciones de Pages y el renderizado
o administración de menús.

## Contexto

La fundación de Navigation necesita enlazar ítems localizados a Pages sin ser
propietaria de su jerarquía, publicación, traducciones o URL. Pages ya decide si
una traducción es públicamente disponible mediante el idioma, la publicación y
todos sus ancestros, y deriva la forma canónica localizada según el idioma
predeterminado, alias y prefijos regionales.

Permitir que Navigation consulte `Page` y `PageTranslation` directamente o
reconstruya URLs duplicaría reglas de disponibilidad y canonicidad. Esas copias
podrían divergir al cambiar los prefijos, la jerarquía o las reglas de
publicación. La frontera debe ser reusable por Navigation y por consumidores
futuros sin convertir el resolvedor HTTP de Pages en un servicio compartido.

## Restricciones

- Técnicas: Laravel, Eloquent y módulos `nWidart`; el contrato es PHP de solo
  lectura y no expone rutas de filesystem, vistas Blade ni modelos mutables.
- Funcionales: una URL solo existe para una Page públicamente disponible en el
  locale solicitado. Navigation valida previamente que el locale recibido esté
  activo.
- Temporales: el primer consumidor es Navigation; no se introduce una API pública
  ni una capa genérica de enlaces para otros tipos de destino.
- Económicas: no se incorpora infraestructura externa, caché distribuida ni
  sincronización asíncrona.
- Equipo: la regla debe permitir evolucionar Pages sin exigir que consumidores
  conozcan sus tablas o su algoritmo de URL.

## Decisión

1. Pages expondrá un servicio de aplicación de solo lectura con la firma
   `PublicPageUrlResolver::forPage(int $pageId, string $locale): ?string`.
2. El servicio devolverá la URL canónica localizada cuando la Page, su traducción
   y todos sus ancestros sean públicamente disponibles para el locale. Devolverá
   `null` cuando no exista un destino público resoluble.
3. Navigation y cualquier consumidor futuro usarán únicamente este servicio para
   destinos Page. No consultarán `Page`, `PageTranslation` ni
   `PublicPageResolver`, y no derivarán paths desde datos de Pages.
4. El servicio no decide respuestas HTTP, no lee sesión ni headers y no devuelve
   Blade, templates, segmentos sin validar ni datos editoriales.

## Criterios de decisión

1. Preservar una única autoridad sobre canonicidad y disponibilidad pública.
2. Reducir acoplamiento entre módulos y prevenir duplicación de reglas críticas.
3. Mantener un contrato mínimo, comprobable y extensible sin anticipar una API
   genérica de enlaces.
4. Conservar la separación entre selección HTTP de idioma y resolución de
   destinos de dominio.

## Consecuencias positivas

- Navigation puede resolver enlaces localizados sin conocer el esquema interno de
  Pages ni los prefijos URL.
- Un cambio en reglas de publicación o canonicidad se concentra en Pages.
- Consumidores futuros pueden reutilizar una frontera estable y fácilmente
  comprobable.

## Consecuencias negativas

- Pages incorpora un contrato público interno que debe conservar compatibilidad o
  versionarse mediante una decisión posterior.
- La resolución de varios ítems puede requerir varias consultas iniciales; se
  medirá antes de introducir una operación por lote o caché.

## Alternativas consideradas

### Navigation consulta modelos de Pages

Descripción: Navigation usa directamente `Page`, `PageTranslation` y sus
relaciones para decidir disponibilidad y construir cada URL.

Ventajas: menos servicio inicial y acceso directo a los datos necesarios.

Desventajas: acopla Navigation al esquema y duplica la lógica de publicación,
jerarquía, prefijos y canonicidad.

Motivo de descarte: contradice la independencia modular y permite que dos
módulos produzcan URLs distintas para la misma Page.

### Navigation reutiliza el resolvedor HTTP de Pages

Descripción: Navigation invoca `PublicPageResolver` para obtener destinos.

Ventajas: reutiliza una implementación ya existente de rutas públicas.

Desventajas: mezcla una petición HTTP, sesión, negociación y respuestas con una
consulta de dominio; también acopla Navigation a efectos secundarios HTTP.

Motivo de descarte: el resolvedor HTTP no es un contrato de lectura de dominio y
su reutilización vulneraría la separación de responsabilidades.

### Navigation compone la URL desde slugs y prefijos

Descripción: Navigation lee los slugs, ancestros y prefijos para formar URLs.

Ventajas: no añade una clase pública a Pages.

Desventajas: duplica la canonicidad, el alias de familia, las reglas del idioma
predeterminado y la disponibilidad en cascada.

Motivo de descarte: introduce divergencias de URL y expone detalles internos de
Pages a cada consumidor.

## Revisión futura

Fecha de revisión o condición observable: antes de introducir un segundo
consumidor, una API pública, caché o una necesidad medida de resolución por lote.

Evidencia a evaluar: número de consumidores, consultas por renderizado de menú,
latencia, duplicación de lógica, necesidades de metadatos y compatibilidad de la
firma actual.
