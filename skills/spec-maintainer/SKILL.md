---
name: spec-maintainer
description: "Proponer, redactar, revisar, actualizar y validar Specs del proyecto con planificación previa, perfiles feature y maintenance, contenido técnico proporcional, clash check y trazabilidad entre aceptación, pruebas y documentación. Usar cuando el usuario pida planificar, crear, materializar, revisar, actualizar o validar una Spec en docs/specs/, preparar una Spec para aprobación o clasificar un cambio funcional como feature o maintenance; no usar para implementar el comportamiento, redactar ADR, mantener documentación ordinaria ni inventar decisiones funcionales ausentes."
---

# Mantener Specs

## Objetivo

Convertir un incremento decidido durante la planificación previa en un contrato
funcional implementable, revisable y proporcional a su riesgo. La plantilla
canónica está en `templates/plantilla_SPEC.md`; este archivo define el
procedimiento y no duplica su esqueleto.

Una Spec no sustituye la planificación de la iniciativa. Antes de redactarla se
deben haber decidido el problema, los bloques o fases, el incremento seleccionado,
el alcance actual y el diferido. La skill puede proponer esa descomposición en
`/plan`, pero no debe inventar decisiones ausentes ni materializar archivos sin
`/build`.

## Perfiles

Seleccionar un solo perfil y declararlo en los metadatos:

- `feature`: capacidades nuevas o cambios en reglas de negocio, contratos
  públicos, modelo de datos, permisos, flujos o compatibilidad.
- `maintenance`: reparación acotada que restaura un comportamiento ya definido
  sin cambiar su contrato, reglas de negocio ni modelo de datos.

Usar `feature` si la clasificación es dudosa. Un bug que requiere elegir un
comportamiento nuevo, ampliar el contrato o migrar datos no es mantenimiento
ligero. Ambos perfiles son Specs con la misma autoridad y requieren aprobación
antes de implementar cambios funcionales.

## Fuentes y entradas

1. Leer `AGENTS.md`, `context/SDD_Inicial.md`, la planificación previa, Specs
   relacionadas, ADR vigentes, código, pruebas y documentación afectada.
2. Identificar el incremento seleccionado, sus dependencias y el comportamiento
   observable que entregará. No convertir una iniciativa completa en una única
   Spec si admite incrementos verticales independientes.
3. Separar explícitamente:
   - alcance actual;
   - fuera de alcance;
   - alcance diferido y condición para retomarlo;
   - riesgos aceptados;
   - deuda técnica, si existe y ha sido aceptada.
4. Determinar qué garantías de calidad y seguridad son indispensables para que
   el incremento sea correcto. Puede diferirse complejidad evolutiva, pero no
   autorización, integridad, validación, seguridad o pruebas necesarias para el
   comportamiento que sí se entrega.

## Flujo

1. Confirmar el modo. En `/plan`, analizar y proponer sin modificar archivos. En
   `/build`, crear o actualizar la Spec, pero no implementar código funcional
   salvo que otra orden y una Spec aprobada autoricen expresamente ese alcance.
2. Realizar un *clash check* contra Specs vigentes, ADR aceptados, reglas de
   negocio, código, pruebas y documentación. Distinguir contradicción, concreción
   y reemplazo; detenerse ante un conflicto no resoluble.
3. Seleccionar `feature` o `maintenance` mediante
   `references/content-profiles.md` y cargar únicamente los bloques técnicos
   aplicables.
4. Crear `docs/specs/SPEC-<nombre-kebab-case>.md` desde la plantilla. Conservar
   las secciones del núcleo común y eliminar las instrucciones editoriales. Para
   cada bloque técnico relevante, completarlo o declarar `No aplicable` con una
   justificación concreta.
5. Usar `Borrador` al crear una Spec, salvo que el usuario aporte aprobación
   explícita. Estados admitidos: `Borrador`, `Propuesta`, `Aprobada`,
   `Completada`, `Rechazada` y `Reemplazada`. Solo `Aprobada` autoriza iniciar la
   implementación; `Completada` registra que el alcance ya fue implementado.
6. Formular criterios de aceptación numerados `CA-01`, `CA-02`, etc., observables
   y sin prescribir detalles internos innecesarios. Relacionar cada criterio con
   riesgos, nivel de prueba y documentación mediante la matriz de trazabilidad.
7. Aplicar `references/review-checklist.md`. Una Spec no puede pasar a
   `Aprobada` con placeholders, decisiones funcionales abiertas, contradicciones
   sin resolver o garantías esenciales diferidas.
8. Ejecutar la validación estructural:

   ```bash
   python3 skills/spec-maintainer/scripts/validate_spec.py \
     docs/specs/SPEC-<nombre-kebab-case>.md
   ```

9. Revisar semánticamente lo que el script no garantiza: corrección del dominio,
   proporcionalidad de calidad y seguridad, viabilidad técnica, suficiencia de
   los criterios y validez de los aplazamientos.

## Cambios durante la implementación

- Resolver dentro de la misma Spec los defectos, refactors y ajustes internos
  necesarios para satisfacer sus criterios sin cambiar comportamiento esperado,
  alcance, datos, API pública ni reglas de negocio.
- Si cambia alguno de esos elementos o los criterios de aceptación, actualizar
  la Spec y obtener nueva aprobación antes de continuar esa parte. No hace falta
  crear otra Spec si sigue siendo el mismo incremento coherente.
- Crear una Spec separada para funcionalidad independiente o bugs ajenos
  descubiertos incidentalmente. El estado abierto de una Spec no la convierte en
  un contenedor ilimitado de cambios.
- Tras completar una Spec, tratar una regresión localizada mediante perfil
  `maintenance` y enlazar la fuente que define el comportamiento esperado.

## Fallo seguro

- No marcar una Spec como aprobada sin autorización explícita.
- No rebajar una `feature` a `maintenance` para evitar contenido necesario.
- No inventar campos, endpoints, reglas, decisiones de producto ni deuda
  aceptada.
- No usar `Fuera de alcance` para omitir corrección, seguridad o integridad
  esenciales del incremento.
- No presentar la validación estructural como aprobación o garantía semántica.
- No editar código, pruebas ni documentación ordinaria mediante esta skill.
