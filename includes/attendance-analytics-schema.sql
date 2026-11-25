ALTER TABLE attendance_requests ADD COLUMN clarification_reason TEXT;
ALTER TABLE attendance_requests ADD COLUMN clarifier_id INTEGER;
ALTER TABLE attendance_requests ADD COLUMN clarified_at INTEGER;
ALTER TABLE attendance_requests ADD COLUMN clarification_reply TEXT;
ALTER TABLE attendance_requests ADD COLUMN replied_at INTEGER;

CREATE INDEX IF NOT EXISTS attendance_shift_location_staff
    ON attendance_shifts (scope, lot_id, staff_id);
CREATE INDEX IF NOT EXISTS attendance_request_kind
    ON attendance_requests (shift_id, kind, id);

CREATE TABLE IF NOT EXISTS attendance_report_policy (
    scope TEXT PRIMARY KEY,
    require_checkout INTEGER NOT NULL CHECK (require_checkout IN (0, 1)),
    excuse_holidays INTEGER NOT NULL CHECK (excuse_holidays IN (0, 1))
);

CREATE TABLE IF NOT EXISTS attendance_report_holidays (
    scope TEXT NOT NULL,
    holiday_date TEXT NOT NULL,
    name TEXT NOT NULL CHECK (length(name) BETWEEN 1 AND 100),
    PRIMARY KEY (scope, holiday_date)
);
