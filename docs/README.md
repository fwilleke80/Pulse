# Pulse documentation

The primary project overview and installation instructions are in the root [README](../README.md).

Detailed documents:

- [Architecture](ARCHITECTURE.md)
- [User guide](USER_GUIDE.md)
- [Monitor seriousness tutorial](MONITOR_TUTORIAL.md)
- [Security model](SECURITY.md)
- [Upgrade guide](UPGRADING.md)
- [Changelog](../CHANGELOG.md)

Pulse 0.7.0 sends actual immutable recipient message emails and optionally asks one or more safety contacts for an explicit, scanner-safe confirmation before that release. Each recipient now has a dedicated monitor page for personal wording, future document assignments, localized preview, and delivery history. Documents remain inaccessible to recipients until a later secure portal release, and sensitive content is still unencrypted at rest.

Before uploading PHP files for any deployment, run `python3 tools/write_version.py` in the project root and include the generated `config/version.php`. If the generated file is absent, Pulse stays available and displays **version unavailable**, but deployments should generate it so the release and asset cache keys are accurate.
