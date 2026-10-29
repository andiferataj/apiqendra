import { BookOpen, Code2, GitBranch, ShieldCheck, ArrowUpRight } from 'lucide-react';
import { PageHeading,PanelTitle,JsonBlock,CopyButton } from '../components/ui';
const signingExample = `import { createHmac } from 'node:crypto';

const timestamp = String(Math.floor(Date.now() / 1000));
const eventId = crypto.randomUUID();
const body = JSON.stringify({
  id: eventId,
  type: 'issue.opened',
  data: {
    issue: { title: 'Add webhook receipts' },
    repository: { name: 'apiqendra/platform' }
  }
});
const signature = 'v1=' + createHmac('sha256', secret)
  .update(timestamp + '.' + body).digest('hex');

await fetch(endpointUrl, {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'X-Event-ID': eventId,
    'X-Apiqendra-Timestamp': timestamp,
    'X-Apiqendra-Signature': signature
  },
  body
});`;
export default function Developer(){
 return <><PageHeading eyebrow="DEVELOPER PORTAL" title="Make the connection." description="The contracts, signing guides, and operational details behind the platform." action={<a className="button secondary" href="/openapi.json" target="_blank" rel="noreferrer"><BookOpen size={15}/> OpenAPI 3.1 <ArrowUpRight size={14}/></a>}/><div className="developer-grid"><section className="panel padded"><PanelTitle title="Send your first signed event" meta="Generic webhook signature contract" action={<Code2 size={20} className="mint-text"/>}/><p>Generate a signature from the exact raw request bytes. The timestamp must be within five minutes of server time. Put the source ID in the signed body and X-Event-ID header. Keep it unchanged when retrying.</p><pre className="json-block">{signingExample}</pre><CopyButton value={signingExample} label="Copy signing example"/></section><aside className="developer-aside"><section className="panel padded"><ShieldCheck size={23} className="mint-text"/><h2>Delivery guarantees</h2><ul><li>At-least-once attempts with stable event and delivery IDs.</li><li>Four attempts per run, bounded backoff with jitter.</li><li>Timeouts are recorded as uncertain outcomes.</li><li>Deduplicate downstream using the Idempotency-Key header.</li><li>Secrets are encrypted and never returned after input.</li></ul></section><section className="panel padded"><GitBranch size={23}/><h2>GitHub ingress</h2><p>Use X-Hub-Signature-256 with sha256=HMAC(secret, rawBody). X-GitHub-Delivery supplies the event identity. issues + action opened normalizes to issue.opened.</p><p>GitHub does not sign timestamps. Delivery IDs and signed content provide deduplication; there is no cryptographic freshness window. Supported events: issues and ping.</p></section></aside></div><section className="panel padded"><PanelTitle title="Outbound delivery envelope" meta="Every destination receives this stable event shape."/><JsonBlock value={{id:'event-uuid',type:'issue.opened',data:{text:'Add webhook receipts',channel:'#engineering',repository:'apiqendra/platform'}}}/><div className="contract-grid">{[['X-Delivery-ID','Stable delivery identifier'],['X-Event-ID','Original event identifier'],['Idempotency-Key','Same delivery ID across retries and replay'],['X-Apiqendra-Timestamp','Unix timestamp in seconds'],['X-Apiqendra-Signature','v1=HMAC-SHA256(secret, timestamp.rawBody)']].map(([k,v])=><div key={k}><code>{k}</code><span>{v}</span></div>)}</div></section></>;
}
