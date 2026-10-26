'use strict';
// Offline characterization of the actual filter handler/listener. No network or app bootstrap.
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const root = process.argv[2] || path.resolve(__dirname, '..');
const view = fs.readFileSync(path.join(root, 'views/tasks/index.php'), 'utf8');
const controller = fs.readFileSync(path.join(root, 'app/Tasks/TaskController.php'), 'utf8');
let failed = 0;
function test(name, body) {
  try { body(); console.log('PASS: ' + name); }
  catch (error) { failed++; console.error('FAIL: ' + name + ': ' + error.message); }
}
function assert(ok, message) { if (!ok) throw new Error(message); }
const payloads = ["';globalThis.stage2Marker=1;//", "'", '"', '&quot;', '\\', '<img src=x>', '\r\n', 'pending&group_id=999'];
const old = view.match(/<select\s+onchange="([^"]+)"/);
if (old) {
  test('crafted status cannot execute through the actual old group-filter handler', () => {
    const handler = old[1].replace('<?= htmlspecialchars($selected_status) ?>', payloads[0]);
    const sandbox = {window: {location: ''}, stage2Marker: 0};
    vm.runInNewContext('(function(){\n' + handler + '\n}).call({value:"all"})', sandbox);
    assert(sandbox.stage2Marker === 0, 'synthetic marker executed: original mixed HTML/JavaScript sink remains');
  });
}
const elements = new Map();
for (const id of ['task-group-filter', 'task-status-filter']) {
  elements.set(id, {value: 'all', listeners: {}, addEventListener(type, fn) { this.listeners[type] = fn; }});
}
elements.set('createTaskModal', {addEventListener() {}});
const scripts = [...view.matchAll(/<script>([\s\S]*?)<\/script>/g)].map(m => m[1]).join('\n');
const context = {document: {getElementById(id) { return elements.get(id); }}, window: {location: ''}, URLSearchParams};
vm.createContext(context);
vm.runInContext(scripts, context);
test('both filter selects avoid inline executable handlers', () => {
  assert(!old && !/<select\s+onchange=/.test(view), 'interpolated inline filter handler remains');
  assert(view.includes('id="task-group-filter"') && view.includes('id="task-status-filter"'), 'stable filter controls absent');
});
test('controller normalizes GET and POST status using a strict allowlist', () => {
  assert(controller.includes("self::normalizeStatus($_GET['status'] ?? 'all')"), 'GET status is not normalized');
  assert(controller.includes("self::normalizeStatus($_POST['status'] ?? 'all')"), 'POST status is not normalized');
  assert(controller.includes("in_array($status, ['all', 'pending', 'done'], true)"), 'strict status allowlist missing');
});
test('actual listeners preserve all valid filters and encode arbitrary values as query data', () => {
  const group = elements.get('task-group-filter');
  const status = elements.get('task-status-filter');
  assert(group.listeners.change && status.listeners.change, 'static change listeners missing');
  for (const value of ['all', 'pending', 'done', ...payloads]) {
    group.value = '123'; status.value = value;
    status.listeners.change();
    const target = new URL(context.window.location, 'https://fixture.invalid/');
    assert(target.pathname === '/tasks.php', 'filter navigation leaves the fixed route');
    assert(target.searchParams.get('group_id') === '123' && target.searchParams.get('status') === value, 'query data changed');
    assert([...target.searchParams.keys()].length === 2, 'payload introduced another query field');
    assert(context.stage2Marker === undefined, 'payload executed');
  }
});
console.log(JSON.stringify({scope: 'actual-source offline filter VM; PHP and browser rendering tested separately', failed}));
process.exitCode = failed ? 1 : 0;
