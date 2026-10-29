import { createHmac,timingSafeEqual } from 'node:crypto';
export function signWebhook(rawBody,secret,timestamp) {
 if (!Number.isSafeInteger(timestamp)||timestamp<0)throw new TypeError('Timestamp must be a non-negative integer.');
 return 'v1='+createHmac('sha256',secret).update(String(timestamp)+'.'+rawBody).digest('hex');
}
export function verifyWebhook(rawBody,secret,signature,timestamp,{now=Math.floor(Date.now()/1000),tolerance=300}={}) {
 if(!/^\d+$/.test(String(timestamp))||Math.abs(now-Number(timestamp))>tolerance)return false;
 const expected=Buffer.from(signWebhook(rawBody,secret,Number(timestamp))),supplied=Buffer.from(String(signature));
 return expected.length===supplied.length&&timingSafeEqual(expected,supplied);
}
