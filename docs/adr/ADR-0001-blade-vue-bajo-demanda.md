# ADR-0001: Integración de Blade y Vue bajo demanda

- **Fecha:** 2026-09-12 22:44 UTC
- **Última actualización:** 2026-09-12 22:44 UTC
- **Estado:** Aceptado
- **Autores:** Responsable del proyecto y OpenCode (asistencia de redacción)
- **Reemplaza a:** No aplica
- **Reemplazado por:** No aplica

## Ámbito e impacto transversal

Componentes afectados: páginas del backoffice, PageBuilder, renderizado público,
módulos con componentes interactivos e infraestructura compartida de assets.

Restricción transversal: Blade será la base de las páginas y Vue se utilizará en
zonas delimitadas, como dependencia compartida con carga bajo demanda. Ningún
módulo dependerá del PageBuilder para poder utilizar Vue.

Fuera de alcance: persistencia y esquema definitivo del PageBuilder,
autenticación, selección de Starter Kit, contratos de API, implementación del
registro de componentes y mecanismo concreto de carga de assets.

## Contexto

El [SDD inicial](../context/SDD_Inicial.md) prevé renderizado público con Blade
y edición visual con Vue, pero su redacción permitía interpretar Vue como base
de todo el administrador. Se necesita admitir tanto pantallas Blade sencillas
como módulos interactivos, sin convertir el CMS en una SPA ni acoplar estos
módulos al editor.

Los bloques públicos también pueden necesitar interactividad, sin que ello
implique descargar el PageBuilder o trasladar el contenido editorial esencial
al renderizado del cliente.

La decisión fue aceptada por el responsable del proyecto al aprobar el plan
documental mediante `/build ok, aplica` y reiterar `/build aplica`. Describe
arquitectura prevista: todavía no hay una aplicación Laravel instalada.

Comprobación de conflictos: no existen ADR anteriores y las Specs completadas
de gobernanza y generación de ADR no prescriben este stack. Se conservan el
monolito modular, Eloquent, Vue 3 con Composition API y el renderizado público
Blade. Este ADR concreta la integración frontend del SDD; la aclaración de la
persistencia pendiente se mantiene en el SDD, no se resuelve en esta decisión.

## Restricciones

- Técnicas: Laravel, Eloquent y módulos con `nWidart/laravel-modules`; Vue 3 con
  Composition API. No se permite parsear Blade mediante expresiones regulares.
- Funcionales: conservar el contenido público esencial renderizado por Blade y
  las validaciones y autorizaciones del servidor, independientemente de Vue.
- Temporales: priorizar un MVP verificable; el instalador visual de módulos
  sigue fuera de alcance.
- Económicas: no hay un presupuesto específico aprobado para esta integración;
  no se exige un servicio externo ni un servidor de renderizado Vue.
- Equipo: mantener un flujo de assets común, evitando instalaciones de Vue por
  módulo y una infraestructura genérica de plugins sin necesidad demostrada.

## Decisión

1. Usar Blade para la estructura de las páginas públicas y del backoffice. No
   adoptar una SPA ni Inertia como base del proyecto.
2. Utilizar Vue para zonas interactivas delimitadas, incluido el editor del
   PageBuilder. Una isla de interactividad es un componente montado en una zona
   concreta de una página servida por Blade, no una aplicación que controle toda
   su navegación.
3. Declarar Vue una sola vez como dependencia del frontend del proyecto y
   compilar los assets con Vite. Los módulos mantendrán sus componentes y puntos
   de entrada, reutilizando esa dependencia sin depender del PageBuilder.
4. Cargar Vue y los componentes únicamente en las páginas que los necesiten.
   Varias instancias o módulos en una misma página deben reutilizar el runtime
   sin cargar copias independientes. Compartir dependencia no obliga a mantener
   una única aplicación Vue global; el editor puede agrupar componentes que
   comparten estado en una misma aplicación.
5. Separar los assets públicos de los del editor: un bloque público interactivo
   no debe descargar herramientas del PageBuilder o del administrador.
6. Delimitar la propiedad del DOM entre Blade y Vue. Montar Vue no hidrata
   automáticamente el HTML de Blade y puede sustituir el contenido del
   contenedor. El contenido editorial esencial debe permanecer disponible desde
   el servidor, también sin JavaScript; las Specs definirán el fallback de las
   funciones interactivas según su naturaleza.
