# SPEC: Fundación de resolución de presentaciones de CMS Templates

- **Estado:** Completada
- **Perfil:** feature
- **Origen de la planificación:** Evolución aprobada durante la planificación de
  Navigation, backoffice y PageBuilder para que el CMS Template efectivo controle
  las superficies visibles del CMS.
- **Spec relacionada:** [SPEC-template-foundation](SPEC-template-foundation.md),
  [SPEC-pages-foundation](SPEC-pages-foundation.md) y
  [SPEC-navigation-foundation](SPEC-navigation-foundation.md), completadas.

## 1. Objetivo o problema

Entregar un contrato de presentación reutilizable para que Pages y Navigation
soliciten Blades por claves semánticas, permitiendo que el CMS Template efectivo
aporte su propia presentación y que Base proporcione el fallback mínimo cuando
un template Custom no la declare.

Este incremento implementará la primera presentación tematizable de Navigation
y migrará la presentación pública estándar de Pages al mismo resolvedor. No
introduce administración, autenticación ni PageBuilder.

## 2. Contexto y evidencia

Core ya valida manifiestos de CMS Templates con una lista de presentaciones y
Blades convencionales. Pages utiliza `public.page.standard`, pero abre el archivo
del template mediante una ruta construida en su resolvedor HTTP. Navigation
resuelve correctamente un árbol localizado y seguro, pero sus componentes Blade
residen dentro del módulo y no pueden ser personalizados por un template Custom.

ADR-0004 reserva la apariencia para CMS Templates y ADR-0006 propone concretar
su resolución y fallback. Las pruebas de Pages cubren la presentación Base y las
de Navigation cubren el árbol y la salida Blade estándar; ninguna verifica una
presentación de Navigation aportada por un template alternativo ni un fallback
explícito a Base.

## 3. Alcance

- Introducir en Core una resolución controlada de presentaciones por clave y CMS
  Template efectivo.
- Definir las claves de presentación de contrato iniciales
  `public.page.standard` y `public.navigation.menu`; los Blades internos,
  layouts, `@include` y componentes privados de cada template no forman parte de
  ese catálogo y permanecen libres dentro de su paquete.
- Mantener las claves en manifiestos `template.json` y validar que cada clave
  declarada tiene su Blade convencional dentro del paquete correspondiente.
- Resolver primero la clave declarada por el template efectivo y, si no está
  declarada, resolver la misma clave desde Base.
- Tratar la ausencia o invalidez de una implementación Base requerida como error
  de configuración controlado, sin exponer paths internos.
- Migrar `public.page.standard` para que Pages solicite la presentación mediante
  el contrato de Core.
- Añadir `public.navigation.menu` como primera superficie de Navigation. Base
  implementa su marcado y recibe exclusivamente el árbol filtrado y el locale
  efectivo; Navigation conserva la formación y el filtrado de ese árbol.
- Exponer un componente de infraestructura de Core que reciba el identificador,
  locale y CMS Template efectivo para conectar Navigation con el resolvedor de
  presentaciones, sin que Navigation seleccione templates o rutas Blade.
- Permitir que un CMS Template Custom implemente `public.page.standard` y/o
  `public.navigation.menu` de manera independiente.

### Fuera de alcance

- Autenticación, login, autorización, dashboard, backoffice, auditoría y rutas
  de administración.
- PageBuilder, Vue, schemas JSON, bloques, contenido editorial adicional, Media,
  HTML enriquecido, assets y CSS.
- Instalación dinámica de templates, subida de archivos, paquetes externos,
  overrides por rutas de filesystem o edición web de templates.
- Cambiar las reglas de disponibilidad, jerarquía, URLs, locales, publicación o
  árboles de Navigation.
- Añadir claves de presentación de sistema como `system.auth.login` o
  `admin.dashboard`.

### Alcance diferido

- Las vistas de sistema se incorporarán al mismo contrato cuando una Spec de
  backoffice defina sus rutas, datos, autenticación, autorización y auditoría.
- Los bloques de PageBuilder definirán sus propias claves y contratos de datos
  tras aprobar su schema, persistencia, validación y renderizado.
- Assets, hojas de estilo y componentes Vue propios de cada presentación se
  retomarán solo al existir una necesidad comprobable y un contrato de carga.
- El contrato de extensión pública de CMS Templates evaluará un manifiesto
  declarativo y no ejecutable para describir presentaciones, textos UI y sus
  requisitos. JSON es un candidato por coherencia con los manifiestos actuales;
  no se adopta formato, schema ni mecanismo de instalación en este incremento.
  Se retomará al definir instalación pública de templates, validación, hashes,
  compatibilidad y experiencia de autoría en una Spec independiente.

