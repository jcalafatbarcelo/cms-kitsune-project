# SPEC: Mantenimiento de seguridad de Laravel Framework

- **Estado:** Aprobada
- **Perfil:** maintenance
- **Origen de la planificación:** `composer audit --locked --abandoned=fail`
  detecta `CVE-2026-128083` (`PKSA-yxbx-3c9s-cjt9`) en la versión bloqueada de
  `laravel/framework`.
- **Spec relacionada:** [SPEC-pages-backoffice-ux](SPEC-pages-backoffice-ux.md),
  en revisión; este mantenimiento restablece el gate que impide cerrar su
  validación.

## 1. Objetivo o problema

Eliminar el advisory de severidad baja `CVE-2026-128083`, titulado “Laravel:
Task Output Injection - Concurrency Process”, que afecta a
`laravel/framework` en versiones `>=13.0.0,<13.40.0`.

El lockfile bloquea Laravel `v13.39.0`, una versión afectada. La restricción
existente de `composer.json` (`^13.17`) permite la primera versión corregida,
`v13.40.0`. El audit Composer falla y, según la política de seguridad de
dependencias, no se puede cerrar ni recomendar integrar una entrega mientras
ese control permanezca en rojo.

## 2. Contexto y evidencia

La ejecución local del 22 de noviembre de 2026 produjo:

```text
Package: laravel/framework
Advisory: PKSA-yxbx-3c9s-cjt9
CVE: CVE-2026-128083
Affected versions: >=13.0.0,<13.40.0
```

**Fuente del comportamiento esperado:** la
[política de seguridad de dependencias](../architecture/dependency-security.md)
exige que el lockfile no contenga advisories conocidos y que las actualizaciones
se validen de forma reproducible.

**Pasos de reproducción:**

1. Ejecutar `composer audit --locked --abandoned=fail --no-plugins
   --no-scripts` desde la raíz.
2. Consultar la versión de `laravel/framework` bloqueada en `composer.lock`.

**Resultado actual:** el audit termina con código `1` e informa
`PKSA-yxbx-3c9s-cjt9` porque el lockfile contiene `laravel/framework@v13.39.0`.

**Resultado esperado:** el audit termina con código `0`, sin advisories ni
paquetes abandonados, con Laravel bloqueado en la primera versión corregida y
sin cambio funcional del CMS.

`composer outdated laravel/framework --direct` identifica `v13.43.0` como la
versión más reciente disponible. Este incremento selecciona deliberadamente
`v13.40.0`, el primer parche no afectado, para limitar el cambio y aislar la
remediación. Una actualización adicional dentro de Laravel 13 requerirá su
propio mantenimiento si fuera necesaria.

La instalación local de `vendor` informa `v13.31.0`, mientras que el artefacto
reproducible y auditado es `composer.lock` con `v13.39.0`. La instalación
reproducible obligatoria debe sincronizar `vendor` con el lockfile resultante;
la versión de `vendor` previa no constituye evidencia de entrega.

## 3. Alcance

- Actualizar deliberadamente `laravel/framework` en `composer.lock` a
  `v13.40.0` usando Composer, con cambios transitivos solo si la resolución
  mínima los requiere.
- Mantener sin cambios `composer.json`, las restricciones de plataforma, los
  módulos, las migraciones, la configuración de aplicación y los contratos del
  CMS.
- Revisar las notas de publicación y cambios de Laravel desde `v13.39.0` hasta
  `v13.40.0`, con atención a `Illuminate\Concurrency` y a los componentes
  utilizados por el CMS.
- Revisar el diff completo del lockfile y documentar cualquier cambio
  transitivo estrictamente necesario.
- Ejecutar instalación reproducible, pruebas enfocadas de las superficies
  Laravel afectadas, suite completa, matriz SQLite/MySQL/MariaDB, formato,
  pruebas JavaScript, build y audits configurados.
- Ejecutar una auditoría de seguridad independiente sobre el diff final.
- Registrar la remediación en `CHANGELOG.md` al completarla.

### Fuera de alcance

