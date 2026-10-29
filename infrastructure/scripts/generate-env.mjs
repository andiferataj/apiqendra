import fs from 'node:fs';
import path from 'node:path';
import { randomBytes } from 'node:crypto';
const root=path.resolve(import.meta.dirname,'../..'),file=path.join(root,'.env');
let text=fs.readFileSync(fs.existsSync(file)?file:path.join(root,'.env.example'),'utf8');
const keys=['APP_KEY','DB_PASSWORD','REDIS_PASSWORD','DEMO_PASSWORD','DEMO_SIMULATOR_TOKEN','METRICS_TOKEN','GRAFANA_PASSWORD'];
for(const name of keys){
 const regex=new RegExp('^'+name+'=(.*)$','m'),match=text.match(regex);
 if(match&&match[1].trim())continue;
 const value=(name==='APP_KEY'?'base64:':'')+randomBytes(32).toString('base64');
 if(match)text=text.replace(regex,name+'='+value);else text+='\n'+name+'='+value+'\n';
}
fs.writeFileSync(file,text,{mode:0o600});
console.log('Local environment prepared; existing secrets retained. Read .env privately for the demo login.');
