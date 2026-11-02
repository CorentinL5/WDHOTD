<?php
require_once __DIR__ . '/public_data.php';
$settings = publicData('settings.json') ?? [];
$supportURL = publicSupportURL($settings['support_url'] ?? null);
$sourceURL = publicSupportURL($settings['source_url'] ?? null);
date_default_timezone_set('Europe/Brussels');
$today = new DateTimeImmutable();
$months = [1 => 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
$dateLabel = $today->format('j') . ' ' . $months[(int)$today->format('n')];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f5f2eb">
    <meta name="description" content="One day. Centuries of stories. Discover what happened on this day, explore the timeline, and follow your curiosity.">
    <title>WDHOTD — What Did Happened On This Day</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/timeline.css">
    <script src="assets/js/feed.js" defer></script>
</head>
<body data-month="<?= $today->format('n') ?>" data-day="<?= $today->format('j') ?>">
    <a class="skip-link" href="#stories">Skip to stories</a>
    <header class="masthead wrap">
        <div class="brand-lockup"><a class="brand" href="./" aria-label="WDHOTD, home">WDH<span>OTD</span></a><span class="brand-tagline">What Did Happened On This Day</span></div>
        <span class="masthead-note">A daily dose of perspective<br>A free history project</span>
        <a class="project-nav" href="#about">Free & independent ↗</a>
        <button class="date-toggle" id="dateToggle" aria-expanded="false" aria-controls="datePicker"><span class="date-dot"></span><span id="headerDate"><?= $dateLabel ?></span><span aria-hidden="true"> ↗</span></button>
    </header>
    <main class="wrap">
        <section class="intro" aria-labelledby="pageTitle">
            <div class="eyebrow"><span class="short-rule"></span> YOUR DAILY WINDOW INTO THE PAST</div>
            <div class="intro-row"><h1 id="pageTitle">One day.<br><em>So many stories.</em></h1><div class="intro-aside"><span class="edition" id="editionDate"><?= $dateLabel ?></span><p>Small moments. World-changing events. <br>Somewhere between then and now, <br>there’s a story worth knowing.</p></div></div>
        </section>
        <form class="date-picker" id="datePicker" hidden>
            <label>Day <input id="dateDay" type="number" min="1" max="31" required value="<?= $today->format('j') ?>"></label>
            <label>Month <select id="dateMonth"><?php foreach ($months as $number => $name): ?><option value="<?= $number ?>" <?= $number === (int)$today->format('n') ? 'selected' : '' ?>><?= $name ?></option><?php endforeach; ?></select></label>
            <button class="solid-button" type="submit">Explore this date →</button>
            <button class="text-button" id="todayButton" type="button">Back to today</button>
        </form>
        <div class="explore-bar">
            <nav class="view-tabs" aria-label="Explore history"><button class="view-tab active" data-view="edition" aria-pressed="true">The daily edition</button><button class="view-tab" data-view="discovery" aria-pressed="false">Discover a story</button><button class="view-tab" data-view="timeline" aria-pressed="false">The timeline</button></nav>
            <div class="explore-actions"><span class="story-count" id="storyCount">Opening the archives…</span><button class="surprise-button" id="surpriseButton" type="button">Surprise me ↝</button></div>
        </div>
        <div class="category-bar"><div class="filters" role="group" aria-label="Story category"><button class="filter active" data-type="events" aria-pressed="true">Events</button><button class="filter" data-type="births" aria-pressed="false">Births</button><button class="filter" data-type="deaths" aria-pressed="false">In memoriam</button><button class="filter" data-type="holidays" aria-pressed="false">Traditions</button><button class="filter" data-type="all" aria-pressed="false">Everything</button></div><label class="search"><span aria-hidden="true">⌕</span><input type="search" id="search" aria-label="Search stories" placeholder="A name, a year…"></label></div>
        <p id="status" class="status" role="status" aria-live="polite">Gathering the stories behind this date…</p>
        <section id="stories" tabindex="-1" aria-label="Stories of the day" aria-busy="true"></section>
        <div class="more-row" id="moreRow" hidden><button id="moreButton" class="outline-button">More from this day ↓</button></div>
        <noscript><p class="notice">Enable JavaScript to browse the stories and explore other dates.</p></noscript>
        <aside class="support-strip" id="sponsorSlot" aria-label="Sponsor" hidden></aside>
        <section class="about-project" id="about" aria-labelledby="aboutTitle"><div><div class="eyebrow">FREE TO EXPLORE. OPEN TO IMPROVE.</div><h2 id="aboutTitle">Good stories belong<br><em>to everyone.</em></h2><p>WDHOTD is a free history project of the internet for curious people. Explore a date, follow a thread, and share what you find.</p></div><div class="project-support"><h3>No account. No paywall.<br>Just curiosity.</h3><p>Optional support helps cover hosting and keep the project going. Every story stays free.</p><?php if ($supportURL): ?><a class="solid-button" href="<?= htmlspecialchars($supportURL, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">Support WDHOTD ↗</a><?php endif; ?><?php if ($sourceURL): ?><a class="text-button" href="<?= htmlspecialchars($sourceURL, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">Explore the source code ↗</a><?php else: ?><a class="text-button" href="LICENSE">Contribution terms ↗</a><?php endif; ?></div></section>
        <aside class="source-note"><span class="source-mark" aria-hidden="true">W</span><div><strong>Curiosity starts here.</strong><p>Stories and context are sourced from English Wikipedia, with links to keep exploring. The daily selection is automatic. Text is available under Wikipedia’s applicable <a href="https://en.wikipedia.org/wiki/Wikipedia:Copyrights" target="_blank" rel="noopener noreferrer">content license</a>; images retain their own licenses.</p></div><span class="source-index" aria-hidden="true">THEN / NOW</span></aside>
    </main>
    <footer class="wrap footer"><a class="brand small" href="./" aria-label="WDHOTD, home">WDH<span>OTD</span></a><p>What Did Happened On This Day · Source visible · Contribution-only.</p><a href="https://en.wikipedia.org/" target="_blank" rel="noopener noreferrer">Made possible by Wikipedia ↗</a></footer>
    <dialog id="detailDialog" aria-labelledby="detailTitle"><div class="dialog-toolbar"><span>FROM THE DAILY ARCHIVES</span><button id="closeDetail" class="icon-button" aria-label="Close story">✕</button></div><div id="detailContent"></div></dialog>
</body>
</html>
