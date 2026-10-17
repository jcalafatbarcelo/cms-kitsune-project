# Problemas habituales de maquetación

## Comprobaciones iniciales

Cuando un estilo no se adapta correctamente, revisa antes de aplicar cambios más
complejos:

- El asset CSS compilado y cargado corresponde a la última versión.
- La caché del navegador está limpia.
- El error está localizado por dispositivo, navegador y media query.
- El selector apunta al elemento correcto; por ejemplo, `.c-textofoto` y
  `.c-textfoto` son selectores distintos.
- No hay herencias inesperadas ni conflictos de especificidad.
- Una regla que debe sobrescribir otra tiene la especificidad y el orden de
  origen necesarios.

## Componentes inicializados mediante JavaScript

Si un componente presenta un error visual después de inicializar JavaScript,
comprueba primero que su contenedor y sus elementos se maquetan correctamente
sin inicializarlo. Después, revisa sus dimensiones y reglas activas en la media
query donde aparece el fallo antes de atribuirlo al script.
