# Instalación

## Requisitos

CMS Kitsune requiere PHP 8.4 o posterior, Composer 2, Node.js 24 LTS y npm. La
persistencia soportada es:

| Entorno | Motor soportado |
| :--- | :--- |
| Desarrollo y pruebas | SQLite 3.45.0 o posterior |
| Producción | MySQL 8.4 LTS o MariaDB 11.4 LTS, con un patch mantenido |

SQLite no está soportado como persistencia de producción. Las versiones legacy
y las ramas Innovation o rolling quedan fuera del contrato definido por el
[ADR-0003](../adr/ADR-0003-baseline-moderna-de-bases-de-datos.md).

PHP debe disponer de `pdo_sqlite` para SQLite o `pdo_mysql` para MySQL y MariaDB.

## Instalación de desarrollo

Desde la raíz del repositorio:

```shell
composer run setup
composer run dev
```

El setup instala dependencias, crea `.env` desde la plantilla si no existe,
genera `APP_KEY`, ejecuta las migraciones y compila los assets. La migración de
Core crea `languages` y el singleton `language_settings`, instala `en` como
idioma activo y lo asigna como base y predeterminado de frontend y backoffice.
Esos son dos predeterminados obligatorios, uno por contexto, que pueden apuntar
al mismo idioma o a idiomas distintos.

Comprobar el resultado con:

```shell
php artisan cms:language:list
php artisan cms:language:validate en
```

## Configuración de producción

Configurar fuera de Git una conexión `mysql` o `mariadb` con credenciales del
entorno. Los nombres necesarios están documentados en `.env.example`; no se
deben versionar valores reales.

El modo de fallback de textos estáticos se controla mediante:

```dotenv
CMS_UI_CATALOG_FALLBACK_MODE=base
```

Valores admitidos:

| Valor | Comportamiento |
| :--- | :--- |
| `base` | Consulta el locale solicitado, después `en` y finalmente devuelve la clave. Recomendado en producción. |
| `key` | Consulta únicamente el locale solicitado y después devuelve la clave. Recomendado en desarrollo y pruebas. |

Un valor desconocido impide arrancar silenciosamente con otra política. La
configuración es compatible con `php artisan config:cache`.

Antes de servir tráfico, ejecutar:

```shell
php artisan migrate --force
php artisan cms:language:validate
php artisan config:cache
```

La validación de `UI catalogs` debe repetirse en cada despliegue que cambie un
catálogo. La administración web, los prefijos de URL y los overrides todavía no
están disponibles.