## 4. *Clash check*

- SDD inicial: conserva Blade para contenido público esencial y Vue bajo demanda.
  Este incremento no desplaza contenido al cliente ni selecciona Blades desde
  datos editoriales. No hay conflicto.
- ADR-0001: respeta el aislamiento de assets y no introduce Vue, SPA ni Inertia.
  No hay conflicto.
- ADR-0002: distingue UI catalogs de contenido y presentaciones. Las claves de
  presentación no son claves de UI ni se persisten en datos editoriales. No hay
  conflicto.
- ADR-0004 y ADR-0006: la sustitución parcial de la decisión 5 de ADR-0004 está
  registrada recíprocamente. El fallback a Base conserva las restricciones sobre
  claves declaradas, rutas Blade y disponibilidad de templates. No hay conflicto.
- ADR-0005 y Navigation: Navigation sigue consumiendo solo URLs canónicas de
  Pages y entrega un árbol ya filtrado; el template no consulta modelos de Pages
  ni compone URLs. No hay conflicto.
- Specs de Pages y Navigation: no se alteran sus invariantes, comandos ni datos.
  La salida Blade de Navigation deja de ser propiedad visual exclusiva del módulo,
  lo que es un cambio de alcance funcional explícito cubierto por esta Spec.
- Código y pruebas: existe Base como template mínimo y no hay templates Custom
  persistidos que requieran migración. La presentación actual de Navigation se
  sustituirá por una capacidad declarada y validada. No hay conflicto
  irresoluble.
- Estado de runtime: los registros de templates conservan un hash de manifiesto,
  pero no las presentaciones declaradas. El resolvedor validará una instantánea
  cuyo hash coincida con el registro antes de usarla; no hay conflicto con el
  ciclo de sincronización y las discrepancias producirán `503`.

## 5. Requisitos y bloques técnicos aplicables

### Presentaciones y módulos

- Una presentación es una clave semántica declarada por código y por el manifiesto
  de un CMS Template; no es un path, namespace o nombre de archivo suministrado
  por datos editables o por HTTP.
- El contrato de Core usa un tipo cerrado `CmsPresentation` con exactamente
  `public.page.standard` y `public.navigation.menu` en este incremento. Solo ese
  tipo puede solicitar una presentación al resolvedor. Un manifiesto puede
  declarar capacidades adicionales con sintaxis válida para trabajo futuro, pero
  Core no las resuelve hasta que una Spec y código añadan su caso.
- Antes de resolver una clave, Core obtiene una instantánea validada del
  `template.json` del template efectivo y de Base, y comprueba cada hash contra
  su registro persistido. Una ausencia, cambio o invalidez posterior a
  `cms:template:sync` se trata como configuración no disponible y devuelve `503`;
  no se forma una ruta desde el manifiesto sin esa validación.
- La convención vigente de ADR-0004 asocia una clave a un Blade regular dentro de
  `Resources/views`; el contrato no abre archivos fuera del paquete ni sigue
  enlaces simbólicos.
- Para una clave requerida y un template efectivo, Core aplica esta precedencia:
  template efectivo que declara la clave, Base que declara la clave, error de
  configuración. No hay fallback a un tercer template ni búsqueda por nombres de
  archivos.
- Una clave omitida por un template Custom no lo invalida y activa el fallback a
  Base. Una clave declarada sin un Blade válido invalida el paquete durante
  sincronización, activación o resolución aplicable.
- Base debe declarar y proporcionar las claves usadas como fallback. La ausencia
  o invalidez de Base no se convierte en `404`: la capa HTTP responde `503` sin
  revelar filesystem, manifiestos completos ni configuración interna.
- La resolución de una presentación conserva `effectiveTemplate`, asignado a la
  Page o al contexto llamador, y `presentationTemplate`, que aporta el Blade. Son
  iguales cuando el template efectivo declara la clave y distintos cuando Base
  aporta el fallback.
- Un Blade solicita textos estáticos mediante el contexto de presentación. La
  resolución consulta primero el UI catalog del `effectiveTemplate` y después el
  del `presentationTemplate` si es distinto. Así, un Custom puede cambiar los
  textos de una presentación heredada de Base sin implementar el Blade.
- En modo de fallback `base`, la precedencia exacta de una clave es: locale del
  `effectiveTemplate`, `en` del `effectiveTemplate`, locale del
  `presentationTemplate`, `en` del `presentationTemplate` y clave literal. En
  modo `key`, se omiten ambos pasos `en`, conservando el fallback estructural al
  `presentationTemplate` antes de devolver la clave.
