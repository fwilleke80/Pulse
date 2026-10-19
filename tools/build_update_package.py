"""@file build_update_package.py
@brief Build a deterministic, checksum-manifested Pulse update ZIP.

The canonical manifest lives at the root of the ZIP. No legacy manifest is generated.
"""

from __future__ import annotations

import argparse
import hashlib
import json
import re
import zipfile
from pathlib import Path
from typing import Any


ROOT_FILES: frozenset[str] = frozenset(
    {
        "bootstrap.php", "composer.json", "README.md", "CHANGELOG.md",
        ".htaccess", ".env.example", ".gitignore", ".editorconfig",
        ".php-cs-fixer.dist.php", "phpstan.neon.dist", "phpunit.xml",
    }
)
ALLOWED_PREFIXES: tuple[str, ...] = (
    "app/", "config/", "database/", "docs/", "public/", "tools/",
)
MANIFEST_PATH: str = "manifest.json"


def AllowedPackageFile(path: str) -> bool:
    """@brief Mirror the Pulse 1.4.0 accepted release file policy.

    @param path Relative POSIX-style archive path.
    @return Whether the installed 1.4.0 updater accepts this file.
    """
    if path == "docs/update-manifest.json":
        return False
    if path in ROOT_FILES or path in ("manifest.json", "public/install.php", "storage/.htaccess"):
        return True
    return (
        path.startswith(ALLOWED_PREFIXES)
        and re.fullmatch(r"[a-zA-Z0-9_. /+\-]+", path) is not None
        and all(part not in ("", ".", "..") for part in path.split("/"))
        and not path.startswith(("tests/", "vendor/", ".git/"))
    )


def Build(root: Path, output: Path) -> dict[str, Any]:
    """@brief Package all allowed source files plus a stable SHA-256 inventory.

    @param root Checked-out source root.
    @param output Output ZIP path (outside the source root recommended).
    @return Canonical manifest dictionary embedded in the ZIP.
    """
    match: re.Match[str] | None = re.search(
        r"return\s+['\"](\d+\.\d+\.\d+)['\"]\s*;",
        (root / "config/version.php").read_text(encoding="utf-8"),
    )
    if match is None:
        raise ValueError("config/version.php lacks a release version")

    file_paths: list[Path] = []
    for source in root.rglob("*"):
        if (not source.is_file() or source.is_symlink() or source == output
            or "__pycache__" in source.parts or source.suffix == ".pyc"):
            continue
        relative: str = source.relative_to(root).as_posix()
        if relative in (MANIFEST_PATH, "docs/update-manifest.json") or relative.startswith((".git/", "vendor/", "tests/", "storage/")):
            continue
        if AllowedPackageFile(relative) and (
            relative in ROOT_FILES
            or relative.startswith(ALLOWED_PREFIXES)
            or relative == "public/install.php"
        ):
            file_paths.append(source)

    file_paths.sort(key=lambda source: source.relative_to(root).as_posix())
    required: set[str] = {"bootstrap.php", "public/index.php", "config/version.php", "database/schema.sql", "CHANGELOG.md"}
    paths: set[str] = {source.relative_to(root).as_posix() for source in file_paths}
    if not required.issubset(paths):
        raise ValueError(f"Package is incomplete: {sorted(required - paths)}")

    files: dict[str, dict[str, Any]] = {}
    migrations: dict[str, str] = {}
    for source in file_paths:
        relative = source.relative_to(root).as_posix()
        raw: bytes = source.read_bytes()
        digest: str = hashlib.sha256(raw).hexdigest()
        files[relative] = {"size": len(raw), "sha256": digest}
        if relative.startswith("database/migrations/") and relative.endswith(".sql"):
            migrations[relative] = digest

    manifest: dict[str, Any] = {
        "format": "pulse-update-v1",
        "version": match.group(1),
        "files": files,
        "migrations": dict(sorted(migrations.items())),
    }
    canonical_content: bytes = (json.dumps(manifest, ensure_ascii=False, indent=2) + "\n").encode("utf-8")
    output.parent.mkdir(parents=True, exist_ok=True)
    with zipfile.ZipFile(output, "w", compression=zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
        for source in file_paths:
            relative = source.relative_to(root).as_posix()
            info = zipfile.ZipInfo(relative, (1980, 1, 1, 0, 0, 0))
            info.compress_type = zipfile.ZIP_DEFLATED
            info.external_attr = 0o100644 << 16
            archive.writestr(info, source.read_bytes(), compress_type=zipfile.ZIP_DEFLATED, compresslevel=9)
        info = zipfile.ZipInfo(MANIFEST_PATH, (1980, 1, 1, 0, 0, 0))
        info.compress_type = zipfile.ZIP_DEFLATED
        info.external_attr = 0o100644 << 16
        archive.writestr(info, canonical_content)
    return manifest


def main() -> None:
    """@brief Parse command-line arguments and build a release update ZIP."""
    parser: argparse.ArgumentParser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--root", type=Path, default=Path(__file__).resolve().parent.parent)
    parser.add_argument("--output", required=True, type=Path)
    args: argparse.Namespace = parser.parse_args()
    manifest: dict[str, Any] = Build(args.root.resolve(), args.output.resolve())
    print(f"Created {args.output}: Pulse {manifest['version']}, {len(manifest['files'])} files, {len(manifest['migrations'])} migrations")


if __name__ == "__main__":
    main()
