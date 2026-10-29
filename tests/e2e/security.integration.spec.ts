import { test,expect,request as requests,type APIRequestContext } from '@playwright/test';
import { signWebhook } from '../../packages/contracts/signing.mjs';
import { randomUUID } from 'node:crypto';
const base=process.env.E2E_BASE_URL??'http://localhost:8084';
async function register(context:APIRequestContext,email:string){
 const session=await (await context.get('/api/v1/session')).json();
 const response=await context.post('/api/v1/register',{headers:{'X-CSRF-TOKEN':session.csrf_token},data:{name:'Security Reviewer',email,password:'synthetic-security-password-123',password_confirmation:'synthetic-security-password-123'}});
 expect(response.status()).toBe(201);
 const auth=await response.json();
 const workspaces=await (await context.get('/api/v1/workspaces')).json();
 return {csrf:auth.csrf_token,workspace:workspaces[0].id};
}
test('real session CSRF, tenant boundaries, viewer denials and unsafe egress',async()=>{
 const a=await requests.newContext({baseURL:base}),b=await requests.newContext({baseURL:base});
 try{
  await a.get('/api/v1/session');
  const rejected=await a.post('/api/v1/register',{data:{name:'No token'}});
  expect(rejected.status()).toBe(419);
  const suffix=randomUUID(),emailB='viewer-'+suffix+'@example.local';
  const aa=await register(a,'owner-'+suffix+'@example.local'),bb=await register(b,emailB);
  const headers={'X-CSRF-TOKEN':aa.csrf,'X-Workspace-ID':aa.workspace};
  expect((await a.get('/api/v1/resources/credentials',{headers:{'X-Workspace-ID':bb.workspace}})).status()).toBe(404);
  const credential=await a.post('/api/v1/resources/credentials',{headers,data:{name:'Synthetic secure token',kind:'bearer',value:'synthetic-private-token-123',allowed_hosts:['api.github.com']}});
  expect(credential.status()).toBe(201);expect(await credential.text()).not.toContain('synthetic-private-token-123');
  const invite=await (await a.post('/api/v1/members/invite',{headers,data:{email:emailB,role:'viewer'}})).json();
  expect((await b.post('/api/v1/invites/accept',{headers:{'X-CSRF-TOKEN':bb.csrf},data:{token:invite.invite_token}})).status()).toBe(200);
  expect((await b.post('/api/v1/resources/credentials',{headers:{'X-CSRF-TOKEN':bb.csrf,'X-Workspace-ID':aa.workspace},data:{name:'Forbidden'}})).status()).toBe(403);
  const unsafe=await a.post('/api/v1/resources/destinations',{headers,data:{name:'Blocked metadata',url:'http://169.254.169.254/latest/meta-data/'}});
  expect(unsafe.status()).toBe(422);
  expect((await a.post('/api/v1/logout',{headers})).status()).toBe(200);
  expect((await a.get('/api/v1/workspaces')).status()).toBe(401);
  expect((await (await a.get('/api/v1/session')).json()).user).toBeNull();
 }finally{await a.dispose();await b.dispose();}
});
test('signed raw HTTP ingress rejects stale signatures and deduplicates concurrent requests',async()=>{
 const context=await requests.newContext({baseURL:base});
 try{
  const session=await register(context,'ingress-'+randomUUID()+'@example.local');
  const headers={'X-CSRF-TOKEN':session.csrf,'X-Workspace-ID':session.workspace};
  const endpointResponse=await context.post('/api/v1/resources/endpoints',{headers,data:{name:'Concurrency source',provider:'generic',event_types:['issue.opened']}});
  expect(endpointResponse.status()).toBe(201);
  const {record,signing_secret:secret}=await endpointResponse.json();
  const source=randomUUID(),raw=JSON.stringify({id:source,type:'issue.opened',data:{issue:{title:'Synthetic concurrent issue'}}}),ts=Math.floor(Date.now()/1000);
  const publicHeaders={'Content-Type':'application/json','X-Event-ID':source,'X-Apiqendra-Timestamp':String(ts),'X-Apiqendra-Signature':signWebhook(raw,secret,ts)};
  expect((await context.post('/hooks/'+record.id,{headers:{...publicHeaders,'X-Apiqendra-Signature':'v1=bad'},data:raw})).status()).toBe(401);
  expect((await context.post('/hooks/'+record.id,{headers:{...publicHeaders,'X-Apiqendra-Timestamp':String(ts-600),'X-Apiqendra-Signature':signWebhook(raw,secret,ts-600)},data:raw})).status()).toBe(401);
  const results=await Promise.all(Array.from({length:10},async()=>{const r=await context.post('/hooks/'+record.id,{headers:publicHeaders,data:raw});return{status:r.status(),...await r.json()};}));
  expect(results.filter(r=>r.status===202)).toHaveLength(1);
  expect(results.filter(r=>r.status===200&&r.duplicate)).toHaveLength(9);
  expect(new Set(results.map(r=>r.event_id)).size).toBe(1);
  expect((await context.post('/hooks/'+record.id,{headers:{...publicHeaders,'X-Event-ID':randomUUID()},data:raw})).status()).toBe(422);
 }finally{await context.dispose();}
});
