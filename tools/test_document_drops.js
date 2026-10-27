/** @file test_document_drops.js
 * @brief DOM regression tests for file drops; run with jsdom available on NODE_PATH.
 */
'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { JSDOM } = require('jsdom');

/** @brief Guards against the misplaced recipient status element that disabled all drop handlers. */
for (const template of ['recipients/edit.php', 'monitors/edit.php'])
{
	const php = fs.readFileSync(path.join(__dirname, '../app/Views', template), 'utf8');
	const dom = new JSDOM(php.replace(/<\?php[\s\S]*?\?>|<\?=[\s\S]*?\?>/g, ''));
	const zone = dom.window.document.querySelector('[data-document-drop-zone]');
	assert.ok(zone?.querySelector('.document-upload-form'), template + ': upload form inside zone');
	assert.ok(zone?.querySelector('[data-drop-status]'), template + ': status inside zone');
	assert.ok(zone?.querySelector('.document-drop-hint'), template + ': visible hint inside zone');
}

/** @brief Exercises a real drop handler, including rejected files and unsaved recipient choices. */
async function Run(recipient)
{
	const dom = new JSDOM(`<!doctype html><section data-document-drop-zone data-drop-uploading="Uploading" data-drop-failed="Failed" data-drop-edit="Edit">
	<form class="document-upload-form" action="https://pulse.example/monitors/documents/upload">
	<input name="_csrf_token" value="csrf-test"><input name="monitor_id" value="7">
	${recipient ? '<input name="recipient_id" value="8">' : ''}
	<input type="file" name="document_file"><input name="title" value="Unsaved title"><textarea name="description">Unsaved description</textarea></form>
	<div data-drop-status></div>
	${recipient ? '<form data-recipient-documents-assignment-form><div class="configuration-block"><input type="checkbox" name="document_ids[]" value="42" checked></div></form>' : ''}</section>`, { url: 'https://pulse.example', runScripts: 'outside-only' });
	const w = dom.window;
	w.matchMedia = () => ({ matches: false, addEventListener() {} });
	w.confirm = () => true;
	let calls = 0;
	w.fetch = async (url, options) =>
	{
		calls++;
		assert.equal(options.credentials, 'same-origin');
		assert.equal(options.body.get('_csrf_token'), 'csrf-test');
		assert.equal(options.body.get('monitor_id'), '7');
		assert.equal(options.body.get('description'), '');
		assert.equal(options.body.get('drop_upload'), '1');
		assert.equal(options.body.get('title'), options.body.get('document_file').name);
		if (recipient) { assert.equal(options.body.get('recipient_id'), '8'); }
		const rejected = calls === 2;
		return { ok: !rejected, redirected: false, headers: { get: () => 'application/json' }, json: async () => ({ saved: !rejected, message: rejected ? 'File type not allowed' : 'Uploaded', id: calls + 100, title: options.body.get('title') }) };
	};
	const source = fs.readFileSync(path.join(__dirname, '../public/assets/app.js'), 'utf8');
	// Run only the two independently scoped modules to avoid unrelated browser APIs in jsdom.
	const dropStart = source.indexOf("\t// A file dropped outside");
	const dropEnd = source.indexOf('\n\tconst backupEncrypt', dropStart);
	const assignmentsStart = source.indexOf("\tfor (const form of document.querySelectorAll('[data-recipient-documents-assignment-form]'))");
	const assignmentsEnd = source.indexOf("\n\tfor (const toggle of document.querySelectorAll('[data-document-upload-toggle]'))", assignmentsStart);
	w.eval(source.slice(dropStart, dropEnd) + '\n' + source.slice(assignmentsStart, assignmentsEnd));
	const zone = w.document.querySelector('[data-document-drop-zone]');
	const outside = new w.Event('drop', { cancelable: true, bubbles: true });
	Object.defineProperty(outside, 'dataTransfer', { value: { types: ['Files'], files: [] } });
	w.document.body.dispatchEvent(outside);
	assert.equal(outside.defaultPrevented, true, 'Outside drop cannot navigate away');
	assert.equal(calls, 0);
	if (recipient) { w.document.querySelector('input[value="42"]').checked = false; }
	const files = [new w.File(['hello'], '<img src=x onerror=alert(1)>.txt'), new w.File(['bad'], 'blocked.exe'), new w.File(['world'], 'second.txt')];
	const drop = new w.Event('drop', { cancelable: true });
	Object.defineProperty(drop, 'dataTransfer', { value: { types: ['Files'], files } });
	zone.dispatchEvent(drop);
	for (let n = 0; n < 100 && zone.hasAttribute('aria-busy'); n++)
	{
		await new Promise(resolve => setTimeout(resolve, 1));
	}
	assert.equal(calls, 3);
	assert.equal(drop.defaultPrevented, true);
	assert.equal(zone.querySelectorAll('[data-drop-list] > div').length, 2);
	assert.equal(zone.querySelectorAll('img').length, 0, 'Filenames must be text, never HTML');
	assert.equal(zone.querySelector('.upload-error').textContent, 'blocked.exe: File type not allowed');
	assert.equal(zone.querySelector('input[name="title"]').value, 'Unsaved title');
	assert.equal(zone.querySelector('textarea').value, 'Unsaved description');
	if (recipient)
	{
		assert.equal(zone.querySelector('input[value="42"]').checked, false, 'Existing unsaved assignment preserved');
		assert.equal(zone.querySelector('input[value="101"]').checked, true, 'Upload assigned to recipient');
		zone.querySelector('input[value="42"]').checked = true;
		zone.querySelector('input[value="101"]').checked = false;
		const unload = new w.Event('beforeunload', { cancelable: true });
		w.dispatchEvent(unload);
		assert.equal(unload.defaultPrevented, true, 'New assignment edits must trigger unsaved guard');
	}
	console.log((recipient ? 'Recipient' : 'Monitor') + ': multi-file drop, rejection, escaping and unsaved state passed');
	w.close();
}
(async () =>
{
	await Run(false);
	await Run(true);
})().catch(error =>
{
	console.error(error);
	process.exitCode = 1;
});
