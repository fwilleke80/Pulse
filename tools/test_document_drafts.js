/**
 * @file test_document_drafts.js
 * @brief Minimal browser-free tests of the text-document draft controller.
 * @example node tools/test_document_drafts.js
 */

'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

/** @brief Creates an event source supporting browser-style handlers. */
function emitter(object = {})
{
	const events = new Map();
	object.addEventListener = function (type, listener)
	{
		const listeners = events.get(type) || [];
		listeners.push(listener);
		events.set(type, listeners);
	};
	object.fire = async function (type, event = {})
	{
		for (const listener of events.get(type) || [])
		{
			await listener(event);
		}
	};
	return object;
}

/** @brief Instantiates only the draft controller, without loading the rest of the browser UI. */
function setup(existing = new Map(), response = null)
{
	const source = fs.readFileSync(path.join(__dirname, '..', 'public', 'assets', 'app.js'), 'utf8');
	const start = source.indexOf("\tfor (const form of document.querySelectorAll('[data-document-dirty-guard]'))");
	const end = source.indexOf("\n\tfor (const form of document.querySelectorAll('[data-recipient-documents-assignment-form]'))", start);
	assert.ok(start > 0 && end > start);
	const block = source.slice(start, end);
	const title = emitter({name: 'title', value: 'Original', readOnly: false});
	const body = emitter({name: 'text_content', value: 'Original body', readOnly: false});
	const submit = {disabled: false};
	const fieldset = {disabled: false};
	const status = {textContent: 'server'};
	const recovery = {hidden: true};
	const restore = emitter();
	const discard = emitter();
	const panel = {
		dataset: {
			saved: 'server', pending: 'pending', local: 'browser', failed: 'storage fail',
			restorable: 'restore available', saving: 'saving', saveFailed: 'server fail',
		},
		querySelector(selector)
		{
			return ({
				'[data-document-draft-status]': status,
				'[data-document-draft-recovery]': recovery,
				'[data-document-draft-restore]': restore,
				'[data-document-draft-discard]': discard,
			})[selector] || null;
		},
	};
	const back = emitter();
	const form = emitter({
		action: '/monitors/documents/text/update',
		dataset: {documentDraftKey: 'test:monitor:123:document:456', unsavedConfirm: 'Discard?'},
		hasAttribute(name)
		{
			return name === 'data-document-autosave';
		},
		querySelectorAll(selector)
		{
			return selector === '[data-document-back]' ? [back] : [title, body];
		},
		querySelector(selector)
		{
			return ({
				'[data-document-draft-panel]': panel,
				'input[name="title"]': title,
				'textarea[name="text_content"]': body,
				'button[type="submit"]': submit,
				'fieldset': fieldset,
			})[selector] || null;
		},
	});
	const document = emitter({
		hidden: false,
		querySelectorAll()
		{
			return [form];
		},
	});
	const timers = new Map();
	let nextTimer = 0;
	const redirects = [];
	const window = emitter({
		localStorage: {
			getItem(key)
			{
				return existing.get(key) || null;
			},
			setItem(key, val)
			{
				existing.set(key, val);
			},
			removeItem(key)
			{
				existing.delete(key);
			},
		},
		setTimeout(fn)
		{
			const id = ++nextTimer;
			timers.set(id, fn);
			return id;
		},
		clearTimeout(id)
		{
			timers.delete(id);
		},
		confirm()
		{
			return true;
		},
		location: {
			assign(target)
			{
				redirects.push(target);
			},
		},
	});
	const fetch = async () =>
	{
		if (response instanceof Error)
		{
			throw response;
		}
		return {ok: response?.ok ?? true, json: async () => response?.payload ?? {saved: true, redirect: '/monitors'}};
	};
	const FormData = class {};
	const AbortController = class
	{
		constructor()
		{
			this.signal = {};
		}
		abort() {}
	};
	new Function('document', 'window', 'fetch', 'FormData', 'AbortController', block)(document, window, fetch, FormData, AbortController);
	return {
		form, title, body, document, window, status, recovery, restore, discard,
		redirects, storage: existing,
		flush()
		{
			for (const fn of [...timers.values()])
			{
				fn();
			}
			timers.clear();
		},
	};
}

/** @brief Exercises local recovery, failed writes, successful writes and discard. */
async function run()
{
	const saved = new Map();
	let ui = setup(saved, new Error('network disconnected'));
	assert.equal(ui.status.textContent, 'server');
	ui.body.value = 'Changed body';
	await ui.body.fire('input');
	assert.equal(ui.status.textContent, 'pending');
	ui.flush();
	assert.equal(ui.status.textContent, 'browser');
	assert.equal(JSON.parse([...saved.values()][0]).content, 'Changed body');
	await ui.form.fire('submit', {preventDefault() {}});
	assert.equal(ui.status.textContent, 'server fail');
	assert.equal(JSON.parse([...saved.values()][0]).content, 'Changed body');
	assert.equal(ui.redirects.length, 0);

	ui = setup(saved);
	assert.equal(ui.status.textContent, 'restore available');
	assert.equal(ui.title.readOnly, true);
	assert.equal(ui.recovery.hidden, false);
	await ui.restore.fire('click');
	assert.equal(ui.body.value, 'Changed body');
	assert.equal(ui.title.readOnly, false);
	await ui.form.fire('submit', {preventDefault() {}});
	assert.equal(saved.size, 0);
	assert.equal(ui.redirects[0], '/monitors');

	saved.set('test:monitor:123:document:456', JSON.stringify({format: 1, title: 'A', content: 'B'}));
	ui = setup(saved);
	await ui.discard.fire('click');
	assert.equal(saved.size, 0);
	assert.equal(ui.status.textContent, 'server');
	assert.equal(ui.title.readOnly, false);

	console.log('Document draft recovery, failed save, successful save and discard: PASSED');
}

run().catch(function (error)
{
	console.error(error);
	process.exitCode = 1;
});
