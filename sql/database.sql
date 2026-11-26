-- Import this file in XAMPP's phpMyAdmin or with the mysql client.
-- Reimporting creates missing tables without replacing existing records.
CREATE DATABASE IF NOT EXISTS mypark_sabah CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE mypark_sabah;

CREATE TABLE IF NOT EXISTS mypark_workspaces (
    id VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY
) ENGINE=InnoDB;

-- The PHP workflows retain their existing record formats. Each business record
-- is saved independently, keyed by its collection and original array key.
CREATE TABLE IF NOT EXISTS mypark_records (
    workspace_id VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    collection VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    record_key VARBINARY(128) NOT NULL,
    position INT UNSIGNED NOT NULL,
    payload JSON NOT NULL,
    PRIMARY KEY (workspace_id, collection, record_key),
    INDEX mypark_record_order (workspace_id, collection, position),
    FOREIGN KEY (workspace_id) REFERENCES mypark_workspaces (id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS mypark_record_parts (
    workspace_id VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    collection VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    record_key VARBINARY(128) NOT NULL,
    part_number INT UNSIGNED NOT NULL,
    bytes MEDIUMBLOB NOT NULL,
    PRIMARY KEY (workspace_id, collection, record_key, part_number),
    FOREIGN KEY (workspace_id, collection, record_key)
        REFERENCES mypark_records (workspace_id, collection, record_key) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS member_vehicles (
    account_scope VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    member_id BIGINT UNSIGNED NOT NULL,
    id VARBINARY(128) NOT NULL,
    plate VARCHAR(128) NOT NULL,
    plate_key VARBINARY(128) NOT NULL,
    name TEXT NOT NULL,
    position BIGINT UNSIGNED NOT NULL AUTO_INCREMENT UNIQUE,
    PRIMARY KEY (account_scope, member_id, id),
    UNIQUE (account_scope, member_id, plate_key),
    FOREIGN KEY (account_scope) REFERENCES mypark_workspaces (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS member_vehicle_preferences (
    account_scope VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    member_id BIGINT UNSIGNED NOT NULL,
    default_vehicle_id VARBINARY(128),
    selected_vehicle_id VARBINARY(128),
    PRIMARY KEY (account_scope, member_id),
    FOREIGN KEY (account_scope) REFERENCES mypark_workspaces (id),
    FOREIGN KEY (account_scope, member_id, default_vehicle_id)
        REFERENCES member_vehicles (account_scope, member_id, id),
    FOREIGN KEY (account_scope, member_id, selected_vehicle_id)
        REFERENCES member_vehicles (account_scope, member_id, id)
) ENGINE=InnoDB;

-- Employee attendance uses this schema along with the rest of MyPark.
CREATE TABLE IF NOT EXISTS attendance_settings (
    scope VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
    radius_m INT NOT NULL CHECK (radius_m BETWEEN 25 AND 5000),
    accuracy_m INT NOT NULL CHECK (accuracy_m BETWEEN 5 AND 1000),
    grace_minutes INT NOT NULL CHECK (grace_minutes BETWEEN 0 AND 120),
    FOREIGN KEY (scope) REFERENCES mypark_workspaces (id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS attendance_admin_locations (
    scope VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    admin_id BIGINT UNSIGNED NOT NULL,
    lot_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (scope, admin_id, lot_id),
    FOREIGN KEY (scope) REFERENCES mypark_workspaces (id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS attendance_shifts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    scope VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    staff_id BIGINT UNSIGNED NOT NULL,
    lot_id BIGINT UNSIGNED NOT NULL,
    lot_name TEXT NOT NULL,
    lot_lat DOUBLE NOT NULL CHECK (lot_lat BETWEEN -90 AND 90),
    lot_lng DOUBLE NOT NULL CHECK (lot_lng BETWEEN -180 AND 180),
    shift_date DATE NOT NULL,
    starts_at BIGINT NOT NULL,
    ends_at BIGINT NOT NULL CHECK (ends_at > starts_at AND ends_at - starts_at < 86400),
    UNIQUE (scope, staff_id, shift_date),
    INDEX attendance_shift_report (scope, shift_date, lot_id),
    FOREIGN KEY (scope) REFERENCES mypark_workspaces (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS attendance_records (
    shift_id BIGINT UNSIGNED PRIMARY KEY,
    checked_in BIGINT, checked_out BIGINT, corrected_in BIGINT, corrected_out BIGINT,
    in_lat DOUBLE, in_lng DOUBLE, in_accuracy DOUBLE, in_distance DOUBLE,
    out_lat DOUBLE, out_lng DOUBLE, out_accuracy DOUBLE, out_distance DOUBLE,
    in_capture VARCHAR(32), out_capture VARCHAR(32),
    in_zone ENUM('within','outside','uncertain'),
    out_zone ENUM('within','outside','uncertain'),
    radius_m INT NOT NULL,
    accuracy_m INT NOT NULL,
    grace_minutes INT NOT NULL,
    created_at BIGINT NOT NULL,
    updated_at BIGINT NOT NULL,
    CHECK (checked_out IS NULL OR checked_in IS NULL OR checked_out >= checked_in),
    FOREIGN KEY (shift_id) REFERENCES attendance_shifts (id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS attendance_photos (
    shift_id BIGINT UNSIGNED PRIMARY KEY,
    captured_at BIGINT NOT NULL,
    jpeg MEDIUMBLOB NOT NULL CHECK (OCTET_LENGTH(jpeg) BETWEEN 1 AND 2000000),
    INDEX attendance_photo_expiry (captured_at),
    FOREIGN KEY (shift_id) REFERENCES attendance_records (shift_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS attendance_photo_parts (
    shift_id BIGINT UNSIGNED NOT NULL,
    part_number INT UNSIGNED NOT NULL,
    bytes MEDIUMBLOB NOT NULL,
    PRIMARY KEY (shift_id, part_number),
    FOREIGN KEY (shift_id) REFERENCES attendance_photos (shift_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS attendance_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    shift_id BIGINT UNSIGNED NOT NULL,
    kind ENUM('evidence','exception','correction') NOT NULL,
    reason TEXT NOT NULL,
    proposed_in BIGINT,
    proposed_out BIGINT,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    created_at BIGINT NOT NULL,
    reviewer_id BIGINT UNSIGNED,
    reviewed_at BIGINT,
    decision_reason TEXT,
    pending_shift BIGINT UNSIGNED AS (CASE WHEN status = 'pending' THEN shift_id ELSE NULL END) STORED,
    UNIQUE (pending_shift),
    INDEX attendance_request_history (shift_id, id),
    FOREIGN KEY (shift_id) REFERENCES attendance_records (shift_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS attendance_audit (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    scope VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    shift_id BIGINT UNSIGNED,
    admin_id BIGINT UNSIGNED NOT NULL,
    action VARCHAR(64) NOT NULL,
    before_value JSON NOT NULL,
    after_value JSON NOT NULL,
    reason TEXT NOT NULL,
    created_at BIGINT NOT NULL,
    INDEX attendance_audit_history (scope, shift_id, id),
    FOREIGN KEY (scope) REFERENCES mypark_workspaces (id),
    FOREIGN KEY (shift_id) REFERENCES attendance_shifts (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add reporting to both new and existing MariaDB installations.
ALTER TABLE attendance_requests
    ADD COLUMN IF NOT EXISTS clarification_reason TEXT,
    ADD COLUMN IF NOT EXISTS clarifier_id BIGINT UNSIGNED,
    ADD COLUMN IF NOT EXISTS clarified_at BIGINT,
    ADD COLUMN IF NOT EXISTS clarification_reply TEXT,
    ADD COLUMN IF NOT EXISTS replied_at BIGINT;

CREATE INDEX IF NOT EXISTS attendance_shift_location_staff
    ON attendance_shifts (scope, lot_id, staff_id);
CREATE INDEX IF NOT EXISTS attendance_request_kind
    ON attendance_requests (shift_id, kind, id);

CREATE TABLE IF NOT EXISTS attendance_report_policy (
    scope VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
    require_checkout TINYINT UNSIGNED NOT NULL CHECK (require_checkout IN (0, 1)),
    excuse_holidays TINYINT UNSIGNED NOT NULL CHECK (excuse_holidays IN (0, 1)),
    FOREIGN KEY (scope) REFERENCES mypark_workspaces (id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS attendance_report_holidays (
    scope VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    holiday_date DATE NOT NULL,
    name VARCHAR(100) NOT NULL CHECK (CHAR_LENGTH(name) BETWEEN 1 AND 100),
    PRIMARY KEY (scope, holiday_date),
    FOREIGN KEY (scope) REFERENCES mypark_workspaces (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
