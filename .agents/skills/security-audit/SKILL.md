---
name: security-audit
description: "Auditar defensivamente cambios y componentes del CMS para detectar vulnerabilidades verificables en límites de confianza de Laravel, Blade, Vue, módulos, datos, medios, publicación y CI; no sustituye Specs, revisión funcional ni quality gates. Usar cuando el usuario solicite una auditoría de seguridad, una revisión antes de un Pull Request, o se cierre un cambio que afecte a autenticación, autorización, entradas HTTP, renderizado, PageBuilder, medios, datos, publicación, trabajos, cachés o workflows; no usar como checklist automática para cambios sin frontera de confianza."
---

# security-audit

## Objetivo

Encontrar vulnerabilidades reales en código propio antes de preparar un Pull
Request. Un hallazgo confirmado debe demostrar una frontera de confianza rota:
un principal con menos privilegio controla una entrada o acción, cruza un
control previsto y obtiene un resultado observable sobre otro principal,
recurso o estado protegido.

La metodología se inspira en `cloudflare/security-audit-skill`, adaptada al
CMS y a sus controles. No sustituye una Spec aprobada, pruebas de aceptación,
revisión funcional, controles de dependencias ni CI.

## Modos

### Revisión de cambio

Usar para revisar un diff antes de abrir o actualizar un Pull Request. No crea
archivos ni modifica el código. Devuelve hallazgos y cobertura en la respuesta.
Es el modo predeterminado cuando la petición no exige artefactos de auditoría.

Resolver el alcance antes de inspeccionar: usar la base explícita del Pull
Request cuando exista; si no, usar el merge-base con `main` y revisar
`<merge-base>...HEAD`. No incluir cambios locales sin confirmar salvo petición
expresa. Si no se puede identificar una base fiable, solicitarla y no declarar
cobertura.

### Auditoría completa

Usar solo ante una solicitud expresa de auditoría completa, pentest de código o
informe de auditoría. Delimitar repositorio, paths, perfil (`quick`, `standard`
o `deep`) y presupuesto de subagentes antes de empezar. Los artefactos se crean
fuera del repositorio, salvo que el usuario haya elegido una ruta ignorada por
Git y se haya comprobado que el directorio completo lo está.

No afirmar cobertura completa: una auditoría puede declarar áreas revisadas,
diferidas, fuera de alcance o pendientes de validar.

| Perfil | Uso | Mínimo de cobertura |
| --- | --- | --- |
| `quick` | Diff pequeño o primera revisión | Una pasada por frontera aplicable y una verificación independiente por candidato |
| `standard` | Pull Request sensible o módulo completo | Unidad por superficie, frontera, módulo y clase; crítica de cobertura y verificación independiente final |
| `deep` | Identidad, autorización, PageBuilder, medios o release | `standard` separado por lifecycle y subsistema, con segunda pasada de las unidades críticas |

Reservar el presupuesto para verificación antes de delegar búsquedas. Si no
alcanza, marcar unidades como `deferred`; no reducir la evidencia necesaria
para confirmar un hallazgo.

## Preparación obligatoria

1. Leer `AGENTS.md`, el SDD, la Spec aprobada que cubra el cambio y los ADR
   aplicables. La skill no autoriza `/build` ni amplía alcance.
2. Inspeccionar el diff, el código y las pruebas afectados; comprobar el estado
   del worktree. Para auditoría completa, registrar además el commit revisado y
   si existen cambios sin confirmar.
3. Identificar actores, recursos protegidos, superficies de entrada, controles
   previstos y rutas paralelas hacia la misma operación.
4. Seleccionar únicamente las clases pertinentes. Explicar por qué se excluye
   una frontera visible relevante; no cargar listas genéricas por el lenguaje o
   una dependencia.

Para la revisión de cada frontera, consultar
[`references/cms-attack-guides.md`](references/cms-attack-guides.md). En
auditoría completa, aplicar además el contrato de
[`references/audit-contract.md`](references/audit-contract.md).

## Fronteras del CMS

Aplicar las clases que correspondan a lo inspeccionado:

| Frontera | Revisar |
| --- | --- |
| HTTP y sesión | autenticación, autorización, CSRF, validación de requests, redirecciones, cookies, cabeceras y errores |
| Eloquent y módulos | IDOR, scopes por recurso, mass assignment, consultas bulk, relaciones, políticas y rutas alternativas |
| Blade y Vue | datos no confiables en HTML/DOM, URLs, renderizado de contenido, `postMessage`, almacenamiento y cambios de sesión |
| PageBuilder | schema, validación al persistir y al leer, contenido de bloque de segundo orden y renderizado Blade |
| Medios | MIME y contenido real, nombre/ruta, visibilidad, descarga, metadatos, transformaciones y autorización |
| Idiomas y publicación | acceso a borradores/previews, publicación en cascada, rutas, caché, invalidación y cambios de idioma |
| Datos y lifecycle | aislamiento por propietario, caché, exportación/importación, jobs, migraciones, borrado y restauración |
| Supply chain y CI | inputs mutables, eventos de Actions, permisos, artefactos, cachés y secretos |

