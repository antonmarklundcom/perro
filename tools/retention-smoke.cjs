// Disposable synthetic fixtures; no production data, contacts or photos are copied.
const fs=require('node:fs'),os=require('node:os'),path=require('node:path'),net=require('node:net'),assert=require('node:assert/strict');
const {spawn}=require('node:child_process');
const root=path.resolve(__dirname,'..'),copy=fs.mkdtempSync(path.join(os.tmpdir(),'perro-retention-'));
fs.cpSync(root,copy,{recursive:true,filter:p=>!path.relative(root,p).split(path.sep).some(x=>['.git','storage'].includes(x))});
const requires=[...fs.readFileSync(path.join(copy,'index.php'),'utf8').matchAll(/^require __DIR__ \. '(\/includes\/[^']+)';/gm)].map(m=>`require_once __DIR__.'${m[1]}';`).join('\n');
fs.writeFileSync(path.join(copy,'retention-test.php'),`<?php ${requires} require_once __DIR__.'/includes/retention.php'; retention_routes(trim(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH),'/')); http_response_code(404);`);
let server,logs='',checks=0;
const check=(label,value)=>{assert.ok(value,label);checks++;};
async function run(){
  const socket=net.createServer();await new Promise(r=>socket.listen(0,'127.0.0.1',r));const port=socket.address().port;await new Promise(r=>socket.close(r));
  server=spawn(process.env.PERRO_PHP_BIN||'php',['-S','127.0.0.1:'+port,'retention-test.php'],{cwd:copy,windowsHide:true,env:{...process.env,PERRO_NOINDEX:''}});server.stderr.on('data',b=>logs+=b);
  const base='http://127.0.0.1:'+port;
  let ready=false;for(let i=0;i<300;i++){try{const r=await fetch(base+'/avisos.rss');if(r.status===200){ready=true;break;}}catch{}await new Promise(r=>setTimeout(r,100));}
  if(!ready)throw Error('PHP retention preview did not become ready: '+logs);
  const past='2025-03-01T12:00:00Z',future=new Date(Date.now()+86400000*60).toISOString();
  const dog={id:'synthetic-retention',slug:'synthetic-visible',name:'Synthetic <dog> & fixture',listing_type:'adoption',status:'published',adoption_status:'available',city:'Luque',created_at:past,published_at:past,updated_at:past,last_confirmed_at:past,expires_at:future,description:'PRIVATE_DESCRIPTION',contact_whatsapp:'595991123456',contact_name:'PRIVATE_OWNER',internal_note:'PRIVATE_NOTE',photos:['private.jpg']};
  const write=change=>fs.writeFileSync(path.join(copy,'storage/data/dogs.json'),JSON.stringify([{...dog,...change}]));
  const get=async(route,options)=>{const r=await fetch(base+route,options);return {status:r.status,headers:r.headers,text:await r.text()};};
  write({});const rss=await get('/avisos.rss');
  check('RSS public content and XML escaping',rss.status===200&&rss.text.includes('Synthetic &lt;dog&gt; &amp; fixture'));
  check('original publication date',rss.text.includes('<pubDate>Sat, 01 Mar 2025 12:00:00 +0000</pubDate>'));
  check('privacy allowlist',!['PRIVATE','595991123456','private.jpg'].some(s=>rss.text.includes(s)));
  check('RSS noindex and revalidation',rss.headers.get('x-robots-tag')==='noindex, nofollow'&&rss.headers.get('cache-control')==='public, max-age=0, must-revalidate');
  check('unchanged RSS conditional 304',(await get('/avisos.rss',{headers:{'If-None-Match':rss.headers.get('etag')}})).status===304);
  let states=await get('/guardados/estado?slugs=synthetic-visible,unknown');let payload=JSON.parse(states.text);
  check('minimal current status',payload.items[0].available===true&&payload.items[1].available===false&&Object.keys(payload.items[0]).join(',')==='slug,available,name,city,type,status');
  check('states never cached',states.headers.get('cache-control')==='no-store');
  for(const change of [{status:'pending'},{status:'removed'},{expires_at:'2000-01-01T00:00:00Z'},{adoption_status:'adopted'},{listing_type:'lost',adoption_status:'reunited'}]){
    write(change);const hidden=await get('/avisos.rss',{headers:{'If-None-Match':rss.headers.get('etag')}});
    check('hidden feed exclusion '+JSON.stringify(change),hidden.status===200&&!hidden.text.includes('synthetic-visible'));
    check('hidden status generic '+JSON.stringify(change),JSON.stringify(JSON.parse((await get('/guardados/estado?slugs=synthetic-visible')).text).items[0])==='{"slug":"synthetic-visible","available":false}');
  }
  for(const query of ['slugs[]=x','slugs=../private','slugs='+Array.from({length:101},(_,i)=>'s-'+i).join(',')])check('bounded invalid request '+query,(await get('/guardados/estado?'+query)).status===400);
  check('feed rejects writes',(await get('/avisos.rss',{method:'POST'})).status===405);
  write({});const page=await get('/guardados');check('saved page noindex and usable fallback',page.status===200&&page.text.includes('noindex,nofollow')&&page.text.includes('<noscript>')&&page.text.includes('data-saved-clear'));
  check('no PHP failures',!/(PHP (Warning|Fatal)|Uncaught)/.test(logs));console.log(JSON.stringify({status:'passed',checks}));
}
run().catch(e=>{console.error(e.stack);console.error(logs.slice(-2000));process.exitCode=1;}).finally(async()=>{if(server&&server.exitCode===null)await new Promise(r=>{server.once('exit',r);server.kill();});fs.rmSync(copy,{recursive:true,force:true});});
