"""Exercise the sample Compose deployment using an already-built PHP image.

Usage: python3 docker/check-compose.py profundarium:ci
Requires Docker Compose and permission to create containers, a network, and a
volume. Pulls the example's Nginx image if needed. It never publishes an image.
"""
import base64
import json
import os
from pathlib import Path
import subprocess
import sys
import tempfile
import urllib.error
import urllib.request
import uuid

root = Path(__file__).resolve().parent.parent
# Select the image passed on the command line and let Docker choose a free
# host port. The deterministic Laravel key is only for these disposable tests.
env = dict(os.environ, PROFUNDARIUM_IMAGE=sys.argv[1], PROFUNDARIUM_HTTP_PORT="0",
           PROFUNDARIUM_APP_KEY="base64:" + base64.b64encode(bytes(32)).decode())
# Create public and forbidden-file fixtures without reading real uploads or
# administrator files. The parent directory must be traversable by Nginx.
with tempfile.TemporaryDirectory(prefix="profundarium-compose-") as directory:
    fixtures = Path(directory) / "files"
    fixtures.mkdir()
    os.chmod(directory, 0o755)
    (fixtures / "example.txt").write_text("mounted file\n")
    (fixtures / "blocked.php").write_text("private")
    (fixtures / ".hidden").write_text("private")
    (fixtures / "outside.txt").symlink_to("/etc/passwd")
    # Compose accepts JSON as YAML. Add fixture mounts without modifying the
    # sample file; both public endpoints should enforce the same restrictions.
    override = Path(directory) / "override.json"
    override.write_text(json.dumps({"services": {"web": {"volumes": [
        f"{fixtures}:/srv/static:ro", f"{fixtures}:/hedgedoc/public/uploads:ro"
    ]}}}))
    # A unique project name keeps test containers, networks, and generated
    # public-file volumes separate from running development deployments.
    command = ["docker", "compose", "-p", "profundarium-test-" + uuid.uuid4().hex[:10],
               "-f", str(root / "compose.example.yml"), "-f", str(override)]

    def compose(*args):
        return subprocess.check_output(command + list(args), env=env, text=True).strip()

    try:
        # Wait for asset initialization, FPM health, and Nginx HTTP health.
        compose("up", "-d", "--wait", "--wait-timeout", "90")
        # Verify the actual runtime identity and kernel restrictions, not just
        # the YAML settings. Neither master process should run as root.
        for service in ("profundarium", "web"):
            status = compose("exec", "-T", service, "cat", "/proc/1/status")
            fields = dict(line.split(":", 1) for line in status.splitlines() if ":" in line)
            assert all(int(uid) != 0 for uid in fields["Uid"].split()), service
            assert int(fields["CapEff"].strip(), 16) == 0, service
            assert int(fields["CapBnd"].strip(), 16) == 0, service
            assert fields["NoNewPrivs"].strip() == "1", service
            print("Verified non-root, zero capabilities, no-new-privileges:", service)

        # The initializer must be non-root too. Exercise a second copy into the
        # same volume, including removal of a stale generated asset.
        compose("run", "--rm", "--no-deps", "--entrypoint", "/bin/sh", "assets-init",
                "-ec", "test $(id -u) != 0; touch /export-public/stale-test.txt")
        compose("run", "--rm", "--no-deps", "assets-init")
        compose("run", "--rm", "--no-deps", "--entrypoint", "/bin/sh", "assets-init",
                "-ec", "test ! -e /export-public/stale-test.txt")
        print("Verified non-root asset initialization and repeat updates")
        port = compose("port", "web", "8080").rsplit(":", 1)[1]
        base = f"http://127.0.0.1:{port}"
        # Exercise Laravel rendering through FastCGI, copied CSS, and /up.
        for path, marker in [("/profundarium/", b"<html"),
                             ("/profundarium/assets/css/notes.css", b""),
                             ("/up", b"")]:
            with urllib.request.urlopen(base + path, timeout=10) as response:
                assert response.status == 200 and marker in response.read(), path
            print("Verified", path)
        # Public files must be readable, while listings, missing files, PHP,
        # hidden files, and symlinks must never return successful responses.
        for prefix in ("/uploads/", "/static/"):
            with urllib.request.urlopen(base + prefix + "example.txt", timeout=10) as response:
                assert response.read() == b"mounted file\n"
            for name in ("", "missing.txt", "blocked.php", ".hidden", "outside.txt"):
                try:
                    urllib.request.urlopen(base + prefix + name, timeout=10)
                except urllib.error.HTTPError as error:
                    assert error.code in (403, 404), (name, error.code)
                else:
                    raise AssertionError("Unexpectedly served " + prefix + name)
            print("Verified mounted files and blocked paths:", prefix)
    except Exception:
        # Preserve useful diagnostics before removing the disposable stack.
        subprocess.run(command + ["logs", "--tail=30"], env=env, check=False)
        raise
    finally:
        # Always clean up, including on failure. --volumes applies only to this
        # uniquely named test project, never an existing deployment.
        subprocess.run(command + ["down", "--volumes"], env=env, check=True)
