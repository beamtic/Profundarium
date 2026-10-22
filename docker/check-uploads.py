"""Verify optional mounted uploads and administrator files are served without exposing private paths."""
import os
from pathlib import Path
import subprocess
import sys
import tempfile
import time

with tempfile.TemporaryDirectory(prefix="profundarium-uploads-") as directory:
    os.chmod(directory, 0o755)
    root = Path(directory)
    (root / "example.txt").write_text("mounted upload\n")
    (root / "blocked.php").write_text("<?php echo 'must not be served';")
    (root / ".hidden").write_text("private")
    (root / "outside.txt").symlink_to("/srv/profundarium/composer.json")
    container = subprocess.check_output([
        "docker", "run", "-d", "--read-only", "--tmpfs", "/tmp:size=64m,mode=1777",
        "--mount", f"type=bind,source={directory},target=/hedgedoc/public/uploads,readonly",
        "--mount", f"type=bind,source={directory},target=/srv/static,readonly",
        sys.argv[1],
    ], text=True).strip()
    try:
        for attempt in range(30):
            ready = subprocess.run(["docker", "exec", container,
                                    "/usr/local/bin/profundarium-healthcheck"],
                                   capture_output=True)
            if ready.returncode == 0:
                break
            time.sleep(0.5)
        else:
            raise RuntimeError("Container did not become healthy")
        for path, expected in (
            ("/uploads/example.txt", b"mounted upload\n"),
            ("/uploads/missing.txt", None),
            ("/uploads/", None),
            ("/uploads/blocked.php", None),
            ("/uploads/.hidden", None),
            ("/uploads/outside.txt", None),
            ("/static/example.txt", b"mounted upload\n"),
            ("/static/missing.txt", None),
            ("/static/", None),
            ("/static/blocked.php", None),
            ("/static/.hidden", None),
            ("/static/outside.txt", None),
            ("/hedgedoc.sqlite", None),
        ):
            result = subprocess.run(["docker", "exec", container, "wget", "-q", "-O", "-",
                                     "http://127.0.0.1:8080" + path], capture_output=True)
            if expected is None:
                assert result.returncode != 0, f"Unexpectedly served {path}"
            else:
                assert result.returncode == 0 and result.stdout == expected, path
            print(f"Verified {path}")
    finally:
        subprocess.run(["docker", "rm", "-f", container], check=True,
                       stdout=subprocess.DEVNULL)
