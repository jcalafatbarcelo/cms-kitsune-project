# Gestión de Pages y Menus

## Alcance

El backoffice permite gestionar Pages y Menus con el superadministrador. Todas
las pantallas están bajo `/admin` y las mutaciones requieren sesión de
superadministrador y protección CSRF.

## Pages

En `/admin/pages` se listan las Pages, su tipo de contenido, los idiomas para los
que son home (si lo son) y el enlace a su edición. Cada locale home aparece en
una insignia independiente dentro de un grupo accesible. El botón de creación abre
`/admin/pages/new`; si la creación no es válida, esta pantalla conserva los
datos introducidos para corregirlos.

Crear una Page:

1. Indicar el nombre interno de la Page. Solo se utiliza para identificarla en
   administración; no se traduce ni se publica en la URL.
2. Elegir el idioma de la primera traducción, su slug y su título.
3. Elegir el tipo de contenido (por defecto `rich-text`), un template opcional y
   un padre opcional.
4. La Page se crea como borrador con stubs para todos los idiomas instalados. No
   se publica automáticamente.

El listado marca con la insignia **Despublicada** las Pages que no están
publicadas.

En la pantalla de edición se gestionan, por idioma:

- el `title` y el `slug`;
- el contenido editorial del editor de texto enriquecido (clave `body.content`);
- la publicación y la despublicación.

Con hasta cinco idiomas instalados, las traducciones se cambian mediante
pestañas. Con seis o más, la pantalla muestra el idioma actual, una búsqueda y
un selector nativo que filtra por locale y nombre del idioma. El idioma inicial
es el configurado para el backoffice cuando la Page dispone de esa traducción; si
un guardado falla, se mantiene activa la traducción enviada. Cambiar de idioma
no guarda datos, no envía una petición HTTP ni recarga la página. Cada panel
muestra una insignia **Publicada** o **Despublicada** según el estado de esa
traducción, independientemente del estado estructural de la Page; al cambiar de
idioma también cambia la insignia visible con el panel.

Sin JavaScript todos los formularios de traducción permanecen visibles y se
pueden editar con `textarea`, y cada panel mantiene un encabezado con el nombre
nativo y el locale para distinguirlo. Con JavaScript solo se muestra la
traducción activa y ese encabezado se oculta porque la pestaña o el selector ya
identifica el idioma, sin repetir visualmente el locale. TinyMCE se carga solo
para sus campos editoriales y, al cambiar de traducción, conserva el borrador en
el `textarea` antes de cerrar el editor.

Una traducción sin `title` o `slug` queda incompleta y no es publicable. El
contenido se sanitiza en el servidor antes de guardarse y el HTML original no se
conserva; el límite para `body.content` es 4 MiB.

La Page permite además moverla a otro padre y borrarla. El selector de padre usa
el nombre interno; **-- Ninguno --** conserva la Page en la raíz y no permite
seleccionar la propia Page ni sus descendientes. El borrado es físico y
atómico y se rechaza si la Page tiene descendientes, está marcada como home o es
referenciada por un ítem de menú. No se puede borrar una traducción de forma
individual.

## Menus

En `/admin/menus` se listan los menús y se accede a `/admin/menus/new` para
crear uno. Cada menú tiene su detalle en `/admin/menus/{menu}`, que muestra solo
sus ítems. Cada ítem referencia una Page por su nombre interno, una etiqueta y
una posición dentro de su idioma.

El detalle presenta tres pestañas en este orden: **Elementos**, **Añadir
elemento** y **Quitar inicio**. La primera contiene el listado y está activa al
cargar. Con JavaScript se pueden cambiar mediante ratón, flechas, Inicio y Fin
sin enviar datos ni recargar la página. Sin JavaScript los tres paneles siguen
visibles y operativos. Las acciones de ítems vuelven al detalle del mismo menú.

Los elementos se agrupan por idioma y se muestran como árbol: cada padre aparece
antes que sus descendientes y los hermanos conservan su posición persistida. La
tabla muestra **Posición**, **Etiqueta**, **Idioma** y **Acciones**. Bajo la
etiqueta aparece el nombre interno de la Page destino; la sangría y la rama son
apoyos visuales y no sustituyen el texto accesible.

Al crear un elemento se puede seleccionar su padre. Al moverlo, el selector no
ofrece el propio elemento ni ninguno de sus descendientes. Los candidatos
pertenecen siempre al mismo menú e idioma y se identifican como
`Etiqueta (#ID)`; **-- Ninguno --** sitúa el elemento en la raíz.

La posición se elige mediante huecos de inserción: primera posición, posiciones
posteriores a hermanos identificados como `Etiqueta (#ID)` y última posición,
que también identifica el último hermano cuando existe. Al cambiar el idioma o
el padre, JavaScript recalcula los huecos y selecciona el último. Sin JavaScript
queda disponible una selección inicial válida. El servidor sigue validando el
entero enviado y rechaza cualquier posición fuera de rango sin corregirla ni
aplicar cambios parciales.

Si **Añadir elemento** falla, el detalle vuelve a esa pestaña cuando JavaScript
está disponible y recupera únicamente locale, Page, etiqueta, padre y posición
que sigan siendo valores válidos y coherentes entre sí. Los errores de guardar o
mover un elemento existente no rellenan el formulario de alta. Sin JavaScript,
los tres paneles siguen visibles para corregir el error.

El detalle identifica con la insignia **Inicio** el ítem marcado como home y
marca con **Despublicada** los ítems cuya Page o traducción del idioma no están
publicadas.

Marcar un ítem como home:

1. Usar la acción **Marcar como inicio** del ítem deseado.
2. Solo se permite si su Page tiene una traducción pública y disponible en el
   idioma del ítem.

Existe como máximo una home por idioma en todo el sistema. Retirar la home exige
marcar la confirmación explícita: la raíz de ese idioma pasará a responder `404`
mientras no se asigne otra home.

## Mensajes y redirecciones

Al crear un Page o un Menu, el backoffice vuelve a la lista correspondiente
(`/admin/pages` o `/admin/menus`). Guardar una edición de Page sigue
volviendo a `/admin/pages`; las acciones de un ítem de menú vuelven al detalle
de su menú y muestran una alerta de confirmación. Las alertas usan un código de
semáforo:

- **Verde (éxito):** la operación se completó.
- **Ámbar (aviso):** la operación se completó, pero tiene una consecuencia
  temporal, como un `404` hasta republicar una Page o asignar otra home.
- **Rojo (error):** la operación no se completó o los datos no son válidos.

Los mensajes se anuncian a lectores de pantalla (`status` para éxito y `alert`
para aviso/error) y no dependen solo del color. Si una operación falla, se
conserva la pantalla actual para corregir los datos.

## Editor de texto enriquecido

El tipo `rich-text` usa TinyMCE en el backoffice. Si el editor no está disponible
en el navegador, el campo se degrada a un `textarea` y el servidor mantiene la
misma validación y sanitización. Si el sanitizador del servidor no está
disponible, el guardado se bloquea y no se acepta HTML sin procesar. Los assets
del editor se cargan únicamente en las pantallas que lo necesitan; la web pública
no los descarga.
