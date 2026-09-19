# Guías de ataque del CMS

Seleccionar solo las fronteras presentes en el alcance. Cada candidato requiere
una entrada controlable, control previsto, recurso protegido y resultado
observable para otro principal o estado compartido.

## HTTP y sesión

- Inventariar mutaciones autenticadas y comprobar autorización por recurso,
  CSRF, validación de request y rutas alternativas.
- Trazar redirecciones, URLs construidas con host o cabeceras y respuestas de
  error hasta su efecto sobre identidad, contenido o navegación.
- Si cookies, proxy, CSP o cabeceras efectivas dependen del despliegue, usar
  `needs_validation` con la comprobación concreta.

## Eloquent y módulos

- Comparar cada `find`, binding de ruta, relación, consulta bulk, exportación y
  job con la policy, propietario o scope que debe limitarlo.
- Comprobar mass assignment, campos de identidad procedentes del request y
  rutas de administración o importación que eviten el flujo normal.
- La existencia de una policy no basta: probar que se invoca para el recurso y
  operación correctos.

## Blade y Vue

- Trazar contenido editorial, URLs, datos de navegador y respuestas HTTP hasta
  Blade sin escape, DOM HTML, navegación, `postMessage` y almacenamiento.
- Verificar escapes de framework, validación de origen y lifecycle de sesión
  antes de declarar XSS, mensaje cross-origin o fuga de almacenamiento.
- Distinguir autoimpacto de ejecución o divulgación contra otra sesión.

## PageBuilder

- Comparar el schema de bloque con validación al escribir, al migrar y al leer.
- Buscar contenido seguro al almacenar que llegue a un sink HTML, URL, plantilla
  o configuración en una fase posterior.
- Confirmar que bloques, traducciones y previews no cruzan página, idioma,
  estado de publicación ni permisos.

## Medios

- Validar tipo real, tamaño, nombre, ruta y transformaciones; no confiar solo
  en extensión o `Content-Type` enviado.
- Revisar visibilidad de objeto, autorización de descarga, miniaturas,
  metadatos y referencias reutilizadas.
- Almacenamiento de objetos, antivirus o CDN no versionados requieren
  `needs_validation`.

## Idiomas, publicación y lifecycle

- Recorrer borrador, preview, traducción, publicación, despublicación,
  eliminación y restauración con el mismo recurso ficticio.
- Comprobar publicación en cascada, rutas, selector de idioma, caché, índices y
  jobs que puedan revelar una copia previa.
- Comparar principal, idioma y estado en cada consulta y clave de caché.

## Datos, jobs e integraciones

- Revisar aislamiento de propietario en importación, exportación, colas,
  listeners, comandos y tareas programadas.
- Trazar datos derivados hacia logs, notificaciones, caché, backups y artefactos.
- Webhooks, LLM, storage externo y servicios cloud se revisan por su frontera
  concreta; no inferir controles no presentes en el código.

## Supply chain y CI

- Para cada workflow, comparar evento, ref descargada, datos controlables,
  permisos, secretos, cachés, artefactos y publicación.
- Verificar SHA de Actions, entradas mutables, separación entre PR no confiable
  y jobs privilegiados, e identidad inmutable de los artefactos promovidos.
- Una dependencia vulnerable o un permiso amplio sin ruta explotable es una nota
  de endurecimiento, no un hallazgo confirmado.
