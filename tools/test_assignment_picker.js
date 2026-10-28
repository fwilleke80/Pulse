/** @file test_assignment_picker.js
 * @brief Exercise the actual rendered document manager: node test_assignment_picker.js rendered.html.
 */
'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { JSDOM } = require('jsdom');
(async function ()
{
	const html = fs.readFileSync(process.argv[2], 'utf8');
	const dom = new JSDOM('<textarea id="draft">Unsaved message</textarea>' + html, { url: 'https://pulse.example', runScripts: 'outside-only' });
	const w = dom.window;
	const manager = w.document.querySelector('[data-recipient-document-manager]');
	const dialog = manager.querySelector('dialog');
	dialog.showModal = () => { dialog.open = true; };
	dialog.close = () => { dialog.open = false; };
	const table = manager.querySelector('[data-assigned-documents]');
	assert.equal(table.querySelectorAll('tbody tr').length, 1);
	assert.ok(!table.textContent.includes('Available'));
	assert.ok(!table.textContent.includes('PRIVATE_DESCRIPTION'));
	let posts = 0;
	w.fetch = async (url, options) =>
	{
		assert.equal(options.credentials, 'same-origin');
		if (options.method === 'POST')
		{
			posts++;
			assert.equal(options.body.get('ajax'), '1');
			assert.equal(options.body.get('id'), '7');
			assert.equal(options.body.get('document_action'), 'assign');
			assert.deepEqual(options.body.getAll('document_ids[]'), ['2']);
			return { ok: true, redirected: false, headers: { get: () => 'application/json' }, json: async () => ({ ok: true, message: 'Saved' }) };
		}
		return { ok: true, redirected: false, text: async () => html };
	};
	const source = fs.readFileSync(path.join(__dirname, '../public/assets/app.js'), 'utf8');
	const start = source.indexOf('/** @brief Refreshes only document assignments');
	w.eval(source.slice(start));
	await new Promise(resolve => setTimeout(resolve, 0));
	manager.querySelector('[data-assign-open]').click();
	assert.equal(dialog.open, true);
	const search = dialog.querySelector('[data-assign-search]');
	search.value = 'missing';
	search.dispatchEvent(new w.Event('input', { bubbles: true }));
	assert.equal(dialog.querySelector('[data-assign-choice]').hidden, true);
	search.value = 'available';
	search.dispatchEvent(new w.Event('input', { bubbles: true }));
	assert.equal(dialog.querySelector('[data-assign-choice]').hidden, false);
	const checkbox = dialog.querySelector('input[type="checkbox"]');
	checkbox.checked = true;
	checkbox.dispatchEvent(new w.Event('input', { bubbles: true }));
	assert.equal(dialog.querySelector('[data-assign-submit]').disabled, false);
	dialog.querySelector('form').dispatchEvent(new w.Event('submit', { cancelable: true, bubbles: true }));
	await new Promise(resolve => setTimeout(resolve, 20));
	assert.equal(posts, 1);
	assert.equal(dialog.open, false);
	assert.equal(manager.querySelector('[data-assignment-status]').textContent, 'Saved');
	assert.equal(w.document.querySelector('#draft').value, 'Unsaved message');
	assert.equal(dialog.querySelector('[data-assign-submit]').disabled, true);
	dom.window.close();
	console.log('Rendered assigned-only table, picker search, immediate assignment and unrelated draft preservation: PASSED');
})();
