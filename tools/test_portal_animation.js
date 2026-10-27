/** @file test_portal_animation.js
 * @brief Exercises expansion, collapse, lazy frame loading and reduced-motion behavior.
 */
'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { JSDOM } = require('jsdom');
const source = fs.readFileSync(path.join(__dirname, '../public/assets/app.js'), 'utf8');
const start = source.indexOf("\tfor (const toggle of document.querySelectorAll('[data-document-preview-toggle]'))");
const end = source.indexOf('\n});', start);
for (const reduced of [false, true])
{
	const dom = new JSDOM(`<div><article data-document-card><button data-document-preview-toggle data-preview-mode="frame" data-show-label="Open" data-hide-label="Close"></button><div data-document-preview-panel hidden><iframe data-document-preview-frame data-preview-src="/preview"></iframe></div></article><article data-document-card></article></div>`, { url: 'https://pulse.example', runScripts: 'outside-only' });
	const w = dom.window;
	w.matchMedia = () => ({ matches: reduced });
	const cards = [...w.document.querySelectorAll('[data-document-card]')];
	const animations = [];
	for (const card of cards)
	{
		card.getBoundingClientRect = () => ({ left: 0, top: 0, width: card.classList.contains('is-expanded') ? 800 : 400, height: card.classList.contains('is-expanded') ? 600 : 200 });
		card.animate = (frames, options) => animations.push({ frames, options });
	}
	w.eval(source.slice(start, end));
	const toggle = w.document.querySelector('button');
	const panel = w.document.querySelector('[data-document-preview-panel]');
	const frame = w.document.querySelector('iframe');
	toggle.click();
	assert.equal(toggle.getAttribute('aria-expanded'), 'true');
	assert.equal(panel.hidden, false);
	assert.equal(frame.dataset.loaded, 'true');
	assert.equal(frame.getAttribute('src'), '/preview');
	toggle.click();
	assert.equal(toggle.getAttribute('aria-expanded'), 'false');
	assert.equal(panel.hidden, true);
	assert.equal(cards[0].classList.contains('is-expanded'), false);
	assert.equal(animations.length, reduced ? 0 : 4);
	if (!reduced)
	{
		assert.match(animations[0].frames[0].transform, /scale\(0.5,/);
		assert.match(animations[2].frames[0].transform, /scale\(2,/);
	}
	dom.window.close();
}
console.log('Portal expand/collapse, lazy preview, and reduced motion: PASSED');
