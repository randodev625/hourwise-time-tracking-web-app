document.addEventListener('DOMContentLoaded', () => {
    // -----------------------------
    // Timer badges
    // -----------------------------
    function formatDur(secs) {
        const h = Math.floor(secs / 3600);
        const m = Math.floor((secs % 3600) / 60);
        const s = secs % 60;

        return h > 0
            ? `${h}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`
            : `${m}:${String(s).padStart(2, '0')}`;
    }

    function attachTimer(timer) {
        if (!timer || timer.dataset.intervalSet === 'true') return;

        const startTs = parseInt(timer.dataset.startTs, 10);
        if (Number.isNaN(startTs)) return;

        const startMs = startTs * 1000;

        function update() {
            const elapsed = Math.floor((Date.now() - startMs) / 1000);
            timer.textContent = elapsed >= 0 ? formatDur(elapsed) : '0:00';
        }

        update();
        setInterval(update, 1000);
        timer.dataset.intervalSet = 'true';
    }

    function attachTimersIn(root = document) {
        root.querySelectorAll('.timer').forEach(attachTimer);
    }

    attachTimersIn();

    // -----------------------------
    // Entries page helpers
    // -----------------------------
    const entriesFilterForm = document.getElementById('entries_filter_form');
    const projectInput = document.getElementById('project_name');
    const clientInput = document.getElementById('client_name');
    const categoryInput = document.getElementById('category_name');
    const startInput = document.getElementById('start_date');
    const endInput = document.getElementById('end_date');
    const loadMoreBtn = document.getElementById('load_more');
    const entriesTbody = document.getElementById('entries_tbody');
    const entriesTable = document.getElementById('entries_table');

    function submitEntriesFilterForm() {
        if (!entriesFilterForm) return;
        entriesFilterForm.submit();
    }

    function formatDate(date) {
        const y = date.getFullYear();
        const m = String(date.getMonth() + 1).padStart(2, '0');
        const d = String(date.getDate()).padStart(2, '0');
        return `${y}-${m}-${d}`;
    }

    // -----------------------------
    // Quick date filter buttons
    // -----------------------------
    const today = new Date();

    document.getElementById('btn_7days')?.addEventListener('click', () => {
        const start = new Date();
        start.setDate(today.getDate() - 6);

        if (startInput) startInput.value = formatDate(start);
        if (endInput) endInput.value = formatDate(today);

        submitEntriesFilterForm();
    });

    document.getElementById('btn_30days')?.addEventListener('click', () => {
        const start = new Date();
        start.setDate(today.getDate() - 29);

        if (startInput) startInput.value = formatDate(start);
        if (endInput) endInput.value = formatDate(today);

        submitEntriesFilterForm();
    });

    document.getElementById('btn_year')?.addEventListener('click', () => {
        const start = new Date(today.getFullYear(), 0, 1);
        const end = new Date(today.getFullYear(), 11, 31);

        if (startInput) startInput.value = formatDate(start);
        if (endInput) endInput.value = formatDate(end);

        submitEntriesFilterForm();
    });

    // -----------------------------
    // Autocomplete helpers
    // Expects filter_lookup.php?type=project|client|category&q=...
    // If suggestion containers do not exist in the HTML,
    // they are created automatically.
    // -----------------------------
    function ensureSuggestionsBox(input, id) {
        if (!input) return null;

        let box = document.getElementById(id);
        if (box) return box;

        box = document.createElement('div');
        box.id = id;
        box.className = 'list-group position-absolute w-100 shadow-sm d-none';
        box.style.zIndex = '1050';
        box.style.maxHeight = '260px';
        box.style.overflowY = 'auto';

        const parent = input.parentElement;
        if (parent) {
            const parentStyle = window.getComputedStyle(parent);
            if (parentStyle.position === 'static') {
                parent.style.position = 'relative';
            }
            parent.appendChild(box);
        }

        return box;
    }

    function hideSuggestions(box) {
        if (!box) return;
        box.innerHTML = '';
        box.classList.add('d-none');
    }

    function attachAutocomplete({ input, type, boxId }) {
        if (!input || !entriesFilterForm) return;

        const box = ensureSuggestionsBox(input, boxId);
        if (!box) return;

        let debounceTimer = null;
        let controller = null;

        async function runLookup(query = '') {
            if (controller) controller.abort();
            controller = new AbortController();

            try {
                const res = await fetch(
                    `filter_lookup.php?type=${encodeURIComponent(type)}&q=${encodeURIComponent(query)}`,
                    { signal: controller.signal }
                );

                if (!res.ok) {
                    hideSuggestions(box);
                    return;
                }

                const items = await res.json();

                box.innerHTML = '';

                if (!Array.isArray(items) || items.length === 0) {
                    hideSuggestions(box);
                    return;
                }

                items.forEach((item) => {
                    const label = typeof item === 'string'
                        ? item
                        : (item.label ?? item.name ?? '');

                    if (!label) return;

                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'list-group-item list-group-item-action';
                    btn.textContent = label;

                    btn.addEventListener('click', () => {
                        let valueToUse = label;

                        if (type === 'project' && label.includes(' — ')) {
                            const parts = label.split(' — ');
                            valueToUse = parts[parts.length - 1].trim();
                        }

                        input.value = valueToUse;
                        hideSuggestions(box);
                        submitEntriesFilterForm();
                    });

                    box.appendChild(btn);
                });

                box.classList.remove('d-none');
            } catch (err) {
                if (err.name !== 'AbortError') {
                    console.error(err);
                }
                hideSuggestions(box);
            }
        }

        input.addEventListener('focus', () => {
            runLookup(input.value.trim());
        });

        input.addEventListener('click', () => {
            if (box.classList.contains('d-none')) {
                runLookup(input.value.trim());
            }
        });

        input.addEventListener('input', () => {
            const q = input.value.trim();

            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                runLookup(q);
            }, 150);
        });

        input.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                hideSuggestions(box);
            }
        });

        document.addEventListener('click', (e) => {
            if (e.target !== input && !box.contains(e.target)) {
                hideSuggestions(box);
            }
        });
    }

    attachAutocomplete({
        input: projectInput,
        type: 'project',
        boxId: 'project_suggestions'
    });

    attachAutocomplete({
        input: clientInput,
        type: 'client',
        boxId: 'client_suggestions'
    });

    attachAutocomplete({
        input: categoryInput,
        type: 'category',
        boxId: 'category_suggestions'
    });

    // -----------------------------
    // Load more entries
    // -----------------------------
    let offset = entriesTbody ? entriesTbody.querySelectorAll('tr').length : 0;

    loadMoreBtn?.addEventListener('click', async () => {
        const projectName = encodeURIComponent(projectInput?.value.trim() || '');
        const clientName = encodeURIComponent(clientInput?.value.trim() || '');
        const categoryName = encodeURIComponent(categoryInput?.value.trim() || '');
        const startDate = encodeURIComponent(startInput?.value || '');
        const endDate = encodeURIComponent(endInput?.value || '');

        try {
            const res = await fetch(
                `entries_ajax.php?offset=${offset}&project_name=${projectName}&client_name=${clientName}&category_name=${categoryName}&start_date=${startDate}&end_date=${endDate}`
            );

            if (!res.ok) {
                throw new Error(`HTTP ${res.status}`);
            }

            const html = await res.text();

            if (!entriesTbody) return;

            if (!html.trim()) {
                loadMoreBtn.style.display = 'none';
                return;
            }

            entriesTbody.insertAdjacentHTML('beforeend', html);
            offset += 25;
            attachTimersIn(entriesTbody);
        } catch (err) {
            console.error(err);
        }
    });

    // -----------------------------
    // Export form
    // -----------------------------
    const exportForm = document.getElementById('export_form');
    const exportScopeInput = document.getElementById('export_scope');
    const exportVisibleIdsInput = document.getElementById('export_visible_ids');

    exportForm?.querySelectorAll('button[type="submit"][data-export-scope]').forEach((button) => {
        button.addEventListener('click', function () {
            if (exportScopeInput) {
                exportScopeInput.value = this.dataset.exportScope || 'filtered';
            }
        });
    });

    exportForm?.addEventListener('submit', () => {
        const exportProject = document.getElementById('export_project_name');
        const exportClient = document.getElementById('export_client_name');
        const exportCategory = document.getElementById('export_category_name');
        const exportStart = document.getElementById('export_start_date');
        const exportEnd = document.getElementById('export_end_date');

        if (exportProject) exportProject.value = projectInput?.value || '';
        if (exportClient) exportClient.value = clientInput?.value || '';
        if (exportCategory) exportCategory.value = categoryInput?.value || '';
        if (exportStart) exportStart.value = startInput?.value || '';
        if (exportEnd) exportEnd.value = endInput?.value || '';

        if (exportScopeInput?.value === 'visible') {
            const visibleIds = Array.from(
                document.querySelectorAll('#entries_tbody tr[data-entry-id]')
            ).map((row) => row.dataset.entryId).filter(Boolean);

            if (exportVisibleIdsInput) {
                exportVisibleIdsInput.value = visibleIds.join(',');
            }
        } else if (exportVisibleIdsInput) {
            exportVisibleIdsInput.value = '';
        }
    });

    // -----------------------------
    // Optional autofocus for stopped timer note flow
    // -----------------------------
    const stoppedNotice = document.querySelector('[data-focus-comment="true"]');
    const commentField = document.getElementById('comment');

    if (stoppedNotice && commentField) {
        commentField.focus();
    }

    // -----------------------------
    // Delete account modal guardrails
    // -----------------------------
    const deleteConfirmInput = document.getElementById('delete_confirm_text');
    const deleteConfirmCheckbox = document.getElementById('confirm_delete');
    const deleteSubmitBtn = document.getElementById('delete_account_submit');

    function syncDeleteAccountSubmitState() {
        if (!deleteSubmitBtn) return;
        const textOk = (deleteConfirmInput?.value || '').trim().toUpperCase() === 'DELETE';
        const checked = !!deleteConfirmCheckbox?.checked;
        deleteSubmitBtn.disabled = !(textOk && checked);
    }

    deleteConfirmInput?.addEventListener('input', syncDeleteAccountSubmitState);
    deleteConfirmCheckbox?.addEventListener('change', syncDeleteAccountSubmitState);
    syncDeleteAccountSubmitState();

    // -----------------------------
    // Navbar hover dropdown (Bootstrap)
    // -----------------------------
    // document.querySelectorAll('.dropdown').forEach(dropdown => {
    //     let timeout;

    //     dropdown.addEventListener('mouseenter', () => {
    //         clearTimeout(timeout);
    //         const toggle = dropdown.querySelector('[data-bs-toggle="dropdown"]');
    //         const menu = dropdown.querySelector('.dropdown-menu');

    //         if (toggle && menu) {
    //             const instance = bootstrap.Dropdown.getOrCreateInstance(toggle);
    //             instance.show();
    //         }
    //     });

    //     dropdown.addEventListener('mouseleave', () => {
    //         timeout = setTimeout(() => {
    //             const toggle = dropdown.querySelector('[data-bs-toggle="dropdown"]');

    //             if (toggle) {
    //                 const instance = bootstrap.Dropdown.getOrCreateInstance(toggle);
    //                 instance.hide();
    //             }
    //         }, 150); // small delay prevents flicker
    //     });
    // });

    // // Enable hover dropdowns only on larger screens, as they can be hard to use on mobile
    // if (window.matchMedia('(min-width: 992px)').matches) {
    //     document.querySelectorAll('.dropdown').forEach(dropdown => {
    //         let timeout;

    //         dropdown.addEventListener('mouseenter', () => {
    //             clearTimeout(timeout);
    //             const toggle = dropdown.querySelector('[data-bs-toggle="dropdown"]');
    //             const instance = bootstrap.Dropdown.getOrCreateInstance(toggle);
    //             instance.show();
    //         });

    //         dropdown.addEventListener('mouseleave', () => {
    //             timeout = setTimeout(() => {
    //                 const toggle = dropdown.querySelector('[data-bs-toggle="dropdown"]');
    //                 const instance = bootstrap.Dropdown.getOrCreateInstance(toggle);
    //                 instance.hide();
    //             }, 150);
    //         });
    //     });
    // }
});
