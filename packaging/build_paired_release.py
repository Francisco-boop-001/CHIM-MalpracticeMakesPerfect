"""Build the paired dev.6/prealpha.33 release from exact local Git commits. No install."""
import argparse
import hashlib
import io
import json
import pathlib
import re
import stat
import subprocess
import tempfile
import zipfile

from build_test_bundle import build, safe_name, zip_bytes

OWNER_VERSION='0.1.0-dev.6'
DEPARTMENT_VERSION='0.1.0-prealpha.33'
BASE_SHA256='029c33426c1e620b649ad3b6c8e6800d15819856b04a76846b1465f4d765708d'
DLL_SHA256='26c53f5c846b7f0eb8e7585ef8ddeb6f10577da6fa2a3edbbb025942744a7493'


def sha256(data):
    return hashlib.sha256(data).hexdigest()


def archive_entries(data):
    with zipfile.ZipFile(io.BytesIO(data)) as archive:
        names=archive.namelist()
        if len(names)!=len(set(n.casefold() for n in names)):
            raise ValueError('Duplicate or case-colliding ZIP members')
        entries={}
        for info in archive.infolist():
            name=info.filename
            safe_name(name.rstrip('/') if info.is_dir() else name)
            if stat.S_ISLNK(info.external_attr >> 16):
                raise ValueError('Symlinks are not release source files')
            if not info.is_dir(): entries[name]=archive.read(info)
        return entries


def export_source(root,commit):
    if not re.fullmatch('[0-9a-f]{40}',commit):
        raise ValueError('Source commit must be a full lowercase SHA-1')
    command=['git','-c','safe.directory='+root.resolve().as_posix(),'-C',str(root)]
    resolved=subprocess.run(command+['rev-parse','--verify',commit+'^{commit}'],check=True,capture_output=True).stdout.decode().strip()
    if resolved!=commit: raise ValueError('Source commit identity mismatch')
    data=subprocess.run(command+['archive','--format=zip',commit],check=True,capture_output=True).stdout
    entries=archive_entries(data)
    return entries,zip_bytes(entries)


def unpack(entries,root):
    root.mkdir()
    for name,data in entries.items():
        target=root.joinpath(*pathlib.PurePosixPath(name).parts)
        if not target.resolve().is_relative_to(root.resolve()):
            raise ValueError('Source extraction escaped its temporary root')
        target.parent.mkdir(parents=True,exist_ok=True)
        target.write_bytes(data)


def department_public_source(entries):
    manifest=json.loads(entries['packaging/public-source-allowlist.json'])
    if manifest.get('kind')!='explicit-public-source-allowlist':
        raise ValueError('Wrong Department public source allowlist kind')
    paths=[safe_name(item['path']) for item in manifest['files']]
    if len(paths)!=len(set(paths)) or 'packaging/public-source-allowlist.json' not in paths:
        raise ValueError('Invalid Department public source allowlist')
    return zip_bytes({name:entries[name] for name in paths})


def verify_package(data,name,version):
    entries=archive_entries(data)
    for manifest in ('manifest.json','server/manifest.json'):
        meta=json.loads(entries[manifest])
        if meta.get('name')!=name or meta.get('version')!=version:
            raise ValueError('Wrong packaged owner/version: '+manifest)
    expected={member:sha256(body) for member,body in entries.items() if member!='checksums.sha256'}
    checks=entries['checksums.sha256']
    if b'\r' in checks or not checks.endswith(b'\n'):
        raise ValueError('Noncanonical package checksums')
    actual={}
    for line in checks.decode('ascii').splitlines():
        digest,member=line.split('  ',1)
        safe_name(member)
        if member in actual or not re.fullmatch('[0-9a-f]{64}',digest):
            raise ValueError('Invalid package checksum entry')
        actual[member]=digest
    if actual!=expected: raise ValueError('Package checksum coverage or digest mismatch')


