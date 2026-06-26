<div
    id="photo-import-resolve-modal"
    hidden
    style="position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:1000;display:flex;align-items:center;justify-content:center;padding:1rem;"
>
    <div
        role="dialog"
        aria-modal="true"
        aria-labelledby="photo-import-resolve-title"
        style="background:#fff;border-radius:10px;max-width:52rem;width:100%;max-height:90vh;overflow:auto;padding:1.25rem;box-shadow:0 12px 40px rgba(0,0,0,0.18);"
    >
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;margin-bottom:1rem;">
            <h2 id="photo-import-resolve-title" style="margin:0;font-size:1.15rem;">Resolve match</h2>
            <button type="button" class="btn" id="photo-import-resolve-close">Close</button>
        </div>

        <div style="display:flex;flex-wrap:wrap;gap:1rem;margin-bottom:1rem;">
            <img
                id="photo-import-resolve-thumb"
                alt=""
                width="160"
                height="120"
                style="width:160px;height:120px;object-fit:cover;border-radius:6px;border:1px solid #e4e4e2;background:#f3f3f1;"
            >
            <div style="min-width:14rem;flex:1;">
                <div><strong>Filename:</strong> <code id="photo-import-resolve-filename"></code></div>
                <div id="photo-import-resolve-title-candidate-wrap" class="field-hint" style="margin-top:0.35rem;display:none;">
                    Parsed title: <span id="photo-import-resolve-title-candidate"></span>
                </div>
                @if ($supportsSkuSearch ?? false)
                    <div id="photo-import-resolve-sku-candidate-wrap" class="field-hint" style="margin-top:0.35rem;display:none;">
                        Parsed SKU: <code id="photo-import-resolve-sku-candidate"></code>
                    </div>
                @endif
                <p class="field-hint" style="margin:0.5rem 0 0;">
                    Search artworks below, click Select, or drag this photo onto an artwork card.
                </p>
            </div>
        </div>

        <label class="field-inline-label" style="display:block;margin-bottom:0.75rem;">
            Search artworks
            <input type="search" id="photo-import-resolve-search" placeholder="Title{{ ($supportsSkuSearch ?? false) ? ', SKU, or inventory code' : '' }}" style="width:100%;margin-top:0.35rem;">
        </label>

        <div id="photo-import-resolve-results" style="display:grid;gap:0.75rem;grid-template-columns:repeat(auto-fill,minmax(15rem,1fr));"></div>
        <p id="photo-import-resolve-empty" class="field-hint" style="margin:0.75rem 0 0;display:none;">No artworks found.</p>
    </div>
</div>

