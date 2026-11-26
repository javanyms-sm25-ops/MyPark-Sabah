<?php

function ajax_table_fragment(): ?string {
    $id = $_GET['_table'] ?? null;
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && is_string($id)
        && preg_match('/\A[a-z][a-z\d-]{0,49}\z/', $id) ? $id : null;
}

function ajax_table_query(): array {
    $query = array_filter($_GET, 'is_string');
    unset($query['_table']);
    return $query;
}

function ajax_table_fold(string $value): string {
    return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
}

function ajax_table_compare(int|float|string|null $left, int|float|string|null $right, string $direction): int {
    if ($left === null || $right === null) return $left === $right ? 0 : ($left === null ? 1 : -1);
    $order = is_numeric($left) && is_numeric($right) ? $left <=> $right
        : strnatcmp(ajax_table_fold((string)$left), ajax_table_fold((string)$right));
    return $direction === 'desc' ? -$order : $order;
}

function ajax_table_page(array $rows, mixed $page, mixed $size): array {
    $size = filter_var($size, FILTER_VALIDATE_INT);
    if (!in_array($size, [10, 25, 50], true)) $size = 10;
    $total = count($rows);
    $pages = max(1, (int)ceil($total / $size));
    $page = min($pages, max(1, (int)filter_var($page, FILTER_VALIDATE_INT)));
    $start = ($page - 1) * $size;
    return ['rows' => array_slice($rows, $start, $size), 'total' => $total, 'page' => $page, 'pages' => $pages,
        'size' => $size, 'first' => $total ? $start + 1 : 0, 'last' => min($total, $start + $size)];
}

/** Columns name the visible, sortable fields. Supply numbers or timestamps for typed sorting. */
function ajax_table_view(string $id, array $rows, array $columns, array $options = []): array {
    $prefix = $id . '_';
    $query = ajax_table_query();
    $search = trim($query[$prefix . 'q'] ?? '');
    $filter = $options['filter'] ?? null;
    $selected = $query[$prefix . 'filter'] ?? ($filter['default'] ?? '');
    if (!isset($filter['choices'][$selected])) $selected = $filter['default'] ?? '';
    $order = $query[$prefix . 'sort'] ?? $query[$prefix . 'order'] ?? ($options['order'] ?? '');
    [$sort, $direction] = array_pad(explode(':', $order, 2), 2, '');
    if (!isset($columns[$sort]) || $sort === 'actions') $sort = '';
    $direction = $direction === 'desc' ? 'desc' : 'asc';
    $needle = ajax_table_fold($search);
    $valuesFor = $options['values'] ?? static fn(array $row): array => $row;
    $results = [];
    foreach ($rows as $row) {
        $values = $valuesFor($row);
        if ($selected !== '' && (string)($values[$filter['field']] ?? '') !== $selected) continue;
        if ($needle !== '') {
            $searchable = array_intersect_key($values, $columns);
            if ($filter && isset($filter['choices'][$values[$filter['field']] ?? ''])) {
                $searchable[] = t($filter['choices'][$values[$filter['field']] ?? '']);
            }
            if (!str_contains(ajax_table_fold(implode(' ', $searchable)), $needle)) continue;
        }
        $results[] = ['row' => $row, 'value' => $values[$sort] ?? null];
    }
    if ($sort !== '') {
        usort($results, static fn(array $a, array $b): int => ajax_table_compare($a['value'], $b['value'], $direction));
    }
    unset($query[$prefix . 'sort'], $query[$prefix . 'order'], $query[$prefix . 'page'],
        $query[$prefix . 'q'], $query[$prefix . 'filter'], $query[$prefix . 'size']);
    $order = $sort === '' ? '' : "$sort:$direction";
    return ajax_table_page(array_column($results, 'row'), $_GET[$prefix . 'page'] ?? ($options['page'] ?? 1), $_GET[$prefix . 'size'] ?? 10)
        + compact('id', 'prefix', 'columns', 'query', 'search', 'filter', 'selected', 'order', 'sort', 'direction')
        + ['search_label' => $options['search_label'] ?? 'Search records', 'numeric' => $options['numeric'] ?? [],
            'empty' => $rows && ($search !== '' || $selected !== '') ? 'No matching records' : ($options['empty'] ?? 'No records yet.')];
}

function ajax_table_search_params(array $view): array {
    $prefix = $view['prefix'];
    $params = $view['query'] + [$prefix . 'size' => $view['size']];
    if ($view['search'] !== '') $params[$prefix . 'q'] = $view['search'];
    if ($view['filter']) $params[$prefix . 'filter'] = $view['selected'];
    if ($view['order'] !== '') $params[$prefix . 'order'] = $view['order'];
    return $params;
}

function ajax_table_url(array $view, array $changes): string {
    return ($_SERVER['SCRIPT_NAME'] ?? '') . '?' . http_build_query(array_replace(ajax_table_search_params($view), $changes));
}

