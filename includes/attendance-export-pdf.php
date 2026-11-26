<?php

require_once __DIR__ . '/report-libraries/pdf/font/unifont/ttfonts.php';
require_once __DIR__ . '/report-libraries/pdf/tfpdf.php';

class MyParkAttendancePdf extends tFPDF {
    private const FONT_DIRECTORY = __DIR__ . '/report-libraries/pdf/font/unifont/';

    public function __construct(private readonly string $reportTitle, private readonly string $period) {
        parent::__construct('L', 'mm', 'A4');
        $this->SetMargins(12, 10, 12);
        $this->SetAutoPageBreak(false);
        $this->AliasNbPages();
        // Read the bundled fonts without writing font metrics into the project.
        if (!defined('_SYSTEM_TTFONTS')) define('_SYSTEM_TTFONTS', self::FONT_DIRECTORY);
        $this->fontpath = __DIR__ . '/report-libraries/pdf/';
        $this->AddFont('Report', '', 'DejaVuSansCondensed.ttf', true);
        $this->AddFont('Report', 'B', 'DejaVuSansCondensed-Bold.ttf', true);
        $this->SetTitle($reportTitle, true);
        $this->SetAuthor('MyPark Sabah');
    }

    private function setTextFont(string $text, bool $bold, float $size): void {
        if (preg_match('/[\p{Han}\p{Hiragana}\p{Katakana}\p{Hangul}]/u', $text)) {
            $this->AddFont('Report Asian', '', 'NotoSansSC-VF.ttf', true);
            $this->SetFont('Report Asian', '', $size);
        } else {
            $this->SetFont('Report', $bold ? 'B' : '', $size);
        }
    }

    private function clean(string $text): string {
        $text = str_replace(['–', '—', '‑'], '-', $text);
        return trim(preg_replace('/[\p{Cc}\x{10000}-\x{10FFFF}]+/u', ' ', $text) ?? '');
    }

    public function Header() {
        $asset = dirname(__DIR__) . '/assets/images/mypark-logo-light.png';
        [$w, $h] = getimagesize($asset);
        $scale = min(44 / $w, 16 / $h);
        $this->Image($asset, 12, 8 + (16 - $h * $scale) / 2, $w * $scale, $h * $scale, 'PNG');

        $this->SetXY(61, 9);
        $title = $this->clean($this->reportTitle);
        $this->setTextFont($title, true, 15);
        $this->SetTextColor(18, 51, 91);
        $this->Cell(0, 7.5, $title, 0, 1);
        $this->SetX(61);
        $this->setTextFont($this->period, false, 9);
        $this->SetTextColor(67, 84, 106);
        $this->Cell(0, 5.5, $this->clean($this->period), 0, 1);
        $this->SetDrawColor(213, 226, 238);
        $this->Line(12, 27, 285, 27);
        $this->SetY(32);
    }

    public function Footer() {
        $this->SetY(-13);
        $this->SetDrawColor(213, 226, 238);
        $this->Line(12, $this->GetY(), 285, $this->GetY());
        $this->SetY(-10);
        $footer = 'MyPark Sabah  |  ' . t('Private attendance report');
        $pageNumber = t('Page') . ' ' . $this->PageNo() . ' / {nb}';
        $this->setTextFont($footer, false, 8);
        $this->SetTextColor(67, 84, 106);
        $this->Cell(205, 5, $footer);
        $this->setTextFont($pageNumber, false, 8);
        $this->Cell(68, 5, $pageNumber, 0, 0, 'R');
    }

    private function wrap(string $text, float $width): array {
        $lines = [];
        $line = '';
        foreach (preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            $candidate = $line === '' ? $word : $line . ' ' . $word;
            if ($this->GetStringWidth($candidate) <= $width) {
                $line = $candidate;
                continue;
            }
            if ($line !== '') $lines[] = $line;
            $line = '';
            if ($this->GetStringWidth($word) <= $width) {
                $line = $word;
                continue;
            }
            foreach (mb_str_split($word) as $character) {
                if ($line !== '' && $this->GetStringWidth($line . $character) > $width) {
                    $lines[] = $line;
                    $line = '';
                }
                $line .= $character;
            }
        }
        $lines[] = $line;
        return $lines;
    }

    private function cells(array $values, array $widths, bool $header): array {
        $cells = [];
        $lines = 1;
        foreach (array_values($values) as $index => $value) {
            $text = $this->clean((string)$value);
            if ($text === '') $text = '-';
            $this->setTextFont($text, $header, $header ? 8.3 : 8.5);
            $wrapped = $this->wrap($text, $widths[$index] - 5);
            $lines = max($lines, count($wrapped));
            $cells[] = [$text, $wrapped];
        }
        return [$cells, 3.9 * $lines + 3.5];
    }