<script>
    (function () {
        const token = @json($token);
        const supportsSkuSearch = @json($supportsSkuSearch ?? false);
        const csrfToken = @json(csrf_token());
        const searchUrl = @json(route('artworks.photo-bulk-import.preview.search', ['token' => $token]));
        const resolveUrl = @json(route('artworks.photo-bulk-import.preview.resolve', ['token' => $token]));
        const undoUrl = @json(route('artworks.photo-bulk-import.preview.undo-resolve', ['token' => $token]));
        const thumbUrlTemplate = @json(route('artworks.photo-bulk-import.preview.thumb', ['token' => $token, 'rowKey' => '__ROW__']));

        const modal = document.getElementById('photo-import-resolve-modal');
        const searchInput = document.getElementById('photo-import-resolve-search');
        const resultsEl = document.getElementById('photo-import-resolve-results');
        const emptyEl = document.getElementById('photo-import-resolve-empty');
        let activeRowKey = null;
        let searchTimer = null;

        function thumbUrl(rowKey) {
            return thumbUrlTemplate.replace('__ROW__', encodeURIComponent(rowKey));
        }

        function openModal(rowKey) {
            const row = document.querySelector(`.photo-import-row[data-row-key="${CSS.escape(rowKey)}"]`);
            if (!row) {
                return;
            }

            activeRowKey = rowKey;
            document.getElementById('photo-import-resolve-filename').textContent = row.dataset.filename || '';
            document.getElementById('photo-import-resolve-thumb').src = thumbUrl(rowKey);

            const titleCandidate = row.dataset.titleCandidate || '';
            const titleWrap = document.getElementById('photo-import-resolve-title-candidate-wrap');
            if (titleCandidate !== '') {
                titleWrap.style.display = '';
                document.getElementById('photo-import-resolve-title-candidate').textContent = titleCandidate;
            } else {
                titleWrap.style.display = 'none';
            }

            if (supportsSkuSearch) {
                const skuCandidate = row.dataset.skuCandidate || '';
                const skuWrap = document.getElementById('photo-import-resolve-sku-candidate-wrap');
                if (skuCandidate !== '') {
                    skuWrap.style.display = '';
                    document.getElementById('photo-import-resolve-sku-candidate').textContent = skuCandidate;
                } else {
                    skuWrap.style.display = 'none';
                }
            }

            searchInput.value = titleCandidate;
            modal.hidden = false;
            modal.style.display = 'flex';
            runSearch(titleCandidate);
            searchInput.focus();
        }

        function closeModal() {
            modal.hidden = true;
            modal.style.display = 'none';
            activeRowKey = null;
            resultsEl.innerHTML = '';
            emptyEl.style.display = 'none';
        }

        function renderArtworkCard(artwork) {
            const card = document.createElement('article');
            card.className = 'photo-import-artwork-card';
            card.dataset.artworkId = String(artwork.id);
            card.style.cssText = 'border:1px solid #e4e4e2;border-radius:8px;padding:0.65rem;background:#fafaf8;display:flex;flex-direction:column;gap:0.5rem;';
            card.innerHTML = `
                <div style="display:flex;gap:0.65rem;align-items:flex-start;">
                    ${artwork.thumbnail_url
                        ? `<img src="${artwork.thumbnail_url}" alt="" width="72" height="54" style="width:72px;height:54px;object-fit:cover;border-radius:4px;border:1px solid #e4e4e2;">`
                        : `<div style="width:72px;height:54px;border-radius:4px;border:1px solid #e4e4e2;background:#ececeb;"></div>`}
                    <div style="min-width:0;flex:1;">
                        <div style="font-weight:600;line-height:1.3;">${artwork.title}</div>
                        ${artwork.dimensions ? `<div class="field-hint">${artwork.dimensions}</div>` : ''}
                        <div class="field-hint">${artwork.completed_date}</div>
                        ${supportsSkuSearch && artwork.sku ? `<div class="field-hint">SKU: <code>${artwork.sku}</code></div>` : ''}
                        ${supportsSkuSearch && artwork.inventory_code ? `<div class="field-hint">Inventory: <code>${artwork.inventory_code}</code></div>` : ''}
                    </div>
                </div>
                <button type="button" class="btn btn-primary photo-import-select-artwork" data-artwork-id="${artwork.id}">Select this artwork</button>
            `;

            card.addEventListener('dragover', (event) => {
                event.preventDefault();
                card.style.outline = '2px solid #1a4d8c';
            });
            card.addEventListener('dragleave', () => {
                card.style.outline = '';
            });
            card.addEventListener('drop', (event) => {
                event.preventDefault();
                card.style.outline = '';
                const draggedRowKey = event.dataTransfer.getData('text/photo-import-row-key');
                if (draggedRowKey && draggedRowKey === activeRowKey) {
                    resolveToArtwork(artwork.id);
                }
            });

            card.querySelector('.photo-import-select-artwork').addEventListener('click', () => {
                resolveToArtwork(artwork.id);
            });

            return card;
        }

        async function runSearch(query) {
            const url = new URL(searchUrl, window.location.origin);
            if (query) {
                url.searchParams.set('q', query);
            }

            const response = await fetch(url.toString(), {
                headers: { 'Accept': 'application/json' },
            });
            const data = await response.json();
            resultsEl.innerHTML = '';

            if (!response.ok) {
                emptyEl.textContent = data.message || 'Search failed.';
                emptyEl.style.display = '';
                return;
            }

            const artworks = data.artworks || [];
            if (artworks.length === 0) {
                emptyEl.textContent = 'No artworks found.';
                emptyEl.style.display = '';
                return;
            }

            emptyEl.style.display = 'none';
            artworks.forEach((artwork) => {
                resultsEl.appendChild(renderArtworkCard(artwork));
            });
        }

        async function resolveToArtwork(artworkId) {
            if (!activeRowKey) {
                return;
            }

            const response = await fetch(resolveUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    row_key: activeRowKey,
                    artwork_id: artworkId,
                }),
            });

            const data = await response.json();
            if (!response.ok) {
                window.alert(data.message || 'Could not resolve match.');
                return;
            }

            closeModal();
            window.location.reload();
        }

        async function undoResolve(rowKey) {
            const response = await fetch(undoUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({ row_key: rowKey }),
            });

            const data = await response.json();
            if (!response.ok) {
                window.alert(data.message || 'Could not undo match.');
                return;
            }

            window.location.reload();
        }

        document.getElementById('photo-import-resolve-close').addEventListener('click', closeModal);
        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                closeModal();
            }
        });

        searchInput.addEventListener('input', () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => runSearch(searchInput.value.trim()), 250);
        });

        document.querySelectorAll('.photo-import-open-resolve').forEach((button) => {
            button.addEventListener('click', () => openModal(button.dataset.rowKey));
        });

        document.querySelectorAll('.photo-import-undo-resolve').forEach((button) => {
            button.addEventListener('click', () => undoResolve(button.dataset.rowKey));
        });

        document.querySelectorAll('.photo-import-row-thumb').forEach((img) => {
            img.setAttribute('draggable', 'true');
            img.addEventListener('dragstart', (event) => {
                const row = img.closest('.photo-import-row');
                if (row) {
                    event.dataTransfer.setData('text/photo-import-row-key', row.dataset.rowKey || '');
                }
            });
        });

        window.photoImportOpenResolve = openModal;
        window.photoImportUndoResolve = undoResolve;
    })();
</script>
