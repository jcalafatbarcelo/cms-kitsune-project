# Pages

## Estado

Pages proporciona rutas públicas jerárquicas mediante la presentación
`public.page.standard`. El CMS Template efectivo aporta el Blade o lo hereda de
`Templates/Base`. PageBuilder y contenido editorial siguen fuera de este
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
guiones simples. Un slug de Page raíz no puede tener formato `xx` ni `xx-xx`,
reservado para los prefijos de idioma; un slug hijo sí puede usar esos formatos.
Sin `--template`, `cms:page:create` hereda el template
predeterminado; `--template=<identifier>` selecciona un template activo que
declare `public.page.standard` o pueda heredarlo de Base.

## UI de templates

La presentación estándar usa las claves semánticas
`page.home.under-construction.heading` y
`page.home.under-construction.message`. El runtime consulta primero el UI catalog
del template efectivo y después el del template que aporta el Blade. Una Page no
guarda el propietario del catálogo ni rutas Blade. Un template sin catálogo
propio hereda los textos de Base; si aporta claves propias, las declara en su
catálogo `en`.

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
redirecciones de negociación, sesión y alias de idioma incluyen
`Cache-Control: private, no-store`; las normalizaciones sintácticas de barra
final usan `301`. La negociación acepta solo idiomas base ISO 639-1 de dos letras
y regiones alfabéticas opcionales de dos letras, e ignora preferencias con
`q=0` y etiquetas fuera de ese contrato. No se crea una cookie persistente.
