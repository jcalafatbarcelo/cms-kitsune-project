# CMS Templates

## Estado

La fundación de CMS Templates está en `Revisión` para paquetes desplegados
localmente y no se considera completada hasta que pasen todos sus criterios de
aceptación. El CMS distribuye `Templates/Base`; la descarga, subida e instalación
desde el producto no están soportadas.

## Paquete

Un paquete reside en `Templates/<Nombre>` e incluye `template.json` y un Blade
por cada presentación declarada. Las claves públicas disponibles actualmente
son `public.page.standard` y `public.navigation.menu`; no se pueden seleccionar
desde HTTP ni desde contenido editorial. Por ejemplo:

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
  "presentations": ["public.page.standard", "public.navigation.menu"]
}
```

Los nombres de directorio son ASCII alfanuméricos y no pueden diferir solo por
mayúsculas/minúsculas. El identificador y directorio quedan inmutables tras la
sincronización.

Cada paquete validado expone sus vistas privadas mediante el namespace Blade
estable `cms-template-<identifier>::`. Por ejemplo, el paquete `acme` puede usar
`@include('cms-template-acme::private.header')`, extender un layout o usar el
componente anónimo `<x-cms-template-acme::private.header />` desde una de sus
presentaciones.
El namespace se registra solo después de validar el manifiesto, su hash y las
rutas del paquete; no se puede elegir desde HTTP ni desde contenido editorial.

## Resolución de presentaciones

En cada renderizado, el CMS inspecciona el manifiesto del template efectivo y
comprueba que su hash coincide con el registro sincronizado. Una clave declarada
usa el Blade del template efectivo; si un template Custom omite una de las dos
claves, el CMS usa la misma clave de `Base`. Un manifiesto, hash o Blade no
válido, o una clave que tampoco proporcione `Base`, produce una respuesta `503`
sin exponer rutas internas.

Los Blades reciben `effectiveTemplate` y `presentationTemplate`. El primero
define los textos UI y el segundo aporta el Blade. Un Custom puede incluir
`Resources/lang` para modificar textos de una presentación heredada, sin
declarar el Blade ni siquiera distribuir un catálogo completo. La búsqueda de
texto consulta el catálogo del template efectivo antes que el del template que
aporta la presentación, respetando `CMS_UI_CATALOG_FALLBACK_MODE`.

La etiqueta accesible del menú Base usa la clave
`navigation.menu.label` y sigue esa misma precedencia de catálogos. Un template
Custom puede cambiarla aunque herede el Blade de navegación de Base.

Para colocar navegación en un Blade de presentación, use el componente de Core:

```blade
<x-cms-navigation identifier="main-menu" locale="{{ $locale }}" :effective-template="$effectiveTemplate" />
```

El componente obtiene un árbol ya localizado y filtrado desde Navigation y lo
entrega al Blade `public.navigation.menu`; no acepta una clave, ruta o template
procedente de la petición.

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
SQLite. Para iniciar MySQL localmente, publícalo solo en loopback mediante el
puerto host `3306`. Introduce credenciales de prueba locales fuera del
historial de la shell; no reutilices una contraseña real:

```powershell
$env:MYSQL_PASSWORD = Read-Host 'Contraseña para el usuario de prueba kitsune'
$env:MYSQL_ROOT_PASSWORD = Read-Host 'Contraseña para root de MySQL'
$env:MYSQL_PWD = $env:MYSQL_PASSWORD

docker run --detach --name kitsune-mysql --publish 127.0.0.1:3306:3306 `
  --env MYSQL_DATABASE=kitsune_test `
  --env MYSQL_USER=kitsune `
  --env MYSQL_PASSWORD `
  --env MYSQL_ROOT_PASSWORD `
  mysql:8.4.11 --log-bin-trust-function-creators=1
```

Espera a que el servicio responda y ejecuta las pruebas con el mismo puerto:

```powershell
docker exec --env MYSQL_PWD kitsune-mysql mysqladmin ping --host=127.0.0.1 --user=kitsune --silent

$env:APP_ENV='testing'
$env:CMS_UI_CATALOG_FALLBACK_MODE='key'
$env:DB_CONNECTION='mysql'
$env:DB_DATABASE='kitsune_test'
$env:DB_HOST='127.0.0.1'
$env:DB_PORT='3306'
$env:DB_USERNAME='kitsune'
$env:DB_PASSWORD=$env:MYSQL_PASSWORD
php artisan config:clear
php artisan test tests/Feature/Core/LanguageInstallationTest.php tests/Feature/Core/TemplateFoundationTest.php
```

Al terminar, elimina el contenedor con `docker rm --force kitsune-mysql`. La
matriz de GitHub Actions ejecuta el mismo conjunto contra SQLite, MySQL y
MariaDB.
