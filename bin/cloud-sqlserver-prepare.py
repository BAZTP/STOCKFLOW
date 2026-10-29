#!/usr/bin/env python3
"""Create local development credentials and TLS material; never commit runtime files."""
from pathlib import Path
import secrets, subprocess, re
root=Path(__file__).resolve().parent.parent
runtime=Path('/workspace/cloud-runtime')
runtime.mkdir(exist_ok=True)
tls=runtime/'sqlserver-tls'; tls.mkdir(exist_ok=True)
local=root/'.env'
text=local.read_text() if local.exists() else (root/'.env.example').read_text()
values=dict(line.split('=',1) for line in text.splitlines() if '=' in line and not line.startswith('#'))
if values.get('DB_CONNECTION')=='mysql':
    if values.get('DB_HOST')!='stockflow-db': raise SystemExit('Custom MySQL connection preserved; configure migration explicitly.')
    backup=runtime/'mysql-app.env'
    if not backup.exists(): backup.write_text(text); backup.chmod(0o600)
elif values.get('DB_HOST') not in ('127.0.0.1','stockflow-sqlserver'):
    raise SystemExit('Custom database connection preserved; configure cloud database explicitly.')
credentials=runtime/'sqlserver.env'
if not credentials.exists():
    credentials.write_text('MSSQL_SA_PASSWORD=Aa1!'+secrets.token_hex(24)+'\nSTOCKFLOW_PASSWORD=Aa1!'+secrets.token_hex(24)+'\n')
    credentials.chmod(0o600)
creds=dict(line.split('=',1) for line in credentials.read_text().splitlines())
settings={'DB_CONNECTION':'sqlsrv','DB_HOST':'stockflow-sqlserver','DB_PORT':'1433','DB_DATABASE':'stockflow','DB_USERNAME':'stockflow','DB_PASSWORD':creds['STOCKFLOW_PASSWORD'],'DB_ENCRYPT':'yes','DB_TRUST_SERVER_CERTIFICATE':'false'}
for key,value in settings.items():
    if re.search(r'^'+key+'=',text,re.M): text=re.sub(r'^'+key+r'=.*$',lambda m:key+'='+value,text,flags=re.M)
    else: text+='\n'+key+'='+value+'\n'
# Write a candidate; activate only after SQL Server is ready.
(runtime/'sqlserver-app.env').write_text(text); (runtime/'sqlserver-app.env').chmod(0o600)
def openssl(*args): subprocess.run(['openssl',*args],check=True,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
if not (tls/'server.crt').exists():
    openssl('req','-x509','-newkey','rsa:2048','-nodes','-keyout',str(tls/'ca.key'),'-out',str(tls/'ca.crt'),'-days','3650','-subj','/CN=StockFlow local development CA')
    openssl('req','-newkey','rsa:2048','-nodes','-keyout',str(tls/'server.key'),'-out',str(tls/'server.csr'),'-subj','/CN=stockflow-sqlserver')
    (tls/'extensions.cnf').write_text('subjectAltName=DNS:stockflow-sqlserver,DNS:localhost,IP:127.0.0.1\nextendedKeyUsage=serverAuth\nkeyUsage=digitalSignature,keyEncipherment\n')
    openssl('x509','-req','-in',str(tls/'server.csr'),'-CA',str(tls/'ca.crt'),'-CAkey',str(tls/'ca.key'),'-CAcreateserial','-out',str(tls/'server.crt'),'-days','825','-extfile',str(tls/'extensions.cnf'))
    (tls/'ca.key').chmod(0o600)
(tls/'ca-bundle.crt').write_bytes(Path('/etc/ssl/certs/ca-certificates.crt').read_bytes()+(tls/'ca.crt').read_bytes())
(tls/'mssql.conf').write_text('[network]\ntlscert=/var/opt/mssql/secrets/server.crt\ntlskey=/var/opt/mssql/secrets/server.key\ntlsprotocols=1.2\nforceencryption=1\n')
sql="IF DB_ID(N'stockflow') IS NULL CREATE DATABASE [stockflow];\nGO\nIF DB_ID(N'stockflow_test') IS NULL CREATE DATABASE [stockflow_test];\nGO\nIF SUSER_ID(N'stockflow') IS NULL CREATE LOGIN [stockflow] WITH PASSWORD=N'"+creds['STOCKFLOW_PASSWORD']+"';\nGO\n"
for db in ('stockflow','stockflow_test'):
    sql+=f"USE [{db}];\nGO\nIF USER_ID(N'stockflow') IS NULL CREATE USER [stockflow] FOR LOGIN [stockflow];\nALTER ROLE db_owner ADD MEMBER [stockflow];\nGO\n"
(runtime/'sqlserver-init.sql').write_text(sql); (runtime/'sqlserver-init.sql').chmod(0o600)
