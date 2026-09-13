---
name: skill-creator
description: Generar y validar el andamiaje de skills del proyecto y mantener su catálogo canónico mediante un flujo compatible con distintos agentes. Usar cuando el usuario pida crear, mantener o migrar skills del proyecto o regenerar su índice; exigir nombre kebab-case, descripción restrictiva y condiciones de uso solo para crear una skill; no usar para modificar código de aplicación ni sobrescribir una skill existente mediante el generador.
---

# Crear y mantener skills del proyecto

Crear e indexar skills de proyecto mediante un flujo determinista. Exigir estos datos solo para crear una skill:

- `nombre_skill`: identificador kebab-case en minúsculas.
- `descripcion_skill`: alcance detallado, restricciones y límites.
- `condiciones_de_uso`: contexto y disparadores autónomos exactos.

## Principio de alcance

Mantener `skill-creator` agnóstica respecto al agente que la ejecute: sus
instrucciones deben poder seguirse desde Claude, Cursor, Copilot, Codex u otro
agente compatible. No trasladar esa cualidad a las skills generadas. Crear cada
nueva skill para las necesidades, arquitectura, convenciones y fuentes de verdad
de este proyecto, según la descripción y las condiciones proporcionadas.

## Estructura de salida

Generar siempre la estructura base y añadir recursos opcionales únicamente
cuando el flujo de la nueva skill los necesite:

```text
.agents/skills/<nombre_skill>/
├── SKILL.md              # Obligatorio
├── agents/
│   └── openai.yaml       # Recomendado; generado por este flujo
├── scripts/              # Opcional: lógica determinista o reutilizable
├── references/           # Opcional: contexto detallado bajo demanda
└── assets/               # Opcional: archivos usados en las salidas
```

El script crea `SKILL.md` y `agents/openai.yaml`. Después del scaffolding, crear
`scripts/`, `references/` o `assets/` solo si se han identificado recursos que
justifican su existencia. No crear archivos auxiliares como `README.md`.

## Flujo

1. Localizar la raíz del proyecto que contiene `AGENTS.md` y `.agents/skills/`.
2. Leer las instrucciones y fuentes de verdad vigentes del proyecto que afecten
   al dominio de la nueva skill.
3. Comprobar que los tres datos estén presentes, que el nombre cumpla
   `[a-z0-9]+(?:-[a-z0-9]+)*` y que `.agents/skills/<nombre_skill>/` no exista.
4. Ejecutar `scripts/crear_nueva_skill.py` desde esta skill. Este script realiza
   el scaffolding definido por `skill-creator`, crea únicamente
   `.agents/skills/<nombre_skill>/SKILL.md` y `.agents/skills/<nombre_skill>/agents/openai.yaml`,
   valida los archivos y regenera atómicamente `.agents/skills/INDEX.md` desde los
   frontmatter de las skills existentes.
5. Pasar los valores como argumentos separados, sin interpolarlos en un comando
   de shell:

   ```bash
   python .agents/skills/skill-creator/scripts/crear_nueva_skill.py \
     --project-root . \
     --nombre-skill "<nombre_skill>" \
     --descripcion-skill "<descripcion_skill>" \
     --condiciones-de-uso "<condiciones_de_uso>"
   ```

6. Revisar el `SKILL.md` generado, concretar su flujo con las convenciones del
   proyecto que correspondan al dominio solicitado, sin convertirlo en una guía
   genérica reutilizable fuera del proyecto, y añadir únicamente los directorios
   opcionales que requiera la implementación.
7. Tras concretar el contenido, regenerar el índice para validar los frontmatter
   con el tooling local, sin buscar validadores en skills globales del cliente:

   ```bash
   python .agents/skills/skill-creator/scripts/crear_nueva_skill.py \
     --project-root . --regenerate-index
   ```

8. Confirmar que la ruta creada y la regeneración de `.agents/skills/INDEX.md` aparecen
   en la salida. Si cualquier paso falla, detenerse y comunicar literalmente el
   error; no dejar una skill parcial ni sobrescribir contenido existente.

## Mantenimiento y migración

Con una petición `/build`, revisar únicamente las skills pertinentes del catálogo
canónico. Aplicar los cambios manuales y movimientos mediante `apply_patch`, sin
duplicar skills ni crear symlinks. Actualizar las referencias operativas y ejecutar
las pruebas existentes de los scripts afectados. No modificar Specs históricas
para reflejar un traslado de rutas.

Para regenerar únicamente el índice, usar el comando del paso 7 sin argumentos
de creación. Requiere que `.agents/skills/` exista; valida todos sus frontmatter
antes de reemplazar atómicamente el índice. Si falla, conserva el índice anterior.
Antes de regenerarlo, comprobar que solo contiene skills aprobadas del proyecto;
el autodescubrimiento de metadatos del cliente no constituye esa aprobación.

## Límites

- No inferir entradas ausentes.
- No instalar dependencias ni acceder a la red.
- No crear documentación auxiliar como `README.md` o `CHANGELOG.md` dentro de
  la skill.
- No incorporar una skill al índice hasta que sus archivos base hayan sido
  validados.
- No editar manualmente `.agents/skills/INDEX.md`: siempre regenerarlo desde los
  `.agents/skills/*/SKILL.md` existentes.
- No buscar ni indexar skills fuera de `.agents/skills/`.
