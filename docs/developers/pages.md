# Pages

## Estado

La fundación de Pages proporciona una home publicada para `en`, renderizada por
`Templates/Base` con la presentación `public.page.standard`. Las rutas
localizadas, PageBuilder, navegación y contenido editorial siguen fuera de este
incremento.

## Home y publicación

Cada idioma tiene una única home. La instalación crea la home inglesa con un
mensaje de construcción. La primera traducción creada para otro idioma se publica
y se asigna como home en la misma operación. Una home no puede despublicarse sin
seleccionar primero una sustituta pública para el mismo idioma.

## Operación por Artisan

```shell
php artisan cms:page:create en about "About"
php artisan cms:page:translate 2 es_ES acerca "Acerca de"
php artisan cms:page:publish 2 en
php artisan cms:page:set-home 2 en
```

Los slugs son obligatorios, únicos por idioma y usan minúsculas ASCII, números y
guiones simples. Sin `--template`, `cms:page:create` hereda el template
predeterminado; `--template=<identifier>` selecciona un template activo que
declare `public.page.standard`.

## UI de templates

La presentación estándar usa las claves semánticas
`page.home.under-construction.heading` y
`page.home.under-construction.message`. El runtime resuelve estas claves contra
el UI catalog del template efectivo; una Page no guarda el propietario del
catálogo ni rutas Blade. Todo template que declare la presentación estándar debe
aportar ambas claves en su catálogo `en`.
