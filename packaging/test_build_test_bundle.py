import hashlib, importlib.util, io, json, pathlib, subprocess, sys, tempfile, unittest, zipfile

SCRIPT = pathlib.Path(__file__).with_name('build_test_bundle.py')

def archive(entries):
    b=io.BytesIO()
    with zipfile.ZipFile(b,'w') as z:
        for name,data in entries.items(): z.writestr(name,data)
    return b.getvalue()

class BundleTests(unittest.TestCase):
    def setUp(self):
        self.temp=tempfile.TemporaryDirectory(prefix='brainwash-package-'); self.addCleanup(self.temp.cleanup)
        self.root=pathlib.Path(self.temp.name)
        for name in ('brainwash','department'):
            (self.root/name/'server').mkdir(parents=True)
        self.bw=self.root/'brainwash'; self.dept=self.root/'department'
        (self.bw/'server/manifest.json').write_text(json.dumps({'name':'brainwash','version':'0.1.0-dev.1','schema_version':2}))
        (self.bw/'server/runtime.php').write_text('<?php function brainwash_runtime_ready(){return true;}')
        (self.dept/'server/manifest.json').write_text(json.dumps({'name':'questionable_decisions','version':'0.1.0-prealpha.10','schema_version':2}))
        (self.dept/'server/api.php').write_text('<?php // updated API')
        (self.dept/'server/adapters').mkdir(); (self.dept/'server/adapters/brainwash.php').write_text('<?php // adapter')
        (self.dept/'ui').mkdir()
        for name in ('app.js','app.css','index.html','brainwash-editor.js','brainwash-editor.css'):
            (self.dept/'ui'/name).write_text('new-'+name)
        self.pcv=archive({'manifest.json':json.dumps({'schema_version':4,'name':'pcv_companion','version':'0.1.0-prealpha.9','server':{}}),'server/manifest.json':'{}'})
        old=archive({'manifest.json':json.dumps({'schema_version':4,'name':'questionable_decisions','version':'0.1.0-prealpha.9','server':{}}),'server/manifest.json':'{}','server/api.php':'old'})
        self.base=self.root/'base.zip'
        self.base.write_bytes(archive({'CHIM/server-plugins/pcv_companion/0.1.0-prealpha.9.dwpkg':self.pcv,'CHIM/server-plugins/questionable_decisions/0.1.0-prealpha.9.dwpkg':old,'SKSE/Plugins/WorldOfDramaLlama.dll':b'old-DLL','WorldOfDramaLlama.esp':b'keep-ESP'}))
        self.dll=self.root/'WorldOfDramaLlama.dll'; self.dll.write_bytes(b'new-reviewed-DLL')
        self.out=self.root/'out'
    def run_builder(self):
        return subprocess.run([sys.executable,str(SCRIPT),'--brainwash-root',str(self.bw),'--department-root',str(self.dept),'--base-mod',str(self.base),'--dll',str(self.dll),'--output',str(self.out)],capture_output=True,text=True)
    def test_installs_matching_new_packages_and_ui_preserving_existing_game_assets(self):
        r=self.run_builder(); self.assertEqual(r.returncode,0,r.stderr)
        bundle=next(self.out.glob('*-TEST.zip'))
        with zipfile.ZipFile(bundle) as z:
            self.assertEqual(z.read('SKSE/Plugins/WorldOfDramaLlama.dll'),b'new-reviewed-DLL')
            self.assertEqual(z.read('WorldOfDramaLlama.esp'),b'keep-ESP')
            self.assertEqual(z.read('PrismaUI/views/WorldOfDramaLlama/brainwash-editor.js'),b'new-brainwash-editor.js')
            self.assertEqual(z.read('CHIM/server-plugins/pcv_companion/0.1.0-prealpha.9.dwpkg'),self.pcv)
            self.assertNotIn('CHIM/server-plugins/questionable_decisions/0.1.0-prealpha.9.dwpkg',z.namelist())
            for plugin,version in [('brainwash','0.1.0-dev.1'),('questionable_decisions','0.1.0-prealpha.10')]:
                name=f'CHIM/server-plugins/{plugin}/{version}.dwpkg'
                with zipfile.ZipFile(io.BytesIO(z.read(name))) as p:
                    self.assertEqual(json.loads(p.read('manifest.json'))['version'],version)
                    self.assertEqual(json.loads(p.read('server/manifest.json'))['version'],version)
                    checks=p.read('checksums.sha256'); self.assertNotIn(b'\r',checks)
                    for line in checks.decode().splitlines():
                        digest,member=line.split('  ',1); self.assertEqual(hashlib.sha256(p.read(member)).hexdigest(),digest)
    def test_refuses_existing_output_without_overwriting(self):
        self.out.mkdir(); sentinel=self.out/'preserve.txt'; sentinel.write_text('original')
        r=self.run_builder(); self.assertNotEqual(r.returncode,0)
        self.assertEqual(sentinel.read_text(),'original'); self.assertEqual(list(self.out.iterdir()),[sentinel])
    def test_rejects_unsafe_base_archive_before_creating_deliverables(self):
        self.base.write_bytes(archive({'../escape.php':'bad'}))
        r=self.run_builder(); self.assertNotEqual(r.returncode,0)
        self.assertFalse((self.root/'escape.php').exists()); self.assertFalse(self.out.exists())

if __name__=='__main__': unittest.main()
