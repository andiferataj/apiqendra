import { test } from 'node:test';
import assert from 'node:assert/strict';
import { SessionClient } from './http-client.mjs';

const base='http://127.0.0.1:8084';
const reset=(code='ECONNRESET')=>new TypeError('fetch failed',{cause:Object.assign(new Error('connection interrupted'),{code})});
const json=(body,status=200)=>Response.json(body,{status});

test('authenticated read reconnects after restore without losing session or CSRF state',async()=>{
 const calls=[];
 const client=new SessionClient(base,{fetch:async(url,options)=>{
  calls.push({url,...options});
  if(calls.length===1)throw reset();
  return json([{id:'workspace',role:'owner'}]);
 }});
 client.cookies.set('apiqendra_session','synthetic-cookie');client.csrf='synthetic-csrf';client.workspace='workspace';
 assert.deepEqual(await client.request('/api/v1/workspaces'),[{id:'workspace',role:'owner'}]);
 assert.equal(calls.length,2);
 for(const call of calls){
  assert.equal(call.url,base+'/api/v1/workspaces');
  assert.equal(call.headers.Cookie,'apiqendra_session=synthetic-cookie');
  assert.equal(call.headers['X-CSRF-TOKEN'],'synthetic-csrf');
  assert.equal(call.headers['X-Workspace-ID'],'workspace');
  assert.equal(call.headers.Connection,'close');
 }
 assert.notEqual(calls[0].signal,calls[1].signal);
});

test('interrupted read body reconnects before parsing or changing cookies',async()=>{
 let calls=0;
 const client=new SessionClient(base,{fetch:async()=>{
  calls++;
  if(calls===1)return {headers:{getSetCookie:()=>['apiqendra_session=partial']},text:async()=>{throw reset('UND_ERR_SOCKET');}};
  return json({status:'ready'});
 }});
 client.cookies.set('apiqendra_session','original');
 assert.equal((await client.request('/health/ready')).status,'ready');
 assert.equal(calls,2);
 assert.equal(client.cookies.get('apiqendra_session'),'original');
});

test('connection failures never replay writes even if the server might have committed',async()=>{
 for(const method of ['POST','PUT','PATCH','DELETE']){
  let calls=0;
  const client=new SessionClient(base,{fetch:async()=>{calls++;throw reset();}});
  await assert.rejects(client.request('/api/v1/workspaces',{method,data:{name:'Synthetic'}}),error=>{
   assert.match(error.message,new RegExp('^'+method+' /api/v1/workspaces transport failed after 1 attempt'));
   assert.equal(error.cause.cause.code,'ECONNRESET');return true;
  });
  assert.equal(calls,1);
 }
});

test('lost response body after a successful write never replays that mutation',async()=>{
 let calls=0;
 const client=new SessionClient(base,{fetch:async()=>{calls++;return {status:201,text:async()=>{throw reset();}};}});
 await assert.rejects(client.request('/api/v1/workspaces',{method:'POST',data:{name:'Synthetic'}}),/transport failed after 1 attempt/);
 assert.equal(calls,1);
});

test('permanent read transport failure is bounded and includes endpoint and root cause',async()=>{
 let calls=0;
 const client=new SessionClient(base,{fetch:async()=>{calls++;throw reset();}});
 await assert.rejects(client.request('/api/v1/workspaces'),error=>{
  assert.equal(error.message,'GET /api/v1/workspaces transport failed after 3 attempt(s) (ECONNRESET).');
  assert.equal(error.cause.cause.code,'ECONNRESET');return true;
 });
 assert.equal(calls,3);
});

test('HTTP authentication, permission, validation and server errors are never retried',async()=>{
 for(const status of [401,403,404,419,422,429,500,502,503]){
  let calls=0;
  const client=new SessionClient(base,{fetch:async()=>{calls++;return json({message:'Deliberate rejection'},status);}});
  await assert.rejects(client.request('/api/v1/workspaces'),new RegExp('returned '+status+': Deliberate rejection'));
  assert.equal(calls,1);
 }
});

test('certificate, DNS, timeout and unclassified failures remain immediate failures',async()=>{
 for(const error of [reset('CERT_HAS_EXPIRED'),reset('ENOTFOUND'),new DOMException('Timed out','TimeoutError'),new Error('unexpected failure')]){
  let calls=0;
  const client=new SessionClient(base,{fetch:async()=>{calls++;throw error;}});
  await assert.rejects(client.request('/api/v1/workspaces'),error=>error.cause!==undefined);
  assert.equal(calls,1);
 }
});

test('session cookies and refreshed CSRF token remain available to subsequent writes',async()=>{
 const calls=[];
 const client=new SessionClient(base,{fetch:async(url,options)=>{
  calls.push({url,...options});
  if(calls.length===1){
   const response=json({csrf_token:'synthetic-token'});
   response.headers.append('Set-Cookie','apiqendra_session=synthetic-session; HttpOnly; Path=/');
   return response;
  }
  return json({id:'created'},201);
 }});
 await client.request('/api/v1/session');
 await client.request('/api/v1/workspaces',{method:'post',data:{name:'Synthetic'}});
 assert.equal(calls[1].method,'POST');
 assert.equal(calls[1].headers.Cookie,'apiqendra_session=synthetic-session');
 assert.equal(calls[1].headers['X-CSRF-TOKEN'],'synthetic-token');
 assert.equal(calls[1].body,JSON.stringify({name:'Synthetic'}));
 assert.equal(calls[1].redirect,'error');
});
