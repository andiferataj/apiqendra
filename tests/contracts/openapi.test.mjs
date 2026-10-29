import { test } from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
const spec=JSON.parse(fs.readFileSync(new URL('../../packages/contracts/openapi.json',import.meta.url)));
test('published OpenAPI has resolvable references, unique operation IDs and path parameters',()=>{
 assert.equal(spec.openapi,'3.1.0');const ids=new Set();
 const resolve=ref=>ref.slice(2).split('/').reduce((value,key)=>value?.[key],spec);
 function walk(value){if(!value||typeof value!=='object')return;if(value.$ref)assert.ok(resolve(value.$ref),'Unresolved '+value.$ref);for(const child of Object.values(value))walk(child);}
 walk(spec);
 for(const [path,methods] of Object.entries(spec.paths)){
  for(const op of Object.values(methods)){
   assert.ok(!ids.has(op.operationId),'Duplicate operation ID');ids.add(op.operationId);
   for(const match of path.matchAll(/\{([^}]+)\}/g))assert.ok(op.parameters.some(p=>{const param=p.$ref?resolve(p.$ref):p;return param.in==='path'&&param.name===match[1]&&param.required;}),'Missing parameter '+match[1]);
  }
 }
 assert.ok(ids.size>=35);
});
test('public webhook uses signatures while secrets remain write-only in contracts',()=>{
 assert.deepEqual(spec.paths['/hooks/{endpoint}'].post.security,[]);
 assert.equal(spec.components.schemas.CredentialInput.properties.value.writeOnly,true);
 assert.equal('value' in spec.components.schemas.Credential.properties,false);
 assert.equal(spec.paths['/api/v1/deliveries/export'].get.responses[200].content['text/csv'].schema.type,'string');
});
