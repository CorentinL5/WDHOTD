'use strict';

(() => {
    const $ = selector => document.querySelector(selector);
    const labels = { events: 'Event', births: 'Born on this day', deaths: 'In memoriam', holidays: 'Tradition' };
    const state = { items: [], view: 'edition', type: 'events', query: '', count: 7, position: 0, controller: null, month: Number(document.body.dataset.month), day: Number(document.body.dataset.day) };
    const today = { month: state.month, day: state.day };
    const stories = $('#stories');
    const status = $('#status');
    const dialog = $('#detailDialog');
    let detailTrigger = null;
    let timelineObserver = null;
    let newestFirst = false;
    const backToTop = $('#backToTop');
    const updateBackToTop = () => { backToTop.hidden = window.scrollY < 500; };
    window.addEventListener('scroll', updateBackToTop, { passive: true });
    updateBackToTop();
    backToTop.addEventListener('click', event => {
        event.preventDefault();
        $('#dateToggle').focus({ preventScroll: true });
        window.scrollTo({ top: 0, behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth' });
    });
    let collection;
    try { collection = WDHOTDCollections.create(localStorage); }
    catch { collection = WDHOTDCollections.create({ getItem() { throw new Error(); }, setItem() { throw new Error(); } }); }
    let noticeTimer;

    function notify(text) {
        const notice = $('#collectionNotice');
        notice.textContent = text;
        notice.hidden = false;
        clearTimeout(noticeTimer);
        noticeTimer = setTimeout(() => { notice.hidden = true; }, 6500);
    }

    function savedStories() {
        try { return collection.read(); }
        catch (error) { notify(error.message); return []; }
    }

    function updateSaved() {
        const saved = savedStories();
        const keys = new Set(saved.map(WDHOTDCollections.key));
        $('#savedCount').textContent = saved.length;
        document.querySelectorAll('.save-button').forEach(button => {
            const active = keys.has(WDHOTDCollections.key(button.story));
            button.textContent = active ? '★ Saved' : '☆ Save';
            button.setAttribute('aria-pressed', String(active));
            button.setAttribute('aria-label', `${active ? 'Remove saved story' : 'Save story'}: ${button.story.selected_title}`);
        });
        $('#exportSaved').disabled = $('#clearSaved').disabled = saved.length === 0;
    }

    function saveButton(item) {
        const button = el('button', 'save-button', '☆ Save');
        button.type = 'button';
        button.story = item;
        button.addEventListener('click', () => {
            try {
                if (collection.has(item)) { collection.remove(item); notify('Removed from your saved stories.'); }
                else { collection.save(item); notify('Story saved in this browser. Export a backup to keep a copy.'); }
                if (state.view === 'saved') render();
                updateSaved();
            } catch (error) { notify(error.message); }
        });
        return button;
    }

    function storyActions(item) {
        const actions = el('div', 'story-actions');
        actions.append(readButton(item), saveButton(item));
        return actions;
    }

    function el(tag, className, text) {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    }

    function safeURL(value) {
        try {
            const url = new URL(value);
            return url.protocol === 'https:' ? url.href : null;
        } catch { return null; }
    }

    function yearLabel(year) {
        return year === null || year === undefined ? 'Every year' : year < 0 ? `${Math.abs(year)} BC` : String(year);
    }

    function shorten(text, length = 200) {
        if (text.length <= length) return text;
        const cut = text.slice(0, length);
        const space = cut.lastIndexOf(' ');
        return cut.slice(0, space > length * 0.6 ? space : length) + '…';
    }

    function dateLabel() {
        return new Intl.DateTimeFormat('en-GB', { day: 'numeric', month: 'long', timeZone: 'UTC' }).format(new Date(Date.UTC(2000, state.month - 1, state.day)));
    }

    function metadata(item) {
        const meta = el('div', 'story-meta');
        meta.append(el('span', 'year', yearLabel(item.year)), el('span', '', '·'), el('span', '', labels[item.type] || 'Story'));
        if (state.view === 'saved') meta.append(el('span', '', '·'), el('span', '', new Intl.DateTimeFormat('en-GB', { day: 'numeric', month: 'short', timeZone: 'UTC' }).format(new Date(Date.UTC(2000, item.month - 1, item.day)))));
        return meta;
    }

    function visual(item, hero) {
        const box = el('div', hero ? 'hero-visual' : 'card-visual');
        const placeholder = el('div', 'image-placeholder');
        placeholder.append(el('span', '', item.year === null ? '∞' : yearLabel(item.year)), el('small', '', 'FROM THE ARCHIVES'));
        box.append(placeholder);
        const media = item.image;
        const url = safeURL(media?.src || item.thumbnail);
        if (media) box.classList.add(`image-${media.kind}`);
        const adequate = !media || media.width >= (hero ? 560 : 240);
        if (url && adequate) {
            const image = el('img');
            image.alt = media?.title || item.selected_title || '';
            image.loading = hero ? 'eager' : 'lazy';
            image.decoding = 'async';
            if (media) { image.width = media.width; image.height = media.height; }
            image.addEventListener('load', () => {
                if (image.naturalWidth < (hero ? 500 : 200)) image.remove();
                else box.classList.add('image-ready');
            }, { once: true });
            image.addEventListener('error', () => image.remove(), { once: true });
            image.src = url;
            box.append(image);
        }
        return box;
    }

    function readButton(item, icon = false) {
        const button = el('button', icon ? 'icon-button' : 'read-link', icon ? '↗' : 'Read the story');
        button.type = 'button';
        button.setAttribute('aria-label', `Read the story: ${item.selected_title}`);
        if (!icon) button.append(el('span', '', '↗'));
        button.addEventListener('click', () => openDetail(item, button));
        return button;
    }

    function card(item, hero = false) {
        const article = el('article', hero ? 'hero-story' : 'story-card');
        const copy = hero ? el('div', 'hero-copy') : article;
        article.append(visual(item, hero));
        if (hero) copy.append(el('span', 'story-number', '01 / IN THE SPOTLIGHT'));
        copy.append(metadata(item), el(hero ? 'h2' : 'h3', '', item.selected_title || 'A story worth discovering'), el('p', 'event-text', shorten(item.event_text || item.extract_short || '', hero ? 360 : 180)), storyActions(item));
        if (hero) article.append(copy);
        return article;
    }

    function selection(items) {
        // Promote Wikipedia's selected events, then spread the selection across centuries.
        const ranked = [...items].sort((a, b) => Number(b.featured) - Number(a.featured) || Number(Boolean(b.event_text)) - Number(Boolean(a.event_text)) || Number(Boolean(b.thumbnail)) - Number(Boolean(a.thumbnail)) || b.year - a.year || a.id - b.id);
        const first = [], rest = [], centuries = new Set();
        for (const item of ranked) {
            const century = item.year === null ? 'annual' : Math.floor(item.year / 100);
            if (!centuries.has(century) || item.featured) { first.push(item); centuries.add(century); }
            else rest.push(item);
        }
        return [...first, ...rest];
    }

    function filtered() {
        const query = state.query.trim().toLocaleLowerCase('en');
        const source = state.view === 'saved' ? savedStories() : state.items;
        return source.filter(item => (state.type === 'all' || item.type === state.type) && (!query || `${item.selected_title} ${item.event_text} ${item.extract_short} ${item.year ?? ''}`.toLocaleLowerCase('en').includes(query)));
    }

    function message(text, retry = false) {
        status.replaceChildren(el('span', '', text));
        status.hidden = false;
        status.classList.toggle('error', retry);
        if (retry) {
            const button = el('button', 'outline-button', 'Try again');
            button.addEventListener('click', load);
            status.append(button);
        }
    }

    function render() {
        if (timelineObserver) { timelineObserver.disconnect(); timelineObserver = null; }
        const items = state.view === 'saved' ? filtered().sort((a, b) => b.saved_at.localeCompare(a.saved_at)) : selection(filtered());
        $('#collectionTools').hidden = state.view !== 'saved';
        $('#editionDate').textContent = state.view === 'saved' ? 'Your collection' : dateLabel();
        stories.replaceChildren();
        $('#moreRow').hidden = true;
        $('#storyCount').textContent = `${items.length} stor${items.length === 1 ? 'y' : 'ies'} to explore`;
        if (!items.length) {
            message(state.query ? 'No stories match your search.' : state.view === 'saved' ? 'Nothing saved in this selection yet. Save stories as you explore, or import a WDHOTD backup.' : 'No stories in this category for this date yet. Select All or explore another date.');
            updateSaved();
            return;
        }
        status.hidden = true;
        if (state.view === 'saved') {
            const grid = el('div', 'story-grid saved-grid');
            items.slice(0, state.count).forEach(item => grid.append(card(item)));
            stories.append(el('h2', 'saved-heading', 'Stories to come back to.'), grid);
            $('#moreRow').hidden = state.count >= items.length;
        }
        else if (state.view === 'timeline') renderTimeline(items);
        else if (state.view === 'discovery') renderDiscovery(items);
        else {
            const label = el('div', 'section-label');
            label.append(el('strong', '', '01'), el('span', '', 'A story to start with'));
            stories.append(label, card(items[0], true));
            if (items.length > 1) {
                const heading = el('div', 'section-label');
                heading.append(el('strong', '', '02'), el('span', '', 'The same day. A different century.'));
                const grid = el('div', 'story-grid');
                items.slice(1, state.count).forEach(item => grid.append(card(item)));
                stories.append(heading, grid);
            }
            $('#moreRow').hidden = state.count >= items.length;
        }
        updateSaved();
    }

    function renderDiscovery(items) {
        state.position = Math.min(state.position, items.length - 1);
        const controls = el('div', 'discovery-controls');
        controls.append(el('span', '', `Story ${state.position + 1} of ${items.length}`));
        const buttons = el('div', 'discovery-buttons');
        const previous = el('button', 'outline-button', '← Previous');
        const next = el('button', 'outline-button', 'Next story →');
        previous.disabled = state.position === 0;
        next.disabled = state.position === items.length - 1;
        previous.addEventListener('click', () => { state.position--; render(); $('.discovery-buttons button').focus(); });
        next.addEventListener('click', () => { state.position++; render(); $('.discovery-buttons button:last-child').focus(); });
        buttons.append(previous, next);
        controls.append(buttons);
        stories.append(controls, card(items[state.position], true), el('p', 'discovery-note', 'One story at a time. Read the full story for a little more context, then see where your curiosity takes you next.'));
    }

    function renderTimeline(items) {
        const dated = items.filter(item => item.year !== null);
        const annual = items.filter(item => item.year === null);
        dated.sort((a, b) => (newestFirst ? b.year - a.year : a.year - b.year) || a.id - b.id);
        const sorted = [...dated, ...annual];
        const years = dated.map(item => item.year);
        const first = years.length ? Math.min(...years) : null;
        const last = years.length ? Math.max(...years) : null;
        const stage = el('div', 'chronicle');
        const intro = el('header', 'chrono-intro');
        const introCopy = el('div');
        introCopy.append(el('div', 'eyebrow', 'ONE DATE. A THOUSAND CONNECTIONS.'), el('h2', '', 'Follow the thread of time.'));
        const range = el('div', 'chrono-range');
        range.append(el('span', '', first === null ? 'Every year' : yearLabel(first)), el('span', 'chrono-range-rule', '→'), el('span', '', last === null ? 'Every story' : yearLabel(last)));
        const caption = el('p', 'chrono-caption', `${items.length} stories${first === null ? '' : ` · ${new Intl.NumberFormat('en').format(last - first)} years of history`} · ${dateLabel()}`);
        intro.append(introCopy, range, caption);
        const overview = el('nav', 'chrono-overview');
        overview.setAttribute('aria-label', 'Jump to a year on the timeline');
        const mapYears = new Map();
        if (years.length) {
            const guide = el('div', 'chrono-overview-labels');
            guide.append(el('span', '', yearLabel(first)), el('span', '', 'EXPLORE THE YEARS'), el('span', '', yearLabel(last)));
            overview.append(guide);
            intro.append(overview);
        }
        const rail = el('nav', 'chrono-nav');
        rail.setAttribute('aria-label', 'Jump to an era');
        const progress = el('span', 'chrono-current', yearLabel(sorted[0].year));
        const jumps = el('div', 'chrono-jumps');
        const direction = el('button', 'chrono-direction', newestFirst ? 'Newest first ↓' : 'Oldest first ↓');
        direction.setAttribute('aria-label', newestFirst ? 'Change to oldest first' : 'Change to newest first');
        direction.addEventListener('click', () => { newestFirst = !newestFirst; render(); $('.chrono-direction').focus({ preventScroll: true }); });
        rail.append(progress, jumps, direction);
        stage.append(intro, rail);
        const track = el('div', 'chrono-track');
        track.append(el('div', 'chrono-origin', 'YOUR JOURNEY STARTS HERE'));
        const eras = new Map();
        let previousYear = null;
        let position = 0;
        for (const item of sorted) {
            const era = item.year === null ? 'Every year' : item.year < 0 ? 'BC' : item.year < 500 ? '1–499' : item.year < 1500 ? '500–1499' : item.year < 1800 ? '1500s–1700s' : item.year < 1900 ? '1800s' : item.year < 2000 ? '1900s' : '2000s';
            if (!eras.has(era)) {
                const marker = el('h3', 'chrono-era', era === 'Every year' ? 'Traditions that return.' : era.includes('–') ? era : `The ${era === 'BC' ? 'ancient world' : era}`);
                marker.tabIndex = -1;
                marker.id = `era-${eras.size}`;
                track.append(marker);
                const jump = el('button', 'chrono-jump', era);
                jump.setAttribute('aria-controls', marker.id);
                jump.addEventListener('click', () => { marker.scrollIntoView({ behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth', block: 'start' }); marker.focus({ preventScroll: true }); });
                jumps.append(jump);
                eras.set(era, jump);
            }
            const gap = item.year !== null && previousYear !== null ? Math.abs(item.year - previousYear) : 0;
            if (gap >= 20) {
                const bridge = el('div', 'chrono-gap');
                bridge.append(el('span', '', `${gap} years ${newestFirst ? 'earlier' : 'later'}`));
                track.append(bridge);
            }
            const row = el('div', `chrono-row ${position % 2 ? 'on-right' : 'on-left'}`);
            row.dataset.year = yearLabel(item.year);
            row.dataset.era = era;
            const date = el('div', 'chrono-date');
            date.append(el('span', 'chrono-pin'), el('span', 'chrono-year', yearLabel(item.year)));
            const article = el('article', 'chrono-card');
            article.append(visual(item, false));
            const copy = el('div', 'chrono-copy');
            copy.append(el('div', 'story-meta', labels[item.type]), el('h4', '', item.selected_title), el('p', 'event-text', item.event_text || item.extract_short), storyActions(item));
            article.append(copy);
            row.append(date, article);
            track.append(row);
            if (item.year !== null && !mapYears.has(item.year)) {
                const point = el('button', 'chrono-point');
                point.style.setProperty('--year-position', `${((item.year - first) / Math.max(1, last - first)) * 100}%`);
                point.setAttribute('aria-label', `Jump to ${yearLabel(item.year)}`);
                point.title = yearLabel(item.year);
                point.addEventListener('click', () => {
                    row.scrollIntoView({ behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth', block: 'start' });
                    row.querySelector('.read-link').focus({ preventScroll: true });
                });
                overview.append(point);
                mapYears.set(item.year, point);
            }
            previousYear = item.year;
            position++;
        }
        const end = el('div', 'chrono-end');
        end.append(el('span', 'chrono-end-dot'), el('p', '', 'History keeps going. So does your curiosity.'), el('small', '', 'Events are shown in chronological order. Distances are not to scale.'));
        track.append(end);
        stage.append(track);
        stories.append(stage);
        timelineObserver = new IntersectionObserver(entries => {
            entries.forEach(entry => entry.target.classList.toggle('is-current', entry.isIntersecting));
            const visible = entries.filter(entry => entry.isIntersecting).sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top);
            if (!visible.length) return;
            progress.textContent = visible[0].target.dataset.year;
            eras.forEach((button, era) => button.setAttribute('aria-current', String(era === visible[0].target.dataset.era)));
            mapYears.forEach((button, year) => button.classList.toggle('is-current', yearLabel(year) === visible[0].target.dataset.year));
        }, { rootMargin: '-15% 0px -55% 0px', threshold: 0 });
        intro.dataset.year = yearLabel(sorted[0].year);
        intro.dataset.era = eras.keys().next().value;
        timelineObserver.observe(intro);
        track.querySelectorAll('.chrono-row').forEach(row => timelineObserver.observe(row));
    }

    function openDetail(item, trigger) {
        detailTrigger = trigger;
        const body = el('article', 'detail-body');
        const heading = el('h2', '', item.selected_title);
        heading.id = 'detailTitle';
        body.append(metadata(item), heading, el('p', 'event-text', item.event_text || item.extract_short));
        body.append(saveButton(item));
        const imageURL = safeURL(item.image?.src || item.thumbnail);
        if (imageURL) {
            const image = el('img');
            image.src = imageURL;
            image.alt = item.image?.title || item.selected_title;
            image.addEventListener('error', () => image.remove(), { once: true });
            body.append(image);
            if (item.image?.title) body.append(el('p', 'image-credit', `Related image: ${item.image.title}. Source: Wikipedia / Wikimedia.`));
        }
        if (item.extract_short && item.extract_short !== item.event_text) body.append(el('h3', 'context-label', 'The bigger picture'), el('p', 'context-text', item.extract_short));
        const wikiURL = safeURL(item.wikipedia_url);
        if (wikiURL) {
            const link = el('a', 'wiki-link', 'Keep exploring on Wikipedia ↗');
            link.href = wikiURL;
            link.target = '_blank';
            link.rel = 'noopener noreferrer';
            body.append(link);
        }
        $('#detailContent').replaceChildren(body);
        updateSaved();
        dialog.showModal();
        dialog.scrollTop = 0;
        $('#closeDetail').focus();
    }

    async function load() {
        if (state.controller) state.controller.abort();
        const controller = new AbortController();
        state.controller = controller;
        state.items = [];
        state.position = 0;
        state.count = 7;
        stories.replaceChildren();
        stories.setAttribute('aria-busy', 'true');
        $('#moreRow').hidden = true;
        $('#storyCount').textContent = 'Opening the archives…';
        message('Gathering the stories behind this date…');
        $('#headerDate').textContent = $('#editionDate').textContent = dateLabel();
        const params = new URLSearchParams({ month: state.month, day: state.day, view: state.view });
        history.replaceState(null, '', `${location.pathname}?${params}`);
        if (state.view === 'saved') { render(); stories.setAttribute('aria-busy', 'false'); return; }
        const all = [];
        try {
            let hasMore = true;
            while (hasMore) {
                const query = new URLSearchParams({ month: state.month, day: state.day, type: 'all', limit: 100, offset: all.length });
                const response = await fetch(`feed.php?${query}`, { signal: controller.signal });
                let data;
                try { data = await response.json(); } catch { throw new Error('The archives are temporarily unavailable. Please try again in a moment.'); }
                if (!response.ok || !data.success) throw new Error(data.error || 'Could not load the stories.');
                all.push(...data.data.map(item => ({ ...item, month: state.month, day: state.day })));
                hasMore = data.meta.has_more && data.data.length > 0;
            }
            if (controller.signal.aborted) return;
            state.items = all;
            render();
            if (!all.length) message('This date is not in the published archives yet. Try Surprise me to explore an available day.', true);
        } catch (error) {
            if (error.name !== 'AbortError') {
                message(error.message, true);
                $('#storyCount').textContent = 'Archives unavailable';
            }
        } finally {
            if (state.controller === controller) stories.setAttribute('aria-busy', 'false');
        }
    }

    document.querySelectorAll('[data-view]').forEach(button => button.addEventListener('click', () => {
        state.view = button.dataset.view;
        if (state.view === 'saved') {
            state.type = 'all'; state.query = ''; state.count = 7;
            $('#search').value = '';
            document.querySelectorAll('[data-type]').forEach(tab => { tab.classList.toggle('active', tab.dataset.type === 'all'); tab.setAttribute('aria-pressed', String(tab.dataset.type === 'all')); });
        }
        history.replaceState(null, '', `${location.pathname}?${new URLSearchParams({ month: state.month, day: state.day, view: state.view })}`);
        document.querySelectorAll('[data-view]').forEach(tab => { tab.classList.toggle('active', tab === button); tab.setAttribute('aria-pressed', String(tab === button)); });
        if (state.view === 'saved' || state.items.length) render();
        else load();
    }));
    document.querySelectorAll('[data-type]').forEach(button => button.addEventListener('click', () => {
        state.type = button.dataset.type;
        state.position = 0;
        state.count = 7;
        document.querySelectorAll('[data-type]').forEach(tab => { tab.classList.toggle('active', tab === button); tab.setAttribute('aria-pressed', String(tab === button)); });
        if (state.view === 'saved' || state.items.length) render();
    }));
    $('#search').addEventListener('input', event => { state.query = event.target.value; state.position = 0; state.count = 7; if (state.view === 'saved' || state.items.length) render(); });
    $('#moreButton').addEventListener('click', () => { const previous = state.count; state.count += 6; render(); const added = document.querySelectorAll('.story-grid .read-link')[previous - 1]; if (added) added.focus(); });
    $('#closeDetail').addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => { if (detailTrigger?.isConnected) detailTrigger.focus(); else document.querySelector(`[data-view="${state.view}"]`)?.focus(); });
    dialog.addEventListener('click', event => { if (event.target === dialog) { const rect = dialog.getBoundingClientRect(); if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) dialog.close(); } });
    $('#dateToggle').addEventListener('click', () => { const picker = $('#datePicker'); picker.hidden = !picker.hidden; $('#dateToggle').setAttribute('aria-expanded', String(!picker.hidden)); if (!picker.hidden) $('#dateDay').focus(); });
    function setDate(month, day) {
        const date = new Date(Date.UTC(2000, month - 1, day));
        if (date.getUTCMonth() + 1 !== month || date.getUTCDate() !== day) { $('#dateDay').setCustomValidity('This date does not exist.'); $('#dateDay').reportValidity(); return; }
        state.month = month;
        state.day = day;
        if (state.view === 'saved') {
            state.view = 'edition';
            document.querySelectorAll('[data-view]').forEach(tab => { tab.classList.toggle('active', tab.dataset.view === 'edition'); tab.setAttribute('aria-pressed', String(tab.dataset.view === 'edition')); });
        }
        $('#dateDay').value = day;
        $('#dateMonth').value = month;
        $('#datePicker').hidden = true;
        $('#dateToggle').setAttribute('aria-expanded', 'false');
        $('#dateToggle').focus();
        load();
    }
    $('#dateDay').addEventListener('input', () => $('#dateDay').setCustomValidity(''));
    $('#dateMonth').addEventListener('change', () => $('#dateDay').setCustomValidity(''));
    $('#datePicker').addEventListener('submit', event => { event.preventDefault(); setDate(Number($('#dateMonth').value), Number($('#dateDay').value)); });
    $('#todayButton').addEventListener('click', () => setDate(today.month, today.day));
    const url = new URLSearchParams(location.search);
    if (['edition', 'discovery', 'timeline', 'saved'].includes(url.get('view'))) {
        state.view = url.get('view');
        document.querySelectorAll('[data-view]').forEach(button => {
            button.classList.toggle('active', button.dataset.view === state.view);
            button.setAttribute('aria-pressed', String(button.dataset.view === state.view));
        });
    }
    if (state.view === 'saved') {
        state.type = 'all';
        document.querySelectorAll('[data-type]').forEach(tab => { tab.classList.toggle('active', tab.dataset.type === 'all'); tab.setAttribute('aria-pressed', String(tab.dataset.type === 'all')); });
    }
    const month = Number(url.get('month')), day = Number(url.get('day'));
    const date = new Date(Date.UTC(2000, month - 1, day));
    if (month >= 1 && month <= 12 && day >= 1 && day <= 31 && date.getUTCMonth() + 1 === month && date.getUTCDate() === day) {
        state.month = month; state.day = day; $('#dateMonth').value = month; $('#dateDay').value = day;
    }
    async function loadSponsor() {
        try {
            const response = await fetch('sponsor.php');
            if (!response.ok) return;
            const { ad } = await response.json();
            if (!ad) return;
            const slot = $('#sponsorSlot');
            const target = safeURL(ad.destination);
            if (!target) return;
            const text = el('p');
            text.append(el('span', '', ad.title), el('br'), el('strong', '', ad.body));
            const link = el('a', '', ad.link_label + ' ↗');
            link.href = target;
            link.target = '_blank'; link.rel = 'sponsored noopener noreferrer';
            slot.replaceChildren(el('span', 'support-label', ad.kind === 'house' ? 'SUPPORT THIS PROJECT' : 'ADVERTISEMENT'), text, link);
            slot.hidden = false;
        } catch { /* A missing advertisement must never interrupt reading. */ }
    }
    $('#surpriseButton').addEventListener('click', async () => {
        const button = $('#surpriseButton');
        button.disabled = true;
        try {
            const response = await fetch('calendar.php');
            if (!response.ok) throw new Error('Could not open the calendar. Please try again.');
            const data = await response.json();
            const dates = (data.dates || []).filter(date => date.month !== state.month || date.day !== state.day);
            if (!dates.length) { message('More dates are on their way. Explore the stories of this day first.'); return; }
            const date = dates[Math.floor(Math.random() * dates.length)];
            $('#dateDay').setCustomValidity('');
            setDate(date.month, date.day);
        } catch(error) { message(error.message); }
        finally { button.disabled = false; }
    });
    $('#exportSaved').addEventListener('click', () => {
        try {
            const link = el('a');
            const file = URL.createObjectURL(new Blob([collection.export()], { type: 'application/json' }));
            link.href = file;
            link.download = `wdhotd-saved-${new Date().toISOString().slice(0, 10)}.json`;
            document.body.append(link); link.click(); link.remove();
            setTimeout(() => URL.revokeObjectURL(file), 10000);
            notify('Backup downloaded. Keep it to recover your collection on another browser or device.');
        } catch (error) { notify(error.message); }
    });
    $('#importSaved').addEventListener('change', async event => {
        const file = event.target.files[0];
        try {
            if (!file) return;
            if (file.size > WDHOTDCollections.MAX_FILE_BYTES) throw new Error('Choose a WDHOTD backup smaller than 2 MB.');
            const added = collection.import(await file.text());
            notify(`${added} new ${added === 1 ? 'story' : 'stories'} recovered. Your existing stories were kept.`);
            render();
        } catch (error) { notify(error.message); }
        finally { event.target.value = ''; }
    });
    $('#clearSaved').addEventListener('click', () => { $('#clearDialog').showModal(); $('#cancelClear').focus(); });
    $('#cancelClear').addEventListener('click', () => $('#clearDialog').close());
    $('#confirmClear').addEventListener('click', () => {
        try { collection.clear(); $('#clearDialog').close(); render(); notify('Collection cleared from this browser. You can recover it from an exported backup.'); }
        catch (error) { notify(error.message); }
    });
    window.addEventListener('storage', event => { if (event.key === null || event.key === WDHOTDCollections.STORAGE_KEY) { if (state.view === 'saved') render(); else updateSaved(); } });
    updateSaved();
    loadSponsor();
    load();
})();
