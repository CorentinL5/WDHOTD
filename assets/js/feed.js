/**
 * WDHOTD Feed Manager - Mode TikTok
 * Scroll vertical infini avec cartes pleine page
 * Avec sauvegarde de position et gestion des éléments vus
 */

document.addEventListener("DOMContentLoaded", () => {
    // Configuration
    const CONFIG = {
        limit: 10, // Charger 10 items à la fois
        debounceDelay: 300,
        scrollSaveInterval: 1000, // Sauvegarder la position toutes les secondes
        viewedItemsKey: 'wdhotd_viewed_items',
        scrollPositionKey: 'wdhotd_scroll_position',
        currentDateKey: 'wdhotd_current_date'
    };

    // État de l'application
    let offset = 0;
    let isLoading = false;
    let hasMore = true;
    let currentFilter = 'all';
    let currentDate = {
        month: new Date().getMonth() + 1,
        day: new Date().getDate()
    };
    
    // Récupérer les éléments déjà vus depuis localStorage
    let viewedItems = new Set();
    try {
        const stored = localStorage.getItem(CONFIG.viewedItemsKey);
        if (stored) {
            viewedItems = new Set(JSON.parse(stored));
        }
    } catch (e) {
        console.error("Error loading viewed items:", e);
    }
    
    // Restaurer la date si elle est différente
    try {
        const storedDate = localStorage.getItem(CONFIG.currentDateKey);
        if (storedDate) {
            const parsed = JSON.parse(storedDate);
            const today = { month: new Date().getMonth() + 1, day: new Date().getDate() };
            if (parsed.month === today.month && parsed.day === today.day) {
                // Même date, on peut restaurer la position
            } else {
                // Date différente, réinitialiser
                viewedItems.clear();
                localStorage.removeItem(CONFIG.scrollPositionKey);
            }
        }
    } catch (e) {
        console.error("Error checking date:", e);
    }
    
    // Sauvegarder la date actuelle
    localStorage.setItem(CONFIG.currentDateKey, JSON.stringify(currentDate));

    // Éléments DOM
    const feedEl = document.getElementById("feed");
    const loadingIndicator = document.getElementById("loadingIndicator");
    const statsContainer = document.getElementById("statsContainer");
    const refreshBtn = document.getElementById("refreshBtn");

    if (!feedEl) {
        console.error("Feed element not found");
        return;
    }
    
    // Indicateur de restauration de position
    let isRestoringScroll = false;
    
    /**
     * Sauvegarde la position de scroll dans localStorage
     */
    function saveScrollPosition() {
        if (isRestoringScroll) return;
        try {
            const scrollY = window.scrollY || window.pageYOffset;
            localStorage.setItem(CONFIG.scrollPositionKey, scrollY.toString());
        } catch (e) {
            console.error("Error saving scroll position:", e);
        }
    }
    
    /**
     * Restaure la position de scroll depuis localStorage
     */
    async function restoreScrollPosition() {
        try {
            const savedPosition = localStorage.getItem(CONFIG.scrollPositionKey);
            if (savedPosition === null) return;
            
            const position = parseInt(savedPosition, 10);
            if (isNaN(position) || position <= 0) return;
            
            isRestoringScroll = true;
            
            // Charger plus d'éléments si nécessaire pour atteindre la position sauvegardée
            let attempts = 0;
            const maxAttempts = 10;
            
            const checkAndScroll = () => {
                const currentHeight = document.documentElement.scrollHeight;
                const headerHeight = document.querySelector('.tiktok-header')?.offsetHeight || 0;
                
                // Si on a assez de contenu pour atteindre la position, scroller
                if (currentHeight > position + window.innerHeight || attempts >= maxAttempts) {
                    window.scrollTo({
                        top: position,
                        behavior: 'auto'
                    });
                    
                    // Marquer les éléments vus qui sont au-dessus de la position
                    setTimeout(() => {
                        markViewedItemsAbove(position);
                        isRestoringScroll = false;
                    }, 100);
                } else if (hasMore && !isLoading && attempts < maxAttempts) {
                    // Charger plus et réessayer
                    attempts++;
                    loadFeed().then(() => {
                        setTimeout(checkAndScroll, 200);
                    });
                } else {
                    // Pas assez de contenu, scroller au maximum possible
                    window.scrollTo({
                        top: currentHeight - window.innerHeight,
                        behavior: 'auto'
                    });
                    isRestoringScroll = false;
                }
            };
            
            // Démarrer la vérification après un court délai
            setTimeout(checkAndScroll, 300);
        } catch (e) {
            console.error("Error restoring scroll position:", e);
            isRestoringScroll = false;
        }
    }
    
    /**
     * Marque les éléments qui sont visibles au-dessus d'une certaine position
     */
    function markViewedItemsAbove(maxY) {
        const cards = feedEl.querySelectorAll(".tiktok-card:not(.viewed)");
        cards.forEach(card => {
            const rect = card.getBoundingClientRect();
            const cardTop = rect.top + window.scrollY;
            if (cardTop < maxY) {
                const cardId = card.getAttribute("data-id");
                if (cardId) {
                    viewedItems.add(cardId);
                    card.classList.add("viewed");
                }
            }
        });
        if (cards.length > 0) {
            saveViewedItems();
        }
    }
    
    /**
     * Sauvegarde la liste des éléments vus
     */
    function saveViewedItems() {
        try {
            localStorage.setItem(CONFIG.viewedItemsKey, JSON.stringify(Array.from(viewedItems)));
        } catch (e) {
            console.error("Error saving viewed items:", e);
        }
    }
    
    // Sauvegarder la position de scroll périodiquement
    let scrollSaveTimer;
    window.addEventListener("scroll", () => {
        clearTimeout(scrollSaveTimer);
        scrollSaveTimer = setTimeout(saveScrollPosition, CONFIG.scrollSaveInterval);
        
        // Marquer les éléments visibles comme vus
        markVisibleItemsAsViewed();
    }, { passive: true });
    
    // Sauvegarder aussi avant de quitter la page
    window.addEventListener("beforeunload", saveScrollPosition);
    
    // Bouton scroll to top
    const scrollToTopBtn = document.getElementById("scrollToTop");
    if (scrollToTopBtn) {
        // Afficher/masquer le bouton selon le scroll
        window.addEventListener("scroll", () => {
            if (window.scrollY > 500) {
                scrollToTopBtn.classList.add("visible");
            } else {
                scrollToTopBtn.classList.remove("visible");
            }
        }, { passive: true });
        
        // Scroll vers le haut au clic
        scrollToTopBtn.addEventListener("click", () => {
            window.scrollTo({
                top: 0,
                behavior: "smooth"
            });
        });
    }
    
    /**
     * Marque les éléments visibles à l'écran comme vus
     */
    function markVisibleItemsAsViewed() {
        const cards = feedEl.querySelectorAll(".tiktok-card:not(.viewed)");
        const viewportTop = window.scrollY;
        const viewportBottom = viewportTop + window.innerHeight;
        
        cards.forEach(card => {
            const rect = card.getBoundingClientRect();
            const cardTop = rect.top + window.scrollY;
            const cardBottom = cardTop + rect.height;
            
            // Si la carte est au moins partiellement visible
            if (cardBottom > viewportTop && cardTop < viewportBottom) {
                const cardId = card.getAttribute("data-id");
                if (cardId) {
                    viewedItems.add(cardId);
                    card.classList.add("viewed");
                }
            }
        });
        
        if (cards.length > 0) {
            saveViewedItems();
        }
    }

    /**
     * Charge le feed depuis l'API
     */
    async function loadFeed(reset = false) {
        if (isLoading || (!hasMore && !reset)) return;
        
        if (reset) {
            offset = 0;
            hasMore = true;
            feedEl.innerHTML = '';
            if (lastCardObserver) {
                lastCardObserver.disconnect();
            }
        }
        
        isLoading = true;
        if (loadingIndicator) {
            loadingIndicator.style.display = "flex";
        }

        try {
            const url = `feed.php?offset=${offset}&limit=${CONFIG.limit}&type=${currentFilter}&month=${currentDate.month}&day=${currentDate.day}`;
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
     * Détecte si on est sur desktop
     */
    function isDesktop() {
        return window.matchMedia("(min-width: 768px)").matches;
    }

    /**
     * Crée une carte TikTok (pleine page mobile, carte desktop)
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
        const desktop = isDesktop();

        // Design différent selon présence d'image et device
        if (hasImage) {
            if (desktop) {
                // Desktop: image en header
                card.innerHTML = `
                    <div class="tiktok-card-bg" data-bg="${item.thumbnail}"></div>
                    <div class="tiktok-card-overlay"></div>
                    <div class="tiktok-card-content">
                        <div class="tiktok-card-tag tag-${type}">${type}</div>
                        <h2 class="tiktok-card-title">${escapeHtml(title)}</h2>
                        ${year ? `<div class="tiktok-card-year">${year}</div>` : ''}
                        ${extract ? `<div class="tiktok-card-extract">${truncateText(extract, 300)}</div>` : ''}
                        ${item.wikipedia_url ? `<a href="${item.wikipedia_url}" target="_blank" rel="noopener" class="tiktok-card-link">Learn more →</a>` : ''}
                    </div>
                `;
            } else {
                // Mobile: image en arrière-plan
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
            }
        } else {
            // Design sans image - fond coloré selon le type
            card.innerHTML = `
                <div class="tiktok-card-bg-no-image bg-${type}"></div>
                ${!desktop ? '<div class="tiktok-card-overlay"></div>' : ''}
                <div class="tiktok-card-content">
                    <div class="tiktok-card-tag tag-${type}">${type}</div>
                    <h2 class="tiktok-card-title">${escapeHtml(title)}</h2>
                    ${year ? `<div class="tiktok-card-year">${year}</div>` : ''}
                    ${extract ? `<div class="tiktok-card-extract">${truncateText(extract, desktop ? 300 : 300)}</div>` : ''}
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

        // Marquer comme vu si déjà dans la liste
        const itemId = (item.id || "").toString();
        if (itemId && viewedItems.has(itemId)) {
            card.classList.add("viewed");
        }
        
        // Animation d'entrée
        setTimeout(() => {
            card.style.opacity = "1";
            card.style.transform = "translateY(0)";
        }, 50);

        // Ouvrir le détail au clic
        card.addEventListener("click", (e) => {
            // Animation confetti
            const rect = card.getBoundingClientRect();
            const x = rect.left + rect.width / 2;
            const y = rect.top + rect.height / 2;
            createConfetti(x, y);
            
            // Marquer comme vu quand on clique dessus
            if (itemId) {
                viewedItems.add(itemId);
                card.classList.add("viewed");
                saveViewedItems();
            }
            openDetail(item);
        });

        return card;
    }

    /**
     * Partage avec l'API Web Share native ou fallback vers réseaux sociaux
     */
    async function shareOnSocial(platform, url, title, text) {
        // Si c'est "native" ou si l'API Web Share est disponible, utiliser le partage natif
        if (platform === 'native' || (navigator.share && !platform)) {
            try {
                await navigator.share({
                    title: title,
                    text: text,
                    url: url
                });
                return;
            } catch (error) {
                // L'utilisateur a annulé ou erreur, continuer avec le fallback
                if (error.name !== 'AbortError') {
                    console.error('Erreur de partage:', error);
                }
                // Si l'utilisateur annule, ne rien faire
                if (error.name === 'AbortError') return;
            }
        }
        
        // Fallback vers les réseaux sociaux
        const encodedUrl = encodeURIComponent(url);
        const encodedTitle = encodeURIComponent(title);
        const encodedText = encodeURIComponent(text);
        
        let shareUrl = '';
        switch(platform) {
            case 'twitter':
                shareUrl = `https://twitter.com/intent/tweet?url=${encodedUrl}&text=${encodedTitle}`;
                break;
            case 'facebook':
                shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${encodedUrl}`;
                break;
            case 'linkedin':
                shareUrl = `https://www.linkedin.com/sharing/share-offsite/?url=${encodedUrl}`;
                break;
            case 'whatsapp':
                shareUrl = `https://wa.me/?text=${encodedTitle}%20${encodedUrl}`;
                break;
            default:
                return;
        }
        
        if (shareUrl) {
            window.open(shareUrl, '_blank', 'width=600,height=400');
        }
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
        const currentUrl = window.location.href;
        const shareText = `${title}${year ? ` (${year})` : ''} - Découvrez l'histoire du jour sur WDHOTD`;

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
                Voir sur Wikipedia →
               </a>`
            : "";
        
        // Boutons de partage - Ajouter le bouton natif si disponible
        const hasNativeShare = navigator.share;
        const nativeShareBtn = hasNativeShare ? `
            <button class="share-btn share-native" data-platform="native" aria-label="Partager" title="Partager">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65 0 1.61 1.31 2.92 2.92 2.92 1.61 0 2.92-1.31 2.92-2.92s-1.31-2.92-2.92-2.92z"/></svg>
            </button>
        ` : '';
        
        const shareButtons = `
            <div class="share-buttons">
                ${nativeShareBtn}
                <button class="share-btn" data-platform="twitter" aria-label="Partager sur Twitter" title="Twitter">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M23 3a10.9 10.9 0 01-3.14 1.53 4.48 4.48 0 00-7.86 3v1A10.66 10.66 0 013 4s-4 9 5 13a11.64 11.64 0 01-7 2c9 5 20 0 20-11.5a4.5 4.5 0 00-.08-.83A7.72 7.72 0 0023 3z"/></svg>
                </button>
                <button class="share-btn" data-platform="facebook" aria-label="Partager sur Facebook" title="Facebook">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z"/></svg>
                </button>
                <button class="share-btn" data-platform="linkedin" aria-label="Partager sur LinkedIn" title="LinkedIn">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M16 8a6 6 0 016 6v7h-4v-7a2 2 0 00-2-2 2 2 0 00-2 2v7h-4v-7a6 6 0 016-6zM2 9h4v12H2z"/><circle cx="4" cy="4" r="2"/></svg>
                </button>
                <button class="share-btn" data-platform="whatsapp" aria-label="Partager sur WhatsApp" title="WhatsApp">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>
                </button>
                <button class="share-btn copy-link" aria-label="Copier le lien" title="Copier le lien">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71"/></svg>
                </button>
            </div>
        `;

        // Construire le HTML
        detailContent.innerHTML = `
            ${img}
            <h2>${escapeHtml(title)}</h2>
            ${meta ? `<div class="meta">${meta}</div>` : ""}
            <div class="extract">${extract}</div>
            ${shareButtons}
            ${wikiLink}
        `;
        
        // Ajouter les event listeners pour le partage
        const shareBtns = detailContent.querySelectorAll('.share-btn');
        shareBtns.forEach(btn => {
            btn.addEventListener('click', async (e) => {
                e.stopPropagation();
                const platform = btn.getAttribute('data-platform');
                if (platform) {
                    await shareOnSocial(platform, currentUrl, title, shareText);
                } else if (btn.classList.contains('copy-link')) {
                    try {
                        await navigator.clipboard.writeText(currentUrl);
                        btn.innerHTML = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>';
                        setTimeout(() => {
                            btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71"/></svg>';
                        }, 2000);
                    } catch (err) {
                        console.error('Erreur lors de la copie:', err);
                    }
                }
            });
        });

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

    // Observer global pour le lazy loading
    let lastCardObserver = null;
    
    /**
     * Observe la dernière carte pour charger plus
     */
    function observeLastCard() {
        const cards = feedEl.querySelectorAll(".tiktok-card");
        if (cards.length === 0) return;

        const lastCard = cards[cards.length - 1];
        
        // Arrêter l'observation précédente si elle existe
        if (lastCardObserver) {
            lastCardObserver.disconnect();
        }
        
        lastCardObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting && hasMore && !isLoading) {
                    loadFeed();
                }
            });
        }, {
            rootMargin: "200px" // Charger 200px avant d'arriver à la fin
        });

        lastCardObserver.observe(lastCard);
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
                
                const statTotal = document.getElementById("statTotal");
                const statEvents = document.getElementById("statEvents");
                const statBirths = document.getElementById("statBirths");
                const statDeaths = document.getElementById("statDeaths");
                const statHolidays = document.getElementById("statHolidays");
                
                if (statTotal) {
                    const oldVal = parseInt(statTotal.textContent) || 0;
                    if (oldVal === 0 || oldVal !== stats.total) {
                        animateValue(statTotal, oldVal, stats.total || 0, 500);
                    }
                }
                if (statEvents) {
                    const oldVal = parseInt(statEvents.textContent) || 0;
                    if (oldVal !== stats.events) {
                        animateValue(statEvents, oldVal, stats.events || 0, 500);
                    }
                }
                if (statBirths) {
                    const oldVal = parseInt(statBirths.textContent) || 0;
                    if (oldVal !== stats.births) {
                        animateValue(statBirths, oldVal, stats.births || 0, 500);
                    }
                }
                if (statDeaths) {
                    const oldVal = parseInt(statDeaths.textContent) || 0;
                    if (oldVal !== stats.deaths) {
                        animateValue(statDeaths, oldVal, stats.deaths || 0, 500);
                    }
                }
                if (statHolidays) {
                    const oldVal = parseInt(statHolidays.textContent) || 0;
                    if (oldVal !== stats.holidays) {
                        animateValue(statHolidays, oldVal, stats.holidays || 0, 500);
                    }
                }
            }
        } catch (error) {
            console.error("Error loading stats:", error);
        }
    }
    
    /**
     * Gère les filtres par type
     */
    function initFilters() {
        const filterBtns = document.querySelectorAll('.filter-btn');
        filterBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const type = btn.getAttribute('data-type');
                if (type === currentFilter) return;
                
                // Mettre à jour l'état actif
                filterBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                
                // Changer le filtre
                currentFilter = type;
                
                // Recharger le feed
                loadFeed(true);
            });
        });
    }
    
    /**
     * Progress bar de scroll
     */
    function initScrollProgress() {
        const progressBar = document.getElementById('scrollProgress');
        if (!progressBar) return;
        
        window.addEventListener('scroll', () => {
            const winScroll = document.body.scrollTop || document.documentElement.scrollTop;
            const height = document.documentElement.scrollHeight - document.documentElement.clientHeight;
            const scrolled = (winScroll / height) * 100;
            progressBar.style.width = scrolled + '%';
        }, { passive: true });
    }
    
    /**
     * Animation de compteur
     */
    function animateValue(element, start, end, duration) {
        if (start === end) return;
        let startTimestamp = null;
        const step = (timestamp) => {
            if (!startTimestamp) startTimestamp = timestamp;
            const progress = Math.min((timestamp - startTimestamp) / duration, 1);
            const value = Math.floor(progress * (end - start) + start);
            element.textContent = value;
            if (progress < 1) {
                window.requestAnimationFrame(step);
            } else {
                element.textContent = end;
            }
        };
        window.requestAnimationFrame(step);
    }
    
    /**
     * Animation confetti au clic sur une carte
     */
    function createConfetti(x, y) {
        const colors = ['#ff3b30', '#34c95e', '#007aff', '#ff9500', '#ff2d55', '#af52de'];
        const confettiCount = 30;
        
        for (let i = 0; i < confettiCount; i++) {
            const confetti = document.createElement('div');
            confetti.className = 'confetti';
            confetti.style.left = x + 'px';
            confetti.style.top = y + 'px';
            confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
            confetti.style.transform = `rotate(${Math.random() * 360}deg)`;
            
            const angle = (Math.PI * 2 * i) / confettiCount;
            const velocity = 50 + Math.random() * 50;
            const vx = Math.cos(angle) * velocity;
            const vy = Math.sin(angle) * velocity;
            
            document.body.appendChild(confetti);
            
            requestAnimationFrame(() => {
                confetti.style.transition = 'all 0.8s cubic-bezier(0.5, 0, 0.5, 1)';
                confetti.style.transform += ` translate(${vx}px, ${vy}px) rotate(${Math.random() * 720}deg)`;
                confetti.style.opacity = '0';
                
                setTimeout(() => confetti.remove(), 800);
            });
        }
    }

    /**
     * Initialise le chargement avec restauration de position
     */
    async function initialize() {
        await loadStats();
        
        // Charger le premier batch
        await loadFeed();
        
        // Restaurer la position (qui chargera plus si nécessaire)
        await restoreScrollPosition();
        
        // Observer pour charger plus
        observeLastCard();
    }
    
    /**
     * Initialise le bouton de rafraîchissement
     */
    function initRefresh() {
        if (refreshBtn) {
            refreshBtn.addEventListener('click', () => {
                // Animation de rotation
                refreshBtn.style.transform = 'rotate(360deg)';
                
                // Réinitialiser et recharger
                viewedItems.clear();
                localStorage.removeItem(CONFIG.viewedItemsKey);
                localStorage.removeItem(CONFIG.scrollPositionKey);
                
                // Recharger le feed
                loadFeed(true).then(() => {
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                    setTimeout(() => {
                        refreshBtn.style.transform = '';
                    }, 500);
                });
            });
        }
    }
    
    // Initialisation
    initialize();
    initFilters();
    initScrollProgress();
    initRefresh();
});
