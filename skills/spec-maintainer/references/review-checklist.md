# Checklist de revisión de Specs

## Preparación

- La planificación previa identifica iniciativa, fases e incremento seleccionado.
- El incremento entrega comportamiento vertical y verificable.
- `feature` o `maintenance` está justificado.
- Alcance actual, fuera de alcance, diferido, riesgos y deuda no se confunden.

## Contenido

- El *clash check* cubre las fuentes de verdad afectadas.
- Las reglas e invariantes no admiten interpretaciones incompatibles.
- Cada bloque técnico relevante está completo o justificado como no aplicable.
- Calidad y seguridad son proporcionales al riesgo sin omitir mínimos esenciales.
- No se imponen detalles internos que no formen parte del contrato.
- Cada criterio describe un resultado observable.
- Cada criterio se relaciona con riesgo, prueba y documentación.
- Las fases de implementación son incrementos verticales, no capas aisladas.

## Preparación para aprobar

- No quedan `TODO`, `TBD`, instrucciones editoriales ni placeholders.
- `Decisiones abiertas` indica `No aplica`.
- No existen conflictos sin resolver.
- Los aplazamientos son seguros y tienen condición de revisión.
- La deuda relevante tiene aceptación explícita y condición de retirada.
- La validación estructural pasa, sin presentarla como revisión semántica.

## Cierre

- Todos los criterios tienen evidencia automatizada aplicable.
- Las pruebas enfocadas y la suite afectada pasan.
- La documentación comprometida se actualizó.
- Las excepciones y deuda aceptadas están registradas.
- El trabajo diferido permanece fuera de alcance y no como pendiente implícito.
