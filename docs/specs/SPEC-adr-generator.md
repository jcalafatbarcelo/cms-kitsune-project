# SPEC: Generador y formato canónico de ADR

## 1. Estado y objetivo

- **Estado:** Propuesta.
- **Artefactos objetivo de una futura implementación:**
  `docs/plantilla_ADR.md`, `skills/adr-generator/SKILL.md`, los recursos y pruebas
  propios de la skill, `skills/INDEX.md` y `CHANGELOG.md`.
- **Objetivo:** establecer un formato canónico y verificable para los
  Architecture Decision Records (ADR), y crear una skill que guíe su generación
  sin duplicar el formato de salida dentro del procedimiento.
- **Fuera de alcance:** crear ADR sobre decisiones que aún no existan, modificar
  decisiones arquitectónicas del CMS, alterar código de aplicación, introducir
  migraciones o contratos HTTP y cambiar la gobernanza general de agentes.

La revisión de `docs/plantilla_ADR.md` forma parte expresa del alcance. La
plantilla será la fuente de verdad del formato del documento resultante;
`skills/adr-generator/SKILL.md` conservará las condiciones de uso, las
comprobaciones y las reglas del procedimiento.

## 2. Contexto y *clash check*

`AGENTS.md` ya limita los ADR a decisiones arquitectónicas duraderas que afecten
a varios componentes o impongan una restricción transversal. También exige
alternativas razonables y consecuencias que justifiquen conservar el
razonamiento. Esta Spec concreta ese mandato y no modifica sus umbrales.

La implementación deberá respetar además el flujo existente de creación de
skills: `adr-generator` se generará mediante `skills/skill-creator/SKILL.md`, y
el índice derivado no se editará manualmente. Actualmente `skills/INDEX.md` no
publica `adr-generator`; por tanto, la skill no se considerará disponible hasta
que exista, supere sus validaciones y el flujo de `skill-creator` regenere el
índice.

No se detectan conflictos con ADR vigentes porque `docs/adr/` todavía no
contiene decisiones. Tampoco hay conflicto con el monolito modular, el patrón
Entity-Translation, el PageBuilder ni las reglas funcionales de
`context/SDD_Inicial.md`: el cambio afecta exclusivamente a gobernanza y
documentación arquitectónica.

Sí existen carencias que la futura implementación deberá corregir:

1. La plantilla no asigna identificador ni ruta canónica al ADR.
2. La fecha no declara zona horaria y usa `hh`, que no distingue de forma
   inequívoca un reloj de 24 horas.
3. Enumera estados, pero no define sus transiciones.
4. No permite enlazar de forma estructurada decisiones reemplazadas.
5. Marca alternativas y revisión futura como opcionales, en contra del umbral
   establecido por `AGENTS.md`.
6. No exige declarar el ámbito ni demostrar el impacto transversal.
7. Contiene las erratas `implicaoens` y `edbería`; además, el alcance solicitado
   identifica la variante `implicaicones`. La implementación deberá eliminar
   ambas variantes incorrectas y usar `implicaciones` y `debería`.

## 3. Convención de identificación y ruta

Cada ADR se guardará en:

```text
docs/adr/ADR-NNNN-titulo-kebab-case.md
```

La convención tendrá estas reglas:

- `NNNN` es un entero decimal de cuatro dígitos, empezando en `0001` y asignado
  como el máximo identificador existente más uno.
- El identificador no se reutiliza, ni siquiera cuando un ADR se rechaza,
  queda obsoleto o es reemplazado. No se renumeran decisiones existentes.
- El título de archivo se deriva del título descriptivo en `kebab-case`, solo
  con letras ASCII minúsculas, números y guiones simples; no empieza ni termina
  con guion ni contiene guiones consecutivos.
- El encabezado principal adopta la forma
  `# ADR-NNNN: Título descriptivo de la decisión` y su número debe coincidir con
  el del archivo.
- La asignación comprobará tanto nombres como encabezados para rechazar números
  duplicados. Si encuentra un archivo que incumple la convención o impide
  determinar con seguridad el siguiente número, se detendrá e informará del
  conflicto en vez de sobrescribir o renumerar.
- Los enlaces entre ADR usarán rutas Markdown relativas al ADR actual.

## 4. Formato temporal

Todos los campos temporales usarán UTC, precisión obligatoria de minuto, reloj
de 24 horas y el formato estable:

```text
YYYY-MM-DD HH:mm UTC
```

La plantilla incluirá `Fecha` para la creación y `Última actualización` para el
último cambio de estado o contenido. Ambas serán obligatorias, tendrán valores
reales y la última actualización no podrá ser anterior a la fecha. No se
admitirán segundos, offsets locales, nombres ambiguos de zona horaria ni fechas
sin hora.

## 5. Estados y transiciones

Los únicos estados admitidos serán:

- **Propuesto:** decisión en evaluación que todavía no restringe el diseño.
- **Aceptado:** decisión aprobada y vigente.
- **Rechazado:** propuesta evaluada que no se adoptará.
- **Obsoleto:** decisión antes aceptada que dejó de ser aplicable sin que otro
  ADR la sustituya.
