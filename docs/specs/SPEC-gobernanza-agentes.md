# SPEC: Gobernanza de agentes y flujo SDD

## 1. Estado y objetivo

- **Estado:** Completada.
- **Artefacto objetivo de una futura implementación:** `AGENTS.md`.
- **Objetivo:** eliminar ambigüedades operativas del flujo SDD sin cambiar la
  arquitectura funcional del CMS.
- **Fuera de alcance:** código de aplicación, migraciones, contratos HTTP,
  esquemas del PageBuilder y creación de nuevas skills.

## 2. Contexto y clash check

La revisión afecta a las instrucciones de los agentes, no al diseño del CMS.
No entra en conflicto con el monolito modular, el patrón Entity-Translation ni
las demás reglas de negocio de `context/SDD_Inicial.md`.

Se detectan dos inconsistencias internas que la implementación deberá resolver:

1. La jerarquía actual agrupa artefactos prescriptivos, decisionales, históricos
   y ejecutables como si tuvieran la misma finalidad.
2. El inventario declara `adr-generator` y `laravel-module-builder`, aunque son
   skills planificadas cuyos directorios todavía no existen. Deben retirarse del
   inventario operativo hasta que se creen. En particular, `adr-generator` se
   abordará después de cerrar el `AGENTS.md` inicial. Además, `skill-creator`
   registra skills en la tabla actual, por lo que cualquier sustitución del
   inventario deberá actualizar también su flujo y su script.

## 3. Requisitos normativos

### 3.1. Jerarquía por finalidad y resolución de conflictos

`AGENTS.md` deberá sustituir la jerarquía lineal actual por estas reglas:

1. Las instrucciones explícitas del usuario seleccionan el objetivo y el modo,
   siempre dentro de las restricciones vigentes del repositorio.
2. Una Spec aprobada prescribe el comportamiento que se debe construir en
   `/build`. Una Spec propuesta o en borrador no autoriza implementación.
3. Los ADR aceptados restringen las soluciones arquitectónicas. Si una Spec
   contradice un ADR vigente, el agente debe detener la implementación, informar
   del conflicto y solicitar que se revise uno de los dos artefactos.
4. El código y las pruebas existentes describen el estado ejecutable actual. Son
   la referencia para compatibilidad, impacto y regresiones, pero no sustituyen
   el diseño deseado de una Spec aprobada.
5. Las skills definen procedimientos especializados; no pueden contradecir una
   Spec, un ADR ni las instrucciones del repositorio.
6. `CHANGELOG.md` es un registro histórico y nunca una fuente prescriptiva. Si
   discrepa del código, se debe señalar la discrepancia y verificar el estado
   mediante el código y las pruebas.
7. `context/SDD_Inicial.md` aporta visión y restricciones de alto nivel. Se usa
   para elaborar Specs y detectar conflictos, pero una decisión posterior y
   explícita puede concretarlo o reemplazarlo.

La fecha por sí sola no resolverá conflictos. Dentro de una misma clase de
artefacto prevalecerá el documento con estado vigente que reemplace de forma
explícita al anterior. Ante una contradicción no resoluble, el agente no deberá
inventar una interpretación: documentará el choque y pedirá una decisión.

### 3.2. Aprobaciones e implementación incremental

La frase «pidiendo validación al usuario» se reemplazará por puntos de control
concretos:

- La aprobación de la Spec y la orden `/build docs/specs/SPEC-[nombre].md`
  constituyen autorización para implementar de forma autónoma todo su alcance.
- El agente puede inspeccionar, editar, ejecutar pruebas, aplicar formato y
  realizar ajustes internos necesarios sin pedir confirmación en cada archivo o
  paso, siempre que no amplíe la Spec.
- Debe solicitar una nueva aprobación únicamente cuando aparezca al menos una de
  estas condiciones:
  - hay que cambiar alcance, modelo de datos, API pública o criterios de
    aceptación de la Spec;
  - existe un conflicto con un ADR, otra Spec vigente o una regla de negocio;
  - se requiere una operación destructiva o irreversible no prevista;
  - faltan decisiones funcionales que admiten alternativas con consecuencias
    distintas y la Spec no permite resolverlas.
- Si surge uno de esos casos, el agente debe dejar el repositorio en un estado
  coherente siempre que sea posible, describir lo completado y lo pendiente, y
  no presentar cambios parciales como una implementación terminada.
