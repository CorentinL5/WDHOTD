'use strict';

// Pure validation/storage logic is shared by the browser and the Node tests.
(function (root, factory) {
    const api = factory();
    if (typeof module === 'object' && module.exports) module.exports = api;
    else root.WDHOTDCollections = api;
})(globalThis, function () {
    const STORAGE_KEY = 'wdhotd.saved.v1';
    const MAX_STORIES = 300;
    const MAX_FILE_BYTES = 2 * 1024 * 1024;
    const types = new Set(['events', 'births', 'deaths', 'holidays']);

    function text(value, limit, required = false) {
        if (typeof value !== 'string' || value.length > limit || (required && !value.trim())) throw new Error('This backup contains an invalid story.');
        return value.trim();
    }

    function safeURL(value, hosts) {
        if (!value) return null;
        try {
            const url = new URL(value);
            if (url.protocol !== 'https:' || url.username || url.password || !hosts.includes(url.hostname)) return null;
            return url.href;
        } catch { return null; }
    }

    function normalise(input) {
        if (!input || typeof input !== 'object' || !types.has(input.type)) throw new Error('This backup contains an invalid story.');
        const { month, day, year } = input;
        const date = new Date(Date.UTC(2000, month - 1, day));
        if (!Number.isInteger(month) || !Number.isInteger(day) || date.getUTCMonth() + 1 !== month || date.getUTCDate() !== day || (year !== null && (!Number.isInteger(year) || Math.abs(year) > 100000))) throw new Error('This backup contains an invalid date.');
        const story = {
            type: input.type, month, day, year,
            selected_title: text(input.selected_title, 500, true),
            event_text: text(input.event_text || '', 6000),
            extract_short: text(input.extract_short || '', 14000),
            wikipedia_url: safeURL(input.wikipedia_url, ['en.wikipedia.org']),
            image: null, thumbnail: null, featured: false,
            saved_at: input.saved_at && !Number.isNaN(Date.parse(input.saved_at)) ? new Date(input.saved_at).toISOString() : new Date().toISOString()
        };
        const image = input.image;
        const imageURL = safeURL(image?.src, ['upload.wikimedia.org', 'thumb.wikimedia.org']);
        if (imageURL && Number.isInteger(image.width) && Number.isInteger(image.height) && image.width >= 1 && image.height >= 1 && image.width <= 10000 && image.height <= 10000) {
            story.image = { src: imageURL, width: image.width, height: image.height, ratio: image.width / image.height, kind: image.width / image.height < 0.9 ? 'portrait' : image.width / image.height > 2 ? 'panorama' : 'landscape', title: text(image.title || story.selected_title, 500), page_url: safeURL(image.page_url, ['en.wikipedia.org']) };
            story.thumbnail = imageURL;
        }
        return story;
    }

    function key(story) {
        return JSON.stringify([story.type, story.month, story.day, story.year, safeURL(story.wikipedia_url, ['en.wikipedia.org']) || '', (story.event_text || story.selected_title || '').trim()]);
    }

    function decode(content) {
        if (typeof content !== 'string' || content.length > MAX_FILE_BYTES || new TextEncoder().encode(content).length > MAX_FILE_BYTES) throw new Error('Choose a WDHOTD backup smaller than 2 MB.');
        let data;
        try { data = JSON.parse(content.replace(/^\uFEFF/, '')); } catch { throw new Error('This file is not a valid JSON backup.'); }
        if (!data || data.app !== 'WDHOTD' || data.version !== 1 || !Array.isArray(data.stories) || data.stories.length > MAX_STORIES) throw new Error('This is not a supported WDHOTD backup.');
        return data.stories.map(normalise);
    }

    function encode(stories) {
        const content = JSON.stringify({ app: 'WDHOTD', version: 1, exported_at: new Date().toISOString(), stories }, null, 2);
        if (new TextEncoder().encode(content).length > MAX_FILE_BYTES) throw new Error('Your collection is too large for one backup. Remove some stories first.');
        return content;
    }

    function create(storage) {
        let previousRaw = null, previousStories = [];
        function read() {
            let raw;
            try { raw = storage.getItem(STORAGE_KEY); } catch { throw new Error('Your browser could not open the saved stories. Check its storage settings.'); }
            if (raw !== previousRaw) { previousStories = raw ? decode(raw) : []; previousRaw = raw; }
            return previousStories.map(story => ({ ...story, image: story.image ? { ...story.image } : null }));
        }
        function write(stories) {
            if (stories.length > MAX_STORIES) throw new Error('You can save up to 300 stories. Export a backup before making room.');
            const content = encode(stories);
            try { storage.setItem(STORAGE_KEY, content); } catch { throw new Error('Your browser could not save this change. Your previous collection is still stored.'); }
            previousRaw = content;
            previousStories = stories;
            return stories.length;
        }
        return {
            read,
            has: story => read().some(saved => key(saved) === key(story)),
            save(story) { const saved = normalise(story); const items = read(); if (!items.some(item => key(item) === key(saved))) items.unshift(saved); return write(items); },
            remove(story) { return write(read().filter(item => key(item) !== key(story))); },
            clear: () => write([]),
            export: () => encode(read()),
            import(content) {
                const incoming = decode(content);
                const current = read();
                const merged = new Map(current.map(story => [key(story), story]));
                incoming.forEach(story => { if (!merged.has(key(story))) merged.set(key(story), story); });
                write([...merged.values()]);
                return merged.size - current.length;
            }
        };
    }
    return { create, normalise, key, decode, STORAGE_KEY, MAX_FILE_BYTES };
});
