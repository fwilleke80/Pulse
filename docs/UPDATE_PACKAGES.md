# Pulse update packages

Starting with Pulse 1.4.3, update packages MUST contain a root-level
`manifest.json` with a complete SHA-256 inventory of every other package member.
`docs/update-manifest.json` is no longer accepted. The Pulse 1.4.2 updater
supports root-only manifests and can install 1.4.3 directly. Older installations
must upgrade through 1.4.2 before using root-only update packages.

Build a release from a complete source checkout:

```bash
python3 tools/build_update_package.py --root . --output ../Pulse_1.4.3_update.zip
```

The Python 3 builder (included in the complete source ZIP) automatically creates
a deterministic ZIP and matching root-level manifest, with the package format,
Pulse version, complete file inventory, uncompressed size and SHA-256 hash for
all members, plus exact migration checksums. It excludes `.env`, private uploads,
tests, development vendor libraries and Git metadata.

The 1.4.3 preflight validates every file hash and migration identity, safe
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
