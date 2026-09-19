# Contrato de auditoría

Este contrato aplica únicamente a `full-audit`. Los archivos se guardan fuera
del repositorio auditado y se validan con `scripts/validate_audit_artifacts.py`.

## `coverage-ledger.json`

El documento es un array. Cada unidad incluye:

```json
{
  "coverage_id": "routes-web-post-pages::authorization",
  "surface": "POST /pages",
  "boundary": "page ownership",
  "module": "Modules/Pages",
  "attack_class": "access-control",
  "status": "covered"
}
```

`coverage_id` es estable dentro de una auditoría y representa `superficie +
frontera + módulo + clase`. Los estados permitidos son:

| Estado | Uso |
| --- | --- |
| `planned` | Unidad pendiente de revisar |
| `covered` | Revisión finalizada sin candidato abierto |
| `candidate` | Existe al menos un candidato vinculado en `findings.json` |
| `blocked` | La revisión no puede completarse por una limitación concreta |
| `deferred` | El presupuesto o plazo impide revisarla |
| `out_of_scope` | La frontera se identificó pero quedó fuera del alcance autorizado |

Cada `blocked`, `deferred` u `out_of_scope` debe incluir `reason`. Una unidad
`candidate` debe estar referenciada por al menos un hallazgo.

## `findings.json`

El documento es un array. Todo registro incluye `verdict`, `fingerprint`,
`title`, `coverage_ids` y `trace`. `coverage_ids` contiene una o más unidades
existentes del ledger. `trace` contiene al menos una referencia con `file`,
`line` y `description`.

| Veredicto | Campos adicionales obligatorios | Restricción |
| --- | --- | --- |
| `confirmed` | `severity`, `evidence`, `remediation` | `severity` es `low`, `medium`, `high` o `critical` |
| `needs_validation` | `blockers`, `validation_plan` | No incluir `severity` |
| `rejected` | `reason` | Conserva el control que refutó el candidato |

`fingerprint` identifica la causa raíz, no el veredicto. No duplicar un mismo
fingerprint dentro de una ejecución.

## Salida de revisión de cambio

La respuesta no necesita JSON, pero debe incluir estas secciones, incluso
cuando estén vacías:

1. Hallazgos ordenados por severidad, con `archivo:línea`.
2. Unidades cubiertas.
3. Unidades `deferred`, `blocked` u `out_of_scope` y su motivo.
4. Validaciones no ejecutadas y limitación concreta.
5. Recomendación de entrega según `SKILL.md`.

## Escenarios de evaluación manual

| Entrada | Resultado esperado |
| --- | --- |
| Cambio solo documental | No activar revisión completa; declarar que no hay frontera sensible modificada |
| Endpoint autenticado que recibe un ID | Revisar autorización del recurso e IDOR, no solo autenticación |
| Renderizado Blade de contenido persistido | Revisar XSS de segundo orden y el límite entre validación y salida |
| Subida de archivo | Revisar MIME, ruta, visibilidad, procesamiento y autorización de descarga |
| Cambio de workflow | Revisar evento, código controlable, permisos, SHA e identidad de artefactos |
| Sin sandbox verificable | No ejecutar objetivo; registrar `needs_validation` con un plan seguro |
