<?php require __DIR__ . '/config.php'; ?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>WDHOTD – On This Day</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="assets/css/style.css">
    </head>

    <body>
        <header>
            <h1>On This Day</h1>
            <div class="filters">
                <button data-type="all" class="filter active">All</button>
                <button data-type="events" class="filter">Events</button>
                <button data-type="births" class="filter">Births</button>
                <button data-type="deaths" class="filter">Deaths</button>
                <button data-type="holidays" class="filter">Holidays</button>
            </div>
        </header>

        <main>
            <div class="container">
                <div id="feed"></div>
                <div class="load-more" id="loadMore">Load more</div>
            </div>

            <div id="detailPanel" class="detail-panel">
                <div class="detail-content" id="detailContent"></div>
                <div class="detail-close" id="closeDetail">Close</div>
            </div>
        </main>

        <footer class="footer">
            <div class="inner">

                <div class="left">
                    <span class="title">WDHOTD</span>
                    <span class="copy">© 2025 — All rights reserved.</span>
                </div>
            </div>
        </footer>

        <script src="assets/js/feed.js"></script>
    </body>
</html>
