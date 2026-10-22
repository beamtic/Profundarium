"""Check Docker stop and fail-fast behavior of the built image (no host ports)."""
import subprocess
import sys
import time

image = sys.argv[1]


def docker(*args):
    return subprocess.check_output(["docker", *args], text=True).strip()


for failure in (None, "nginx", "php-fpm"):
    container = docker("run", "-d", "--read-only", "--tmpfs",
                       "/tmp:size=64m,mode=1777", image)
    try:
        for attempt in range(30):
            result = subprocess.run(
                ["docker", "exec", container, "/usr/local/bin/profundarium-healthcheck"],
                stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL,
            )
            if result.returncode == 0:
                break
            time.sleep(0.5)
        else:
            raise RuntimeError("HTTP health check never became ready")

        if failure:
            docker("exec", container, "s6-svc", "-k",
                   f"/tmp/profundarium-services/{failure}")
        else:
            docker("stop", "--time", "10", container)
        result = subprocess.run(["docker", "wait", container], text=True,
                                capture_output=True, timeout=15, check=True)
        expected = "1" if failure else "0"
        assert result.stdout.strip() == expected, result.stdout
        print(f"{failure or 'graceful stop'}: exit {expected}")
    except Exception:
        subprocess.run(["docker", "logs", container], check=False)
        raise
    finally:
        # Remove only the temporary container created by this test.
        subprocess.run(["docker", "rm", "-f", container], check=True,
                       stdout=subprocess.DEVNULL)
