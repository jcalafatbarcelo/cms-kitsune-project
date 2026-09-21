# CMS Templates

## Estado

La fundación de CMS Templates está disponible para paquetes desplegados
localmente. El CMS distribuye `Templates/Base`; la descarga, subida e instalación
desde el producto no están soportadas.

## Paquete

Un paquete reside en `Templates/<Nombre>` e incluye `template.json` y un Blade
por cada presentación declarada. Por ejemplo:

```text
Templates/Acme/
├── template.json
└── Resources/views/public/page/standard.blade.php
```

```json
{
  "schema_version": 1,
  "identifier": "acme",
  "name": "Acme",
  "presentations": ["public.page.standard"]
}
```

Los nombres de directorio son ASCII alfanuméricos y no pueden diferir solo por
mayúsculas/minúsculas. El identificador y directorio quedan inmutables tras la
sincronización.

## Operación

Después de desplegar un paquete compatible:

```shell
php artisan cms:template:sync
php artisan cms:template:list
php artisan cms:template:activate acme
php artisan cms:template:set-default acme
```

Base no se puede desactivar. Un template predeterminado tampoco puede
desactivarse. Los comandos revalidan el manifiesto y sus Blades antes de activar
o seleccionar un template.

## Validación multi-motor

La fundación debe verificarse también contra MySQL 8.4 y MariaDB 11.4, además de
SQLite. Para iniciar MySQL localmente sin ocupar el puerto interno del motor,
usa el puerto host `3306`:

```powershell
docker run --detach --name kitsune-mysql --publish 3306:3306 `
  --env MYSQL_DATABASE=kitsune_test `
  --env MYSQL_USER=kitsune `
  --env MYSQL_PASSWORD=kitsune_test_password `
  --env MYSQL_ROOT_PASSWORD=kitsune_root_test_password `
  mysql:8.4.11 --log-bin-trust-function-creators=1
```

Espera a que el servicio responda y ejecuta las pruebas con el mismo puerto:

```powershell
docker exec kitsune-mysql mysqladmin ping --host=127.0.0.1 --user=kitsune --password=kitsune_test_password --silent

$env:APP_ENV='testing'
$env:CMS_UI_CATALOG_FALLBACK_MODE='key'
$env:DB_CONNECTION='mysql'
$env:DB_DATABASE='kitsune_test'
$env:DB_HOST='127.0.0.1'
$env:DB_PORT='3306'
$env:DB_USERNAME='kitsune'
$env:DB_PASSWORD='kitsune_test_password'
php artisan config:clear
php artisan test tests/Feature/Core/LanguageInstallationTest.php tests/Feature/Core/TemplateFoundationTest.php
```

Al terminar, elimina el contenedor con `docker rm --force kitsune-mysql`. La
matriz de GitHub Actions ejecuta el mismo conjunto contra SQLite, MySQL y
MariaDB.
