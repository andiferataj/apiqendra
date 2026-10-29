import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createHmac } from 'node:crypto';
import { createHandler } from './server.mjs';
import { Readable } from 'node:stream';
const token='synthetic-simulator-control-token';
async function fixture(t) {
 const handler=createHandler({token});
 const request=async(path,body,headers={},method='POST',chunks)=>{
  const req=Readable.from(chunks ?? (body === undefined ? [] : [JSON.stringify(body)]));
  req.url=path;req.method=method;
  req.headers=Object.fromEntries(Object.entries({'Content-Type':'application/json','X-Simulator-Token':token,...headers}).map(([k,v])=>[k.toLowerCase(),v]));
  let status=200,responseHeaders={};
  return new Promise((resolve,reject)=>{
   const res={destroyed:false,writeHead(s,h){status=s;responseHeaders=h;},end(text){resolve(new Response(text,{status,headers:responseHeaders}));}};
   Promise.resolve(handler(req,res)).catch(reject);
  });
 };
 return {request};
}
const id='aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa',secret='synthetic-destination-secret';
function signed(body,key='stable-delivery') {
 const raw=JSON.stringify(body),ts=String(Math.floor(Date.now()/1000));
 return {'X-Destination-ID':id,'X-Apiqendra-Timestamp':ts,'X-Apiqendra-Signature':'v1='+createHmac('sha256',secret).update(ts+'.'+raw).digest('hex'),'Idempotency-Key':key};
}
test('health is public but control requires its token',async t=>{
 const f=await fixture(t);assert.equal((await f.request('/health',undefined,{},'GET')).status,200);
 const response=await f.request('/register',{}, {'X-Simulator-Token':''});assert.equal(response.status,401);
});
test('signed delivery is verified and duplicate side effects are acknowledged',async t=>{
 const f=await fixture(t);assert.equal((await f.request('/register',{destination_id:id,secret})).status,200);
 const body={id:'event-001',type:'issue.opened',data:{text:'Synthetic notification'}};
 const headers=signed(body);
 const a=await f.request('/deliver/success',body,headers);assert.equal(a.status,200);
 const b=await f.request('/deliver/success',body,headers);assert.equal(b.status,200);assert.match((await b.json()).message,/Duplicate/);
 const forged=await f.request('/deliver/success',body,{...headers,'X-Apiqendra-Signature':'v1=bad'});assert.equal(forged.status,401);
});
test('recovery scenario returns two 503 responses followed by success',async t=>{
 const f=await fixture(t);await f.request('/register',{destination_id:id,secret});
 const body={id:'event-recovery',data:{}},headers=signed(body,'recovery');
 assert.equal((await f.request('/deliver/recover',body,headers)).status,503);
 assert.equal((await f.request('/deliver/recover',body,headers)).status,503);
 assert.equal((await f.request('/deliver/recover',body,headers)).status,200);
});
test('rate limits expose bounded Retry-After before succeeding',async t=>{
 const f=await fixture(t);await f.request('/register',{destination_id:id,secret});
 const body={id:'event-rate-limit'},headers=signed(body,'rate-limit');
 const first=await f.request('/deliver/429',body,headers);assert.equal(first.status,429);assert.equal(first.headers.get('Retry-After'),'2');
 assert.equal((await f.request('/deliver/429',body,headers)).status,200);
});
test('invalid control target is rejected rather than used as an arbitrary proxy',async t=>{
 const f=await fixture(t);const result=await f.request('/emit',{endpoint_id:'http://169.254.169.254/',secret:'synthetic',provider:'generic'});assert.equal(result.status,422);
});
test('destination simulator rejects unsigned or stale requests',async t=>{
 const f=await fixture(t);await f.request('/register',{destination_id:id,secret});
 const body={id:'event-stale'};
 assert.equal((await f.request('/deliver/success',body,{'Idempotency-Key':'a','X-Destination-ID':id})).status,401);
 const ts='1000',raw=JSON.stringify(body),sig='v1='+createHmac('sha256',secret).update(ts+'.'+raw).digest('hex');
 assert.equal((await f.request('/deliver/success',body,{'Idempotency-Key':'a','X-Destination-ID':id,'X-Apiqendra-Timestamp':ts,'X-Apiqendra-Signature':sig})).status,401);
});
test('Slack-like provider validates notification payload shape',async t=>{
 const f=await fixture(t);
 assert.equal((await f.request('/slack/notify',{channel:'#dev'})).status,422);
 assert.equal((await f.request('/slack/notify',{text:'Synthetic notification',channel:'#dev'})).status,200);
});

test('signature verification preserves UTF-8 characters split across transport chunks',async t=>{
 const f=await fixture(t);await f.request('/register',{destination_id:id,secret});
 const body={id:'event-unicode',data:{text:'Pershendetje nga qendra — Ç'}};
 const bytes=Buffer.from(JSON.stringify(body)),position=bytes.indexOf(Buffer.from('Ç'))+1;
 assert.ok(position>0);
 const response=await f.request('/deliver/success',body,signed(body,'unicode'),'POST',[bytes.subarray(0,position),bytes.subarray(position)]);
 assert.equal(response.status,200);
});
