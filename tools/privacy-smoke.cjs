// Development-only: node tools/privacy-smoke.cjs. PHP runtime path can be set via PERRO_PHP_BIN.
// Uses a disposable copy; never sends requests to production or changes source storage.
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const crypto = require('node:crypto');
const net = require('node:net');
const assert = require('node:assert/strict');
const {spawn} = require('node:child_process');
const source = path.resolve(__dirname, '..');
const copy = fs.mkdtempSync(path.join(os.tmpdir(), 'perro-privacy-'));
fs.cpSync(source, copy, {recursive:true, filter:file => {
  const relative = path.relative(source, file).split(path.sep).join('/');
  return !relative.split('/').includes('.git') && !relative.startsWith('storage/data') && !relative.startsWith('storage/uploads/');
}});
let processHandle, logs='', checks=0;
function check(name, condition) { assert.ok(condition,name); checks++; console.log('PASS '+name); }
function data(name) { return JSON.parse(fs.readFileSync(path.join(copy,'storage','data',name+'.json'),'utf8')); }
function writeData(name, value) { fs.writeFileSync(path.join(copy,'storage','data',name+'.json'),JSON.stringify(value)); }
async function freePort() { const socket=net.createServer(); await new Promise(r=>socket.listen(0,'127.0.0.1',r)); const port=socket.address().port; await new Promise(r=>socket.close(r)); return port; }
async function run() {
  const port=await freePort(), base='http://127.0.0.1:'+port;
  const password=crypto.randomBytes(24).toString('base64url');
  processHandle=spawn(process.env.PERRO_PHP_BIN || 'php',['-S','127.0.0.1:'+port,'router.php'],{
    cwd:copy, windowsHide:true, env:{...process.env,
      PERRO_ADMIN_PASSWORD_SHA256:crypto.createHash('sha256').update(password).digest('hex'),
      PERRO_OPERATOR_NAME:'LOCAL TEST OPERATOR <script>',PERRO_OPERATOR_ADDRESS:'LOCAL TEST ADDRESS',PERRO_PRIVACY_EMAIL:'privacy@example.invalid'}
  });
  let startupError; processHandle.on('error',error=>startupError=error);
  processHandle.stderr.on('data',value=>logs+=value.toString());
  function session() { const cookies=new Map(); return async (route,fields)=>{
    const response=await fetch(base+route,{method:fields?'POST':'GET',body:fields?new URLSearchParams(fields):undefined,redirect:'manual',headers:{cookie:[...cookies].map(([k,v])=>k+'='+v).join('; ')}});
    for(const cookie of response.headers.getSetCookie()) { const pair=cookie.split(';')[0],i=pair.indexOf('='); cookies.set(pair.slice(0,i),pair.slice(i+1)); }
    return {status:response.status,html:await response.text(),headers:response.headers};
  }; }
  const user=session(),admin=session(),visitor=session();
  let ready=false;
  for(let i=0;i<50;i++) { try { await visitor('/'); ready=true;break; } catch { if(startupError) throw startupError; await new Promise(r=>setTimeout(r,100)); } }
  check('isolated preview starts',ready);
  const routes=['/','/perros','/dar-perro-en-adopcion','/perros-perdidos-paraguay','/como-funciona','/centros-de-adopcion','/seguridad','/terminos','/privacidad','/cachorros-en-adopcion','/perros-de-raza-en-adopcion','/admin','/robots.txt','/sitemap.xml'];
  for(const route of routes) check('route '+route,(await visitor(route)).status===200);
  for(const route of ['/config.php','/storage/data/submissions.json','/includes/legal.php','/AGENTS.md','/LEGAL-RELEASE.md','/tools/privacy-smoke.cjs']) check('private route '+route,(await visitor(route)).status===404);
  const token=html=>html.match(/name="csrf_token" value="([^"]+)"/)[1];
  const form=await user('/dar-perro-en-adopcion');
  check('private name selected by default',/name="name_visibility" value="private" checked/.test(form.html));
  check('public name not selected',!/name="name_visibility" value="public" checked/.test(form.html));
  check('WhatsApp not selected',!/name="public_whatsapp"[^>]*checked/.test(form.html));
  for(const route of ['/terminos','/privacidad']) {
    const page=await visitor(route);
    check('complete legal page '+route,!page.html.includes('Texto pendiente') && page.html.includes('LOCAL TEST ADDRESS'));
    check('operator escaped '+route,page.html.includes('LOCAL TEST OPERATOR &lt;script&gt;')&&!page.html.includes('LOCAL TEST OPERATOR <script>'));
  }
  const privacy=await visitor('/privacidad');
  check('no false automatic deletion promise',privacy.html.includes('No prometemos una supresión automática'));
  const csrf=token(form.html),version= form.html.match(/name="terms_version" value="([^"]+)"/)[1];
  const fields={csrf_token:csrf,form_started:String(Math.floor(Date.now()/1000)-10),listing_type:'adoption',submitter_name:'PRIVATE FULL NAME SENTINEL',email:'private-owner@example.invalid',whatsapp:'0981999999',relationship:'Responsable actual',name:'LOCAL TEST DOG',department:'Central',city:'Luque',age_group:'Adulto',sex:'Macho',size:'Mediano',description:'Disposable local privacy test only; this record is never sent to production.',adult_confirm:'1',authorized_confirm:'1',photo_consent:'1',terms_accept:'1',no_sale_confirm:'1',terms_version:version,privacy_version:version,name_visibility:'private',public_display_name:'IGNORED PRIVATE ALIAS'};
  check('bad CSRF returns 419',(await user('/enviar-perro',{...fields,csrf_token:'invalid'})).status===419);
  for(const change of [{no_sale_confirm:'0'},{photo_consent:'on'},{terms_version:'old'},{privacy_version:'old'},{name_visibility:'public',public_display_name:''}]) {
    await user('/enviar-perro',{...fields,...change}); check('reject '+JSON.stringify(change),data('submissions').length===0);
  }
  await user('/enviar-perro',{...fields,public_whatsapp:'0'});
  const submission=data('submissions')[0];
  check('explicit private permissions stored',submission.public_name===false&&submission.public_whatsapp===false&&submission.public_display_name==='');
  check('policy acceptance is versioned and timestamped',submission.consents.no_sale_confirm===true&&submission.consents.terms_version===version&&submission.consents.privacy_version===version&&!!submission.consents.accepted_at);
  const login=await admin('/admin'); await admin('/admin/login',{csrf_token:token(login.html),username:'admin',password});
  const panel=await admin('/admin'),adminCsrf=token(panel.html);
  check('admin can see private submitter and permissions',panel.html.includes(fields.submitter_name)&&panel.html.includes('nombre privado'));
  async function approve(id) { await admin('/admin/action',{csrf_token:adminCsrf,dataset:'submissions',id,action:'approve'}); return data('dogs').at(-1); }
  let dog=await approve(submission.id),profile=await visitor('/perro/'+dog.slug);
  check('private names not copied to public dog',dog.contact_name===''&&dog.public_name===false);
  check('no private name/email/phone leak after approval',![fields.submitter_name,fields.email,'595981999999',fields.public_display_name].some(value=>profile.html.includes(value)));
  await user('/enviar-perro',{...fields,name:'OPT IN DOG',name_visibility:'public',public_display_name:'PUBLIC ALIAS <img src=x>',public_whatsapp:'1'});
  dog=await approve(data('submissions').at(-1).id); profile=await visitor('/perro/'+dog.slug);
  check('separate name and WhatsApp opt-in works',profile.html.includes('PUBLIC ALIAS &lt;img src=x&gt;')&&profile.html.includes('https://wa.me/595981999999'));
  check('full name stays private even with public alias',!profile.html.includes(fields.submitter_name));
  const dogs=data('dogs'); const legacy={...dogs[0],id:'legacy-test',slug:'legacy-test',contact_name:'LEGACY PRIVATE NAME'};delete legacy.public_name;dogs.push(legacy);writeData('dogs',dogs);
  check('legacy contact_name is private without explicit permission',!(await visitor('/perro/legacy-test')).html.includes('LEGACY PRIVATE NAME'));
  await user('/enviar-perro',{...fields,name:'NO CHOICE DOG',name_visibility:'',public_display_name:'IGNORED'});
  check('missing optional choice fails private',data('submissions').at(-1).public_name===false&&data('submissions').at(-1).public_display_name==='');
  const png=Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aEe8AAAAASUVORK5CYII=','base64');
  const imageDir=path.join(copy,'storage','uploads',dogs[0].source_submission_id); fs.mkdirSync(imageDir,{recursive:true});fs.writeFileSync(path.join(imageDir,'audit.png'),png);
  dogs[0].photos=['audit.png'];writeData('dogs',dogs);
  let photo=await visitor('/media/'+dogs[0].id+'/audit.png');
  check('published photos use no-store',photo.status===200&&photo.headers.get('cache-control')==='no-store');
  dogs[0].expires_at='2020-01-01T00:00:00-03:00';writeData('dogs',dogs);
  check('expired photo revoked',(await visitor('/media/'+dogs[0].id+'/audit.png')).status===404);
  dogs[0].expires_at='2099-01-01T00:00:00-03:00';dogs[0].status='removed';writeData('dogs',dogs);
  check('withdrawn photo revoked',(await visitor('/media/'+dogs[0].id+'/audit.png')).status===404);
  check('no PHP warnings or fatal errors',!/(?:PHP (?:Warning|Fatal error)|Uncaught)/.test(logs));
  console.log(checks+' checks passed; disposable copy: '+copy);
}
run().catch(error=>{console.error(error.message);process.exitCode=1;}).finally(()=>{if(processHandle)processHandle.kill();});
