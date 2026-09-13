from __future__ import annotations

import importlib.util
import sys
import tempfile
import unittest
from pathlib import Path

SCRIPT = Path(__file__).parents[1] / "scripts" / "validate_adr.py"
SPEC = importlib.util.spec_from_file_location("validate_adr", SCRIPT)
MODULE = importlib.util.module_from_spec(SPEC)
assert SPEC.loader is not None
sys.modules[SPEC.name] = MODULE
SPEC.loader.exec_module(MODULE)


def valid_adr(number: str = "0001", title: str = "Usar skills para ADR") -> str:
    return f"""# ADR-{number}: {title}

- **Fecha:** 2026-08-14 18:04 UTC
- **Última actualización:** 2026-08-14 18:05 UTC
- **Estado:** Propuesto
- **Autores:** Equipo TFM
- **Reemplaza a:** No aplica
- **Reemplazado por:** No aplica

## Ámbito e impacto transversal

Componentes afectados: documentación técnica, sistema de skills.

Restricción transversal: cualquier ADR del proyecto debe seguir el formato
canónico y las validaciones compartidas.

Fuera de alcance: no modifica código de aplicación ni decisiones funcionales.

## Contexto

El proyecto necesita registrar decisiones arquitectónicas con trazabilidad y
evitar plantillas divergentes.

## Restricciones

- Técnicas: debe funcionar con scripts Python estándar del repositorio.
- Funcionales: debe respetar el flujo SDD del proyecto.
- Temporales: no hay plazo de expiración inmediato.
- Económicas: no introduce servicios externos ni coste adicional.
- Equipo: mantiene un flujo revisable por cualquier agente del proyecto.

## Decisión

Usar una skill de proyecto con plantilla canónica y validación estructural para
los ADR.

## Criterios de decisión

Prioridad alta para consistencia documental, seguridad de no sobrescribir y bajo
coste de mantenimiento.

## Consecuencias positivas

- Los ADR tendrán una estructura verificable y una numeración coherente.

## Consecuencias negativas

- El flujo añade una validación adicional antes de publicar cada decisión.

## Alternativas consideradas

### Mantener plantilla suelta

Descripción: conservar una plantilla en docs sin automatización.

Ventajas: requiere menos archivos de tooling.

Desventajas: permite divergencias y errores de estructura.

Motivo de descarte: no cubre las validaciones exigidas por la gobernanza.

### Documentar solo en Specs

Descripción: registrar decisiones arquitectónicas únicamente en Specs.

Ventajas: reduce el número de artefactos.

Desventajas: mezcla diseño previo con decisiones duraderas ya adoptadas.

Motivo de descarte: no conserva el razonamiento arquitectónico de forma estable.

## Revisión futura

Fecha de revisión o condición observable: 2026-12-31 10:00 UTC.

Evidencia a evaluar: número de ADR creados, errores detectados y cambios de
gobernanza necesarios.
"""


class AdrValidatorTests(unittest.TestCase):
    def setUp(self) -> None:
        self.temporary = tempfile.TemporaryDirectory()
        self.root = Path(self.temporary.name)
        self.adr_dir = self.root / "docs" / "adr"
        self.adr_dir.mkdir(parents=True)

    def tearDown(self) -> None:
        self.temporary.cleanup()

    def write_adr(self, name: str, content: str) -> Path:
        path = self.adr_dir / name
        path.write_text(content, encoding="utf-8")
        return path

    def test_valid_adr_passes(self) -> None:
        path = self.write_adr("ADR-0001-usar-skills-para-adr.md", valid_adr())

        self.assertEqual(MODULE.validate(path), [])

    def test_rejects_invalid_identity_and_timestamp(self) -> None:
        content = valid_adr().replace("# ADR-0001", "# ADR-0002").replace(
            "2026-08-14 18:05 UTC", "2026-08-14 18:03 UTC"
        )
        path = self.write_adr("ADR-0001-usar-skills-para-adr.md", content)

        errors = "\n".join(MODULE.validate(path))

        self.assertIn("encabezado no coincide", errors)
        self.assertIn("anterior a Fecha", errors)

    def test_rejects_placeholders_and_missing_sections(self) -> None:
        content = valid_adr().replace("Equipo TFM", "Autor").replace(
            "## Revisión futura", "## Revision pendiente"
        )
        path = self.write_adr("ADR-0001-usar-skills-para-adr.md", content)

        errors = "\n".join(MODULE.validate(path))

        self.assertIn("Autores debe contener un valor real", errors)
        self.assertIn("secciones obligatorias", errors)

    def test_rejects_insufficient_scope_and_alternatives(self) -> None:
        content = valid_adr().replace(
            "Componentes afectados: documentación técnica, sistema de skills.",
            "Componentes afectados: documentación técnica.",
        ).replace("### Documentar solo en Specs", "#### Documentar solo en Specs")
        path = self.write_adr("ADR-0001-usar-skills-para-adr.md", content)

        errors = "\n".join(MODULE.validate(path))

        self.assertIn("al menos dos componentes", errors)
        self.assertIn("al menos dos opciones", errors)

    def test_rejects_non_reciprocal_replacement(self) -> None:
        previous = valid_adr("0001", "Usar plantilla antigua").replace(
            "- **Estado:** Propuesto", "- **Estado:** Reemplazado"
        )
        current = valid_adr("0002", "Usar skill ADR").replace(
            "- **Estado:** Propuesto", "- **Estado:** Aceptado"
        ).replace(
            "- **Reemplaza a:** No aplica",
            "- **Reemplaza a:** [ADR-0001](ADR-0001-usar-plantilla-antigua.md)",
        )
        self.write_adr("ADR-0001-usar-plantilla-antigua.md", previous)
        path = self.write_adr("ADR-0002-usar-skill-adr.md", current)

        errors = "\n".join(MODULE.validate(path))

        self.assertIn("Reemplaza a no es recíproca", errors)


if __name__ == "__main__":
    unittest.main()
