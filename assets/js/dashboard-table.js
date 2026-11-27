/* Table filtering, sorting, and paging for the Dashboard and staff portal. */
(function (root) {
  'use strict';
  const collator = new Intl.Collator('en', { sensitivity: 'base', numeric: true });
  const numeric = new Set(['id', 'fee', 'hours', 'created_ts']);
  function filterSort(rows, kind, state) {
    const query = state.q.trim().toLocaleLowerCase('en');
    const fields = kind === 'accounts' ? ['name', 'email'] : ['user', 'plate', 'lot'];
    const filtered = rows.filter(row => {
      if (query && !fields.map(key => row[key]).join(' ').toLocaleLowerCase('en').includes(query)) return false;
      if (state.status && row.status !== state.status) return false;
      if (state.role && row.role !== state.role) return false;
      if (state.location && String(row.lot_id) !== state.location) return false;
      if (state.from && (!row.created_day || row.created_day < state.from)) return false;
      if (state.to && (!row.created_day || row.created_day > state.to)) return false;
      return true;
    });
    return filtered.sort((a, b) => {
      const left = a[state.sort], right = b[state.sort];
      if (left == null || right == null) return left == right ? a.id - b.id : left == null ? 1 : -1;
      const order = numeric.has(state.sort) ? Number(left) - Number(right) : collator.compare(left, right);
      return order ? order * (state.dir === 'asc' ? 1 : -1) : a.id - b.id;
    });
  }
  function paginate(rows, page, size) {
    const pages = Math.max(1, Math.ceil(rows.length / size));
    page = Math.min(Math.max(1, page), pages);
    const start = (page - 1) * size;
    const last = Math.min(start + size, rows.length);
    const label = rows.length
      ? (root.MyParkLocale?.text('Showing {first}–{last} of {total} results', { first: start + 1, last, total: rows.length })
        ?? `Showing ${start + 1}–${last} of ${rows.length} results`)
      : (root.MyParkLocale?.text('0 results') ?? '0 results');
    return { rows: rows.slice(start, start + size), page, pages, total: rows.length, label };
  }
  function pageNumbers(page, pages) {
    const range = (start, length) => Array.from({ length }, (_, index) => start + index);
    if (pages <= 7) return range(1, pages);
    if (page <= 4) return [...range(1, 5), '…', pages];
    if (page >= pages - 3) return [1, '…', ...range(pages - 4, 5)];
    return [1, '…', page - 1, page, page + 1, '…', pages];
  }
  function renderPager(container, { page, pages }, goToPage) {
    const translate = root.MyParkLocale?.text ?? (message => message);
    function button(label, target, direction) {
      const item = container.ownerDocument.createElement('button');
      item.type = 'button';
      item.className = direction ? 'pagination__item pagination__' + direction : 'pagination__item';
      item.textContent = label;
      item.disabled = target < 1 || target > pages;
      item.addEventListener('click', () => {
        if (!item.disabled && target !== page) goToPage(target);
      });
      return item;
    }
    const items = pageNumbers(page, pages).map(number => {
      if (number === '…') {
        const gap = container.ownerDocument.createElement('span');
        gap.className = 'pagination__gap';
        gap.textContent = number;
        gap.setAttribute('aria-hidden', 'true');
        return gap;
      }
      const item = button(String(number), number);
      item.setAttribute('aria-label', root.MyParkLocale?.text('Page {page}', { page: number }) ?? `Page ${number}`);
      if (number === page) item.setAttribute('aria-current', 'page');
      return item;
    });
    container.replaceChildren(
      button(translate('Back'), page - 1, 'back'),
      ...items,
      button(translate('Next'), page + 1, 'next')
    );
  }
  const api = { filterSort, paginate, pageNumbers, renderPager };
  if (typeof module !== 'undefined' && module.exports) module.exports = api;
  else root.MyParkTableData = api;
}(globalThis));