7. Entregar a cada componente solo los datos necesarios, serializados de forma
   segura y autorizados para el contexto. Resolver componentes mediante un
   registro controlado: el contenido editorial no podrá seleccionar imports
   arbitrarios ni ejecutar plantillas o JavaScript almacenados.
8. Compilar los assets durante desarrollo o despliegue. Activar un módulo no
   significa instalar Vue ni compilar dependencias en el navegador; los detalles
   de integración con el ciclo de vida de módulos se definirán en su Spec.

Las Specs de implementación concretarán el registro y la carga de componentes,
sin anticipar una plataforma genérica de plugins. Deberán incluir pruebas que
verifiquen, en el incremento aplicable, la ausencia del runtime Vue en páginas
que no lo necesitan, su reutilización entre módulos, el aislamiento de los
assets del editor y la disponibilidad del contenido público esencial sin
JavaScript. También cubrirán la serialización segura y el rechazo de componentes
no registrados.

## Criterios de decisión

1. Preservar seguridad, renderizado público y fronteras modulares.
2. Permitir un editor rico sin imponer su coste a todas las páginas.
3. Minimizar infraestructura y dependencias duplicadas.
4. Mantener componentes comprobables y un despliegue reproducible.

## Consecuencias positivas

- Las pantallas sencillas pueden permanecer en Blade sin cargar Vue.
- El PageBuilder y otros módulos pueden reutilizar Vue sin dependencia mutua.
- Los bloques públicos admiten interactividad manteniendo el contenido esencial
  servido por Laravel, sin requerir SSR de Vue.
- No es necesario mantener una API separada solo para convertir el backoffice
  en una SPA; los endpoints interactivos se definirán cuando sean necesarios.

## Consecuencias negativas

- Hay que diseñar y probar las fronteras del DOM y los datos entre PHP y Vue;
  se mitigará con contenedores explícitos y contratos validados por el servidor.
- La compilación común exige coordinar versiones y puntos de entrada de los
  módulos. Las pruebas de assets deberán detectar duplicaciones y filtraciones
  del bundle administrativo hacia páginas públicas.
- Las islas independientes no comparten estado automáticamente. Cuando exista
  estado estrechamente relacionado, se agruparán dentro de una aplicación
  acotada, evitando un bus global de eventos por defecto.
- Un bloque puede necesitar presentación Blade y lógica Vue diferenciadas;
  deberán probarse juntas para evitar incoherencias y pérdida de accesibilidad.

## Alternativas consideradas

### Backoffice completo con Vue e Inertia

Descripción: utilizar Inertia para las páginas del administrador y mantener
Blade para el sitio público.

Ventajas: navegación integrada y un modelo uniforme de componentes y propiedades
para el administrador; no obliga a diseñar una API independiente.

Desventajas: extiende Vue a pantallas sencillas y adopta convenciones de página
que no son necesarias para incorporar un editor aislado.

Motivo de descarte: el alcance actual necesita interactividad selectiva, no un
administrador completo basado en Vue. No se descarta por incompatibilidad con
Blade público, pues pueden convivir.

### SPA Vue separada con API Laravel

Descripción: construir el administrador como aplicación cliente independiente
que consume una API.

Ventajas: autonomía del frontend y contratos reutilizables por otros clientes.

Desventajas: añade navegación cliente, contratos y coordinación de autenticación
y despliegue sin una necesidad actual de clientes independientes.

Motivo de descarte: mayor complejidad que la necesaria para el monolito modular
y el MVP; Vue por sí solo no exige una SPA ni autenticación por tokens.

### Blade con JavaScript sin Vue

Descripción: resolver toda la interactividad con JavaScript nativo sobre Blade.

Ventajas: evita el runtime Vue y resulta suficiente para controles sencillos.

Desventajas: obliga a gestionar manualmente estado y sincronización de una
interfaz compleja como el editor visual.

Motivo de descarte: Vue ya es la tecnología prevista para el editor y aporta
estructura a esa complejidad. JavaScript nativo y HTML siguen siendo opciones
válidas para controles que no necesiten Vue.

## Revisión futura

Fecha de revisión o condición observable: al especificar el primer incremento
del PageBuilder, o si aparecen necesidades de navegación cliente transversal,
estado compartido entre pantallas o clientes independientes.

Evidencia a evaluar: tamaño y composición de bundles, duplicación del runtime,
coste de mantener Blade y Vue, accesibilidad sin JavaScript y complejidad real
de los contratos entre módulos. Una revisión que cambie la decisión deberá
seguir el flujo de ADR y la aprobación de las Specs afectadas.
