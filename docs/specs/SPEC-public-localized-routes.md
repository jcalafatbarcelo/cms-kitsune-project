# SPEC: Rutas públicas localizadas

- **Estado:** Propuesta
- **Perfil:** feature
- **Origen de la planificación:** Tercer incremento del [roadmap de contenido,
  templates y navegación](../architecture/content-delivery-roadmap.md), que
  reúne la selección HTTP de locale de LOC-05 y la parte de rutas de Pages de
  LOC-06.
- **Spec relacionada:** [SPEC-static-language-foundation](SPEC-static-language-foundation.md) y [SPEC-pages-foundation](SPEC-pages-foundation.md), completadas.

## 1. Objetivo o problema

Exponer las Pages públicas mediante URLs canónicas localizadas y resolver el
idioma efectivo sin acoplar la URL al locale interno del CMS. El idioma
predeterminado de frontend no llevará prefijo; los idiomas secundarios admitirán
un prefijo regional explícito y un alias corto por familia. La home sin prefijo
podrá negociar el idioma del navegador en la primera visita y recordará la
selección exclusivamente en la sesión de Laravel.

## 2. Contexto y evidencia

Core registra locales internos como `en` y `es_ES`; dichos identificadores no
son etiquetas HTTP BCP 47. Pages ya mantiene slugs únicos por idioma,
jerarquía, home por idioma y disponibilidad pública en cascada, pero solo
expone `/` con el idioma predeterminado de frontend. El SDD exige prefijos de URL

## 3. Alcance

- Añadir a cada idioma un `url_prefix` explícito, único y apto para URL, con los
  formatos `xx` y `xx-xx`.
- Añadir la designación opcional de idioma general para resolver el alias corto
  de una familia de idioma, como `/es/`.
- Migrar los idiomas existentes derivando `en` desde `en` y `es-es` desde
  `es_ES`; la migración debe fallar antes de escribir si el locale no puede
  representarse en esos formatos o si produce una colisión.
- Actualizar el manifiesto de alta manual de idiomas al schema version `2`, con
  `url_prefix` obligatorio para nuevas instalaciones manuales.
- Proporcionar un comando Artisan no interactivo para designar el idioma general
  de una familia antes de activar una segunda variante.
- Resolver la home y las Pages publicables por rutas jerárquicas derivadas de
  los slugs, sin persistir el path completo.
- Usar rutas sin prefijo para el idioma predeterminado de frontend y rutas con
  prefijo para cualquier idioma secundario activo.
- Negociar `Accept-Language` solo en la primera petición a `/` que no tenga
  idioma válido en sesión, y guardar el idioma resultante en la sesión.
- Priorizar locale explícito en URL sobre sesión; la sesión sobre la negociación
  inicial; y el predeterminado de frontend como último recurso.
- Mantener Blade y CMS Templates como mecanismo de renderizado de Pages.

### Fuera de alcance

- Menús, items de navegación, selector visual de idioma y redirección entre
  traducciones desde un menú.
- Cookie persistente, consentimiento, perfil de usuario y preferencias
  almacenadas a largo plazo.
- Overrides de `UI catalogs`, backoffice, API JSON, Vue, PageBuilder, SEO
  editorial, sitemap y redirecciones históricas de slugs.
- Cambio manual de `url_prefix` después de instalar un idioma. Esa operación
  requerirá una Spec posterior con autorización, validación de colisiones y
  estrategia de redirección.

### Alcance diferido

- Navigation consumirá la disponibilidad y las URLs localizadas de Pages en su
  propio incremento, sin poseer ni alterar la ruta canónica.
- Una evolución de consentimiento decidirá si una elección se copia a una cookie
  persistente y su duración. Hasta entonces solo se usa la cookie técnica de
  sesión de Laravel.
- Una futura evolución podrá ampliar los formatos de `url_prefix` para scripts,
  regiones numéricas o idiomas ISO de tres letras después de definir sus
  contratos de compatibilidad y redirección.

## 4. *Clash check*

- SDD inicial: concreta prefijos de URL, Page/Translation, jerarquía y
  publicación en cascada. Esta Spec los materializa sin introducir menús. No hay
  contradicción.
- SPEC-static-language-foundation: `locale` es un identificador interno y delega
  la conversión de `Accept-Language` a LOC-05. `url_prefix` es un contrato URL
  separado; no reemplaza ni relaja la validación de `locale`. No hay conflicto.
- SPEC-pages-foundation: difiere expresamente las rutas localizadas y prohíbe
  persistir el path completo. Esta Spec deriva los segmentos desde la jerarquía
  y conserva la disponibilidad existente. No hay conflicto.
