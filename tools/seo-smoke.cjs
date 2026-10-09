// Metadata, crawlability and lifecycle regression checks using synthetic notices only.
const fs=require('node:fs'),os=require('node:os'),path=require('node:path'),net=require('node:net');
const {spawn}=require('node:child_process');
const assert=require('node:assert/strict');
const root=path.resolve(__dirname,'..'),copy=fs.mkdtempSync(path.join(os.tmpdir(),'perro-seo-'));
fs.cpSync(root,copy,{recursive:true,filter:p=>{const r=path.relative(root,p).split(path.sep).join('/');return !r.split('/').includes('.git')&&!r.startsWith('storage/data')&&!r.startsWith('storage/uploads')&&!r.startsWith('storage/cache');}});
const php=process.env.PERRO_PHP_BIN||'php';
let checks=0,logs='',server;
const check=(label,ok)=>{assert.ok(ok,label);checks++;};
const editorial=['guias-adopcion','requisitos-para-adoptar','elegir-perro','hogar-temporal','reubicacion-responsable','primeros-dias-perro-adoptado','seguridad','apoyar'];
const publicRoutes=['','perros','cachorros-en-adopcion','perros-de-raza-en-adopcion','perros-perdidos-paraguay','dar-perro-en-adopcion','como-funciona','privacidad','terminos',...editorial];
const decode=s=>s.replaceAll('&amp;','&').replaceAll('&quot;','"').replaceAll('&#039;',"'");
async function start(overrides={}) {
  const socket=net.createServer();await new Promise(r=>socket.listen(0,'127.0.0.1',r));const port=socket.address().port;await new Promise(r=>socket.close(r));
  server=spawn(php,['-S','127.0.0.1:'+port,'router.php'],{cwd:copy,windowsHide:true,env:{...process.env,PERRO_NOINDEX:'',PERRO_MASCOTA_GUIDES_ENABLED:'',...overrides}});
  server.stderr.on('data',b=>logs+=b.toString());
  const base='http://127.0.0.1:'+port;
  for(let i=0;i<60;i++){try{await fetch(base+'/health');return base;}catch{await new Promise(r=>setTimeout(r,100));}}
  throw Error('Cannot start preview: '+logs);
}
async function stop(){if(server&&server.exitCode===null){const handle=server;await new Promise(r=>{handle.once('exit',r);handle.kill();});}server=null;}
async function run(){
  let base=await start();
  const get=async route=>{const r=await fetch(base+'/'+route,{redirect:'manual'});return {status:r.status,headers:r.headers,html:await r.text()};};
  const inventory=[],titles=new Set(),descriptions=new Set(),pages=new Map();
  for(const route of publicRoutes){
    const page=await get(route),h=page.html;
    check(route+' status',page.status===200);
    check(route+' one H1',(h.match(/<h1\b/g)||[]).length===1);
    const title=decode(h.match(/<title>(.*?)<\/title>/s)[1]);
    const desc=decode(h.match(/name="description" content="([^"]+)"/)[1]);
    check(route+' unique descriptive title',title.length>12&&!titles.has(title));titles.add(title);
    check(route+' unique description',desc.length>35&&!descriptions.has(desc));descriptions.add(desc);
    check(route+' own canonical',h.includes('rel="canonical" href="https://perro.com.py/'+route+'"'));
    check(route+' indexable',h.includes('name="robots" content="index,follow"'));
    if(editorial.includes(route)){
      check(route+' visible date and navigable sections',h.includes('Actualizado el 9 de octubre de 2026')&&h.includes('id="paso-1"'));
      const scripts=[...h.matchAll(/<script type="application\/ld\+json">(.*?)<\/script>/gs)].map(m=>JSON.parse(m[1]));
      check(route+' visible-content schema',scripts.some(s=>s['@graph']?.some(n=>n.url==='https://perro.com.py/'+route))&&h.includes('aria-label="Ruta de navegación"'));
    }
    check(route+' no product or invented review schema',!h.includes('"@type":"Product"')&&!h.includes('aggregateRating'));
    pages.set(route,page);
    inventory.push({route:'/'+route,title,h1:decode(h.match(/<h1[^>]*>(.*?)<\/h1>/s)[1].replace(/<[^>]*>/g,' ')),description:desc,canonical:'https://perro.com.py/'+route});
  }
  const site=await get('sitemap.xml');
  for(const route of publicRoutes)check('sitemap '+route,site.html.includes('<loc>https://perro.com.py/'+route+'</loc>'));
  check('sitemap excludes private empty and filtered routes',![...site.html.matchAll(/<loc>(.*?)<\/loc>/g)].some(m=>/(admin|gracias|centros-de-adopcion|\?)/.test(m[1])));
  check('edited pages have factual sitemap date',site.html.includes('<loc>https://perro.com.py/elegir-perro</loc><lastmod>2026-10-09</lastmod>'));
  const robots=await get('robots.txt');
  check('robots distinguishes public and private routes',robots.html.includes('Allow: /')&&robots.html.includes('Disallow: /admin')&&robots.html.includes('Disallow: /storage/')&&robots.html.includes('Sitemap: https://perro.com.py/sitemap.xml'));
  for(const route of ['docs/seo/2026-10-09/KEYWORD-PAGE-MAP.csv','docs/seo/2026-10-09/README.md','docs/business/2026-10-09/REVENUE-PLAN.md','includes/editorial.php','storage/data/dogs.json','config.php'])check('private '+route,(await get(route)).status===404);
  for(const route of ['perros?page=2','perros?page=999','perros?page=0','perros?page=abc','perros?page[]=2','perros?page=01']){
    const p=await get(route);check('invalid page '+route,p.status===404&&p.html.includes('noindex,nofollow'));
  }
  check('filtered empty results have 404 and noindex',(await get('perros?city=NoExiste')).status===404&&(await get('perros?city=NoExiste')).html.includes('noindex,follow'));
  for(const route of ['?utm_source=local','elegir-perro?x=1','dar-perro-en-adopcion?type=lost','perros?sort=newest','perros?unknown=1'])check('query variants noindex '+route,(await get(route)).html.includes('noindex,follow'));
  check('bare empty catalog remains useful',pages.get('perros').html.includes('Todavía no hay avisos activos')&&pages.get('perros').html.includes('Cómo adoptar un perro en Paraguay'));
  const linked=new Set();
  for(const [route,p]of pages){for(const m of p.html.matchAll(/href="([^"]+)"/g)){
    const href=decode(m[1]);let url;try{url=new URL(href,'https://perro.com.py/'+route);}catch{continue;}
    if(url.origin!=='https://perro.com.py'||url.pathname.startsWith('/assets/')||url.pathname==='/favicon.svg'||url.pathname.startsWith('/admin'))continue;
    const target=url.pathname.replace(/^\//,'');linked.add(target);
    if(!pages.has(target)) {const response=await get(target+url.search);check('working link '+route+' -> '+href,response.status===200);}
    if(url.hash&&pages.has(target))check('working anchor '+href,pages.get(target).html.includes('id="'+decodeURIComponent(url.hash.slice(1))+'"'));
  }}
  for(const slug of editorial)check('crawlable guide '+slug,linked.has(slug));
  check('Mascota links disabled while its build is not live',![...pages.values()].some(p=>/href="https:\/\/mascota.com.py/.test(p.html)));
  const now=new Date().toISOString(),future=new Date(Date.now()+86400000*60).toISOString();
  const template={id:'dog-synthetic',slug:'synthetic-visible',name:'Synthetic visibility fixture',listing_type:'adoption',status:'published',adoption_status:'available',department:'Central',city:'Luque',age_group:'Adulto',approximate_age:'',sex:'Macho',size:'Mediano',breed_label:'',mixed_breed:true,description:'Synthetic fixture only; never a public dog.',health_information:'',vaccination_status:'',sterilization_status:'',compatibility:'',reason:'',adoption_requirements:'',last_location:'',incident_date:'',photos:[],public_name:false,public_whatsapp:false,contact_whatsapp:'',contact_name:'',published_at:now,updated_at:now,expires_at:future};
  const writeDogs=dogs=>fs.writeFileSync(path.join(copy,'storage/data/dogs.json'),JSON.stringify(dogs));
  for(const [label,change,status]of [['active',{},200],['pending',{status:'pending'},404],['expired',{expires_at:'2000-01-01T00:00:00Z'},404],['withdrawn',{status:'removed'},404],['adopted',{adoption_status:'adopted'},410],['reunited',{listing_type:'lost',adoption_status:'reunited'},410]]){
    writeDogs([{...template,...change}]);
    const listing=await get('perro/'+template.slug),map=await get('sitemap.xml'),catalog=await get('perros');
    check(label+' notice status',listing.status===status);
    check(label+' sitemap visibility',map.html.includes('/perro/'+template.slug)===(status===200));
    check(label+' catalog visibility',catalog.html.includes('href="/perro/'+template.slug+'"')===(status===200));
    if(status!==200)check(label+' ended/private page noindex',listing.html.includes('noindex,nofollow'));
  }
  writeDogs(Array.from({length:13},(_,i)=>({...template,id:'dog-'+i,slug:'synthetic-'+i})));
  const page2=await get('perros?page=2');
  check('valid pagination own canonical',page2.status===200&&page2.html.includes('rel="canonical" href="https://perro.com.py/perros?page=2"')&&page2.html.includes('index,follow'));
  check('pagination HTML links without default query duplicates',(await get('perros')).html.includes('href="/perros?page=2"'));
  writeDogs([]);
  await stop();base=await start({PERRO_NOINDEX:'1',PERRO_MASCOTA_GUIDES_ENABLED:'1'});
  for(const route of ['',...editorial,'perros','sitemap.xml']){const p=await get(route);check('preview header '+route,p.headers.get('x-robots-tag')==='noindex, nofollow');if(route!=='sitemap.xml')check('preview HTML '+route,p.html.includes('noindex,nofollow')&&!p.html.includes('application/ld+json'));}
  check('preview robots denies crawling',(await get('robots.txt')).html==='User-agent: *\nDisallow: /\n');
  check('Mascota care links activate only by explicit configuration',(await get('elegir-perro')).html.includes('href="https://mascota.com.py/mascotas/perros/razas/labrador/"'));
  check('PHP has no warnings or fatal errors',!/(PHP (Warning|Fatal)|Uncaught)/.test(logs));
  if(process.env.PERRO_SEO_REPORT)fs.writeFileSync(process.env.PERRO_SEO_REPORT,JSON.stringify({checks,inventory},null,2));
  console.log(JSON.stringify({checks,indexableStaticRoutes:publicRoutes.length,status:'passed'}));
}
run().catch(e=>{console.error(e.stack);console.error(logs.slice(-2500));process.exitCode=1;}).finally(stop);
