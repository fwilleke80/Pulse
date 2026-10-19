# Pulse update packages

From Pulse 1.4.2 onward the canonical update manifest is `manifest.json` at
**the ZIP root**. To install 1.4.2 from Pulse 1.4.1, this transition package
also includes `docs/update-manifest.json`: the legacy verifier expects that
second manifest to enumerate all other members, including the root manifest.
Pulse 1.4.2 validates both when present. Beginning with 1.4.3, update packages
can include only root `manifest.json`.

Build a release from a complete source checkout:

```bash
python3 tools/build_update_package.py --root . --output ../Pulse_1.4.2_update.zip --include-legacy-manifest
```

For subsequent versions, omit `--include-legacy-manifest`. The Python 3 script (included in the complete source ZIP) automatically creates
a deterministic ZIP and the matching manifest, with the package format, exact
Pulse version, full file list, uncompressed size and SHA-256 digest for each
member, plus a migration inventory. It excludes private `.env`, uploaded files,
tests, development vendor libraries and git metadata.

The 1.4.2 preflight validates every file hash and migration identity, safe
paths, version compatibility, destination directory permissions, approximate
staging disk space and required PHP extensions. It displays the applicable
`CHANGELOG.md` sections. After approval, the updater repeats these validations.
The PHP process must be able to write application directories and create backup
files. The first 1.4.0-to-1.4.1 installation necessarily runs the older 1.4.0
preflight because the new code has not been installed yet.

**Hashes are not publisher signatures.** The manifest proves completeness
relative to itself, not the identity of the package producer. Administrators
must still obtain ZIP files from a trusted source. Prefer test installations
for the first use of a new update package; back up externally first.

A restore is limited to backups from the exact installed Pulse version. Restore
preflight checks archive integrity, dump syntax, environment key, filesystem and
initial free-space conditions before asking for final administrator approval.
Preflight does not guarantee transactional migration or the success of recovery
under sudden disk failures; keep an independent off-server backup.
