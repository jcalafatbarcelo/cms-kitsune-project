#!/usr/bin/env python3
"""Crea una skill mínima y la registra en el AGENTS.md del proyecto."""

from __future__ import annotations

import argparse
import json
import re
import shutil
import sys
import tempfile
from pathlib import Path


NAME_PATTERN = re.compile(r"[a-z0-9]+(?:-[a-z0-9]+)*\Z")
INVENTORY_MARKER = "### Skills Registradas en el Proyecto"
TABLE_SEPARATOR = "| :--- | :--- | :--- |"


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--project-root", type=Path, default=Path.cwd())
    parser.add_argument("--nombre-skill", required=True)
    parser.add_argument("--descripcion-skill", required=True)
    parser.add_argument("--condiciones-de-uso", required=True)
    return parser.parse_args()


def clean_required(value: str, field: str) -> str:
    value = " ".join(value.split())
    if not value:
        raise ValueError(f"{field} no puede estar vacío")
    return value


def yaml_string(value: str) -> str:
    return json.dumps(value, ensure_ascii=False)


def table_cell(value: str) -> str:
    return value.replace("\\", "\\\\").replace("|", "\\|")


def build_skill_md(name: str, description: str, conditions: str) -> str:
    trigger = f"{description} Usar cuando {conditions}"
    return f"""---
name: {name}
description: {yaml_string(trigger)}
---

# {name}

## Objetivo

{description}

## Contexto del proyecto

Aplicar esta skill dentro del proyecto que la contiene. Antes de ejecutarla,
consultar `AGENTS.md` y las fuentes de verdad del repositorio que afecten a su
dominio. Concretar el flujo con su arquitectura y convenciones; no asumir que la
skill deba ser reutilizable fuera del proyecto.

## Estructura

```text
skills/{name}/
├── SKILL.md              # Instrucciones y metadatos obligatorios
├── agents/
│   └── openai.yaml       # Metadatos de interfaz
├── scripts/              # Opcional: automatizaciones ejecutables
├── references/           # Opcional: contexto cargado bajo demanda
└── assets/               # Opcional: plantillas y recursos de salida
```

Crear las carpetas opcionales solo cuando este flujo necesite esos recursos.

## Flujo de trabajo

1. Confirmar que la solicitud coincide con las condiciones indicadas en la descripción.
2. Leer las instrucciones y fuentes de verdad aplicables del proyecto.
3. Reunir las entradas necesarias antes de realizar cambios.
4. Ejecutar la tarea dentro del alcance descrito, respetando sus límites.
5. Validar el resultado y comunicar cualquier error exacto sin ocultarlo.

## Límites

- No actuar fuera del alcance definido en la descripción.
- No inventar entradas obligatorias que el usuario no haya proporcionado.
- Detener la ejecución si una validación requerida falla.
"""


def build_openai_yaml(name: str, description: str) -> str:
    display_name = name.replace("-", " ").capitalize()
    short = description[:61].rstrip(" .")
    if len(short) < 25:
        short = f"Ejecuta el flujo {display_name}"[:64]
    prompt = f"Usa ${name} para ejecutar este flujo de forma validada."
    return (
        "interface:\n"
        f"  display_name: {yaml_string(display_name)}\n"
        f"  short_description: {yaml_string(short)}\n"
        f"  default_prompt: {yaml_string(prompt)}\n"
    )


def validate_scaffold(skill_dir: Path, name: str) -> None:
    skill_md = skill_dir / "SKILL.md"
    openai_yaml = skill_dir / "agents" / "openai.yaml"
    if not skill_md.is_file() or not openai_yaml.is_file():
        raise RuntimeError("el scaffolding no contiene SKILL.md y agents/openai.yaml")
    content = skill_md.read_text(encoding="utf-8")
    if not content.startswith("---\n") or f"name: {name}\n" not in content:
        raise RuntimeError("SKILL.md no contiene frontmatter válido para el nombre solicitado")


def register(agents_path: Path, name: str, description: str, conditions: str) -> None:
    content = agents_path.read_text(encoding="utf-8")
    if INVENTORY_MARKER not in content or TABLE_SEPARATOR not in content:
        raise RuntimeError("AGENTS.md no contiene el inventario de skills esperado")
    if re.search(rf"^\| `{re.escape(name)}` \|", content, flags=re.MULTILINE):
        raise RuntimeError(f"la skill '{name}' ya está registrada en AGENTS.md")
    row = (
        f"| `{table_cell(name)}` | `skills/{table_cell(name)}/SKILL.md` | "
        f"{table_cell(description)} Cuándo usarla: {table_cell(conditions)} |"
    )
    insertion = content.index("\n", content.index(TABLE_SEPARATOR)) + 1
    agents_path.write_text(content[:insertion] + row + "\n" + content[insertion:], encoding="utf-8")


def main() -> int:
    args = parse_args()
    root = args.project_root.resolve()
    agents_path = root / "AGENTS.md"
    skills_dir = root / "skills"
    name = clean_required(args.nombre_skill, "nombre_skill")
    description = clean_required(args.descripcion_skill, "descripcion_skill")
    conditions = clean_required(args.condiciones_de_uso, "condiciones_de_uso")

    if not NAME_PATTERN.fullmatch(name):
        raise ValueError("nombre_skill debe estar en kebab-case y usar solo a-z, 0-9 y guiones")
    if not agents_path.is_file():
        raise FileNotFoundError(f"no se encontró AGENTS.md en {root}")

    target = skills_dir / name
    if target.exists():
        raise FileExistsError(f"la ruta ya existe: {target}")

    skills_dir.mkdir(parents=True, exist_ok=True)
    with tempfile.TemporaryDirectory(prefix=f".{name}-", dir=skills_dir) as temp:
        staged = Path(temp) / name
        (staged / "agents").mkdir(parents=True)
        (staged / "SKILL.md").write_text(
            build_skill_md(name, description, conditions), encoding="utf-8"
        )
        (staged / "agents" / "openai.yaml").write_text(
            build_openai_yaml(name, description), encoding="utf-8"
        )
        validate_scaffold(staged, name)
        shutil.move(str(staged), target)

    try:
        register(agents_path, name, description, conditions)
    except Exception:
        shutil.rmtree(target)
        raise

    print(f"Skill creada correctamente: {target}")
    print(f"Skill registrada correctamente: {agents_path}")
    return 0


if __name__ == "__main__":
    try:
        sys.exit(main())
    except Exception as error:
        print(f"ERROR: {error}", file=sys.stderr)
        sys.exit(1)
