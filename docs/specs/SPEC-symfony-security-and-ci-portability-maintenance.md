# SPEC: Mantenimiento de seguridad Symfony y portabilidad de CI

- **Estado:** Completada
- **Perfil:** maintenance
- **Origen de la planificación:** Peticion `/build` para corregir los advisories
  de Composer y el fallo de CI observados en el Pull Request #50, ejecutando el
  mantenimiento en la rama `module/pages-backoffice-ux`.
- **Spec relacionada:** [SPEC-pages-backoffice-ux](SPEC-pages-backoffice-ux.md), en revisión.

## 1. Objetivo o problema

Restaurar los checks obligatorios de seguridad y calidad del Pull Request #50:

- corregir las versiones vulnerables de seis componentes Symfony bloqueadas en
  `composer.lock`, sin ampliar dependencias ni cambiar contratos del CMS; y
- hacer independiente de la representacion localizada del mensaje la prueba de
  regresion que verifica el manejo de un `parent_id` no escalar en la creacion
  de Pages.

La política `docs/architecture/dependency-security.md` prohíbe ignorar
advisories y exige actualizar y validar el lockfile.
`SPEC-pages-backoffice-ux.md` exige que las entradas manipuladas produzcan
errores controlados sin persistencia parcial ni respuestas `500`.

## 2. Contexto y evidencia

**Fuente del comportamiento esperado:**
`docs/architecture/dependency-security.md` y
`docs/specs/SPEC-pages-backoffice-ux.md`.

El job `Composer security audit` del Pull Request #50 ejecuta:

```shell
composer audit --locked --abandoned=fail --no-plugins --no-scripts
```

El comando finaliza con codigo `1` e informa once advisories publicados entre el
28 de octubre y el 4 de noviembre de 2026 sobre estas versiones bloqueadas:

| Paquete | Version actual | Version objetivo | Advisories detectados |
| :--- | :--- | :--- | :--- |
| `symfony/html-sanitizer` | `8.1.0` | `8.1.11` | `PKSA-wwnc-rwy3-v11m`, `PKSA-4z5q-y2w8-bwqq`, `PKSA-n87g-t5cn-m1yq` |
| `symfony/http-foundation` | `8.1.9` | `8.1.11` | `PKSA-24ph-xkrh-y79j`, `PKSA-pr6q-2w5s-kzzf`, `PKSA-nxjs-s6hn-vb6m`, `PKSA-b2gg-vk1g-6d5q` |
| `symfony/http-kernel` | `8.1.6` | `8.1.11` | `PKSA-3gxv-7xhq-mgmz` |
| `symfony/mime` | `8.1.6` | `8.1.10` | `PKSA-wtxr-p26d-nn42` |
| `symfony/routing` | `8.1.6` | `8.1.11` | `PKSA-y8c5-1b7f-r1gj` |
| `symfony/yaml` | `8.1.6` | `8.1.11` | `PKSA-nktx-f1n1-b4yd` |

`symfony/html-sanitizer` es una dependencia directa usada para contenido
`rich-text`; las otras cinco son dependencias transitivas del framework y del
tooling. Las restricciones actuales de `composer.json` permiten las versiones
corregidas. El dry-run reproducible:

```shell
composer update symfony/html-sanitizer:8.1.11 \
  symfony/http-foundation:8.1.11 symfony/http-kernel:8.1.11 \
  symfony/mime:8.1.10 symfony/routing:8.1.11 symfony/yaml:8.1.11 \
  --with-dependencies --minimal-changes --dry-run --no-scripts --no-plugins
```

resuelve exactamente seis actualizaciones, sin instalaciones, eliminaciones,
cambios de `composer.json` ni actualizaciones transitivas adicionales. Las notas
oficiales de Symfony `8.1.10` y `8.1.11` identifican estas versiones como releases
de seguridad e incluyen los fixes correspondientes.

Los jobs `PHP quality` y `Database integration` para SQLite, MySQL y MariaDB
fallan ademas en la misma prueba HTTP. La prueba envia `parent_id` como array y
el comportamiento corregido devuelve el error de validacion sin persistir Page
ni traduccion. Sin embargo, la asercion exige el literal tecnico
`validation.integer`. Una instalacion limpia de CI presenta en su lugar el
mensaje ingles resuelto por el traductor de validacion de Laravel. Los UI
catalogs y `CMS_UI_CATALOG_FALLBACK_MODE` no gobiernan esos mensajes. La causa de
que el entorno local mostrase la clave no necesita alterarse para corregir esta
desviacion: ninguna de las dos representaciones forma parte del contrato que la
prueba debe verificar.

