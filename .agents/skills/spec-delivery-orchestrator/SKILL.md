---
name: spec-delivery-orchestrator
description: "Orquestar la ejecución de una Spec funcional aprobada mediante un constructor aislado y una auditoría posterior independiente, con preflight de Docker, correcciones en la sesión constructora y reauditoría de delta; no redacta ni aprueba Specs, no sustituye security-audit ni ejecuta cambios fuera del alcance aprobado. Usar cuando se implemente durante /build una docs/specs/SPEC-*.md funcional en estado Aprobada; no usar para planificación, creación o aprobación de Specs, cambios no funcionales aislados, ni como sustituto de quality gates o auditorías de seguridad."
---

# Orquestar Entrega de Specs

## Objetivo

Entregar una Spec funcional `Aprobada` sin ocupar la conversación principal con
la implementación. Coordina un constructor aislado, una auditoría posterior e
independiente y los ciclos de corrección necesarios. No redacta, aprueba ni
amplía Specs; no sustituye quality gates ni las skills especializadas.

## Precondiciones

1. Confirmar `/build`, una Spec funcional concreta en estado `Aprobada`, rama
   válida y worktree conocido. La Spec es la única autorización funcional.
2. El agente principal lee `AGENTS.md`, SDD, Spec, ADR, código, pruebas y
   documentación afectados. Debe realizar el clash check antes de delegar.
3. Si la Spec exige migraciones, persistencia, matriz multi-motor u otra
   comprobación Docker, ejecutar `docker info` antes de iniciar al constructor.
   Si Docker no está disponible, informar la limitación antes de delegar. La
   implementación puede continuar solo si el alcance sigue verificable, pero la
   Spec no puede declararse `Completada` mientras falte una comprobación exigida.

## Roles Aislados

Preferir sesiones o conversaciones independientes cuando la plataforma las
soporte. Si no, usar tareas o subagentes independientes. La plataforma puede no
permitir controlar el modelo, crear una conversación separada o garantizar
ejecución en segundo plano; declarar esa limitación, sin presentar el aislamiento
como garantizado.

Cada tarea delegada debe releer `AGENTS.md` y recibir las mismas restricciones de
alcance, arquitectura, seguridad, secretos, ramas, pruebas y entrega que el
agente principal. Su única especialización es su función:

- **Constructor:** antes de editar, reconcilia la Spec con el estado actual de
  código, pruebas y documentación. Si encuentra conflicto material, se detiene y
  lo informa. Implementa solo el alcance aprobado, usa `documentation-maintainer`
  para documentación ordinaria aplicable y `adr-generator` solo si surge una
  decisión arquitectónica no prescrita. No hace commit, push, cambio de rama ni
  modifica cambios ajenos.
- **Auditor:** se inicia solo después de que termine el constructor. Es de solo
  lectura y no ejecuta cambios. Revisa el diff completo, incluidos archivos sin
  seguimiento, contra la Spec, código, pruebas, documentación, roadmaps y
  changelog. Carga `security-audit` cuando exista una frontera de confianza; el
  uso de esa skill no sustituye la comprobación funcional y documental.

## Flujo

1. Lanzar el constructor aislado y conservar su identificador de sesión o tarea.
   Su entrega debe incluir reconciliación, archivos modificados, pruebas,
   validaciones, estado Docker, limitaciones y estado Git.
2. Cuando termine, lanzar un auditor nuevo e independiente. Debe enumerar todos
   los hallazgos que requieran edición en una única respuesta, con veredicto
   `confirmed`, `needs_validation` o `rejected`, evidencia, impacto y prueba o
   corrección mínima. Debe comprobar explícitamente que documentación y código
   describen el mismo comportamiento.
3. Para cada hallazgo `confirmed` que requiera cambios, reanudar la misma sesión
   o tarea del constructor y entregarle el conjunto completo de hallazgos. El
   constructor corrige solo esos elementos y ajusta las pruebas necesarias.
4. Tras cada corrección, iniciar una auditoría nueva e independiente sobre el
   diff resultante. No reutilizar el veredicto anterior como evidencia de que la
   corrección es segura ni auditar mientras otro agente edita el mismo worktree.
5. Repetir el ciclo hasta que no queden hallazgos `confirmed` pendientes. Un
   `needs_validation` en una frontera sensible impide declarar la validación
   completamente superada: asignar comprobación, responsable y condición de
   cierre.
6. El agente principal ejecuta o verifica pruebas enfocadas, suite afectada,
   formato, análisis, build, auditorías de dependencias y matriz Docker que la
   Spec exija. Usa `spec-maintainer` para validar estructura y estado de la Spec.
   Revisa el diff, documentación, deuda y excepciones antes de marcarla
   `Completada`.

## Límites

- Constructor y auditor no escriben en paralelo sobre el mismo worktree.
- La auditoría no corrige archivos ni omite hallazgos para acelerar el cierre.
- El agente principal no sustituye las validaciones omitidas por resultados de
  subagentes ni declara superado un control no ejecutado.
- No usar esta skill para planificar, redactar o aprobar Specs, ni para cambios
  no funcionales aislados.
