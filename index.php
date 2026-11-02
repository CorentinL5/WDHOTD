<?php
// The PHP development server can route missing files back to index.php.
// This application uses query parameters, not catch-all page routes.
if (PHP_SAPI !== 'cli') {
    $requestedPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php')), '/');
    if (!in_array($requestedPath, [$basePath . '/', $basePath . '/index.php'], true)) {
        http_response_code(404);
        exit;
    }
}
require_once __DIR__ . '/public_data.php';
$settings = publicData('settings.json') ?? [];
$supportURL = publicSupportURL($settings['support_url'] ?? null);
$sourceURL = publicSupportURL($settings['source_url'] ?? null);
$siteURL = publicSiteURL($settings['site_url'] ?? null);
$savedView = in_array($_GET['view'] ?? '', ['saved', 'stats'], true);
date_default_timezone_set('Europe/Brussels');
$today = new DateTimeImmutable();
$months = [1 => 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
$dateLabel = $today->format('j') . ' ' . $months[(int)$today->format('n')];
$pageMonth = (int)$today->format('n');
$pageDay = (int)$today->format('j');
if (isset($_GET['month'], $_GET['day'])) {
    $requestedMonth = filter_var($_GET['month'], FILTER_VALIDATE_INT);
    $requestedDay = filter_var($_GET['day'], FILTER_VALIDATE_INT);
    if ($requestedMonth && $requestedDay && checkdate($requestedMonth, $requestedDay, 2000)) { $pageMonth = $requestedMonth; $pageDay = $requestedDay; }
}
$pageDate = $pageDay . ' ' . $months[$pageMonth];
$canonical = $siteURL ? $siteURL . (isset($_GET['month'], $_GET['day']) ? '/?month=' . $pageMonth . '&day=' . $pageDay : '/') : null;
if (!$siteURL || $savedView) header('X-Robots-Tag: noindex, nofollow');
$initialArchive = publicData(sprintf('%02d-%02d.json', $pageMonth, $pageDay));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#eeece5">
    <meta name="description" content="Explore <?= htmlspecialchars($pageDate, ENT_QUOTES, 'UTF-8') ?> through history: events, people and stories, with an interactive timeline and your own saved collection.">
    <meta name="robots" content="<?= $siteURL && !$savedView ? 'index,follow' : 'noindex,nofollow' ?>">
    <?php if ($canonical): ?><link rel="canonical" href="<?= htmlspecialchars($canonical, ENT_QUOTES, 'UTF-8') ?>"><meta property="og:url" content="<?= htmlspecialchars($canonical, ENT_QUOTES, 'UTF-8') ?>"><?php endif; ?>
    <meta property="og:title" content="WDHOTD — What Did Happened On This Day">
    <meta property="og:description" content="One day. Centuries of stories. Follow your curiosity through history.">
    <meta property="og:type" content="website">
    <link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
    <title>WDHOTD — What Did Happened On This Day | <?= htmlspecialchars($pageDate, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="<?= assetURL('assets/css/style.css') ?>">
    <link rel="stylesheet" href="<?= assetURL('assets/css/timeline.css') ?>">
    <link rel="stylesheet" href="<?= assetURL('assets/css/collection.css') ?>">
    <link rel="stylesheet" href="<?= assetURL('assets/css/paper.css') ?>">
    <script src="<?= assetURL('assets/js/collection.js') ?>" defer></script>
    <script src="<?= assetURL('assets/js/feed.js') ?>" defer></script>
    <script src="<?= assetURL('assets/js/metrics.js') ?>" defer></script>
</head>
<body id="top" data-month="<?= $today->format('n') ?>" data-day="<?= $today->format('j') ?>">
    <a class="skip-link" href="#stories">Skip to stories</a>
    <header class="masthead wrap">
        <div class="brand-lockup"><a class="brand" href="./" aria-label="WDHOTD, home">WDH<span>OTD</span></a><span class="brand-tagline">What Did Happened On This Day</span></div>
        <span class="masthead-note">The daily history journal<br>Independent. Free to explore.</span>
        <a class="project-nav" href="#about">Free & independent ↗</a>
        <button class="date-toggle" id="dateToggle" aria-expanded="false" aria-controls="datePicker" aria-label="Choose a date"><span id="headerDate"><?= $pageDate ?></span><span aria-hidden="true"> ↓</span></button>
    </header>
    <main class="wrap">
        <section class="intro" aria-labelledby="pageTitle">
            <div class="eyebrow"><span class="short-rule"></span> A DATE. A DIFFERENT PERSPECTIVE.</div>
            <div class="intro-row"><div><h1 id="pageTitle">History, <em>on this day.</em></h1><p class="intro-description">Discover the events, lives and unexpected connections behind a single date.</p></div><div class="intro-aside"><span class="edition-label">THE EDITION</span><span class="edition" id="editionDate"><?= $pageDate ?></span><p>A new way into the past.<br>Every day, free for everyone.</p></div></div>
        </section>
        <form class="date-picker" id="datePicker" hidden>
            <label>Day <input id="dateDay" type="number" min="1" max="31" required value="<?= $pageDay ?>"></label>
            <label>Month <select id="dateMonth"><?php foreach ($months as $number => $name): ?><option value="<?= $number ?>" <?= $number === $pageMonth ? 'selected' : '' ?>><?= $name ?></option><?php endforeach; ?></select></label>
            <button class="solid-button" type="submit">Explore this date →</button>
            <button class="text-button" id="todayButton" type="button">Back to today</button>
        </form>
        <div class="explore-bar">
            <nav class="view-tabs" aria-label="Explore history"><button class="view-tab active" data-view="edition" aria-pressed="true">Daily edition</button><button class="view-tab" data-view="discovery" aria-pressed="false">Discover</button><button class="view-tab" data-view="timeline" aria-pressed="false">Timeline</button><button class="view-tab" data-view="saved" aria-pressed="false">Saved <span id="savedCount">0</span></button><button class="view-tab" data-view="stats" aria-pressed="false">Stats</button></nav>
            <div class="explore-actions"><span class="story-count" id="storyCount">Opening the archives…</span><button class="surprise-button" id="surpriseButton" type="button">Surprise me ↝</button></div>
        </div>
        <div class="category-bar"><div class="filters" role="group" aria-label="Story category"><button class="filter active" data-type="events" aria-pressed="true">Events</button><button class="filter" data-type="births" aria-pressed="false">Births</button><button class="filter" data-type="deaths" aria-pressed="false">Deaths</button><button class="filter" data-type="holidays" aria-pressed="false">Traditions</button><button class="filter" data-type="all" aria-pressed="false">All</button></div><label class="search"><span aria-hidden="true">⌕</span><input type="search" id="search" aria-label="Search stories" placeholder="A name, a year…"></label></div>
        <section class="collection-tools" id="collectionTools" hidden aria-label="Your collection"><div><strong>Your own little archive.</strong><p>Saved in this browser. Export a backup to recover it or move to another device. Importing merges your stories.</p></div><div class="collection-buttons"><button id="exportSaved" class="outline-button">Export backup ↓</button><label class="outline-button import-label" for="importSaved">Import backup ↑</label><input type="file" id="importSaved" accept=".json,application/json"><button id="clearSaved" class="text-button">Clear collection</button></div></section>
        <p id="status" class="status" role="status" aria-live="polite">Gathering the stories behind this date…</p>
        <section id="stories" tabindex="-1" aria-label="Stories of the day" aria-busy="true"></section>
        <div class="more-row" id="moreRow" hidden><button id="moreButton" class="outline-button">More from this day ↓</button></div>
        <noscript><section class="noscript-stories"><h2><?= htmlspecialchars($pageDate, ENT_QUOTES, 'UTF-8') ?> in history</h2><p>Enable JavaScript for the interactive timeline, saved stories and date exploration.</p><?php foreach (array_slice($initialArchive['data'] ?? [], 0, 8) as $story): ?><article><h3><?= htmlspecialchars((string)($story['year'] ?? '') . ' — ' . $story['selected_title'], ENT_QUOTES, 'UTF-8') ?></h3><p><?= htmlspecialchars($story['event_text'] ?? $story['extract_short'] ?? '', ENT_QUOTES, 'UTF-8') ?></p></article><?php endforeach; ?></section></noscript>
        <aside class="support-strip" id="sponsorSlot" aria-label="Sponsor" hidden></aside>
        <section class="about-project" id="about" aria-labelledby="aboutTitle"><div><div class="eyebrow">FREE TO EXPLORE. OPEN TO IMPROVE.</div><h2 id="aboutTitle">Good stories belong<br><em>to everyone.</em></h2><p>WDHOTD is a free history project for curious people. Explore a date, follow a thread, and share what you find.</p></div><div class="project-support"><h3>No account. No paywall.<br>Just curiosity.</h3><p>Optional support helps cover hosting and keep the project going. Every story stays free.</p><?php if ($supportURL): ?><a class="solid-button" href="<?= htmlspecialchars($supportURL, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">Support WDHOTD ↗</a><?php endif; ?><?php if ($sourceURL): ?><a class="text-button" href="<?= htmlspecialchars($sourceURL, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">Explore the source code ↗</a><?php else: ?><a class="text-button" href="LICENSE">Contribution terms ↗</a><?php endif; ?></div></section>
        <aside class="source-note"><span class="source-mark" aria-hidden="true">W</span><div><strong>Curiosity starts here.</strong><p>Stories and context are sourced from English Wikipedia, with links to keep exploring. The daily selection is automatic. Text is available under Wikipedia’s applicable <a href="https://en.wikipedia.org/wiki/Wikipedia:Copyrights" target="_blank" rel="noopener noreferrer">content license</a>; images retain their own licenses.</p></div><span class="source-index" aria-hidden="true">THEN / NOW</span></aside>
    </main>
    <footer class="wrap footer"><a class="brand small" href="./" aria-label="WDHOTD, home">WDH<span>OTD</span></a><p>What Did Happened On This Day · Source visible · Contribution-only.</p><a href="https://en.wikipedia.org/" target="_blank" rel="noopener noreferrer">Made possible by Wikipedia ↗</a></footer>
    <dialog id="detailDialog" aria-labelledby="detailTitle"><div class="dialog-toolbar"><span>FROM THE DAILY ARCHIVES</span><button id="closeDetail" class="icon-button" aria-label="Close story">✕</button></div><div id="detailContent"></div></dialog>
    <dialog id="clearDialog" aria-labelledby="clearTitle"><div class="confirm-body"><h2 id="clearTitle">Clear this browser’s collection?</h2><p>Export a backup first if you want to recover these stories later.</p><button id="cancelClear" class="outline-button">Keep my stories</button><button id="confirmClear" class="solid-button">Clear collection</button></div></dialog>
    <div class="collection-notice" id="collectionNotice" role="status" aria-live="polite" hidden></div>
    <a class="back-to-top" id="backToTop" href="#top" hidden><span aria-hidden="true">↑</span> Back to top</a>
</body>
</html>
