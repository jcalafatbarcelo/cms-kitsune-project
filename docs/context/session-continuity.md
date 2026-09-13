# Continuidad de sesión

## Objetivo

Esta guía describe cómo registrar resúmenes breves de las sesiones de trabajo
con agentes para poder reanudar una tarea cuando el contexto disponible se ha
reducido, ha habido una compactación o se inicia una sesión nueva. Está dirigida
a contributors del core y a los agentes que operan sobre el repositorio.

Las notas de continuidad son **memoria de trabajo local**, no documentación del
producto. Su finalidad es permitir retomar el trabajo con una lectura breve y
verificaciones dirigidas, sin volver a cargar conversaciones completas.

## Carácter de las notas

- **Auxiliares y no normativas.** No prescriben comportamiento, no aprueban
  Specs y no amplían su alcance.
- **Histórico, no instrucciones.** Describen lo ocurrido en una sesión anterior,
  no órdenes vigentes.
- **Prescindibles.** El producto debe poder entenderse y mantenerse sin ellas.
- **Locales.** No se versionan y no forman parte de las fuentes de verdad
  descritas en `AGENTS.md`.

Una conclusión necesaria para el equipo se traslada, con la revisión que
corresponda, a una Spec, un ADR o la documentación canónica. No se deja
únicamente en una nota de sesión.

## Ubicación y nombres

Las notas se guardan en:

```text
docs/context/sessions/
```

Este directorio está excluido de Git mediante `.gitignore`. Por tanto, las notas
no se incorporan al repositorio, no se comparten al clonar y no tienen respaldo
de Git. Cada persona mantiene su propia continuidad local.

Un archivo por sesión y tarea, con nombre
`YYYY-MM-DD-HHmm-<tarea>.md`; por ejemplo,
`2026-09-13-1530-continuidad-sesion.md`. Si una conversación aborda tareas
independientes, se separan sus notas para no mezclar contextos.

## Cuándo guardar una nota

- Al cerrar una sesión de trabajo.
- Al alcanzar un hito significativo o antes de cambiar de tarea.
- Antes de una pausa larga o cuando se prevea que el contexto pueda compactarse.

No conviene depender únicamente del aviso de contexto lleno para decidir
guardarla.

## Estructura de la nota

La estructura canónica está en
[Plantilla de nota de continuidad de sesión](session-template.md). Resume hechos,
estado y próximo paso; no reproduce archivos completos ni razonamiento extenso.
Separa de forma explícita hechos comprobados, decisiones documentadas, propuestas
no aprobadas y dudas pendientes.

## Cómo recuperar contexto

1. Indicar la ruta exacta de la nota que se quiere consultar. Es la opción
   preferente.
2. Si no se conoce la ruta, localizar candidatas por tarea. No cargar todas las
   notas ni elegir automáticamente la más reciente.
3. Si existen varias tareas candidatas y no está claro cuál continuar, preguntar
   antes de asumir una.
4. Ampliar el histórico solo si falta un dato concreto que la nota no recoge.

## Verificación tras leer una nota

Una nota describe el estado de una sesión anterior. Antes de dar algo por válido:

- contrastar las afirmaciones relevantes con el código, Git, las pruebas y los
  documentos actuales;
- no presentar una verificación antigua como si correspondiera al estado actual;
- confirmar si las referencias (rama, commit, Spec) siguen vigentes;
- tratar cualquier cambio sin commit como no garantizado.

## Límites y seguridad

- No incluir secretos, credenciales, valores de `.env`, volcados ni datos
  personales. Véase
  [Configuración y gestión de secretos](../architecture/configuration-and-secrets.md).
- No copiar transcripciones indiscriminadas de terminales o conversaciones.
- No presentar el contenido de una nota como documentación canónica ni como
  decisión aprobada.
- No enlazar a archivos de sesión concretos desde documentación versionada: no
  existen en un clon limpio.

## Modos de operación

- En `/plan` no se puede escribir ninguna nota: el modo es de solo lectura. El
  resumen se puede redactar en la respuesta, pero su materialización requiere
  `/build`.
- Una nota no activa `/build`, no aprueba una Spec, no amplía su alcance ni
  autoriza cambios por sí misma. Las reglas de modo y de fuentes de verdad están
  en `AGENTS.md`.
- Guardar o actualizar una nota es mantenimiento no funcional: requiere `/build`,
  no una Spec aprobada.

## Colaboración y conservación

Las notas no se exigen a otras personas para entender el proyecto. Para
compartir contexto puntual con otra persona, se prepara un resumen seleccionado y
revisado y se entrega por el canal acordado, en lugar de asumir que Git lo
distribuirá.

No se añade borrado automático ni un índice global. Las notas antiguas pueden
eliminarse manualmente cuando dejen de aportar continuidad.
