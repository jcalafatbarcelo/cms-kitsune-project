# Problemas habituales detectados

Aquí encontrarás una lista de problemas habituales localizados y donde "suele" estar el error.

## Comprobaciones iniciales

Cuando tenemos problemas de estilos que no se adaptan correctamente, conviene revisar ciertos puntos antes de abordar soluciones más complejas:

- El CSS está correctamente cargado (es la última versión).
- Hemos limpiado el caché (de Joomla y del navegadcor) por si es un problema de cacché.
- Hemos comprobado en qué dispositivos / navegadores aparece el error (y en qué `media`).
- Revisamos que el estilo que se tiene que aplicar está bien "apuntado". Es muy común que, por ejemplo, `.c-textofoto` esté declarado y cuando vamos a declarar la misma clase en un `media` concreto haya un error de escritura, `.c-textfoto` (en este ejemplo hemos quitado una `o`, por lo que el apuntador ya no es correcto y no lo encontrará el navegador).
- Comprobamos que el contenedor que da error, no está heredando estilos que no contemplados.
- Que si queremos "machacar" estilos ya presentes, el apuntador tiene suficiente autoridad para garantizar que se priorice la nueva versión por encima de la original.

## OwSlider (Slider)

### El Slider no se visualiza correctamente cuando se pasa de un slider a otro (next o prev)

Cuando el Slider no se visualiza correctamente el cambio de un item a otro, mostrando más contenido del que debería, haciendose la caja contenedora más grande u otro error similar. Suele ser debido a un error de maquetación en el `media` en el que falla.

**Truco para solucionarlo**
Comprueba que el slider se muestra correctamente incluso sin inicializar el Slider. Un error común es no "maquetar" el contenedor del Slider sin inicializarlo, lo que provoca que el Script owSlider no lea correctamente los tamaños "a mostrar" y no calcule bien los tamaños a la hora de realizar las animaciones de `next` y `prev`. 