- Un template Custom puede distribuir `Resources/lang` para personalizar textos
  de presentaciones heredadas aunque omita esas presentaciones de su manifiesto.
  Sus claves pertenecen a su propio identificador y se validan con las reglas de
  UI catalogs; la ausencia completa del catálogo sigue permitiendo el fallback a
  Base.
- La asignación y resolución de una Page comprobarán la resolubilidad de
  `public.page.standard` mediante esta precedencia, no únicamente la declaración
  del template efectivo. Una Page puede usar un template Custom que omite esa
  clave cuando Base la proporciona; si ninguno la declara válidamente, la
  asignación o resolución se rechaza.
- Pages solicita `public.page.standard` mediante el resolvedor de Core y entrega
  solo sus datos públicos ya validados, el template efectivo, el resolvedor de UI
  catalogs y el locale efectivo.
- Navigation solicita `public.navigation.menu` con un árbol compuesto solo por
  `id`, `label`, `url` y `children`, ya filtrado por su servicio de dominio. El
  Blade de template controla el HTML, accesibilidad y composición recursiva, sin
  recuperar modelos ni recalcular disponibilidad o URLs.
- El componente Core `cms-navigation` recibe `{identifier}`, `{locale}` y el
  `CmsTemplate` efectivo desde el Blade de la Page, invoca
  `PublicMenuResolver::forMenu()` y solicita `CmsPresentation::PublicNavigationMenu`.
  No tiene un Blade visible propio: entrega al Blade resuelto `items`, `identifier`,
  `locale`, `effectiveTemplate` y `presentationTemplate`.
- Navigation no selecciona el template ni lee la petición HTTP. El componente
  Core es el único adaptador entre el árbol de Navigation y la presentación.

### Datos, migraciones, API, Vue y PageBuilder

No aplicable: no se crean tablas ni se modifica el formato de datos, migraciones,
backfills, APIs HTTP/JSON, Vue, schemas de PageBuilder, colas o integraciones
externas.

### Seguridad y validación

- El resolvedor acepta solo claves aprobadas por código y templates registrados;
  nunca valores de query string, contenido de Page, etiquetas de menú, locale o
  manifest no validado.
- Un Blade interno de un template puede componerse libremente, pero no recibe una
  vía para pedir al resolvedor una clave dinámica. El componente `cms-navigation`
  no acepta un template, clave o ruta desde query string, contenido, etiquetas ni
  cualquier entrada HTTP no validada.
- Los datos entregados a los Blades conservan el escape por defecto de Blade.
  Ninguna presentación habilita HTML enriquecido ni ejecución de contenido.
- Un template Custom no puede suplantar Base con una ruta externa, alterar rutas
  de módulos, elegir un import JavaScript ni leer modelos de otro módulo para
  reconstruir el árbol.
- Los fallos de configuración generan errores controlados y no registran el árbol
  completo, contenido editorial, credenciales ni paths de filesystem.

## 6. Calidad, seguridad y deuda

### Garantías obligatorias

- Aplicar TDD al orden de precedencia, a los manifiestos declarados y a los fallos
  de configuración.
- Probar Base como fallback para una presentación ausente de un template Custom y
  la preferencia de una implementación Custom válida.
- Probar que solo los dos casos iniciales de `CmsPresentation` llegan al resolvedor
  y que una clave adicional declarada en un manifiesto no se convierte en una
  superficie renderizable.
- Probar que una discrepancia de `manifest_hash`, un manifiesto ausente o una
  instantánea inválida tras sincronización responde `503` sin leer un Blade.
- Probar que un Custom que omite el Blade puede sustituir un texto de Base desde
  su propio UI catalog, y que un texto ausente conserva la cadena de fallback al
  catálogo de Base en los modos `base` y `key`.
- Probar que un Blade ausente, enlace simbólico o clave no declarada no se
  resuelve ni expone paths internos.
- Probar Pages y Navigation con Base y con un template Custom, verificando que se
  conserva la publicación en cascada, las URLs canónicas y el árbol localizado.
- Probar el componente Core con varios identificadores de menú y con Base y
  Custom, verificando que Navigation recibe solo `identifier` y `locale` y que el
  HTML procede de `public.navigation.menu` del template resuelto.
- Probar que una Page puede usar un template Custom que omite
  `public.page.standard` cuando Base lo declara, y que se rechaza si no existe
  una implementación válida en ninguno de los dos templates.
- Probar las respuestas HTTP `503` para una presentación requerida sin
  implementación válida y mantener `404` para Pages no resolubles.
