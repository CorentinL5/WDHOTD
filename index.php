<?php require __DIR__ . '/config.php'; ?>

<?php
$currentDate = date('d/m/Y');
$siteUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
$siteName = "WDHOTD - What Happened On This Day";
$description = "Découvrez les événements historiques, naissances, décès et fêtes qui se sont produits le $currentDate dans l'histoire. Explorez l'histoire jour par jour !";
?>
<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        
        <!-- Primary Meta Tags -->
        <title><?= htmlspecialchars($siteName) ?> - <?= htmlspecialchars($currentDate) ?></title>
        <meta name="title" content="<?= htmlspecialchars($siteName) ?>">
        <meta name="description" content="<?= htmlspecialchars($description) ?>">
        <meta name="keywords" content="histoire, événements historiques, ce jour dans l'histoire, naissances, décès, fêtes, historique, chronologie, wikipedia">
        <meta name="author" content="WDHOTD">
        <meta name="robots" content="index, follow">
        <meta name="language" content="French">
        <meta name="revisit-after" content="1 days">
        <link rel="canonical" href="<?= htmlspecialchars($siteUrl) ?>">
        
        <!-- Open Graph / Facebook -->
        <meta property="og:type" content="website">
        <meta property="og:url" content="<?= htmlspecialchars($siteUrl) ?>">
        <meta property="og:title" content="<?= htmlspecialchars($siteName) ?>">
        <meta property="og:description" content="<?= htmlspecialchars($description) ?>">
        <meta property="og:image" content="<?= htmlspecialchars($siteUrl) ?>/assets/og-image.png">
        <meta property="og:site_name" content="WDHOTD">
        <meta property="og:locale" content="fr_FR">
        
        <!-- Twitter -->
        <meta property="twitter:card" content="summary_large_image">
        <meta property="twitter:url" content="<?= htmlspecialchars($siteUrl) ?>">
        <meta property="twitter:title" content="<?= htmlspecialchars($siteName) ?>">
        <meta property="twitter:description" content="<?= htmlspecialchars($description) ?>">
        <meta property="twitter:image" content="<?= htmlspecialchars($siteUrl) ?>/assets/og-image.png">
        
        <!-- Favicon & Theme -->
        <link rel="icon" type="image/x-icon" href="/favicon.ico">
        <meta name="theme-color" content="#0a0a0a">
        <meta name="msapplication-TileColor" content="#0a0a0a">
        
        <!-- Stylesheet -->
        <link rel="stylesheet" href="assets/css/style.css">
        
        <!-- Structured Data (Schema.org) -->
        <script type="application/ld+json">
        {
            "@context": "https://schema.org",
            "@type": "WebSite",
            "name": "<?= htmlspecialchars($siteName) ?>",
            "url": "<?= htmlspecialchars($siteUrl) ?>",
            "description": "<?= htmlspecialchars($description) ?>",
            "inLanguage": "fr-FR",
            "potentialAction": {
                "@type": "SearchAction",
                "target": "<?= htmlspecialchars($siteUrl) ?>?search={search_term_string}",
                "query-input": "required name=search_term_string"
            }
        }
        </script>
        <script type="application/ld+json">
        {
            "@context": "https://schema.org",
            "@type": "Organization",
            "name": "WDHOTD",
            "url": "<?= htmlspecialchars($siteUrl) ?>",
            "logo": "<?= htmlspecialchars($siteUrl) ?>/assets/logo.png",
            "sameAs": []
        }
        </script>
    </head>

    <body>
        <header class="tiktok-header">
            <div class="header-content">
                <div class="header-left">
                    <h1>WDHOTD</h1>
                    <div class="header-subtitle">What Happened On This Day</div>
                    <div class="header-date"><?= htmlspecialchars($currentDate) ?></div>
                </div>
                
                <!-- Stats compactes -->
                <div class="stats-compact" id="statsContainer">
                    <button class="stat-compact-item filter-btn active" data-type="all" aria-label="Tous les événements">
                        <span class="stat-compact-value" id="statTotal">-</span>
                        <span class="stat-compact-label">Tous</span>
                    </button>
                    <button class="stat-compact-item filter-btn" data-type="events" aria-label="Événements">
                        <span class="stat-compact-value" id="statEvents">-</span>
                        <span class="stat-compact-label">Événements</span>
                    </button>
                    <button class="stat-compact-item filter-btn" data-type="births" aria-label="Naissances">
                        <span class="stat-compact-value" id="statBirths">-</span>
                        <span class="stat-compact-label">Naissances</span>
                    </button>
                    <button class="stat-compact-item filter-btn" data-type="deaths" aria-label="Décès">
                        <span class="stat-compact-value" id="statDeaths">-</span>
                        <span class="stat-compact-label">Décès</span>
                    </button>
                    <button class="stat-compact-item filter-btn" data-type="holidays" aria-label="Fêtes">
                        <span class="stat-compact-value" id="statHolidays">-</span>
                        <span class="stat-compact-label">Fêtes</span>
                    </button>
                </div>
            </div>
        </header>
        
        <!-- Progress bar de scroll -->
        <div class="scroll-progress" id="scrollProgress"></div>

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

        <button id="scrollToTop" class="scroll-to-top" aria-label="Retour en haut">↑</button>

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
