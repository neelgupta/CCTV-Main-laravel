<script>
(function () {
    'use strict';

    const CHEVRON_SVG = '<svg class="ml-3 h-4 w-4 shrink-0 text-slate-400 transition-transform duration-200" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.25a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z" clip-rule="evenodd"/></svg>';

    let activeDropdown = null;

    function closeAllDropdowns() {
        if (activeDropdown) {
            activeDropdown.close();
            activeDropdown = null;
        }
    }

    document.addEventListener('click', function (e) {
        if (activeDropdown && !activeDropdown.wrapper.contains(e.target) && !activeDropdown.menu.contains(e.target)) {
            closeAllDropdowns();
        }
    });

    window.addEventListener('resize', function () {
        if (activeDropdown) activeDropdown.updatePosition();
    });

    window.addEventListener('scroll', function (e) {
        if (activeDropdown) {
            if (activeDropdown.menu && activeDropdown.menu.contains(e.target)) return;
            activeDropdown.updatePosition();
        }
    }, true);

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && activeDropdown) {
            const btn = activeDropdown.button;
            closeAllDropdowns();
            if (btn) btn.focus();
        }
    });

    function enhanceSelect(select) {
        if (!select || select.dataset.enhancedCustomSelect === '1') return;
        if (select.multiple) return;
        if (select.dataset.native === 'true' || select.dataset.noCustomSelect === '1') return;
        if (select.classList.contains('hidden') || select.classList.contains('sr-only')) return;
        if (select.getAttribute('x-ref') === 'hiddenSelect') return;
        if (select.closest('.custom-select-wrapper')) return;
        if (select.classList.contains('swal2-select') || select.closest('.swal2-container') || select.closest('.swal2-popup') || select.closest('[class*="swal2-"]')) return;
        if (select.closest('[x-data*="searchable-select"]') || select.closest('[x-data*="loadOptionsFromSelect"]')) return;
        if (select.closest('[x-data*="client-picker"]') || select.closest('[x-data*="ticketAssigneeSelect"]')) return;

        select.dataset.enhancedCustomSelect = '1';

        const isCompact = select.classList.contains('py-1') || select.classList.contains('py-1.5') || select.classList.contains('text-xs');
        const buttonPadding = isCompact ? 'px-3 py-1.5 text-xs' : 'px-4 py-3 text-sm';
        const buttonRound = isCompact ? 'rounded-lg' : 'rounded-xl';

        // Visually hide the native select while keeping it focusable/validatable in DOM
        select.style.setProperty('position', 'absolute', 'important');
        select.style.setProperty('opacity', '0', 'important');
        select.style.setProperty('pointer-events', 'none', 'important');
        select.style.setProperty('width', '1px', 'important');
        select.style.setProperty('height', '1px', 'important');
        select.style.setProperty('margin', '0', 'important');
        select.style.setProperty('padding', '0', 'important');
        select.style.setProperty('z-index', '-1', 'important');
        select.tabIndex = -1;

        // Create outer wrapper
        const wrapper = document.createElement('div');
        let widthClass = 'w-full';
        if (select.classList.contains('w-1/2') || select.classList.contains('sm:w-1/2')) {
            widthClass = 'w-full sm:w-1/2';
        }
        const hasNoMargin = select.closest('td, th, .flex') || select.classList.contains('mt-0') || isCompact;
        wrapper.className = `custom-select-wrapper relative ${hasNoMargin ? '' : 'mt-1'} block ${widthClass}`;

        // Trigger button
        const button = document.createElement('button');
        button.type = 'button';
        button.className = `custom-select-trigger flex w-full items-center justify-between ${buttonRound} border border-gray-300 bg-white ${buttonPadding} text-left shadow-sm transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500`;
        if (select.disabled) {
            button.disabled = true;
            button.classList.add('cursor-not-allowed', 'bg-slate-100', 'text-slate-500');
        }

        const labelSpan = document.createElement('span');
        labelSpan.className = 'min-w-0 truncate select-label font-normal text-slate-800';

        button.appendChild(labelSpan);
        button.insertAdjacentHTML('beforeend', CHEVRON_SVG);
        const chevron = button.querySelector('svg');

        // Dropdown panel
        const menu = document.createElement('div');
        menu.className = `custom-select-menu hidden absolute z-50 mt-2 w-full overflow-hidden ${buttonRound} border border-slate-200 bg-white shadow-xl`;

        // Search container
        const searchWrap = document.createElement('div');
        searchWrap.className = 'border-b border-slate-100 p-2.5 bg-slate-50/50';
        const searchInput = document.createElement('input');
        searchInput.type = 'search';
        searchInput.autocomplete = 'off';
        searchInput.className = 'block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500';
        searchInput.placeholder = 'Search...';
        searchWrap.appendChild(searchInput);
        menu.appendChild(searchWrap);

        // Options container (showing exactly 10 records in single view before scrollbar: max-h-[410px])
        const listWrap = document.createElement('div');
        listWrap.className = 'custom-select-options max-h-[410px] overflow-y-auto overscroll-contain py-1';
        menu.appendChild(listWrap);

        const emptyMessage = document.createElement('div');
        emptyMessage.className = 'hidden px-4 py-3 text-sm text-slate-500';
        emptyMessage.textContent = 'No matching options found.';
        listWrap.appendChild(emptyMessage);

        // Insert into DOM
        select.parentNode.insertBefore(wrapper, select);
        wrapper.appendChild(button);
        wrapper.appendChild(menu);
        wrapper.appendChild(select);

        const dropdownController = {
            wrapper,
            button,
            menu,
            isOpen: false,
            updatePosition() {
                if (!this.isOpen || !button.isConnected) return;
                const rect = button.getBoundingClientRect();
                const viewportHeight = window.innerHeight;
                const viewportWidth = window.innerWidth;

                // Close if button is scrolled out of viewport
                if (rect.bottom < 0 || rect.top > viewportHeight) {
                    this.close();
                    return;
                }

                const menuWidth = Math.min(viewportWidth - 20, Math.max(rect.width, 260));
                let left = rect.left;
                if (left + menuWidth > viewportWidth - 10) {
                    left = Math.max(10, viewportWidth - menuWidth - 10);
                }

                menu.style.position = 'fixed';
                menu.style.zIndex = '99999';
                menu.style.left = `${left}px`;
                menu.style.width = `${menuWidth}px`;

                const spaceBelow = viewportHeight - rect.bottom;
                const spaceAbove = rect.top;
                const estimatedMenuHeight = Math.min(menu.offsetHeight || 320, 400);

                // Open upwards if not enough room below and more room above
                const openUpwards = spaceBelow < estimatedMenuHeight && spaceAbove > spaceBelow;

                if (openUpwards) {
                    menu.style.top = 'auto';
                    menu.style.bottom = `${viewportHeight - rect.top + 6}px`;
                    menu.style.marginTop = '0';
                    menu.style.marginBottom = '0';
                    const availableAbove = Math.max(140, spaceAbove - 20);
                    if (listWrap) {
                        listWrap.style.maxHeight = `${Math.min(380, availableAbove - 60)}px`;
                    }
                } else {
                    menu.style.bottom = 'auto';
                    menu.style.top = `${rect.bottom + 6}px`;
                    menu.style.marginTop = '0';
                    menu.style.marginBottom = '0';
                    const availableBelow = Math.max(140, spaceBelow - 20);
                    if (listWrap) {
                        listWrap.style.maxHeight = `${Math.min(380, availableBelow - 60)}px`;
                    }
                }
            },
            open() {
                if (button.disabled || select.disabled) return;
                closeAllDropdowns();
                this.isOpen = true;
                activeDropdown = this;
                wrapper.classList.add('z-30');

                // Keep options and selected state synced
                if (listWrap.querySelectorAll('button[data-option-value]').length !== select.options.length) {
                    renderOptions();
                } else {
                    syncSelectedState();
                }

                // Append menu to body so it escapes all overflow-hidden and overflow-x-auto containers
                if (menu.parentNode !== document.body) {
                    document.body.appendChild(menu);
                }

                menu.classList.remove('hidden');
                if (chevron) chevron.classList.add('rotate-180');
                button.classList.add('ring-2', 'ring-indigo-500', 'border-indigo-500');

                this.updatePosition();

                searchInput.value = '';
                filterOptions('');
                if (searchWrap.style.display !== 'none') {
                    setTimeout(() => searchInput.focus(), 30);
                }
            },
            close() {
                this.isOpen = false;
                wrapper.classList.remove('z-30');
                menu.classList.add('hidden');
                if (chevron) chevron.classList.remove('rotate-180');
                button.classList.remove('ring-2', 'ring-indigo-500', 'border-indigo-500');
                if (menu.parentNode === document.body) {
                    if (wrapper.isConnected) {
                        wrapper.appendChild(menu);
                    } else {
                        menu.remove();
                    }
                }
                if (activeDropdown === this) {
                    activeDropdown = null;
                }
            },
            toggle() {
                if (this.isOpen) {
                    this.close();
                } else {
                    this.open();
                }
            }
        };

        function filterOptions(query) {
            const q = query.trim().toLowerCase();
            const optionButtons = listWrap.querySelectorAll('button[data-option-value]');
            let visibleCount = 0;
            optionButtons.forEach(btn => {
                const text = (btn.dataset.optionText || '').toLowerCase();
                const matches = !q || text.includes(q);
                btn.style.display = matches ? 'flex' : 'none';
                if (matches) visibleCount++;
            });
            emptyMessage.classList.toggle('hidden', visibleCount > 0);
        }

        searchInput.addEventListener('input', function () {
            filterOptions(this.value);
            dropdownController.updatePosition();
        });

        searchInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const firstVisible = Array.from(listWrap.querySelectorAll('button[data-option-value]')).find(b => b.style.display !== 'none');
                if (firstVisible) {
                    firstVisible.click();
                }
            }
        });

        button.addEventListener('click', function (e) {
            e.preventDefault();
            dropdownController.toggle();
        });

        // Click on label focuses/opens custom select
        if (select.id) {
            const label = document.querySelector(`label[for="${select.id}"]`);
            if (label) {
                label.addEventListener('click', function (e) {
                    e.preventDefault();
                    button.focus();
                    dropdownController.open();
                });
            }
        }

        function renderOptions() {
            Array.from(listWrap.querySelectorAll('button[data-option-value]')).forEach(b => b.remove());

            const options = Array.from(select.options);
            searchWrap.style.display = options.length <= 4 ? 'none' : 'block';

            let selectedOption = select.options[select.selectedIndex];
            if (!selectedOption && options.length > 0) {
                selectedOption = options[0];
            }

            const currentText = selectedOption ? (selectedOption.textContent || selectedOption.label || '') : '';
            labelSpan.textContent = currentText || select.getAttribute('placeholder') || 'Select an option...';
            if (!currentText || (selectedOption && selectedOption.value === '')) {
                labelSpan.classList.add('text-slate-500');
                labelSpan.classList.remove('text-slate-800');
            } else {
                labelSpan.classList.remove('text-slate-500');
                labelSpan.classList.add('text-slate-800');
            }

            options.forEach(opt => {
                const optVal = opt.value;
                const optText = opt.textContent || opt.label || '';
                const isSelected = opt.selected;
                const isDisabled = opt.disabled;

                const optButton = document.createElement('button');
                optButton.type = 'button';
                optButton.dataset.optionValue = optVal;
                optButton.dataset.optionText = optText;
                optButton.className = `flex w-full items-center justify-between border-t border-slate-50 px-4 py-2.5 text-left text-sm transition-colors hover:bg-slate-50 ${isSelected ? 'bg-indigo-50 text-indigo-700 font-medium' : 'text-slate-700'}`;
                if (isDisabled) {
                    optButton.disabled = true;
                    optButton.classList.add('opacity-50', 'cursor-not-allowed', 'bg-slate-50');
                }

                const textSpan = document.createElement('span');
                textSpan.className = 'min-w-0 truncate font-medium';
                textSpan.textContent = optText;
                optButton.appendChild(textSpan);

                const badgeSpan = document.createElement('span');
                badgeSpan.className = `selected-badge text-xs font-semibold text-indigo-600 ${isSelected ? '' : 'hidden'}`;
                badgeSpan.textContent = 'Selected';
                optButton.appendChild(badgeSpan);

                optButton.addEventListener('click', function (e) {
                    e.preventDefault();
                    if (isDisabled) return;

                    select.value = optVal;
                    select.dispatchEvent(new Event('input', { bubbles: true }));
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                    if (window.jQuery) {
                        try { window.jQuery(select).trigger('change'); } catch (err) {}
                    }

                    syncSelectedState();
                    dropdownController.close();
                    button.focus();
                });

                listWrap.insertBefore(optButton, emptyMessage);
            });
        }

        function syncSelectedState() {
            const selectedOption = select.options[select.selectedIndex];
            const currentVal = select.value;
            const currentText = selectedOption ? (selectedOption.textContent || selectedOption.label || '') : '';
            labelSpan.textContent = currentText || 'Select an option...';
            if (!currentText || (selectedOption && selectedOption.value === '')) {
                labelSpan.classList.add('text-slate-500');
                labelSpan.classList.remove('text-slate-800');
            } else {
                labelSpan.classList.remove('text-slate-500');
                labelSpan.classList.add('text-slate-800');
            }

            listWrap.querySelectorAll('button[data-option-value]').forEach(btn => {
                const matches = btn.dataset.optionValue === currentVal;
                btn.classList.toggle('bg-indigo-50', matches);
                btn.classList.toggle('text-indigo-700', matches);
                btn.classList.toggle('font-medium', matches);
                btn.classList.toggle('text-slate-700', !matches);
                const badge = btn.querySelector('.selected-badge');
                if (badge) badge.classList.toggle('hidden', !matches);
            });
        }

        select.addEventListener('change', syncSelectedState);
        select.addEventListener('input', syncSelectedState);
        select._syncCustomSelect = syncSelectedState;
        select._renderCustomSelect = renderOptions;

        function syncDisabled() {
            button.disabled = select.disabled;
            button.classList.toggle('cursor-not-allowed', select.disabled);
            button.classList.toggle('bg-slate-100', select.disabled);
            button.classList.toggle('text-slate-500', select.disabled);
        }

        function syncErrors() {
            const hasError = select.classList.contains('border-red-500') || select.classList.contains('border-red-400') || select.classList.contains('error') || select.classList.contains('is-invalid');
            button.classList.toggle('border-red-500', hasError);
            button.classList.toggle('ring-1', hasError);
            button.classList.toggle('ring-red-300', hasError);
        }

        select.addEventListener('invalid', function () {
            button.classList.add('border-red-500', 'ring-1', 'ring-red-300');
        });

        const observer = new MutationObserver(function (mutations) {
            let optionsChanged = false;
            let attrChanged = false;
            mutations.forEach(m => {
                if (m.type === 'childList') optionsChanged = true;
                if (m.type === 'attributes') {
                    attrChanged = true;
                    if (m.attributeName === 'disabled') syncDisabled();
                    if (m.attributeName === 'class') syncErrors();
                }
            });
            if (optionsChanged) {
                renderOptions();
            } else if (attrChanged) {
                syncSelectedState();
            }
        });

        observer.observe(select, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['disabled', 'class', 'value'],
        });

        if (select.form) {
            select.form.addEventListener('reset', function () {
                setTimeout(syncSelectedState, 20);
            });
        }

        renderOptions();
        syncErrors();
        syncDisabled();
    }

    function initAll() {
        document.querySelectorAll('select').forEach(enhanceSelect);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }

    const bodyObserver = new MutationObserver(function (mutations) {
        mutations.forEach(m => {
            m.addedNodes.forEach(node => {
                if (node.nodeType === 1) {
                    if (node.classList && (node.classList.contains('swal2-container') || node.classList.contains('swal2-popup') || (typeof node.matches === 'function' && node.matches('.swal2-container, .swal2-container *')))) {
                        return;
                    }
                    if (node.tagName === 'SELECT') {
                        enhanceSelect(node);
                    } else if (node.querySelectorAll) {
                        node.querySelectorAll('select').forEach(enhanceSelect);
                    }
                }
            });
        });
    });

    if (document.body) {
        bodyObserver.observe(document.body, { childList: true, subtree: true });
    } else {
        document.addEventListener('DOMContentLoaded', () => {
            bodyObserver.observe(document.body, { childList: true, subtree: true });
        });
    }

    window.enhanceCustomSelects = initAll;
    window.enhanceSelect = enhanceSelect;
})();
</script>

