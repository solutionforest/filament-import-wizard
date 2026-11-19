# Security policy

## Reporting a vulnerability

Please **do not open a public issue** for a security problem. Report it privately to the Solution Forest maintainers (GitHub: *Security → Report a vulnerability* on this repository, or your usual
internal security contact) with the affected version, what an attacker can do and, if possible, a minimal reproduction. We aim to acknowledge reports within 3 working days.

## Supported versions

Only the latest `0.x` minor release receives security fixes — see the table in the [README](README.md#supported-versions).

## What is already covered

The wizard keeps its state in locked Livewire properties, escapes everything it prints, confines uploads to a per-panel directory with an allow-list of types, only forwards importer-declared
options, and guards CSV exports against formula injection. These are pinned by the `security` test group (`composer security`) and the dependency audit in CI. See [Testing](docs/testing.md).