- ADR-0001: el contenido público esencial continúa renderizado por Blade. No hay
  conflicto.
- ADR-0004: las Pages solicitan una presentación declarada y no guardan rutas
  Blade. La ruta resuelve una PageTranslation antes del template efectivo. No hay
  conflicto.
- Roadmap de localización: LOC-06 menciona menús como dependencia, pero el SDD
  exige que los menús sean independientes de las URLs canónicas. Esta Spec limita
  LOC-06 a las rutas de Pages y difiere Navigation; el roadmap deberá precisar
  esa separación al completar el incremento.

## 5. Requisitos y bloques técnicos aplicables

### Dominio e invariantes

- `url_prefix` identifica un idioma en una URL y no sustituye `locale` como
  identidad interna, clave de `UI catalog` o entrada de Artisan existente.
- Todo `url_prefix` es único entre idiomas instalados, incluso inactivos, para
  reservar URLs estables. Usa exactamente dos letras ASCII minúsculas o dos
  pares de letras ASCII minúsculas separados por un guion, como `es` o `es-mx`.
- Una familia de idioma es el primer par de letras del `url_prefix`. Puede tener
  como máximo un idioma activo marcado como general.
- Si una familia tiene varias variantes activas, un idioma con prefijo corto
  `xx` debe ser su idioma general; así la ruta `/{family}/` no representa un
  idioma distinto del que resuelve el alias.
- El alias corto `/{family}/` resuelve el idioma general activo de esa familia.
  Si no existe general y hay exactamente un idioma activo en la familia, resuelve
  ese idioma. Si hay varias variantes activas sin general, ninguna operación puede
  dejar esa familia en dicho estado.
- El alias corto es la URL canónica de la única variante activa o de la variante
  general. La ruta regional explícita de esa misma variante redirige al alias.
- Activar una segunda variante de una familia exige que una variante activa esté
  marcada como general. Artisan proporciona una operación no interactiva para
  marcarla antes de activar la segunda variante.
- El idioma predeterminado de frontend se resuelve sin prefijo. Todo idioma
  secundario debe estar activo y se resuelve con su `url_prefix`.
- Una ruta localizada solo resuelve una traducción públicamente disponible para
  el idioma seleccionado. No existe fallback de contenido a otro idioma.
- Los segmentos de una Page deben coincidir en orden con su cadena de ancestros,
  desde la raíz hasta la traducción objetivo. La ruta no persiste ni acepta paths
  calculados por clientes.
- Un slug de una Page raíz no puede tener el formato `xx` ni `xx-xx`, pues
  produciría una ambigüedad actual o futura en el primer segmento. Los slugs de
  Pages no raíz pueden usar esos formatos. Crear una Page raíz, añadir una
  traducción a una Page raíz o convertir una Page en raíz debe rechazar esa
  condición de forma atómica.
- La sesión guarda únicamente el locale interno del idioma efectivo. Una entrada
  de sesión ausente, desconocida o inactiva se descarta.

### Entidades y migraciones

La migración aditiva modifica `languages`:

```text
languages
- url_prefix: varchar(5), unique, not null
- is_url_general: boolean, not null, default false
```

- La migración deriva `url_prefix` del locale existente: idioma de dos letras sin
  región a `xx` y con región alfabética de dos letras a `xx-xx` en minúsculas. Si
  un locale no cumple esa representación o dos registros derivan el mismo valor,
  debe abortar con un error accionable y sin aplicar escritura parcial.
- La creación integrada de `en` fija `url_prefix = en` e
  `is_url_general = false`.
- El manifiesto schema version `2` exige `url_prefix` y
  `is_url_general`; el schema version `1` no se aceptará para nuevas
  instalaciones manuales después de esta migración.
- La validación de manifiesto y la instalación deben comprobar gramática y
  unicidad antes de persistir. La restricción única de base de datos permanece
  como garantía ante concurrencia. El servicio de dominio impone como máximo un
  idioma general activo por familia y exige uno antes de activar una segunda
  variante.

### Comandos y contratos

```text
cms:language:set-url-general {locale}
```

- El comando marca un idioma instalado y activo como general para su familia.
- La operación es atómica, no interactiva y deja de marcar cualquier general
  previo de la misma familia.
- Un idioma inactivo, desconocido o cuyo prefijo corto entre en conflicto con un
  general regional se rechaza con un mensaje accionable.
- La migración y sus restricciones deben verificarse en SQLite, MySQL 8.4 y
  MariaDB 11.4.

