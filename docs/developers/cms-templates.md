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
