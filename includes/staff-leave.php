<?php

const STAFF_LEAVE_STATUSES = ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'];
const STAFF_LEAVE_REASON_REQUIRED = 'Please enter a reason for your leave request.';
const STAFF_LEAVE_REASON_TOO_LONG = 'Enter a leave reason of 500 characters or fewer.';

function staff_leave_date_is_valid(string $value): bool {
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
}

function staff_leave_submit(array $staff, array $input): string {
    $start = $input['start_date'] ?? '';
    $end = $input['end_date'] ?? '';
    if (!staff_leave_date_is_valid($start) || !staff_leave_date_is_valid($end) || $end < $start) {
        throw new RuntimeException('Enter valid leave dates. The end date must be on or after the start date.', 422);
    }

    $reason = preg_replace('/\A[\x09-\x0D\p{Z}\x{FEFF}]+|[\x09-\x0D\p{Z}\x{FEFF}]+\z/u', '', $input['reason'] ?? '');
    if ($reason === '') {
        throw new RuntimeException(STAFF_LEAVE_REASON_REQUIRED, 422);
    }
    if ($reason === null || preg_match('/\A.{1,500}\z/us', $reason) !== 1) {
        throw new RuntimeException(STAFF_LEAVE_REASON_TOO_LONG, 422);
    }

    $requests = MyParkData::$records['staff_leave_requests'] ?? [];
    foreach ($requests as $request) {
        if ($request['staff_id'] === $staff['id'] && $request['status'] !== 'rejected'
            && $request['start_date'] === $start && $request['end_date'] === $end) {
            throw new RuntimeException('A leave request already exists for these dates.', 409);
        }
    }

    $id = max(array_keys($requests) ?: [0]) + 1;
    MyParkData::$records['staff_leave_requests'][$id] = [
        'id' => $id,
        'staff_id' => $staff['id'],
        'staff_name' => $staff['name'],
        'start_date' => $start,
        'end_date' => $end,
        'reason' => $reason,
        'status' => 'pending',
        'submitted_at' => date(DATE_ATOM),
    ];
    return 'Leave request created.';
}

function staff_leave_review(array $actor, array $input): string {
    if ($actor['role'] !== 'admin') {
        throw new RuntimeException('Only Admin can review leave requests.', 403);
    }
    $decision = $input['decision'] ?? '';
    if (!in_array($decision, ['approved', 'rejected'], true)) {
        throw new RuntimeException('Choose a valid leave decision.', 422);
    }

    $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $request = $id === false ? null : (MyParkData::$records['staff_leave_requests'][$id] ?? null);
    if (!$request) throw new RuntimeException('Leave request not found.', 404);
    if ($request['status'] !== 'pending') {
        throw new RuntimeException('This leave request has already been reviewed.', 409);
    }

    $request['status'] = $decision;
    $request['reviewer_id'] = $actor['id'];
    $request['reviewer_name'] = $actor['name'];
    $request['reviewed_at'] = date(DATE_ATOM);
    MyParkData::$records['staff_leave_requests'][$id] = $request;
    return $decision === 'approved' ? 'Leave approval recorded.' : 'Leave rejection recorded.';
}

function staff_leave_rows(array $actor): array {
    $requests = array_reverse(MyParkData::$records['staff_leave_requests'] ?? []);
    return match ($actor['role']) {
        'admin' => $requests,
        'staff' => array_filter($requests, static fn($request) => $request['staff_id'] === $actor['id']),
        default => [],
    };
}