**Pasos de reproducción:**

1. Partir del lockfile actual y ejecutar el
audit Composer anterior. Resultado actual: once advisories y codigo `1`.
Resultado esperado: ningun advisory ni paquete abandonado y codigo `0`.
2. Ejecutar `composer test` o la matriz de integracion desde la instalacion
limpia de GitHub Actions. Resultado actual: la respuesta es correcta, pero la
prueba falla porque no contiene `validation.integer`. Resultado esperado: la
prueba verifica el error de sesion, el renderizado correcto y la ausencia de
persistencia sin depender del texto resuelto.

No cambian las reglas de negocio, el modelo de datos, los contratos públicos ni
el comportamiento esperado del CMS.

## 3. Alcance

- Actualizar deliberadamente `composer.lock` solo para bloquear las seis
  versiones Symfony corregidas identificadas en esta Spec.
- Mantener sin cambios las restricciones de `composer.json` y revisar el diff
  completo del lockfile para excluir resoluciones no requeridas.
- Revisar las notas oficiales de las releases de seguridad y su compatibilidad
  con Laravel 13, PHP 8.5 y los usos del CMS.
- Ajustar la prueba HTTP de `parent_id` no escalar para comprobar el error de
  validacion mediante el contrato de sesion, el renderizado posterior y la
  ausencia de persistencia, sin exigir una clave o traduccion concreta.
- Verificar el test localmente y en el entorno limpio de CI sin fijar una
  representacion concreta del mensaje de validacion.
- Ejecutar instalacion reproducible, pruebas enfocadas, suite completa, matriz
  SQLite/MySQL/MariaDB, formato, build y audits configurados.
- Registrar la remediacion de seguridad en `CHANGELOG.md` una vez completada.

### Fuera de alcance

- Cambiar `composer.json`, restricciones de versiones o dependencias directas.
- Actualizar paquetes distintos de los seis componentes Symfony identificados.
- Ignorar, suprimir, aceptar temporalmente o reducir el umbral de advisories.
- Modificar los workflows, sus permisos, sus comandos o la proteccion de rama.
- Cambiar el comportamiento del UI catalog, sus modos de fallback, las reglas de
  validacion, los mensajes visibles o el flujo HTTP de creacion de Pages.
- Introducir nuevas pruebas funcionales ajenas a las regresiones cubiertas.
- Actualizar dependencias npm o GitHub Actions.

### Alcance diferido

- Los advisories o actualizaciones de dependencias que aparezcan despues de la
  resolucion se trataran en un incremento independiente cuando el audit o
  Dependabot los detecten.

## 4. *Clash check*

- **SDD inicial:** conserva Laravel, PHP 8.x, Eloquent, Blade y el monolito
  modular. Los patches no cambian el stack ni sus fronteras. No hay conflicto.
- **SPEC-pages-backoffice-ux:** exige validacion controlada de entradas, ausencia
  de persistencia parcial, sanitizacion del contenido y pruebas multi-motor. El
  mantenimiento restaura sus checks sin cambiar la aceptacion. No hay conflicto.
- **SPEC-pages-backoffice:** prescribe sanitizacion obligatoria en servidor para
  `rich-text`. Actualizar `symfony/html-sanitizer` corrige bypasses conocidos sin
  cambiar el contrato permitido por el CMS. No hay conflicto.
- **ADR-0001:** mantiene Blade como base y carga frontend bajo demanda. No se
  modifican assets ni decisiones de renderizado. No hay conflicto.
- **ADR-0003:** exige SQLite, MySQL 8.4 y MariaDB 11.4. La matriz sigue siendo
  obligatoria y no se cambia la baseline. No hay conflicto.
- **Politica de seguridad de dependencias:** exige corregir lockfiles, revisar
  cambios directos y transitivos, instalar reproduciblemente y no recomendar
  fusion con audits o checks aplicables fallidos. Esta Spec aplica esa politica.
