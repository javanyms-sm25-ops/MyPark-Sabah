<?php

require_once __DIR__ . '/report-libraries/excel/SimpleXLSXGen.php';

class MyParkAttendanceWorkbook extends \Shuchkin\SimpleXLSXGen {
    private const STATUS_COLORS = [
        'on_time' => ['E0F4E9', '176B45'], 'late' => ['FFF1D6', '7C460A'], 'missing' => ['FCE9EC', 'A52E44'],
        'leave' => ['DCECF9', '12335B'], 'holiday' => ['DCECF9', '12335B'], 'pending' => ['FFE9D3', '89390D'],
        'clarification' => ['FFE9D3', '89390D'], 'unverified' => ['FFE9D3', '89390D'],
    ];
    public int $detailRows = 0;

    // Extend the upstream writer with conditional styles for the status column.
    protected function _writeEntry($fh, &$cdrec, $cfilename, $data) {
        if ($this->detailRows > 0 && $cfilename === 'xl/styles.xml') {
            $styles = '';
            foreach (self::STATUS_COLORS as [$fill, $text]) {
                $styles .= '<dxf><font><b/><color rgb="FF' . $text . '"/></font>'
                    . '<fill><patternFill patternType="solid"><fgColor rgb="FF' . $fill . '"/></patternFill></fill></dxf>';
            }
            $data = str_replace('</styleSheet>', '<dxfs count="' . count(self::STATUS_COLORS) . '">' . $styles . '</dxfs></styleSheet>', $data);
        }
        if ($this->detailRows > 0 && $cfilename === 'xl/worksheets/sheet2.xml') {
            $rules = '<conditionalFormatting sqref="M2:M' . ($this->detailRows + 1) . '">';
            foreach (array_keys(self::STATUS_COLORS) as $priority => $status) {
                $text = str_replace('"', '""', attendance_export_label($status));
                $rules .= '<cfRule type="cellIs" dxfId="' . $priority . '" priority="' . ($priority + 1)
                    . '" operator="equal"><formula>' . attendance_xlsx_xml('"' . $text . '"') . '</formula></cfRule>';
            }
            $data = str_replace('</worksheet>', $rules . '</conditionalFormatting></worksheet>', $data);
        }
        return parent::_writeEntry($fh, $cdrec, $cfilename, $data);
    }
}

function attendance_xlsx_xml(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
}

function attendance_xlsx_text(string $value): string {
    $value = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $value) ?? '';
    return $value === '' ? '' : "\0" . $value;
}

function attendance_xlsx_header(string $text): string {
    return '<style bgcolor="#12335B" color="#FFFFFF" border="thin#7899B8"><b><raw>' . attendance_xlsx_xml($text) . '</raw></b></style>';
}

function attendance_xlsx_number(float $number, string $format): string {
    return '<style nf="' . $format . '">' . sprintf('%.10F', $number) . '</style>';
}

function attendance_xlsx_datetime(string $iso): string {
    if ($iso === '') return '';
    $value = new DateTimeImmutable($iso);
    $serial = \Shuchkin\SimpleXLSXGen::date2excel(...array_map('intval', explode(' ', $value->format('Y n j G i s'))));
    return attendance_xlsx_number($serial, strlen($iso) === 10 ? 'yyyy-mm-dd' : 'yyyy-mm-dd hh:mm:ss');
}

function attendance_xlsx_add_table(MyParkAttendanceWorkbook $workbook, string $name, array $headings, array $rows, array $widths): void {
    $workbook->addSheet([array_map('attendance_xlsx_header', $headings), ...$rows], $name)->freezePanes('A2');
    foreach ($widths as $index => $width) $workbook->setColWidth($index + 1, $width);
    if ($rows) $workbook->autoFilter('A1:' . chr(64 + count($headings)) . (count($rows) + 1));
}

function attendance_build_xlsx(array $data): string {
    $book = new MyParkAttendanceWorkbook();
    $book->detailRows = count($data['detail']);
    $book->setTitle($data['title'])->setAuthor('MyPark Sabah');
    $summary = [[attendance_xlsx_header($data['title']), attendance_xlsx_header('MyPark Sabah')]];
    foreach ($data['scope'] as $label => $value) $summary[] = [attendance_xlsx_text($label), attendance_xlsx_text($value)];
    $summary[] = ['', ''];
    $summary[] = [attendance_xlsx_header(t('Attendance Overview')), attendance_xlsx_header(t('Total'))];
    foreach ($data['metrics'] as $label => $value) {
        $summary[] = [attendance_xlsx_text($label), is_float($value) ? attendance_xlsx_number($value / 100, '0.0%') : $value];
    }
    $book->addSheet($summary, 'Summary')->freezePanes('A2')->setColWidth('A', 45)->setColWidth('B', 68);

    $details = [];
    foreach ($data['detail'] as $entry) {
        $cells = [];
        foreach ($entry as $key => $value) {
            $cells[] = match ($key) {
                'shift_date', 'starts_at', 'ends_at', 'actual_in', 'actual_out', 'effective_in', 'effective_out' => attendance_xlsx_datetime($value),
                'shift_id' => $value,
                'hours' => $value === null ? '' : attendance_xlsx_number($value, '0.00'),
                default => attendance_xlsx_text($value),
            };
        }
        $details[] = $cells;
    }
    attendance_xlsx_add_table($book, 'Detailed Attendance', array_map('t', ATTENDANCE_EXPORT_COLUMNS), $details,
        [12, 16, 14, 27, 31, 25, 25, 25, 25, 25, 25, 24, 24, 22, 22, 25, 52, 25]);

    if ($data['people']) {
        $people = [];
        foreach ($data['people'] as $entry) {
            $cells = [];
            foreach ($entry as $key => $value) {
                $cells[] = match ($key) {
                    'staff_id', 'name', 'location', 'full' => attendance_xlsx_text($value),
                    'hours' => attendance_xlsx_number($value, '0.00'),
                    'attendance_rate', 'punctuality_rate' => $value === null ? '' : attendance_xlsx_number($value / 100, '0.0%'),
                    default => $value,
                };
            }
            $people[] = $cells;
        }
        attendance_xlsx_add_table($book, 'Employee Summary',
            array_map('t', ['Staff ID', 'Employee', 'Assigned Location', ...array_values(ATTENDANCE_PERFORMANCE_FIELDS)]),
            $people, [14, 28, 36, 27, 16, 16, 16, 16, 23, 23, 18, 18, 24]);
    }
    if ($data['type'] === 'location' && $data['locations']) {
        $places = array_map(static fn($lot) => [attendance_xlsx_text($lot['name']), $lot['shifts'],
            $lot['present'], $lot['missing'], $lot['pending']], $data['locations']);
        attendance_xlsx_add_table($book, 'Location Summary',
            array_map('t', array_values(ATTENDANCE_LOCATION_FIELDS)),
            $places, [38, 23, 20, 23, 34]);
    }
    $file = (string)$book;
    if (!str_starts_with($file, "PK\x03\x04")) throw new RuntimeException('Could not generate Excel workbook.');
    return $file;
}
