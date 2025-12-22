/**
 * WDHOTD Feed Manager - Mode TikTok
 * Scroll vertical infini avec cartes pleine page
 */

document.addEventListener("DOMContentLoaded", () => {
    // Configuration
    const CONFIG = {
        limit: 10, // Charger 10 items à la fois
        debounceDelay: 300
    };

    // État de l'application
    let offset = 0;
    let isLoading = false;
    let hasMore = true;
    let currentDate = {
        month: new Date().getMonth() + 1,
        day: new Date().getDate()
    };

    // Éléments DOM
    const feedEl = document.getElementById("feed");
    const loadingIndicator = document.getElementById("loadingIndicator");
    const statsContainer = document.getElementById("statsContainer");

    if (!feedEl) {
        console.error("Feed element not found");
        return;
    }

    /**
     * Charge le feed depuis l'API
     */
    async function loadFeed() {
        if (isLoading || !hasMore) return;
        
        isLoading = true;
        if (loadingIndicator) {
            loadingIndicator.style.display = "flex";
        }

        try {
            const url = `feed.php?offset=${offset}&limit=${CONFIG.limit}&type=all&month=${currentDate.month}&day=${currentDate.day}`;
            const res = await fetch(url);
            
            if (!res.ok) {
                throw new Error(`HTTP error! status: ${res.status}`);
            }

            const data = await res.json();
            
            if (!data.success) {
                throw new Error(data.error || "Une erreur est survenue");
            }

            const items = data.data || [];
            
            if (items.length === 0) {
                hasMore = false;
                if (loadingIndicator) {
                    loadingIndicator.style.display = "none";
                }
                return;
            }

            // Rendre les items
            items.forEach(item => {
                const card = createTikTokCard(item);
                feedEl.appendChild(card);
            });

            offset += items.length;
            hasMore = items.length >= CONFIG.limit;
            
            // Observer pour charger plus quand on approche de la fin
            observeLastCard();

        } catch (error) {
            console.error("Error loading feed:", error);
        } finally {
            isLoading = false;
            if (loadingIndicator) {
                loadingIndicator.style.display = "none";
            }
        }
    }

    /**
     * Crée une carte TikTok (pleine page)
     */
    function createTikTokCard(item) {
        const card = document.createElement("div");
        card.className = "tiktok-card";
        card.setAttribute("data-id", item.id || "");

        const hasImage = item.thumbnail && item.thumbnail.trim() !== "";
        const type = (item.type || "").toLowerCase();
        const title = item.selected_title || "Untitled";
        const year = item.year || "";
        const extract = item.extract_html || item.extract_short || "";

        // Design différent selon présence d'image
        if (hasImage) {
            card.innerHTML = `
                <div class="tiktok-card-bg" data-bg="${item.thumbnail}"></div>
                <div class="tiktok-card-overlay"></div>
                <div class="tiktok-card-content">
                    <div class="tiktok-card-tag tag-${type}">${type}</div>
                    <h2 class="tiktok-card-title">${escapeHtml(title)}</h2>
                    ${year ? `<div class="tiktok-card-year">${year}</div>` : ''}
                    ${extract ? `<div class="tiktok-card-extract">${truncateText(extract, 200)}</div>` : ''}
                    ${item.wikipedia_url ? `<a href="${item.wikipedia_url}" target="_blank" rel="noopener" class="tiktok-card-link">Learn more →</a>` : ''}
                </div>
            `;
        } else {
            // Design sans image - fond coloré selon le type
            card.innerHTML = `
                <div class="tiktok-card-bg-no-image bg-${type}"></div>
                <div class="tiktok-card-content">
                    <div class="tiktok-card-tag tag-${type}">${type}</div>
                    <h2 class="tiktok-card-title">${escapeHtml(title)}</h2>
                    ${year ? `<div class="tiktok-card-year">${year}</div>` : ''}
                    ${extract ? `<div class="tiktok-card-extract">${truncateText(extract, 300)}</div>` : ''}
                    ${item.wikipedia_url ? `<a href="${item.wikipedia_url}" target="_blank" rel="noopener" class="tiktok-card-link">Learn more →</a>` : ''}
                </div>
            `;
        }

        // Charger l'image en arrière-plan si présente
        if (hasImage) {
            const bgEl = card.querySelector(".tiktok-card-bg[data-bg]");
            if (bgEl) {
                const img = new Image();
                img.onload = () => {
                    bgEl.style.backgroundImage = `url('${item.thumbnail}')`;
                    bgEl.removeAttribute("data-bg");
                    card.classList.add("loaded");
                };
                img.onerror = () => {
                    // Si l'image ne charge pas, utiliser le design sans image
                    card.classList.add("no-image-fallback");
                };
                img.src = item.thumbnail;
            }
        } else {
            card.classList.add("loaded");
        }

        // Animation d'entrée
        setTimeout(() => {
            card.style.opacity = "1";
            card.style.transform = "translateY(0)";
        }, 50);

        // Ouvrir le détail au clic
        card.addEventListener("click", () => {
            openDetail(item);
        });

        return card;
    }

    /**
     * Ouvre le panneau de détail
     */
    function openDetail(item) {
        const detailPanel = document.getElementById("detailPanel");
        const detailContent = document.getElementById("detailContent");
        
        if (!detailPanel || !detailContent) return;

        const title = item.selected_title || "Untitled";
        const year = item.year || "";
        const type = (item.type || "").toLowerCase();
        const extractHtml = item.extract_html || "";
        const extractText = item.extract_short || "";
        const extract = extractHtml || escapeHtml(extractText);

        // Image avec lazy loading
        const img = item.thumbnail
            ? `<div class="detail-image">
                <img src="${item.thumbnail}" alt="${escapeHtml(title)}" loading="lazy">
               </div>`
            : "";

        // Métadonnées
        const meta = [type, year].filter(Boolean).join(" • ");

        // Lien Wikipedia
        const wikiLink = item.wikipedia_url
            ? `<a href="${item.wikipedia_url}" target="_blank" rel="noopener noreferrer" class="wiki-link">
                View on Wikipedia →
               </a>`
            : "";

        // Construire le HTML
        detailContent.innerHTML = `
            ${img}
            <h2>${escapeHtml(title)}</h2>
            ${meta ? `<div class="meta">${meta}</div>` : ""}
            <div class="extract">${extract}</div>
            ${wikiLink}
        `;

        // Ajouter l'historique pour gérer le bouton retour
        history.pushState({ detail: true }, "");
        
        // Ouvrir le panneau avec animation
        requestAnimationFrame(() => {
            detailPanel.classList.add("open");
        });

        // Focus pour l'accessibilité
        detailPanel.setAttribute("tabindex", "-1");
        detailPanel.focus();
    }

    /**
     * Ferme le panneau de détail
     */
    function closeDetail() {
        const detailPanel = document.getElementById("detailPanel");
        if (!detailPanel) return;
        
        detailPanel.classList.remove("open");
        
        // Retirer le focus après l'animation
        setTimeout(() => {
            detailPanel.removeAttribute("tabindex");
        }, 300);
    }

    // Gestion du bouton fermer
    const closeDetailBtn = document.getElementById("closeDetail");
    if (closeDetailBtn) {
        closeDetailBtn.addEventListener("click", closeDetail);
    }

    // Fermer avec la touche Escape
    document.addEventListener("keydown", (e) => {
        if (e.key === "Escape") {
            const detailPanel = document.getElementById("detailPanel");
            if (detailPanel && detailPanel.classList.contains("open")) {
                closeDetail();
            }
        }
    });

    // Fermer au swipe-back Android/iOS (back button)
    window.addEventListener("popstate", (e) => {
        const detailPanel = document.getElementById("detailPanel");
        if (detailPanel && detailPanel.classList.contains("open")) {
            closeDetail();
        }
    });

    /**
     * Échappe le HTML pour éviter les injections XSS
     */
    function escapeHtml(text) {
        const div = document.createElement("div");
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Tronque un texte (en enlevant le HTML)
     */
    function truncateText(html, maxLength) {
        const div = document.createElement("div");
        div.innerHTML = html;
        const text = div.textContent || div.innerText || "";
        if (text.length <= maxLength) return html;
        return text.substring(0, maxLength) + "...";
    }

    /**
     * Observe la dernière carte pour charger plus
     */
    function observeLastCard() {
        const cards = feedEl.querySelectorAll(".tiktok-card");
        if (cards.length === 0) return;

        const lastCard = cards[cards.length - 1];
        
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting && hasMore && !isLoading) {
                    loadFeed();
                }
            });
        }, {
            rootMargin: "200px" // Charger 200px avant d'arriver à la fin
        });

        observer.observe(lastCard);
    }

    /**
     * Charge les statistiques du jour
     */
    async function loadStats() {
        if (!statsContainer) return;
        
        try {
            const url = `stats.php?month=${currentDate.month}&day=${currentDate.day}`;
            const res = await fetch(url);
            
            if (!res.ok) return;
            
            const data = await res.json();
            
            if (data.success && data.stats) {
                const stats = data.stats;
                
                const statEvents = document.getElementById("statEvents");
                const statBirths = document.getElementById("statBirths");
                const statDeaths = document.getElementById("statDeaths");
                const statHolidays = document.getElementById("statHolidays");
                
                if (statEvents) statEvents.textContent = stats.events || 0;
                if (statBirths) statBirths.textContent = stats.births || 0;
                if (statDeaths) statDeaths.textContent = stats.deaths || 0;
                if (statHolidays) statHolidays.textContent = stats.holidays || 0;
            }
        } catch (error) {
            console.error("Error loading stats:", error);
        }
    }

    // Initialisation
    loadStats();
    loadFeed();
});
