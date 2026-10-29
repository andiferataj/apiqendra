import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { randomUUID } from 'node:crypto';
import os from 'node:os';
import { signWebhook } from '../../packages/contracts/signing.mjs';
import { SessionClient,until } from './http-client.mjs';

const project=process.env.APIQENDRA_VERIFY_PROJECT;
const base=process.env.E2E_BASE_URL;
assert.match(project??'',/^apiqendra-verify-[0-9]{14}-[a-f0-9]{6}$/,'Run this only through Release.ps1.');
assert.ok(base&&['127.0.0.1','localhost'].includes(new URL(base).hostname));
assert.equal(path.basename(process.cwd()),project,'A disposable verification snapshot is required.');
assert.ok(fs.readFileSync('.env','utf8').includes('COMPOSE_PROJECT_NAME='+project));
const records=[];
const machine={platform:os.platform(),release:os.release(),arch:os.arch(),cpu_model:os.cpus()[0]?.model,cpu_threads:os.cpus().length,host_memory_bytes:os.totalmem(),node:process.version};
const report=path.resolve('.runtime/release-acceptance.json');
fs.mkdirSync(path.dirname(report),{recursive:true});
function compose(...args){
 return execFileSync('docker',['compose','--project-name',project,...args],{cwd:process.cwd(),encoding:'utf8',timeout:180000,windowsHide:true}).trim();
}
function sql(query){
 return compose('exec','-T','postgres','sh','-c','exec psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -tAc "$1"','release-check',query);
}
async function step(name,action){
 const started=performance.now();
 try{
  const details=await action();
  records.push({name,status:'passed',seconds:Number(((performance.now()-started)/1000).toFixed(3)),details});
  console.log('PASS '+name);
 }catch(error){
  records.push({name,status:'failed',message:error.message,cause:error.cause?.message,code:error.cause?.cause?.code??error.cause?.code,stack:error.stack});
  throw error;
 }finally{
  fs.writeFileSync(report,JSON.stringify({project,machine,steps:records},null,2)+'\n');
 }
}
const client=new SessionClient(base);
await client.register('acceptance-'+randomUUID()+'@example.local');
async function eventDelivery(eventId){
 const all=await client.request('/api/v1/deliveries?range=30d');
 return all.data.find(delivery=>delivery.event_id===eventId);
}
async function detail(deliveryId){return client.request('/api/v1/deliveries/'+deliveryId);}

await step('broker-loss-recovers-database-intent',async()=>{
 compose('stop','worker');
 const run=await client.request('/api/v1/simulation/run',{method:'POST',data:{scenario:'success',provider:'generic'}});
 const queued=await until(()=>eventDelivery(run.event_id),value=>Boolean(value),{label:'queued delivery'});
 await until(async()=>Number(compose('exec','-T','redis','redis-cli','LLEN','apiqendra_queues:deliveries')),n=>n>0,{timeout:15000,label:'published queue item'});
 compose('stop','dispatcher');
 compose('exec','-T','redis','redis-cli','-n','0','FLUSHDB');
 compose('restart','redis');
 compose('start','worker','dispatcher');
 const delivered=await until(()=>detail(queued.id),value=>value.delivery.status==='delivered',{timeout:115000,label:'recovered delivery after broker loss'});
 assert.equal(delivered.attempts.length,1);
 return {event_id:run.event_id,delivery_id:queued.id,attempts:1};
});

await step('killed-worker-preserves-uncertainty',async()=>{
 const run=await client.request('/api/v1/simulation/run',{method:'POST',data:{scenario:'timeout',provider:'generic'}});
 const queued=await until(()=>eventDelivery(run.event_id),value=>Boolean(value),{label:'timeout delivery'});
 await until(()=>detail(queued.id),value=>value.delivery.status==='delivering'&&value.attempts.some(a=>a.outcome==='sending'),{interval:200,timeout:20000,label:'in-flight persisted attempt'});
 compose('kill','-s','SIGKILL','worker');
 await client.request('/api/v1/simulation/recover',{method:'POST',data:{destination_id:run.result.destination_id}});
 compose('up','-d','worker');
 const recovered=await until(()=>detail(queued.id),value=>value.delivery.status==='delivered',{timeout:115000,label:'delivery after expired worker lease'});
 assert.ok(recovered.attempts.some(a=>a.uncertain&&a.outcome==='uncertain'));
 assert.ok(recovered.attempts.length>=2);
 assert.equal(recovered.delivery.id,queued.id);
 return {delivery_id:queued.id,attempts:recovered.attempts.length,uncertain_recorded:true};
});

