#!/usr/bin/env node
/**
 * Renders a SciFlow Anais volume in headless Chrome, saves the PDF and checks
 * the layout. Development/QA tool (not shipped in the plugin package).
 *
 *   npm i playwright-core   (once, anywhere on NODE_PATH)
 *   node bin/anais-check.js <site-url> <admin-user> <admin-pass> <volume> [out.pdf]
 *
 * Volumes: resumos | resumos_enfrute | resumos_semco | palestras_resumos
 * Env: CHROME=/path/to/chrome (default /usr/bin/google-chrome)
 * Exit code 1 when a check fails.
 */
const { chromium } = require('playwright-core');
const [, , base, user, pass, volume, outPdf] = process.argv;

(async () => {
  const browser = await chromium.launch({
    executablePath: process.env.CHROME || '/usr/bin/google-chrome',
    headless: false,
    args: ['--headless=new', '--no-sandbox'],
  });
  const page = await (await browser.newContext()).newPage();
  await page.goto(base + '/wp-login.php');
  await page.fill('#user_login', user);
  await page.fill('#user_pass', pass);
  await Promise.all([page.waitForNavigation(), page.click('#wp-submit')]);

  const t0 = Date.now();
  await page.goto(`${base}/wp-admin/admin.php?page=sciflow-anais&anais_preview=1&anais_volume=${volume}`, { timeout: 120000 });
  await page.waitForSelector('html[data-sf-ready="1"]', { timeout: 600000 });
  const seconds = Math.round((Date.now() - t0) / 1000);

  const r = await page.evaluate(() => {
    const pages = [...document.querySelectorAll('.pagedjs_page')];
    const problems = [];
    // 1. Overflow: content box taller than the page area.
    pages.forEach((pg, i) => {
      const area = pg.querySelector('.pagedjs_page_content');
      if (area && area.scrollHeight > area.clientHeight + 2) problems.push(`overflow p${i}`);
    });
    // 2. Blank pages (no text, no image).
    pages.forEach((pg, i) => {
      const content = pg.querySelector('.pagedjs_page_content');
      const txt = content ? content.innerText.trim() : '';
      if (!txt && !(content && content.querySelector('img'))) problems.push(`blank p${i}`);
    });
    // 3. Summary vs real page of each target, and vs the printed folio.
    let toc = 0;
    document.querySelectorAll('.sf-toc-pg').forEach(el => {
      toc++;
      const target = document.getElementById(el.dataset.target);
      const pg = target && target.closest('.pagedjs_page');
      const idx = pg ? pages.indexOf(pg) : -1;
      const folio = pg && pg.querySelector('.sf-folio');
      if (String(idx) !== el.textContent.trim()) problems.push(`toc ${el.dataset.target}: ${el.textContent} != ${idx}`);
      if (!folio || folio.textContent !== el.textContent.trim()) problems.push(`folio ${el.dataset.target}`);
    });
    // 4. Every work starts at the top of a page.
    document.querySelectorAll('article.sf-trabalho[id]').forEach(a => {
      const pg = a.closest('.pagedjs_page');
      const top = a.getBoundingClientRect().top - pg.querySelector('.pagedjs_page_content').getBoundingClientRect().top;
      if (top > 2) problems.push(`not at top ${a.id}`);
    });
    // 5. Folios: sequential = physical index, present on every non-image page.
    let folios = 0;
    pages.forEach((pg, i) => {
      const f = pg.querySelector('.sf-folio');
      const image = pg.querySelector('.sf-imagem, .sf-capa');
      if (f) { folios++; if (f.textContent !== String(i)) problems.push(`folio p${i}=${f.textContent}`); }
      else if (!image && i > 0) problems.push(`missing folio p${i}`);
    });
    // 6. Running header on every content page.
    let headers = 0;
    pages.filter(pg => pg.classList.contains('pagedjs_conteudo_page')).forEach((pg, i) => {
      if (pg.querySelector('.pagedjs_margin-top-center .sf-cabecalho, .pagedjs_margin-top-center')?.innerText.trim()) headers++;
      else problems.push(`no header p${pages.indexOf(pg)}`);
    });
    const works = new Set([...document.querySelectorAll('article.sf-trabalho[id]')].map(a => a.id));
    return { pages: pages.length, toc, folios, headers, works: works.size, problems };
  });

  if (outPdf) await page.pdf({ path: outPdf, preferCSSPageSize: true, printBackground: true });
  console.log(JSON.stringify({ volume, seconds, ...r, problems: r.problems.slice(0, 20), problemCount: r.problems.length }, null, 1));
  await browser.close();
  process.exit(r.problems.length ? 1 : 0);
})().catch(e => { console.error(e); process.exit(1); });
