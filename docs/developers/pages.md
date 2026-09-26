# Pages

## Estado

Pages proporciona rutas públicas jerárquicas, renderizadas por `Templates/Base`
con la presentación `public.page.standard`. PageBuilder, navegación y contenido
editorial siguen fuera de este incremento.

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
guiones simples. Un slug de Page raíz no puede tener formato `xx` ni `xx-xx`,
reservado para los prefijos de idioma; un slug hijo sí puede usar esos formatos.
Sin `--template`, `cms:page:create` hereda el template
predeterminado; `--template=<identifier>` selecciona un template activo que
declare `public.page.standard`.

## UI de templates

La presentación estándar usa las claves semánticas
`page.home.under-construction.heading` y
`page.home.under-construction.message`. El runtime resuelve estas claves contra
el UI catalog del template efectivo; una Page no guarda el propietario del
catálogo ni rutas Blade. Todo template que declare la presentación estándar debe
aportar ambas claves en su catálogo `en`.

## Rutas públicas localizadas

El idioma predeterminado de frontend no lleva prefijo: `/` resuelve su home y
`/parent/child` resuelve una cadena exacta de slugs y ancestros. Un idioma
secundario activo usa su alias corto de familia, por ejemplo `/es/` y
`/es/padre/hija`. Una variante no general mantiene el prefijo regional, como
`/es-mx/padre/hija`.

La variante regional de la única variante activa o de la variante general
redirige con `302` al alias corto. Si el alias o un prefijo regional resuelve el
idioma predeterminado, redirige con `302` a la URL equivalente sin prefijo. No
hay fallback de contenido: una traducción ausente, una jerarquía incorrecta o un
ancestro no publicable responde `404`.

En `/`, la selección usa locale activo de sesión, después `Accept-Language` y,
por último, el predeterminado de frontend. Las URLs explícitas y las rutas sin
prefijo distintas de `/` actualizan la sesión según su idioma representado. Las
redirecciones de negociación, sesión y canonicidad incluyen
`Cache-Control: private, no-store`; no se crea una cookie persistente.
