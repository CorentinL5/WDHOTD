const test = require('node:test');
const assert = require('node:assert/strict');
const collection = require('../assets/js/collection.js');
function memory() { const values = new Map(); return { getItem: key => values.get(key) || null, setItem: (key, value) => values.set(key, value) }; }
function story(overrides = {}) { return { id: 1, type: 'events', month: 2, day: 29, year: 2000, selected_title: 'A saved story', event_text: 'The actual historical event.', extract_short: 'A little context.', wikipedia_url: 'https://en.wikipedia.org/wiki/History', ...overrides }; }
function backup(stories) { return JSON.stringify({ app: 'WDHOTD', version: 1, stories }); }
test('saved stories retain their text and can be recovered in another browser', () => {
    const original = collection.create(memory());
    original.save(story());
    const another = collection.create(memory());
    assert.equal(another.import(original.export()), 1);
    assert.equal(another.read()[0].event_text, story().event_text);
    assert.equal(another.read()[0].extract_short, story().extract_short);
});
test('database IDs changing do not duplicate a saved story', () => {
    const saved = collection.create(memory());
    saved.save(story()); saved.save(story({ id: 999, selected_title: 'A corrected article title' }));
    assert.equal(saved.read().length, 1);
});
test('Unicode source URLs use a stable identity', () => {
    const saved = collection.create(memory());
    const item = story({ wikipedia_url: 'https://en.wikipedia.org/wiki/Émile' });
    saved.save(item);
    assert.equal(saved.has(item), true);
    assert.equal(saved.has(story({ wikipedia_url: 'https://en.wikipedia.org/wiki/%C3%89mile' })), true);
});
test('imports merge without replacing existing stories', () => {
    const saved = collection.create(memory()); saved.save(story());
    assert.equal(saved.import(backup([story(), story({ year: 2001 })])), 1);
    assert.equal(saved.read().length, 2);
});
test('an invalid entry prevents partial import', () => {
    const saved = collection.create(memory()); saved.save(story());
    assert.throws(() => saved.import(backup([story({ year: 2002 }), story({ day: 30 })])));
    assert.equal(saved.read().length, 1);
});
test('unsafe external links and tracking images are not restored', () => {
    const item = collection.normalise(story({ wikipedia_url: 'javascript:alert(1)', image: { src: 'https://tracker.invalid/pixel', width: 500, height: 500 } }));
    assert.equal(item.wikipedia_url, null); assert.equal(item.image, null);
});
test('imports discard unknown fields without prototype pollution', () => {
    const payload = JSON.parse(backup([story()]).replace('"stories":', '"__proto__":{"polluted":true},"stories":'));
    const saved = collection.create(memory()); saved.import(JSON.stringify(payload));
    assert.equal({}.polluted, undefined); assert.equal(saved.read()[0].polluted, undefined);
});
test('story markup remains plain text for the DOM renderer', () => {
    const item = collection.normalise(story({ selected_title: '<img src=x onerror=alert(1)>' }));
    assert.equal(item.selected_title, '<img src=x onerror=alert(1)>');
});
test('unknown formats, future versions and oversized files are rejected', () => {
    assert.throws(() => collection.decode('{"app":"WDHOTD","version":2,"stories":[]}'));
    assert.throws(() => collection.decode('not json'));
    assert.throws(() => collection.decode(' '.repeat(collection.MAX_FILE_BYTES + 1)));
});
test('a failed browser write preserves the previous collection', () => {
    const storage = memory(); const saved = collection.create(storage); saved.save(story());
    storage.setItem = () => { throw new Error('Quota exceeded'); };
    assert.throws(() => saved.save(story({ year: 2001 })), /previous collection/);
    assert.equal(saved.read().length, 1);
});
test('the collection limit is enforced without deleting existing entries', () => {
    const saved = collection.create(memory());
    saved.import(backup(Array.from({ length: 300 }, (_, i) => story({ year: 1000 + i }))));
    assert.throws(() => saved.save(story({ year: 3000 })), /300 stories/);
    assert.equal(saved.read().length, 300);
});
test('one story can be removed without losing others', () => {
    const saved = collection.create(memory()); saved.save(story()); saved.save(story({ year: 2001 }));
    saved.remove(story()); assert.equal(saved.read()[0].year, 2001);
});
test('a backup with a Unicode byte-order mark can be recovered', () => {
    assert.equal(collection.decode('\uFEFF' + backup([story()])).length, 1);
});
