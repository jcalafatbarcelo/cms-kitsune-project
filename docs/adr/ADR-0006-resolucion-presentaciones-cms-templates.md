# ADR-0006: Resolución de presentaciones de CMS Templates

- **Fecha:** 2026-09-28 11:06 UTC
- **Última actualización:** 2026-09-28 19:57 UTC
- **Estado:** Aceptado
- **Autores:** Responsable del proyecto y OpenCode (asistencia de redacción)
- **Reemplaza a:** No aplica
- **Reemplazado por:** No aplica

## Ámbito e impacto transversal

Componentes afectados: Core, CMS Templates, Pages, Navigation, renderizado
público Blade y futuras vistas de sistema.

Restricción transversal: un módulo o Pages entrega datos de dominio y solicita
una clave de presentación controlada. Core resuelve el Blade desde el CMS Template
efectivo si este declara la clave, o desde Base como fallback explícito. Ningún
dato editable, módulo consumidor ni petición HTTP selecciona rutas, namespaces o
archivos Blade.

Fuera de alcance: autenticación, autorización, rutas de administración, editor
Vue, PageBuilder, instalación dinámica de templates, assets, CSS y tematización
de vistas de sistema concretas.

## Contexto

ADR-0004 establece que los CMS Templates son propietarios de la apariencia y
declaran presentaciones por claves estables. Pages ya usa
`public.page.standard`, pero su resolvedor abre directamente el Blade de su
template efectivo mediante una ruta construida. Navigation, por su parte,
entrega hoy su presentación Blade desde el módulo. Esta diferencia impide que un
template Custom personalice la estructura HTML de un menú sin modificar código
del módulo.

La decisión 5 de
[ADR-0004](ADR-0004-arquitectura-de-cms-templates.md) exigía que el template
efectivo declarase la presentación solicitada y rechazaba cualquier ausencia.
Esa regla era incompatible con el fallback parcial acordado para Base. Este ADR
sustituye únicamente esa exigencia de declaración por el template efectivo; las
restantes decisiones de ADR-0004 permanecen vigentes. Los metadatos de reemplazo
no se usan porque ADR-0004 sigue aceptado y no queda sustituido en su totalidad.

Los módulos deben conservar reglas de dominio, validación, disponibilidad y
URLs, mientras que el template efectivo debe controlar el marcado de las
superficies visibles. La solución no puede delegar la selección de archivos en
datos editoriales ni introducir un mecanismo de override libre por rutas de
filesystem.

## Restricciones

- Técnicas: Laravel, Eloquent, Blade y CMS Templates desplegados bajo
  `Templates/`; se conserva la validación de manifiestos no ejecutables y la
  convención de vistas de ADR-0004.
- Funcionales: Base aporta las presentaciones mínimas. Un template Custom puede
  especializar solo las claves que declare y hereda las ausentes desde Base.
- Temporales: Navigation se migra como primera salida tematizable; login y
  administración requerirán sus propias Specs después de definir sus contratos.
- Económicas: no se añade infraestructura externa ni dependencia nueva.
- Equipo: las claves deben ser revisables y comprobables, sin exigir a autores
  de templates conocer clases, rutas o modelos internos de los módulos.

## Decisión

1. Toda superficie visible del CMS solicitará una clave de presentación
   semántica. No se exige que todos los Blades residan físicamente en Base: cada
   CMS Template puede aportar sus propios Blades dentro de su paquete.
2. Core resolverá una clave contra el CMS Template efectivo cuando este la
   declare en su manifiesto. Si no la declara, resolverá la misma clave contra
   Base. Base es el fallback mínimo, no una copia u override de templates
   Custom.
3. Base deberá declarar e implementar cada clave que se use como fallback. Si
   ninguna implementación válida puede resolver una clave requerida, se tratará
   como un error de configuración y la respuesta HTTP será `503`, sin exponer
   rutas internas. `404` seguirá reservado para recursos o rutas inexistentes.
4. Cada template declarado deberá seguir aportando el Blade convencional de las
   claves que liste en su manifiesto. Omitir una clave permite heredarlo desde
   Base; declararla sin Blade válido invalida el template.
5. Los módulos proporcionarán estructuras de datos de presentación ya validadas.
   Navigation conservará la resolución del árbol y sus URLs, pero el template
   será dueño del HTML de `public.navigation.menu`. Pages conservará identidad,
   publicación y contenido, pero no abrirá un Blade por una ruta codificada.
6. Las claves admitidas se definen por código y Specs. El contenido editorial,
   los parámetros HTTP y las configuraciones editables no pueden introducir
   claves, rutas, nombres de archivo, namespaces ni imports arbitrarios.
7. El resultado de la resolución conserva tanto el CMS Template efectivo como el
   CMS Template que aporta la presentación. Para un texto estático, el UI catalog
   del template efectivo tiene prioridad aunque Base aporte el Blade: permite a
   un template Custom personalizar textos de una presentación heredada sin copiar
   su HTML. Si el texto no existe en el template efectivo, se consulta el
   propietario de la presentación, normalmente Base.
