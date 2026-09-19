from __future__ import annotations

import importlib.util
import json
import tempfile
import unittest
from pathlib import Path


SCRIPT = Path(__file__).parents[1] / "scripts" / "validate_audit_artifacts.py"
SPEC = importlib.util.spec_from_file_location("validate_audit_artifacts", SCRIPT)
MODULE = importlib.util.module_from_spec(SPEC)
assert SPEC.loader is not None
SPEC.loader.exec_module(MODULE)


class ValidateAuditArtifactsTests(unittest.TestCase):
    def write_artifacts(self, ledger: list[dict], findings: list[dict]) -> tuple[Path, Path]:
        temporary = tempfile.TemporaryDirectory()
        self.addCleanup(temporary.cleanup)
        root = Path(temporary.name)
        ledger_path = root / "coverage-ledger.json"
        findings_path = root / "findings.json"
        ledger_path.write_text(json.dumps(ledger), encoding="utf-8")
        findings_path.write_text(json.dumps(findings), encoding="utf-8")
        return ledger_path, findings_path

    def valid_ledger(self) -> list[dict]:
        return [{
            "coverage_id": "routes-web-post-pages::access-control",
            "surface": "POST /pages",
            "boundary": "page ownership",
            "module": "Modules/Pages",
            "attack_class": "access-control",
            "status": "candidate",
        }]

    def confirmed_finding(self) -> dict:
        return {
            "verdict": "confirmed",
            "fingerprint": "pages.create.owner-scope",
            "title": "Page creation bypasses owner scope",
            "coverage_ids": ["routes-web-post-pages::access-control"],
            "trace": [{"file": "Modules/Pages/app/Actions/CreatePage.php", "line": 42, "description": "Owner comes from request"}],
            "severity": "high",
            "evidence": "A different owner identifier reaches the persisted record.",
            "remediation": "Derive owner from the authenticated principal.",
        }

    def test_accepts_linked_confirmed_finding(self) -> None:
        ledger_path, findings_path = self.write_artifacts(
            self.valid_ledger(), [self.confirmed_finding()]
        )

        MODULE.validate_artifacts(ledger_path, findings_path)

    def test_rejects_unknown_coverage_reference(self) -> None:
        finding = self.confirmed_finding()
        finding["coverage_ids"] = ["unknown"]
        ledger_path, findings_path = self.write_artifacts(self.valid_ledger(), [finding])

        with self.assertRaisesRegex(ValueError, "unknown coverage_ids"):
            MODULE.validate_artifacts(ledger_path, findings_path)

    def test_rejects_boolean_trace_line(self) -> None:
        finding = self.confirmed_finding()
        finding["trace"][0]["line"] = True
        ledger_path, findings_path = self.write_artifacts(self.valid_ledger(), [finding])

        with self.assertRaisesRegex(ValueError, "line must be a positive integer"):
            MODULE.validate_artifacts(ledger_path, findings_path)

    def test_rejects_candidate_without_finding(self) -> None:
        ledger_path, findings_path = self.write_artifacts(self.valid_ledger(), [])

        with self.assertRaisesRegex(ValueError, "candidate without linked finding"):
            MODULE.validate_artifacts(ledger_path, findings_path)

    def test_accepts_candidate_with_linked_rejected_finding(self) -> None:
        finding = self.confirmed_finding()
        finding.update({
            "verdict": "rejected",
            "reason": "The policy enforces the owner scope.",
        })
        finding.pop("severity")
        finding.pop("evidence")
        finding.pop("remediation")
        ledger_path, findings_path = self.write_artifacts(self.valid_ledger(), [finding])

        MODULE.validate_artifacts(ledger_path, findings_path)

    def test_rejects_severity_for_needs_validation(self) -> None:
        finding = self.confirmed_finding()
        finding.update({
            "verdict": "needs_validation",
            "severity": "high",
            "blockers": ["The CDN policy is not versioned."],
            "validation_plan": "Inspect the deployed cache policy.",
        })
        finding.pop("evidence")
        finding.pop("remediation")
        ledger_path, findings_path = self.write_artifacts(self.valid_ledger(), [finding])

        with self.assertRaisesRegex(ValueError, "needs_validation must not include severity"):
            MODULE.validate_artifacts(ledger_path, findings_path)


if __name__ == "__main__":
    unittest.main()
