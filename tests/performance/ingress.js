import http from 'k6/http';
import crypto from 'k6/crypto';
import { check } from 'k6';
export const options={scenarios:{ingress:{executor:'constant-arrival-rate',rate:1,timeUnit:'1s',duration:'30s',preAllocatedVUs:2,maxVUs:4}},thresholds:{checks:['rate>0.99'],http_req_duration:['p(95)<2000']}};
export default function(){
 if(!__ENV.ENDPOINT_URL||!__ENV.WEBHOOK_SECRET)throw new Error('Set ENDPOINT_URL and WEBHOOK_SECRET for a dedicated synthetic endpoint.');
 const ts=String(Math.floor(Date.now()/1000));
 const id='load-'+__VU+'-'+__ITER+'-'+ts;
 const body=JSON.stringify({id,type:'issue.opened',data:{issue:{title:'Synthetic load '+__VU+' '+__ITER},repository:{name:'load/fixture'}}});
 const response=http.post(__ENV.ENDPOINT_URL,body,{headers:{'Content-Type':'application/json','X-Event-ID':id,'X-Apiqendra-Timestamp':ts,'X-Apiqendra-Signature':'v1='+crypto.hmac('sha256',__ENV.WEBHOOK_SECRET,ts+'.'+body,'hex')}});
 check(response,{'accepted durably':r=>r.status===202});
}
