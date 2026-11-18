"""HTTP integration checks. Run ONLY against a disposable seeded demo database.
Usage: KRONOS_TEST_URL=http://127.0.0.1:3005 python3 backend/tests/smoke.py
"""
import os, json, urllib.request as U, urllib.error as E, http.cookiejar, uuid
from dataclasses import dataclass
from datetime import datetime, timedelta
from zoneinfo import ZoneInfo
from decimal import Decimal
from concurrent.futures import ThreadPoolExecutor
from threading import Barrier
import struct, zlib
BASE=os.environ['KRONOS_TEST_URL'].rstrip('/')+'/api/v1'
TAG=uuid.uuid4().hex[:7].upper()
PASS=os.getenv('DEMO_PASSWORD','Kronos-demo-2026')

def ok(test,description):
    assert test,description
    print('OK',description,flush=True)

def expect(x, status):
    assert x[0]==status, x
    return x[1]['data']

def cash(amount,tendered=None): return {'method':'cash','amount':str(amount),'tendered':str(tendered or amount)}

def V(x): return Decimal(str(x))

class Session:
    def __init__(self):
        self.jar=http.cookiejar.CookieJar()
        self.o=U.build_opener(U.HTTPCookieProcessor(self.jar))
        pre=expect(self.call('GET','/auth/csrf'),200)
        assert pre['demo'], 'Smoke requiere entorno desechable y APP_DEMO'
        self.csrf=pre['csrf_token']
    def call(self,method,path,body=None,key=None,csrf=True,raw=None,content=None):
        h={'Accept':'application/json'}
        if method not in ('GET','HEAD'):
            if csrf:h['X-CSRF-Token']=self.csrf
            if key:h['Idempotency-Key']=key if isinstance(key,str) else str(uuid.uuid4())
            h['Content-Type']=content or 'application/json'
        data=raw if raw is not None else (json.dumps(body or {}).encode() if method not in ('GET','HEAD') else None)
        req=U.Request(BASE+path,data=data,headers=h,method=method)
        try:
            with self.o.open(req,timeout=13) as r:return r.status,json.load(r),dict(r.headers)
        except E.HTTPError as e:return e.code,json.load(e),dict(e.headers)
    def login(self,user):
        a=expect(self.call('POST','/auth/login',{'login':user,'password':PASS}),200)
        self.csrf=a['csrf_token'];return a['user']
    def get(self,p):return expect(self.call('GET',p),200)
    def post(self,p,v,status=201,key=True):return expect(self.call('POST',p,v,key),status)
    def put(self,p,v,status=200):return expect(self.call('PATCH',p,v),status)

admin=Session();expect(admin.call('GET','/dashboard'),401)
old=admin.csrf;who=admin.login('demo');ok(who['role']=='admin' and admin.csrf!=old,'inicio de sesión y rotación CSRF');
expect(admin.call('POST','/customers',{'name':'Sin CSRF'},csrf=False),403)
tech=Session();tech_id=tech.login('tecnico')['id']; reception=Session();reception.login('recepcion')
expect(tech.call('GET','/sales'),403);expect(tech.call('POST','/cash-sessions',{'register_id':2,'opening':'0'},True),403)
expect(tech.call('GET','/service-orders/1047'),404);ok('cost' not in tech.get('/products')[0],'permisos directos, aislamiento de orden y proyección de costos')
start=admin.get('/cash-sessions/current'); sid=start['session']['id'];
expect(admin.call('POST','/customers',{'name':'RUT incorrecto '+TAG,'document_type':'rut','document':'1-1'}),422)
c=admin.post('/customers',{'name':'Cliente de prueba '+TAG,'document_type':'pasaporte','document':'TEST-'+TAG},key=False)
d=admin.post('/devices',{'customer_id':c['id'],'type':'Notebook','brand':'Demo','model':TAG},key=False)
args={'customer_id':c['id'],'device_id':d['id'],'technician_id':tech_id,'fault':'No inicia','diagnosis':'Alimentación comprobada','priority':'media','accessories':['Cargador'],'preliminary':'40000'}
o=admin.post('/service-orders',args); oid=o['id']; root='/service-orders/'+str(oid)
assert admin.get(root)['state']=='borrador'; assert V(admin.get('/cash-sessions/current')['summary']['expected'])==V(start['summary']['expected'])
v=admin.put(root,{**args,'version':o['version']});expect(admin.call('PATCH',root,{**args,'version':o['version']}),412)
body={'version':v['version'],'diagnosis_authorized':True,'accessories_verified':True,'policy_accepted':True,'advance_accepted':True,'advance':{'session_id':sid,'payments':[cash(10000,12000)]},'temporary_access':'temporal-ficticia'}
k=str(uuid.uuid4());first=admin.call('POST',root+'/confirm',body,k);expect(first,200);second=admin.call('POST',root+'/confirm',body,k);expect(second,200)
ok(first[1]==second[1] and second[2].get('Idempotency-Replayed')=='true','borrador versionado, recepción y replay de anticipo')
assert len(admin.get(root)['payments'])==1 and V(admin.get(root)['paid'])==10000
assert 'temporal-ficticia' not in json.dumps(admin.get(root)) and 'temporal-ficticia' not in json.dumps(admin.get(root+'/receipt'))

