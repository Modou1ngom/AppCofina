/**
 * Capture les captures d'écran du manuel missions.
 * Prérequis : php artisan serve (port 8000) + npm run dev (Vite)
 * Usage : node scripts/capture-manuel-screenshots.mjs
 */
import { chromium } from 'playwright';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const OUT_DIR = path.join(__dirname, '..', 'docs', 'images', 'manuel-missions');
const BASE_URL = process.env.APP_URL || 'http://127.0.0.1:8000';
const PASSWORD = 'password';

fs.mkdirSync(OUT_DIR, { recursive: true });

async function login(page, email) {
    await page.context().clearCookies();
    await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle' });
    await page.waitForSelector('#email', { timeout: 15000 });
    await page.fill('#email', email);
    await page.fill('#password', PASSWORD);
    await page.getByRole('button', { name: /connexion|se connecter|login/i }).click();
    await page.waitForURL((url) => !url.pathname.includes('/login'), { timeout: 30000 });
    await page.waitForTimeout(1500);
}

async function shot(page, filename, options = {}) {
    const filePath = path.join(OUT_DIR, filename);
    await page.screenshot({ path: filePath, fullPage: options.fullPage ?? true, ...options });
    console.log(`✓ ${filename}`);
    return filePath;
}

async function shotLocator(page, locator, filename) {
    const filePath = path.join(OUT_DIR, filename);
    await locator.screenshot({ path: filePath });
    console.log(`✓ ${filename}`);
}

const browser = await chromium.launch({ headless: true });
const context = await browser.newContext({
    viewport: { width: 1440, height: 900 },
    locale: 'fr-FR',
});
const page = await context.newPage();

try {
    // --- Demandeur (test@example.com) ---
    await login(page, 'test@example.com');

    await page.goto(`${BASE_URL}/missions`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1000);
    await shot(page, '01-tableau-de-bord.png');

    const searchBar = page.locator('form').filter({ has: page.getByPlaceholder(/Ex\. 42|N°/i) }).first();
    if (await searchBar.count()) {
        await shotLocator(page, searchBar, '02-recherche-tableau-de-bord.png');
    }

    await page.goto(`${BASE_URL}/missions/create`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1000);
    await shot(page, '03-creation-mission.png');

    const sitesSection = page.locator('text=Sites de la mission').locator('xpath=ancestor::div[contains(@class,"col-span-2") or contains(@class,"space-y")]').first();
    if (await sitesSection.count()) {
        await shotLocator(page, sitesSection, '04-sites-menus-deroulants.png');
    } else {
        await shot(page, '04-sites-menus-deroulants.png');
    }

    await page.goto(`${BASE_URL}/missions/14`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1000);
    await shot(page, '05-fiche-brouillon.png');

    await page.goto(`${BASE_URL}/missions/16`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1000);
    await shot(page, '06-fiche-mission-en-cours.png');

    await page.goto(`${BASE_URL}/missions/rapports`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1000);
    await shot(page, '07-rapports-mission.png');

    await page.goto(`${BASE_URL}/missions/traitees`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1000);
    await shot(page, '08-missions-traitees.png');

    // --- DGA ---
    await login(page, 'dga@example.com');
    await page.goto(`${BASE_URL}/missions/validation/dga`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1000);
    await shot(page, '09-validations-dga.png');

    // --- MD ---
    await login(page, 'md@example.com');
    await page.goto(`${BASE_URL}/missions/validation/md`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1000);
    await shot(page, '10-validations-md.png');

    // --- Facilities ---
    await login(page, 'facilities@example.com');
    await page.goto(`${BASE_URL}/missions/validation/facilities`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1000);
    await shot(page, '11-facilities-liste.png');

    await page.goto(`${BASE_URL}/missions/validation/facilities/3`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1500);
    await shot(page, '12-facilities-traitement.png');

    await page.goto(`${BASE_URL}/missions/recap-logistique?context=facilities`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1000);
    await shot(page, '13-recap-logistique-facilities.png');

    // --- RH ---
    await login(page, 'rh@example.com');
    await page.goto(`${BASE_URL}/missions/validation/rh-logistique`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1000);
    await shot(page, '14-validation-rh.png');

    // --- RRH ---
    await login(page, 'rrh@example.com');
    await page.goto(`${BASE_URL}/missions/validation/signature-rrh`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1000);
    await shot(page, '15-signature-rrh.png');

    // --- Finance ---
    await login(page, 'finance@example.com');
    await page.goto(`${BASE_URL}/missions/validation/finance`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1000);
    await shot(page, '16-validation-finance.png');

    await page.goto(`${BASE_URL}/missions/recap-logistique?context=finance`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1000);
    await shot(page, '17-recap-logistique-finance.png');

    // --- Menu sidebar ---
    await login(page, 'test@example.com');
    await page.goto(`${BASE_URL}/missions`, { waitUntil: 'networkidle' });
    const sidebar = page.locator('[data-sidebar="sidebar"], nav, aside').first();
    if (await sidebar.count()) {
        await shotLocator(page, sidebar, '00-menu-gestion-missions.png');
    }

    console.log(`\nCaptures enregistrées dans : ${OUT_DIR}`);
} catch (error) {
    console.error('Erreur capture :', error);
    process.exitCode = 1;
} finally {
    await browser.close();
}
