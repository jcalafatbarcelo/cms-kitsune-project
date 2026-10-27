# Navigation

## Estado

Navigation proporciona menús públicos como árboles visuales independientes de la
jerarquía de Pages. Cada árbol pertenece a un idioma instalado; no hay fallback
de etiquetas ni de destinos entre idiomas.

## Operación por Artisan

```shell
php artisan cms:menu:create main-menu
php artisan cms:menu:item:create main-menu en 2 "About"
php artisan cms:menu:item:create main-menu en 3 "Team" --parent=1 --position=1
php artisan cms:menu:item:create main-menu es_ES 2 "Acerca de"
php artisan cms:menu:item:update 1 4 "Company"
php artisan cms:menu:item:move 1 2
php artisan cms:menu:item:remove 1
```

El argumento `page` es el `page_id` de la identidad estable de Pages, no el ID
de una traducción. Cada ítem pertenece al locale indicado en el comando: los
ítems de `en` y `es_ES` pueden formar árboles distintos dentro del mismo menú.
Un ítem solo se renderiza cuando su Page destino tiene una traducción pública en
ese mismo locale.

El identificador del menú usa entre 1 y 100 letras ASCII en minúscula, números
y guiones simples; empieza y termina con un carácter alfanumérico y no admite
guiones consecutivos.

La etiqueta usa hasta 255 caracteres UTF-8 y no admite caracteres de control.

Las posiciones son contiguas desde `1` entre hermanos. Crear o mover un ítem
reindexa el grupo afectado dentro de la misma transacción. `--position` es
opcional solo al crear, cuando añade el ítem al final; `--parent` omitido crea o
mueve el ítem a la raíz. No se puede retirar un ítem con hijos.

## Presentación pública

Un Blade de presentación solicita el menú mediante el componente de Core y
entrega el locale y CMS Template efectivo decididos por la capa HTTP:

```blade
<x-cms-navigation identifier="main-menu" :locale="$locale" :effective-template="$effectiveTemplate" />
```

El componente entrega el árbol al Blade `public.navigation.menu` resuelto desde
el template efectivo o Base. Para cada destino, Navigation consume
`PublicPageUrlResolver` de Pages: omite el ítem y todo su subárbol cuando la Page
no tiene una traducción pública disponible para ese locale. Navigation no
construye URLs ni consulta la jerarquía o traducciones internas de Pages.

Navigation agrupa los `page_id` únicos del menú y solicita sus URLs en una única
operación `forPages()` antes de recorrer el árbol.

## Home por ítem de menú

La home de cada idioma se marca desde un ítem de menú, con una única home por
idioma en todo el sistema y con independencia del menú al que pertenezca el ítem.
Marcar un ítem como home exige que su Page tenga una traducción pública y
disponible en el idioma del ítem, incluidos sus ancestros. Retirar la home de un
idioma es una acción explícita con confirmación: la raíz de ese idioma pasa a
responder `404` mientras no exista una home asignada.

Al recibir la raíz, Pages resuelve el idioma efectivo y consulta a Navigation la
Page marcada como home para ese idioma; Navigation busca el ítem y devuelve su
Page de destino. Para rutas con slugs, Navigation no interviene.

Despublicar la traducción de la home, despublicar un ancestro que la haga
inaccesible o borrar la Page home se bloquea.