def image(kind='valid'):
 b='kronos-smoke-file'; prefix=f'--{b}\r\nContent-Disposition: form-data; name="file"; filename="foto.png"\r\nContent-Type: image/png\r\n\r\n'.encode()
 def chunk(t,x):return struct.pack('>I',len(x))+t+x+struct.pack('>I',zlib.crc32(t+x))
 x=(b'\x89PNG\r\n\x1a\n'+chunk(b'IHDR',struct.pack('>IIBBBBB',1,1,8,2,0,0,0))+chunk(b'IDAT',zlib.compress(b'\x00\x11\x22\x33'))+chunk(b'IEND',b'')) if kind=='valid' else b'<svg xmlns="http://www.w3.org/2000/svg"></svg>'
 return prefix+x+f'\r\n--{b}--\r\n'.encode(),f'multipart/form-data; boundary={b}'
for kind,code in [('invalid',422),('valid',201)]:
 blob,ct=image(kind);expect(admin.call('POST',root+'/photos',raw=blob,content=ct),code)
ok(len(admin.get(root)['photos'])==1,'archivo rechazado por MIME y foto válida privada')
admin.post(root+'/transitions',{'state':'diagnostico'},200)

def product(k,price,units):
 p=admin.post('/products',{'sku':'TEST-'+TAG+'-'+k,'name':'Componente '+k+' '+TAG,'price':str(price),'cost':'5000','minimum':0,'location_id':1})['id']
 admin.post('/stock-movements',{'product_id':p,'kind':'entrada','quantity':units,'reason':'Prueba'})
 return p
p1=product('A',11900,3);p2=product('B',23800,1)
res=admin.post('/stock-reservations',{'target':'order','target_id':oid,'quantity':2,'product_id':p1})['id']
stock=lambda p:next(x for x in admin.get('/products?q='+TAG) if x['id']==p)
assert [int(stock(p1)[k]) for k in ('physical','reserved','available')]==[3,2,1]
expect(admin.call('POST','/stock-movements',{'product_id':p1,'kind':'ajuste','direction':'out','quantity':2,'reason':'No permitido'},True),409)
cart={'items':[{'product_id':p1,'quantity':1},{'product_id':p2,'quantity':1}],'discount':'3570','discount_reason':'Autorizado'}
price=admin.post('/sales/preview',cart,200,key=False);assert (price['subtotal'],price['tax'],price['total'])==('27000','5130','32130')
q=admin.post('/quotes',{'target':'order','target_id':oid,'valid_until':(datetime.now(ZoneInfo('America/Santiago'))+timedelta(days=5)).date().isoformat(),**cart})['id']
admin.post(f'/quotes/{q}/send',{'reference':'Entrega presencial de prueba'},200);admin.post(f'/quotes/{q}/approvals',{'decision':'approved','channel':'presencial','reference':'Conformidad de prueba'},200)
o=admin.get(root);ok(V(o['balance'])==22130 and V(o['paid'])==10000 and o['term_kind']=='firm','cotización, IVA, aprobación y saldo tras anticipo')
admin.post(root+'/transitions',{'state':'en_reparacion'},200)
admin.post('/stock-reservations/'+str(res)+'/consume',{'quantity':1,'reason':'Instalación'},200)
assert [int(stock(p1)[k]) for k in ('physical','reserved','available')]==[2,1,1]
admin.post('/stock-reservations/'+str(res)+'/release',{'quantity':1,'reason':'Sin uso'},200)
ok([int(stock(p1)[k]) for k in ('physical','reserved','available')]==[2,0,2],'reservar, consumir, liberar y rechazar stock negativo')

