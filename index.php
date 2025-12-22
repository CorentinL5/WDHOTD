<?php require __DIR__ . '/config.php'; ?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>WDHOTD – What Happened On This Day</title>
        <meta name="description" content="Discover historical events, births, deaths and holidays that happened on this day in history.">
        <meta name="keywords" content="history, historical events, on this day, births, deaths">
        <meta name="author" content="WDHOTD">
        <link rel="stylesheet" href="assets/css/style.css">
        <meta name="theme-color" content="#0a0a0a">
    </head>

    <body>
        <header class="tiktok-header">
            <div class="header-content">
                <h1>WDHOTD</h1>
                <div class="header-subtitle">What Happened On This Day</div>
                
                <!-- Stats compactes -->
                <div class="stats-compact" id="statsContainer">
                    <span class="stat-compact-item">
                        <span class="stat-compact-value" id="statEvents">-</span>
                        <span class="stat-compact-label">Events</span>
                    </span>
                    <span class="stat-compact-item">
                        <span class="stat-compact-value" id="statBirths">-</span>
                        <span class="stat-compact-label">Births</span>
                    </span>
                    <span class="stat-compact-item">
                        <span class="stat-compact-value" id="statDeaths">-</span>
                        <span class="stat-compact-label">Deaths</span>
                    </span>
                    <span class="stat-compact-item">
                        <span class="stat-compact-value" id="statHolidays">-</span>
                        <span class="stat-compact-label">Holidays</span>
                    </span>
                </div>
            </div>
        </header>

        <main class="tiktok-main">
            <div id="feed" class="tiktok-feed"></div>
            <div class="loading-indicator" id="loadingIndicator">
                <div class="spinner"></div>
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
