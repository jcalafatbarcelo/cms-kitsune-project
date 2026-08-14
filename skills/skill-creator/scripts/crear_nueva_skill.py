#!/usr/bin/env python3
"""Crea una skill mínima y regenera el índice de skills del proyecto."""

from __future__ import annotations

import argparse
import json
import os
import re
import shutil
import sys
import tempfile
from pathlib import Path

NAME_PATTERN = re.compile(r"[a-z0-9]+(?:-[a-z0-9]+)*\Z")
FRONTMATTER_PATTERN = re.compile(r"\A---\n(?P<body>.*?)\n---(?:\n|\Z)", re.DOTALL)


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


def parse_scalar(value: str, path: Path, field: str) -> str:
    value = value.strip()
    if value.startswith('"'):
        try:
            parsed = json.loads(value)
        except json.JSONDecodeError as error:
            raise RuntimeError(f"frontmatter inválido en {path}: {field}") from error
        if not isinstance(parsed, str):
            raise RuntimeError(
                f"frontmatter inválido en {path}: {field} debe ser texto"
            )
        return parsed
    if not value or value[0] in "'[{&*!|>":
        raise RuntimeError(f"frontmatter inválido en {path}: {field}")
    return value


def read_metadata(skill_md: Path) -> tuple[str, str, str]:
    content = skill_md.read_text(encoding="utf-8")
    match = FRONTMATTER_PATTERN.match(content)
    if not match:
        raise RuntimeError(f"frontmatter ausente o inválido en {skill_md}")

    fields: dict[str, str] = {}
    for line in match.group("body").splitlines():
        if not line.strip() or line.lstrip().startswith("#"):
            continue
        if line.startswith((" ", "\t")) or ":" not in line:
            raise RuntimeError(f"frontmatter inválido en {skill_md}")
        key, value = line.split(":", 1)
        key = key.strip()
        if key in fields:
            raise RuntimeError(f"campo duplicado '{key}' en {skill_md}")
        fields[key] = parse_scalar(value, skill_md, key)

    if set(fields) != {"name", "description"}:
        raise RuntimeError(
            f"frontmatter de {skill_md} debe contener name y description"
        )
    name = fields["name"]
    if not NAME_PATTERN.fullmatch(name):
        raise RuntimeError(f"nombre inválido en {skill_md}: {name}")

    marker = " Usar cuando "
    if marker in fields["description"]:
        description, conditions = fields["description"].split(marker, 1)
    else:
        description = fields["description"]
        conditions = fields["description"]
    if not description.strip() or not conditions.strip():
        raise RuntimeError(f"description incompleta en {skill_md}")
    return name, description.strip(), conditions.strip()


def build_index(skills_dir: Path) -> str:
    skills_root = skills_dir.resolve()
    entries: list[tuple[str, str, str, str]] = []
    names: set[str] = set()
    directory_names: list[tuple[str, str]] = []

    for skill_md in sorted(skills_dir.glob("*/SKILL.md")):
        resolved = skill_md.resolve()
        if resolved.parent.parent != skills_root:
            raise RuntimeError(f"la ruta de skill escapa de {skills_dir}: {skill_md}")
        name, description, conditions = read_metadata(skill_md)
        if name in names:
            raise RuntimeError(f"nombre de skill duplicado en el índice: {name}")
        names.add(name)
        directory_names.append((name, skill_md.parent.name))
        entries.append((name, f"skills/{name}/SKILL.md", description, conditions))

    for name, directory in directory_names:
        if directory != name:
            raise RuntimeError(
                f"el nombre '{name}' no coincide con el directorio {directory}"
            )

    lines = [
        "# Índice de skills",
        "",
        "> Archivo generado por `skill-creator`. No editar manualmente.",
        "",
        "| Skill | Ruta | Descripción | Cuándo usarla |",
        "| :--- | :--- | :--- | :--- |",
    ]
    lines.extend(
        f"| `{table_cell(name)}` | `{table_cell(path)}` | "
        f"{table_cell(description)} | {table_cell(conditions)} |"
        for name, path, description, conditions in entries
    )
    return "\n".join(lines) + "\n"


def regenerate_index(skills_dir: Path) -> Path:
    content = build_index(skills_dir)
    index_path = skills_dir / "INDEX.md"
    descriptor, temporary_name = tempfile.mkstemp(prefix=".INDEX-", dir=skills_dir)
    temporary = Path(temporary_name)
    try:
        with os.fdopen(descriptor, "w", encoding="utf-8") as handle:
            handle.write(content)
            handle.flush()
            os.fsync(handle.fileno())
        os.replace(temporary, index_path)
    except Exception:
        temporary.unlink(missing_ok=True)
        raise
    return index_path


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
        raise RuntimeError(
            "SKILL.md no contiene frontmatter válido para el nombre solicitado"
        )


def main() -> int:
    args = parse_args()
    root = args.project_root.resolve()
    agents_path = root / "AGENTS.md"
    skills_dir = root / "skills"
    name = clean_required(args.nombre_skill, "nombre_skill")
    description = clean_required(args.descripcion_skill, "descripcion_skill")
    conditions = clean_required(args.condiciones_de_uso, "condiciones_de_uso")

    if not NAME_PATTERN.fullmatch(name):
        raise ValueError(
            "nombre_skill debe estar en kebab-case y usar solo a-z, 0-9 y guiones"
        )
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
        index_path = regenerate_index(skills_dir)
    except Exception:
        shutil.rmtree(target)
        raise

    print(f"Skill creada correctamente: {target}")
    print(f"Índice de skills regenerado correctamente: {index_path}")
    return 0


if __name__ == "__main__":
    try:
        sys.exit(main())
    except Exception as error:
        print(f"ERROR: {error}", file=sys.stderr)
        sys.exit(1)
