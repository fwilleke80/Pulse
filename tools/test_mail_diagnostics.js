/** @file test_mail_diagnostics.js
 * @brief Runs against rendered monitor HTML containing owner/recipient/safety draft errors.
 */
'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { JSDOM } = require('jsdom');
(async function ()
{
	const dom = new JSDOM(fs.readFileSync(process.argv[2], 'utf8'), { url: 'https://pulse.example/monitors/edit?id=8&tab=messages&section=recipient&language=de&field=recipient_default_subject_de', runScripts: 'outside-only' });
	const w = dom.window;
	const opened = [];
	for (const tab of w.document.querySelectorAll('[data-tab-target], [data-subtab-target], [data-language-target]'))
	{
		tab.addEventListener('click', event =>
		{
			event.preventDefault();
			opened.push(tab.dataset.tabTarget || tab.dataset.subtabTarget || tab.dataset.languageTarget);
			const name = tab.dataset.tabTarget || tab.dataset.subtabTarget || tab.dataset.languageTarget;
			const attribute = tab.dataset.tabTarget ? 'data-tab-panel' : tab.dataset.subtabTarget ? 'data-subtab-panel' : 'data-language-panel';
			for (const panel of w.document.querySelectorAll('[' + attribute + '="' + name + '"]'))
			{
				panel.hidden = false;
			}
		});
	}
	const source = fs.readFileSync(path.join(__dirname, '../public/assets/app.js'), 'utf8');
	w.eval(source.slice(source.indexOf('/** @brief Reveals exact editor fields')));
	await new Promise(resolve => setTimeout(resolve, 0));
	assert.deepEqual(opened.slice(0,3), ['messages','recipient','de']);
	const subject = w.document.getElementById('recipient_default_subject_de');
	assert.equal(w.document.activeElement, subject);
	assert.equal(subject.getAttribute('aria-invalid'), 'true');
	assert.ok(subject.validity.valid, 'Draft errors must not block native submission');
	assert.ok(w.document.querySelector('[data-subtab-target="recipient"] .mail-issue-marker'));
	const saved = w.document.querySelector('.mail-readiness-summary').textContent;
	assert.ok(saved.includes('Subject is missing'));
	assert.ok(w.document.querySelector('.mail-readiness-summary a[href*="field=recipient_default_subject_de"]'));
	subject.value = 'Fixed';
	subject.dispatchEvent(new w.Event('input', { bubbles: true }));
	assert.equal(subject.getAttribute('aria-invalid'), 'false');
	assert.equal(w.document.querySelector('[data-subtab-target="recipient"] .mail-issue-marker'), null);
	assert.equal(w.document.querySelector('.mail-readiness-summary').textContent, saved, 'Saved summary must not claim an unsaved correction is applied');
	for (const [id,value] of [['owner_due_notice_subject','Owner'],['safety_invitation_body_de','Go {url}']])
	{
		const input = w.document.getElementById(id);
		input.value = value;
		input.dispatchEvent(new w.Event('input', { bubbles: true }));
	}
	assert.equal(w.document.querySelectorAll('.mail-issue-marker').length, 0);
	dom.window.close();
	console.log('Exact deep link, draft field/subtab/language markers, saveability and saved-vs-draft distinction: PASSED');
})();
