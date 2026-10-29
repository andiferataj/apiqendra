import { defineConfig,devices } from '@playwright/test';
export default defineConfig({
 testDir:'./tests/e2e',
 timeout:45000,
 expect:{timeout:10000},
 fullyParallel:false,
 forbidOnly:!!process.env.CI,
 retries:process.env.CI?1:0,
 reporter:[['list'],['html',{open:'never'}]],
 use:{trace:'retain-on-failure',screenshot:'only-on-failure'},
 projects:[
  {name:'ui',testMatch:'ui.spec.ts',use:{...devices['Desktop Chrome'],baseURL:'http://ui.apiqendra.test'}},
  {name:'mobile',testMatch:'mobile.spec.ts',use:{...devices['iPhone 13'],defaultBrowserType:'chromium',baseURL:'http://ui.apiqendra.test'}},
  {name:'integration',testMatch:['integration.spec.ts','security.integration.spec.ts'],use:{...devices['Desktop Chrome'],baseURL:process.env.E2E_BASE_URL??'http://localhost:8084'}},
 ],
 // UI projects fulfill built assets and synthetic API responses entirely in memory.
 // Integration project targets the running Compose stack.
});