- Los hitos intermedios se comunicarán como progreso, no como solicitudes de
  permiso, salvo que coincidan con uno de los puntos de control anteriores.

### 3.3. Pruebas y calidad en `/build`

Toda implementación deberá:

1. añadir o actualizar pruebas automatizadas para el comportamiento modificado;
2. ejecutar primero las pruebas enfocadas y después la suite afectada disponible;
3. ejecutar las herramientas de formato y análisis estático configuradas en el
   repositorio para los archivos modificados;
4. revisar el diff y comprobar que no se incluyan secretos, artefactos generados
   accidentales ni cambios ajenos al alcance;
5. informar con el comando exacto y su resultado de cada comprobación;
6. declarar como advertencia cualquier comprobación no ejecutada, indicando la
   limitación concreta, y no afirmar que pasó;
7. considerar fallida la entrega si falla una comprobación por causa atribuible
   al cambio, salvo que el usuario acepte expresamente la deuda.

La implementación de esta Spec deberá formular estas obligaciones sin imponer
herramientas que el proyecto todavía no haya configurado.

### 3.4. Criterio para crear ADR

Solo se creará un ADR si la decisión es arquitectónica y duradera, afecta a más
de un componente o establece una restricción transversal, existen alternativas
razonables y las consecuencias futuras justifican conservar el razonamiento.

No se creará un ADR para refactors locales, nombres, correcciones rutinarias,
detalles reversibles de implementación ni decisiones ya prescritas por una Spec
o un ADR vigente. `adr-generator` está planificada, pero su creación queda
expresamente aplazada hasta que se cierre el `AGENTS.md` inicial. Mientras no
exista, no debe anunciarse como disponible y se usará directamente
`docs/plantilla_ADR.md` cuando se cumpla el criterio. Si no se cumple, bastará la
Spec, la documentación funcional o el historial del cambio, según corresponda.

### 3.5. Política de `CHANGELOG.md`

- El changelog solo se modificará cuando exista un cambio notable que registrar;
  no se tocará por cambios internos sin impacto relevante para el producto o su
  proceso documentado.
- La marca se actualizará únicamente cuando se modifique el propio changelog.
- Se usará UTC y el formato estable `YYYY-MM-DD HH:mm UTC`.
- En una misma entrega se actualizará una sola vez, al finalizar, para evitar
  diffs causados exclusivamente por ejecuciones repetidas.
- Una futura implementación deberá normalizar la marca existente al nuevo
  formato solo si esa misma entrega añade o modifica una entrada del changelog.

### 3.6. Descubrimiento de skills

El descubrimiento se limitará estrictamente al árbol `skills/`. El agente no
buscará skills en `docs/`, dependencias, directorios del sistema ni en cualquier
otra ubicación del repositorio, salvo que una instrucción explícita disponga lo
contrario.

Para evitar recorrer y cargar todos los `SKILL.md` en cada tarea, se creará
`skills/INDEX.md` como catálogo ligero. Contendrá, para cada skill existente,
únicamente su nombre, ruta, descripción resumida y condiciones de uso. El flujo
será:

1. Consultar una sola vez `skills/INDEX.md` para decidir si alguna entrada se
   corresponde con la tarea actual.
2. Abrir únicamente el `SKILL.md` de las skills seleccionadas.
3. No inspeccionar las demás carpetas de `skills/` si el índice es válido.
4. Si el índice no existe o una ruta seleccionada no está disponible, limitar la
   recuperación a `skills/*/SKILL.md`, informar de la desincronización y no
   buscar fuera de `skills/`.

Cada `skills/<nombre>/SKILL.md` seguirá siendo la fuente de verdad del flujo y de
su frontmatter. `skills/INDEX.md` será una vista derivada para descubrimiento, no
una segunda fuente editable de triggers. No enumerará skills planificadas o
ausentes: `adr-generator` y `laravel-module-builder` solo se incorporarán cuando
existan y hayan sido validadas.

La implementación deberá adaptar `skills/skill-creator/SKILL.md` y
`skills/skill-creator/scripts/crear_nueva_skill.py` para regenerar de forma
determinista y atómica `skills/INDEX.md` desde los `SKILL.md` existentes después
de validar una nueva skill. El índice no se mantendrá manualmente y su generación
fallará si encuentra nombres duplicados, frontmatter inválido o rutas que
escapen de `skills/`. `AGENTS.md` dejará de contener el inventario detallado y se
limitará a señalar el índice, su alcance y el flujo de carga selectiva.

