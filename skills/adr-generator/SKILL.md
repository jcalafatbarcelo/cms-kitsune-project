---
name: adr-generator
description: "Proponer, redactar, revisar y actualizar ADR del proyecto con plantilla canónica, numeración, estados, reemplazos y validaciones estructurales. Usar cuando el usuario pida proponer, generar, redactar, revisar o actualizar un Architecture Decision Record y la decisión supere el umbral arquitectónico definido por AGENTS.md; no usar para refactors locales, correcciones rutinarias, decisiones ya prescritas o documentación no arquitectónica."
---

# adr-generator

## Objetivo

Guiar la propuesta, redacción, revisión y actualización de Architecture Decision
Records del proyecto con una plantilla canónica y validaciones deterministas.

La plantilla de salida es
`skills/adr-generator/templates/plantilla_ADR.md`. Este `SKILL.md` define el
procedimiento; no duplica el esqueleto mantenible del ADR.

## Cuándo usarla

Usa esta skill cuando el usuario pida proponer, generar, redactar, revisar o
actualizar un ADR y la decisión cumpla todo el umbral de `AGENTS.md`:

- es arquitectónica y duradera;
- afecta a más de un componente o impone una restricción transversal;
- admite alternativas razonables;
- tiene consecuencias futuras que justifican conservar el razonamiento; y
- no está ya prescrita por una Spec o ADR vigente.

No la uses para refactors locales, cambios de nombre, correcciones rutinarias,
detalles reversibles ni documentación funcional que no registre una decisión
arquitectónica.

## Flujo

1. Leer `AGENTS.md`, `context/SDD_Inicial.md`, Specs afectadas y ADR existentes
   en `docs/adr/`.
2. Confirmar el umbral arquitectónico. Si no se cumple, rechazar la creación del
   ADR y explicar si corresponde una Spec, documentación funcional, changelog o
   comentario de implementación.
3. Hacer *clash check* contra Specs aprobadas, ADR aceptados y reglas de negocio.
   Si aparece una contradicción no resuelta, detener el flujo y solicitar una
   decisión.
4. Asignar el identificador como el máximo `ADR-NNNN` existente más uno. Si hay
   nombres, encabezados o números duplicados o mal formados, detener el flujo sin
   sobrescribir ni renumerar.
5. Crear el ADR desde `templates/plantilla_ADR.md`, completando todos los
   metadatos y secciones obligatorias. La creación inicial usa `Propuesto` salvo
   que el usuario aporte evidencia explícita de aceptación previa.
6. Para reemplazos, actualizar de forma recíproca `Reemplaza a` y `Reemplazado
   por`; solo un ADR `Aceptado` puede reemplazar decisiones previas.
7. Ejecutar la validación estructural antes de terminar:

   ```bash
   python3 skills/adr-generator/scripts/validate_adr.py docs/adr/ADR-NNNN-titulo-kebab-case.md
   ```

8. Revisar explícitamente las partes semánticas que el script no puede garantizar:
   viabilidad real de alternativas, consecuencias concretas e impacto transversal.

## Estados

Estados admitidos: `Propuesto`, `Aceptado`, `Rechazado`, `Obsoleto` y
`Reemplazado`.

Transiciones válidas:

```text
Propuesto -> Aceptado | Rechazado
Aceptado  -> Obsoleto | Reemplazado
```

`Rechazado`, `Obsoleto` y `Reemplazado` son terminales. Una reconsideración se
documenta en un ADR nuevo enlazado al anterior.

## Fallo seguro

- No sobrescribir ADR existentes.
- No reutilizar identificadores.
- No dejar relaciones de reemplazo unilaterales.
- No presentar validaciones estructurales como garantía semántica absoluta.
- Si falta información obligatoria, pedirla antes de escribir o dejar el ADR en
  estado `Propuesto` con contenido completo y verificable.
