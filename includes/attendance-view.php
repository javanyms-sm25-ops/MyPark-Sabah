<?php

function attendance_badge(?string $key): string {
    $labels = ATTENDANCE_LABELS + ['approved' => 'Approved', 'rejected' => 'Review Rejected'];
    return $key === null ? '—' : '<span class="attendance-badge attendance-' . e($key) . '">' . te($labels[$key] ?? $key) . '</span>';
}

function attendance_fields(string $action, int $id = 0): string {
    $fields = ['csrf' => csrf(), 'action' => $action];
    if ($id) $fields['shift_id'] = $id;
    if (in_array($action, ['check_in', 'check_out', 'exception'], true)) {
        $fields += ['gps_status' => 'skipped', 'latitude' => '', 'longitude' => '', 'accuracy' => ''];
    }
    $html = '';
    foreach ($fields as $name => $value) {
        $html .= '<input type="hidden" name="' . e($name) . '" value="' . e($value) . '">';
    }
    return $html;
}

function attendance_location_evidence(array $row, string $event): string {
    if ($row[$event . '_capture'] === null) return '';
    $status = te(ATTENDANCE_CAPTURE[$row[$event . '_capture']]);
    $html = '<p>' . $status . '</p>';
    if ($row[$event . '_accuracy'] !== null) {
        $html .= '<p>' . te('Distance') . ' · ' . e($row[$event . '_distance']) . ' m<br>' .
            te('GPS Accuracy') . ' · ' . e($row[$event . '_accuracy']) . ' m</p>';
    }
    if ($row[$event . '_lat'] !== null) {
        $html .= '<p>' . te('Reported coordinates') . ' · ' . e(attendance_coordinates($row, $event)) . '</p>';
    } elseif ($row[$event . '_capture'] === 'captured') {
        $html .= '<p>' . te('Reported coordinates have expired.') . '</p>';
    }
    return $html;
}

function attendance_coordinates(array $row, string $event): string {
    return $row[$event . '_lat'] === null ? '—' : sprintf('%.5F, %.5F', $row[$event . '_lat'], $row[$event . '_lng']);
}
