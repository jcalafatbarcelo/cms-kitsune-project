# SPEC: Remediación del audit de dependencias npm

- **Estado:** Revisión
- **Perfil:** maintenance
- **Origen de la planificación:** Petición `/build` para corregir el fallo del job `Audit npm dependencies` de GitHub Actions.
- **Spec relacionada:** No aplica.

## 1. Objetivo o problema

Restaurar el resultado satisfactorio del job `npm security audit` corrigiendo las
versiones vulnerables bloqueadas en `package-lock.json`, sin cambiar las
capacidades, contratos públicos, reglas de negocio ni datos del CMS.

## 2. Contexto y evidencia

**Fuente del comportamiento esperado:**
`docs/architecture/dependency-security.md` exige que los audits se ejecuten
contra el lockfile, que no se ignoren advisories y que se actualice el lockfile
con el gestor de paquetes cuando exista una corrección.

El workflow `.github/workflows/dependency-security.yml` ejecuta `npm audit
--package-lock-only --audit-level=low --ignore-scripts` y falla con código 1.
La ejecución programada de GitHub Actions del 2026-10-09 y la reproducción local
identifican tres vulnerabilidades: `shell-quote@1.9.0`, transitiva de
`concurrently@10.0.5`, y dos rutas hacia `source-map-js@1.2.1`, transitivas de
`@tailwindcss/vite` y `vite`.

El advisory `GHSA-pqg4-j6r4-53mv` corrige `shell-quote` desde la versión 1.11.0;
el advisory `GHSA-68fv-2mgg-jv7q` corrige `source-map-js` desde la versión 1.2.2.
Las restricciones actuales de `package.json` permiten resolver las correcciones:
`concurrently@10.0.6` depende de `shell-quote@1.12.0` y `source-map-js@1.2.2`
es la versión parcheada disponible.

**Pasos de reproducción:**

1. Partir del lockfile actual.
2. Ejecutar `npm audit --package-lock-only --audit-level=low --ignore-scripts`.

**Resultado actual:** el comando informa una vulnerabilidad alta y dos críticas
y termina con código 1, bloqueando el check obligatorio de GitHub.

**Resultado esperado:** el mismo comando termina con código 0 sin advisories que
alcancen el umbral bajo configurado, y la instalación y build conservan su
reproducibilidad.

No cambian las reglas de negocio, el modelo de datos ni los contratos públicos.

## 3. Alcance

- Actualizar deliberadamente `package-lock.json` para bloquear versiones no
  vulnerables de `concurrently`, `shell-quote` y `source-map-js`, respetando las
  restricciones vigentes de `package.json`.
- Revisar los cambios directos y transitivos causados por la resolución.
- Verificar instalación reproducible, compilación de assets y audit npm con los
  mismos comandos de CI.

### Fuera de alcance

- Cambiar `package.json`, scripts npm, el workflow de seguridad, sus umbrales o
  ignorar advisories.
- Actualizar dependencias Composer, GitHub Actions u otras dependencias npm no
  requeridas por la resolución de los advisories identificados.
- Modificar código de aplicación, pruebas de aplicación, contratos, datos o
  documentación de producto.

### Alcance diferido

- Los futuros advisories o actualizaciones de dependencias se tratarán mediante
  un incremento independiente cuando Dependabot o los audits los detecten.

## 4. *Clash check*

- `docs/context/SDD_Inicial.md` y ADR-0001 requieren un frontend compartido
  basado en Vue y Vite; la actualización conserva esa arquitectura y no altera
  sus límites.
- `docs/architecture/dependency-security.md` exige corregir lockfiles con
  advisories, prohíbe ignorarlos y prescribe la instalación, build y audit de
  verificación. Este incremento concreta ese proceso.
- `docs/architecture/continuous-integration.md` define `npm ci --ignore-scripts`
  y `npm run build` como quality gate; se mantienen sin modificación.
- `.github/dependabot.yml` agrupa actualizaciones npm minor y patch; las
  correcciones propuestas son compatibles con esa política.
