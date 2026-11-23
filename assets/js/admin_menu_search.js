(function () {
    var form = document.getElementById('menuSearchForm');
    var input = document.getElementById('menuSearch');
    var results = document.getElementById('menuResults');
    var status = document.getElementById('menuSearchStatus');
    var clearLink = document.getElementById('clearMenuSearch');
    var pagination = document.getElementById('menuPagination');
    var previousPage = document.getElementById('menuPreviousPage');
    var nextPage = document.getElementById('menuNextPage');
    var pageStatus = document.getElementById('menuPageStatus');

    if (!form || !input || !results || !status || !clearLink
        || !pagination || !previousPage || !nextPage || !pageStatus) {
        return;
    }

    var timer;
    var controller;

    function addCell(row, text) {
        var cell = document.createElement('td');
        cell.textContent = text;
        row.appendChild(cell);
    }

    function renderItems(items) {
        var fragment = document.createDocumentFragment();

        if (items.length === 0) {
            var emptyRow = document.createElement('tr');
            var emptyCell = document.createElement('td');
            emptyCell.colSpan = 5;
            emptyCell.className = 'muted';
            emptyCell.textContent = 'No menu items found.';
            emptyRow.appendChild(emptyCell);
            fragment.appendChild(emptyRow);
        }

        items.forEach(function (item) {
            var row = document.createElement('tr');
            addCell(row, item.name);
            addCell(row, item.description);
            addCell(row, item.price + ' RWF');
            addCell(row, item.has_image ? 'Available' : 'None');

            var actionCell = document.createElement('td');
            var editLink = document.createElement('a');
            editLink.className = 'button';
            editLink.href = 'menu_edit?id=' + encodeURIComponent(item.id);
            editLink.textContent = 'Edit';
            actionCell.appendChild(editLink);
            row.appendChild(actionCell);
            fragment.appendChild(row);
        });

        results.replaceChildren(fragment);
    }

    function updatePageLink(link, query, page, disabled) {
        var url = new URL(window.location.href);
        if (query) {
            url.searchParams.set('q', query);
        } else {
            url.searchParams.delete('q');
        }
        if (page > 1) {
            url.searchParams.set('page', String(page));
        } else {
            url.searchParams.delete('page');
        }
        link.href = url.pathname + url.search + url.hash;
        link.dataset.page = String(page);
        link.setAttribute('aria-disabled', disabled ? 'true' : 'false');
        if (disabled) {
            link.setAttribute('tabindex', '-1');
        } else {
            link.removeAttribute('tabindex');
        }
    }

    function renderPagination(page, pages, query) {
        pagination.hidden = pages <= 1;
        pageStatus.textContent = 'Page ' + page + ' of ' + pages;
        updatePageLink(previousPage, query, Math.max(1, page - 1), page <= 1);
        updatePageLink(nextPage, query, Math.min(pages, page + 1), page >= pages);
    }

    async function searchMenu(requestedPage) {
        var query = input.value.trim();
        var page = requestedPage || 1;
        clearLink.hidden = query === '';
        if (controller) {
            controller.abort();
        }
        controller = new AbortController();
        status.textContent = 'Searching...';

        var url = new URL(window.location.href);
        if (query) {
            url.searchParams.set('q', query);
        } else {
            url.searchParams.delete('q');
        }
        if (page > 1) {
            url.searchParams.set('page', String(page));
        } else {
            url.searchParams.delete('page');
        }
        window.history.replaceState({}, '', url);

        try {
            var searchUrl = new URL('menu_search', window.location.href);
            searchUrl.searchParams.set('q', query);
            searchUrl.searchParams.set('page', String(page));
            var response = await fetch(searchUrl, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                signal: controller.signal
            });
            if (!response.ok) {
                throw new Error('Search request failed with HTTP ' + response.status);
            }

            var data = await response.json();
            if (!Array.isArray(data.items) || typeof data.total !== 'number'
                || typeof data.page !== 'number' || typeof data.pages !== 'number'
                || typeof data.limit !== 'number') {
                throw new Error('The search response was invalid.');
            }

            renderItems(data.items);
            var firstItem = data.total === 0 ? 0 : ((data.page - 1) * data.limit) + 1;
            var lastItem = Math.min(data.page * data.limit, data.total);
            status.textContent = data.total === 0
                ? '0 items found.'
                : 'Showing ' + firstItem + '-' + lastItem + ' of ' + data.total + ' items.';
            renderPagination(data.page, data.pages, query);
        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }
            console.error(error);
            status.textContent = 'Search failed. Please try again.';
        }
    }

    input.addEventListener('input', function () {
        window.clearTimeout(timer);
        timer = window.setTimeout(function () { searchMenu(1); }, 250);
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        window.clearTimeout(timer);
        searchMenu(1);
    });

    pagination.addEventListener('click', function (event) {
        var link = event.target.closest('a[data-page]');
        if (!link) {
            return;
        }
        event.preventDefault();
        if (link.getAttribute('aria-disabled') === 'true') {
            return;
        }
        window.clearTimeout(timer);
        searchMenu(Number(link.dataset.page));
    });

    clearLink.addEventListener('click', function (event) {
        event.preventDefault();
        input.value = '';
        window.clearTimeout(timer);
        searchMenu(1);
        input.focus();
    });
})();
