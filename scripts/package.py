from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED

root = Path(__file__).resolve().parent.parent
required = ['build/index.js', 'build/index.asset.php', 'build/index.css']
for name in required:
    if not (root / name).exists():
        raise SystemExit('Run npm install && npm run build before packaging: missing ' + name)
(root / 'dist').mkdir(exist_ok=True)
with ZipFile(root / 'dist/funnelkit-lite.zip', 'w', ZIP_DEFLATED) as archive:
    for name in ['funnelkit-lite.php', 'uninstall.php', 'README.md', 'includes', 'assets', 'build', 'docs']:
        path = root / name
        for file in ([path] if path.is_file() else sorted(path.rglob('*'))):
            if file.is_file() and file.suffix != '.map':
                archive.write(file, 'funnelkit-lite/' + str(file.relative_to(root)))
print('Created dist/funnelkit-lite.zip')
