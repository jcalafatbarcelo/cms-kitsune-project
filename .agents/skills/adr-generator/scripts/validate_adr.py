#!/usr/bin/env python3
"""Valida la estructura determinista de un ADR del proyecto."""

from __future__ import annotations

import argparse
import re
import sys
from dataclasses import dataclass
from datetime import datetime
from pathlib import Path

ADR_FILE_PATTERN = re.compile(r"ADR-(?P<number>\d{4})-(?P<slug>[a-z0-9]+(?:-[a-z0-9]+)*)\.md\Z")
ADR_HEADER_PATTERN = re.compile(r"\A# ADR-(?P<number>\d{4}): (?P<title>.+)\n")
TIMESTAMP_FORMAT = "%Y-%m-%d %H:%M UTC"
VALID_STATES = {"Propuesto", "Aceptado", "Rechazado", "Obsoleto", "Reemplazado"}
TERMINAL_STATES = {"Rechazado", "Obsoleto", "Reemplazado"}
REQUIRED_METADATA = [
    "Fecha",
    "Última actualización",
    "Estado",
    "Autores",
    "Reemplaza a",
    "Reemplazado por",
]
REQUIRED_SECTIONS = [
    "Ámbito e impacto transversal",
    "Contexto",
    "Restricciones",
    "Decisión",
    "Criterios de decisión",
    "Consecuencias positivas",
    "Consecuencias negativas",
    "Alternativas consideradas",
    "Revisión futura",
]
FORBIDDEN_TEXT = {
    "implicaicones",
    "implicaoens",
    "edbería",
    "TODO",
    "TBD",
}
GENERIC_VALUES = {"Autor", "Título", "Alternativa 1", "Alternativa 2", "Nombre Apellido"}


class AdrValidationError(RuntimeError):
    """Error agregado de validación ADR."""


@dataclass(frozen=True)
class AdrDocument:
    path: Path
    content: str
    number: str
    metadata: dict[str, str]


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("adr_path", type=Path)
    return parser.parse_args()


def parse_timestamp(value: str, field: str, errors: list[str]) -> datetime | None:
    if not re.fullmatch(r"\d{4}-\d{2}-\d{2} \d{2}:\d{2} UTC", value):
        errors.append(f"{field} no sigue YYYY-MM-DD HH:mm UTC")
        return None
    try:
        return datetime.strptime(value, TIMESTAMP_FORMAT)
    except ValueError:
        errors.append(f"{field} contiene una fecha imposible")
        return None


def parse_metadata(content: str) -> dict[str, str]:
    metadata: dict[str, str] = {}
    for line in content.splitlines()[1:20]:
        match = re.fullmatch(r"- \*\*(?P<key>[^*]+):\*\* (?P<value>.+)", line)
        if match:
            metadata[match.group("key")] = match.group("value").strip()
        elif line.startswith("## "):
            break
    return metadata


def read_adr(path: Path, errors: list[str] | None = None) -> AdrDocument:
    content = path.read_text(encoding="utf-8")
    local_errors: list[str] = []
    file_match = ADR_FILE_PATTERN.fullmatch(path.name)
    if not file_match:
        local_errors.append("el nombre del archivo no sigue ADR-NNNN-titulo-kebab-case.md")
        number = "0000"
    else:
        number = file_match.group("number")

    header_match = ADR_HEADER_PATTERN.match(content)
    if not header_match:
        local_errors.append("el encabezado no sigue '# ADR-NNNN: Título descriptivo'")
    elif header_match.group("number") != number:
        local_errors.append("el identificador del encabezado no coincide con el archivo")

    metadata = parse_metadata(content)
    missing = [key for key in REQUIRED_METADATA if key not in metadata]
    if missing:
        local_errors.append(f"faltan metadatos obligatorios: {', '.join(missing)}")

    if errors is not None:
        errors.extend(local_errors)
    elif local_errors:
        raise AdrValidationError("\n".join(local_errors))

    return AdrDocument(path=path, content=content, number=number, metadata=metadata)