def release(args):
    if args.output.exists(): raise ValueError('Output must be a fresh directory')
    if not args.output.parent.is_dir(): raise ValueError('Output parent must exist')
    for path,expected in ((args.base_mod,BASE_SHA256),(args.dll,DLL_SHA256)):
        if sha256(path.read_bytes())!=expected: raise ValueError('Pinned input checksum mismatch: '+str(path))
    owner_entries,owner_source=export_source(args.owner_root,args.owner_commit)
    dept_entries,_=export_source(args.department_root,args.department_commit)
    dept_source=department_public_source(dept_entries)
    with tempfile.TemporaryDirectory(prefix='mmp-release-',dir=args.output.parent.resolve()) as temporary:
        scratch=pathlib.Path(temporary)
        owner=scratch/'owner'; department=scratch/'department'
        unpack(owner_entries,owner); unpack(dept_entries,department)
        committed=build(owner,department,args.base_mod,args.dll,scratch/'committed')
        worktree=build(args.owner_root,args.department_root,args.base_mod,args.dll,scratch/'worktree')
        if committed['members']!=worktree['members'] or committed['sha256']!=worktree['sha256']:
            raise ValueError('Worktree payload does not match the exact committed sources')
        payloads={}
        for name,version in (('brainwash',OWNER_VERSION),('questionable_decisions',DEPARTMENT_VERSION)):
            path=scratch/'committed'/name/(version+'.dwpkg')
            data=path.read_bytes(); verify_package(data,name,version)
            payloads[version+'.dwpkg']=data
        bundle=(scratch/'committed'/committed['bundle']).read_bytes()
        members=archive_entries(bundle)
        for name,version in (('brainwash',OWNER_VERSION),('questionable_decisions',DEPARTMENT_VERSION)):
            owned=[member for member in members if member.startswith('CHIM/server-plugins/'+name+'/') and member.endswith('.dwpkg')]
            if owned!=['CHIM/server-plugins/'+name+'/'+version+'.dwpkg']:
                raise ValueError('Expected exactly one current package for '+name)
        baseline=archive_entries(args.base_mod.read_bytes())
        changed={name for name in set(baseline)|set(members) if baseline.get(name)!=members.get(name)}
        fixed={'SKSE/Plugins/WorldOfDramaLlama.dll','MALPRACTICE-MAKES-PERFECT-TEST.txt'}
        fixed.update('PrismaUI/views/WorldOfDramaLlama/'+name for name in ('app.js','app.css','index.html','brainwash-editor.js','brainwash-editor.css'))
        for name in changed:
            owned=any(name.startswith('CHIM/server-plugins/'+owner+'/') and name.endswith('.dwpkg') for owner in ('brainwash','questionable_decisions'))
            if name not in fixed and not owned: raise ValueError('Unrelated baseline member changed: '+name)
        payloads[committed['bundle']]=bundle
        payloads['WorldOfDramaLlama-'+DEPARTMENT_VERSION+'.zip']=bundle
        payloads['MalpracticeMakesPerfect-'+OWNER_VERSION+'-source.zip']=owner_source
        payloads['WorldOfDramaLlama-'+DEPARTMENT_VERSION+'-source.zip']=dept_source
        receipt={'schemaVersion':1,'ownerCommit':args.owner_commit,'departmentCommit':args.department_commit,
                 'baselineSha256':BASE_SHA256,'dllSha256':DLL_SHA256,'worktreeMatchesCommittedSources':True,
                 'unchangedBaselineMembers':len(set(baseline)-changed),'changedMembers':sorted(changed),
                 'bundleMembers':committed['members'],'artifacts':{name:sha256(data) for name,data in sorted(payloads.items())},
                 'authority':'Windows-local source/package checks only; no install, activation, provider, or gameplay proof.'}
        payloads['release-receipt.json']=(json.dumps(receipt,indent=2)+'\n').encode()
        payloads['SHA256SUMS.txt']=''.join(sha256(data)+'  '+name+'\n' for name,data in sorted(payloads.items())).encode('ascii')
        args.output.mkdir()
        for name,data in payloads.items(): (args.output/name).write_bytes(data)
        for name,data in payloads.items():
            if (args.output/name).read_bytes()!=data: raise ValueError('Output readback mismatch: '+name)
        return receipt


if __name__=='__main__':
    parser=argparse.ArgumentParser(description=__doc__)
    for key in ('owner-root','department-root','base-mod','dll','output'):
        parser.add_argument('--'+key,type=pathlib.Path,required=True)
    for key in ('owner-commit','department-commit'): parser.add_argument('--'+key,required=True)
    try:
        receipt=release(parser.parse_args())
        print(json.dumps({'artifacts':receipt['artifacts'],'worktreeMatchesCommittedSources':True},indent=2))
    except (ValueError,OSError,KeyError,zipfile.BadZipFile,subprocess.CalledProcessError) as error:
        parser.exit(1,str(error)+'\n')
