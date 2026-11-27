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

function attendance_distance_label(float $metres): string {
    return $metres >= 1000 ? number_format($metres / 1000, 2) . ' km' : number_format(round($metres)) . ' m';
}

function attendance_coordinates(array $row, string $event): string {
    return sprintf('%.6F, %.6F', $row[$event . '_lat'], $row[$event . '_lng']);
}

function attendance_location_map_url(array $row, string $event): ?string {
    if ($row[$event . '_lat'] === null || $row[$event . '_lng'] === null) return null;
    return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode(attendance_coordinates($row, $event));
}
