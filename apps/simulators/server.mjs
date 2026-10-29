import http from 'node:http';
import { createHmac, timingSafeEqual } from 'node:crypto';
import { pathToFileURL } from 'node:url';
const hmac = (secret, body, ts) => 'v1=' + createHmac('sha256', secret).update(ts + '.' + body).digest('hex');
const equal = (a, b) => { const x = Buffer.from(String(a)), y = Buffer.from(String(b)); return x.length === y.length && timingSafeEqual(x, y); };
export function createHandler({ token = process.env.DEMO_SIMULATOR_TOKEN, apiUrl = 'http://api:8000' } = {}) {
 if (!token || token.length < 16) throw new Error('A generated simulator token is required.');
 const secrets = new Map(), receipts = new Map(), counts = new Map();
 const json = (res, status, body, headers = {}) => { if (res.destroyed) return; res.writeHead(status, {'Content-Type':'application/json', ...headers}); res.end(JSON.stringify(body)); };
 return async (req, res) => {
  const path = new URL(req.url, 'http://simulator').pathname;
  if (req.method === 'GET' && path === '/health') return json(res,200,{ok:true,message:'Simulator ready'});
  let raw = '';
  try {
   const chunks=[];let size=0;
   for await (const chunk of req) { const bytes=Buffer.from(chunk);size+=bytes.length;if(size>262144){json(res,413,{ok:false});req.destroy();return;}chunks.push(bytes); }
   raw=Buffer.concat(chunks).toString('utf8');
   const data = raw ? JSON.parse(raw) : {};
   if (path === '/github/issues' && req.method === 'GET') return json(res,200,{ok:true,count:1,message:'Synthetic GitHub issue fixture',id:142});
   if (path === '/slack/notify' && req.method === 'POST') {
    if (typeof data.text !== 'string' || data.text.length > 4000) return json(res,422,{ok:false,message:'A text field is required'});
    return json(res,200,{ok:true,id:'notification-synthetic'});
   }
   if (!equal(req.headers['x-simulator-token'] ?? '', token)) return json(res,401,{ok:false});
   if (path === '/register' && req.method === 'POST') {
    if (!/^[a-f0-9-]{36}$/.test(data.destination_id) || typeof data.secret !== 'string' || data.secret.length < 16) return json(res,422,{ok:false});
    secrets.set(data.destination_id, data.secret); return json(res,200,{ok:true});
   }
   if (path === '/emit' && req.method === 'POST') {
    if (!/^[a-f0-9-]{36}$/.test(data.endpoint_id) || typeof data.secret !== 'string' || !['generic','github'].includes(data.provider)) return json(res,422,{ok:false});
    const payload = data.provider === 'github' ? {action:'opened',...data.payload} : {id:data.event_id,type:'issue.opened',data:data.payload};
    const body = JSON.stringify(payload), ts = String(Math.floor(Date.now()/1000));
    const headers = {'Content-Type':'application/json'};
    if (data.provider === 'github') Object.assign(headers, {'X-GitHub-Event':'issues','X-GitHub-Delivery':data.event_id,'X-Hub-Signature-256':'sha256='+createHmac('sha256',data.secret).update(body).digest('hex')});
    else Object.assign(headers, {'X-Event-ID':data.event_id,'X-Apiqendra-Timestamp':ts,'X-Apiqendra-Signature':hmac(data.secret,body,ts)});
    if (data.scenario === 'invalid_signature') { headers['X-Apiqendra-Signature']='v1='+'0'.repeat(64); headers['X-Hub-Signature-256']='sha256='+'0'.repeat(64); }
    const target = apiUrl + '/hooks/' + data.endpoint_id;
    const first = await fetch(target,{method:'POST',headers,body,signal:AbortSignal.timeout(4500)});
    const response = await first.json();
    let duplicate = false;
    if (data.scenario === 'duplicate' && first.status === 202) {
     const second = await fetch(target,{method:'POST',headers,body,signal:AbortSignal.timeout(4500)});
     const result = await second.json(); duplicate = second.status === 200 && result.duplicate === true && result.event_id === response.event_id;
    }
    return json(res,200,{event_id:response.event_id ?? null,ingress_status:first.status,duplicate,validated:data.scenario === 'invalid_signature' ? first.status === 401 : data.scenario === 'duplicate' ? duplicate : first.status === 202});
   }
   if (path.startsWith('/deliver/') && req.method === 'POST') {
    const secret = secrets.get(req.headers['x-destination-id']);
    const ts = req.headers['x-apiqendra-timestamp'], signature = req.headers['x-apiqendra-signature'];
    if (!secret || !/^\d+$/.test(ts ?? '') || Math.abs(Date.now()/1000 - Number(ts)) > 300 || !equal(hmac(secret,raw,ts),signature ?? '')) return json(res,401,{ok:false,message:'Signature invalid'});
    const key = req.headers['idempotency-key']; if (!key) return json(res,422,{ok:false});
    if (receipts.has(key)) return json(res,200,{ok:true,id:receipts.get(key),message:'Duplicate safely acknowledged'});
    const attempt = (counts.get(key) ?? 0)+1; counts.set(key,attempt);
    const behavior = path.split('/').at(-1);
    if (behavior === '503' || (behavior === 'recover' && attempt < 3)) return json(res,503,{ok:false,message:'Synthetic service outage'});
    if (behavior === '429' && attempt < 2) return json(res,429,{ok:false,message:'Synthetic rate limit'},{'Retry-After':'2'});
    if (behavior === 'unauthorized') return json(res,401,{ok:false});
    if (behavior === 'timeout') { const timer = setTimeout(()=>json(res,200,{ok:true}),8000); req.on('close',()=>clearTimeout(timer)); return; }
    if (behavior === 'delay') await new Promise(resolve=>setTimeout(resolve,800));
    if (behavior === 'invalid') { res.writeHead(502,{'Content-Type':'text/plain'}); return res.end('Synthetic malformed upstream response'); }
    receipts.set(key,data.id); return json(res,200,{ok:true,id:data.id});
   }
   return json(res,404,{ok:false});
  } catch { return json(res,502,{ok:false,message:'Simulator request could not complete'}); }
 };
}
export function createSimulator(options) { return http.createServer(createHandler(options)); }
if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) createSimulator().listen(8090,'0.0.0.0',()=>console.log('Apiqendra simulator listening on 8090'));
