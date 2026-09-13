# Seguridad de dependencias

## Objetivo y alcance

Este documento describe el control disponible para detectar vulnerabilidades
conocidas en las dependencias bloqueadas de Composer y npm. Está dirigido a
contributors del core y responsables de mantenimiento.

El control combina Dependabot con el workflow
`.github/workflows/dependency-security.yml`. No genera un SBOM, no analiza el
código fuente ni cubre todavía paquetes del sistema operativo, imágenes de
contenedor o servicios externos.

## Fuentes de verdad

- `composer.lock` contiene las versiones directas y transitivas de PHP.
- `package-lock.json` contiene las versiones directas y transitivas de npm.
- `.github/dependabot.yml` programa propuestas de actualización para Composer,
  npm y GitHub Actions.

Los manifests declaran restricciones compatibles, pero los audits se ejecutan
contra los lockfiles para evaluar las versiones reproducibles del proyecto.

## Control automatizado

El workflow ejecuta dos jobs independientes sobre `ubuntu-24.04`:

| Job | Entorno | Comprobaciones |
| :--- | :--- | :--- |
| `Composer security audit` | PHP 8.5, último patch; Composer 2 | Validez de `composer.json` y `composer.lock`, advisories de todas las severidades y paquetes abandonados. |
| `npm security audit` | Node 24 LTS, último patch; npm incluido | Advisories de todas las severidades presentes en `package-lock.json`. |

Se ejecutan en todos los Pull Requests, en cada push a `main`, diariamente a las
05:23 UTC y bajo demanda mediante `workflow_dispatch`. No hay filtros de rutas,
por lo que ambos checks siempre estarán disponibles si posteriormente se
declaran obligatorios mediante las reglas de la rama.

El workflow solo necesita permiso de lectura del contenido, no usa secretos y
no instala las dependencias del proyecto. Las Actions están fijadas por commit
SHA y Dependabot revisa sus actualizaciones semanalmente.

## Ejecución local

Desde la raíz del repositorio, ejecutar:

```shell
composer validate --strict --no-check-publish --no-plugins --no-scripts
composer audit --locked --abandoned=fail --no-plugins --no-scripts
npm audit --package-lock-only --audit-level=low --ignore-scripts
```

Los comandos necesitan acceso a los registros y servicios de advisories de
Composer y npm. Un error de red, autenticación o disponibilidad produce un fallo
y no debe tratarse como una auditoría superada.

## Tratamiento de fallos

1. Identificar el paquete, la ruta transitiva, el advisory y la versión
   corregida indicada por Composer, npm o Dependabot.
2. Aplicar la actualización mediante un Pull Request de Dependabot o actualizar
   deliberadamente el lockfile con el gestor correspondiente.
3. Revisar cambios directos y transitivos y ejecutar las pruebas afectadas antes
   de integrar la actualización.
4. Repetir los tres comandos locales y comprobar ambos jobs en GitHub.

El workflow nunca ejecuta `npm audit fix`, `composer update` ni modifica los
lockfiles. No hay advisories ignorados. Si una corrección no estuviera disponible
y fuera imprescindible aceptar temporalmente el riesgo, la excepción deberá
aprobarse de forma explícita, documentar alcance, motivo, responsable y caducidad
y limitarse al advisory concreto.

## Controles pendientes

La generación automatizada y conservación del SBOM sigue pendiente. También
queda pendiente convertir los checks en obligatorios, incorporar dependency
review y definir la protección de Pull Requests. Estos incrementos se abordarán
por separado y no deben presentarse como activos.

### Validación futura de workflows

`actionlint` se evaluará al construir el quality gate general o cuando el
repositorio incorpore más workflows. Hasta alcanzar uno de esos hitos, el coste
de añadir y mantener otra herramienta no aporta una ventaja neta frente a la
validación estructural y la ejecución real en GitHub.

Si se adopta, se ejecutará en CI con una versión fijada y un mecanismo
reproducible. No se exigirá una instalación global en los equipos de desarrollo;
la validación local deberá reutilizar el mismo comando versionado que CI. La
comprobación estática de `actionlint` será complementaria y no sustituirá la
ejecución del workflow en GitHub Actions.