### HTTP, rutas y resolución

Las formas canónicas son:

```text
/                         home del idioma predeterminado
/parent/child             Page del idioma predeterminado
/es/                      alias corto de una familia secundaria
/es-es/                   variante regional secundaria explícita
/es/padre/hija            Page de un idioma secundario resuelta por alias
/es-es/padre/hija         Page de una variante regional explícita
```

- Los ejemplos usan `es` como alias corto y `es-es` como prefijo regional; el
  valor real procede de `languages.url_prefix` y de su familia.
- `/` selecciona idioma con esta precedencia: locale válido de sesión; en su
  ausencia, mejor coincidencia activa de `Accept-Language`; en su ausencia, el
  predeterminado de frontend. Si el resultado es secundario, responde con una
  redirección temporal a su URL canónica; si es predeterminado, renderiza la
  home.
- La negociación de navegador ocurre solo en `/` sin locale válido en sesión.
  Una ruta con prefijo nunca se negocia ni redirige a otro idioma.
- Un locale explícito, incluido el predeterminado implícito de una ruta sin
  prefijo, actualiza la sesión. Las rutas de Pages sin prefijo fuera de `/`
  siempre representan el idioma predeterminado y no se redirigen según sesión.
- `Accept-Language` se compara primero contra el locale instalado equivalente,
  normalizando BCP 47 a la gramática interna (`es-ES` a `es_ES`). Si no hay
  coincidencia exacta, puede usar el idioma base solo cuando existe exactamente
  un idioma activo con ese primer segmento; de otro modo usa el predeterminado.
- Un prefijo desconocido, inactivo o una cadena de slugs inexistente, incompleta,
  con jerarquía incorrecta o no pública devuelve `404`. Un alias corto con varias
  variantes activas exige una general y no puede resolverse de forma arbitraria.
- Una ruta regional explícita que corresponde a la única variante activa o a la
  variante general redirige con `302` a la forma canónica con alias corto. Las
  variantes activas no generales conservan su prefijo regional explícito como URL
  canónica.
- Las redirecciones decididas por sesión o navegador deben usar `302` y
  `Cache-Control: private, no-store`; no se compartirán en cachés HTTP. No se
  define canónica SEO ni redirección permanente en este incremento.
- La ruta entrega al renderizador la `PageTranslation`, locale y template ya
  resueltos. El renderizador no recibe ni interpreta segmentos de URL como una
  vista Blade.

### Seguridad y validación

- Los segmentos de URL se validan como prefijo regional o slug antes de consultar
  o componer la jerarquía; nunca se usan como rutas de filesystem, nombres de
  Blade, claves de template ni nombres de clase.
- La negociación procesa exclusivamente las etiquetas y pesos de
  `Accept-Language`; no registra el header completo ni valores de sesión.
- El locale de sesión se valida contra idiomas activos en cada uso y no autoriza
  acceso a borradores ni a traducciones no públicas.
- Las reglas de idioma general y formatos de slug raíz se validan en el servicio
  de dominio y se conservan las restricciones de base de datos aplicables para
  condiciones de carrera.
- No se introducen cookies persistentes, identificadores de perfil ni datos
  personales adicionales.

### API, Vue 3, PageBuilder e integración externa

No aplicable: no se introducen APIs JSON, Vue, PageBuilder, colas ni servicios
externos.

## 6. Calidad, seguridad y deuda

### Garantías obligatorias

- Aplicar TDD a la precedencia de resolución, derivación de path jerárquico,
  disponibilidad y colisiones URL.
- Cubrir HTTP para idioma predeterminado, secundario, home, Page anidada,
  alias corto, variante regional explícita, `404`, redirección por sesión,
  redirección canónica, negociación inicial y actualización de sesión por URL
  explícita.
- Cubrir locale inactivo, sesión inválida, `Accept-Language` exacto, coincidencia
  de idioma base no ambigua y caso ambiguo que vuelve al predeterminado.
- Cubrir migración, backfill, unicidad de `url_prefix`, alias corto, idioma
  general y sus invariantes en SQLite, MySQL 8.4 y MariaDB 11.4.
- Ejecutar pruebas enfocadas, suite afectada, Pint, build frontend y controles de
  seguridad configurados.

### Riesgos aceptados

- El cambio de prefijo queda diferido; no se habilitará sin una estrategia de
  redirección, autorización y auditoría.
- La negociación solo en `/` evita que páginas compartidas sin prefijo cambien de
  idioma silenciosamente según la sesión.

### Deuda técnica

