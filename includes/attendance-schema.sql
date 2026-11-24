CREATE TABLE IF NOT EXISTS attendance_settings (
    scope TEXT PRIMARY KEY,
    radius_m INTEGER NOT NULL CHECK (radius_m BETWEEN 25 AND 5000),
    accuracy_m INTEGER NOT NULL CHECK (accuracy_m BETWEEN 5 AND 1000),
    grace_minutes INTEGER NOT NULL CHECK (grace_minutes BETWEEN 0 AND 120)
);

-- Accounts, lots and leave are session records. Their IDs are scoped to this workspace.
CREATE TABLE IF NOT EXISTS attendance_admin_locations (
    scope TEXT NOT NULL,
    admin_id INTEGER NOT NULL,
    lot_id INTEGER NOT NULL,
    PRIMARY KEY (scope, admin_id, lot_id)
);

CREATE TABLE IF NOT EXISTS attendance_shifts (
    id INTEGER PRIMARY KEY,
    scope TEXT NOT NULL,
    staff_id INTEGER NOT NULL,
    lot_id INTEGER NOT NULL,
    lot_name TEXT NOT NULL,
    lot_lat REAL NOT NULL CHECK (lot_lat BETWEEN -90 AND 90),
    lot_lng REAL NOT NULL CHECK (lot_lng BETWEEN -180 AND 180),
    shift_date TEXT NOT NULL,
    starts_at INTEGER NOT NULL,
    ends_at INTEGER NOT NULL CHECK (ends_at > starts_at AND ends_at - starts_at < 86400),
    UNIQUE (scope, staff_id, shift_date)
);
CREATE INDEX IF NOT EXISTS attendance_shift_report ON attendance_shifts (scope, shift_date, lot_id);

CREATE TABLE IF NOT EXISTS attendance_records (
    shift_id INTEGER PRIMARY KEY REFERENCES attendance_shifts(id),
    checked_in INTEGER,
    checked_out INTEGER,
    corrected_in INTEGER,
    corrected_out INTEGER,
    in_lat REAL, in_lng REAL, in_accuracy REAL, in_distance REAL,
    out_lat REAL, out_lng REAL, out_accuracy REAL, out_distance REAL,
    in_capture TEXT, out_capture TEXT,
    in_zone TEXT CHECK (in_zone IN ('within','outside','uncertain')),
    out_zone TEXT CHECK (out_zone IN ('within','outside','uncertain')),
    radius_m INTEGER NOT NULL,
    accuracy_m INTEGER NOT NULL,
    grace_minutes INTEGER NOT NULL,
    created_at INTEGER NOT NULL,
    updated_at INTEGER NOT NULL,
    CHECK (checked_out IS NULL OR checked_in IS NULL OR checked_out >= checked_in)
);

CREATE TABLE IF NOT EXISTS attendance_photos (
    shift_id INTEGER PRIMARY KEY REFERENCES attendance_records(shift_id),
    captured_at INTEGER NOT NULL,
    jpeg BLOB NOT NULL CHECK (length(jpeg) BETWEEN 1 AND 2000000)
);
CREATE INDEX IF NOT EXISTS attendance_photo_expiry ON attendance_photos (captured_at);

CREATE TABLE IF NOT EXISTS attendance_requests (
    id INTEGER PRIMARY KEY,
    shift_id INTEGER NOT NULL REFERENCES attendance_records(shift_id),
    kind TEXT NOT NULL CHECK (kind IN ('evidence','exception','correction')),
    reason TEXT NOT NULL,
    proposed_in INTEGER,
    proposed_out INTEGER,
    status TEXT NOT NULL DEFAULT 'pending' CHECK (status IN ('pending','approved','rejected')),
    created_at INTEGER NOT NULL,
    reviewer_id INTEGER,
    reviewed_at INTEGER,
    decision_reason TEXT
);
CREATE UNIQUE INDEX IF NOT EXISTS attendance_one_pending ON attendance_requests (shift_id) WHERE status = 'pending';
CREATE INDEX IF NOT EXISTS attendance_request_history ON attendance_requests (shift_id, id);

CREATE TABLE IF NOT EXISTS attendance_audit (
    id INTEGER PRIMARY KEY,
    scope TEXT NOT NULL,
    shift_id INTEGER REFERENCES attendance_shifts(id),
    admin_id INTEGER NOT NULL,
    action TEXT NOT NULL,
    before_value TEXT NOT NULL,
    after_value TEXT NOT NULL,
    reason TEXT NOT NULL,
    created_at INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS attendance_audit_history ON attendance_audit (scope, shift_id, id);
