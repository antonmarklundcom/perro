// Read-only post-deploy check. Run explicitly: node tools/check-release.cjs https://perro.com.py perro-EXPECTED
const [base,release]=process.argv.slice(2);
if (!base || !release || !/^perro-[a-f0-9]{12}$/.test(release)) throw Error('Provide deployment URL and expected local X-Perro-Release.');
const origin=new URL(base);if(origin.protocol!=='https:'&&!['localhost','127.0.0.1'].includes(origin.hostname)) throw Error('Remote checks require HTTPS.');
(async()=>{
 let failed=false;
 for(const path of ['/health','/perros','/terminos','/privacidad']){
  const response=await fetch(new URL(path,origin),{signal:AbortSignal.timeout(20000),redirect:'error'});
  const assertions={status:response.status===200,release:response.headers.get('x-perro-release')===release,csp:!!response.headers.get('content-security-policy')?.includes("default-src 'self'"),phpVersionHidden:!response.headers.get('x-powered-by'),hsts:origin.protocol!=='https:'||!!response.headers.get('strict-transport-security')};
  if(path==='/health')assertions.body=(await response.text())==='{"status":"ok"}';
  const ok=Object.values(assertions).every(Boolean);failed||=!ok;console.log(JSON.stringify({path,ok,assertions}));
 }
 if(failed)process.exitCode=1;
})().catch(error=>{console.error(error.message);process.exitCode=1;});