await step('database-backup-and-restore',async()=>{
 const before=Number(sql('SELECT count(*) FROM webhook_events'));
 compose('exec','-T','postgres','sh','-c','pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" -Fc -f /tmp/apiqendra-release.dump');
 const sentinel=await client.request('/api/v1/workspaces',{method:'POST',data:{name:'Restore sentinel',environment:'sandbox'}});
 assert.match(sentinel.id,/^[a-f0-9-]{36}$/i);
 compose('stop','web','api','worker','dispatcher','scheduler');
 try{
  compose('exec','-T','postgres','sh','-c','pg_restore --exit-on-error --clean --if-exists --no-owner -U "$POSTGRES_USER" -d "$POSTGRES_DB" /tmp/apiqendra-release.dump');
 }finally{
  compose('up','-d','--wait','--wait-timeout','120');
 }
 await until(()=>client.request('/health/ready'),value=>value.status==='ready',{timeout:20000,label:'HTTP readiness after database restore'});
 assert.equal(Number(sql('SELECT count(*) FROM webhook_events')),before);
 assert.equal(Number(sql("SELECT count(*) FROM workspaces WHERE id='"+sentinel.id+"'")),0);
 const current=await client.request('/api/v1/workspaces');
 assert.ok(current.some(workspace=>workspace.id===client.workspace));
 const overview=await client.request('/api/v1/overview?range=30d');
 assert.ok(overview.events>=2);
 return {persisted_events:before,sentinel_removed:true,encrypted_records_readable:true};
});

await step('payload-retention-preserves-history',async()=>{
 const events=await client.request('/api/v1/events?range=30d');
 const event=events.data.find(record=>record.simulated);
 assert.ok(event);
 assert.match(event.id,/^[a-f0-9-]{36}$/i);
 sql("UPDATE webhook_events SET received_at=CURRENT_TIMESTAMP-INTERVAL '40 days' WHERE id='"+event.id+"'");
 compose('exec','-T','api','php','artisan','relay:retain','--days=30');
 const refreshed=(await client.request('/api/v1/events?range=30d')).data.find(record=>record.id===event.id);
 // Operational search uses creation time while retention uses source receipt time.
 assert.ok(refreshed?.payload_purged_at);
 assert.equal(refreshed.preview.retention,'Payload removed by retention policy.');
 assert.ok(await eventDelivery(event.id));
 return {event_id:event.id,payload_removed:true,delivery_history_retained:true};
});

await step('measured-ingress-and-queue-baseline',async()=>{
 const workspace=await client.request('/api/v1/workspaces',{method:'POST',data:{name:'Measured release baseline',environment:'sandbox'}});
 client.workspace=workspace.id;
 const source=await client.request('/api/v1/resources/endpoints',{method:'POST',data:{name:'Load source',provider:'generic',event_types:['issue.opened']}});
 const destination=await client.request('/api/v1/resources/destinations',{method:'POST',data:{name:'Load sink',url:'http://simulator:8090/deliver/success'}});
 await client.request('/api/v1/resources/routes',{method:'POST',data:{endpoint_id:source.record.id,destination_id:destination.record.id,event_type:'issue.opened',mapping_id:null}});
 const latencies=[],count=30,start=performance.now();
 for(let i=0;i<count;i++){
  const timestamp=Math.floor(Date.now()/1000);
  const sourceId=randomUUID();
  const body=JSON.stringify({id:sourceId,type:'issue.opened',data:{issue:{title:'Synthetic load '+i},repository:{name:'load/fixture'}}});
  const began=performance.now();
  const response=await fetch(base+'/hooks/'+source.record.id,{method:'POST',headers:{Connection:'close','Content-Type':'application/json','X-Event-ID':sourceId,'X-Apiqendra-Timestamp':String(timestamp),'X-Apiqendra-Signature':signWebhook(body,source.signing_secret,timestamp)},body,signal:AbortSignal.timeout(10000)});
  latencies.push(performance.now()-began);
  assert.equal(response.status,202);
  await response.text();
  const wait=start+(i+1)*1000-performance.now();
  if(wait>0)await new Promise(resolve=>setTimeout(resolve,wait));
 }
 const ingressSeconds=(performance.now()-start)/1000;
 const drained=await until(async()=>{
  const first=await client.request('/api/v1/deliveries?range=30d');
  const second=first.last_page>1?await client.request('/api/v1/deliveries?range=30d&page=2'):{data:[]};
  return [...first.data,...second.data];
 },rows=>rows.length===count&&rows.every(row=>row.status==='delivered'),{timeout:90000,interval:2000,label:'complete load queue drain'});
 const sorted=[...latencies].sort((a,b)=>a-b),p95=sorted[Math.ceil(sorted.length*.95)-1];
 assert.ok(p95<2000,'Ingress exceeded the provisional 2-second p95 sanity bound.');
 assert.equal(drained.reduce((sum,row)=>sum+row.attempt_count,0),count);
 return {accepted:count,delivered:drained.length,ingress_rate:Number((count/ingressSeconds).toFixed(3)),ingress_p95_ms:Number(p95.toFixed(2)),queue_drain_seconds:Number(((performance.now()-start)/1000-ingressSeconds).toFixed(3)),methodology:'30 synthetic events at 1 event/second, one worker; provisional sanity threshold'};
});

console.log('Isolated runtime acceptance passed. Evidence: '+report);