- **Reemplazado:** decisión antes aceptada cuya vigencia pasa a otro ADR.

Las transiciones válidas serán:

```text
Propuesto -> Aceptado | Rechazado
Aceptado  -> Obsoleto | Reemplazado
```

`Rechazado`, `Obsoleto` y `Reemplazado` serán estados terminales. No se permitirá
volver a un estado anterior ni editar el significado histórico para simular una
transición inválida; una reconsideración se documentará en un ADR nuevo que
enlace al anterior. La creación inicial solo podrá usar `Propuesto`, salvo que
el usuario aporte evidencia explícita de que la decisión ya fue aceptada.

La transición a `Reemplazado` exigirá informar `Reemplazado por`, y el nuevo ADR
deberá informar `Reemplaza a`. Ambos enlaces serán recíprocos y apuntarán a
archivos existentes. En cualquier otro estado, `Reemplazado por` será `No
aplica`. `Reemplaza a` podrá ser `No aplica` o contener uno o más ADR existentes;
solo un ADR `Aceptado` podrá reemplazar decisiones previas.

## 6. Formato obligatorio de `docs/plantilla_ADR.md`

La implementación reemplazará los placeholders actuales por instrucciones
inequívocas y mantendrá, como mínimo, estos metadatos:

- `Fecha`.
- `Última actualización`.
- `Estado`.
- `Autores`.
- `Reemplaza a`.
- `Reemplazado por`.

Después de los metadatos, todo ADR deberá contener estas secciones obligatorias,
en este orden:

1. **Ámbito e impacto transversal:** delimita componentes, módulos, equipos o
   procesos afectados y explica por qué la decisión es arquitectónica y
   transversal. Debe declarar también qué queda fuera del ámbito.
2. **Contexto:** describe el problema y la necesidad de decidir.
3. **Restricciones:** enumera restricciones técnicas, funcionales, temporales,
   económicas o de equipo; si una categoría no aplica, lo declara y justifica.
4. **Decisión:** identifica claramente la opción elegida y cómo se aplicará.
5. **Criterios de decisión:** expone los criterios utilizados y su prioridad o
   peso relativo.
6. **Consecuencias positivas:** documenta beneficios concretos.
7. **Consecuencias negativas:** documenta costes, riesgos, compromisos o deuda
   asumida, junto con mitigaciones cuando existan.
8. **Alternativas consideradas:** incluye opciones razonables distintas de la
   decisión, con sus ventajas, desventajas y motivo de descarte.
9. **Revisión futura:** fija una fecha UTC o una condición observable que active
   la revisión, además de indicar qué evidencia deberá evaluarse.

No se marcarán alternativas ni revisión futura como opcionales. La plantilla
corregirá expresamente `implicaicones` (y la variante existente `implicaoens`)
por `implicaciones`, y `edbería` por `debería`; ninguna de las grafías erróneas
podrá permanecer en el artefacto.

## 7. Responsabilidades de `adr-generator`

### 7.1. Condiciones de uso

La skill solo se activará para proponer, redactar, revisar o actualizar un ADR.
Antes de generar el archivo, comprobará que la decisión:

1. es arquitectónica y duradera;
2. afecta a más de un componente o establece una restricción transversal;
3. admite alternativas razonables;
4. tiene consecuencias futuras que justifican conservar el razonamiento; y
5. no es un refactor local, un cambio de nombre, una corrección rutinaria, un
   detalle reversible ni una decisión ya prescrita por una Spec o ADR vigente.

Si no se cumple el umbral, la skill rechazará crear el ADR y explicará qué
artefacto resulta más apropiado. Antes de redactarlo hará un *clash check* contra
Specs y ADR existentes y detendrá el flujo ante contradicciones no resueltas.

### 7.2. Separación entre procedimiento y formato

`skills/adr-generator/SKILL.md` referenciará `docs/plantilla_ADR.md` como formato
obligatorio de salida y no copiará su esqueleto ni mantendrá una segunda lista de
secciones. La skill conservará en cambio:

- triggers y exclusiones;
- pasos para descubrir decisiones relacionadas y asignar el identificador;
- comprobación del umbral arquitectónico y del impacto transversal;
- *clash check* y reglas para estados y reemplazos;
- recopilación de datos faltantes;
- validaciones previas y posteriores a la escritura;
- instrucciones de fallo seguro, sin sobrescritura ni renumeración; y
- comandos de prueba aplicables a sus recursos.

La plantilla no incorporará el flujo interno de la skill. De este modo, la
plantilla determina **qué aspecto tiene** un ADR y la skill determina **cuándo y
cómo** se produce y valida.

### 7.3. Validaciones mínimas

La skill y sus pruebas automatizadas rechazarán, como mínimo:

- nombre, ruta, encabezado o identificador que incumplan la convención o estén
  duplicados;
- timestamps que no sigan `YYYY-MM-DD HH:mm UTC`, tengan una fecha imposible o
  presenten una última actualización anterior a la creación;
- estado desconocido o transición inválida;
- relación de reemplazo ausente, unilateral, autorreferente, circular,
  incompatible con el estado o dirigida a un ADR inexistente;
