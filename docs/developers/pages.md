# Pages

## Estado

Pages proporciona rutas públicas jerárquicas mediante la presentación
`public.page.standard`. El CMS Template efectivo aporta el Blade o lo hereda de
`Templates/Base`. Pages introduce el primer tipo de contenido editorial
`rich-text`, su contenido persistido y su gestión desde el backoffice.

## Tipos de contenido

Cada Page tiene un `content_type` inmutable y compartido por todas sus
traducciones. Los tipos proceden de módulos instalados que los registran durante
el arranque; no son una lista administrativa persistida. El tipo inicial
editorial es `rich-text`, cuyo editor de backoffice es TinyMCE y cuya clave
editorial es `body.content`. El tipo de sistema `system.static` no es
seleccionable y se reserva para la Page inicial "Under construction", sin editor.

Un identificador de tipo desconocido, duplicado o con un proveedor inválido
produce un error controlado; el contenido no se reinterpreta ni se elige un tipo
alternativo.

## Contenido editorial

Cada `PageTranslation` guarda cero o más contenidos identificados por clave. La
clave usa segmentos ASCII en minúscula separados por puntos (por ejemplo
`body.content`), sin `::`, sin segmentos vacíos y con un máximo de 255
caracteres. En este incremento todo valor es HTML de un único editor y se
sanitiza en el servidor antes de persistirse; el HTML original no se conserva. El
límite de aplicación para `body.content` es 4 MiB medidos en bytes.

El fallback de contenido reutiliza el modo global de `UI catalogs`: en modo
`base`, una clave ausente o vacía resuelve el idioma predeterminado de frontend y
después la clave literal; en modo `key`, solo la clave literal. El idioma de
fallback sigue dinámicamente el predeterminado de frontend.

## Home y publicación

Cada idioma tiene como máximo una única home en todo el sistema. La instalación
crea la home inglesa "Under construction" publicada y la asocia a un ítem de un
menú sembrado (`home`). El resto de Pages se crean como borradores: ninguna Page
creada por el usuario se publica automáticamente. La home se marca y retira desde
un ítem de menú; véase [Navigation](navigation.md).

Al crear una Page se generan stubs de traducción para todos los idiomas
instalados. Una traducción sin `title` o `slug` válidos queda incompleta y no es
publicable. Al instalar un idioma nuevo se crean stubs para las Pages existentes.

## Operación por Artisan

```shell
php artisan cms:page:create en about "About"
php artisan cms:page:create es_ES inicio "Inicio" --type=rich-text
php artisan cms:page:translate 2 es_ES acerca "Acerca de"
php artisan cms:page:publish 2 en
php artisan cms:page:unpublish 2 en
```

Los slugs son obligatorios, únicos por idioma y usan minúsculas ASCII, números y
guiones simples. Un slug de Page raíz no puede tener formato `xx` ni `xx-xx`,
reservado para los prefijos de idioma; un slug hijo sí puede usar esos formatos.
Sin `--template`, `cms:page:create` hereda el template
predeterminado; `--template=<identifier>` selecciona un template activo que
declare `public.page.standard` o pueda heredarlo de Base. El `--type` por defecto
es `rich-text`.

El borrado de una Page es físico y atómico: elimina la Page, sus traducciones y
sus contenidos. Se rechaza si la Page tiene descendientes, está marcada como home
o es referenciada por un ítem de menú. No existe borrado de una traducción
individual; la Page es la unidad de borrado.

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
