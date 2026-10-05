// Development-only: node tools/privacy-smoke.cjs. PHP runtime path can be set via PERRO_PHP_BIN.
// Uses a disposable copy; never sends requests to production or changes source storage.
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const crypto = require('node:crypto');
const net = require('node:net');
const assert = require('node:assert/strict');
const {spawn, execFileSync} = require('node:child_process');
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
  const php=process.env.PERRO_PHP_BIN || 'php';
  const gdArgs=process.env.PERRO_PHP_GD_DIR ? ['-d','extension_dir='+process.env.PERRO_PHP_GD_DIR,'-d','extension=gd'] : [];
  processHandle=spawn(php,[...gdArgs,'-d','upload_max_filesize=5M','-d','post_max_size=30M','-d','memory_limit=128M','-S','127.0.0.1:'+port,'router.php'],{
    cwd:copy, windowsHide:true, env:{...process.env,
      PERRO_ADMIN_PASSWORD_SHA256:crypto.createHash('sha256').update(password).digest('hex'),
      PERRO_OPERATOR_NAME:'LOCAL TEST OPERATOR <script>',PERRO_OPERATOR_ADDRESS:'LOCAL TEST ADDRESS',PERRO_PRIVACY_EMAIL:'privacy@example.invalid'}
  });
  let startupError; processHandle.on('error',error=>startupError=error);
  processHandle.stderr.on('data',value=>logs+=value.toString());
  function session() { const cookies=new Map(); return async (route,fields)=>{
    const response=await fetch(base+route,{method:fields?'POST':'GET',body:fields?(fields instanceof FormData?fields:new URLSearchParams(fields)):undefined,redirect:'manual',headers:{cookie:[...cookies].map(([k,v])=>k+'='+v).join('; ')}});
    for(const cookie of response.headers.getSetCookie()) { const pair=cookie.split(';')[0],i=pair.indexOf('='); cookies.set(pair.slice(0,i),pair.slice(i+1)); }
    const bytes=Buffer.from(await response.arrayBuffer());
    return {status:response.status,html:bytes.toString('utf8'),bytes,headers:response.headers};
  }; }
  const user=session(),admin=session(),visitor=session();
  let ready=false;
  for(let i=0;i<50;i++) { try { await visitor('/'); ready=true;break; } catch { if(startupError) throw startupError; await new Promise(r=>setTimeout(r,100)); } }
  check('isolated preview starts',ready);
  const routes=['/','/perros','/dar-perro-en-adopcion','/perros-perdidos-paraguay','/como-funciona','/centros-de-adopcion','/seguridad','/terminos','/privacidad','/cachorros-en-adopcion','/perros-de-raza-en-adopcion','/admin','/robots.txt','/sitemap.xml'];
  for(const route of routes) check('route '+route,(await visitor(route)).status===200);
  for(const route of ['/config.php','/storage/data/submissions.json','/includes/legal.php','/AGENTS.md','/LEGAL-RELEASE.md','/tools/privacy-smoke.cjs','/.git/config','/assets/%2e%2e/storage/data/submissions.json','/%69ncludes/data.php']) check('private route '+route,(await visitor(route)).status===404);
  const token=html=>html.match(/name="csrf_token" value="([^"]+)"/)[1];
  const home=await visitor('/');
  const release=home.headers.get('x-perro-release');
  check('release fingerprint available without private data',/^perro-[a-f0-9]{12}$/.test(release)&&home.html.includes('name="perro-release" content="'+release+'"'));
  const cssFile=path.join(copy,'assets/css/site.css'),cssOriginal=fs.readFileSync(cssFile);
  const oldCssUrl=home.html.match(/href="([^"]+site\.css\?v=[a-f0-9]+)"/)[1];
  fs.appendFileSync(cssFile,'\n/* disposable cache invalidation check */\n');
  const changedHome=await visitor('/'),newCssUrl=changedHome.html.match(/href="([^"]+site\.css\?v=[a-f0-9]+)"/)[1];
  check('CSS changes invalidate asset URL and release fingerprint',newCssUrl!==oldCssUrl&&changedHome.headers.get('x-perro-release')!==release);
  check('versioned CSS loads',(await visitor(newCssUrl)).status===200);
  fs.writeFileSync(cssFile,cssOriginal);
  check('lost and found links preselect correct form type',(await visitor('/dar-perro-en-adopcion?type=lost')).html.includes('<option value="lost" selected>')&&(await visitor('/dar-perro-en-adopcion?type=found')).html.includes('<option value="found" selected>'));
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
  const fields={csrf_token:csrf,form_started:String(Math.floor(Date.now()/1000)-10),listing_type:'adoption',submitter_name:'PRIVATE FULL NAME SENTINEL',email:'private-owner@example.invalid',whatsapp:'0981999999',relationship:'Responsable actual',name:'LOCAL TEST DOG',department:'Central',city:'Asunción',age_group:'Adulto',sex:'Macho',size:'Mediano',description:'Disposable local privacy test only; this record is never sent to production.',adult_confirm:'1',authorized_confirm:'1',photo_consent:'1',terms_accept:'1',no_sale_confirm:'1',terms_version:version,privacy_version:version,name_visibility:'private',public_display_name:'IGNORED PRIVATE ALIAS'};
  check('bad CSRF returns 419',(await user('/enviar-perro',{...fields,csrf_token:'invalid'})).status===419);
  for(const change of [{no_sale_confirm:'0'},{photo_consent:'on'},{terms_version:'old'},{privacy_version:'old'},{name_visibility:'public',public_display_name:''},{description:'too short',size:'Grande',reason:'Contexto de prueba para conservar.'},{size:'forged size'},{age_group:'forged age'},{relationship:'forged role'},{listing_type:'sell'},{listing_type:'found',last_location:'',incident_date:''},{listing_type:'lost',last_location:'Central',incident_date:'2099-01-01'},{listing_type:'lost',last_location:'Central',incident_date:'2026-02-30'}]) {
    await user('/enviar-perro',{...fields,...change}); check('reject '+JSON.stringify(change),data('submissions').length===0);
    if (change.description) {
      const retry=await user('/dar-perro-en-adopcion');
      check('validation retry preserves selected size and context',retry.html.includes('<option value="Grande" selected>')&&retry.html.includes('Contexto de prueba para conservar.'));
    }
  }
  await user('/enviar-perro',{...fields,public_whatsapp:'0'});
  const submission=data('submissions')[0];
  check('explicit private permissions stored',submission.public_name===false&&submission.public_whatsapp===false&&submission.public_display_name==='');
  check('policy acceptance is versioned and timestamped',submission.consents.no_sale_confirm===true&&submission.consents.terms_version===version&&submission.consents.privacy_version===version&&!!submission.consents.accepted_at);
  const login=await admin('/admin'); await admin('/admin/login',{csrf_token:token(login.html),username:'admin',password});
  const panel=await admin('/admin'),adminCsrf=token(panel.html);
  check('admin can see private submitter and permissions',panel.html.includes(fields.submitter_name)&&panel.html.includes('nombre privado'));
  async function approve(id) { await admin('/admin/action',{csrf_token:adminCsrf,dataset:'submissions',id,action:'approve',review_confirm:'1'}); return data('dogs').at(-1); }
  await admin('/admin/action',{csrf_token:adminCsrf,dataset:'submissions',id:submission.id,action:'approve'});
  check('approval requires explicit review',data('dogs').length===0);
  let dog=await approve(submission.id),profile=await visitor('/perro/'+dog.slug);
  check('private names not copied to public dog',dog.contact_name===''&&dog.public_name===false);
  check('no private name/email/phone leak after approval',![fields.submitter_name,fields.email,'595981999999',fields.public_display_name].some(value=>profile.html.includes(value)));
  check('private-contact profile offers working mediation link',profile.html.includes('Consultar al equipo de Perro')&&profile.html.includes('https://wa.me/595992279599'));
  const firstDog=dog;
  const cityResults=await visitor('/perros?city=asuncion');
  check('city search accepts missing accents',cityResults.html.includes(firstDog.name));
  check('search size is optional',!/<select name="size" required/.test(cityResults.html));
  check('selected search size retained',(await visitor('/perros?size=Mediano')).html.includes('<option value="Mediano" selected>'));
  check('text search accepts upper case and missing accents',(await visitor('/perros?q=ASUNCION')).html.includes(firstDog.name));
  check('nonmatching search excludes listing',!(await visitor('/perros?city=Encarnacion')).html.includes(firstDog.name));
  const queueHtml=(await admin('/admin?queue=approved')).html.split('id="solicitudes"')[1].split('id="publicadas"')[0];
  const pendingHtml=(await admin('/admin?queue=pending')).html.split('id="solicitudes"')[1].split('id="publicadas"')[0];
  check('admin status filter includes only requested submissions',queueHtml.includes(firstDog.name)&&!pendingHtml.includes(firstDog.name));
  await Promise.all([approve(submission.id),approve(submission.id)]);
  check('duplicate and concurrent approval creates exactly one dog',data('dogs').length===1&&data('dogs')[0].id===firstDog.id);
  await admin('/admin/action',{csrf_token:adminCsrf,dataset:'submissions',id:submission.id,action:'reject'});
  check('approved submission cannot be rejected',data('submissions')[0].status==='approved');
  await user('/enviar-perro',{...fields,name:'OPT IN DOG',name_visibility:'public',public_display_name:'PUBLIC ALIAS <img src=x>',public_whatsapp:'1'});
  dog=await approve(data('submissions').at(-1).id); profile=await visitor('/perro/'+dog.slug);
  check('separate name and WhatsApp opt-in works',profile.html.includes('PUBLIC ALIAS &lt;img src=x&gt;')&&profile.html.includes('https://wa.me/595981999999'));
  check('full name stays private even with public alias',!profile.html.includes(fields.submitter_name));
  const optInSubmission=data('submissions').at(-1), optInDog=dog;
  const editUrl='/admin/edit?dataset=submissions&id='+optInSubmission.id;
  check('anonymous editor access blocked',(await visitor(editUrl)).headers.get('location')==='/admin');
  const edit=await admin(editUrl);
  check('editor includes complete private review fields',edit.status===200&&['name="health_information"','name="last_location"','name="reason"','name="internal_note"',fields.submitter_name].every(value=>edit.html.includes(value)));
  const revision=html=>html.match(/name="revision" value="([^"]+)"/)[1];
  const editFields={...fields,dataset:'submissions',id:optInSubmission.id,csrf_token:adminCsrf,revision:revision(edit.html),review_confirm:'1',name:'CORRECTED LOCAL DOG',health_information:'Local-only veterinary context',compatibility:'Local-only compatibility',reason:'Local-only context',adoption_requirements:'Local-only requirements',hide_name:'1',hide_whatsapp:'1',internal_note:'LOCAL INTERNAL NOTE'};
  await admin('/admin/edit',editFields);
  const corrected=data('dogs').find(d=>d.id===optInDog.id);
  check('approved source edit synchronizes publication',corrected.name===editFields.name&&corrected.health_information===editFields.health_information&&data('submissions').find(s=>s.id===optInSubmission.id).name===editFields.name);
  profile=await visitor('/perro/'+corrected.slug);
  check('contact permissions can be revoked without withdrawing listing',profile.status===200&&!profile.html.includes('PUBLIC ALIAS')&&!profile.html.includes('https://wa.me/595981999999')&&!profile.html.includes('LOCAL INTERNAL NOTE'));
  check('revocations recorded privately',data('submissions').find(s=>s.id===optInSubmission.id).privacy_revocations.length===2);
  check('direct published edit redirects to authoritative source',(await admin('/admin/edit?dataset=dogs&id='+optInDog.id)).headers.get('location')==='/admin/edit?dataset=submissions&id='+optInSubmission.id);
  await admin('/admin/edit',{...editFields,name:'STALE OVERWRITE'});
  check('stale concurrent edits rejected',data('dogs').find(d=>d.id===optInDog.id).name===editFields.name);
  async function state(id,action,extra={}) { return admin('/admin/action',{csrf_token:adminCsrf,dataset:'dogs',id,action,...extra}); }
  await state(firstDog.id,'adopted');
  check('adopted dog removed from search detail and sitemap',!(await visitor('/perros')).html.includes(firstDog.name)&&(await visitor('/perro/'+firstDog.slug)).status===404&&!(await visitor('/sitemap.xml')).html.includes(firstDog.slug));
  await state(firstDog.id,'available');
  check('renewal requires owner confirmation',data('dogs').find(d=>d.id===firstDog.id).adoption_status==='adopted');
  await state(firstDog.id,'available',{owner_confirmed:'1'});
  check('confirmed renewal restores active listing',(await visitor('/perro/'+firstDog.slug)).status===200&&Date.parse(data('dogs').find(d=>d.id===firstDog.id).expires_at)>Date.now());
  await state(firstDog.id,'reserved');
  check('reserved status visible on detail',(await visitor('/perro/'+firstDog.slug)).html.includes('Reservado'));
  await state(firstDog.id,'reunited');
  check('adoption cannot use lost-dog reunited state',data('dogs').find(d=>d.id===firstDog.id).adoption_status==='reserved');
  await state(firstDog.id,'expired'); await state(firstDog.id,'available',{owner_confirmed:'1'});
  check('expired listing can be renewed after confirmation',(await visitor('/perro/'+firstDog.slug)).status===200);
  await state(firstDog.id,'unpublish'); await approve(submission.id);
  check('approval retry cannot reopen withdrawn listing',(await visitor('/perro/'+firstDog.slug)).status===404&&data('dogs').filter(d=>d.source_submission_id===submission.id).length===1);
  await state(firstDog.id,'available',{owner_confirmed:'1'});
  await user('/enviar-perro',{...fields,name:'LOCAL LOST DOG',listing_type:'lost',age_group:'No se sabe',size:'No se sabe',last_location:'Zona aproximada LOCAL',incident_date:'2026-10-01'});
  const lost=await approve(data('submissions').at(-1).id),lostProfile=await visitor('/perro/'+lost.slug);
  check('lost profile uses recovery context and incident facts',lostProfile.html.includes('Perro perdido')&&lostProfile.html.includes('2026-10-01')&&lostProfile.html.includes('Zona aproximada LOCAL')&&!lostProfile.html.includes('sobre su adopción responsable'));
  check('lost dog appears only in recovery listings',(await visitor('/perros-perdidos-paraguay')).html.includes(lost.name)&&!(await visitor('/perros')).html.includes(lost.name));
  await state(lost.id,'adopted');
  check('lost listing cannot be marked adopted',data('dogs').find(d=>d.id===lost.id).adoption_status==='available');
  await state(lost.id,'reunited');
  check('reunited listing removed from active recovery page',!(await visitor('/perros-perdidos-paraguay')).html.includes(lost.name)&&(await visitor('/perro/'+lost.slug)).status===404);
  profile=await visitor('/perro/'+firstDog.slug);
  await visitor('/reportar',{csrf_token:token(profile.html),slug:firstDog.slug,reason:'LOCAL TEST REPORT',contact:'LOCAL REPORT CONTACT'});
  check('public report persists privately',data('reports').at(-1).reason==='LOCAL TEST REPORT');
  await admin('/admin/action',{csrf_token:adminCsrf,dataset:'reports',id:data('reports').at(-1).id,action:'resolve'});
  check('admin resolves report',data('reports').at(-1).status==='resolved');
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
  async function multipart(entries,files) { const body=new FormData();for(const [key,value] of Object.entries(entries))body.append(key,value);for(const file of files)body.append('photos[]',new Blob([file.bytes],{type:file.type||'image/png'}),file.name);return body; }
  const beforeUploads=data('submissions').length;
  const invalid=await multipart({...fields,name:'INVALID PHOTO DOG'},[{name:'pretend.jpg',type:'image/jpeg',bytes:Buffer.from('not an image')}]);
  await user('/enviar-perro',invalid);
  check('invalid photo rejects complete submission',data('submissions').length===beforeUploads);
  const withGD=form.html.includes('name="photos[]"');
  if(withGD) {
    const image=execFileSync(php,[...gdArgs,'-r','$image=imagecreatetruecolor(12,12);imagepng($image);imagedestroy($image);']);
    const beforeOversize=data('submissions').length;
    await user('/enviar-perro',await multipart(fields,[{name:'too-large.png',bytes:Buffer.alloc(6*1024*1024)}]));
    check('over-limit individual upload rejected',data('submissions').length===beforeOversize);
    await user('/enviar-perro',await multipart(fields,Array.from({length:6},(_,i)=>({name:'extra-'+i+'.png',bytes:image}))));
    check('more than five photos rejected',data('submissions').length===beforeOversize);
    await user('/enviar-perro',await multipart({...fields,name:'LOCAL PHOTO DOG'},[{name:'owner-original.png',bytes:image}]));
    const photoSubmission=data('submissions').at(-1),filename=photoSubmission.photos[0];
    check('valid photo converted to sanitized random JPEG',photoSubmission.name==='LOCAL PHOTO DOG'&&photoSubmission.photos.length===1&&/^[a-f0-9]{20}\.jpg$/.test(filename));
    const pendingUrl='/admin/media/'+photoSubmission.id+'/'+filename;
    check('pending image never publicly accessible',(await visitor(pendingUrl)).headers.get('location')==='/admin'&&(await visitor('/media/'+photoSubmission.id+'/'+filename)).status===404);
    const pending=await admin(pendingUrl);
    check('admin reviews pending photo privately',pending.status===200&&pending.headers.get('cache-control')==='no-store'&&pending.bytes[0]===0xff&&pending.bytes[1]===0xd8);
    const editor=await admin('/admin/edit?dataset=submissions&id='+photoSubmission.id);
    check('editor renders real pending photo',editor.html.includes(pendingUrl));
    const addFields={...fields,dataset:'submissions',id:photoSubmission.id,csrf_token:adminCsrf,revision:revision(editor.html),name:photoSubmission.name,review_confirm:'1'};
    const noPermission=await multipart(addFields,[{name:'new-admin-photo.png',bytes:image}]);noPermission.append('keep_photos[]',filename);
    await admin('/admin/edit',noPermission);
    check('adding photo requires specific authorization',data('submissions').find(s=>s.id===photoSubmission.id).photos.length===1);
    const addBody=await multipart({...addFields,photo_authorized:'1'},[{name:'new-admin-photo.png',bytes:image}]);addBody.append('keep_photos[]',filename);
    await admin('/admin/edit',addBody);
    check('admin can add an authorized photo',data('submissions').find(s=>s.id===photoSubmission.id).photos.length===2);
    const published=await approve(photoSubmission.id);
    check('approved sanitized photo publicly accessible',(await visitor('/media/'+published.id+'/'+filename)).status===200);
    await admin('/admin/edit',{...fields,dataset:'submissions',id:photoSubmission.id,csrf_token:adminCsrf,revision:revision(editor.html),name:photoSubmission.name,review_confirm:'1'});
    // Approval changed the revision: first edit must fail without deleting photos.
    check('stale photo edit preserves image',fs.existsSync(path.join(copy,'storage','uploads',photoSubmission.id,filename)));
    const refreshed=await admin('/admin/edit?dataset=submissions&id='+photoSubmission.id);
    await admin('/admin/edit',{...fields,dataset:'submissions',id:photoSubmission.id,csrf_token:adminCsrf,revision:revision(refreshed.html),name:photoSubmission.name,review_confirm:'1'});
    check('photo removal updates source public record and file',data('submissions').find(s=>s.id===photoSubmission.id).photos.length===0&&data('dogs').find(d=>d.id===published.id).photos.length===0&&!fs.existsSync(path.join(copy,'storage','uploads',photoSubmission.id,filename))&&(await visitor('/media/'+published.id+'/'+filename)).status===404);
  } else {
    check('missing GD shows clear rejection', (await user('/dar-perro-en-adopcion')).html.includes('habilite GD'));
  }
  const tooBig=await multipart(fields,[{name:'oversize.png',bytes:Buffer.alloc(31*1024*1024)}]);
  check('post body limit gives actionable 413',(await user('/enviar-perro',tooBig)).status===413);
  const savedDogs=data('dogs');
  const dogsPath=path.join(copy,'storage','data','dogs.json');fs.writeFileSync(dogsPath,'{damaged');
  check('corrupt storage fails closed without overwriting',(await visitor('/perros')).status===503&&fs.readFileSync(dogsPath,'utf8')==='{damaged');
  check('corrupt dog detail renders error without recursive failure',(await visitor('/perro/'+optInDog.slug)).status===503&&fs.readFileSync(dogsPath,'utf8')==='{damaged');
  writeData('dogs',savedDogs);
  const recovered=savedDogs.map(d=>({...d,internal_note:'LOCAL JOURNAL RECOVERY'}));
  fs.writeFileSync(path.join(copy,'storage','data','transaction.json'),JSON.stringify({dogs:recovered,submissions:data('submissions')}));
  check('interrupted multi-file commit recovered before serving',(await visitor('/perros')).status===200&&data('dogs').every(d=>d.internal_note==='LOCAL JOURNAL RECOVERY')&&!fs.existsSync(path.join(copy,'storage','data','transaction.json')));
  let lastSubmissionResponse;
  for(let i=0;i<25;i++){lastSubmissionResponse=await user('/enviar-perro',{...fields,name:'LOCAL RATE TEST '+i});if(lastSubmissionResponse.status===429)break;}
  check('public submission flood is limited',lastSubmissionResponse.status===429);
  const freshUser=session(),freshForm=await freshUser('/dar-perro-en-adopcion');
  check('submission limit survives fresh cookies',(await freshUser('/enviar-perro',{...fields,csrf_token:token(freshForm.html)})).status===429);
  const reportTarget=data('dogs').find(d=>d.status==='published'&&d.adoption_status==='available');
  const reportProfile=await visitor('/perro/'+reportTarget.slug);let lastReport;
  for(let i=0;i<12;i++){lastReport=await visitor('/reportar',{csrf_token:token(reportProfile.html),slug:reportTarget.slug,reason:'LOCAL RATE REPORT'});if(lastReport.status===429)break;}
  check('public report flood is limited',lastReport.status===429);
  const secondAdmin=session(),secondLogin=await secondAdmin('/admin');await secondAdmin('/admin/login',{csrf_token:token(secondLogin.html),username:'admin',password});
  check('second admin session starts',(await secondAdmin('/admin')).html.includes('Moderación de Perro'));
  const newPassword=crypto.randomBytes(24).toString('base64url');
  await admin('/admin/action',{csrf_token:adminCsrf,action:'change_password',current_password:password,new_password:newPassword,confirm_password:newPassword});
  check('password change revokes other sessions',!(await secondAdmin('/admin')).html.includes('Moderación de Perro')&&(await admin('/admin')).html.includes('Moderación de Perro'));
  for(let i=0;i<10;i++){const attempt=session(),loginPage=await attempt('/admin');await attempt('/admin/login',{csrf_token:token(loginPage.html),username:'admin',password:'wrong-password'});}
  const blocked=session(),blockedLogin=await blocked('/admin');await blocked('/admin/login',{csrf_token:token(blockedLogin.html),username:'admin',password:newPassword});
  check('login limit survives fresh cookies',!(await blocked('/admin')).html.includes('Moderación de Perro'));
  const concurrentSubmission={...data('submissions')[0],id:'sub-concurrency-test',status:'pending',name:'LOCAL CONCURRENCY DOG'};
  const allSubmissions=data('submissions');allSubmissions.push(concurrentSubmission);writeData('submissions',allSubmissions);
  const workerScript="require 'includes/bootstrap.php';require 'includes/data.php';require 'includes/legal.php';require 'includes/moderation.php';$_POST=['review_confirm'=>'1'];echo json_encode(moderate_record('submissions','sub-concurrency-test','approve'));";
  const workerResults=await Promise.all(Array.from({length:6},()=>new Promise((resolve,reject)=>{
    const worker=spawn(php,['-r',workerScript],{cwd:copy,windowsHide:true});let stdout='',stderr='';worker.stdout.on('data',x=>stdout+=x);worker.stderr.on('data',x=>stderr+=x);worker.on('error',reject);worker.on('exit',code=>code===0&&!stderr?resolve(JSON.parse(stdout)):reject(Error('Concurrent worker failed: '+stderr)));
  })));
  check('six independent PHP workers approve once safely',workerResults.every(result=>result[0]==='success')&&data('dogs').filter(d=>d.source_submission_id===concurrentSubmission.id).length===1&&data('submissions').find(s=>s.id===concurrentSubmission.id).status==='approved');
  logs=logs.replace(/PHP Warning:  POST Content-Length of[^\r\n]+/g,'');
  check('no PHP warnings or fatal errors',!/(?:PHP (?:Warning|Fatal error)|Uncaught)/.test(logs));
  console.log(checks+' checks passed; disposable copy: '+copy);
}
run().catch(error=>{console.error(error.message);process.exitCode=1;}).finally(()=>{if(processHandle)processHandle.kill();});
