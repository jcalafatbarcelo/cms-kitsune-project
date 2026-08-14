---
name: skill-creator
description: Generar el andamiaje aislado de una nueva skill orientada al proyecto, validarlo y registrarlo en el inventario de AGENTS.md mediante un flujo compatible con distintos agentes. Usar cuando el usuario pida crear una skill, automatizar un flujo recurrente o estandarizar una tarea y proporcione nombre kebab-case, descripción restrictiva y condiciones de uso; no usar para modificar código de aplicación ni para sobrescribir una skill existente.
---

# Crear una nueva skill

Crear y registrar skills de proyecto mediante un flujo determinista. Exigir estos datos antes de ejecutar:

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
skills/<nombre_skill>/
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

1. Localizar la raíz del proyecto que contiene `AGENTS.md` y su directorio `skills/`.
2. Leer las instrucciones y fuentes de verdad vigentes del proyecto que afecten
   al dominio de la nueva skill.
3. Comprobar que los tres datos estén presentes, que el nombre cumpla
   `[a-z0-9]+(?:-[a-z0-9]+)*` y que `skills/<nombre_skill>/` no exista.
4. Ejecutar `scripts/crear_nueva_skill.py` desde esta skill. Este script realiza
   el scaffolding definido por `skill-creator`, crea únicamente
   `skills/<nombre_skill>/SKILL.md` y `skills/<nombre_skill>/agents/openai.yaml`,
   valida los archivos y registra la skill en `AGENTS.md`.
5. Pasar los valores como argumentos separados, sin interpolarlos en un comando
   de shell:

   ```bash
   python skills/skill-creator/scripts/crear_nueva_skill.py \
     --project-root . \
     --nombre-skill "<nombre_skill>" \
     --descripcion-skill "<descripcion_skill>" \
     --condiciones-de-uso "<condiciones_de_uso>"
   ```

6. Revisar el `SKILL.md` generado, concretar su flujo con las convenciones del
   proyecto que correspondan al dominio solicitado, sin convertirlo en una guía
   genérica reutilizable fuera del proyecto, y añadir únicamente los directorios
   opcionales que requiera la implementación.
7. Ejecutar el validador de `skill-creator` sobre la carpeta generada cuando
   esté disponible:

   ```bash
   python /opt/codex/skills/.system/skill-creator/scripts/quick_validate.py \
     skills/<nombre_skill>
   ```

8. Confirmar que la ruta creada y el registro de `AGENTS.md` aparecen en la
   salida. Si cualquier paso falla, detenerse y comunicar literalmente el error;
   no editar parcialmente el inventario ni sobrescribir contenido existente.

## Límites

- No inferir entradas ausentes.
- No instalar dependencias ni acceder a la red.
- No crear documentación auxiliar como `README.md` o `CHANGELOG.md` dentro de
  la skill.
- No registrar una skill hasta que sus archivos base hayan sido validados.
- No alterar filas existentes del inventario de skills.