function ajax_table_begin(array $view): void {
    if (ajax_table_fragment() === $view['id']) {
        // The database request buffer holds any preceding page chrome.
        ob_clean();
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: private, no-store');
        header('X-MyPark-Table: ' . $view['id']);
    }
    $id = $view['id'];
    $prefix = $view['prefix'];
    ?>
    <div class="ajax-table" data-ajax-table="<?= e($id) ?>">
      <form method="get" action="<?= e($_SERVER['SCRIPT_NAME'] ?? '') ?>" id="filter-<?= e($id) ?>" class="ajax-table-tools" data-table-form>
        <?php foreach ($view['query'] as $key => $value): ?>
          <input type="hidden" name="<?= e($key) ?>" value="<?= e($value) ?>">
        <?php endforeach; ?>
        <input type="hidden" name="<?= e($prefix) ?>order" value="<?= e($view['order']) ?>" data-table-order>
        <label class="ajax-table-search"><span class="validation-sr-only"><?= te($view['search_label']) ?></span>
          <input type="search" maxlength="150" name="<?= e($prefix) ?>q" value="<?= e($view['search']) ?>" placeholder="<?= te($view['search_label']) ?>" data-table-search>
        </label>
        <?php if ($view['filter']): ?>
          <label><span class="validation-sr-only"><?= te($view['filter']['label']) ?></span>
            <select name="<?= e($prefix) ?>filter" data-table-change>
              <?php foreach ($view['filter']['choices'] as $value => $label): ?>
                <option value="<?= e($value) ?>" <?= (string)$value === $view['selected'] ? 'selected' : '' ?>><?= te($label) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        <?php endif; ?>
        <button type="submit" class="btn btn-secondary"><?= te('Search') ?></button>
        <label class="ajax-table-size"><?= te('Rows per page') ?>
          <select name="<?= e($prefix) ?>size" data-table-change>
            <?php foreach ([10, 25, 50] as $size): ?><option <?= $size === $view['size'] ? 'selected' : '' ?>><?= $size ?></option><?php endforeach; ?>
          </select>
        </label>
      </form>
      <p class="validation-sr-only" aria-live="polite" data-table-loading></p>
      <div class="ajax-table-error" data-table-error hidden><span role="alert"><?= te('The table could not be updated.') ?></span>
        <button type="button" class="btn btn-secondary" data-table-retry><?= te('Retry') ?></button>
      </div>
      <div class="ajax-table-results" data-table-results data-current-page="<?= $view['page'] ?>">
    <?php
}

function ajax_table_head(array $view): void {
    ?>
    <thead><tr>
      <?php foreach ($view['columns'] as $field => $label): ?>
        <?php if ($field === 'actions'): ?><th scope="col" data-column="actions"><?= te($label) ?></th><?php continue; endif; ?>
        <?php $direction = $view['sort'] === $field && $view['direction'] === 'asc' ? 'desc' : 'asc'; ?>
        <th scope="col" data-column="<?= e($field) ?>"<?= in_array($field, $view['numeric'], true) ? ' class="ajax-table-number"' : '' ?><?= $view['sort'] === $field ? ' aria-sort="' . ($view['direction'] === 'asc' ? 'ascending' : 'descending') . '"' : '' ?>>
          <button type="submit" form="filter-<?= e($view['id']) ?>" class="ajax-table-sort" name="<?= e($view['prefix']) ?>sort"
                  value="<?= e("$field:$direction") ?>" data-table-sort="<?= e($field) ?>">
            <?= te($label) ?>
            <svg viewBox="0 0 16 16" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.6"
                 stroke-linecap="round" stroke-linejoin="round" focusable="false" aria-hidden="true">
              <path class="ajax-sort-up" d="m4 6 4-4 4 4"/><path class="ajax-sort-down" d="m4 10 4 4 4-4"/>
            </svg>
          </button>
        </th>
      <?php endforeach; ?>
    </tr></thead>
    <?php
}

function ajax_table_empty(array $view): void {
    if (!$view['rows']) echo '<tr><td class="ajax-table-empty" colspan="' . count($view['columns']) . '">' . te($view['empty']) . '</td></tr>';
}

function ajax_table_end(array $view): void {
    $current = $view['page'];
    $pages = $view['pages'];
    $nearby = array_unique([1, max(1, $current - 1), $current, min($pages, $current + 1), $pages]);
    ?>
        <div class="ajax-table-footer">
          <span data-table-count role="status"><?= $view['total'] ? te('Showing {first}–{last} of {total} results', [
              'first' => $view['first'], 'last' => $view['last'], 'total' => $view['total'],
          ]) : te('0 results') ?></span>
          <?php if ($pages > 1): ?><nav class="ajax-table-pages" aria-label="<?= te('Pagination') ?>">
            <?php $pageUrl = static fn(int $page) => e(ajax_table_url($view, [$view['prefix'] . 'page' => $page])); ?>
            <?php if ($current > 1): ?><a href="<?= $pageUrl($current - 1) ?>" data-table-page><?= te('Previous') ?></a>
            <?php else: ?><span aria-disabled="true"><?= te('Previous') ?></span><?php endif; ?>
            <?php $previous = 0; foreach ($nearby as $number): ?>
              <?php if ($number - $previous > 1): ?><span aria-hidden="true">…</span><?php endif; ?>
              <a href="<?= $pageUrl($number) ?>" data-table-page <?= $number === $current ? 'aria-current="page"' : '' ?>><?= $number ?></a>
            <?php $previous = $number; endforeach; ?>
            <?php if ($current < $pages): ?><a href="<?= $pageUrl($current + 1) ?>" data-table-page><?= te('Next') ?></a>
            <?php else: ?><span aria-disabled="true"><?= te('Next') ?></span><?php endif; ?>
          </nav><?php endif; ?>
        </div>
      </div>
    </div>
    <?php
    if (ajax_table_fragment() === $view['id']) exit;
}
