// Capture only from the disposable board created through seed-forum.
const {chromium}=require('playwright');
const {expect}=require('playwright/test');
const {execFileSync}=require('node:child_process');
const fs=require('node:fs');
const path=require('node:path');
const base=process.env.KB_TEST_URL;
const root=process.env.KB_TEST_BOARD_ROOT;
const out=process.env.KB_ARTIFACT_DIR;
const configHelper=process.env.KB_SCREENSHOT_CONFIG_HELPER;
if(base!=='http://localhost:8102'||!root?.endsWith('/kb-author-change-seed-33')||!out||!configHelper)throw new Error('Use the explicit seed-forum screenshot fixture');
function tree(value){return execFileSync('php',[configHelper,root,...(value===undefined?[]:[String(value)])],{encoding:'utf8'}).trim();}
(async()=>{
 const browser=await chromium.launch({headless:true});
 const previous=tree();
 fs.mkdirSync(out,{recursive:true});
 const manifest=[];
 async function login(name){
  const context=await browser.newContext({viewport:{width:1440,height:1000},locale:'en-US'});
  const page=await context.newPage();
  await page.goto(`${base}/ucp.php?mode=login`);
  await page.locator('#username').fill(name);
  await page.locator('#password').fill('KbTest1234!');
  await page.waitForFunction(()=>Date.now()/1000>Number(document.querySelector('[name="creation_time"]').value)+2);
  await page.locator('[name="login"][type="submit"]').click();
  await page.waitForURL(u=>!u.search.includes('mode=login'));
  return page;
 }
 async function shot(page,name,url,selector,notice=false){
  const response=await page.goto(`${base}/${url}`,{waitUntil:'networkidle'});
  if(!response||response.status()>=400&&!(notice&&response.status()===409))throw new Error(`${name}: HTTP ${response?.status()}`);
  await expect(page.locator(selector).first()).toBeVisible();
  if(await page.locator('body').innerText().then(t=>/Fatal error:|General Error|Parse error:/.test(t)))throw new Error(`${name}: error page`);
  await page.screenshot({path:path.join(out,name+'.png'),fullPage:true});
  manifest.push({name,url});console.log('Saved '+name);
 }
 try{
  const admin=await login('admin');
  const author=await login('kb_author1');
  if(!process.env.KB_SCREENSHOTS_ACP_ONLY){
  tree(0);
  await shot(admin,'01-flat-index','knowledgebase','table.kb-index-table');
  await shot(admin,'02-article','knowledgebase/article?k=1','.kb-article-heading');
  await shot(author,'03-manage-your-articles','knowledgebase/my_articles','table');
  await shot(admin,'04-add-article','knowledgebase/posting?id=1','#postform');
  await shot(admin,'05-approval-queue','knowledgebase/queue','table');
  await shot(admin,'06-search-results','knowledgebase/search?keywords=backup','.content');
  await shot(admin,'09-board-search-kb-block','search.php?keywords=backup','#page-body');
  await shot(admin,'10-advanced-search-option','search.php','#kb_also');
  await shot(admin,'12-recent-changes','knowledgebase/recent_changes','#page-body');
  await shot(admin,'13-submit-redirect','knowledgebase/posting?id=1&mode=redirect','#postform');
  await shot(admin,'14-change-author','knowledgebase/change_author?k=2','form[action*="change_author"]');
  await shot(admin,'15-private-draft-notice','knowledgebase/change_author?k=1','.kb-notice',true);
  await shot(admin,'16-markdown-search','knowledgebase/search?keywords=clean&sf=articles&cid=0','.content');
  tree(1);
  await shot(admin,'08-category-tree','knowledgebase','#page-body');
  }
  let sid=(await admin.context().cookies()).find(c=>c.name.endsWith('_sid')).value;
  await admin.goto(`${base}/adm/index.php?sid=${sid}`);
  if(await admin.locator('input[type="password"]').count()){
   await admin.locator('input[type="password"]').fill('KbTest1234!');
   await admin.waitForFunction(()=>{const e=document.querySelector('[name="creation_time"]');return !e||Date.now()/1000>Number(e.value)+2;});
   await admin.locator('[name="login"][type="submit"]').click();
  }
  if(await admin.getByRole('link',{name:/Proceed to the ACP/}).count())await admin.getByRole('link',{name:/Proceed to the ACP/}).click();
  sid=(await admin.context().cookies()).find(c=>c.name.endsWith('_sid')).value;
  await shot(admin,'07-acp-configuration',`adm/index.php?i=-phpbbmodders-knowledgebase-acp-config_module&mode=settings&sid=${sid}`,'#acp');
  await shot(admin,'11-acp-search-settings',`adm/index.php?i=-phpbbmodders-knowledgebase-acp-search_module&mode=index&sid=${sid}`,'#acp');
  if(process.env.KB_SCREENSHOT_MANIFEST)fs.writeFileSync(process.env.KB_SCREENSHOT_MANIFEST,JSON.stringify(manifest.map(item=>({...item,url:item.url.replace(/([?&])sid=[^&]+/g,'$1').replace(/[?&]$/,'')})),null,2)+'\n');
  console.log(JSON.stringify({result:'PASS',screenshots:manifest.length}));
 }finally{tree(previous);await browser.close();}
})();