    private function drawRow(array $cells, array $widths, float $height, bool $header, bool $tinted = false): void {
        $x = 12;
        $y = $this->GetY();
        $this->SetDrawColor(213, 226, 238);
        $this->SetFillColor(...($header ? [18, 51, 91] : ($tinted ? [244, 249, 252] : [255, 255, 255])));
        $this->SetTextColor(...($header ? [255, 255, 255] : [20, 38, 61]));
        foreach ($cells as $index => [$text, $lines]) {
            $width = $widths[$index];
            $this->Rect($x, $y, $width, $height, 'DF');
            $this->setTextFont($text, $header, $header ? 8.3 : 8.5);
            foreach ($lines as $line => $part) {
                $this->SetXY($x + 1.4, $y + 1.5 + 3.9 * $line);
                $this->Cell($width - 2.8, 3.9, $part);
            }
            $x += $width;
        }
        $this->SetY($y + $height);
    }

    public function table(string $title, array $headings, array $rows, array $widths): void {
        if ($this->GetY() > 165) $this->AddPage();
        $this->Ln(4);
        $text = $this->clean($title);
        $this->setTextFont($text, true, 11);
        $this->SetTextColor(18, 51, 91);
        $this->Cell(0, 7, $text, 0, 1);
        [$header, $height] = $this->cells($headings, $widths, true);
        $this->drawRow($header, $widths, $height, true);
        foreach ($rows as $index => $row) {
            [$cells, $rowHeight] = $this->cells($row, $widths, false);
            if ($this->GetY() + $rowHeight > 191) {
                $this->AddPage();
                $this->drawRow($header, $widths, $height, true);
            }
            $this->drawRow($cells, $widths, $rowHeight, false, $index % 2 === 0);
        }
    }
}

function attendance_build_pdf(array $data): string {
    $pdf = new MyParkAttendancePdf($data['title'], $data['scope'][t('Reporting period')]);
    $pdf->AddPage();
    $scope = [];
    foreach ($data['scope'] as $label => $value) $scope[] = [$label, $value];
    $pdf->table(t('Applied filters'), [t('Filter'), t('Selection')], $scope, [57, 216]);

    $numbers = [];
    foreach ($data['metrics'] as $label => $value) {
        $numbers[] = [$label, is_float($value) ? number_format($value, 1) . '%' : $value];
    }
    $pdf->table(t('Attendance Overview'), [t('Measure'), t('Total')], $numbers, [214, 59]);

    if ($data['people']) {
        $people = array_map(static fn($p) => [
            $p['name'] . ' · ' . $p['staff_id'], $p['location'], $p['scheduled_days'], $p['present_days'],
            $p['on_time_days'], $p['late_days'], $p['absent_days'], $p['leave_days'], number_format($p['hours'], 2),
            attendance_report_display('attendance_rate', $p['attendance_rate']),
            attendance_report_display('punctuality_rate', $p['punctuality_rate']), $p['full'],
        ], $data['people']);
        $pdf->table(t('Employee performance'), array_map('t', ['Employee', 'Assigned Location',
            'Scheduled Days', 'Present Days', 'On Time', 'Late Days', 'Absent Days', 'Leave Days', 'Hours', 'Attendance Rate', 'Punctuality Rate', 'Full Attendance']),
            $people, [40, 37, 23, 19, 15, 14, 16, 14, 19, 24, 24, 28]);
    }

    if ($data['type'] === 'location' && $data['locations']) {
        $places = array_map(static fn($row) => array_values($row), $data['locations']);
        $pdf->table(t('Attendance by Parking Location'), array_map('t', array_values(ATTENDANCE_LOCATION_FIELDS)),
            $places, [103, 40, 35, 40, 55]);
    }

    $details = [];
    foreach ($data['detail'] as $entry) {
        $details[] = [
            $entry['shift_date'],
            $entry['name'] . ' · ' . $entry['staff_id'],
            $entry['location'],
            substr($entry['starts_at'], 11, 5) . ' - ' . substr($entry['ends_at'], 11, 5)
                . (substr($entry['ends_at'], 0, 10) === $entry['shift_date'] ? '' : ' (' . t('Next day') . ')'),
            $entry['effective_in'], $entry['effective_out'],
            $entry['hours'] === null ? '' : number_format($entry['hours'], 2),
            implode(' · ', array_unique(array_filter([$entry['status'], $entry['in_zone'],
                $entry['out_zone'] !== '' ? t('Check-out') . ' ' . $entry['out_zone'] : '',
                $entry['review'] === '—' ? '' : $entry['review'], $entry['exceptions'], $entry['holiday']]))),
        ];
    }
    $pdf->table(t('Detailed Attendance'), $details
        ? array_map('t', ['Shift Date (MYT)', 'Employee', 'Assigned Location', 'Scheduled shift',
            'Effective Check-In (MYT)', 'Effective Check-Out (MYT)', 'Hours', 'Status, GPS & Exceptions'])
        : [t('Shift records')],
        $details ?: [[t('No attendance records match these filters.')]],
        $details ? [21, 35, 33, 27, 31, 31, 14, 81] : [273]);

    $file = $pdf->Output('S');
    if (!str_starts_with($file, '%PDF-')) throw new RuntimeException('Could not generate PDF report.');
    return $file;
}
