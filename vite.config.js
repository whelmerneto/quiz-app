import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            // Self-hosted at build time. The aliases become --font-display,
            // --font-sans and --font-mono, which glass.css and the Tailwind
            // theme both read.
            fonts: [
                bunny('Bricolage Grotesque', {
                    alias: 'display',
                    weights: [700, 800],
                    // The metric-matched fallback needs the optional "fontaine"
                    // package; the stack below is close enough not to pay for it.
                    optimizedFallbacks: false,
                    fallbacks: ['ui-sans-serif', 'system-ui', 'sans-serif'],
                }),
                bunny('IBM Plex Sans', {
                    alias: 'sans',
                    weights: [400, 500, 600],
                    optimizedFallbacks: false,
                    fallbacks: ['ui-sans-serif', 'system-ui', 'sans-serif'],
                }),
                bunny('IBM Plex Mono', {
                    alias: 'mono',
                    weights: [500],
                    optimizedFallbacks: false,
                    fallbacks: ['ui-monospace', 'SFMono-Regular', 'Menlo', 'monospace'],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
