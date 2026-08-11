"""@file write_version.py
@brief Generate config/version.php for a repository checkout or packaged build.
"""

from __future__ import annotations

import os
import re
import subprocess
from pathlib import Path


def GetExistingVersion(output_file: Path) -> str:
	"""@brief Read the packaged fallback version.

	@param output_file Existing PHP version file.
	@return Version string, or ``dev`` when it cannot be read.
	"""
	if not output_file.is_file():
		return "dev"

	match: re.Match[str] | None = re.search(
		r"return\s+['\"]([^'\"]+)['\"]\s*;",
		output_file.read_text(encoding="utf-8"),
	)
	return match.group(1) if match is not None else "dev"


def GetVersionString(project_root: Path, output_file: Path) -> str:
	"""@brief Resolve an explicit, Git-derived, or existing version.

	@param project_root Pulse project root.
	@param output_file Existing generated version file.
	@return Version string.
	"""
	explicit_version: str = os.environ.get("PULSE_VERSION", "").strip()

	if explicit_version != "":
		return explicit_version

	if not (project_root / ".git").exists():
		return GetExistingVersion(output_file)

	try:
		result: subprocess.CompletedProcess[str] = subprocess.run(
			["git", "-C", str(project_root), "describe", "--tags", "--long", "--dirty"],
			check=True,
			capture_output=True,
			text=True,
		)
		version: str = result.stdout.strip()
		return version if version != "" else GetExistingVersion(output_file)
	except (OSError, subprocess.SubprocessError) as exception:
		print(f"Could not derive the Git version: {exception}")
		return GetExistingVersion(output_file)


def main() -> None:
	"""@brief Write the resolved version to config/version.php."""
	project_root: Path = Path(__file__).resolve().parent.parent
	output_file: Path = project_root / "config" / "version.php"
	version: str = GetVersionString(project_root, output_file)
	content: str = (
		"<?php\n\n"
		"/**\n"
		" * @file version.php\n"
		" * @brief Generated Pulse version.\n"
		" */\n\n"
		"declare(strict_types=1);\n\n"
		f"return {version!r};\n"
	)

	output_file.write_text(content, encoding="utf-8")
	print(f"Wrote version {version} to {output_file}")


if __name__ == "__main__":
	main()
