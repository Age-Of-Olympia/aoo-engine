#!/usr/bin/env node
/**
 * AoO UI/UX wireframes — screenshot helper
 *
 * Drives a headless Chromium (via puppeteer, already installed in this repo)
 * to capture each wireframe at the right viewport size. Useful for quickly
 * sharing the current state of the design without asking reviewers to spin
 * up a local server.
 *
 * Prerequisites
 *   - Apache running locally and serving /var/www/html (the dev container does this by default)
 *   - puppeteer installed under /var/www/html/node_modules (already the case)
 *
 * Usage
 *   node docs/wireframes/screenshot.js            # capture all pages
 *   node docs/wireframes/screenshot.js --out=/some/dir
 *
 * Output goes to /tmp/wf-shots by default (volatile — not committed).
 */
const puppeteer = require('../../node_modules/puppeteer');
const path = require('path');
const fs = require('fs');

const args = Object.fromEntries(
    process.argv.slice(2).map(a => {
        const m = a.match(/^--([^=]+)=(.*)$/);
        return m ? [m[1], m[2]] : [a.replace(/^--/, ''), true];
    })
);

const BASE = args.base || 'http://localhost/docs/wireframes/';
const OUT  = args.out  || '/tmp/wf-shots';

const pages = [
    { url: 'index.html',                  file: '01-index.png',             viewport: { width: 1500, height: 2200 } },
    { url: 'desktop-main.html',           file: '02-desktop-main.png',      viewport: { width: 1600, height: 1700 } },
    { url: 'desktop-main.html#s-none',    file: '02b-desktop-main-none.png',viewport: { width: 1600, height: 1700 } },
    { url: 'desktop-main.html#s-player',  file: '02c-desktop-main-pvp.png', viewport: { width: 1600, height: 1700 } },
    { url: 'desktop-panel.html',          file: '03-desktop-panel.png',     viewport: { width: 1600, height: 1700 } },
    { url: 'desktop-panel.html#p-inv',    file: '03b-desktop-panel-inv.png',viewport: { width: 1600, height: 1700 } },
    { url: 'desktop-panel.html#p-log',    file: '03c-desktop-panel-log.png',viewport: { width: 1600, height: 1700 } },
    { url: 'mobile-main.html',            file: '04-mobile-main.png',       viewport: { width: 1500, height: 1500 } },
    { url: 'mobile-sheet.html',           file: '05-mobile-sheet.png',      viewport: { width: 1500, height: 2000 } },
];

(async () => {
    fs.mkdirSync(OUT, { recursive: true });
    const browser = await puppeteer.launch({
        headless: 'new',
        protocolTimeout: 120000,
        args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage', '--disable-gpu'],
    });
    for (const p of pages) {
        process.stdout.write(`-> ${p.url} ... `);
        const page = await browser.newPage();
        await page.setViewport(p.viewport);
        try {
            await page.goto(BASE + p.url, { waitUntil: 'domcontentloaded', timeout: 15000 });
            await page.evaluate(() => document.fonts.ready);
            await new Promise(r => setTimeout(r, 600));
            const out = path.join(OUT, p.file);
            await page.screenshot({ path: out, fullPage: false });
            console.log('saved ' + out);
        } catch (e) {
            console.log('FAILED: ' + e.message);
        } finally {
            await page.close();
        }
    }
    await browser.close();
})().catch(e => { console.error(e); process.exit(1); });
