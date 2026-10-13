#!/usr/bin/env python3
"""Create a stable Joomla plugin ZIP so the SHA-256 pin is reproducible."""

from pathlib import Path
import sys
from zipfile import ZIP_DEFLATED, ZipFile, ZipInfo

root = Path(__file__).resolve().parent
target = Path(sys.argv[1]).resolve()
files = sorted(
    path
    for folder in (root / "services", root / "src", root / "language")
    for path in folder.rglob("*")
    if path.is_file()
)
files.append(root / "dharmashop.xml")

with ZipFile(target, "w", compression=ZIP_DEFLATED, compresslevel=9) as archive:
    for path in sorted(files):
        relative = path.relative_to(root).as_posix()
        entry = ZipInfo(relative, date_time=(2026, 10, 1, 0, 0, 0))
        entry.compress_type = ZIP_DEFLATED
        entry.external_attr = 0o100644 << 16
        archive.writestr(entry, path.read_bytes(), compress_type=ZIP_DEFLATED, compresslevel=9)
