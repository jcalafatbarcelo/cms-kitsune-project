# SPEC: Mantenimiento de advisories Symfony de noviembre de 2026

- **Estado:** Completada
- **Perfil:** maintenance
- **Origen de la planificación:** Fallo del check `Composer security audit` del PR #60 el 26 de noviembre de 2026; petición de corrección del error restante.
- **Spec relacionada:** `docs/specs/SPEC-symfony-security-and-ci-portability-maintenance.md`

## 1. Objetivo o problema

Restaurar el check `Composer security audit` del PR #60 sin cambiar el
comportamiento del CMS. El audit detecta seis advisories publicados el 25 de
noviembre de 2026 que afectan a cuatro componentes Symfony bloqueados en
`composer.lock`.

## 2. Contexto y evidencia

**Fuente del comportamiento esperado:**
`docs/architecture/dependency-security.md`: los advisories no se ignoran, el
lockfile se actualiza deliberadamente y la corrección se valida con instalación
reproducible, pruebas y audits.

El job ejecuta:

```shell
composer audit --locked --abandoned=fail --no-plugins --no-scripts
```

y finaliza con código `1`, informando estos advisories:

| Paquete | Versión actual | Versión corregida | Advisories |
| :--- | :--- | :--- | :--- |
| `symfony/html-sanitizer` | `8.1.11` | `8.1.12` | `PKSA-8dv1-1nwm-344v` / CVE-2026-133756 |
| `symfony/http-kernel` | `8.1.11` | `8.1.12` | `PKSA-k1tg-4k6j-w4xf` / CVE-2026-133743 |
| `symfony/mailer` | `8.1.10` | `8.1.12` | `PKSA-vqxj-j8fm-1q3k`, `PKSA-9r8k-3vgs-8f3v` |
| `symfony/routing` | `8.1.11` | `8.1.12` | `PKSA-pbtx-4qgh-mmkh`, `PKSA-2vy6-qchn-f13r` |

El dry-run reproducible:

```shell
composer update symfony/html-sanitizer symfony/http-kernel \
  symfony/mailer symfony/routing \
  --with-dependencies --minimal-changes --dry-run --no-scripts --no-plugins
```

resuelve exactamente cinco actualizaciones de lockfile:

```text
symfony/html-sanitizer  8.1.11 -> 8.1.12
symfony/http-foundation 8.1.11 -> 8.1.12
symfony/http-kernel     8.1.11 -> 8.1.12
symfony/mailer          8.1.10 -> 8.1.12
symfony/routing         8.1.11 -> 8.1.12
```

`composer.json` no necesita cambios: `symfony/html-sanitizer` ya está declarado
como `^8.1` y Laravel 13.40 admite `symfony/http-kernel`, `mailer` y `routing`
en `^7.4 || ^8.0`.

**Pasos de reproducción:**

1. Ejecutar el audit sobre el lockfile actual.

   Resultado actual: seis advisories y código de salida `1`.

2. Actualizar los cinco componentes Symfony corregidos y repetir el audit.

   Resultado esperado: ningún advisory, ningún paquete abandonado y código `0`.

## 3. Alcance

- Actualizar deliberadamente `composer.lock` solo para bloquear las cinco
  versiones Symfony corregidas identificadas en esta Spec.
- Mantener `composer.json` sin cambios.
- Revisar las notas de seguridad y compatibilidad de Symfony 8.1.12.
- Ejecutar instalación reproducible, audit, pruebas enfocadas, suite completa,
  matriz SQLite/MySQL/MariaDB, formato, pruebas JavaScript y build de Vite.
- Registrar la corrección en `CHANGELOG.md` una vez validada.

### Fuera de alcance

- Cambiar reglas de negocio, modelo de datos, API pública, autorización,
  comportamiento de UI, rutas o contratos de módulos.
- Actualizar dependencias npm, GitHub Actions o paquetes no afectados.
- Ignorar, suprimir o aceptar temporalmente advisories.
- Cambiar workflows, permisos o comandos de CI.
- Introducir pruebas funcionales ajenas a la regresión de dependencias.

### Alcance diferido

- Cualquier advisory o actualización que aparezca después de esta corrección se
  tratará en un incremento independiente cuando el audit o Dependabot lo
  detecten.

## 4. *Clash check*

- **SDD inicial:** conserva Laravel, PHP 8.x, Blade y el monolito modular. Los
  parches no cambian el stack ni sus fronteras. Sin conflicto.
- **ADR-0003:** mantiene SQLite, MySQL 8.4 y MariaDB 11.4 como baseline; la
  matriz sigue siendo obligatoria. Sin conflicto.
- **Política de seguridad de dependencias:** exige actualizar el lockfile,
  revisar cambios directos y transitivos, instalar reproduciblemente y no
  recomendar fusión con audits fallidos. Esta Spec aplica esa política.