- metadatos o secciones obligatorias ausentes, vacías o fuera del orden
  canónico;
- placeholders sin completar, incluidos texto entre corchetes, instrucciones de
  la plantilla, ejemplos conservados como respuesta, marcadores `TODO`/`TBD` y
  valores genéricos como `Autor`, `Título` o `Alternativa 1`;
- una declaración de ámbito que no identifique al menos dos componentes
  afectados o no justifique explícitamente una restricción transversal;
- ausencia de consecuencias positivas o negativas, o cualquiera de esas
  secciones sin al menos una consecuencia concreta y específica de la decisión;
- ausencia de alternativas razonables: se exigirán al menos dos opciones
  materialmente distintas de la elegida, y cada una tendrá descripción,
  ventajas, desventajas y motivo de descarte;
- revisión futura sin fecha UTC ni condición observable, o sin evidencia a
  evaluar; y
- cualquiera de las erratas `implicaicones`, `implicaoens` o `edbería`.

Las validaciones estructurales podrán automatizarse, pero no se presentarán como
garantía semántica absoluta. La skill deberá revisar de forma explícita que las
alternativas sean viables en el contexto, que las consecuencias no sean frases
genéricas y que el impacto declarado sea verdaderamente transversal.

## 8. Artefactos de la futura implementación

| Artefacto | Cambio previsto |
| :--- | :--- |
| `docs/plantilla_ADR.md` | Convertirla en el formato canónico con metadatos, secciones, ayudas de redacción y correcciones especificadas. |
| `skills/adr-generator/SKILL.md` | Crear el procedimiento, referenciar la plantilla y conservar condiciones, reglas y comprobaciones sin duplicar el formato. |
| Recursos o scripts de `adr-generator` | Implementar las validaciones deterministas necesarias sin depender de código de aplicación. |
| Pruebas de `adr-generator` | Cubrir casos válidos y cada categoría de rechazo definida en esta Spec. |
| `skills/INDEX.md` | Regenerarlo mediante `skill-creator` para publicar la skill ya validada; no editarlo manualmente. |
| `CHANGELOG.md` | Registrar el nuevo flujo y la revisión notable de la plantilla con una única marca UTC final. |

No se creará un ADR para adoptar este formato: es una decisión de gobernanza
documental ya prescrita por esta Spec, no una decisión sobre la arquitectura del
CMS.

## 9. Datos, API y PageBuilder

El esquema E-R, las migraciones, los contratos de API, las firmas de código de
aplicación y las estructuras JSON del PageBuilder **no son aplicables**. El
alcance se limita a documentación y tooling de la skill.

## 10. Criterios de aceptación

1. `docs/plantilla_ADR.md` es el único formato de salida y exige la ruta
   `docs/adr/ADR-NNNN-titulo-kebab-case.md` con identificador coherente e
   inmutable.
2. Todos los timestamps tienen precisión de minuto, usan reloj de 24 horas, UTC
   explícito y el formato `YYYY-MM-DD HH:mm UTC`.
3. Solo existen los cinco estados definidos y únicamente se permiten las
   transiciones especificadas.
4. Los ADR reemplazados y reemplazantes se enlazan mediante campos recíprocos y
   coherentes con el estado.
5. La plantilla exige ámbito e impacto transversal, contexto, restricciones,
   decisión, criterios, consecuencias positivas, consecuencias negativas,
   alternativas y revisión futura.
6. Las erratas `implicaicones`, `implicaoens` y `edbería` no aparecen en la
   plantilla final y se usan `implicaciones` y `debería`.
7. `skills/adr-generator/SKILL.md` conserva el procedimiento y apunta a la
   plantilla sin duplicar su estructura como otro formato mantenible.
8. La skill rechaza placeholders incompletos, menos de dos alternativas
   razonables, alternativas sin análisis, o consecuencias positivas o negativas
   ausentes o genéricas.
9. Las pruebas cubren identificación, timestamps, estados, transiciones,
   reemplazos, estructura, ámbito transversal, placeholders, alternativas,
   consecuencias y revisión futura, incluyendo casos positivos y negativos.
10. `adr-generator` se crea mediante `skill-creator` y solo aparece en el índice
    regenerado después de superar las validaciones.

## 11. Plan de implementación propuesto

1. Aprobar esta Spec y ordenar explícitamente
   `/build docs/specs/SPEC-adr-generator.md`.
2. Usar `skills/skill-creator/SKILL.md` para crear el andamiaje de
   `adr-generator` y regenerar el índice conforme al flujo existente.
3. Revisar `docs/plantilla_ADR.md` para convertirla en el formato canónico
   definido en esta Spec.
4. Implementar en la skill el procedimiento y las validaciones, manteniendo la
   separación entre formato y flujo.
5. Añadir y ejecutar primero las pruebas enfocadas de `adr-generator`, después
   la suite de skills afectada y las herramientas de formato o análisis ya
   configuradas.
6. Revisar el diff completo, registrar el cambio notable en `CHANGELOG.md` y
   fijar una única marca UTC al finalizar la entrega.
