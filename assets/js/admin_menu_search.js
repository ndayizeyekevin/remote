(function () {
    var form = document.getElementById('menuSearchForm');
    var input = document.getElementById('menuSearch');
    var results = document.getElementById('menuResults');
    var status = document.getElementById('menuSearchStatus');
    var clearLink = document.getElementById('clearMenuSearch');
    var pageSizeSelect = document.getElementById('menuPageSize');
    var pagination = document.getElementById('menuPagination');
    var previousPage = document.getElementById('menuPreviousPage');
    var nextPage = document.getElementById('menuNextPage');
    var pageStatus = document.getElementById('menuPageStatus');
    var pageNumbers = document.getElementById('menuPageNumbers');

    if (!form || !input || !results || !status || !clearLink || !pageSizeSelect
        || !pagination || !previousPage || !nextPage || !pageStatus || !pageNumbers) {
        return;
    }

    var timer;
    var controller;
    var defaultPageSize = 25;

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

    function pageUrl(query, page, pageSize) {
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
        if (pageSize !== defaultPageSize) {
            url.searchParams.set('per_page', String(pageSize));
        } else {
            url.searchParams.delete('per_page');
        }
        return url.pathname + url.search + url.hash;
    }

    function selectedPageSize() {
        return pageSizeSelect.value === 'all' ? 'all' : Number(pageSizeSelect.value);
    }

    function updatePageLink(link, query, page, pageSize, disabled) {
        link.href = pageUrl(query, page, pageSize);
        link.dataset.page = String(page);
        link.setAttribute('aria-disabled', disabled ? 'true' : 'false');
        if (disabled) {
            link.setAttribute('tabindex', '-1');
        } else {
            link.removeAttribute('tabindex');
        }
    }

    function renderPageNumbers(page, pages, query, pageSize) {
        var visiblePages = [];
        if (pages <= 7) {
            for (var number = 1; number <= pages; number++) {
                visiblePages.push(number);
            }
        } else {
            visiblePages.push(1);
            var windowStart = Math.max(2, page - 1);
            var windowEnd = Math.min(pages - 1, page + 1);
            if (page <= 3) {
                windowEnd = Math.min(pages - 1, 4);
            } else if (page >= pages - 2) {
                windowStart = Math.max(2, pages - 3);
            }
            if (windowStart > 2) {
                visiblePages.push('...');
            }
            for (var current = windowStart; current <= windowEnd; current++) {
                visiblePages.push(current);
            }
            if (windowEnd < pages - 1) {
                visiblePages.push('...');
            }
            visiblePages.push(pages);
        }

        var fragment = document.createDocumentFragment();
        visiblePages.forEach(function (number) {
            if (number === '...') {
                var ellipsis = document.createElement('span');
                ellipsis.className = 'pagination-ellipsis';
                ellipsis.setAttribute('aria-hidden', 'true');
                ellipsis.textContent = '...';
                fragment.appendChild(ellipsis);
                return;
            }

            var link = document.createElement('a');
            link.className = 'pagination-page';
            link.href = pageUrl(query, number, pageSize);
            link.dataset.page = String(number);
            link.textContent = String(number);
            link.setAttribute('aria-label', 'Page ' + number);
            if (number === page) {
                link.classList.add('is-current');
                link.setAttribute('aria-current', 'page');
            }
            fragment.appendChild(link);
        });
        pageNumbers.replaceChildren(fragment);
    }

    function renderPagination(page, pages, query, pageSize) {
        pagination.hidden = pages <= 1;
        pageStatus.textContent = 'Page ' + page + ' of ' + pages;
        updatePageLink(previousPage, query, Math.max(1, page - 1), pageSize, page <= 1);
        updatePageLink(nextPage, query, Math.min(pages, page + 1), pageSize, page >= pages);
        renderPageNumbers(page, pages, query, pageSize);
    }

    async function searchMenu(requestedPage) {
        var query = input.value.trim();
        var page = requestedPage || 1;
        var pageSize = selectedPageSize();
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
        if (pageSize !== defaultPageSize) {
            url.searchParams.set('per_page', String(pageSize));
        } else {
            url.searchParams.delete('per_page');
        }
        window.history.replaceState({}, '', url);

        try {
            var searchUrl = new URL('menu_search', window.location.href);
            searchUrl.searchParams.set('q', query);
            searchUrl.searchParams.set('page', String(page));
            searchUrl.searchParams.set('per_page', String(pageSize));
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
                || typeof data.limit !== 'number' || typeof data.page_size !== 'number') {
                throw new Error('The search response was invalid.');
            }

            window.history.replaceState({}, '', pageUrl(query, data.page, pageSize));
            renderItems(data.items);
            var firstItem = data.total === 0 ? 0 : ((data.page - 1) * data.page_size) + 1;
            var lastItem = Math.min(data.page * data.page_size, data.total);
            status.textContent = data.total === 0
                ? '0 items found.'
                : 'Showing ' + firstItem + '-' + lastItem + ' of ' + data.total + ' items.';
            renderPagination(data.page, data.pages, query, pageSize);
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

    pageSizeSelect.addEventListener('change', function () {
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
