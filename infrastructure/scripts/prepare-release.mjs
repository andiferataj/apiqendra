import fs from 'node:fs';
import path from 'node:path';
import net from 'node:net';
import { randomBytes } from 'node:crypto';

const root=path.resolve(import.meta.dirname,'../..');
const suffix=new Date().toISOString().replace(/[^0-9]/g,'').slice(0,14)+'-'+randomBytes(3).toString('hex');
const project='apiqendra-verify-'+suffix;
const directory=path.join(root,'.runtime','verification',project);
const excluded=new Set(['.git','.runtime','.aws','.codex','.agents','node_modules','vendor','dist','test-results','playwright-report']);
function copyTree(source,destination){
 fs.mkdirSync(destination,{recursive:true});
 for(const entry of fs.readdirSync(source,{withFileTypes:true})){
  if(entry.isSymbolicLink()||excluded.has(entry.name))continue;
  const relative=path.relative(root,path.join(source,entry.name)).split(path.sep).join('/');
  if(entry.isDirectory()&&['apps/api/storage','apps/api/bootstrap/cache'].includes(relative)){
   const target=path.join(destination,entry.name);fs.mkdirSync(target,{recursive:true});
   if(fs.existsSync(path.join(source,entry.name,'.gitkeep')))fs.copyFileSync(path.join(source,entry.name,'.gitkeep'),path.join(target,'.gitkeep'));
   continue;
  }
  if(entry.isDirectory())copyTree(path.join(source,entry.name),path.join(destination,entry.name));
  else if(entry.isFile()&&!/\.(log|sqlite(?:-shm|-wal)?|dump|key|pem)$/.test(entry.name)&&(!entry.name.startsWith('.env')||entry.name==='.env.example'))fs.copyFileSync(path.join(source,entry.name),path.join(destination,entry.name));
 }
}
fs.mkdirSync(path.dirname(directory),{recursive:true});
fs.mkdirSync(directory);
copyTree(root,directory);
const server=net.createServer();
await new Promise((resolve,reject)=>{server.on('error',reject);server.listen(0,'127.0.0.1',resolve);});
const port=server.address().port;
await new Promise(resolve=>server.close(resolve));
let environment=fs.readFileSync(path.join(directory,'.env.example'),'utf8');
const values={COMPOSE_PROJECT_NAME:project,API_IMAGE:project+'-api:local',HTTP_PORT:String(port),APP_URL:'http://127.0.0.1:'+port,DB_DATABASE:'apiqendra_release',APP_ENV:'local',DEMO_ENABLED:'true'};
for(const name of ['APP_KEY','DB_PASSWORD','REDIS_PASSWORD','DEMO_PASSWORD','DEMO_SIMULATOR_TOKEN','METRICS_TOKEN','GRAFANA_PASSWORD'])values[name]=(name==='APP_KEY'?'base64:':'')+randomBytes(32).toString('base64');
for(const [name,value] of Object.entries(values)){
 const pattern=new RegExp('^'+name+'=.*$','m');
 environment=pattern.test(environment)?environment.replace(pattern,name+'='+value):environment+'\n'+name+'='+value;
}
fs.writeFileSync(path.join(directory,'.env'),environment+'\n',{mode:0o600});
console.log(JSON.stringify({directory,project,port,url:values.APP_URL}));
