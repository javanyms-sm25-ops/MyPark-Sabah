(() => {
  'use strict';

  const translate = window.MyParkLocale?.text ?? (message => message);

  function createRequest() {
    let current;
    return {
      cancel() {
        current?.abort();
        current = null;
      },
      async run(load, success, error, setBusy) {
        current?.abort();
        const request = new AbortController();
        current = request;
        setBusy(true);
        try {
          const result = await load(request.signal);
          if (current === request) success(result);
        } catch (failure) {
          if (current === request && failure.name !== 'AbortError') error(failure);
        } finally {
          if (current === request) {
            current = null;
            setBusy(false);
          }
        }
      }
    };
  }

  function pageLabel(page) {
    return page.total ? translate('Showing {first}–{last} of {total} results', {
      first: page.first, last: page.last, total: page.total
    }) : translate('0 results');
  }

  window.MyParkTableRequest = { create: createRequest, pageLabel };

  // Make room below a page-size menu opened near the viewport edge.
  document.addEventListener('focusin', event => {
    if (!event.target.matches('.table-footer .table-page-size select option')
        || !CSS.supports('selector(::picker(select))')) return;
    const select = event.target.closest('select');
    requestAnimationFrame(() => {
      if (!select.matches(':open')) return;
      const last = select.options[select.options.length - 1];
      const viewport = window.visualViewport;
      const bottom = viewport ? viewport.offsetTop + viewport.height : window.innerHeight;
      const overflow = last.getBoundingClientRect().bottom + 8 - bottom;
      if (overflow > 0) window.scrollBy({ top: Math.ceil(overflow), behavior: 'instant' });
    });
  });

  document.querySelectorAll('[data-ajax-table]').forEach(table => {
    const id = table.dataset.ajaxTable;
    const prefix = id + '_';
    const form = table.querySelector('[data-table-form]');
    const search = form.querySelector('[data-table-search]');
    const order = form.querySelector('[data-table-order]');
    const failure = table.querySelector('[data-table-error]');
    const loading = table.querySelector('[data-table-loading]');
    const request = createRequest();
    let results = table.querySelector('[data-table-results]');
    let timer, retry;

    function setBusy(busy) {
      results.setAttribute('aria-busy', String(busy));
      loading.textContent = busy ? translate('Refreshing records…') : '';
    }

    function withTableParams(params) {
      const url = new URL(window.location.href);
      for (const key of [...url.searchParams.keys()]) {
        if (key.startsWith(prefix) || key === '_table') url.searchParams.delete(key);
      }
      for (const [key, value] of params) {
        if (key.startsWith(prefix) && (value !== '' || key === prefix + 'filter')) url.searchParams.set(key, value);
      }
      return url;
    }

    function refresh(params, focusSort, focusPage = false) {
      clearTimeout(timer);
      failure.hidden = true;
      retry = () => refresh(params, focusSort, focusPage);
      const url = withTableParams(params);
      url.searchParams.set('_table', id);
      request.run(async signal => {
        const response = await fetch(url, { credentials: 'same-origin', cache: 'no-store', signal });
        if (!response.ok || response.headers.get('X-MyPark-Table') !== id) {
          throw new Error(translate('The table could not be updated.'));
        }
        const documentFragment = new DOMParser().parseFromString(await response.text(), 'text/html');
        const nextResults = documentFragment.querySelector('[data-table-results]');
        const nextOrder = documentFragment.querySelector('[data-table-order]');
        if (!nextResults || !nextOrder) throw new Error(translate('The table could not be updated.'));
        return { nextResults, nextOrder };
      }, ({ nextResults, nextOrder }) => {
        const active = document.activeElement;
        const restore = results.contains(active);
        results.replaceWith(nextResults);
        results = nextResults;
        order.value = nextOrder.value;
        const current = withTableParams(params);
        current.searchParams.delete(prefix + 'sort');
        if (order.value) current.searchParams.set(prefix + 'order', order.value);
        else current.searchParams.delete(prefix + 'order');
        if (results.dataset.currentPage === '1') current.searchParams.delete(prefix + 'page');
        else current.searchParams.set(prefix + 'page', results.dataset.currentPage);
        history.replaceState(history.state, '', current);
        if (restore && focusSort) results.querySelector('[data-table-sort="' + CSS.escape(focusSort) + '"]')?.focus();
        if (restore && focusPage) (results.querySelector('[aria-current="page"]') ?? search).focus();
        if (restore && active.matches('[data-table-size]')) results.querySelector('[data-table-size]')?.focus({ preventScroll: true });
        table.dispatchEvent(new CustomEvent('mypark:table-updated', { bubbles: true }));
      }, () => { failure.hidden = false; }, setBusy);
    }

    function formParams(submitter) {
      const params = new URLSearchParams(new FormData(form));
      if (submitter?.dataset.tableSort) params.set(submitter.name, submitter.value);
      return params;
    }

    form.addEventListener('submit', event => {
      event.preventDefault();
      refresh(formParams(event.submitter), event.submitter?.dataset.tableSort);
    });
    search.addEventListener('input', () => {
      request.cancel();
      setBusy(false);
      clearTimeout(timer);
      timer = setTimeout(() => refresh(formParams()), 300);
    });
    table.addEventListener('change', event => {
      if (event.target.matches('[data-table-change]') && event.target.form === form) refresh(formParams());
    });
    table.addEventListener('click', event => {
      const page = event.target.closest('[data-table-page]');
      if (!page || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
      event.preventDefault();
      const params = formParams();
      params.set(prefix + 'page', new URL(page.href).searchParams.get(prefix + 'page'));
      refresh(params, null, true);
    });
    failure.querySelector('[data-table-retry]').addEventListener('click', () => retry?.());
    table.addEventListener('mypark:table-refresh', () => refresh(window.location.search ? new URLSearchParams(location.search) : formParams()));
  });
})();
