import { test } from 'node:test';
import assert from 'node:assert/strict';
import { signWebhook,verifyWebhook } from '../../packages/contracts/signing.mjs';
test('published signing client preserves raw-byte authenticity and freshness',()=>{
 const raw='{"type":"issue.opened","data":{"title":"Synthetic"}}',secret='synthetic-contract-secret',ts=1720000000;
 const sig=signWebhook(raw,secret,ts);
 assert.equal(verifyWebhook(raw,secret,sig,String(ts),{now:ts+299}),true);
 assert.equal(verifyWebhook(raw,secret,sig,String(ts),{now:ts+301}),false);
 assert.equal(verifyWebhook(raw+' ',secret,sig,String(ts),{now:ts}),false);
 assert.equal(verifyWebhook(raw,secret,'v1=bad',String(ts),{now:ts}),false);
 assert.equal(verifyWebhook(raw,secret,sig,'not-a-time',{now:ts}),false);
});
test('delivery signature has a stable cross-language test vector',()=>{
 assert.equal(signWebhook('{"hello":"world"}','synthetic-test-key',1700000000),'v1=a86c12f10bed5d09cf42d3455218be90f5d859dacbbbc946aa2c29f79631670b');
});