No aplica. Las funcionalidades diferidas tienen condición de entrada propia.

## 7. Criterios de aceptación

- **CA-01:** Cada idioma instalado posee un `url_prefix` válido `xx` o `xx-xx` y
  único; el backfill conserva `en` y rechaza sin escritura parcial una colisión o
  locale no representable.
- **CA-02:** La instalación manual schema version `2` requiere un prefijo válido
  y el estado de idioma general; rechaza duplicados de forma accionable, incluida
  una carrera de persistencia. No puede activarse una segunda variante sin un
  idioma general activo en su familia.
- **CA-03:** `/` y `/{path}` resuelven únicamente Pages publicables del idioma
  predeterminado de frontend; sus rutas respetan la jerarquía de slugs.
- **CA-04:** Un prefijo regional explícito y un alias corto resuelven únicamente
  Pages publicables del idioma secundario activo correspondiente, sin fallback de
  contenido. El alias elige el idioma general o la única variante activa y es su
  URL canónica; el prefijo regional equivalente redirige temporalmente al alias.
- **CA-05:** Una URL localizada explícita actualiza la sesión y tiene precedencia
  sobre cualquier valor previo; una URL sin prefijo distinta de `/` representa el
  idioma predeterminado sin redirigir por sesión.
- **CA-06:** La primera visita a `/` negocia un idioma activo desde
  `Accept-Language`, lo guarda en sesión y redirige temporalmente a su home con
  prefijo solo si no es el idioma predeterminado. Una sesión válida conserva ese
  comportamiento en visitas posteriores.
- **CA-07:** Locale de sesión inválido, header ambiguo, idioma inactivo, prefijo
  desconocido, jerarquía inválida, traducción ausente o ancestro no publicable no
  exponen contenido y producen el fallback o `404` aplicable.
- **CA-08:** Un slug raíz no puede usar el formato `xx` ni `xx-xx`, mientras un
  slug hijo sí puede. Los segmentos URL no pueden seleccionar archivos Blade,
  templates ni rutas de filesystem.
- **CA-09:** Las redirecciones por sesión, navegador o forma canónica usan `302`
  y `Cache-Control: private, no-store`; no se crea cookie persistente.

## 8. Trazabilidad de pruebas y documentación

| Criterio | Riesgo cubierto | Nivel de prueba | Evidencia esperada | Impacto documental |
| :--- | :--- | :--- | :--- | :--- |
| CA-01 | Prefijos ambiguos o migración parcial | Integración/BD multi-motor | Backfill y restricción única | Administración de idiomas |
| CA-02 | Alta de idioma o general inválidos | Integración/BD | Manifiesto y carrera rechazados | Administración de idiomas |
| CA-03 | URL por defecto que expone contenido incorrecto | HTTP e integración | Home, paths jerárquicos y publicación | Rutas públicas |
| CA-04 | Alias, URL secundaria o canónica incorrectos | HTTP e integración | Home, paths, publicación y redirección | Rutas públicas |
| CA-05 | Precedencia impredecible de idioma | HTTP | Sesión y URL explícita | Configuración de idioma |
| CA-06 | Negociación inicial incorrecta | HTTP | Navegador, sesión y redirección | Configuración de idioma |
| CA-07 | Exposición o fallback de contenido inválido | HTTP e integración | Matriz de errores y disponibilidad | Diagnóstico público |
| CA-08 | Ambigüedad o selección de código | Integración y seguridad | Colisiones y entrada hostil rechazadas | Extensión de Pages |
| CA-09 | Redirección cacheada o persistencia no consentida | HTTP | Código, cabeceras y cookies comprobados | Privacidad y operación |

## 9. Plan de implementación

1. Crear pruebas rojas de `url_prefix`, idioma general, migración y manifiesto;
   implementar la persistencia y la validación de Core hasta CA-01 y CA-02.
2. Crear pruebas HTTP rojas de paths por defecto y con prefijo; implementar la
   resolución jerárquica y la disponibilidad hasta CA-03, CA-04 y CA-08.
3. Crear pruebas HTTP rojas de sesión y negociación; implementar precedencia,
   redirecciones y cabeceras hasta CA-05 a CA-07 y CA-09.
4. Ejecutar matriz multi-motor, quality gates, documentación de administración y
   desarrollo, y actualizar ambos roadmaps al completar el incremento.

## 10. Decisiones abiertas

No aplica. El prefijo regional, el alias corto, el idioma general, la ausencia
de prefijo para el idioma predeterminado, la jerarquía de URLs, la sesión y la
negociación inicial quedan
