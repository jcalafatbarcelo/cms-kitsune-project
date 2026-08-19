from __future__ import annotations

import importlib.util
import sys
import tempfile
import unittest
from pathlib import Path

SCRIPT = Path(__file__).parents[1] / "scripts" / "validate_spec.py"
SPEC = importlib.util.spec_from_file_location("validate_spec", SCRIPT)
MODULE = importlib.util.module_from_spec(SPEC)
assert SPEC.loader is not None
sys.modules[SPEC.name] = MODULE
SPEC.loader.exec_module(MODULE)


def valid_spec(profile: str = "feature", state: str = "Borrador") -> str:
    context = "La planificación seleccionó un incremento vertical verificable."
    requirements = "Las reglas, contratos y bloques aplicables están definidos."
    if profile == "maintenance":
        context = """Fuente del comportamiento esperado: SPEC-languages.md.

Pasos de reproducción: solicitar un idioma inexistente.

Resultado actual: se devuelve un error 500.

Resultado esperado: se devuelve un error 404."""
        requirements = (
            "No cambian las reglas de negocio, el modelo de datos ni el contrato; "
            "se restaura el código HTTP definido."
        )
    return f"""# SPEC: Gestionar idiomas

- **Estado:** {state}
- **Perfil:** {profile}
- **Origen de la planificación:** Issue 42
- **Spec relacionada:** No aplica

## 1. Objetivo o problema

Entregar la gestión acotada de idiomas del CMS.

## 2. Contexto y evidencia

{context}

## 3. Alcance

- Gestionar idiomas activos.

### Fuera de alcance

- Traducción automática.

### Alcance diferido

- No aplica porque el incremento no difiere garantías necesarias.

## 4. *Clash check*

No se detectan conflictos con Specs, ADR ni reglas vigentes.

## 5. Requisitos y bloques técnicos aplicables

{requirements}

## 6. Calidad, seguridad y deuda

### Garantías obligatorias

- Validar y autorizar todas las operaciones.

### Riesgos aceptados

- No aplica.

### Deuda técnica

- No aplica.

## 7. Criterios de aceptación

- **CA-01:** una operación válida devuelve el resultado esperado.
- **CA-02:** una operación no autorizada es rechazada.

## 8. Trazabilidad de pruebas y documentación

| Criterio | Riesgo cubierto | Nivel de prueba | Evidencia esperada | Impacto documental |
| :--- | :--- | :--- | :--- | :--- |
| CA-01 | Persistencia | Integración | Estado persistido | Guía de administración |
| CA-02 | Autorización | HTTP | Respuesta 403 | No aplica; comportamiento interno |

## 9. Plan de implementación

1. Entregar un flujo completo y comprobable de administración.

## 10. Decisiones abiertas

- No aplica.
"""


class SpecValidatorTests(unittest.TestCase):
    def setUp(self) -> None:
        self.temporary = tempfile.TemporaryDirectory()
        self.spec_dir = Path(self.temporary.name) / "docs" / "specs"
        self.spec_dir.mkdir(parents=True)

    def tearDown(self) -> None:
        self.temporary.cleanup()

    def write_spec(self, content: str, name: str = "SPEC-gestionar-idiomas.md") -> Path:
        path = self.spec_dir / name
        path.write_text(content, encoding="utf-8")
        return path

    def test_valid_feature_spec_passes(self) -> None:
        self.assertEqual(MODULE.validate(self.write_spec(valid_spec())), [])

    def test_valid_maintenance_spec_passes(self) -> None:
        self.assertEqual(
            MODULE.validate(self.write_spec(valid_spec(profile="maintenance"))), []
        )

    def test_rejects_unknown_profile_and_missing_section(self) -> None:
        content = valid_spec(profile="initiative").replace(
            "## 4. *Clash check*", "## 4. Revisión informal"
        )

        errors = "\n".join(MODULE.validate(self.write_spec(content)))

        self.assertIn("perfil desconocido", errors)
        self.assertIn("secciones obligatorias", errors)

    def test_rejects_incomplete_maintenance_evidence(self) -> None:
        content = valid_spec(profile="maintenance").replace(
            "Pasos de reproducción:", "Reproducción:"
        )

        errors = "\n".join(MODULE.validate(self.write_spec(content)))

        self.assertIn("Pasos de reproducción", errors)

    def test_rejects_duplicate_or_untraced_criteria(self) -> None:
        content = (
            valid_spec()
            .replace(
                "- **CA-02:** una operación no autorizada es rechazada.",
                "- **CA-01:** una operación no autorizada es rechazada.",
            )
            .replace("| CA-01 | Persistencia", "| CA-03 | Persistencia")
        )

        errors = "\n".join(MODULE.validate(self.write_spec(content)))

        self.assertIn("no pueden duplicarse", errors)
        self.assertIn("falta trazabilidad", errors)

    def test_rejects_placeholders_and_open_decisions_when_approved(self) -> None:
        content = (
            valid_spec(state="Aprobada")
            .replace("- No aplica.\n", "- [Riesgo pendiente].\n", 1)
            .replace(
                "## 10. Decisiones abiertas\n\n- No aplica.",
                "## 10. Decisiones abiertas\n\n- Requiere decisión del usuario.",
            )
        )

        errors = "\n".join(MODULE.validate(self.write_spec(content)))

        self.assertIn("placeholders editoriales", errors)
        self.assertIn("debe cerrar las decisiones abiertas", errors)

    def test_rejects_invalid_filename(self) -> None:
        errors = MODULE.validate(self.write_spec(valid_spec(), "gestionar idiomas.md"))

        self.assertTrue(any("SPEC-nombre-kebab-case" in error for error in errors))


if __name__ == "__main__":
    unittest.main()