### 3.7. Idioma y convenciones

- Las respuestas al usuario y la documentación funcional y de proceso se
  redactarán en español, salvo petición expresa o convención previa del archivo.
- Los identificadores de código, nombres de clases, métodos, variables, tablas,
  campos, rutas y claves de API se escribirán en inglés.
- Los comentarios de código se escribirán en inglés y solo cuando expliquen el
  motivo o una restricción no evidente; no narrarán el código.
- Los mensajes de commit usarán inglés, en modo imperativo y con un prefijo
  convencional coherente con el historial (`docs:`, `feat:`, `fix:`, etc.).
- Los términos técnicos consolidados podrán conservarse en inglés cuando una
  traducción reduzca la precisión. No se traducirán identificadores existentes
  únicamente para cumplir esta política.

## 4. Artefactos afectados por la futura implementación

| Artefacto | Cambio previsto |
| :--- | :--- |
| `AGENTS.md` | Aplicar los requisitos, retirar skills ausentes y dirigir el descubrimiento exclusivamente a `skills/INDEX.md`. |
| `skills/INDEX.md` | Incorporar el catálogo derivado de las skills que existen y están validadas. |
| `skills/skill-creator/SKILL.md` | Sustituir el registro en `AGENTS.md` por la regeneración del índice. |
| `skills/skill-creator/scripts/crear_nueva_skill.py` | Generar atómicamente el índice desde los frontmatter existentes y ajustar validaciones y salida. |
| Pruebas de `skill-creator` | Verificar que el scaffolding no modifica `AGENTS.md`, actualiza el índice y rechaza metadatos inválidos o duplicados. |
| `CHANGELOG.md` | Registrar el cambio notable y usar la nueva marca UTC. |

## 5. Datos, API y PageBuilder

Esta Spec no introduce entidades, relaciones, migraciones, endpoints, firmas de
clases ni estructuras JSON del PageBuilder. Esos apartados no son aplicables
porque el alcance se limita a gobernanza documental y tooling de skills.

## 6. Criterios de aceptación

1. La finalidad de Specs, ADRs, código/pruebas, skills, changelog y SDD queda
   diferenciada, incluido un procedimiento explícito ante contradicciones.
2. Una orden `/build` sobre una Spec aprobada permite completar autónomamente el
   alcance y los únicos puntos que requieren nueva aprobación están enumerados.
3. `/build` exige pruebas, formato, análisis estático, revisión del diff e informe
   veraz de comprobaciones omitidas o fallidas.
4. El criterio de ADR excluye decisiones locales, rutinarias o fácilmente
   reversibles.
5. La marca del changelog solo cambia junto con una entrada real, especifica UTC
   y se actualiza una vez por entrega.
6. `AGENTS.md` no enumera skills inexistentes ni duplica triggers; dirige al
   catálogo `skills/INDEX.md` y prohíbe buscar skills fuera de `skills/`.
7. El índice solo contiene skills existentes, se deriva de sus frontmatter y
   permite seleccionar una skill sin cargar todos sus archivos de instrucciones.
8. `skill-creator` deja de editar una tabla manual, regenera el índice de forma
   determinista y atómica, y sus pruebas cubren el nuevo comportamiento.
9. Las convenciones de idioma separan claramente comunicación y documentación
   en español de identificadores y comentarios técnicos en inglés.
10. No se altera código de aplicación ni se crea un ADR para esta mejora de
   gobernanza, pues no cambia la arquitectura del CMS.

## 7. Plan de implementación propuesto

1. Actualizar `AGENTS.md`, retirar las skills pendientes y reemplazar la tabla
   manual por una referencia de alcance limitado a `skills/INDEX.md`.
2. Crear el índice inicial solo con `skill-creator`.
3. Adaptar el contrato, script y pruebas de `skill-creator` para regenerarlo.
4. Ejecutar las pruebas del script y validaciones documentales disponibles.
5. Registrar el cambio en `CHANGELOG.md` y fijar una única marca UTC final.
6. Revisar el diff completo contra los criterios de aceptación.

La creación de `adr-generator` queda fuera de esta implementación y será objeto
de una petición y una Spec posteriores, una vez cerrado el `AGENTS.md` inicial.
