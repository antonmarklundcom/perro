"""Build a Hostinger code-only ZIP from a clean committed revision, never storage data."""
import hashlib
import pathlib
import subprocess
import sys
import zipfile

root = pathlib.Path(__file__).resolve().parents[1]
if len(sys.argv) != 2:
    raise SystemExit("Usage: python tools/build-release.py C:/AI-work/perro-release.zip")
if subprocess.check_output(["git", "status", "--porcelain"], cwd=root).strip():
    raise SystemExit("Commit reviewed changes before packaging; working tree must be clean.")
revision = subprocess.check_output(["git", "rev-parse", "HEAD"], cwd=root, text=True).strip()
tracked = subprocess.check_output(["git", "ls-files", "-z"], cwd=root).decode().split("\0")
allowed = {"index.php", "config.php", "router.php", ".htaccess", "favicon.svg", "storage/.htaccess", "tools/maintenance.php"}
files = [name for name in tracked if name in allowed or name.startswith(("includes/", "assets/"))]
if not files or "includes/editorial.php" not in files:
    raise SystemExit("Incomplete release file list")
output = pathlib.Path(sys.argv[1]).resolve()
if output == root or root in output.parents:
    raise SystemExit("Write release outside the source checkout")
output.parent.mkdir(parents=True, exist_ok=True)
with zipfile.ZipFile(output, "w", zipfile.ZIP_DEFLATED) as archive:
    for name in sorted(files):
        archive.writestr(name, subprocess.check_output(["git", "show", revision + ":" + name], cwd=root))
digest = hashlib.sha256(output.read_bytes()).hexdigest()
output.with_suffix(".zip.sha256").write_text(digest + "  " + output.name + "\n", encoding="utf-8")
print(f"Revision: {revision}\nFiles: {len(files)}\nSHA256: {digest}\nZIP: {output}")
