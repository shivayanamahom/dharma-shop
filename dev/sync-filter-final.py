"""Copy only this task's changed source files to test.test; preserve unrelated dev/sync.sh."""
from pathlib import Path
import subprocess, shutil
root=Path('/Volumes/Work/Server/www/test')
paths=subprocess.check_output(['git','diff','HEAD','--name-only'],text=True).splitlines()+subprocess.check_output(['git','ls-files','--others','--exclude-standard'],text=True).splitlines()
for path in paths:
    p=Path(path);dst=None
    if path.startswith('com_dharmashop/admin/'): dst=root/'administrator/components/com_dharmashop'/p.relative_to('com_dharmashop/admin')
    elif path.startswith('com_dharmashop/site/'): dst=root/'components/com_dharmashop'/p.relative_to('com_dharmashop/site')
    elif path.startswith('com_dharmashop/media/'): dst=root/'media/com_dharmashop'/p.relative_to('com_dharmashop/media')
    elif path.startswith('plg_dharmashopfield_basic/'): dst=root/'plugins/dharmashopfield/basic'/p.relative_to('plg_dharmashopfield_basic')
    elif path.startswith('mod_dharmashop_filter/'):
        rel=p.relative_to('mod_dharmashop_filter');dst=root/('media/mod_dharmashop_filter' if str(rel).startswith('media/') else 'modules/mod_dharmashop_filter')/(rel.relative_to('media') if str(rel).startswith('media/') else rel)
    if dst:
        dst.parent.mkdir(parents=True,exist_ok=True);shutil.copy2(p,dst)
        if '/language/' in path:
            parts=p.parts;idx=parts.index('language');lang=parts[idx+1];dest=root/('administrator/language' if path.startswith('com_dharmashop/admin/') else 'language')/lang/p.name;shutil.copy2(p,dest)
    if p.suffix=='.php':
        r=subprocess.run(['php','-l',str(p)],capture_output=True,text=True)
        if r.returncode:print(r.stdout,r.stderr);raise SystemExit(1)
print('Targeted filter sync and PHP lint passed')