- No hay Specs vigentes que prescriban las versiones vulnerables ni contratos,
  pruebas o código afectados por este cambio. No se detectan conflictos.

## 5. Requisitos y bloques técnicos aplicables

### Dependencias y compatibilidad

- El lockfile debe resolver `shell-quote` en una versión igual o superior a
  1.11.0 y `source-map-js` en una versión igual o superior a 1.2.2.
- La actualización debe mantenerse dentro de las restricciones existentes de
  `package.json`; no se introducen paquetes nuevos ni cambios de manifest.
- La resolución debe ser instalable con `npm ci --ignore-scripts` bajo Node 24,
  que es el entorno del workflow.
- No cambian las reglas de negocio, el modelo de datos ni los contratos públicos.

### Datos, API, módulos, Vue 3, PageBuilder y asincronía

No aplicable: el incremento solo modifica la resolución reproducible de
dependencias de frontend; no modifica persistencia, rutas, contratos, módulos,
componentes, schemas JSON ni colas.

### Seguridad

- No se aceptan allowlists, excepciones ni advisories ignorados.
- No se ejecutará `npm audit fix` sin revisar su diff; la actualización efectiva
  del lockfile debe limitarse a la remediación descrita.
- No se añadirán secretos ni configuraciones de registro.

## 6. Calidad, seguridad y deuda

### Garantías obligatorias

- `npm audit --package-lock-only --audit-level=low --ignore-scripts` termina sin
  vulnerabilidades que alcancen el umbral configurado.
- `npm ci --ignore-scripts` y `npm run build` terminan correctamente desde la
  resolución bloqueada.
- Se revisan las notas y dependencias de las versiones directas afectadas antes
  de integrar el cambio, conforme a la política de seguridad de dependencias.

### Riesgos aceptados

- No aplica: no se aceptan vulnerabilidades conocidas ni cambios incompatibles
  sin validación.

### Deuda técnica

- No aplica: no se introduce deuda; Dependabot y los audits existentes mantienen
  la detección de advisories futuros.

## 7. Criterios de aceptación

- **CA-01:** El lockfile resuelve `shell-quote` y `source-map-js` en versiones
  corregidas, y el audit npm configurado en GitHub Actions finaliza con código 0.
- **CA-02:** La instalación reproducible de las dependencias bloqueadas y la
  compilación de assets de Vite finalizan correctamente bajo el entorno Node 24
  de CI.
- **CA-03:** El diff no modifica manifests, workflows, código de aplicación,
  contratos públicos ni documentación de producto; los únicos cambios de
  dependencias son los necesarios para corregir los advisories identificados.

## 8. Trazabilidad de pruebas y documentación

| Criterio | Riesgo cubierto | Nivel de prueba | Evidencia esperada | Impacto documental |
| :--- | :--- | :--- | :--- | :--- |
| CA-01 | Persistencia de advisories críticos o altos y bloqueo del merge | Integración de gestor de paquetes | `npm audit --package-lock-only --audit-level=low --ignore-scripts` termina con código 0 | No aplica: la política ya describe el comando y el tratamiento de fallos. |
| CA-02 | Lockfile no reproducible o regresión de compilación | Integración de gestor de paquetes | `npm ci --ignore-scripts` y `npm run build` terminan con código 0 | No aplica: los comandos vigentes de CI no cambian. |
| CA-03 | Cambio transitivo inesperado o ampliación de alcance | Revisión de configuración y lockfile | Diff revisado y CI de GitHub en verde | No aplica: no cambian los procesos documentados. |

## 9. Plan de implementación

1. Actualizar el lockfile de forma controlada para resolver los dos advisories,
   sin modificar las restricciones del manifest.
2. Revisar las versiones directas y transitivas resultantes, sus notas y el diff
   completo frente al alcance de esta Spec.
3. Ejecutar audit npm, instalación reproducible y build; confirmar el job de
   seguridad de GitHub Actions en el Pull Request.

## 10. Decisiones abiertas

- No aplica: las versiones corregidas y los comandos de validación están
  determinados por los advisories y la política de seguridad vigente.
