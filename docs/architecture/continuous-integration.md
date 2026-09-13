# Integración continua

## Objetivo y alcance

Este documento describe el quality gate que se ejecuta en GitHub Actions sobre
cada Pull Request y cada push a `main`. Está dirigido a contributors del core y
responsables de mantenimiento.

El workflow `.github/workflows/ci.yml` comprueba el formato y las pruebas del
backend, la compilación de los assets frontend y la validez de los propios
workflows. Los controles de seguridad de dependencias se documentan en
[Seguridad de dependencias](dependency-security.md) y se ejecutan en workflows
independientes.

## Jobs

Todos los jobs usan `ubuntu-24.04`, fijan las Actions por commit SHA, declaran
`permissions: contents: read` y no utilizan secretos.

| Job | Entorno | Comprobaciones |
| :--- | :--- | :--- |
| `PHP quality` | PHP 8.5, último patch; Composer 2 | `composer install` reproducible, formato con Pint y suite de Pest. |
| `Frontend build` | Node 24 LTS, último patch; npm incluido | `npm ci` y compilación de assets con Vite. |
| `Workflow lint` | `actionlint` 1.7.12 | Validez de los workflows de `.github/workflows/`. |

Se ejecutan en todos los Pull Requests, en cada push a `main` y bajo demanda
mediante `workflow_dispatch`. No hay filtros de rutas, por lo que los tres checks
siempre están disponibles si posteriormente se declaran obligatorios.

## Ejecución local

Desde la raíz del repositorio, tras instalar las dependencias:

```shell
composer lint
composer test
npm ci --ignore-scripts
npm run build
```

`composer lint` ejecuta Pint en modo comprobación y no modifica archivos. Para
aplicar el formato, usar `vendor/bin/pint`.

## Validación de workflows

`actionlint` se descarga en CI desde el release oficial con la versión fijada
`1.7.12` y se verifica su integridad con el SHA-256
`8aca8db96f1b94770f1b0d72b6dddcb1ebb8123cb3712530b08cc387b349a3d8` antes de
ejecutarlo. No se instala globalmente ni se depende de una Action de terceros.

Para reproducirlo en Linux o macOS:

```shell
curl --fail --silent --show-error --location \
  --output actionlint.tar.gz \
  https://github.com/rhysd/actionlint/releases/download/v1.7.12/actionlint_1.7.12_linux_amd64.tar.gz
echo "8aca8db96f1b94770f1b0d72b6dddcb1ebb8123cb3712530b08cc387b349a3d8  actionlint.tar.gz" | sha256sum --check --strict
tar --extract --gzip --file actionlint.tar.gz actionlint
./actionlint -color
```

En Windows, descargar `actionlint_1.7.12_windows_amd64.zip` de la misma release,
verificarlo contra `actionlint_1.7.12_checksums.txt` y ejecutar el binario
extraído. La versión y el checksum deben coincidir con los de CI.

`actionlint` analiza además los bloques `run` con `shellcheck` cuando lo
encuentra en el `PATH`. Los runners de Ubuntu lo incluyen, por lo que instalar
`shellcheck` en local (por ejemplo, `winget install koalaman.shellcheck`)
aproxima la validación local a la de CI. Si no está disponible, esa parte de la
comprobación se omite sin producir errores.

La comprobación estática es complementaria: no sustituye la ejecución real del
workflow en GitHub Actions.

## Checks obligatorios

El ruleset `Protect main` exige Pull Request y los checks `Composer security
audit`, `npm security audit` y `Dependency review`. Los checks `PHP quality`,
`Frontend build` y `Workflow lint` deben añadirse como obligatorios una vez que
el workflow se haya publicado y sus ejecuciones sean estables.

## Fuera de alcance actual

- Análisis estático del código (PHPStan o Larastan).
- Umbral de cobertura de pruebas; no se impone sin una línea base acordada.
- Caché de dependencias de Composer en CI.
- Despliegue o verificación de entornos remotos.

## Fuentes de verdad

- `.github/workflows/ci.yml`: definición ejecutable del quality gate.
- `composer.json`: scripts `lint` y `test`.
- `package.json`: scripts `build` y `dev`.
- `phpunit.xml`: configuración de las pruebas.
