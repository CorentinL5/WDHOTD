document.addEventListener("DOMContentLoaded", () => {
    let offset = 0;
    const limit = 12;
    let currentType = "all";

    const feedEl = document.getElementById("feed");
    const loadMoreEl = document.getElementById("loadMore");
    const detailPanel = document.getElementById("detailPanel");
    const detailContent = document.getElementById("detailContent");
    const closeDetailBtn = document.getElementById("closeDetail");
    const filterButtons = document.querySelectorAll(".filter");

    async function loadFeed(reset = false) {
        if (reset) {
            offset = 0;
            feedEl.innerHTML = "";
            loadMoreEl.style.display = "block";
        }

        const res = await fetch(`feed.php?offset=${offset}&limit=${limit}&type=${currentType}`);
        const items = await res.json();

        items.forEach(item => {
            const card = document.createElement("div");
            card.className = "card";

            const img = item.thumbnail || "";

            card.innerHTML = `
                <div class="bg" style="${img ? `background-image:url('${img}')` : ""}"></div>
                <div class="tag">${item.type || ""}</div>
                <div class="card-content">
                    <h3>${item.selected_title || "Untitled"}</h3>
                    <div class="year">${item.year || ""}</div>
                </div>
            `;

            card.addEventListener("click", () => openDetail(item));

            if (item.thumbnail) {
                const imgEl = new Image();
                imgEl.src = item.thumbnail;

                imgEl.onload = () => {
                    const ratio = imgEl.width / imgEl.height;

                    // limites de hauteur pour éviter les extrêmes
                    const minH = 150;
                    const maxH = 260;

                    // logiques simples :
                    // wider image => taller card
                    // vertical image => shorter card
                    let newHeight = 180; // base

                    if (ratio > 1.6) newHeight = 230;       // très horizontale
                    else if (ratio > 1.2) newHeight = 200;  // horizontale normale
                    else if (ratio < 0.8) newHeight = 160;  // verticale
                    else newHeight = 180;                   // quasi carré

                    // clamp final (ne jamais exagérer)
                    newHeight = Math.max(minH, Math.min(maxH, newHeight));

                    card.style.setProperty("--card-height", newHeight + "px");
                };
            }

            feedEl.appendChild(card);
        });

        if (items.length < limit) {
            loadMoreEl.style.display = "none";
        }

        offset += limit;
    }

    function openDetail(item) {

        // Nettoyage des valeurs vides
        const title = item.selected_title || "Untitled";
        const year  = item.year || "";
        const type  = item.type || "";
        const extract = item.extract_html || item.extract_short || "";

        const img = item.thumbnail ?
            `<div class="detail-image">
                <img src="${item.thumbnail}">
            </div>`
            : "";

        detailContent.innerHTML = `
        ${img}
        <h2>${title}</h2>
        <div class="meta">${type}${year ? " • " + year : ""}</div>
        ${extract}
        <br><br>
        ${item.wikipedia_url ?
            `<a href="${item.wikipedia_url}" target="_blank" rel="noopener noreferrer">View on Wikipedia</a>`
            : ""}
    `;

        detailPanel.classList.add("open");
    }

    function closeDetail() {
        detailPanel.classList.remove("open");
    }

    // Bouton close
    closeDetailBtn.addEventListener("click", closeDetail);

    // Fermer au swipe-back Android/iOS (back button)
    window.addEventListener("popstate", () => {
        // si panel ouvert → on le ferme au lieu de quitter
        if (detailPanel.classList.contains("open")) {
            closeDetail();
            history.pushState(null, ""); // on annule le back
        }
    });

    // Filtres
    filterButtons.forEach(btn => {
        btn.addEventListener("click", () => {
            filterButtons.forEach(b => b.classList.remove("active"));
            btn.classList.add("active");
            currentType = btn.dataset.type || "all";
            loadFeed(true);
        });
    });

    // Load more
    loadMoreEl.addEventListener("click", () => loadFeed(false));

    // Premier chargement
    loadFeed(true);
});
