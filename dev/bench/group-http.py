#!/usr/bin/env python3
"""Read-only HTTP checks; emits timings/counts, never exports Joomla debug/session data."""
import json,re,time,statistics,urllib.request,urllib.parse,ssl,http.cookiejar
from pathlib import Path
root=Path(__file__).resolve().parents[2]
demo=json.loads((root/'dist/common-fields-demo.json').read_text())
base='https://test.test'
ctx=ssl._create_unverified_context()
opener=urllib.request.build_opener(urllib.request.HTTPSHandler(context=ctx),urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def get(url):
 t=time.perf_counter()
 with opener.open(url) as r:s=r.read();code=r.status;headers=dict(r.headers)
 return s.decode(),(time.perf_counter()-t)*1000,len(s),code,headers
def debug(html):
 marker='phpdebugbar.addDataSet('
 if marker not in html:return None
 data,_=json.JSONDecoder().raw_decode(html.split(marker,1)[1]);q=data['queries']
 sql=[s['sql'] for s in q['data']['statements']]
 assert not any('dharmashop_group_field_' in s for s in sql),'frontend read group defaults'
 return {'queries':q['count'],'sql_ms':q['data']['accumulated_duration_str'],'php_ms':round(data['profile']['duration']*1000,2),'common_field_queries':0}
def sample(name,url,check=None):
 times=[];sizes=[];d=None
 for _ in range(21):
  html,t,size,code,headers=get(url);assert code==200
  if check:check(html)
  if times or _:times.append(t);sizes.append(size)
  d=debug(html)
 result={'p50_ms':round(statistics.median(times),2),'p95_ms':round(sorted(times)[18],2),'bytes_median':int(statistics.median(sizes)),**(d or {})}
 report[name]=result
 return html
report={}
gid=demo['group_id']
for mode in ['grid','list','table']:
 html=sample(mode,base+'/index.php/tovary-kategorii/smartfony?sort=new&product_view='+mode,lambda s:(_ for _ in ()).throw(AssertionError('group missing')) if f'data-dharmashop-group="{gid}"' not in s else None)
 # Attribute order is known from rendered group card. Only select this sample's card.
 card=re.search(r'<(?:article|tr)[^>]*data-dharmashop-group="'+str(gid)+r'"[^>]*>',html).group(0)
 attrs=dict(re.findall(r'([\w-]+)="([^"]*)"',card))
 import html as h
 source=attrs['data-group-source'];token=h.unescape(attrs['data-group-context'])
 ajax=base+h.unescape(attrs['data-group-url'])+'&'+urllib.parse.urlencode({'source':source,'context':token,'choice[106]':125})
 def validate(s):
  data=json.loads(s);assert data['success'] and data['product'] in demo['product_ids'];assert 'коричневый' in data['html'];assert 'group_field_' not in data['html']
 sample('ajax_'+mode,ajax,validate)
 result=json.loads(get(ajax)[0]);assert result['product']==demo['product_ids'][4],'hidden memory retained when matching member exists'
 # A discounted source preserves its own hidden memory.
 q=base+h.unescape(attrs['data-group-url'])+'&'+urllib.parse.urlencode({'source':demo['product_ids'][1],'context':token,'choice[106]':126})
 data=json.loads(get(q)[0]);assert data['success'] and data['product']==demo['product_ids'][1] and '15' in data['html'] and '741' in data['html']
 # A forged signature must never expose a selection.
 forged=base+h.unescape(attrs['data-group-url'])+'&'+urllib.parse.urlencode({'source':source,'context':token+'x','choice[106]':125})
 assert not json.loads(get(forged)[0])['success']
# All six variants return only their own product card, including sold-out and discounted states.
prices=[15990,15741,18990,20490,21990,23490]
for i,pid in enumerate(demo['product_ids']):
 q=base+h.unescape(attrs['data-group-url'])+'&'+urllib.parse.urlencode({'source':pid,'context':token,'choice[106]':125+i%2})
 body,_,_,_,headers=get(q);data=json.loads(body)
 assert data['success'] and data['product']==pid
 plain=re.sub(r'\s+','',re.sub('<[^>]+>','',h.unescape(data['html'])))
 assert str(prices[i])+'₽' in plain
 assert len(re.findall(r'data-group-source=',data['html']))==1
 assert ('data-dharmashop-add-to-cart' in data['html'])==(i!=5)
 assert headers.get('Cache-Control','').lower()=='no-store'
 assert headers.get('X-Robots-Tag')=='noindex'
# Hidden controls cannot be injected into selection.
q=base+h.unescape(attrs['data-group-url'])+'&'+urllib.parse.urlencode({'source':source,'context':token,'choice[102]':111})
assert not json.loads(get(q)[0])['success']
# Use the real routed product URL observed in the card.
first=json.loads(get(base+h.unescape(attrs['data-group-url'])+'&'+urllib.parse.urlencode({'source':demo['product_ids'][0],'context':token,'choice[106]':125}))[0])
product=h.unescape(re.search(r'href="([^"]+)"[^>]*>[^<]*'+re.escape(demo['group_title']),first['html']).group(1))
productHtml=sample('product',base+product,lambda s:(_ for _ in ()).throw(AssertionError('common fields not rendered')) if not all(v in s for v in ['OLED 120','Горилла гласс 5','Документация','youtube','Дополнительное фото']) else None)
# Existing local file/image references must resolve without involving the video provider.
urls=set(re.findall(r'(?:src|href)="(https://test.test/[^"]*images/dharmashop/[^"]+)"',productHtml))
for url in urls:
 req=urllib.request.Request(h.unescape(url),method='HEAD')
 with opener.open(req) as response:assert response.status==200
assert not re.search(r'<iframe[^>]+youtube',productHtml)
report['media_checks']={'local_urls':len(urls),'video_iframes_before_play':0}
(root/'dist/common-fields-http.json').write_text(json.dumps(report,indent=2,ensure_ascii=False))
print(json.dumps(report,indent=2,ensure_ascii=False))
print('PASS: 3 listing layouts, 3 AJAX layouts, discount, hidden axis, forged context, page fields, zero group-default SQL')
