#!/usr/bin/env python3
"""Valida la estructura determinista de una Spec del proyecto."""

from __future__ import annotations

import argparse
import re
import sys
from pathlib import Path

FILE_PATTERN = re.compile(r"SPEC-[a-z0-9]+(?:-[a-z0-9]+)*\.md\Z")
HEADER_PATTERN = re.compile(r"\A# SPEC: (?P<title>.+)\n")
VALID_STATES = {
    "Borrador",
    "Propuesta",
    "Aprobada",
    "Completada",
    "Rechazada",
    "Reemplazada",
}
VALID_PROFILES = {"feature", "maintenance"}
APPROVAL_STATES = {"Aprobada", "Completada"}
REQUIRED_METADATA = [
    "Estado",
    "Perfil",
    "Origen de la planificación",
    "Spec relacionada",
]
REQUIRED_SECTIONS = [
    "Objetivo o problema",
    "Contexto y evidencia",
    "Alcance",
    "*Clash check*",
    "Requisitos y bloques técnicos aplicables",
    "Calidad, seguridad y deuda",
    "Criterios de aceptación",
    "Trazabilidad de pruebas y documentación",
    "Plan de implementación",
    "Decisiones abiertas",
]
FORBIDDEN_TEXT = {"TODO", "TBD"}
PLACEHOLDER_PATTERN = re.compile(r"\[[^\]\n]+\](?!\()")
CRITERION_PATTERN = re.compile(r"^- \*\*(CA-(?P<number>\d{2,})):?\*\*", re.MULTILINE)


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("spec_path", type=Path)
    return parser.parse_args()


def parse_metadata(content: str) -> dict[str, str]:
    metadata: dict[str, str] = {}
    for line in content.splitlines()[1:20]:
        match = re.fullmatch(r"- \*\*(?P<key>[^*]+):\*\* (?P<value>.+)", line)
        if match:
            metadata[match.group("key")] = match.group("value").strip()
        elif line.startswith("## "):
            break
    return metadata


def section_ranges(content: str, errors: list[str]) -> dict[str, str]:
    headings = [
        (match.group("title"), match.start())
        for match in re.finditer(
            r"^## (?:\d+\. )?(?P<title>.+)$", content, re.MULTILINE
        )
    ]
    actual = [heading for heading, _ in headings]
    if actual != REQUIRED_SECTIONS:
        errors.append(
            "las secciones obligatorias están ausentes, duplicadas o fuera del orden canónico"
        )

    ranges: dict[str, str] = {}
    for index, (heading, start) in enumerate(headings):
        if heading not in REQUIRED_SECTIONS:
            continue
        end = headings[index + 1][1] if index + 1 < len(headings) else len(content)
        section = content[start:end]
        ranges[heading] = section.split("\n", 1)[1].strip() if "\n" in section else ""
    for heading in REQUIRED_SECTIONS:
        if not ranges.get(heading):
            errors.append(f"la sección '{heading}' está ausente o vacía")
    return ranges


def validate_metadata(metadata: dict[str, str], errors: list[str]) -> tuple[str, str]:
    missing = [key for key in REQUIRED_METADATA if key not in metadata]
    if missing:
        errors.append(f"faltan metadatos obligatorios: {', '.join(missing)}")

    state = metadata.get("Estado", "")
    profile = metadata.get("Perfil", "")
    if state not in VALID_STATES:
        errors.append(f"estado desconocido: {state}")
    if profile not in VALID_PROFILES:
        errors.append(f"perfil desconocido: {profile}")
    return state, profile


def validate_criteria(
    content: str, sections: dict[str, str], errors: list[str]
) -> set[str]:
    criteria_section = sections.get("Criterios de aceptación", "")
    matches = list(CRITERION_PATTERN.finditer(criteria_section))
    criteria = [match.group(1) for match in matches]
    if not criteria:
        errors.append("debe existir al menos un criterio con identificador CA-NN")
        return set()
    if len(criteria) != len(set(criteria)):
        errors.append(
            "los identificadores de criterios de aceptación no pueden duplicarse"
        )

    expected = [f"CA-{number:02d}" for number in range(1, len(criteria) + 1)]
    if criteria != expected:
        errors.append("los criterios deben numerarse consecutivamente desde CA-01")

    traceability = sections.get("Trazabilidad de pruebas y documentación", "")
    for criterion in criteria:
        row_pattern = rf"^\|\s*{re.escape(criterion)}\s*\|"
        if not re.search(row_pattern, traceability, re.MULTILINE):
            errors.append(f"falta trazabilidad para {criterion}")
    return set(criteria)


def validate_profile(profile: str, sections: dict[str, str], errors: list[str]) -> None:
    context = sections.get("Contexto y evidencia", "")
    requirements = sections.get("Requisitos y bloques técnicos aplicables", "")
    if profile == "maintenance":
        required_labels = [
            "Fuente del comportamiento esperado:",
            "Pasos de reproducción:",
            "Resultado actual:",
            "Resultado esperado:",
        ]
        for label in required_labels:
            if label not in context:
                errors.append(f"el perfil maintenance debe incluir '{label}'")
        if not re.search(
            r"(No cambian|Sin cambios en).*(reglas|modelo de datos|contrato)",
            requirements,
            re.IGNORECASE | re.DOTALL,
        ):
            errors.append(
                "el perfil maintenance debe confirmar que no cambia reglas, modelo de datos ni contrato"
            )


def validate_approval(
    state: str, content: str, sections: dict[str, str], errors: list[str]
) -> None:
    if state not in APPROVAL_STATES:
        return
    for forbidden in FORBIDDEN_TEXT:
        if re.search(rf"\b{forbidden}\b", content):
            errors.append(f"una Spec {state} no puede contener {forbidden}")
    if PLACEHOLDER_PATTERN.search(content):
        errors.append(f"una Spec {state} no puede contener placeholders editoriales")
    decisions = sections.get("Decisiones abiertas", "")
    if not re.search(r"\bNo aplica\b", decisions, re.IGNORECASE):
        errors.append(
            f"una Spec {state} debe cerrar las decisiones abiertas con 'No aplica'"
        )


def validate(path: Path) -> list[str]:
    errors: list[str] = []
    if not path.is_file():
        return [f"no existe la Spec: {path}"]
    if not FILE_PATTERN.fullmatch(path.name):
        errors.append("el nombre del archivo no sigue SPEC-nombre-kebab-case.md")

    content = path.read_text(encoding="utf-8")
    header = HEADER_PATTERN.match(content)
    if not header or not header.group("title").strip():
        errors.append("el encabezado no sigue '# SPEC: Título descriptivo'")

    h1_count = len(re.findall(r"^# ", content, re.MULTILINE))
    if h1_count != 1:
        errors.append("la Spec debe contener un único H1")

    metadata = parse_metadata(content)
    state, profile = validate_metadata(metadata, errors)
    sections = section_ranges(content, errors)
    validate_criteria(content, sections, errors)
    validate_profile(profile, sections, errors)
    validate_approval(state, content, sections, errors)
    return errors


def main() -> int:
    args = parse_args()
    errors = validate(args.spec_path)
    if errors:
        print("Spec inválida:", file=sys.stderr)
        for error in errors:
            print(f"- {error}", file=sys.stderr)
        return 1
    print(f"Spec válida: {args.spec_path}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