def section_ranges(content: str, errors: list[str]) -> dict[str, str]:
    headings = [(match.group(1), match.start()) for match in re.finditer(r"^## (.+)$", content, re.MULTILINE)]
    actual = [heading for heading, _ in headings]
    if actual[: len(REQUIRED_SECTIONS)] != REQUIRED_SECTIONS:
        errors.append("las secciones obligatorias están ausentes o fuera del orden canónico")

    ranges: dict[str, str] = {}
    for index, (heading, start) in enumerate(headings):
        if heading not in REQUIRED_SECTIONS:
            continue
        end = headings[index + 1][1] if index + 1 < len(headings) else len(content)
        body = content[start:end].split("\n", 1)[1] if "\n" in content[start:end] else ""
        ranges[heading] = body.strip()
    for heading in REQUIRED_SECTIONS:
        if not ranges.get(heading):
            errors.append(f"la sección '{heading}' está ausente o vacía")
    return ranges


def linked_paths(value: str, base: Path) -> list[Path]:
    if value == "No aplica":
        return []
    paths: list[Path] = []
    for link in re.finditer(r"\[[^\]]+\]\(([^)]+)\)", value):
        target = link.group(1)
        if target.startswith(("http://", "https://")):
            continue
        paths.append((base / target).resolve())
    if not paths and value != "No aplica":
        paths.append((base / value).resolve())
    return paths


def validate_identity(path: Path, document: AdrDocument, errors: list[str]) -> None:
    adr_dir = path.parent
    numbers: dict[str, list[str]] = {}
    for candidate in adr_dir.glob("ADR-*.md"):
        file_match = ADR_FILE_PATTERN.fullmatch(candidate.name)
        if file_match:
            numbers.setdefault(file_match.group("number"), []).append(candidate.name)
        try:
            header_match = ADR_HEADER_PATTERN.match(candidate.read_text(encoding="utf-8"))
        except UnicodeDecodeError:
            continue
        if header_match:
            numbers.setdefault(header_match.group("number"), []).append(candidate.name)
    duplicates = {number: names for number, names in numbers.items() if len(names) > 2}
    if duplicates:
        errors.append("existen identificadores ADR duplicados")
    if document.number == "0000":
        return
    for number, names in numbers.items():
        if number == document.number and len(set(names)) > 1:
            errors.append(f"el identificador ADR-{number} está duplicado")


def validate_metadata(document: AdrDocument, errors: list[str]) -> None:
    metadata = document.metadata
    created = parse_timestamp(metadata.get("Fecha", ""), "Fecha", errors)
    updated = parse_timestamp(metadata.get("Última actualización", ""), "Última actualización", errors)
    if created and updated and updated < created:
        errors.append("Última actualización no puede ser anterior a Fecha")

    state = metadata.get("Estado", "")
    if state not in VALID_STATES:
        errors.append(f"estado desconocido: {state}")
    if not metadata.get("Autores") or metadata.get("Autores") in GENERIC_VALUES:
        errors.append("Autores debe contener un valor real")


def validate_replacements(document: AdrDocument, errors: list[str]) -> None:
    state = document.metadata.get("Estado", "")
    base = document.path.parent
    replaces = linked_paths(document.metadata.get("Reemplaza a", ""), base)
    replaced_by = linked_paths(document.metadata.get("Reemplazado por", ""), base)

    all_targets = replaces + replaced_by
    if any(target == document.path.resolve() for target in all_targets):
        errors.append("un ADR no puede reemplazarse a sí mismo")
    missing = [target for target in all_targets if not target.is_file()]
    if missing:
        errors.append("la relación de reemplazo apunta a un ADR inexistente")

    if state == "Reemplazado" and not replaced_by:
        errors.append("un ADR Reemplazado debe informar Reemplazado por")
    if state != "Reemplazado" and replaced_by:
        errors.append("Reemplazado por solo aplica al estado Reemplazado")
    if replaces and state != "Aceptado":
        errors.append("solo un ADR Aceptado puede reemplazar decisiones previas")
    if state in TERMINAL_STATES and replaces:
        errors.append("un estado terminal no puede iniciar nuevos reemplazos")

    for target in all_targets:
        if not target.is_file():
            continue
        target_doc = read_adr(target)
        target_replaces = linked_paths(target_doc.metadata.get("Reemplaza a", ""), target.parent)
        target_replaced_by = linked_paths(target_doc.metadata.get("Reemplazado por", ""), target.parent)
        if target in replaces and document.path.resolve() not in target_replaced_by:
            errors.append("la relación Reemplaza a no es recíproca")
        if target in replaced_by and document.path.resolve() not in target_replaces:
            errors.append("la relación Reemplazado por no es recíproca")
        if document.path.resolve() in target_replaces and target in replaces:
            errors.append("la relación de reemplazo es circular")


