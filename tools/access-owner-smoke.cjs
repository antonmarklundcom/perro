// Synthetic fixtures in a disposable copy; never touches deployed storage or sends messages.
const fs=require('node:fs'),os=require('node:os'),path=require('node:path'),crypto=require('node:crypto'),net=require('node:net');
const {spawn,execFileSync}=require('node:child_process');
const assert=require('node:assert/strict');
const root=path.resolve(__dirname,'..'),copy=fs.mkdtempSync(path.join(os.tmpdir(),'perro-access-owner-'));
fs.cpSync(root,copy,{recursive:true,filter:p=>{const r=path.relative(root,p).replaceAll('\\','/');return !r.split('/').includes('.git')&&!r.startsWith('storage/data')&&!r.startsWith('storage/uploads/')&&!r.startsWith('storage/cache');}});
const php=process.env.PERRO_PHP_BIN||'php',secret=crypto.randomBytes(24).toString('hex');
const env={...process.env,PERRO_ADMIN_PASSWORD_SHA256:crypto.createHash('sha256').update(secret).digest('hex')};
const requires="require_once 'includes/bootstrap.php';require_once 'includes/data.php';require_once 'includes/render.php';require_once 'includes/moderation.php';require_once 'includes/access.php';require_once 'includes/accounts.php';require_once 'includes/owner-updates.php';";
const phpRun=code=>execFileSync(php,['-r',requires+code],{cwd:copy,env,encoding:'utf8',windowsHide:true});
const main=`
$checks=[]; function test_owner($name,$ok){global $checks;if(!$ok)throw new RuntimeException($name);$checks[]=$name;}
$_SESSION=['perro_admin'=>true,'admin_account_id'=>'admin','admin_started'=>time(),'admin_last_seen'=>time(),'admin_version'=>admin_credential_version('admin')];
$now=now_iso(); $dog=['id'=>'dog-owner-test','source_submission_id'=>'sub-owner-test','slug'=>'synthetic-owner-test','name'=>'SYNTHETIC DOG','listing_type'=>'adoption','status'=>'published','adoption_status'=>'available','created_at'=>$now,'updated_at'=>$now,'published_at'=>$now,'last_confirmed_at'=>$now,'expires_at'=>date(DATE_ATOM,time()+3600),'description'=>'Synthetic dog only, never a real animal or real owner.','photos'=>[]];
$source=['id'=>'sub-owner-test','status'=>'approved','published_dog_id'=>$dog['id'],'whatsapp'=>'595981123456','email'=>'private-sentinel@example.invalid','submitter_name'=>'PRIVATE OWNER SENTINEL','created_at'=>$now,'updated_at'=>$now];
commit_datasets(['dogs'=>[$dog],'submissions'=>[$source],'owner_actions'=>[]]);
test_owner('primary has every supported capability',admin_has_capability('manage_accounts')&&admin_has_capability('export_private')&&admin_has_capability('permanent_delete'));
$inv=invite_admin_account('legacy@example.invalid','Synthetic teammate');test_owner('invitation defaults to moderator',$inv[0]==='success'&&admin_accounts()[0]['role']==='moderator');
test_owner('unsupported role rejected',invite_admin_account('bad@example.invalid','Synthetic bad','primary')[0]==='error');
$a=admin_accounts()[0];$a['active']=true;$a['password_hash']=password_hash('synthetic-password-long',PASSWORD_DEFAULT);unset($a['role']);persist_admin_account($a,'fixture');
$_SESSION['admin_account_id']=$a['id'];$_SESSION['admin_version']=admin_credential_version();
test_owner('legacy moderator preserves moderation',admin_has_capability('moderate')&&admin_has_capability('edit')&&admin_has_capability('photos')&&admin_has_capability('owner_links'));
test_owner('legacy moderator has no private export deletion or account management',!admin_has_capability('export_private')&&!admin_has_capability('permanent_delete')&&!admin_has_capability('manage_accounts'));
test_owner('moderator cannot grant themselves manager',change_admin_role($a['id'],'manager')[0]==='error');
$_SESSION['admin_account_id']='admin';$_SESSION['admin_version']=admin_credential_version();change_admin_role($a['id'],'manager');
$_SESSION['admin_account_id']=$a['id'];$_SESSION['admin_version']=admin_credential_version();
test_owner('primary grant enables manager capabilities',admin_has_capability('export_private')&&admin_has_capability('permanent_delete')&&!admin_has_capability('manage_accounts'));
$_SESSION['admin_account_id']='admin';$_SESSION['admin_version']=admin_credential_version();change_admin_role($a['id'],'moderator');
test_owner('contact verification mandatory',create_owner_action_link($dog['id'],'confirm',$source['email'],false)[0]==='error');
test_owner('wrong contact rejected',create_owner_action_link($dog['id'],'confirm','someone-else@example.invalid',true)[0]==='error');
$issue=create_owner_action_link($dog['id'],'confirm',$source['email'],true);test_owner('verified owner link issued',$issue[0]==='success');parse_str(parse_url($issue[2],PHP_URL_QUERY),$query);$token=$query['token'];
test_owner('raw token and private contacts never persisted in owner actions',!str_contains(json_encode(read_dataset('owner_actions')),$token)&&!str_contains(json_encode(read_dataset('owner_actions')),$source['email'])&&!str_contains(json_encode(read_dataset('owner_actions')),$source['whatsapp']));
$before=read_dataset('dogs');test_owner('preview does not mutate dog',find_owner_action($token)!==null&&read_dataset('dogs')===$before);
test_owner('scope cannot be changed',apply_owner_action($token,'withdraw')[0]==='error'&&find_record('dogs',$dog['id'])['status']==='published');
$consents=find_record('submissions',$source['id']);test_owner('available confirmation succeeds',apply_owner_action($token,'confirm')[0]==='success');
test_owner('confirmation preserves private source and contact permissions',find_record('submissions',$source['id'])===$consents);
test_owner('confirmation consumed exactly once',apply_owner_action($token,'confirm')[0]==='error');
$issue=create_owner_action_link($dog['id'],'withdraw','0981 123 456',true);parse_str(parse_url($issue[2],PHP_URL_QUERY),$query);$withdraw=$query['token'];
test_owner('withdrawal immediately hides listing',apply_owner_action($withdraw,'withdraw')[0]==='success'&&public_dogs()===[]);
test_owner('withdrawal preserves moderation history',count(read_dataset('moderation'))>=4&&end($GLOBALS['perro_datasets']['moderation'])['action']==='owner_withdrawal_requested');
test_owner('withdrawal cannot reopen listing',apply_owner_action($withdraw,'confirm')[0]==='error');
commit_datasets(['dogs'=>[$dog],'owner_actions'=>[]]);$issue=create_owner_action_link($dog['id'],'confirm',$source['email'],true);parse_str(parse_url($issue[2],PHP_URL_QUERY),$query);$old=$query['token'];
$issue=create_owner_action_link($dog['id'],'withdraw',$source['email'],true);parse_str(parse_url($issue[2],PHP_URL_QUERY),$query);$fresh=$query['token'];
test_owner('new issuance revokes prior link',find_owner_action($old)===null&&find_owner_action($fresh)!==null);
$changed=find_record('submissions',$source['id']);$changed['email']='changed@example.invalid';save_record('submissions',$changed);test_owner('changed source invalidates outstanding link',find_owner_action($fresh)===null);save_record('submissions',$source);
$pending=$source;$pending['status']='pending';save_record('submissions',$pending);test_owner('owner link cannot approve pending source',apply_owner_action($fresh,'withdraw')[0]==='error'&&find_record('submissions',$source['id'])['status']==='pending');save_record('submissions',$source);
$adopted=$dog;$adopted['adoption_status']='adopted';save_record('dogs',$adopted);test_owner('outstanding owner link cannot reopen adopted dog',apply_owner_action($fresh,'withdraw')[0]==='error'&&find_record('dogs',$dog['id'])['adoption_status']==='adopted');save_record('dogs',$dog);
$rows=read_dataset('owner_actions');foreach($rows as &$r)$r['expires_at']=time()-1;unset($r);commit_datasets(['owner_actions'=>$rows]);test_owner('expired link cannot mutate',find_owner_action($fresh)===null&&apply_owner_action($fresh,'withdraw')[0]==='error');
foreach([['status'=>'pending'],['status'=>'expired'],['adoption_status'=>'adopted'],['adoption_status'=>'reserved'],['expires_at'=>date(DATE_ATOM,time()-1)]] as $change){$closed=array_replace($dog,$change);save_record('dogs',$closed);test_owner('closed or unavailable state rejected '.json_encode($change),create_owner_action_link($dog['id'],'confirm',$source['email'],true)[0]==='error');}
save_record('dogs',$dog);commit_datasets(['owner_actions'=>[]]);$issue=create_owner_action_link($dog['id'],'confirm',$source['email'],true);parse_str(parse_url($issue[2],PHP_URL_QUERY),$query);
$_SERVER['REMOTE_ADDR']='192.0.2.222';for($i=0;$i<30;$i++)test_owner('rate allowance '.($i+1),owner_action_rate_allowed());test_owner('rate limit enforced',!owner_action_rate_allowed());test_owner('rate records exclude raw address and contacts',!str_contains(json_encode(read_dataset('security')),'192.0.2.222')&&!str_contains(json_encode(read_dataset('security')),$source['email']));
echo json_encode(['checks'=>$checks,'token'=>$query['token']]);
`;
let server,checks=0;
const check=(name,ok)=>{assert.ok(ok,name);checks++;console.log('PASS '+name)};
const read=n=>JSON.parse(fs.readFileSync(path.join(copy,'storage/data',n+'.json'),'utf8'));
async function run(){
 const result=JSON.parse(phpRun(main));for(const name of result.checks)check(name,true);
 const sock=net.createServer();await new Promise(r=>sock.listen(0,'127.0.0.1',r));const port=sock.address().port;await new Promise(r=>sock.close(r));
 server=spawn(php,['-S','127.0.0.1:'+port,'router.php'],{cwd:copy,env,windowsHide:true,stdio:'ignore'});
 function client(){let cookie='';return async(url,fields)=>{const res=await fetch('http://127.0.0.1:'+port+url,{method:fields?'POST':'GET',body:fields?new URLSearchParams(fields):undefined,redirect:'manual',headers:{cookie}});const set=res.headers.getSetCookie();if(set.length)cookie=set.map(c=>c.split(';')[0]).join('; ');return {status:res.status,html:await res.text(),headers:res.headers};};}
 const visit=client();for(let i=0;i<50;i++){try{await visit('/health');break}catch{await new Promise(r=>setTimeout(r,100));}}
 const before=JSON.stringify(read('dogs'));const preview=await visit('/actualizar-aviso?token='+result.token);check('GET strips bearer token from URL',preview.status===303&&preview.headers.get('location')==='/actualizar-aviso');
 const form=await visit('/actualizar-aviso');check('GET offers scoped form without owner identity',form.status===200&&form.html.includes('name="scope" value="confirm"')&&!form.html.includes('PRIVATE OWNER SENTINEL')&&!form.html.includes('private-sentinel@example.invalid')&&!form.html.includes('595981123456'));check('GET never confirms listing',JSON.stringify(read('dogs'))===before);
 check('POST requires CSRF',(await visit('/actualizar-aviso',{scope:'confirm'})).status===419&&JSON.stringify(read('dogs'))===before);
 const csrf=form.html.match(/name="csrf_token" value="([^"]+)"/)[1];check('valid scoped POST succeeds',(await visit('/actualizar-aviso',{scope:'confirm',csrf_token:csrf})).html.includes('Cambio guardado'));check('used token returns generic unavailable',(await visit('/actualizar-aviso?token='+result.token)).status===404);
 const invalid=await visit('/actualizar-aviso?token='+('a'.repeat(64)));check('missing or expired tokens reveal no identity',invalid.status===404&&!invalid.html.includes('PRIVATE OWNER SENTINEL')&&!invalid.html.includes('private-sentinel@example.invalid'));
 const admin=client();const login=await admin('/admin');const loginCsrf=login.html.match(/name="csrf_token" value="([^"]+)"/)[1];await admin('/admin/login',{csrf_token:loginCsrf,username:'legacy@example.invalid',password:'synthetic-password-long'});check('legacy moderator route export denied',(await admin('/admin/export.csv')).status===403);check('legacy moderator account management denied',(await admin('/admin/accounts')).status===403);check('legacy moderator delete denied',(await admin('/admin/delete',{csrf_token:loginCsrf,id:'sub-owner-test',delete_confirm:'1'})).status===403);
 console.log(checks+' checks passed.');
}
run().catch(e=>{console.error(e);process.exitCode=1}).finally(async()=>{if(server&&server.exitCode===null){const stopped=new Promise(r=>server.once('exit',r));server.kill();await stopped;}fs.rmSync(copy,{recursive:true,force:true,maxRetries:10,retryDelay:100});});
