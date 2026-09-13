# Seguridad de dependencias

## Objetivo y alcance

Este documento describe el control disponible para detectar vulnerabilidades
conocidas en las dependencias bloqueadas de Composer y npm. Está dirigido a
contributors del core y responsables de mantenimiento.

El control combina Dependabot con los workflows
`.github/workflows/dependency-security.yml` y `.github/workflows/sbom.yml`.
Audita las versiones bloqueadas y conserva inventarios SPDX de las dependencias
que GitHub reconoce. No analiza el código fuente ni cubre todavía paquetes del
sistema operativo, imágenes de contenedor o servicios externos.

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

## Revisión de Pull Requests de dependencias

Dependabot propone versiones y los audits detectan vulnerabilidades conocidas,
pero ninguna de esas señales garantiza compatibilidad con el CMS. Esta checklist
es obligatoria para agentes y mantenedores antes de recomendar la integración de
una actualización, incluso si todos los checks disponibles están en verde.

1. Identificar el ecosistema, los paquetes directos y transitivos modificados y
   si el cambio es `major`, `minor` o `patch`. Una versión `minor` o `patch`
   también puede introducir regresiones; SemVer reduce riesgo, no lo elimina.
2. Revisar el diff del manifest y del lockfile, las release notes y el changelog
   de cada dependencia directa. Comprobar requisitos de PHP, Node, Laravel y
   otras dependencias, cambios de configuración, deprecaciones, migraciones y
   problemas conocidos aplicables.
3. Para un grupo de actualizaciones, comprobar el conjunto completo. Si aparece
   un fallo y no puede atribuirse con claridad, separar o recrear la propuesta
   antes de integrar para aislar la dependencia responsable.
4. Ejecutar las comprobaciones aplicables desde un estado reproducible:

   ```shell
   composer validate --strict --no-check-publish --no-plugins --no-scripts
   composer install --no-interaction --prefer-dist
   composer test
   composer audit --locked --abandoned=fail --no-plugins --no-scripts
   npm ci --ignore-scripts
   npm run build
   npm audit --package-lock-only --audit-level=low --ignore-scripts
   ```

   Las pruebas enfocadas del módulo afectado deben ejecutarse antes de la suite
   afectada cuando existan. No es necesario ejecutar Composer para un cambio
   exclusivo de npm ni npm para uno exclusivo de Composer, salvo que el impacto
   sea transversal o el quality gate vigente los exija.
5. En actualizaciones de GitHub Actions, comprobar en el repositorio oficial que
   el SHA fijado corresponde al tag indicado, revisar cambios incompatibles de
   inputs, outputs y runtime, verificar que los permisos siguen siendo mínimos y
   confirmar una ejecución real de los workflows afectados.
6. Examinar el resultado de CI y distinguir un fallo del cambio de uno de red,
   registro o infraestructura. Un fallo externo se reintenta y se documenta; no
   se interpreta como check superado.
7. Resumir el riesgo revisado, los comandos exactos y sus resultados, y cualquier
   comprobación omitida con su limitación. No recomendar la fusión mientras una
   comprobación aplicable falle o la compatibilidad relevante siga sin validar.

El indicador de compatibilidad de Dependabot es evidencia auxiliar basada en
otras ejecuciones conocidas, no una prueba del repositorio. El SBOM es un
inventario y tampoco valida compatibilidad. Mientras no exista un quality gate
general con instalación, tests y build obligatorios, estas comprobaciones deben
realizarse manualmente y verificarse antes de cada fusión.

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

## Software Bill of Materials

El workflow `Software bill of materials` solicita a la API de GitHub una
instantánea del Dependency Graph en formato SPDX 2.3. Usa el flujo asíncrono de
generación y descarga, valida que el inventario no esté vacío y comprueba que
contenga dependencias identificadas mediante Package URL (PURL) de Composer y
npm. Un resultado incompleto, un error de la API o una espera superior a cinco
minutos hacen fallar el job.

Se ejecuta después de cambios en `composer.lock`, `package-lock.json` o el propio
workflow que lleguen a `main`, cada martes a las 05:47 UTC y bajo demanda. Cada
ejecución correcta publica `kitsune-cms-sbom.spdx.json` como artifact de GitHub
Actions durante 90 días. El artifact se identifica con el `run_id`; la ejecución
asociada conserva el commit y el evento que originaron la captura.

Para obtenerlo, abrir la ejecución correspondiente del workflow, localizar la
sección **Artifacts** y descargar `kitsune-cms-sbom-<run_id>`. El JSON extraído
tiene el documento SPDX en la raíz y puede procesarse con herramientas
compatibles con SPDX 2.3.

El workflow solo concede `contents: read` al `GITHUB_TOKEN`, no realiza checkout
ni instala dependencias. La URL de descarga devuelta por GitHub se acepta
únicamente si pertenece al endpoint esperado del repositorio. La Action oficial
que conserva el artifact está fijada por SHA y sus actualizaciones quedan bajo
Dependabot.

El SBOM representa el estado procesado por el Dependency Graph, no una
atestación criptográfica del commit ni un análisis del contenido construido.
GitHub puede tardar brevemente en reflejar un lockfile recién integrado. La
ejecución semanal permite regenerar la captura después de ese procesamiento;
para releases futuras deberá evaluarse si además se necesita un SBOM ligado al
artefacto de distribución y firmado o atestado.

## Controles pendientes

Queda pendiente convertir los checks en obligatorios, incorporar dependency
review y definir la protección de Pull Requests. Estos incrementos se abordarán
por separado y no deben presentarse como activos. La atestación de un SBOM de
release también queda fuera del alcance actual.

### Validación futura de workflows

`actionlint` se ha reevaluado al incorporar el workflow de SBOM. Con dos
workflows acotados, el coste de añadir y mantener otra herramienta todavía no
aporta una ventaja neta frente a la validación estructural y la ejecución real
en GitHub; se evaluará de nuevo al construir el quality gate general o si sigue
creciendo la automatización.

Si se adopta, se ejecutará en CI con una versión fijada y un mecanismo
reproducible. No se exigirá una instalación global en los equipos de desarrollo;
la validación local deberá reutilizar el mismo comando versionado que CI. La
comprobación estática de `actionlint` será complementaria y no sustituirá la
ejecución del workflow en GitHub Actions.
