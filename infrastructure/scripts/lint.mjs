import fs from 'node:fs';
import path from 'node:path';
const root = path.resolve(import.meta.dirname, '../../apps/web/src');
let errors = 0;
for (const file of fs.readdirSync(root,{recursive:true}).filter(f=>/\.(ts|tsx|css)$/.test(f))) {
 const text = fs.readFileSync(path.join(root,file),'utf8');
 for (const [pattern,message] of [[/\bdebugger\s*;/g,'debugger statement'],[/dangerouslySetInnerHTML/g,'raw HTML injection'],[/\bas any\b/g,'untyped cast'],[/console\.log\(/g,'debug logging']]) {
  if(pattern.test(text)){console.error(file+': '+message);errors++;}
 }
}
if(errors)process.exit(1);
console.log('Source hygiene passed. TypeScript checks run separately.');