# Confirmar y revertir cobro mixto sin consolidar transferencia como efectivo.
mixed={**cart,'session_id':sid,'fingerprint':price['fingerprint'],'payments':[cash(12130,15000),{'method':'transfer','amount':'20000','reference':'TEST-'+TAG}]}
key=str(uuid.uuid4()); s=expect(admin.call('POST','/sales',mixed,key),201); s2=expect(admin.call('POST','/sales',mixed,key),201)
assert s['id']==s2['id'] and s['payments'][0]['change']=='2870'; expect(admin.call('POST','/sales',{**mixed,'customer_id':c['id']},key),409)
assert V(admin.get('/cash-sessions/current')['summary']['expected'])-V(start['summary']['expected'])==22130
admin.post(f'/sales/{s["id"]}/void',{'session_id':sid,'reason':'Prueba','return_confirmed':True,'references':{'transfer':'REV-'+TAG}},200)
ok(int(stock(p2)['physical'])==1 and V(admin.get('/cash-sessions/current')['summary']['expected'])-V(start['summary']['expected'])==10000,'venta mixta, cambio, idempotencia y reversión íntegra')

# Dos usuarios, dos turnos y dos conexiones intentando la misma última unidad.
other=reception.post('/cash-sessions',{'register_id':2,'opening':'0'})['id']
pay={'items':[{'product_id':p2,'quantity':1}]};pv=admin.post('/sales/preview',pay,200,key=False);barrier=Barrier(2)
def race(x):
 client,session=x;barrier.wait(timeout=8)
 return client.call('POST','/sales',{**pay,'session_id':session,'fingerprint':pv['fingerprint'],'payments':[cash(23800)]},True)
with ThreadPoolExecutor(max_workers=2) as pool: results=list(pool.map(race,[(admin,sid),(reception,other)]))
ok(sorted(x[0] for x in results)==[201,409] and int(stock(p2)['physical'])==0,'última unidad con dos turnos concurrentes: un solo éxito')

p=admin.post('/pc-projects',{'title':'PC '+TAG,'customer_id':c['id'],'technician_id':tech_id,'type':'nuevo'})['id'];admin.post(f'/pc-projects/{p}/items',{'product_id':p1,'quantity':1});pp=admin.get(f'/pc-projects/{p}');dup=admin.post(f'/pc-projects/{p}/duplicate',{})['id'];dup=admin.get(f'/pc-projects/{dup}')
ok(pp['compatibility']['state']=='verify' and len(dup['items'])==1 and not dup['reservations'] and not dup['quotes'] and not dup['payments'],'compatibilidad incompleta y duplicación sin compromisos')

# Evidencia exacta de BIOS, sin convertir otros datos ausentes en "compatible".
bios=admin.post('/pc-projects',{'title':'BIOS '+TAG,'customer_id':c['id'],'technician_id':tech_id})['id']
for component in [1,4]:admin.post(f'/pc-projects/{bios}/items',{'product_id':component,'quantity':1})
expect(admin.call('PUT','/products/4/bios-support',{'cpu_id':1,'board_revision':'demo-r1','bios_version':'demo-X1','compatibility':'yes','source':'https://example.test/fuente-ficticia'}),200)
admin.put(f'/pc-projects/{bios}/firmware',{'board_revision':'demo-r1','bios_version':'demo-X1'})
compat=admin.get(f'/pc-projects/{bios}')['compatibility']
ok(compat['state']=='verify' and next(i for i in compat['checks'] if i['label']=='Compatibilidad de BIOS')['state']=='compatible','BIOS comprobada con evidencia exacta; otros datos siguen por verificar')

batch='sku,name,price,cost,minimum\nCSV-'+TAG+',Artículo demo,1000,300,1'
preview=admin.post('/products/imports/preview',{'csv':batch},200,key=False); assert preview['valid']==1
ok(admin.post('/products/imports/commit',{'csv':batch})['imported']==1,'previsualización y alta por CSV sin alterar saldos físicos')

shift=reception.get('/cash-sessions/current')['summary']; amount=str(int(V(shift['expected'])));reception.post(f'/cash-sessions/{other}/counts',{'counted':amount});close=reception.post(f'/cash-sessions/{other}/close',{'counted':amount},200);assert V(close['difference'])==0
expect(reception.call('POST',f'/cash-sessions/{other}/movements',{'direction':'in','amount':'5','reason':'fuera del turno'},True),409)
expect(tech.call('POST','/auth/logout',{}),200);expect(tech.call('GET','/dashboard'),401)
ok(True,'arqueo, cierre bloquea cobro posterior y logout invalida sesión')
