import type { Page } from '@playwright/test';
import fs from 'node:fs/promises';
import path from 'node:path';
export const user={id:'user-001',name:'Alex Developer',email:'alex@example.local'};
export const workspace={id:'workspace-001',name:'Acme Engineering',environment:'sandbox',role:'owner'};
const now=new Date().toISOString();
export const connection={id:'connection-001',workspace_id:workspace.id,name:'GitHub Sandbox',provider:'demo-github',base_url:'http://simulator:8090',credential_id:null,status:'healthy',version:1,created_at:now,config:{default_path:'/github/issues'}};
export const overview={events:24,delivery_counts:{delivered:22,retrying:1,dead_letter:1},attempts:28,attempt_success_rate:85.7,p95_ms:124,backlog:1,series:Array.from({length:12},(_,i)=>({bucket:new Date(Date.now()-(11-i)*3600000).toISOString(),total:[1,2,1,3,2,4,2,1,4,3,3,2][i],delivered:[1,2,1,2,2,3,2,1,3,2,3,2][i]})),recent_events:[{id:'event-001',source_id:'github-issue-synthetic-142',event_type:'issue.opened',status:'accepted',simulated:true,received_at:now,preview:{issue:{title:'Synthetic issue'}}}],recent_deliveries:[{id:'delivery-001',event_id:'event-001',destination_id:'destination-001',status:'delivered',attempt_count:1,generation:0,created_at:now}],range:'24h'};
export async function mockApi(page:Page,{signedIn=true,role='owner'}:{signedIn?:boolean;role?:string}={}){
 await page.route('http://ui.apiqendra.test/**',async route=>{
  const requested=new URL(route.request().url()).pathname;
  const relative=requested.startsWith('/assets/')?requested.slice(1):'index.html';
  const file=path.resolve('apps/web/dist',relative);
  const contentType=file.endsWith('.js')?'application/javascript':file.endsWith('.css')?'text/css':'text/html';
  await route.fulfill({contentType,body:await fs.readFile(file)});
 });
 const resources:Record<string,unknown[]>={connections:[connection],endpoints:[],destinations:[],routes:[],credentials:[],mappings:[]};
 await page.route('**/api/v1/**',async route=>{
  const req=route.request(),url=new URL(req.url()),path=url.pathname.replace('/api/v1','');
  let body:unknown={};let status=200;
  if(path==='/session')body={user:signedIn?user:null,csrf_token:'test-csrf',demo:true};
  else if(path==='/login'||path==='/register')body={user,csrf_token:'test-csrf'};
  else if(path==='/workspaces')body=[{...workspace,role}];
  else if(path==='/overview')body=overview;
  else if(path.startsWith('/resources/')){
   const kind=path.split('/')[2];if(req.method()==='GET')body=resources[kind]??[];
   else if(req.method()==='POST'){const record={id:kind+'-new',...req.postDataJSON(),created_at:now,status:'active',version:1};resources[kind]?.unshift(record);body={record,signing_secret:kind==='endpoints'?'synthetic-once-shown-secret':null};status=201;}
  }
  else if(path==='/simulation')body=[];
  else if(path==='/simulation/run'){body={id:'simulation-001',scenario:req.postDataJSON().scenario,result:{}};status=202;}
  else if(path.startsWith('/deliveries')||path.startsWith('/events')||path.startsWith('/logs/'))body={data:[],current_page:1,last_page:1,total:0};
  else if(path==='/members')body={members:[{id:'member-001',role:'owner',user}],invites:[]};
  else if(path==='/alerts')body={rules:[],events:[]};
  else if(path.includes('/execute'))body={ok:true,status_code:200,latency_ms:12,summary:{ok:true,count:1}};
  await route.fulfill({status,contentType:'application/json',body:JSON.stringify(body)});
 });
}
