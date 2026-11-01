import argparse
import io
import json
import pathlib
import stat
import unittest
from unittest import mock
import zipfile

import build_paired_release as release
from build_test_bundle import package
import test_build_test_bundle as fixtures


class ReleaseTests(unittest.TestCase):
    def setUp(self):
        fixtures.BundleTests.setUp(self)
        for root,name,version in ((self.bw,'brainwash',release.OWNER_VERSION),(self.dept,'questionable_decisions',release.DEPARTMENT_VERSION)):
            (root/'server/manifest.json').write_bytes(json.dumps({'name':name,'version':version,'schema_version':2}).encode())
        (self.dept/'packaging').mkdir()
        public=['packaging/public-source-allowlist.json','server/manifest.json','server/api.php']
        (self.dept/'packaging/public-source-allowlist.json').write_bytes(json.dumps({'kind':'explicit-public-source-allowlist','files':[{'path':name} for name in public]}).encode())
        self.owner_entries=self.entries(self.bw); self.dept_entries=self.entries(self.dept)
        self.args=argparse.Namespace(owner_root=self.bw,department_root=self.dept,base_mod=self.base,dll=self.dll,output=self.out,owner_commit='a'*40,department_commit='b'*40)
    @staticmethod
    def entries(root):
        return {path.relative_to(root).as_posix():path.read_bytes() for path in root.rglob('*') if path.is_file()}
    def run_release(self):
        with mock.patch.object(release,'BASE_SHA256',release.sha256(self.base.read_bytes())),mock.patch.object(release,'DLL_SHA256',release.sha256(self.dll.read_bytes())),mock.patch.object(release,'export_source',side_effect=[(self.owner_entries,release.zip_bytes(self.owner_entries)),(self.dept_entries,release.zip_bytes(self.dept_entries))]):
            return release.release(self.args)
    def test_release_is_reproducible_curated_and_checksum_complete(self):
        receipt=self.run_release()
        first=self.entries(self.out)
        self.args.output=self.root/'out-two'
        self.run_release()
        self.assertEqual(first,self.entries(self.args.output))
        self.assertTrue(receipt['worktreeMatchesCommittedSources'])
        self.assertEqual({name for name in first if name.endswith('.dwpkg')},{release.OWNER_VERSION+'.dwpkg',release.DEPARTMENT_VERSION+'.dwpkg'})
        paired=next(data for name,data in first.items() if name.endswith('-TEST.zip'))
        self.assertEqual(first['WorldOfDramaLlama-'+release.DEPARTMENT_VERSION+'.zip'],paired)
        source=release.archive_entries(first['WorldOfDramaLlama-'+release.DEPARTMENT_VERSION+'-source.zip'])
        self.assertEqual(set(source),{'packaging/public-source-allowlist.json','server/manifest.json','server/api.php'})
        checks={line.split('  ',1)[1]:line.split('  ',1)[0] for line in first['SHA256SUMS.txt'].decode().splitlines()}
        self.assertEqual(checks,{name:release.sha256(data) for name,data in first.items() if name!='SHA256SUMS.txt'})
    def test_rejects_worktree_drift_before_deliverables(self):
        (self.bw/'server/runtime.php').write_bytes(b'<?php // uncommitted change')
        with self.assertRaisesRegex(ValueError,'does not match'): self.run_release()
        self.assertFalse(self.out.exists())
    def test_rejects_wrong_pinned_baseline_before_export(self):
        with mock.patch.object(release,'export_source') as export:
            with self.assertRaisesRegex(ValueError,'checksum mismatch'): release.release(self.args)
            export.assert_not_called()
        self.assertFalse(self.out.exists())
    def test_rejects_unsafe_case_colliding_and_symlink_archives(self):
        for data in (fixtures.archive({'../bad':'bad'}),fixtures.archive({'A':'one','a':'two'})):
            with self.assertRaises(ValueError): release.archive_entries(data)
        stream=io.BytesIO()
        with zipfile.ZipFile(stream,'w') as package:
            info=zipfile.ZipInfo('link'); info.create_system=3; info.external_attr=(stat.S_IFLNK|0o777)<<16
            package.writestr(info,'target')
        with self.assertRaisesRegex(ValueError,'Symlinks'): release.archive_entries(stream.getvalue())
    def test_rejects_incomplete_or_tampered_package_checksums(self):
        _,data=package(self.bw,'brainwash')
        entries=release.archive_entries(data)
        entries['server/runtime.php']+=b'// tampered'
        with self.assertRaisesRegex(ValueError,'digest mismatch'):
            release.verify_package(release.zip_bytes(entries),'brainwash',release.OWNER_VERSION)


if __name__=='__main__': unittest.main()