- **Codigo y pruebas:** el sanitizador, transporte seguro, proxies, rutas y
  renderizado dependen de los componentes afectados; las restricciones permiten
  los patches. La prueba HTTP fallida ya demuestra comportamiento correcto y su
  unica desviacion es la asercion textual dependiente del entorno. No hay
  conflicto irresoluble.

## 5. Requisitos y bloques técnicos aplicables

### Dependencias y compatibilidad

- La resolucion final debe contener exactamente:
  `symfony/html-sanitizer@8.1.11`,
  `symfony/http-foundation@8.1.11`,
  `symfony/http-kernel@8.1.11`, `symfony/mime@8.1.10`,
  `symfony/routing@8.1.11` y `symfony/yaml@8.1.11`.
- `composer.json` debe permanecer sin cambios y el lockfile no debe incorporar
  otros updates, installs o removals.
- La instalacion desde `composer.lock` debe ser reproducible con PHP 8.5 y
  Composer 2, las versiones usadas por CI, sin ignorar requisitos de plataforma.
- Deben revisarse los cambios de seguridad y compatibilidad de Symfony 8.1.10 y
  8.1.11, especialmente sanitizacion de URL y media, request/base URL, proxies,
  routing, subrequests, MIME y parseo YAML.
- Laravel debe permanecer en la version bloqueada; este mantenimiento no actualiza
  el framework ni cambia su configuracion.

### Regresion HTTP y mensajes de validacion

- La prueba de `parent_id` no escalar debe verificar el redirect y el error de
  validacion de `parent_id` en la sesion antes de seguirlo.
- La pantalla de creacion debe renderizarse con codigo `200` despues del error y
  no debe crear Page ni PageTranslation.
- La prueba no debe depender del literal `validation.integer` ni de una
  traduccion inglesa concreta; debe comprobar directamente el contrato HTTP y de
  persistencia.
- No se cambia codigo de aplicacion para adaptar la prueba: el comportamiento
  ejecutable ya satisface el contrato vigente.

### Datos, migraciones, API, modulos y contratos publicos

No aplicable: no se crean ni modifican entidades, tablas, columnas, indices,
migraciones, backfills, rutas, requests, responses, autorizacion, firmas de
servicios, eventos ni contratos intermodulares.
No cambian las reglas, el modelo de datos ni los contratos públicos.

### Frontend, Vue 3, PageBuilder y JSON

No aplicable: no se modifican componentes, CSS, JavaScript, assets, Vue,
PageBuilder, schemas JSON ni renderizado Blade. El build frontend se ejecuta
solo como quality gate transversal del Pull Request.

### Asincronia e integraciones externas

No aplicable: no se introducen colas, reintentos de negocio, servicios externos,
credenciales ni nuevas integraciones. Composer y los servicios de advisories se
usan unicamente como tooling de entrega; un fallo de red no cuenta como audit
superado.

### Seguridad

- No se aceptan allowlists, excepciones ni advisories ignorados.
- No se ejecuta `composer update` global: la resolucion debe usar la lista cerrada
  de paquetes con constraints temporales exactas y
  `--with-dependencies --minimal-changes`.
- No se anaden secretos, tokens, mirrors ni configuracion de repositorios.
- Debe ejecutarse `security-audit` sobre el diff final por afectar sanitizacion,
  request, proxies, routing y otras fronteras HTTP.
- La actualizacion no autoriza a relajar sanitizacion, validacion, HTTPS, proxies,
  CSRF, autenticacion ni autorizacion para resolver incompatibilidades.

## 6. Calidad, seguridad y deuda

### Garantias obligatorias

- TDD no aporta ventaja neta en este mantenimiento: no se introduce
  comportamiento ejecutable nuevo y la regresion ya falla en CI por una asercion
  inadecuada. Se exige conservar esa reproduccion como evidencia, ajustar la
  prueba al contrato vigente y validar los patches con las suites existentes.
- `composer validate --strict --no-check-publish --no-plugins --no-scripts` y el
  audit Composer configurado terminan con codigo `0`.
- `composer install --no-interaction --prefer-dist` instala exactamente el
  lockfile resultante y ejecuta correctamente el descubrimiento de paquetes.
- Las pruebas enfocadas cubren sanitizacion de contenido, transporte/proxies,
  rutas y la regresion `parent_id`; despues pasan `composer test` y la matriz de
  integracion en SQLite, MySQL 8.4 y MariaDB 11.4.
