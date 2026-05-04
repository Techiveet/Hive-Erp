import puppeteer from 'puppeteer';

(async () => {
  console.log("Launching browser...");
  const browser = await puppeteer.launch({ headless: "new", args: ['--no-sandbox'] });
  const page = await browser.newPage();
  
  page.on('console', msg => console.log('BROWSER CONSOLE:', msg.type(), msg.text()));
  page.on('pageerror', err => console.log('BROWSER ERROR:', err.message));
  page.on('requestfailed', req => console.log('REQUEST FAILED:', req.url(), req.failure().errorText));

  // Login
  console.log("Logging in...");
  await page.goto('http://localhost:3000/login');
  await page.type('input[name="email"]', 'admin@techive.com');
  await page.type('input[name="password"]', 'password');
  await page.click('button[type="submit"]');
  await page.waitForNavigation();

  // Go to project detail
  console.log("Navigating to projects...");
  await page.goto('http://localhost:3000/dashboard/project-management/projects');
  await page.waitForSelector('text/View Details', { timeout: 10000 }).catch(() => {});
  
  // Try to click the first project
  const links = await page.$$('a[href^="/dashboard/project-management/projects/"]');
  if (links.length > 0) {
    await links[0].click();
    await page.waitForSelector('text/Create Task', { timeout: 10000 });
  } else {
    console.log("No projects found!");
    await browser.close();
    return;
  }

  console.log("Opening Create Task modal...");
  await page.evaluate(() => {
    const buttons = Array.from(document.querySelectorAll('button'));
    const createBtn = buttons.find(b => b.textContent.includes('Create Task'));
    if (createBtn) createBtn.click();
  });

  await new Promise(r => setTimeout(r, 2000));

  console.log("Filling form...");
  await page.type('input[name="title"]', 'Test Task with Signature');
  
  // Click signature button
  console.log("Opening signature pad...");
  await page.evaluate(() => {
    const buttons = Array.from(document.querySelectorAll('button'));
    const sigBtn = buttons.find(b => b.title === 'Add Signature' || b.querySelector('svg.lucide-pen-tool'));
    if (sigBtn) sigBtn.click();
  });
  
  await new Promise(r => setTimeout(r, 1000));
  
  // Draw signature
  console.log("Drawing signature...");
  await page.mouse.move(300, 300);
  await page.mouse.down();
  await page.mouse.move(350, 350);
  await page.mouse.up();
  
  await new Promise(r => setTimeout(r, 1000));
  
  // Insert signature
  await page.evaluate(() => {
    const buttons = Array.from(document.querySelectorAll('button'));
    const insertBtn = buttons.find(b => b.textContent.includes('Insert Signature'));
    if (insertBtn) insertBtn.click();
  });
  
  await new Promise(r => setTimeout(r, 2000));

  // Submit form
  console.log("Submitting form...");
  await page.evaluate(() => {
    const buttons = Array.from(document.querySelectorAll('button'));
    const submitBtn = buttons.find(b => b.textContent.includes('Create Task') && b.type === 'submit');
    if (submitBtn) submitBtn.click();
  });
  
  await new Promise(r => setTimeout(r, 5000));
  console.log("Done.");
  await browser.close();
})();
