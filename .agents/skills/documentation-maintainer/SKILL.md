---
name: documentation-maintainer
description: "Crear, actualizar, reorganizar y validar documentación canónica del producto en docs/ según su audiencia, estado de implementación y versión. Usar cuando una petición con /build afecte guías de usuario, administración, desarrollo, arquitectura, módulos o API, o una implementación aprobada requiera actualizarlas; no usar para Specs, ADR, CHANGELOG.md, código de aplicación ni para operar contenido generado por una plataforma externa."
---

# Mantener la documentación del producto

Crear documentación portable, revisable y coherente con el estado real del CMS.
Aplicar la estrategia definida en
`docs/architecture/documentation-strategy.md` sin sustituir las fuentes de
verdad del proyecto.

## Entradas necesarias

Antes de editar, determinar mediante la petición y el repositorio:

- objetivo y audiencia del documento;
- comportamiento, contrato o procedimiento que se documenta;
- estado real: previsto, en desarrollo, disponible, deprecado o sin soporte;
- versión o rama aplicable, cuando exista versionado público;
- documentación relacionada y rutas afectadas.

Solicitar aclaración solo si falta una decisión que produzca documentos
materialmente distintos. No preguntar por detalles que puedan verificarse en
las fuentes de verdad.

## Flujo

1. Confirmar que la tarea no corresponde a una Spec, ADR, changelog, código de
   aplicación ni a la operación de una plataforma documental externa.
2. Leer el código y las pruebas afectados, las Specs aprobadas, los ADR vigentes
   y la documentación relacionada. Contrastar siempre las afirmaciones sobre
   disponibilidad con el estado ejecutable.
3. Identificar una o varias audiencias: usuario/editor, administrador,
   desarrollador de extensiones o contributor del core.
4. Elegir o conservar la ubicación más específica bajo `docs/`:

   | Contenido | Ubicación preferente |
   | :--- | :--- |
   | Primeros pasos | `docs/getting-started/` |
   | Uso del backoffice | `docs/user-guide/` |
   | Operación y despliegue | `docs/administration/` |
   | Extensión del CMS | `docs/developers/` |
   | Arquitectura transversal | `docs/architecture/` |
   | Responsabilidad de módulos | `docs/modules/` |
   | Contratos HTTP | `docs/api/` |

   No crear directorios vacíos ni mover documentos únicamente para anticipar
   una estructura futura.
5. Redactar el cambio mínimo completo. Separar explicación conceptual,
   requisitos, procedimiento, resultado esperado y errores frecuentes cuando
   sean aplicables; omitir secciones artificiales.
6. Mantener un único `H1`, encabezados descriptivos, enlaces relativos y
   secciones enlazables. Definir términos en su primera aparición, evitar
   duplicación y enlazar la fuente canónica en lugar de copiarla.
7. Usar comandos, rutas, payloads y ejemplos exactos y verificables. Marcar
   expresamente lo previsto y no presentarlo como disponible. No inventar
   contratos, configuración ni comportamiento ausente de las fuentes.
8. Añadir diagramas Mermaid o capturas solo cuando reduzcan una ambigüedad real.
   Las imágenes deben usar datos ficticios, ocultar información sensible, tener
   texto alternativo y complementar —no reemplazar— las instrucciones textuales.
9. Declarar la versión aplicable cuando varias versiones públicas puedan diferir.
   Las vistas generadas deberán identificar también la revisión o commit fuente.
10. Ejecutar los validadores Markdown, comprobadores de enlaces, build del portal
    y detector de secretos ya configurados que resulten aplicables. No instalar
    ni imponer herramientas inexistentes.
11. Revisar el diff completo para detectar afirmaciones no sustentadas, enlaces
    rotos, secretos, artefactos generados accidentales y cambios ajenos.
12. Informar de cada comando y resultado. Declarar como advertencia las
    comprobaciones no ejecutadas y su limitación concreta.

## Criterios de salida

- El documento permite completar su objetivo sin depender de conocimiento
  implícito ni de una wiki externa.
- La audiencia, disponibilidad y versión no resultan ambiguas.
- Las afirmaciones se corresponden con las fuentes de verdad aplicables.
- Los ejemplos son seguros, mínimos y reproducibles cuando el producto lo
  permite.
- La organización y los enlaces permiten publicar el Markdown con otra
  herramienta sin una reescritura sustancial.

## Límites

- No editar `docs/specs/`, `docs/adr/`, `CHANGELOG.md` ni
  `docs/changelog/` mediante esta skill; usar sus flujos específicos.
- No modificar código, pruebas, contratos ni configuración ejecutable para hacer
  coincidir el producto con la documentación. Si se descubre esa necesidad,
  detener esa parte y aplicar los requisitos SDD del proyecto.
- No publicar como canónico contenido inferido o generado por IA sin revisión.
- No seleccionar, desplegar ni configurar una wiki, buscador o portal externo.
- No incluir secretos, datos reales ni instrucciones inseguras.