def validate_placeholders(content: str, errors: list[str]) -> None:
    for forbidden in FORBIDDEN_TEXT:
        if forbidden in content:
            errors.append(f"texto prohibido o errata pendiente: {forbidden}")
    for generic in GENERIC_VALUES:
        if re.search(rf"(?<!\w){re.escape(generic)}(?!\w)", content):
            errors.append(f"placeholder genérico sin completar: {generic}")
    if re.search(r"\[[^\]]+\](?!\()", content):
        errors.append("hay texto entre corchetes sin completar")


def validate_scope(section: str, errors: list[str]) -> None:
    match = re.search(r"Componentes afectados:\s*(.+)", section)
    if not match:
        errors.append("el ámbito debe declarar Componentes afectados")
    else:
        components = [item.strip() for item in match.group(1).split(",") if item.strip()]
        if len(components) < 2:
            errors.append("el ámbito debe identificar al menos dos componentes afectados")
    if not re.search(r"(Restricción transversal|Impacto transversal):\s*(?!No aplica)", section):
        errors.append("el ámbito debe justificar explícitamente la restricción transversal")


def validate_consequences(name: str, section: str, errors: list[str]) -> None:
    bullets = [line for line in section.splitlines() if line.startswith("- ") and len(line) > 25]
    if not bullets:
        errors.append(f"{name} debe incluir al menos una consecuencia concreta")


def validate_alternatives(section: str, errors: list[str]) -> None:
    alternatives = re.split(r"^### .+$", section, flags=re.MULTILINE)[1:]
    if len(alternatives) < 2:
        errors.append("Alternativas consideradas debe incluir al menos dos opciones")
        return
    for alternative in alternatives:
        for label in ("Descripción:", "Ventajas:", "Desventajas:", "Motivo de descarte:"):
            if label not in alternative:
                errors.append(f"cada alternativa debe contener {label}")


def validate_review(section: str, errors: list[str]) -> None:
    has_date = re.search(r"\d{4}-\d{2}-\d{2} \d{2}:\d{2} UTC", section)
    has_condition = "Condición observable:" in section or "condición observable:" in section
    if not has_date and not has_condition:
        errors.append("Revisión futura debe incluir una fecha UTC o condición observable")
    if "Evidencia a evaluar:" not in section:
        errors.append("Revisión futura debe indicar Evidencia a evaluar")


def validate(path: Path) -> list[str]:
    errors: list[str] = []
    if not path.is_file():
        return [f"no existe el ADR: {path}"]
    document = read_adr(path.resolve(), errors)
    validate_identity(path.resolve(), document, errors)
    validate_metadata(document, errors)
    validate_replacements(document, errors)
    validate_placeholders(document.content, errors)
    sections = section_ranges(document.content, errors)
    validate_scope(sections.get("Ámbito e impacto transversal", ""), errors)
    validate_consequences("Consecuencias positivas", sections.get("Consecuencias positivas", ""), errors)
    validate_consequences("Consecuencias negativas", sections.get("Consecuencias negativas", ""), errors)
    validate_alternatives(sections.get("Alternativas consideradas", ""), errors)
    validate_review(sections.get("Revisión futura", ""), errors)
    return errors


def main() -> int:
    args = parse_args()
    errors = validate(args.adr_path)
    if errors:
        print("ADR inválido:", file=sys.stderr)
        for error in errors:
            print(f"- {error}", file=sys.stderr)
        return 1
    print(f"ADR válido: {args.adr_path}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
