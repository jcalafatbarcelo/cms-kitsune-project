from __future__ import annotations

import importlib.util
import subprocess
import sys
import tempfile
import unittest
from pathlib import Path

SCRIPT = Path(__file__).parents[1] / "scripts" / "crear_nueva_skill.py"
SPEC = importlib.util.spec_from_file_location("crear_nueva_skill", SCRIPT)
MODULE = importlib.util.module_from_spec(SPEC)
assert SPEC.loader is not None
SPEC.loader.exec_module(MODULE)


def write_skill(root: Path, directory: str, name: str, description: str) -> None:
    skill_dir = root / "skills" / directory
    skill_dir.mkdir(parents=True)
    (skill_dir / "SKILL.md").write_text(
        f'---\nname: {name}\ndescription: "{description}"\n---\n\n# {name}\n',
        encoding="utf-8",
    )


class SkillCreatorTests(unittest.TestCase):
    def setUp(self) -> None:
        self.temporary = tempfile.TemporaryDirectory()
        self.root = Path(self.temporary.name)
        self.agents_content = "# Instrucciones\n\nNo modificar.\n"
        (self.root / "AGENTS.md").write_text(self.agents_content, encoding="utf-8")
        write_skill(
            self.root,
            "skill-creator",
            "skill-creator",
            "Crear skills. Usar cuando se solicite una skill.",
        )

    def tearDown(self) -> None:
        self.temporary.cleanup()

    def run_creator(
        self, name: str = "sample-skill"
    ) -> subprocess.CompletedProcess[str]:
        return subprocess.run(
            [
                sys.executable,
                str(SCRIPT),
                "--project-root",
                str(self.root),
                "--nombre-skill",
                name,
                "--descripcion-skill",
                "Procesar una tarea concreta.",
                "--condiciones-de-uso",
                "el usuario solicite el procesamiento.",
            ],
            check=False,
            capture_output=True,
            text=True,
        )

    def test_creation_updates_index_without_modifying_agents(self) -> None:
        result = self.run_creator()

        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertEqual(
            (self.root / "AGENTS.md").read_text(encoding="utf-8"),
            self.agents_content,
        )
        index = (self.root / "skills" / "INDEX.md").read_text(encoding="utf-8")
        self.assertIn("`sample-skill`", index)
        self.assertIn("`skill-creator`", index)
        self.assertLess(
            index.index("| `sample-skill` |"), index.index("| `skill-creator` |")
        )
        self.assertIn("Índice de skills regenerado correctamente", result.stdout)

    def test_invalid_existing_frontmatter_rolls_back_new_skill_and_index(self) -> None:
        invalid = self.root / "skills" / "invalid" / "SKILL.md"
        invalid.parent.mkdir()
        invalid.write_text("# Sin frontmatter\n", encoding="utf-8")
        index = self.root / "skills" / "INDEX.md"
        index.write_text("índice anterior\n", encoding="utf-8")

        result = self.run_creator()

        self.assertNotEqual(result.returncode, 0)
        self.assertFalse((self.root / "skills" / "sample-skill").exists())
        self.assertEqual(index.read_text(encoding="utf-8"), "índice anterior\n")
        self.assertIn("frontmatter ausente o inválido", result.stderr)

    def test_duplicate_names_are_rejected(self) -> None:
        write_skill(
            self.root,
            "duplicate-directory",
            "skill-creator",
            "Duplicada. Usar cuando nunca.",
        )

        with self.assertRaisesRegex(RuntimeError, "nombre de skill duplicado"):
            MODULE.build_index(self.root / "skills")

    def test_symlink_outside_skills_is_rejected(self) -> None:
        outside = self.root / "outside"
        write_skill(outside, "external", "external", "Externa. Usar cuando nunca.")
        (self.root / "skills" / "external").symlink_to(
            outside / "skills" / "external", target_is_directory=True
        )

        with self.assertRaisesRegex(RuntimeError, "escapa"):
            MODULE.build_index(self.root / "skills")


if __name__ == "__main__":
    unittest.main()