- `vendor/bin/pint --test`, `npm ci --ignore-scripts`, `npm run test:js`,
  `npm run build` y el audit npm configurado terminan correctamente.
- El diff se revisa para excluir dependencias no autorizadas, generados
  accidentales, secretos y cambios ajenos.
- Los checks aplicables del Pull Request #50 deben ejecutarse de nuevo y quedar
  en verde antes de recomendar la fusion.

### Riesgos aceptados

No aplica: no se aceptan advisories conocidos, incompatibilidades, checks
omitidos ni cambios transitivos adicionales sin una nueva revision del alcance.

### Deuda tecnica

No aplica: los audits diarios y Dependabot mantienen la deteccion posterior; el
mantenimiento no introduce una excepcion temporal ni una version local.

## 7. Criterios de aceptación

- **CA-01:** `composer.lock` bloquea exclusivamente las seis versiones Symfony
  corregidas definidas en esta Spec, `composer.json` permanece sin cambios y el
  audit Composer termina con codigo `0`, sin advisories ni paquetes abandonados.
- **CA-02:** La instalacion reproducible, las pruebas enfocadas de sanitizacion y
  HTTP, la suite completa y la matriz SQLite/MySQL/MariaDB pasan con el lockfile
  corregido, sin relajar garantias de seguridad ni contratos publicos.
- **CA-03:** La regresion `parent_id` no escalar verifica el error de sesion y el
  renderizado correcto, y demuestra que no se persisten Page ni PageTranslation
  sin depender de la clave o del texto traducido.
- **CA-04:** Formato PHP, pruebas JavaScript, build, audit npm y revision
  independiente de seguridad pasan; el diff final contiene solo el lockfile, la
  prueba de regresion y los metadatos/documentacion de entrega autorizados.
- **CA-05:** Los checks aplicables del Pull Request #50 se reejecutan con el
  commit corregido y quedan en verde antes de recomendar su fusion.

## 8. Trazabilidad de pruebas y documentación

| Criterio | Riesgo cubierto | Nivel de prueba | Evidencia esperada | Impacto documental |
| :--- | :--- | :--- | :--- | :--- |
| CA-01 | Vulnerabilidades conocidas o resolucion ampliada | Integracion de gestor de paquetes | Versiones exactas, diff del lockfile, `composer validate` y audit con codigo `0` | `CHANGELOG.md`, seccion `Seguridad`; la politica vigente no cambia. |
| CA-02 | Regresion de sanitizacion, request, proxies, routing o persistencia | Integracion, HTTP y matriz multi-motor | Pruebas enfocadas, `composer test` y SQLite/MySQL/MariaDB en verde | No aplica: se restaura el comportamiento ya documentado. |
| CA-03 | Prueba dependiente del entorno o error `500` oculto | HTTP local y en CI | Error de sesion, GET `200` y ausencia de Page/traduccion | No aplica: la documentacion ya exige un error controlado. |
| CA-04 | Regresion transversal o cambio ajeno | Formato, JavaScript, build, audits y auditoria independiente | Todos los gates aplicables y diff review pasan | No aplica fuera del changelog: no cambian configuracion ni uso. |
| CA-05 | Fusion con evidencia remota obsoleta o checks fallidos | CI/E2E del Pull Request | Jobs de seguridad, calidad y matriz reejecutados sobre el commit corregido | No aplica: el proceso ya esta documentado. |

## 9. Plan de implementación

1. Corregir la asercion HTTP para verificar el contrato de sesion, renderizado y
   persistencia sin depender de la representacion localizada del mensaje.
2. Actualizar de forma minima los seis paquetes autorizados, revisar notas,
   versiones, metadatos y diff del lockfile, e instalar la resolucion bloqueada.
3. Ejecutar pruebas enfocadas, suite completa, matriz multi-motor, formato,
   JavaScript, build y ambos audits; realizar la auditoria independiente de
   seguridad y corregir cualquier hallazgo confirmado.
4. Actualizar `CHANGELOG.md`, revisar el diff final y reejecutar los checks del
   Pull Request #50 antes de recomendar la fusion.

## 10. Decisiones abiertas

No aplica: los paquetes, versiones corregidas, desviacion de la prueba, alcance y
quality gates estan determinados por los advisories, el dry-run, los logs del
Pull Request y las politicas vigentes. La aprobacion de esta Spec sigue siendo
necesaria antes de implementar.
