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
    skill_dir = root / ".agents" / "skills" / directory
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
        index = (self.root / ".agents" / "skills" / "INDEX.md").read_text(encoding="utf-8")
        self.assertIn("`.agents/skills/sample-skill/SKILL.md`", index)
        self.assertFalse((self.root / "skills").exists())
        self.assertIn("`sample-skill`", index)
        self.assertIn("`skill-creator`", index)
        self.assertLess(
            index.index("| `sample-skill` |"), index.index("| `skill-creator` |")
        )
        self.assertIn("Índice de skills regenerado correctamente", result.stdout)

    def test_invalid_existing_frontmatter_rolls_back_new_skill_and_index(self) -> None:
        invalid = self.root / ".agents" / "skills" / "invalid" / "SKILL.md"
        invalid.parent.mkdir()
        invalid.write_text("# Sin frontmatter\n", encoding="utf-8")
        index = self.root / ".agents" / "skills" / "INDEX.md"
        index.write_text("índice anterior\n", encoding="utf-8")

        result = self.run_creator()

        self.assertNotEqual(result.returncode, 0)
        self.assertFalse((self.root / ".agents" / "skills" / "sample-skill").exists())
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
            MODULE.build_index(self.root / ".agents" / "skills")

    def test_symlink_outside_skills_is_rejected(self) -> None:
        outside = self.root / "outside"
        write_skill(outside, "external", "external", "Externa. Usar cuando nunca.")
        (self.root / ".agents" / "skills" / "external").symlink_to(
            outside / ".agents" / "skills" / "external", target_is_directory=True
        )

        with self.assertRaisesRegex(RuntimeError, "escapa"):
            MODULE.build_index(self.root / ".agents" / "skills")

    def run_regeneration(self, *extra: str) -> subprocess.CompletedProcess[str]:
        return subprocess.run(
            [sys.executable, str(SCRIPT), "--project-root", str(self.root),
             "--regenerate-index", *extra],
            check=False, capture_output=True, text=True,
        )

    def test_regeneration_only_is_deterministic_and_does_not_create_skill(self) -> None:
        skills_dir = self.root / ".agents" / "skills"
        before = set(skills_dir.iterdir())
        result = self.run_regeneration()
        self.assertEqual(result.returncode, 0, result.stderr)
        index = skills_dir / "INDEX.md"
        content = index.read_bytes()
        self.assertEqual(set(skills_dir.iterdir()), before | {index})
        self.assertIn(b".agents/skills/skill-creator/SKILL.md", content)
        result = self.run_regeneration()
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertEqual(index.read_bytes(), content)
        self.assertEqual(
            (self.root / "AGENTS.md").read_text(encoding="utf-8"), self.agents_content
        )
        self.assertFalse((self.root / "skills").exists())

    def test_regeneration_failure_preserves_index(self) -> None:
        skills_dir = self.root / ".agents" / "skills"
        index = skills_dir / "INDEX.md"
        index.write_bytes(b"previous index\n")
        (skills_dir / "skill-creator" / "SKILL.md").write_text("invalid", encoding="utf-8")
        result = self.run_regeneration()
        self.assertNotEqual(result.returncode, 0)
        self.assertIn("frontmatter", result.stderr)
        self.assertEqual(index.read_bytes(), b"previous index\n")
        self.assertEqual(sorted(p.name for p in skills_dir.iterdir()),
                         ["INDEX.md", "skill-creator"])

    def test_regeneration_rejects_creation_arguments(self) -> None:
        result = self.run_regeneration("--nombre-skill", "sample-skill")
        self.assertEqual(result.returncode, 2)
        self.assertFalse((self.root / ".agents" / "skills" / "INDEX.md").exists())

    def test_regeneration_requires_project_marker(self) -> None:
        (self.root / "AGENTS.md").unlink()
        result = self.run_regeneration()
        self.assertNotEqual(result.returncode, 0)
        self.assertIn("AGENTS.md", result.stderr)

    def test_regeneration_requires_existing_skills_directory(self) -> None:
        (self.root / ".agents").rename(self.root / "saved-agents")
        result = self.run_regeneration()
        self.assertNotEqual(result.returncode, 0)
        self.assertFalse((self.root / ".agents").exists())


if __name__ == "__main__":
    unittest.main()
