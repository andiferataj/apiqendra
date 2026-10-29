import { test,expect } from '@playwright/test';
import { mockApi } from './fixtures';
test('mobile navigation, simulation and overview fit the viewport',async({page})=>{
 await mockApi(page);await page.goto('/');
 await expect(page.getByRole('heading',{name:'Every connection. Under control.'})).toBeVisible();
 expect(await page.evaluate(()=>document.documentElement.scrollWidth<=window.innerWidth)).toBe(true);
 await page.getByRole('button',{name:'Toggle navigation'}).click();
 await page.getByRole('link',{name:'Simulation Lab',exact:false}).click();
 await expect(page.getByRole('heading',{name:'Break it. Watch it recover.'})).toBeVisible();
 expect(await page.evaluate(()=>document.documentElement.scrollWidth<=window.innerWidth)).toBe(true);
 await page.screenshot({path:'.runtime/simulation-mobile.png',fullPage:true});
});
