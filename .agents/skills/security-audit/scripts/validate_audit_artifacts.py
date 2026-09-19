#!/usr/bin/env python3
"""Validate the minimal structured artifacts produced by security-audit."""

from __future__ import annotations

import argparse
import json
import sys
from pathlib import Path
from typing import Any


LEDGER_STATUSES = {"planned", "covered", "candidate", "blocked", "deferred", "out_of_scope"}
VERDICTS = {"confirmed", "needs_validation", "rejected"}
SEVERITIES = {"low", "medium", "high", "critical"}


def read_array(path: Path, label: str) -> list[dict[str, Any]]:
    try:
        data = json.loads(path.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError) as error:
        raise ValueError(f"{label}: could not read valid JSON: {error}") from error
    if not isinstance(data, list):
        raise ValueError(f"{label}: document must be an array")
    if not all(isinstance(item, dict) for item in data):
        raise ValueError(f"{label}: every record must be an object")
    return data


def require_text(record: dict[str, Any], field: str, label: str) -> str:
    value = record.get(field)
    if not isinstance(value, str) or not value.strip():
        raise ValueError(f"{label}: '{field}' must be non-empty text")
    return value


def validate_trace(record: dict[str, Any], label: str) -> None:
    trace = record.get("trace")
    if not isinstance(trace, list) or not trace:
        raise ValueError(f"{label}: 'trace' must be a non-empty array")
    for index, entry in enumerate(trace):
        if not isinstance(entry, dict):
            raise ValueError(f"{label}: trace[{index}] must be an object")
        require_text(entry, "file", f"{label}: trace[{index}]")
        require_text(entry, "description", f"{label}: trace[{index}]")
        line = entry.get("line")
        if type(line) is not int or line < 1:
            raise ValueError(f"{label}: trace[{index}].line must be a positive integer")


def validate_ledger(ledger: list[dict[str, Any]]) -> set[str]:
    coverage_ids: set[str] = set()
    for index, unit in enumerate(ledger):
        label = f"ledger[{index}]"
        coverage_id = require_text(unit, "coverage_id", label)
        if coverage_id in coverage_ids:
            raise ValueError(f"{label}: duplicate coverage_id '{coverage_id}'")
        coverage_ids.add(coverage_id)
        for field in ("surface", "boundary", "module", "attack_class"):
            require_text(unit, field, label)
        status = require_text(unit, "status", label)
        if status not in LEDGER_STATUSES:
            raise ValueError(f"{label}: unsupported status '{status}'")
        if status in {"blocked", "deferred", "out_of_scope"}:
            require_text(unit, "reason", label)
    return coverage_ids


def validate_findings(findings: list[dict[str, Any]], coverage_ids: set[str]) -> set[str]:
    fingerprints: set[str] = set()
    linked_finding_ids: set[str] = set()
    for index, finding in enumerate(findings):
        label = f"findings[{index}]"
        verdict = require_text(finding, "verdict", label)
        if verdict not in VERDICTS:
            raise ValueError(f"{label}: unsupported verdict '{verdict}'")
        fingerprint = require_text(finding, "fingerprint", label)
        if fingerprint in fingerprints:
            raise ValueError(f"{label}: duplicate fingerprint '{fingerprint}'")
        fingerprints.add(fingerprint)
        require_text(finding, "title", label)
        validate_trace(finding, label)
        linked_ids = finding.get("coverage_ids")
        if not isinstance(linked_ids, list) or not linked_ids or not all(isinstance(item, str) for item in linked_ids):
            raise ValueError(f"{label}: coverage_ids must be a non-empty text array")
        unknown = set(linked_ids) - coverage_ids
        if unknown:
            raise ValueError(f"{label}: unknown coverage_ids: {', '.join(sorted(unknown))}")
        linked_finding_ids.update(linked_ids)
        if verdict == "confirmed":
            severity = require_text(finding, "severity", label)
            if severity not in SEVERITIES:
                raise ValueError(f"{label}: unsupported severity '{severity}'")
            require_text(finding, "evidence", label)
            require_text(finding, "remediation", label)
        elif verdict == "needs_validation":
            if "severity" in finding:
                raise ValueError(f"{label}: needs_validation must not include severity")
            blockers = finding.get("blockers")
            if not isinstance(blockers, list) or not blockers or not all(isinstance(item, str) and item.strip() for item in blockers):
                raise ValueError(f"{label}: blockers must be a non-empty text array")
            require_text(finding, "validation_plan", label)
        else:
            require_text(finding, "reason", label)
    return linked_finding_ids


def validate_artifacts(ledger_path: Path, findings_path: Path) -> None:
    ledger = read_array(ledger_path, "coverage-ledger")
    findings = read_array(findings_path, "findings")
    coverage_ids = validate_ledger(ledger)
    linked_finding_ids = validate_findings(findings, coverage_ids)
    for unit in ledger:
        if unit["status"] == "candidate" and unit["coverage_id"] not in linked_finding_ids:
            raise ValueError(
                f"ledger: candidate without linked finding '{unit['coverage_id']}'"
            )


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--ledger", required=True, type=Path)
    parser.add_argument("--findings", required=True, type=Path)
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    try:
        validate_artifacts(args.ledger, args.findings)
    except ValueError as error:
        print(f"ERROR: {error}", file=sys.stderr)
        return 1
    print("security-audit artifacts are valid.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
