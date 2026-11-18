"""Real multipart uploads against an isolated PHP/SQLite fixture; no app credentials or live data."""
import base64, json, os, pathlib, socket, sqlite3, subprocess, tempfile, time, urllib.request, urllib.error, uuid
root=pathlib.Path(__file__).resolve().parents[1]
php=os.environ.get('PHP_BIN','php')
ini=os.environ.get('PHP_TEST_INI')
command=[php]+(['-c',ini] if ini else [])
fixture=root/'tests/artwork_revision_batches.php'
with tempfile.TemporaryDirectory(prefix='rcs-revision-test-') as directory:
    env=os.environ.copy();env['ARTWORK_REVISION_TEST_DIR']=directory
    subprocess.run(command+[str(fixture)],env=env,check=True,capture_output=True)
    with socket.socket() as listener:
        listener.bind(('127.0.0.1',0));port=listener.getsockname()[1]
    base=f'http://127.0.0.1:{port}/'
    with open(pathlib.Path(directory)/'server.log','w') as log:
        server=subprocess.Popen(command+['-S',f'127.0.0.1:{port}',str(fixture)],env=env,stdout=log,stderr=log)
        try:
            for attempt in range(50):
                try:
                    urllib.request.urlopen(base,timeout=1).close();break
                except (OSError,urllib.error.URLError):time.sleep(.05)
            else:raise AssertionError('PHP fixture did not start')
            db=sqlite3.connect(pathlib.Path(directory)/'test.sqlite')
            def upload(names,proof=False,expected=None):
                boundary=uuid.uuid4().hex;parts=[]
                for name in names:
                    payload=base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aS7sAAAAASUVORK5CYII=') if name.endswith('.png') else f'%PDF-1.4 test {name}'.encode()
                    parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="artwork[]"; filename="{name}"\r\nContent-Type: application/octet-stream\r\n\r\n'.encode()+payload+b'\r\n')
                parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="file_count"\r\n\r\n{len(names) if expected is None else expected}\r\n--{boundary}--\r\n'.encode())
                request=urllib.request.Request(base+('?proof=1' if proof else ''),data=b''.join(parts),headers={'Content-Type':f'multipart/form-data; boundary={boundary}'})
                try:
                    with urllib.request.urlopen(request,timeout=10) as response:return response.status,json.load(response)
                except urllib.error.HTTPError as response:return response.code,json.load(response)
            status,result=upload(['front.pdf','back.png']);assert status==200,result
            assert len(result['files'])==2
            assert result['files'][1]['mime']=='image/png'
            ids=[f['id'] for f in result['files']]
            assert db.execute('SELECT is_current FROM artwork_files WHERE id IN (1,2)').fetchall()==[(0,),(0,)]
            assert db.execute('SELECT id FROM artwork_files WHERE order_item_id=11 AND is_current=1 ORDER BY id').fetchall()==[(i,) for i in ids]
            assert db.execute('SELECT is_current FROM artwork_files WHERE id=3').fetchone()==(1,)
            assert db.execute("SELECT COUNT(*) FROM events WHERE event_type='design_file_superseded'").fetchone()==(2,)
            status,result=upload(['not-allowed.exe']);assert status==422
            assert db.execute('SELECT COUNT(*) FROM artwork_files WHERE order_item_id=11 AND is_current=1').fetchone()==(2,)
            # Two admin batches: only the latest proof batch is current; customer assets remain current.
            status,first=upload(['proof1.pdf','proof2.png'],True);assert status==200,first
            status,latest=upload(['corrected.pdf','corrected-back.png'],True);assert status==200,latest
            assert all(db.execute('SELECT is_current FROM artwork_files WHERE id=?',(f['id'],)).fetchone()==(0,) for f in first['files'])
            assert all(db.execute('SELECT is_current FROM artwork_files WHERE id=?',(f['id'],)).fetchone()==(1,) for f in latest['files'])
            assert all(db.execute('SELECT is_current FROM artwork_files WHERE id=?',(i,)).fetchone()==(1,) for i in ids)
            # A mismatched count cannot silently replace the old batch.
            before=db.execute('SELECT COUNT(*) FROM artwork_files').fetchone()
            status,result=upload(['one.pdf'],True,expected=2);assert status==422
            assert db.execute('SELECT COUNT(*) FROM artwork_files').fetchone()==before
            # Failure during publication rolls back archive flags/rows and removes newly moved files.
            db.execute("CREATE TRIGGER fail_new BEFORE INSERT ON artwork_files BEGIN SELECT RAISE(ABORT,'test failure'); END");db.commit()
            paths=set(pathlib.Path(directory).rglob('*.pdf'))
            status,result=upload(['rollback.pdf'],True);assert status==500,result
            assert db.execute('SELECT COUNT(*) FROM artwork_files').fetchone()==before
            assert set(pathlib.Path(directory).rglob('*.pdf'))==paths
            assert all(db.execute('SELECT is_current FROM artwork_files WHERE id=?',(f['id'],)).fetchone()==(1,) for f in latest['files'])
            print('PASS: real multi-file customer/proof uploads, latest batches only, preserved history, ownership isolation, complete-count validation and database/filesystem rollback')
        finally:
            server.terminate();server.wait(timeout=5)