- Ejecutar pruebas enfocadas, suite afectada, Pint, build de Vite, validación de
  manifiestos y matriz SQLite, MySQL y MariaDB. La matriz es aplicable porque
  Pages y Navigation conservan persistencia existente aunque no haya migración.

### Riesgos aceptados

- Base seguirá siendo el único template distribuido por el MVP. La capacidad de
  fallback se demostrará con un fixture de template Custom de pruebas, no con un
  segundo paquete de producto.

### Deuda técnica

No aplica. Las vistas de sistema, assets y PageBuilder quedan fuera de alcance con
condiciones explícitas, no como comportamiento incompleto de esta presentación.

## 7. Criterios de aceptación

- **CA-01:** Una presentación requerida se resuelve desde el CMS Template efectivo
  cuando este la declara y proporciona un Blade válido; si no la declara, se
  resuelve desde Base. Core solo acepta `public.page.standard` y
  `public.navigation.menu` como claves de presentación de este incremento.
- **CA-02:** Un template que declara una clave sin Blade regular válido se rechaza,
  y la ausencia de una implementación Base requerida produce un error controlado
  `503` sin revelar rutas internas. Un manifiesto ausente, inválido o cuyo hash no
  coincide con el registro tampoco se resuelve.
- **CA-03:** Pages obtiene `public.page.standard` mediante el contrato de Core y
  conserva sus respuestas públicas, disponibilidad en cascada y `404` para Pages
  no resolubles. Una Page puede usar un template Custom que omite la clave si
  Base la implementa; se rechaza si ninguno de los dos la resuelve. El Custom
  puede modificar textos estáticos de ese Blade heredado sin aportar un Blade
  propio; los textos ausentes resuelven desde Base conforme al modo de fallback
  de UI catalogs.
- **CA-04:** Navigation entrega el árbol localizado y filtrado a
  `public.navigation.menu`; Base y un template Custom pueden producir HTML
  distinto sin alterar labels, orden, URLs ni reglas de disponibilidad. El
  componente Core recibe el identificador, locale y template efectivo, y es el
  único que conecta Navigation con la presentación.
- **CA-05:** Ningún parámetro HTTP, dato editorial, etiqueta de menú o manifest no
  validado puede seleccionar una clave no aprobada, ruta Blade, namespace,
  archivo o import arbitrario.

## 8. Trazabilidad de pruebas y documentación

| Criterio | Riesgo cubierto | Nivel de prueba | Evidencia esperada | Impacto documental |
| :--- | :--- | :--- | :--- | :--- |
| CA-01 | Template Custom ignorado, fallback ambiguo o clave dinámica | Integración/filesystem | Precedencia y claves cerradas verificadas | Arquitectura de templates y extensibilidad |
| CA-02 | Template inválido, manifiesto modificado, fuga de path o error HTTP incorrecto | Integración/HTTP/filesystem | Hash validado y `503` controlado | Diagnóstico de templates |
| CA-03 | Regresión de Pages, renderizado directo, texto incorrecto o template parcial rechazado | HTTP/integración | URL, publicación, Blade y UI catalogs equivalentes con fallback | Guía de Pages y UI catalogs de templates |
| CA-04 | Menú no tematizable, contexto ambiguo o lógica duplicada | Integración/HTTP | Árbol idéntico y HTML según template mediante el componente Core | Guía de Navigation y templates |
| CA-05 | Selección de código o fichero desde entrada no confiable | Integración/seguridad | Entradas rechazadas sin lectura externa | Contrato de seguridad de templates |

## 9. Plan de implementación

1. Crear pruebas rojas de claves cerradas, manifiesto validado por hash, resolución
   de una clave declarada, fallback a Base y fallos controlados; implementar el
   contrato mínimo de Core hasta CA-01, CA-02 y CA-05.
2. Crear pruebas rojas de Pages con presentación resuelta por contrato; migrar
   `public.page.standard` sin cambiar sus reglas públicas hasta CA-03.
3. Crear pruebas rojas del componente Core de Navigation con un fixture Custom;
   declarar e implementar `public.navigation.menu` en Base y migrar el HTML hasta
   CA-04.
4. Ejecutar quality gates y matriz, documentar el contrato real de presentaciones
   y completar la Spec solo tras verificar todos los criterios.

## 10. Decisiones abiertas

No aplica. ADR-0006 está aceptado y ADR-0004 registra el reemplazo parcial de su
decisión 5. La tematización de vistas de sistema, la carga de assets por
presentación y los bloques de PageBuilder requieren Specs posteriores.
