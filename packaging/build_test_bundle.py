"""Build a local TEST-only MO2 bundle from reviewed sources and an existing Department mod."""
import argparse
import hashlib
import io
import json
import pathlib
import re
import zipfile


def safe_name(name):
    p=pathlib.PurePosixPath(name)
    if not name or '\\' in name or ':' in name or p.is_absolute() or '..' in p.parts or p.as_posix()!=name:
        raise ValueError('Unsafe ZIP member')
    return name


def zip_bytes(entries):
    result=io.BytesIO()
    with zipfile.ZipFile(result,'w',compression=zipfile.ZIP_DEFLATED) as z:
        for name,data in sorted(entries.items()):
            info=zipfile.ZipInfo(safe_name(name),date_time=(2026,10,24,0,0,0)); info.compress_type=zipfile.ZIP_DEFLATED
            z.writestr(info,data)
    return result.getvalue()


def package(root,expected_name,relative_files=None,preserved_overlay=None):
    server=root/'server'
    meta=json.loads((server/'manifest.json').read_text(encoding='utf-8-sig'))
    if meta.get('name')!=expected_name or meta.get('schema_version')!=2 or not re.fullmatch(r'[0-9A-Za-z][0-9A-Za-z.+-]{0,63}',meta.get('version','')):
        raise ValueError('Invalid inner package identity')
    if relative_files is None:
        relative_files=[p.relative_to(server).as_posix() for p in server.rglob('*') if p.is_file() and not any(part in ('tests','__pycache__') for part in p.relative_to(server).parts)]
    payload={}
    for rel in sorted(set(relative_files)|{'manifest.json'}):
        safe_name(rel); p=server/rel
        if p.is_symlink() or any(part.startswith('.') for part in pathlib.PurePosixPath(rel).parts):
            raise ValueError('Invalid payload file')
        if p.is_file():
            data=p.read_bytes()
        elif preserved_overlay is not None and rel in preserved_overlay:
            data=preserved_overlay[rel]
        else:
            raise ValueError('Invalid payload file: '+rel)
        payload['server/'+rel]=data
    outer={'schema_version':4,'name':meta['name'],'version':meta['version'],'description':meta.get('description','Brainwash TEST candidate'),'server':{'mutable_paths':[]}}
    payload['manifest.json']=(json.dumps(outer,ensure_ascii=False,indent=2)+'\n').encode()
    payload['checksums.sha256']=''.join(hashlib.sha256(data).hexdigest()+'  '+name+'\n' for name,data in sorted(payload.items())).encode('ascii')
    return meta,zip_bytes(payload)


def build(brainwash_root,department_root,base_mod,dll,output):
    if output.exists(): raise ValueError('Output must be a fresh directory')
    with zipfile.ZipFile(base_mod) as z:
        names=z.namelist()
        if len(names)!=len(set(names)): raise ValueError('Duplicate ZIP members')
        entries={safe_name(n):z.read(n) for n in names if not n.endswith('/')}
    old=[n for n in entries if n.startswith('CHIM/server-plugins/questionable_decisions/') and n.endswith('.dwpkg')]
    if len(old)!=1: raise ValueError('Expected one base Department package')
    with zipfile.ZipFile(io.BytesIO(entries[old[0]])) as z:
        old_names=z.namelist()
        if len(old_names)!=len(set(old_names)): raise ValueError('Duplicate package members')
        for name in old_names: safe_name(name)
        dept_files=[name[7:] for name in old_names if name.startswith('server/') and not name.endswith('/')]
        overlay_names=('installation_guard.php','log.php','manifest.json','preprocessing.php','reflection.php','scope.php','source-manifest.json','state.php')
        preserved_overlay={rel:z.read('server/'+rel) for rel in dept_files if rel in {'setup/pcv-overlay/'+name for name in overlay_names}}
    dept_files.append('adapters/brainwash.php')
    bw_meta,bw_pkg=package(brainwash_root,'brainwash')
    dept_meta,dept_pkg=package(department_root,'questionable_decisions',dept_files,preserved_overlay)
    entries.pop(old[0])
    for meta,data in ((bw_meta,bw_pkg),(dept_meta,dept_pkg)):
        entries[f"CHIM/server-plugins/{meta['name']}/{meta['version']}.dwpkg"]=data
    entries['SKSE/Plugins/WorldOfDramaLlama.dll']=dll.read_bytes()
    for name in ('app.js','app.css','index.html','brainwash-editor.js','brainwash-editor.css'):
        entries['PrismaUI/views/WorldOfDramaLlama/'+name]=(department_root/'ui'/name).read_bytes()
    entries['BRAINWASH-TEST.txt']=(
        'Brainwash + Department TEST candidate. Use only a disposable MO2 profile/save and isolated server.\n'
        'This replaces the Department test mod; do not enable duplicate copies. Owner plugins remain separate requirements.\n'
        'No Skyrim acceptance or model-quality certification is implied. See the paired verification report.\n'
    ).encode()
    bundle_name=f"Brainwash-{bw_meta['version']}-WorldOfDramaLlama-{dept_meta['version']}-TEST.zip"
    bundle=zip_bytes(entries)
    output.mkdir(parents=True)
    (output/bundle_name).write_bytes(bundle)
    for meta,data in ((bw_meta,bw_pkg),(dept_meta,dept_pkg)):
        folder=output/meta['name']; folder.mkdir(); (folder/(meta['version']+'.dwpkg')).write_bytes(data)
    (output/'SHA256SUMS.txt').write_bytes((hashlib.sha256(bundle).hexdigest()+'  '+bundle_name+'\n').encode('ascii'))
    receipt={'bundle':bundle_name,'sha256':hashlib.sha256(bundle).hexdigest(),'base_sha256':hashlib.sha256(base_mod.read_bytes()).hexdigest(),'dll_sha256':hashlib.sha256(dll.read_bytes()).hexdigest(),'members':{n:hashlib.sha256(b).hexdigest() for n,b in sorted(entries.items())}}
    (output/'package-receipt.json').write_text(json.dumps(receipt,indent=2)+'\n',encoding='utf-8')
    return receipt


if __name__=='__main__':
    parser=argparse.ArgumentParser()
    for key in ('brainwash-root','department-root','base-mod','dll','output'): parser.add_argument('--'+key,type=pathlib.Path,required=True)
    args=parser.parse_args()
    try:
        result=build(args.brainwash_root,args.department_root,args.base_mod,args.dll,args.output)
        print(json.dumps({'bundle':result['bundle'],'sha256':result['sha256']}))
    except (ValueError,OSError,KeyError,zipfile.BadZipFile) as error: parser.exit(1,str(error)+'\n')
