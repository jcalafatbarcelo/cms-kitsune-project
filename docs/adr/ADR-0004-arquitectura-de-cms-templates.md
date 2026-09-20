# ADR-0004: Arquitectura de CMS Templates

- **Fecha:** 2026-09-20 21:37 UTC
- **Última actualización:** 2026-09-20 21:51 UTC
- **Estado:** Aceptado
- **Autores:** Responsable del proyecto y OpenCode (asistencia de redacción)
- **Reemplaza a:** No aplica
- **Reemplazado por:** No aplica

## Ámbito e impacto transversal

Componentes afectados: Core, Pages, renderizado público Blade, UI catalogs,
assets, PageBuilder futuro y vistas de sistema futuras.

Restricción transversal: un CMS Template es un paquete global que aporta la
apariencia y capacidades declaradas del CMS. Ningún contenido editorial ni dato
administrativo puede seleccionar una ruta Blade, una clase PHP o un asset por su
nombre; solicita una clave de presentación declarada y el CMS Template efectivo
resuelve su implementación interna.

Fuera de alcance: instalación dinámica, subida desde backoffice, distribución
remota, firma, dependencias de terceros, PageBuilder, login, recuperación de
contraseña, backoffice y sus vistas tematizables.

## Contexto

Pages debe poder elegir cómo presentar contenido editorial sin asumir que todos
los sitios conservan el mismo diseño. A su vez, las vistas de sistema podrán
adaptar su apariencia sin que los módulos propietarios pierdan sus rutas,
autorización o lógica de negocio. Un CMS Template es distinto de una plantilla
del PageBuilder: puede incluir Blade, assets, UI catalogs, configuración y, en
incrementos posteriores, bloques propios.

El proyecto entrega inicialmente un único CMS Template Base junto al código del
CMS. Aunque el MVP/TFM no permitirá instalar templates desde el producto, una
instalación futura debe poder cambiar de CMS Template sin reinstalar el CMS. Por
ello, el contrato de paquetes y su ciclo de vida no se debe sustituir por vistas
globales o por un nombre Blade almacenado en Page.

ADR-0002 ya exige que los templates futuros sean propietarios de UI catalogs
JSON, pero aplaza su raíz y ciclo de carga. El SDD y ADR-0001 exigen Blade para
el contenido público esencial, con Vue solo donde sea necesario.

## Restricciones

- Técnicas: Laravel 13, Eloquent, nWidart para módulos y Blade para el
  renderizado esencial. Los UI catalogs siguen el contrato JSON no ejecutable de
  ADR-0002. Los archivos de un CMS Template son código desplegado y versionado,
  no contenido suministrado por un editor.
- Funcionales: debe existir un CMS Template Base integrado y no eliminable. Debe
  existir exactamente un CMS Template predeterminado activo. Pages podrá heredar
  ese predeterminado o fijar un template explícito, cuando su Spec lo habilite.
- Temporales: el MVP/TFM solo contempla el CMS Template Base desplegado con el
  CMS. La instalación o sustitución dinámica desde el producto queda diferida.
- Económicas: no se incorpora en el MVP infraestructura de marketplace,
  repositorio de paquetes, revisión de procedencia o distribución remota.
- Equipo: los contratos de presentación deben ser revisables y comprobables sin
  exigir que autores de Pages conozcan las rutas internas de cada template.

## Decisión

1. Definir CMS Template como paquete global, separado de los módulos de dominio
   y de los tipos de bloque del PageBuilder. Un paquete podrá aportar Blade,
   assets, UI catalogs, configuración declarada y extensiones que se aprueben en
   Specs posteriores.
2. Incluir un CMS Template Base desplegado y versionado con el CMS. Será el único
   disponible en el primer MVP, no podrá eliminarse y deberá implementar las
   presentaciones mínimas que establezcan las Specs aplicables.
3. Registrar las capacidades de presentación mediante claves estables, como
   `public.page.standard`, y no mediante nombres de Blade. Una Page solicitará
   una clave; el CMS Template efectivo declarará si la implementa y resolverá su
   Blade interno. El valor de Page nunca contendrá rutas, namespaces de vistas ni
   nombres de archivos.
4. Modelar la selección de template de una Page con dos modos: `inherits_default`
   usa el CMS Template predeterminado global y `explicit_template` fija uno
   concreto. `Default` es el modo de herencia, no el identificador de un
   template. Cambiar el predeterminado afecta intencionadamente a las páginas que
   heredan; no afecta a las que fijan un template.
5. Rechazar la asignación de una Page cuando el CMS Template efectivo no declare
   la clave de presentación solicitada. Si un template explícito está referenciado
   por páginas, no podrá dejar de estar disponible sin una reasignación explícita
   y atómica; no habrá fallback silencioso. La herencia al predeterminado es el
   único fallback de selección permitido.