- **SPEC-symfony-security-and-ci-portability-maintenance:** actualizó componentes
  Symfony a 8.1.11. Esta Spec continúa la misma práctica para advisories nuevos,
  sin modificar su alcance histórico.
- **Código y pruebas:** `html-sanitizer` protege contenido `rich-text`; routing,
  HTTP kernel, mailer y http-foundation son dependencias de Laravel. Los parches
  corrigen bypasses conocidos sin cambiar la API usada por el CMS.

## 5. Requisitos y bloques técnicos aplicables

No cambian las reglas de negocio, el modelo de datos ni los contratos públicos.

### Dependencias y compatibilidad

- La resolución final debe contener exactamente:
  `symfony/html-sanitizer@8.1.12`,
  `symfony/http-foundation@8.1.12`,
  `symfony/http-kernel@8.1.12`,
  `symfony/mailer@8.1.12` y
  `symfony/routing@8.1.12`.
- `composer.json` debe permanecer sin cambios.
- El lockfile no debe incorporar otras actualizaciones, instalaciones ni
  eliminaciones.
- La instalación desde `composer.lock` debe ser reproducible con PHP 8.5 y
  Composer 2.
- Deben revisarse los cambios de seguridad de Symfony 8.1.12, especialmente
  sanitización de meta refresh, forwarded headers, URLs generadas, routing y
  transporte SMTP.

### Datos, migraciones, API, frontend y PageBuilder

- **E-R y migraciones:** No aplica; no cambia el modelo de datos.
- **API y contratos públicos:** No aplica; no cambia rutas, comandos, firmas ni
  respuestas.
- **Vue, Blade y PageBuilder:** No aplica; no se modifican componentes, schemas
  ni renderizado.
- **Asincronía e integraciones:** No aplica; no se añaden colas, reintentos ni
  servicios externos.

### Seguridad

- El audit de Composer debe finalizar sin advisories ni paquetes abandonados.
- No se permite una allowlist, supresión o reducción del umbral de severidad.
- La actualización debe mantener las versiones corregidas bloqueadas en el
  lockfile.

## 6. Calidad, seguridad y deuda

### Garantías obligatorias

- `composer audit --locked --abandoned=fail --no-plugins --no-scripts` finaliza
  con código `0`.
- La suite completa y la matriz SQLite/MySQL/MariaDB pasan con el lockfile
  actualizado.
- La instalación reproducible no modifica `composer.json` ni el lockfile.
- No se introducen secretos ni artefactos generados.

### Riesgos aceptados

- No aplica: la corrección está disponible y no requiere aceptar riesgo residual.

### Deuda técnica

- No aplica: no se difiere ninguna garantía de seguridad necesaria.

## 7. Criterios de aceptación

- **CA-01:** El audit de Composer finaliza con código `0` y sin advisories.
- **CA-02:** El lockfile contiene las cinco versiones Symfony `8.1.12` indicadas
  y no incorpora otros cambios; `composer.json` permanece intacto.
- **CA-03:** La instalación reproducible, la suite completa y la matriz
  SQLite/MySQL/MariaDB pasan.
- **CA-04:** Pint, pruebas JavaScript y build de Vite pasan.
- **CA-05:** `CHANGELOG.md` registra la corrección de seguridad y la fecha.

## 8. Trazabilidad de pruebas y documentación

| Criterio | Riesgo cubierto | Nivel de prueba | Evidencia esperada | Impacto documental |
| :--- | :--- | :--- | :--- | :--- |
| CA-01 | Advisory de seguridad en producción | Integración | `composer audit --locked` con código `0` | `CHANGELOG.md` |
| CA-02 | Actualización no controlada del lockfile | Integración | Diff del lockfile limitado a cinco paquetes | `docs/architecture/dependency-security.md` |
| CA-03 | Regresión en Laravel, routing o HTTP | Suite y matriz | Pruebas SQLite, MySQL y MariaDB | No aplica |
| CA-04 | Regresión de estilos o frontend | Build y JS | Pint, `npm run test:js`, `npm run build` | No aplica |
| CA-05 | Pérdida de trazabilidad del cambio | Documental | Entrada en `CHANGELOG.md` | `CHANGELOG.md` |

## 9. Plan de implementación

1. Actualizar el lockfile con los cinco parches Symfony y revisar el diff.
2. Ejecutar instalación reproducible y audits.
3. Ejecutar pruebas enfocadas y suite completa; repetir la matriz de bases.
4. Ejecutar Pint, pruebas JavaScript y build de Vite.
5. Registrar la corrección en `CHANGELOG.md` y cerrar la Spec.

## 10. Decisiones abiertas

No aplica: la versión corregida, el alcance y los criterios están definidos.
