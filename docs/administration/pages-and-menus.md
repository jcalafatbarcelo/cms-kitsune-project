# Gestión de Pages y Menus

## Alcance

El backoffice permite gestionar Pages y Menus con el superadministrador. Todas
las pantallas están bajo `/admin` y las mutaciones requieren sesión de
superadministrador y protección CSRF.

## Pages

En `/admin/pages` se listan las Pages, su tipo de contenido, el idioma para el
que son home (si lo son) y el enlace a su edición.

Crear una Page:

1. Elegir el idioma de la primera traducción, su slug y su título.
2. Elegir el tipo de contenido (por defecto `rich-text`), un template opcional y
   un padre opcional.
3. La Page se crea como borrador con stubs para todos los idiomas instalados. No
   se publica automáticamente.

En la pantalla de edición se gestionan, por idioma:

- el `title` y el `slug`;
- el contenido editorial del editor de texto enriquecido (clave `body.content`);
- la publicación y la despublicación.

Una traducción sin `title` o `slug` queda incompleta y no es publicable. El
contenido se sanitiza en el servidor antes de guardarse y el HTML original no se
conserva; el límite para `body.content` es 4 MiB.

La Page permite además moverla a otro padre y borrarla. El borrado es físico y
atómico y se rechaza si la Page tiene descendientes, está marcada como home o es
referenciada por un ítem de menú. No se puede borrar una traducción de forma
individual.

## Menus

En `/admin/menus` se crean menús y sus ítems. Cada ítem referencia una Page, una
etiqueta y una posición dentro de su idioma.

Marcar un ítem como home:

1. Usar la acción **Marcar como inicio** del ítem deseado.
2. Solo se permite si su Page tiene una traducción pública y disponible en el
   idioma del ítem.

Existe como máximo una home por idioma en todo el sistema. Retirar la home exige
marcar la confirmación explícita: la raíz de ese idioma pasará a responder `404`
mientras no se asigne otra home.

## Editor de texto enriquecido

El tipo `rich-text` usa TinyMCE en el backoffice. Si el editor no está disponible
en el navegador, el campo se degrada a un `textarea` y el servidor mantiene la
misma validación y sanitización. Si el sanitizador del servidor no está
disponible, el guardado se bloquea y no se acepta HTML sin procesar. Los assets
del editor se cargan únicamente en las pantallas que lo necesitan; la web pública
no los descarga.
