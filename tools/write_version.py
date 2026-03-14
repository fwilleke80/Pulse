from __future__ import annotations

import subprocess
from pathlib import Path


def GetVersionString() -> str:
	try:
		result = subprocess.run(
			["git", "describe", "--tags", "--long", "--dirty"],
			check=True,
			capture_output=True,
			text=True,
		)
		version: str = result.stdout.strip()

		if version == "":
			return "dev"

		return version
	except Exception as e:
		print(f"Error occurred while fetching version: {e}")
		return "dev"


def main() -> None:
	version: str = GetVersionString()

	project_root: Path = Path(__file__).resolve().parent.parent
	output_file: Path = project_root / "config" / "version.php"
	content: str = (
		"<?php\n\n"
		"declare(strict_types=1);\n\n"
		f"return {version!r};\n"
	)

	output_file.write_text(content, encoding="utf-8")
	print(f"Wrote version {version} to {output_file}")


if __name__ == "__main__":
	main()