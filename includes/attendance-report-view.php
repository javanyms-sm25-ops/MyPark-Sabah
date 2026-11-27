<?php

require_once __DIR__ . '/attendance-view.php';
require_once __DIR__ . '/ajax-table.php';

function attendance_staff_options(array $staff): array {
    return array_map(static fn($account) => $account['name'] . ' · ' . attendance_staff_id($account['id']), $staff);
}

function attendance_select(string $name, string $label, array $options, string $selected, ?string $empty = null): void {
    ?>
    <label><?= te($label) ?>
      <select name="<?= e($name) ?>">
        <?php if ($empty !== null): ?><option value=""><?= te($empty) ?></option><?php endif; ?>
        <?php foreach ($options as $value => $text): ?>
          <option value="<?= e($value) ?>" <?= (string)$value === $selected ? 'selected' : '' ?>><?= e($text) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <?php
}

function attendance_report_filter_fields(array $filters, array $staff, array $locations, bool $usePicker, bool $exception): void {
    ?>
    <label for="attendance-report-from"><?= te('Start Date') ?>
      <input type="date" name="from" id="attendance-report-from" value="<?= e($filters['from']) ?>" <?= $usePicker ? 'data-date-range-start' : '' ?>>
    </label>
    <label for="attendance-report-to"><?= te('End Date') ?>
      <input type="date" name="to" id="attendance-report-to" value="<?= e($filters['to']) ?>" <?= $usePicker ? 'data-date-range-end' : '' ?>>
    </label>
    <?php
    if ($usePicker): ?>
      <label><?= te('Search by name or staff ID') ?><input type="search" name="search" maxlength="80" value="<?= e($filters['search']) ?>"></label>
    <?php else:
        attendance_select('employee', 'Employee', attendance_staff_options($staff), $filters['employee'], 'All permitted employees');
    endif;
    $statusLabels = array_map('t', array_intersect_key(ATTENDANCE_LABELS, array_fill_keys(ATTENDANCE_REPORT_STATUSES, true)));
    $moreActive = $filters['location'] !== '' || $filters['status'] !== ''
        || ($exception && $filters['exception'] !== '') || ($usePicker ? $filters['employee'] !== '' : $filters['search'] !== '');
    ?>
    <details class="attendance-details attendance-more-filters" <?= $moreActive ? 'open' : '' ?>>
      <summary><?= te('More filters') ?></summary>
      <div class="attendance-fields">
        <?php if ($usePicker):
            attendance_select('employee', 'Employee', attendance_staff_options($staff), $filters['employee'], 'All permitted employees');
        else: ?>
          <label><?= te('Search by name or staff ID') ?><input type="search" name="search" maxlength="80" value="<?= e($filters['search']) ?>"></label>
        <?php endif;
        attendance_select('location', 'Parking location', array_column($locations, 'name', 'id'), $filters['location'], 'All permitted locations');
        attendance_select('status', 'Attendance status', $statusLabels, $filters['status'], 'All statuses');
        if ($exception) attendance_select('exception', 'Exception type', array_map('t', ATTENDANCE_EXCEPTION_LABELS), $filters['exception'], 'All exception types'); ?>
      </div>
    </details>
    <?php
}

function attendance_report_display(string $key, mixed $value): string {
    if ($value === null) return '—';
    return match ($key) {
        'attendance_rate', 'punctuality_rate' => number_format($value, 1) . '%',
        'hours' => number_format($value, 2),
        'full' => is_string($value) ? t(ATTENDANCE_FULL_LABELS[$value]) : (string)$value,
        default => (string)$value,
    };
}

function attendance_performance_tiles(array $performance): void {
    ?>
    <dl class="attendance-facts attendance-summary">
      <?php foreach (ATTENDANCE_PERFORMANCE_FIELDS as $key => $label): ?>
        <div><dt><?= te($label) ?></dt><dd><?= e(attendance_report_display($key, $performance[$key])) ?></dd></div>
      <?php endforeach; ?>
    </dl>
    <?php
}

function attendance_performance_help(): void {
    ?>
    <details class="attendance-details">
      <summary><?= te('Attendance rules & definitions') ?></summary>
      <p><?= te('Each employee with a scheduled shift is counted once. Checked-in employees include recorded arrivals waiting for review.') ?></p>
      <p><?= te('Attendance rate compares present and absent days after shifts end. Unfinished days, pending reviews, unverified check-ins, approved leave, and excused holidays are excluded.') ?></p>
      <p><?= te('A present day needs a verified check-in for every required shift. Punctuality measures how many present days had all check-ins on time. Recorded hours use paired effective times.') ?></p>
      <p><?= te('Full attendance uses every required shift in the selected scope and completed period. Status and exception filters can narrow the displayed records.') ?></p>
    </details>
    <?php
}

function attendance_review_label(array $row): string {
    if ($row['awaiting_reply']) return t('Clarification requested');
    return match ($row['review_status']) {
        'pending' => t('Pending Review'),
        'approved' => t('Approved'),
        'rejected' => t('Review Rejected'),
        default => '—',
    };
}

function attendance_status_bars(array $items, ?int $scale = null): void {
    $ceiling = $scale ?? max([1, ...array_column($items, 'size')]);
    ?>
    <ul class="attendance-bars">
      <?php foreach ($items as $item): ?>
        <li><span><?= e($item['name']) ?></span><strong><?= e($item['value']) ?></strong>
          <div aria-hidden="true" class="attendance-bar-track"><span style="width:<?= e(round(100 * $item['size'] / $ceiling, 1)) ?>%"></span></div>
        </li>
      <?php endforeach; ?>
    </ul>
    <?php
}

function attendance_report_summary_tiles(array $summary, bool $collapseDetails = false): void {
    $primary = ['scheduled', 'checked_in', 'attendance_rate', 'pending'];
    ?>
    <dl class="attendance-facts attendance-summary">
      <?php foreach ($primary as $key): ?>
        <div class="attendance-key-metric"><dt><?= te(ATTENDANCE_REPORT_METRICS[$key]) ?></dt><dd><?= e(attendance_report_display($key, $summary[$key])) ?></dd></div>
      <?php endforeach; ?>
    </dl>
    <?php if ($collapseDetails): ?>
      <details class="attendance-details">
        <summary><?= te('More attendance metrics') ?></summary>
    <?php endif; ?>
    <dl class="attendance-secondary-metrics">
      <?php foreach (array_diff(array_keys(ATTENDANCE_REPORT_METRICS), $primary) as $key): ?>
        <div><dt><?= te(ATTENDANCE_REPORT_METRICS[$key]) ?></dt><dd><?= e(attendance_report_display($key, $summary[$key])) ?></dd></div>
      <?php endforeach; ?>
    </dl>
    <?php if ($collapseDetails): ?>
        <?php attendance_performance_help(); ?>
      </details>
    <?php endif; ?>
    <?php
}

function attendance_report_pager(array $pagination, array $query, string $page): void {
    if ($pagination['pages'] < 2) return;
    pagination_nav($pagination['current'], $pagination['pages'], static fn(int $number) =>
        attendance_report_links($query, $page, ['row_page' => $number]) . '#report-rows', 'Attendance record pages');
}
