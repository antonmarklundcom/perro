// Real Chrome interactions against disposable synthetic storage; no production data or external delivery.
const fs=require('node:fs'),os=require('node:os'),path=require('node:path'),crypto=require('node:crypto'),net=require('node:net');
const {spawn,execFileSync}=require('node:child_process');
const assert=require('node:assert/strict');
const {chromium}=require(process.env.PERRO_PLAYWRIGHT_MODULE||'C:/Users/anton/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const root=path.resolve(__dirname,'..'),copy=fs.mkdtempSync(path.join(os.tmpdir(),'perro-retention-browser-'));
const screens=process.env.PERRO_BROWSER_SCREEN_DIR||'C:/AI research and to do/perro-next/browser';
const php=process.env.PERRO_PHP_BIN||'php',password=crypto.randomBytes(24).toString('hex');
const env={...process.env,PERRO_ADMIN_PASSWORD_SHA256:crypto.createHash('sha256').update(password).digest('hex')};
const includes="require_once 'includes/bootstrap.php';require_once 'includes/data.php';require_once 'includes/access.php';";
fs.cpSync(root,copy,{recursive:true,filter:p=>{const r=path.relative(root,p).replaceAll('\\','/');return !r.split('/').includes('.git')&&!r.startsWith('storage/data')&&!r.startsWith('storage/uploads/')&&!r.startsWith('storage/cache');}});
const phpRun=code=>execFileSync(php,['-r',includes+code],{cwd:copy,env,encoding:'utf8',windowsHide:true});
const read=n=>JSON.parse(fs.readFileSync(path.join(copy,'storage/data',n+'.json'),'utf8'));
const storageKey='perro-saved-dogs:v1';
let browser,server,checks=0;
const check=(name,ok)=>{assert.ok(ok,name);checks++;console.log('PASS '+name)};
const screenshot=async(page,name)=>{await page.evaluate(()=>{if(document.activeElement instanceof HTMLElement)document.activeElement.blur();window.scrollTo({top:0,left:0,behavior:'instant'});});await page.screenshot({path:path.join(screens,name),fullPage:true});};
async function run(){
 phpRun("$now=now_iso();$dogs=[];$subs=[];foreach(['uno','dos'] as $n){$id='dog-browser-'.$n;$sid='sub-browser-'.$n;$dogs[]=['id'=>$id,'source_submission_id'=>$sid,'slug'=>'synthetic-browser-'.$n,'name'=>'Aviso sintético '.$n,'listing_type'=>'adoption','status'=>'published','adoption_status'=>'available','department'=>'Central','city'=>'Luque','age_group'=>'Adulto','approximate_age'=>'','sex'=>'Macho','size'=>'Mediano','breed_label'=>'','mixed_breed'=>true,'description'=>'Aviso ficticio usado únicamente para comprobar la interfaz en un servidor local.','photos'=>[],'public_name'=>false,'contact_name'=>'','contact_whatsapp'=>'','created_at'=>$now,'published_at'=>$now,'updated_at'=>$now,'last_confirmed_at'=>$now,'expires_at'=>date(DATE_ATOM,time()+86400)];$subs[]=['id'=>$sid,'status'=>'approved','published_dog_id'=>$id,'submitter_name'=>'SYNTHETIC PRIVATE OWNER','email'=>'synthetic-private@example.invalid','whatsapp'=>'595981123456','created_at'=>$now,'updated_at'=>$now];}commit_datasets(['dogs'=>$dogs,'submissions'=>$subs,'owner_actions'=>[],'settings'=>[['id'=>'admin-user-'.hash('sha256','synthetic-team@example.invalid'),'email'=>'synthetic-team@example.invalid','name'=>'Synthetic team','role'=>'moderator','active'=>true,'disabled'=>false,'password_hash'=>password_hash('synthetic-password-long',PASSWORD_DEFAULT),'created_at'=>$now]]]);");
 const sock=net.createServer();await new Promise(r=>sock.listen(0,'127.0.0.1',r));const port=sock.address().port;await new Promise(r=>sock.close(r));const base='http://127.0.0.1:'+port;
 server=spawn(php,['-S','127.0.0.1:'+port,'router.php'],{cwd:copy,env,windowsHide:true,stdio:'ignore'});
 for(let i=0;i<60;i++){try{if((await fetch(base+'/health')).ok)break}catch{}await new Promise(r=>setTimeout(r,100));}
 browser=await chromium.launch({headless:true,executablePath:process.env.PERRO_CHROME_BIN||'C:/Program Files/Google/Chrome/Application/chrome.exe'});
 fs.mkdirSync(screens,{recursive:true});
 if(process.env.PERRO_BROWSER_SCREEN_ONLY==='1'){
  for(const width of [375,1440]){
   const context=await browser.newContext({viewport:{width,height:900}});const page=await context.newPage();await page.goto(base+'/perros');
   await page.evaluate(key=>localStorage.setItem(key,'["synthetic-browser-uno"]'),storageKey);await page.reload();await page.locator('[data-save-dog="synthetic-browser-uno"][aria-pressed="true"]').waitFor();await screenshot(page,'retention-listing-'+width+'.png');
   await page.goto(base+'/guardados');await page.locator('[data-saved-list] a').waitFor();await screenshot(page,'retention-saved-'+width+'.png');await context.close();
  }
  phpRun("$dog=find_record('dogs','dog-browser-uno');$dog['status']='removed';$dog['updated_at']=now_iso();save_record('dogs',$dog);");
  const context=await browser.newContext({viewport:{width:375,height:900}});const page=await context.newPage();await page.goto(base+'/guardados');await page.evaluate(key=>localStorage.setItem(key,'["synthetic-browser-uno"]'),storageKey);await page.reload();await page.waitForFunction(()=>document.querySelector('[data-saved-list]').textContent.includes('ya no está disponible'));await screenshot(page,'retention-withdrawn-375.png');await context.close();console.log('Five synthetic public screenshots refreshed: '+screens);return;
 }
 const errors=[];
 for(const width of [375,1440]){
  const context=await browser.newContext({viewport:{width,height:900}});const page=await context.newPage();page.on('pageerror',e=>errors.push(e.message));
  await page.goto(base+'/perros');await page.locator('[data-save-dog]').first().waitFor({state:'visible'});
  check(width+' listing has no horizontal overflow',await page.evaluate(()=>document.documentElement.scrollWidth<=window.innerWidth+1));
  const save=page.locator('[data-save-dog="synthetic-browser-uno"]');check(width+' save control outside card hyperlink',await save.evaluate(el=>el.closest('a')===null&&el.closest('article')!==null));
  await save.click();check(width+' actual save click stays on listing',new URL(page.url()).pathname==='/perros'&&await save.getAttribute('aria-pressed')==='true');
  check(width+' storage contains only a slug array',await page.evaluate(key=>localStorage.getItem(key)==='["synthetic-browser-uno"]',storageKey));
  await screenshot(page,'retention-listing-'+width+'.png');
  await page.goto(base+'/guardados');await page.locator('[data-saved-list] a').waitFor();check(width+' saved page resolves current public state',await page.locator('[data-saved-list] a').getAttribute('href')==='/perro/synthetic-browser-uno');
  check(width+' saved page no horizontal overflow',await page.evaluate(()=>document.documentElement.scrollWidth<=window.innerWidth+1));
  await screenshot(page,'retention-saved-'+width+'.png');
  await page.locator('[data-saved-list] button').click();check(width+' remove clears saved reference',await page.evaluate(key=>localStorage.getItem(key)==='[]',storageKey));
  await page.goto(base+'/perros');const button=page.locator('[data-save-dog="synthetic-browser-uno"]');await button.waitFor({state:'visible'});await button.focus();await page.keyboard.press('Enter');check(width+' keyboard Enter activates save',await button.getAttribute('aria-pressed')==='true');
  await page.locator('[data-save-dog="synthetic-browser-dos"]').click();check(width+' saved storage excludes names contacts and snapshots',await page.evaluate(key=>{const v=JSON.parse(localStorage.getItem(key));return v.length===2&&v.every(x=>typeof x==='string'&&x.startsWith('synthetic-browser-'));},storageKey));
  await page.goto(base+'/guardados');await page.waitForFunction(()=>document.querySelectorAll('[data-saved-list] a').length===2);
  await context.setOffline(true);await page.evaluate(()=>window.dispatchEvent(new Event('pageshow')));await page.waitForFunction(()=>document.querySelector('[data-saved-status]').textContent.includes('No pudimos confirmar'));
  check(width+' offline state offers no stale contact or dog link',await page.locator('[data-saved-list] a').count()===0);
  await page.locator('[data-saved-list] button').first().click();check(width+' remove still works offline',await page.evaluate(key=>JSON.parse(localStorage.getItem(key)).length===1,storageKey));
  await page.locator('[data-saved-clear]').click();check(width+' clear still works offline',await page.evaluate(key=>localStorage.getItem(key)===null,storageKey));await context.setOffline(false);
  await page.goto(base+'/perros');await page.locator('[data-save-dog="synthetic-browser-uno"]').click();
  const second=await context.newPage();await second.goto(base+'/guardados');await second.locator('[data-saved-list] a').waitFor();await page.bringToFront();await page.locator('[data-save-dog="synthetic-browser-uno"]').click();await second.bringToFront();await second.waitForFunction(()=>document.querySelectorAll('[data-saved-list] li').length===0);check(width+' changes synchronize across tabs',true);await second.close();
  await context.close();
 }
 const context=await browser.newContext({viewport:{width:375,height:900}});const page=await context.newPage();await page.goto(base+'/perros');await page.locator('[data-save-dog="synthetic-browser-uno"]').click();
 phpRun("$dog=find_record('dogs','dog-browser-uno');$dog['status']='removed';$dog['updated_at']=now_iso();save_record('dogs',$dog);");
 await page.goto(base+'/guardados');await page.waitForFunction(()=>document.querySelector('[data-saved-list]').textContent.includes('ya no está disponible'));
 const saved=page.locator('[data-saved-list]');check('withdrawn saved entry has no dog or contact link',await saved.locator('a').count()===0&&!(await saved.innerText()).includes('SYNTHETIC PRIVATE OWNER')&&!(await saved.innerText()).includes('595981123456'));
 await screenshot(page,'retention-withdrawn-375.png');check('withdrawn state endpoint only exposes slug and unavailable',JSON.stringify(await (await fetch(base+'/guardados/estado?slugs=synthetic-browser-uno')).json())==='{"items":[{"slug":"synthetic-browser-uno","available":false}]}');await context.close();
 const nojs=await browser.newContext({javaScriptEnabled:false,viewport:{width:375,height:900}});const plain=await nojs.newPage();await plain.goto(base+'/guardados');check('noscript explains browser feature and offers active notices',await plain.locator('noscript').isVisible()&&await plain.locator('noscript a').getAttribute('href')==='/perros');await plain.goto(base+'/perros');check('noscript keeps listing usable without dead save controls',await plain.locator('a[href="/perro/synthetic-browser-dos"]').count()>0&&!await plain.locator('[data-save-dog]').first().isVisible());await nojs.close();
 const team=await browser.newContext({viewport:{width:375,height:900}});const admin=await team.newPage();await admin.goto(base+'/admin');await admin.locator('[name="username"]').fill('synthetic-team@example.invalid');await admin.locator('[name="password"]').fill('synthetic-password-long');await Promise.all([admin.waitForURL('**/admin'),admin.locator('form[action="/admin/login"] button[type="submit"]').click()]);
 await admin.goto(base+'/admin?section=publicadas');check('moderator CSV and account management links hidden',await admin.locator('a[href="/admin/export.csv"]').count()===0&&await admin.locator('a[href="/admin/accounts"]').count()===0);
 const form=admin.locator('form[action="/admin/owner-link"]');check('eligible published dog exposes owner link form only once',await form.count()===1);await form.locator('xpath=..').locator('summary').click();check('owner form controls visible and scope constrained',await form.locator('[name="verified_contact"]').isVisible()&&await form.locator('select option').evaluateAll(es=>es.map(e=>e.value).sort().join(',')==='confirm,withdraw'));
 check('mobile admin owner form no overflow',await admin.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
 check('moderator direct CSV endpoint denied',(await team.request.get(base+'/admin/export.csv')).status()===403);
 await team.close();
 const primary=await browser.newContext({viewport:{width:1440,height:900}});const owner=await primary.newPage();await owner.goto(base+'/admin');await owner.locator('[name="username"]').fill('admin');await owner.locator('[name="password"]').fill(password);await Promise.all([owner.waitForURL('**/admin'),owner.locator('form[action="/admin/login"] button[type="submit"]').click()]);await owner.goto(base+'/admin/accounts');check('primary account UI has supported role choices',await owner.locator('select[name="role"]').first().locator('option').evaluateAll(es=>es.map(e=>e.value).sort().join(',')==='manager,moderator'));await primary.close();
 check('browser JavaScript emits no uncaught errors',errors.length===0);console.log(checks+' browser checks passed. Screenshots: '+screens);
}
run().catch(e=>{console.error(e);process.exitCode=1}).finally(async()=>{if(browser)await browser.close();if(server&&server.exitCode===null){const stopped=new Promise(r=>server.once('exit',r));server.kill();await stopped;}fs.rmSync(copy,{recursive:true,force:true,maxRetries:10,retryDelay:100});});
