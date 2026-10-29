'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const root = path.resolve(__dirname, '..');
const init = fs.readFileSync(path.join(root, 'views/partials/theme-init.php'), 'utf8');
const match = init.match(/<script>([\s\S]*?)<\/script>/);
assert.ok(match, 'The pre-paint theme initializer remains present');
let checks = 0;
for (const saved of ['dark', 'light', undefined]) {
  for (const systemDark of [true, false]) {
    for (const blocked of [false, true]) {
      const values = new Map([['unrelated-setting', 'keep-me']]);
      if (saved !== undefined) values.set('lsc-theme', saved);
      const attributes = new Map([['data-theme', 'dark']]);
      const element = {
        style: {},
        setAttribute: (name, value) => attributes.set(name, value),
        removeAttribute: name => attributes.delete(name),
      };
      const storage = {
        getItem: name => { if (blocked) throw new Error('Storage blocked'); return values.get(name) ?? null; },
        setItem: (name, value) => { if (blocked) throw new Error('Storage blocked'); values.set(name, value); },
        removeItem: name => { if (blocked) throw new Error('Storage blocked'); values.delete(name); },
      };
      vm.runInNewContext(match[1], {
        document: {documentElement: element}, localStorage: storage,
        window: {matchMedia: () => ({matches: systemDark})},
      }, {timeout: 1000});
      assert.equal(attributes.get('data-theme'), 'light', `Light-only with saved=${saved}, systemDark=${systemDark}, blockedStorage=${blocked}`);
      assert.equal(element.style.colorScheme, 'light', 'Native form controls stay light');
      assert.equal(values.get('unrelated-setting'), 'keep-me', 'Unrelated browser preferences are preserved');
      if (!blocked) assert.notEqual(values.get('lsc-theme'), 'dark', 'An old dark preference is removed or replaced');
      checks++;
    }
  }
}
assert.match(init, /<meta\s+name="color-scheme"\s+content="light"\s*\/?\s*>/, 'Declare light color scheme before first paint');
const toggle = fs.readFileSync(path.join(root, 'views/partials/theme-toggle.php'), 'utf8');
assert.doesNotMatch(toggle, /<button\b|<script\b|data-lsc-theme-toggle|lscToggleTheme\s*\(/i, 'No rendered dark-mode control or switching script');
console.log(`PASS: ${checks} light-only preference/system/storage cases; form-control scheme and removed toggle verified.`);
