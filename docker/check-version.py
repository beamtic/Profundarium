"""Validate release metadata locally and in CI before building or publishing.

Run from any directory: python3 docker/check-version.py
This reads files and GitHub Actions metadata; it does not bump or publish versions.
"""
import os
from pathlib import Path
import re

# Resolve paths relative to this script, rather than the caller's directory.
root = Path(__file__).resolve().parent.parent
version = (root / "VERSION").read_text().strip()
# Require three numeric components. Each may be zero, but leading zeros such
# as 01 are rejected. Major versions of 1 and higher are allowed.
if not re.fullmatch(r"(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)", version):
    raise SystemExit("VERSION must contain a version such as 0.1.0")
# Check both image defaults: the initializer and PHP runtime must use the
# release recorded in VERSION, so their public files and application code match.
references = re.findall(
    r"image: \$\{PROFUNDARIUM_IMAGE:-ghcr\.io/beamtic/profundarium:([^}]+)\}",
    (root / "compose.example.yml").read_text(),
)
if references != [version, version]:
    raise SystemExit("Both sample Profundarium image defaults must match VERSION")
# A tag-triggered workflow must publish the version declared by that commit.
# Branch and local checks do not require a Git tag.
if os.environ.get("GITHUB_REF_TYPE") == "tag":
    if os.environ.get("GITHUB_REF_NAME") != "v" + version:
        raise SystemExit("Release tag must equal v" + version)
print("Release version verified:", version)
