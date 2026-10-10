// It. 48 — make faces-bench: lo mismo que make e2e, pero solo las mediciones (*.bench.js).
import base from '../playwright.config.js';

export default { ...base, testDir: '.', testMatch: /.*\.bench\.js$/, outputDir: '../../../storage/framework/testing/bench' };