8. La precedencia de cada propietario conserva ADR-0002. En modo `base`, se
   consulta el locale solicitado y después `en` del template efectivo, seguidos
   por el locale solicitado y `en` del template que aporta la presentación; si no
   existe valor, se devuelve la clave literal. En modo `key`, se omiten los
   fallbacks a `en`, pero se conserva el fallback estructural desde el template
   efectivo al propietario de la presentación.
9. Core resuelve solo claves de presentación declaradas por código. Las claves
   iniciales son `public.page.standard` y `public.navigation.menu`; un template
   puede usar Blades internos libremente, pero no puede convertir una clave nueva
   del manifiesto en una capacidad ejecutable hasta que una Spec y código la
   incorporen. Esta restricción no limita los `@include` o componentes internos
   del template.
10. Antes de resolver una presentación, Core obtiene una instantánea validada de
    `template.json` y comprueba que su hash coincide con el registro persistido.
    Un paquete ausente, modificado o inválido después de sincronizarse no se
    resuelve y genera un `503` controlado hasta que se sincronice correctamente.

La decisión 5 de ADR-0004 registra recíprocamente esta sustitución parcial y
conserva el rechazo cuando ninguno de los dos templates declara una
implementación válida.

## Criterios de decisión

1. Mantener al CMS Template como propietario efectivo de la apariencia visible.
2. Preservar las fronteras de dominio y evitar que un template reimplemente la
   lógica de Pages o Navigation.
3. Permitir templates Custom parciales sin duplicar las presentaciones mínimas de
   Base.
4. Rechazar configuraciones inválidas de forma determinista y segura.
5. Evitar una infraestructura genérica de overrides o plugins antes de que exista
   una necesidad demostrada.

## Consecuencias positivas

- Base define un mínimo visual completo y los templates Custom pueden sustituir
  únicamente las superficies que necesiten personalizar.
- Navigation y futuras vistas de sistema pueden tematizarse sin transferir sus
  rutas, autorización ni reglas de dominio al template.
- La ausencia de una presentación Custom tiene un fallback explícito, comprobable
  y compatible con templates ya desplegados.
- Un template Custom puede adaptar textos de Base sin duplicar la presentación
  Blade ni asumir la propiedad de su estructura HTML.
- Las claves semánticas evitan que contenido o configuración elijan código o
  paths de filesystem.

## Consecuencias negativas

- Core incorpora un resolvedor transversal y debe probar la precedencia entre
  template efectivo y Base.
- Cada presentación nueva necesita una clave, una implementación Base y pruebas
  antes de ser consumida.
- Un fallo de despliegue de Base puede impedir renderizar una superficie requerida;
  se mitiga con validación de manifiesto, archivos regulares y respuesta `503`
  controlada.
- La resolución de textos incorpora una precedencia adicional entre dos
  propietarios de UI catalogs; se mitiga con una cadena fija y pruebas en ambos
  modos de fallback.

## Alternativas consideradas

### Los módulos conservan siempre sus Blades visibles

Descripción: Navigation y cada módulo renderizan su propio HTML, mientras los
templates solo controlan las Pages.

Ventajas: menor cambio inmediato y componentes Blade simples.

Desventajas: un template no puede controlar visualmente menús o superficies de
sistema sin modificar módulos; la tematización queda incompleta.

Motivo de descarte: contradice el papel de los CMS Templates como propietarios de
la apariencia y crea fronteras visuales inconsistentes.

### Copiar o sobrescribir archivos de módulos desde Base

Descripción: Base contiene copias de vistas de módulos o busca overrides por
rutas convencionales de filesystem.

Ventajas: permite cambios de HTML con poca infraestructura inicial.

Desventajas: duplica vistas, hace frágil la compatibilidad entre releases y
permite ambigüedad o escapes de rutas si se generaliza.

Motivo de descarte: no define capacidades declaradas ni una precedencia segura y
comprobable.

### Exigir que todo template implemente todas las presentaciones

Descripción: un template Custom debe declarar y aportar cada clave visible del
CMS, sin fallback a Base.

Ventajas: cada template es visualmente autosuficiente.

Desventajas: aumenta la carga de mantenimiento y bloquea templates Custom que
solo necesitan cambiar una superficie.

Motivo de descarte: Base ya es el mínimo integrado y un fallback explícito ofrece
una evolución más segura con menor duplicación.

## Revisión futura

Fecha de revisión o condición observable: al añadir la primera presentación de
vista de sistema, el primer bloque de PageBuilder o una necesidad comprobable de
assets propios por presentación.

Evidencia a evaluar: número de claves, templates parciales desplegados, fallos de
fallback, coste de validación, compatibilidad de assets, accesibilidad de las
salidas y necesidad de ampliar el contrato de datos entre módulos y templates.
