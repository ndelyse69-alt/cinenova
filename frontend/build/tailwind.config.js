/**
 * Tailwind CLI config for CineNova's compiled production stylesheet.
 * Scans every backend PHP template for the utility classes actually
 * used (including bracket arbitrary-value classes like w-[1.125rem])
 * and outputs a static frontend/css/tailwind.css — no runtime JIT
 * compiler is shipped to the browser (unlike the Play CDN script
 * this replaces).
 *
 * Rebuild after changing/adding Tailwind classes in any template:
 *   npx tailwindcss -c frontend/build/tailwind.config.js \
 *       -i frontend/build/tailwind-input.css \
 *       -o frontend/css/tailwind.css --minify
 */
module.exports = {
  content: [
    '../../backend/**/*.php',
    '../js/**/*.js',
  ],
  theme: {
    extend: {},
  },
  plugins: [],
};