6. Conservar las fronteras funcionales: Pages posee identidad, jerarquía, URL,
   publicación y contenido editorial; cada módulo conserva sus rutas, acciones y
   autorización. El CMS Template implementa solamente la apariencia de una
   presentación registrada. Un template no registra rutas arbitrarias ni ejecuta
   comportamiento elegido por contenido.
7. En el MVP, el ciclo de vida se limita a paquetes confiables ya desplegados por
   una actualización del CMS. La arquitectura preserva una instalación futura
   sin reinstalación, pero esa capacidad requerirá una Spec independiente que
   defina procedencia, compatibilidad, integridad, autorización, auditoría,
   instalación atómica, rollback y el tratamiento de código ejecutable.
8. La primera presentación mínima será pública y editorial, `public.page.standard`.
   Las presentaciones de login, recuperación y backoffice se definirán después de
   que existan sus módulos y contratos funcionales.

## Criterios de decisión

1. Evitar que datos editables seleccionen código o rutas de vistas arbitrarias.
2. Mantener separadas la semántica de contenido, la lógica de módulos y la
   apariencia intercambiable.
3. Permitir cambiar el template predeterminado sin migrar o reinstalar contenido.
4. Entregar un MVP seguro y verificable sin simular una distribución dinámica.
5. Mantener una extensión compatible con UI catalogs, assets y PageBuilder sin
   anticipar sus contratos.

## Consecuencias positivas

- Pages puede pedir una presentación comprobable sin acoplarse a la estructura
  interna del CMS Template.
- El template Base garantiza un renderizado mínimo disponible desde la primera
  página pública.
- Los templates futuros pueden aportar UI catalogs con el mismo modelo de
  propiedad y completitud de Core y los módulos.
- La selección por herencia permite un cambio global controlado, mientras que la
  selección explícita protege páginas que requieren una apariencia concreta.
- La futura instalación puede diseñarse como actualización de paquetes sin
  convertir el contenido editorial en código o rutas de filesystem.

## Consecuencias negativas

- El registro de templates y sus claves de presentación añade persistencia y
  validación antes de que haya varios templates reales; se limita al contrato
  mínimo y al CMS Template Base.
- Cambiar el predeterminado modifica intencionadamente todas las páginas que
  heredan; el backoffice futuro deberá comunicar su impacto antes de confirmar la
  operación.
- Un template es código ejecutable desplegado, a diferencia de un UI catalog
  JSON. La instalación dinámica no se habilita hasta que exista una cadena de
  suministro, autorización y auditoría especificadas.
- Un template que no implemente una presentación necesaria no puede asignarse a
  esa página; la validación anticipada evita una página pública rota.

## Alternativas consideradas

### Vistas Blade globales sin CMS Templates

Descripción: mantener todas las vistas bajo `resources/views` y permitir que
Pages almacene un nombre de vista o de Blade.

Ventajas: menor infraestructura inicial y uso directo de convenciones Laravel.

Desventajas: acopla contenido y módulos a rutas internas, permite referencias
frágiles o inseguras y no proporciona un ciclo de vida para cambiar de apariencia
sin modificar el CMS.

Motivo de descarte: no satisface el requisito de CMS Templates globales ni una
evolución segura hacia su intercambio.

### CMS Template único sin registro ni selección

Descripción: distribuir Base como único conjunto de vistas y posponer toda
identidad o selección de templates.

Ventajas: implementación inicial muy reducida.

Desventajas: obliga a rediseñar Pages y sus referencias al introducir un segundo
template, y no representa el predeterminado ni la herencia ya decididos.

Motivo de descarte: difiere una frontera necesaria para las primeras páginas y
haría que el MVP fijase implícitamente la apariencia como parte del dominio.

### Instalación dinámica desde el primer MVP

Descripción: permitir descargar, subir o instalar CMS Templates desde el
backoffice desde el inicio.

Ventajas: ofrece personalización inmediata sin despliegue del CMS.

Desventajas: incorpora código de terceros, procedencia, integridad, permisos,
auditoría, compatibilidad, rollback y una superficie de ejecución no necesaria
para el TFM.

Motivo de descarte: el beneficio no compensa la superficie de seguridad y
operación antes de disponer de sus garantías.

## Revisión futura

Fecha de revisión o condición observable: al especificar la instalación dinámica
de un segundo CMS Template, el primer template con bloques PageBuilder propios o
la tematización de una vista de sistema, lo que ocurra antes.

Evidencia a evaluar: contrato de manifiesto, colisiones de claves de
presentación y propietarios de UI catalogs, compatibilidad de assets y bloques,
experiencia al cambiar el predeterminado, referencias explícitas de Pages,
cadena de suministro, auditoría, resultados de pruebas de aislamiento y coste de
mantenimiento de varios templates.
