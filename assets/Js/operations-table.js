/* Pure table pipeline, shared by both Operations tables and development tests. */
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
    return { rows: rows.slice(start, start + size), page, pages, total: rows.length,
      label: rows.length ? `Showing ${start + 1}–${Math.min(start + size, rows.length)} of ${rows.length} results` : '0 results' };
  }
  function pageNumbers(page, pages) {
    const chosen = [...new Set([1, pages, page - 1, page, page + 1])].filter(n => n >= 1 && n <= pages).sort((a, b) => a - b);
    const result = [];
    chosen.forEach((n, i) => { if (i && n - chosen[i - 1] > 1) result.push('…'); result.push(n); });
    return result;
  }
  const api = { filterSort, paginate, pageNumbers };
  if (typeof module !== 'undefined' && module.exports) module.exports = api;
  else root.MyParkTableData = api;
}(globalThis));
