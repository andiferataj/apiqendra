import assert from 'node:assert/strict';
import { setTimeout as pause } from 'node:timers/promises';

const transientReadErrors=new Set(['ECONNRESET','ECONNREFUSED','ECONNABORTED','EPIPE','UND_ERR_SOCKET']);

export class SessionClient {
 constructor(baseUrl,{fetch:fetchImpl=globalThis.fetch}={}){
  this.baseUrl=baseUrl;this.fetch=fetchImpl;this.cookies=new Map();this.csrf='';this.workspace='';
 }
 async request(path,{method='GET',data,headers={}}={}){
  method=method.toUpperCase();
  // This client deliberately restarts containers. Do not retain sockets across restarts.
  const fullHeaders={Accept:'application/json',...headers,Connection:'close'};
  if(this.csrf)fullHeaders['X-CSRF-TOKEN']=this.csrf;
  if(this.workspace)fullHeaders['X-Workspace-ID']=this.workspace;
  if(this.cookies.size)fullHeaders.Cookie=[...this.cookies].map(([key,value])=>key+'='+value).join('; ');
  if(data!==undefined)fullHeaders['Content-Type']='application/json';
  const safeRead=method==='GET'||method==='HEAD';
  let response,text;
  for(let attempt=1;;attempt++){
   try{
    response=await this.fetch(this.baseUrl+path,{method,headers:fullHeaders,body:data===undefined?undefined:JSON.stringify(data),redirect:'error',signal:AbortSignal.timeout(20000)});
    text=await response.text();
    break;
   }catch(error){
    const code=error?.cause?.code??error?.code;
    if(!safeRead||!transientReadErrors.has(code)||attempt>=3){
     throw new Error(method+' '+path+' transport failed after '+attempt+' attempt(s)'+(code?' ('+code+')':'')+'.',{cause:error});
    }
    // A read can reconnect after a restart. A write may already have committed.
    await pause(attempt*150);
   }
  }
  for(const header of response.headers.getSetCookie()){
   const cookie=header.split(';',1)[0],separator=cookie.indexOf('=');
   if(separator>0)this.cookies.set(cookie.slice(0,separator),cookie.slice(separator+1));
  }
  let body;
  try{body=JSON.parse(text);}catch{body=text;}
  if(!response.ok)throw new Error(method+' '+path+' returned '+response.status+': '+(body&&typeof body==='object'?body.message:'unreadable response'));
  if(body?.csrf_token)this.csrf=body.csrf_token;
  return body;
 }
 async register(email){
  await this.request('/api/v1/session');
  await this.request('/api/v1/register',{method:'POST',data:{name:'Release Acceptance',email,password:'synthetic-release-password-123',password_confirmation:'synthetic-release-password-123'}});
  const spaces=await this.request('/api/v1/workspaces');
  assert.ok(spaces.length);
  this.workspace=spaces[0].id;
  return spaces[0];
 }
}
export async function until(read,predicate,{timeout=100000,interval=1500,label='expected state'}={}){
 const deadline=Date.now()+timeout;
 while(Date.now()<deadline){
  const value=await read();
  if(predicate(value))return value;
  await pause(interval);
 }
 throw new Error('Timed out waiting for '+label);
}
