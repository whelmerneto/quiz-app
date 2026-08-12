/**
 * Measures the contrast of every text run against the pixels actually composited
 * behind it, in the DEFAULT translucent state — not against the opaque fallback.
 *
 * Glass is the reason this script exists. A token's ratio against --glass-solid
 * says nothing about the shipped page, because at rest the mesh gradient shows
 * through the tint and the ground under a given word depends on where that word
 * sits. So: screenshot the page, screenshot it again with the text turned
 * transparent, and compare each glyph's colour against the ground revealed
 * underneath it. Glyph pixels only — a bounding-box average washes out the thin
 * strokes that actually fail.
 *
 *   node docs/contrast-probe.mjs [url ...]
 */
import { chromium } from 'playwright';
import { PNG } from 'pngjs';

const TARGETS = process.argv.slice(2);
const VIEWPORTS = [
    { name: '390', width: 390, height: 844 },
    { name: '1440', width: 1440, height: 900 },
];

const srgbToLinear = (c) => {
    const s = c / 255;

    return s <= 0.04045 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4;
};

const luminance = ([r, g, b]) =>
    0.2126 * srgbToLinear(r) + 0.7152 * srgbToLinear(g) + 0.0722 * srgbToLinear(b);

const ratio = (a, b) => {
    const [hi, lo] = luminance(a) >= luminance(b) ? [a, b] : [b, a];

    return (luminance(hi) + 0.05) / (luminance(lo) + 0.05);
};

const parseRgb = (value) => value.match(/\d+(\.\d+)?/g).slice(0, 3).map(Number);

async function probe(page, url, viewport) {
    await page.setViewportSize(viewport);
    await page.goto(url, { waitUntil: 'networkidle' });
    // The mesh drifts, so freeze it or the two captures disagree on the ground.
    await page.addStyleTag({ content: '*,*::before,*::after{animation:none!important;transition:none!important}' });
    await page.waitForTimeout(250);

    const runs = await page.evaluate(() => {
        const out = [];
        const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);

        for (let node = walker.nextNode(); node; node = walker.nextNode()) {
            const text = node.textContent.trim();

            if (text === '') {
                continue;
            }

            const el = node.parentElement;

            if (el.closest('[aria-hidden="true"] .sr-only, .sr-only') !== null) {
                continue;
            }

            const style = getComputedStyle(el);
            const box = el.getBoundingClientRect();

            if (box.width === 0 || box.height === 0 || style.visibility === 'hidden') {
                continue;
            }

            const size = parseFloat(style.fontSize);
            const weight = Number.parseInt(style.fontWeight, 10) || 400;
            // WCAG large text: 24px, or 18.66px at 700+.
            const large = size >= 24 || (size >= 18.66 && weight >= 700);

            out.push({
                text: text.slice(0, 34),
                color: style.color,
                required: large ? 3 : 4.5,
                box: { x: box.x, y: box.y, width: box.width, height: box.height },
            });
        }

        return out;
    });

    const shot = async () => PNG.sync.read(await page.screenshot({ animations: 'disabled' }));

    const normal = await shot();
    await page.addStyleTag({ content: '*{color:transparent!important;text-shadow:none!important}' });
    await page.waitForTimeout(120);
    const blank = await shot();

    const scale = normal.width / viewport.width;
    const results = [];

    for (const run of runs) {
        const fg = parseRgb(run.color);
        const ratios = [];

        const x0 = Math.max(0, Math.floor(run.box.x * scale));
        const y0 = Math.max(0, Math.floor(run.box.y * scale));
        const x1 = Math.min(normal.width, Math.ceil((run.box.x + run.box.width) * scale));
        const y1 = Math.min(normal.height, Math.ceil((run.box.y + run.box.height) * scale));

        for (let y = y0; y < y1; y++) {
            for (let x = x0; x < x1; x++) {
                const i = (normal.width * y + x) * 4;
                const painted = [normal.data[i], normal.data[i + 1], normal.data[i + 2]];
                const ground = [blank.data[i], blank.data[i + 1], blank.data[i + 2]];

                // A glyph pixel is one the text actually changed, and changed
                // most of the way — antialiased edges sit between the two and
                // would report a softer ratio than the stroke really has.
                const delta = Math.abs(painted[0] - ground[0])
                    + Math.abs(painted[1] - ground[1])
                    + Math.abs(painted[2] - ground[2]);
                const toFg = Math.abs(painted[0] - fg[0])
                    + Math.abs(painted[1] - fg[1])
                    + Math.abs(painted[2] - fg[2]);

                if (delta > 24 && toFg < 30) {
                    ratios.push(ratio(fg, ground));
                }
            }
        }

        if (ratios.length < 8) {
            continue;
        }

        ratios.sort((a, b) => a - b);

        results.push({
            text: run.text,
            required: run.required,
            min: ratios[0],
            median: ratios[Math.floor(ratios.length / 2)],
        });
    }

    return results;
}

const browser = await chromium.launch();
const context = await browser.newContext();

// Some runs only exist for the browser that owns the round — the result page's
// review grid and its badge — so the probe has to be able to carry a session.
//   QUIZ_COOKIES='name=value; other=value' node docs/contrast-probe.mjs …
if (process.env.QUIZ_COOKIES !== undefined) {
    await context.addCookies(
        process.env.QUIZ_COOKIES
            .split(';')
            .map((pair) => pair.trim())
            .filter((pair) => pair.includes('='))
            .map((pair) => {
                const [name, ...rest] = pair.split('=');

                return { name, value: rest.join('='), domain: 'localhost', path: '/' };
            })
    );
}

const page = await context.newPage();
let failures = 0;

for (const url of TARGETS) {
    for (const viewport of VIEWPORTS) {
        const results = await probe(page, url, viewport);

        console.log(`\n=== ${url} @${viewport.name} ===`);

        for (const r of results) {
            const ok = r.min >= r.required;

            failures += ok ? 0 : 1;
            console.log(
                `${ok ? 'pass' : 'FAIL'}  min ${r.min.toFixed(2)}  med ${r.median.toFixed(2)}`
                + `  need ${r.required}   ${JSON.stringify(r.text)}`
            );
        }
    }
}

await browser.close();
console.log(`\n${failures} failing run(s).`);
process.exit(failures === 0 ? 0 : 1);
