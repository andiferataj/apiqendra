import { test,expect } from '@playwright/test';
import { mockApi } from './fixtures';
test('overview shows server data and navigation works',async({page})=>{
 await mockApi(page);await page.goto('/');
 await expect(page.getByRole('heading',{name:'Every connection. Under control.'})).toBeVisible();
 await expect(page.locator('.metric-value').first()).toHaveText('24');
 await expect(page.getByText('85.7%')).toBeVisible();
 await page.getByRole('link',{name:'Connections',exact:true}).click();
 await expect(page.getByRole('heading',{name:'Bring your services together.'})).toBeVisible();
 await expect(page.getByText('GitHub Sandbox',{exact:true}).first()).toBeVisible();
 await page.getByRole('button',{name:'Execute GitHub Sandbox'}).click();
 await page.getByRole('button',{name:'Send request',exact:true}).click();
 await expect(page.locator('.json-block')).toContainText('"status_code": 200');
});
test('endpoint form saves to API and displays secret only once',async({page})=>{
 await mockApi(page);await page.goto('/#relay');
 await page.getByRole('tab',{name:'Endpoints',exact:true}).click();
 await page.getByRole('button',{name:'Add endpoint',exact:true}).first().click();
 await page.getByLabel('Name',{exact:true}).fill('Issue ingress');
 await page.getByRole('button',{name:'Create endpoint',exact:true}).click();
 await expect(page.getByRole('heading',{name:'Save this signing secret'})).toBeVisible();
 await expect(page.locator('.secret-value')).toHaveText('synthetic-once-shown-secret');
 await page.getByRole('button',{name:'I saved the secret'}).click();
 await expect(page.getByText('Issue ingress',{exact:true})).toBeVisible();
});
test('simulation starts chosen scenario and reports API state',async({page})=>{
 await mockApi(page);await page.goto('/#simulation');
 await expect(page.getByRole('heading',{name:'Break it. Watch it recover.'})).toBeVisible();
 const request=page.waitForRequest(r=>r.url().includes('/simulation/run')&&r.method()==='POST');
 await page.locator('.scenario-card').filter({hasText:'The golden path'}).getByRole('button',{name:'Run scenario'}).click();
 expect((await request).postDataJSON()).toEqual({scenario:'success',provider:'generic'});
 await expect(page.locator('.toast')).toContainText('Scenario started.');
});
test('viewer sees operational data and cannot configure credentials',async({page})=>{
 await mockApi(page,{role:'viewer'});await page.goto('/#vault');
 await expect(page.getByRole('heading',{name:'Keep your secrets. Safely.'})).toBeVisible();
 await expect(page.getByRole('button',{name:'Add credential'})).toHaveCount(0);
 await expect(page.getByRole('link',{name:'Audit trail'})).toHaveCount(0);
});
test('authentication and registration are accessible forms',async({page})=>{
 await mockApi(page,{signedIn:false});await page.goto('/');
 await expect(page.getByRole('heading',{name:'Welcome back.'})).toBeVisible();
 await page.getByRole('button',{name:'Create an account',exact:true}).click();
 await page.getByLabel('Your name').fill('Synthetic Developer');
 await page.getByLabel('Email address').fill('synthetic@example.local');
 await page.getByLabel('Password',{exact:true}).fill('synthetic-password-123');
 await page.getByLabel('Confirm password').fill('synthetic-password-123');
 await page.getByRole('button',{name:'Create account',exact:true}).click();
 await expect(page.getByRole('heading',{name:'Every connection. Under control.'})).toBeVisible();
});
test('desktop layout fits and has no console errors',async({page})=>{
 const errors:string[]=[];page.on('pageerror',e=>errors.push(e.message));await mockApi(page);await page.goto('/');
 await expect(page.getByRole('heading',{name:'Every connection. Under control.'})).toBeVisible();
 expect(await page.evaluate(()=>document.documentElement.scrollWidth<=window.innerWidth)).toBe(true);
 expect(errors).toEqual([]);
 await page.screenshot({path:'.runtime/overview-desktop.png',fullPage:true});
});

test('expired protected session returns to the sign-in form',async({page})=>{
 await mockApi(page);await page.goto('/');
 await expect(page.getByRole('heading',{name:'Every connection. Under control.'})).toBeVisible();
 await page.route('**/api/v1/resources/credentials',route=>route.fulfill({status:401,contentType:'application/json',body:JSON.stringify({message:'Unauthenticated.'})}));
 await page.getByRole('link',{name:'Credential vault',exact:true}).click();
 await expect(page.getByRole('heading',{name:'Welcome back.'})).toBeVisible();
 await expect(page.getByLabel('Email address')).toBeEditable();
});
