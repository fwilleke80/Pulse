/** @brief Tests natural/numeric sorting, direction, refresh retention and table isolation. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { JSDOM } = require('jsdom');
(async function ()
{
	const dom = new JSDOM(fs.readFileSync(process.argv[2], 'utf8'), { runScripts: 'outside-only' });
	const w = dom.window;
	const table = w.document.querySelector('[data-document-table]');
	const row = table.tBodies[0].rows[0].cloneNode(true);
	table.tBodies[0].replaceChildren();
	for (const [id, name, type, size] of [[1,'File 10','Image',900],[2,'File 2','Audio',2000000],[3,'File 1','PDF',10000]])
	{
		const item = row.cloneNode(true);
		Object.assign(item.dataset, { documentId: String(id), documentName: name, documentType: type, documentSize: String(size) });
		table.tBodies[0].append(item);
	}
	const other = table.cloneNode(true);
	other.dataset.documentTable += '-other';
	table.after(other);
	const source = fs.readFileSync(path.join(__dirname, '../public/assets/app.js'), 'utf8');
	w.eval(source.slice(source.indexOf('/** @brief Sorts document tables')));
	await new Promise(resolve => setTimeout(resolve, 0));
	const ids = target => Array.from(target.tBodies[0].rows).map(item => item.dataset.documentId);
	const click = key => table.querySelector('[data-document-sort="' + key + '"]').click();
	assert.deepEqual(ids(table), ['3','2','1']);
	click('name'); assert.deepEqual(ids(table), ['1','2','3']);
	click('size'); assert.deepEqual(ids(table), ['1','3','2']);
	click('size'); assert.deepEqual(ids(table), ['2','3','1']);
	assert.equal(table.querySelector('[data-document-sort="size"]').closest('th').getAttribute('aria-sort'), 'descending');
	assert.deepEqual(ids(other), ['3','2','1']);
	const replacement = table.cloneNode(true);
	replacement.tBodies[0].append(...Array.from(replacement.tBodies[0].rows).reverse());
	table.replaceWith(replacement);
	w.document.dispatchEvent(new w.Event('pulse:documents-refreshed'));
	assert.deepEqual(ids(replacement), ['2','3','1']);
	replacement.querySelector('[data-document-sort="type"]').click();
	assert.deepEqual(ids(replacement), ['2','1','3']);
	dom.window.close();
	console.log('Document sorting: natural names, numeric sizes, types, directions, aria-sort, isolation and refresh retention passed');
})();