- Actualizar Laravel a una versión posterior a `v13.40.0`.
- Cambiar `composer.json`, añadir o retirar dependencias directas.
- Modificar código de aplicación, comportamiento, rutas, datos, migraciones,
  autorización, sanitización o contratos públicos para adaptarse a Laravel.
- Ignorar el advisory, reducir el umbral de auditoría o añadir excepciones.
- Actualizar dependencias npm, GitHub Actions, PHP o imágenes de CI.
- Marcar como completada `SPEC-pages-backoffice-ux` sin que sus evidencias y
  todos sus gates aplicables estén cerrados.

### Alcance diferido

- La actualización a `v13.43.0` u otras versiones posteriores de Laravel 13 se
  evaluará en una Spec independiente, con sus notas de publicación y riesgo
  propios.

## 4. *Clash check*

- **SDD inicial:** se conservan Laravel, Eloquent, Blade y el monolito modular;
  no cambia la arquitectura ni el alcance funcional. No hay conflicto.
- **ADR-0001:** no se modifica la integración Blade/Vue ni la carga de assets.
  No hay conflicto.
- **ADR-0003:** se mantienen PHP 8.4+, SQLite moderno, MySQL 8.4 y MariaDB
  11.4; la matriz multi-motor sigue siendo obligatoria. No hay conflicto.
- **SPEC-pages-backoffice-ux:** no altera sus criterios ni su código. La
  corrección restaura el audit transversal requerido para su cierre. No hay
  conflicto.
- **SPEC-symfony-security-and-ci-portability-maintenance:** el mantenimiento
  anterior corrigió componentes Symfony y prescribe una actualización mínima
  del lockfile y gates completos. Esta Spec adopta el mismo enfoque para Laravel
  sin reabrir su alcance. No hay conflicto.
- **Política de seguridad de dependencias:** exige lockfiles reproducibles,
  revisión de cambios directos/transitivos, pruebas y audits sin excepciones.
  Esta Spec la aplica. No hay conflicto.

## 5. Requisitos y bloques técnicos aplicables

### Dependencias y compatibilidad

- La resolución final bloquea `laravel/framework@v13.40.0`.
- `composer.json` permanece sin cambios.
- Antes de modificar el lockfile se ejecuta un dry-run con el paquete objetivo,
  `--with-dependencies`, `--minimal-changes`, `--no-scripts` y `--no-plugins`.
- Si Composer propone instalar, eliminar o actualizar paquetes no necesarios
  para alcanzar `v13.40.0`, se detiene el incremento para revisar el alcance.
- Se revisan release notes, requisitos de PHP y compatibilidad con Laravel 13,
  Pest 5, `nwidart/laravel-modules`, TinyMCE y los componentes Symfony ya
  bloqueados.
- `composer install --no-interaction --prefer-dist` instala exactamente el
  lockfile resultante, sin ignorar requisitos de plataforma.

### Datos, migraciones, API, módulos y contratos públicos

No aplicable: no se crean ni modifican entidades, tablas, columnas, índices,
migraciones, backfills, rutas, requests, responses, autorización, firmas,
eventos ni contratos intermodulares. No cambian reglas de negocio ni
comportamiento observable.

### Frontend, Vue 3, PageBuilder y JSON

No aplicable: no se modifican componentes, CSS, JavaScript, assets, Vue,
PageBuilder, schemas JSON ni renderizado Blade. El build frontend se ejecuta
como quality gate transversal.

### Asincronía e integraciones externas

No aplicable: no se incorporan colas, reintentos de negocio, proveedores,
credenciales ni integraciones. Se revisa específicamente el cambio de seguridad
en `Illuminate\Concurrency`, pero no se adopta ni configura esa capacidad.

### Seguridad

- No se aceptan allowlists, supresiones ni advisories ignorados.
- Se conserva la política actual de HTTPS, proxies, CSRF, autenticación,
  autorización, validación y sanitización.
- La actualización se limita al gestor de dependencias y al lockfile; no se
  incorporan secretos, repositorios, mirrors ni configuración de Composer.
- `security-audit` revisa el diff final por afectar al framework y a una
  frontera de ejecución de procesos.

