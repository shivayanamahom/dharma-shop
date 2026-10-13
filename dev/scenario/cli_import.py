#!/usr/bin/env python3
"""Импорт из командной строки на тестовом сайте: профиль из двух товаров, `dharmashop:import` без и с --apply, повтор, правка цены.

  SCENARIO_URL=… SCENARIO_USER=… SCENARIO_PASS=… SCENARIO_SITE_PATH=/путь/к/сайту python3 dev/scenario/cli_import.py

Созданные товары и профиль удаляются в конце. Команда выполняется от имени SCENARIO_USER (параметр --user).
"""
from __future__ import annotations

import csv
import os
import subprocess
import sys
import tempfile

sys.path.insert(0, os.path.dirname(__file__))
from imp import Importer  # noqa: E402
from lib import Site, db, ok  # noqa: E402

R: list[tuple[bool, str]] = []
SOURCE = "qa-cli"


def cli(site_path: str, *args: str) -> tuple[int, str]:
    r = subprocess.run(["php", "cli/joomla.php", *args], cwd=site_path, capture_output=True, text=True, timeout=900)
    return r.returncode, r.stdout + r.stderr


def main() -> int:
    site_path = os.environ["SCENARIO_SITE_PATH"]
    user = os.environ["SCENARIO_USER"]
    a = Site()
    a.login()
    im = Importer(a)
    products_before = {r[0] for r in db("SELECT id FROM PFX_dharmashop_products")}
    root = db("SELECT id FROM PFX_categories WHERE extension = 'com_dharmashop' AND published = 1 ORDER BY id LIMIT 1")[0][0]
    path = os.path.join(tempfile.mkdtemp(), "cli.csv")
    rows = [["sku", "title", "price"], ["QACLI-1", "QA CLI первый", "100"], ["QACLI-2", "QA CLI второй", "250.5"]]

    def write(rs):
        with open(path, "w", encoding="utf-8-sig", newline="") as fh:
            csv.writer(fh, delimiter=";").writerows(rs)
    write(rows)
    params = {"key": "sku", "source_code": SOURCE, "create_new": "1", "update_existing": "1", "skip_errors": "1", "new_state": "1", "default_category": root, "separator": ";"}
    pid = im.save_profile("QA CLI импорт", "products", params, csv_path=path, mapping={0: "sku", 1: "title", 2: "price"})
    try:
        code, out = cli(site_path, "dharmashop:import", str(pid), "--user", user)
        ok(code == 0 and ("каталог не изменён" in out.lower() or "catalog is unchanged" in out.lower()), "проверка без --apply: каталог не изменён", R)
        ok(int(db("SELECT COUNT(*) FROM PFX_dharmashop_variants WHERE sku LIKE 'QACLI-%'")[0][0]) == 0, "после проверки товаров нет", R)
        code, out = cli(site_path, "dharmashop:import", str(pid), "--user", user, "--apply")
        ok(code == 0 and ("Импорт применён" in out or "Import applied" in out), "с --apply: импорт применён", R)
        n = int(db("SELECT COUNT(*) FROM PFX_dharmashop_variants WHERE sku LIKE 'QACLI-%'")[0][0])
        ok(n == 2, f"создано товаров: {n} из 2", R)
        code, out = cli(site_path, "dharmashop:import", str(pid), "--user", user, "--apply")
        ok(code == 0 and ("создать 0, обновить 0, без изменений 2" in out or "create 0, update 0, unchanged 2" in out), "повтор: без изменений 2", R)
        rows[2][2] = "300"
        write(rows)
        im.save_profile("QA CLI импорт", "products", params, csv_path=path, mapping={0: "sku", 1: "title", 2: "price"}, profile_id=pid)
        code, out = cli(site_path, "dharmashop:import", str(pid), "--user", user, "--apply")
        price = db("SELECT vp.price FROM PFX_dharmashop_variants v JOIN PFX_dharmashop_variant_prices vp ON vp.variant_id = v.id WHERE v.sku = 'QACLI-2' LIMIT 1")[0][0]
        ok(code == 0 and ("обновить 1" in out or "update 1" in out) and abs(float(price) - 300) < 0.01, f"правка цены применена: {price}", R)
        code, out = cli(site_path, "dharmashop:import", "999999", "--user", user)
        ok(code != 0, "несуществующий профиль даёт ошибку", R)
        code, out = cli(site_path, "dharmashop:import", str(pid), "--user", "нет-такого")
        ok(code != 0, "несуществующий пользователь даёт ошибку", R)
    finally:
        new = [r[0] for r in db("SELECT id FROM PFX_dharmashop_products") if r[0] not in products_before]
        if new:
            lst = "/administrator/index.php?option=com_dharmashop&view=products"
            tok = a.token(a.get(lst).text)
            for task in ("products.trash", "products.delete"):
                a.post(lst, {"cid[]": new, "task": task, tok: "1", "boxchecked": str(len(new))})
        jobs = [r[0] for r in db(f"SELECT id FROM PFX_dharmashop_import_jobs WHERE profile_id = {pid}")]
        if jobs:
            url = "/administrator/index.php?option=com_dharmashop_import&view=jobs"
            tok = a.token(a.get(url).text)
            a.post(url, {"cid[]": jobs, "task": "jobs.delete", tok: "1", "boxchecked": str(len(jobs))})
        db(f"DELETE FROM PFX_dharmashop_import_profiles WHERE id = {pid}")
        print(f"  удалено товаров: {len(new)}")
    bad = [name for passed, name in R if not passed]
    print(f"Итог: {len(R) - len(bad)} из {len(R)}")
    return 1 if bad else 0


if __name__ == "__main__":
    sys.exit(main())