Cuando aparezcan integración LLM, webhooks, colas externas, almacenamiento de
objetos, proxy/CDN o infraestructura cloud, revisar la frontera concreta y
marcar como `needs_validation` los controles que no estén versionados.

## Flujo de revisión

1. Crear una lista de unidades de cobertura: `superficie + frontera + módulo +
   clase de ataque`. Para un diff pequeño basta con una unidad por frontera; no
   revisar por archivo de manera aislada.
2. Recorrer cada unidad desde entrada hasta decisión de confianza y sink. Buscar
   inyección, autorización, manejo de recursos, secretos, lógica de negocio,
   fugas, estados de error, lifecycle y cadenas entre módulos cuando apliquen.
3. Para cada candidato, documentar actor, entrada controlable, control previsto,
   recurso o principal afectado, ruta fuente y resultado mínimo observable.
4. Validar cada candidato con una segunda lectura independiente del código. El
   agente que lo encontró no puede confirmarlo por sí mismo.
5. Contrastar defaults del framework y la versión configurada. Laravel, Vue,
   navegador, proxy, proveedor o configuración no visibles no se presumen.
6. Clasificar el resultado y registrar las unidades cubiertas y no cubiertas.
7. Para cada hallazgo confirmado, proponer el cambio mínimo en el último punto
   fiable de decisión y una prueba de regresión proporcional.

En auditoría completa, el archivo `coverage-ledger.json` y `findings.json`
deben validar antes de informar resultados:

```shell
python .agents/skills/security-audit/scripts/validate_audit_artifacts.py \
  --ledger /ruta/externa/coverage-ledger.json \
  --findings /ruta/externa/findings.json
```

## Evidencia y veredictos

Usar exactamente uno de estos veredictos por candidato:

| Veredicto | Requisito |
| --- | --- |
| `confirmed` | Trazabilidad de código completa, frontera rota y resultado observable con evidencia fuente; solo incluye severidad si el impacto está demostrado |
| `needs_validation` | Hipótesis fundada en código cuya comprobación decisiva depende de infraestructura, configuración, navegador o sandbox no disponible; no asignar severidad |
| `rejected` | El código, una prueba existente o un control efectivo refuta la hipótesis; conservar el motivo para no reabrir el mismo falso positivo |

Un resultado debe incluir rutas y líneas, condición necesaria, recurso afectado,
evidencia, impacto concreto y la prueba o comprobación que sostiene el
veredicto. No presentar como vulnerabilidad una desviación de checklist, una
defensa adicional ausente, autoimpacto, un fallo hipotético de despliegue ni una
caída sin impacto de frontera.

Calibrar severidad por probabilidad e impacto demostrado. No superar el impacto
observado ni inferir acceso a producción desde un fixture local.

## Ejecución segura

La inspección de fuentes es de solo lectura. Ejecutar tests, builds, parsers,
browsers o fixtures controlados por el objetivo solo dentro de un sandbox que
demuestre simultáneamente: sin red externa, entorno vacío con allowlist, código
y toolchain de solo lectura, escrituras limitadas a scratch y límites de CPU,
memoria, procesos, disco y tiempo.

Si no se demuestra ese aislamiento, no ejecutar código controlado por el
objetivo para confirmar una vulnerabilidad. Mantener el candidato como
`needs_validation` y describir una comprobación local segura que el responsable
pueda realizar. Nunca probar entornos desplegados, identidades reales, secretos,
servicios compartidos ni disponibilidad.

## Salida

En revisión de cambio, informar primero los hallazgos por severidad con
`archivo:línea`, después las unidades cubiertas, los límites y las validaciones
no ejecutadas. Si no hay hallazgos, declarar expresamente la cobertura y los
riesgos no revisados.

En auditoría completa, mantener fuera de Git un ledger de cobertura y registros
estructurados de los tres veredictos. Derivar el informe solo desde esos
registros después de su validación independiente. No incluir secretos, payloads
operativos contra servicios reales ni instrucciones de explotación.

## Decisión de entrega

| Resultado | Acción |
| --- | --- |
| `confirmed` `critical` o `high` | No recomendar abrir ni fusionar el Pull Request hasta corregirlo o aceptar explícitamente el riesgo |
| `confirmed` `medium` o `low` | Corregir antes de fusionar o registrar aceptación explícita con alcance, responsable y motivo |
| `needs_validation` en frontera sensible | No declarar la revisión superada; asignar responsable, comprobación y condición de cierre |
| `rejected` | Conservar el motivo de descarte en el registro de auditoría |
| `deferred` u `out_of_scope` | Declarar la limitación; no equivalen a ausencia de riesgo |

La corrección de un hallazgo que cambie comportamiento sigue requiriendo una
Spec aprobada y una orden `/build` conforme a `AGENTS.md`.

## Límites

- No ejecutar la auditoría completa ni crear artefactos por una pregunta de
  seguridad o una revisión focalizada.
- No modificar el código auditado salvo que el usuario ordene un `/build` que
  cubra la corrección y exista una Spec aprobada si el cambio es funcional.
- No sustituir `composer audit`, `npm audit`, Dependency Review, Pint, Pest,
  Vite ni los checks de GitHub Actions.
- No presentar una revisión de agentes como certificación de seguridad o como
  sustitución de un pentest profesional.