## 6. Calidad, seguridad y deuda

### Garantías obligatorias

- TDD no aporta ventaja neta: no se introduce comportamiento nuevo; el riesgo
  se valida mediante la instalación reproducible, regresiones existentes y
  gates configurados.
- Ejecutar primero pruebas enfocadas de HTTP, autenticación, rutas, contenido
  sanitizado y ejecución de comandos; después `composer test`.
- Ejecutar la matriz de integración prescrita en SQLite, MySQL 8.4.11 y
  MariaDB 11.4.13.
- Ejecutar `composer lint`, `npm ci --ignore-scripts`, `npm run test:js`,
  `npm run build`, `composer validate`, `composer audit` y `npm audit`.
- Revisar el diff para excluir cambios no autorizados, generados accidentales y
  secretos.
- Repetir en CI los checks aplicables del Pull Request antes de recomendar la
  integración.

### Riesgos aceptados

No aplica: no se aceptan vulnerabilidades conocidas, incompatibilidades ni
checks omitidos.

### Deuda técnica

No aplica: el mantenimiento no introduce una excepción temporal ni una versión
local. Dependabot y los audits diarios continúan detectando advisories futuros.

## 7. Criterios de aceptación

- **CA-01:** `composer.lock` bloquea `laravel/framework@v13.40.0`,
  `composer.json` no cambia y el diff no contiene cambios de dependencias no
  justificados por la resolución mínima.
- **CA-02:** `composer validate` y `composer audit --locked
  --abandoned=fail` terminan con código `0`, sin advisories ni paquetes
  abandonados.
- **CA-03:** La instalación reproducible, las pruebas enfocadas, la suite
  completa y la matriz SQLite/MySQL/MariaDB pasan sin modificar contratos,
  datos, configuración ni comportamiento del CMS.
- **CA-04:** Pint, pruebas JavaScript, build frontend y audit npm pasan; la
  revisión independiente de seguridad no deja hallazgos confirmados pendientes.
- **CA-05:** El changelog registra la corrección y los checks aplicables de CI
  se reejecutan sobre el commit resultante antes de recomendar su integración.

## 8. Trazabilidad de pruebas y documentación

| Criterio | Riesgo cubierto | Nivel de prueba | Evidencia esperada | Impacto documental |
| :--- | :--- | :--- | :--- | :--- |
| CA-01 | Resolución ampliada o cambio de dependencia no revisado | Integración de Composer | Dry-run, diff de `composer.lock` y versiones efectivas | No aplica |
| CA-02 | Advisory conocido o lockfile inválido | Integración de gestor de paquetes | `composer validate` y audit sin advisories | `CHANGELOG.md`, Seguridad |
| CA-03 | Regresión de framework, HTTP, BD o comandos | HTTP, integración y matriz multi-motor | Pruebas enfocadas, suite y matriz en verde | No aplica |
| CA-04 | Regresión transversal de assets o seguridad | Formato, JavaScript, build, audit y auditoría independiente | Gates en verde y ausencia de hallazgos confirmados | No aplica |
| CA-05 | Integración sin trazabilidad o CI obsoleto | CI y revisión documental | Checks remotos sobre el commit y changelog actualizado | `CHANGELOG.md` |

## 9. Plan de implementación

1. Ejecutar el dry-run de Composer con la versión exacta `v13.40.0`, revisar
   release notes y el conjunto de cambios directos y transitivos.
2. Actualizar únicamente la resolución aprobada, instalar desde el lockfile y
   comprobar la versión efectiva.
3. Ejecutar pruebas enfocadas, suite, matriz multi-motor, formato, JavaScript,
   build y ambos audits.
4. Ejecutar la auditoría de seguridad independiente, corregir todos los
   hallazgos confirmados y repetirla sobre el delta.
5. Actualizar el changelog, revisar el diff final y reejecutar los checks de CI
   antes de recomendar la integración.

## 10. Decisiones abiertas

No aplica. La versión mínima corregida está determinada por el advisory y la
restricción vigente; actualizaciones adicionales de Laravel quedan fuera de este
incremento.
