MyPark Sabah — PHP Session Prototype

Requires PHP 8.1+ with PDO SQLite enabled. Saved vehicles use a private SQLite
database. The other prototype records continue to use PHP sessions.
From this folder run: php -S localhost:8000 router.php
Open http://localhost:8000

Always use router.php with the PHP development server. It exposes pages, API
routes and static assets, and blocks direct access to config, includes and storage.
On Apache 2.4, enable AllowOverride AuthConfig (or AllowOverride All) for the
project directory so the private directories' Require all denied rules apply.
Alternatively put equivalent Require all denied rules in the Apache vhost.
Ensure DirectoryIndex includes index.php and verify each private directory
returns HTTP 403 before serving the site.

Demo accounts:
Super Admin: superadmin@park.com / super123
Admin: admin@park.com / admin123
Staff: staff@park.com / staff123
Member: user@park.com / user123

Styles: assets/css/
Scripts: assets/js/
Images: assets/images/
Admin Dashboard: admin/dashboard.php; staff: staff.php; browser data endpoints: api/
Legacy SQLite archive: storage/parking.db. The Session prototype does not open it.
If config/recaptcha.local.php is missing, create it with the following content.
Replace both placeholders with your development reCAPTCHA v3 keys. Use the
hostname you open in the browser and register it for the key in Google.
The local config is ignored by Git. Enable PHP cURL for login verification.

<?php
return [
    'site_key' => 'YOUR_V3_SITE_KEY',
    'secret_key' => 'YOUR_V3_SECRET_KEY',
    'allowed_hostnames' => ['localhost'],
    'min_score' => 0.5,
];

MyPark stores demo data in a private app folder under PHP's session.save_path
(or the system temporary folder if that setting is empty). PHP must be able to
write there. Keep this location outside the web root.

Registration sends a one-time verification link over SMTP. Open it within
10 minutes in the browser where you started registration. Password recovery
sends a six-digit OTP. Request it from the browser where you registered your
demo account.
In config/smtp.local.php, set email to the SMTP sender address and app_password
to its app password. Git ignores this local config file. Localhost links use
the current port and project path automatically. For other hosts set app_url
to the site's base URL, including any project subfolder.
Gmail uses smtp.gmail.com on port 587
with STARTTLS. PHP cURL with SMTP support and a trusted CA bundle is required.

Accounts, parking locations, OKU verifications, issued compounds, digital
receipts, wallet balances and wallet activity survive sign-out in the same
browser. Sign-out clears booking history, maintenance and customer support reviews.
The browser's demo session expires after roughly a month of inactivity.
Saved vehicles and their default/selected preferences persist in SQLite and
survive sign-out using the same browser-specific demo account identity.
Do not use this prototype for real payments or official enforcement.

To try compound payments, create a fine in Staff → Fine Management, sign out,
then sign in as a member in the same browser. Open Compounds and search for the
fine number or plate. Only members can use wallet credit, pay parking fees or
top up with PayPal Sandbox. Admin and Super Admin can look up compounds and view
the records available through Dashboard.

Open Receipts to find parking bookings, compound payments and wallet top-ups.
Parking cards and the Parking tab show session details and let you open tickets,
check the countdown or switch to an eligible bay type. Drivers can cancel a session
while more than five minutes remain and receive a notification once it is cancelled.
The receipt date range
includes today and the previous 29 days in Malaysia time. View or print receipts
from the original account in the same browser. Receipt numbers contain the
transaction date and time in Malaysia, followed by the vehicle plate or receipt
type. Matching numbers get a short sequence suffix.

PayPal Sandbox top-ups add demo credit. Confirming payment deducts the parking
fee from the demo wallet and starts parking immediately. Future reservations are
not supported. Successful parking payments and top-ups appear in Notifications.
Email OTP/reset delivery, SMS and live support are not connected.
Photo rotation is a preview; saving keeps the original image.
Third-party fonts, chatbot and ticket QR library require Internet access.

PayPal Sandbox setup
In the PayPal Developer Dashboard, open Apps & Credentials, select Sandbox,
and copy the client ID and secret from the same app. Use Malaysian sandbox
accounts to test payments in MYR. Enable PHP cURL and create the Git-ignored
file config/paypal.local.php with the following contents. Replace both
placeholders with your sandbox app credentials.

<?php
return [
    'client_id' => 'YOUR_SANDBOX_CLIENT_ID',
    'client_secret' => 'YOUR_SANDBOX_CLIENT_SECRET',
];

Dashboard https://developer.paypal.com/dashboard/applications/sandbox

Super Admin parking availability
Open admin/dashboard.php as Super Admin. The panel above Reservation Management
uses the same snapshot as the table. Each active stay counts as one occupied bay
at its location. Available bays = max(0, capacity minus occupied bays) for each
bay type. Completed and cancelled stays do not occupy bays. Missing capacity is shown as
"Capacity unavailable". Counts come from this browser's MyPark demo session.
Select a marker or a location in the list to set the table's Location filter.
Completing a stay or using Refresh availability updates the counts and time.

To turn on the Google map, enable Maps JavaScript API and billing in your Google
Cloud project. Restrict the browser API key to the APIs it uses and the sites
that serve MyPark, for example http://localhost:8000/* for the PHP dev server.
Create the ignored file config/maps.local.php with:

<?php
return [
    'api_key' => 'YOUR_MAPS_JAVASCRIPT_API_KEY',
    'map_id' => 'DEMO_MAP_ID', // Optional: use your own JavaScript map ID.
];

Google setup: https://developers.google.com/maps/documentation/javascript/get-api-key
The location list works without the key. Locations need valid lat/lng values
to appear as map markers.

Admin and Super Admin can open Add parking location from Parking locations in
Dashboard. Enable Places API (New) and allow it on the browser key to search by
place name. Suggestions cover Malaysia and prioritize Sabah. Selecting a
suggestion places the pin and fills in the city and address. Enable and allow
Geocoding API to look up the city and address from a dropped pin. Review the details before
saving. Choose the pin on the map or select a search result to set the position.
Saved locations appear in Find parking for members using the same demo browser.

OKU Parking Verification (demo)
Use fictional proof and the demo accounts in the same browser. A member
opens OKU Parking Verification from the Profile menu or the Profile status card.
The dedicated oku-verification.php page handles proof uploads and resubmissions.
The supplied accessibility logo appears above its heading. A Super Admin can review
in Dashboard > OKU Verification, or grant review access to an Admin there.
An Admin without a grant and Staff cannot review or open proof. Keep document
numbers out of rejection reasons. A rejected member can submit a new proof.

There is no project policy for which supporting documents to accept in Sabah.
Confirm this policy before accepting real proof. The demo uses the existing
profile upload formats (JPG, PNG and WebP) with a 2 MB server-side limit.
Approval applies only to parking inside MyPark Sabah.

The app keeps pending proof in its private PHP file session and removes it
when a decision is recorded. The decision, reviewer and timestamp stay in the
session. Keep PHP's file session storage outside the web root, enable uploads
and Fileinfo, and set PHP upload/post limits to accommodate the 2 MB proof.
Keep request-body logging disabled for uploads and verification requests.
The existing browser-specific session model does not share reviews across
separate browsers. OKU verification uses its existing session records.

Use Dashboard > Set OKU bay allocation to reserve bays within a location's
existing total. New demo sessions start with one OKU bay at Kota Kinabalu
Waterfront. Locations missing an allocation default to zero. Existing stays
without a bay type count as regular. Verified members can choose the OKU type
at booking or switch an active stay from History. A switch is for remaining
time at the same location and retains the current fee. Booking review,
confirmation, history changes and the map use the same per-type counts.

Saved vehicle selector
On the member Parking page, the Selected vehicle card sits between the statistics
and location search. Add vehicle opens My vehicles in Profile and returns to
Parking with the new vehicle selected. Manage vehicles opens the existing list.
Choose another vehicle in the dropdown to save it immediately. The Use vehicle
button provides the same action when JavaScript is disabled.

The first vehicle becomes the default. The last valid selection is restored on
future visits, with the default as fallback. Changing the default keeps a valid
current selection. Removing a selected vehicle falls back to a remaining default.
Plates use the existing member booking format (one to three letters, one to four
digits, optional final letter). Case and whitespace are normalized consistently.
A database unique constraint prevents duplicate plates within one member account.

Setup and migration run automatically on the first vehicle request. The app
creates member_vehicles and member_vehicle_preferences in vehicles.sqlite3 under
the private application session directory returned by session_save_path() after
bootstrap. This directory is outside the web root. PHP needs read/write access
there and the pdo_sqlite extension in the PHP installation serving the site.
Existing session vehicles, including old plain plate strings, are imported once
per member with their IDs and default preserved. Normalized duplicate legacy
plates merge into the first saved record. Booking-time plate snapshots stay intact.

Ownership combines the current member ID with a random account scope retained
across sign-out and session-ID rotation. This follows the prototype's existing
browser-specific accounts. Keep the demo browser cookie when demonstrating saved
accounts. Account storage and OKU approval retain their existing session lifecycle.

The booking page starts with the selected saved vehicle. Add vehicle is also
available there. Edit details in the review lets a member change the vehicle and
review again. Both review and confirmation load the member's vehicle from SQLite,
check the plate's active stays, capacity and the member's current OKU approval.
Members must save a valid vehicle before review. Admin manual parking follows its
existing access rules. The app supports immediate stays of 1-24 hours.

Starting parking copies the saved vehicle ID and current plate into the booking.
Parking sessions, e-tickets and receipts read that booking-time plate. Changing the
dashboard selection affects later bookings. While a vehicle has an active stay,
its plate cannot be edited and it cannot be removed. Its nickname can be edited.
End the stay before changing its plate or removing it. Legacy active stays without
vehicle IDs are matched by their normalized plate. Bookings keep the prototype's
existing confirmation and payment behavior, including its fee review and e-ticket.

Walkthrough files
includes/vehicles.php contains SQLite setup, migration, preferences and ownership.
includes/vehicle-selector.php and assets/js/vehicles.js render and save selection.
dashboard.php places the card. profile.php manages saved vehicle details.
includes/parking-booking.php validates bookings and stores the plate snapshot.
parking-details.php and includes/booking-panel.php handle review and confirmation.
api/vehicles.php handles authenticated member vehicle requests with CSRF checks.

Verification used isolated fictional accounts and a disposable SQLite/session
directory. Checks covered multiple vehicles, default/selection fallback, sign-out,
duplicate normalization, cross-member API requests, forged booking IDs, CSRF,
review replay, stale/deleted selections, active-stay edit/removal restrictions,
historical plates, legacy migration and OKU review/confirmation eligibility.

Employee Attendance
Open Employee Attendance as Super Admin. Use Admin Location Access
to assign an Admin to their workplaces, then schedule a shift for a Staff account.
An end time earlier than the start time means the shift ends the following day.
The Admin's existing Leave requests page is linked from Employee Attendance.
Staff open My Attendance from their dashboard to check in, check out, see the
last 31 calendar days of shifts, and request a review or time correction.

PHP needs PDO SQLite and Fileinfo, and a writable session directory outside the
web root. The app creates or migrates attendance.sqlite3 on the first attendance
visit while keeping existing records. Use
localhost or HTTPS for camera and location permissions. This prototype still uses
browser-session workspaces for accounts, parking locations, and approved leave.
For a demo, use the same browser when switching between Staff and Admin accounts.
A deployment with independent employee devices requires shared account, location,
and leave storage and a shared workspace identity.

The default workplace radius is 150 m, maximum GPS error 100 m, and late grace
period 10 minutes. Super Admin can change these for new records. Staff can check
in up to 30 minutes before work begins and before the shift ends. Missing
check-ins are flagged after that shift ends. Any approved full-day leave that
overlaps a shift excludes it from missing-attendance counts, including overnight
shifts. Staff can request corrections when outside the normal recording window.
Photos and raw reported coordinates are available to the employee, scoped Admins,
and Super Admin for up to 90 days, then purged on the next attendance request.
Keep the database and its backups private. General reports omit photos, raw GPS
coordinates, and private review notes. They retain GPS statuses and timestamps.
Browser GPS and camera capture are reported evidence. They cannot establish true
physical presence or image authenticity. Failed verification triggers human review.

Attendance analytics and reports
Employee Attendance has Overview, Attendance Records, Monthly Calendar, Attendance
Exceptions, and Reports & Export. Use the report preview to confirm the same rows
that will be exported. Daily, weekly and monthly reports use the start date.
Weeks start on Monday. The other report types use both dates. Limit each report
to 366 calendar days and 10,000 shifts. Use filters to split larger reports.
Employees can open Monthly Calendar and their summary from My Attendance.

Shifts are attributed to their start date in MYT, including overnight shifts.
The scheduled employee count includes each employee with a shift once.
The attendance rate is present employee-days divided by present plus absent
employee-days. A present day needs a verified check-in for each required shift.
Absence means a completed required shift with no check-in and no open review.
Approved leave, excused holidays, unfinished shifts and unresolved verification
are excluded from settled attendance rates. Punctuality is on-time days divided
by present days. Working hours use paired effective timestamps, including
approved corrections. A blank rate means no eligible days were observed.

Full attendance is assessed after the reporting period and its shifts end. It
requires at least one required day, verified check-ins, no unresolved reviews,
and by default a check-out on every required shift. It considers the whole
employee and location scope for the period, even when a status filter hides
some records. Super Admin can change the check-out rule and enter the holidays
observed by the company under Reports & Export > Reporting settings. There is
one scheduled shift per employee per start date in the existing scheduler.

Admins can request clarification once per pending attendance review. The
employee can reply from the shift details. The request stays pending until an
authorized reviewer decides it. Clarification and review decisions are audited.

Automatic SQLite schema version 2 adds attendance_report_policy,
attendance_report_holidays, five clarification columns on attendance_requests,
and indexes for scoped reporting. Existing shift, leave, and evidence workflows
retain their browser-session workspace. Reports are generated on demand using
bundled tFPDF 1.34 and SimpleXLSXGen 1.5.17 (with licenses in includes/report-libraries).
Exports need PHP mbstring and zlib, which are enabled in the current XAMPP.
Excel reports have typed dates and numbers, filtered and frozen headers, and
conditional status colours.

Run php -d extension=zip tests/attendance-reporting.php on Windows/XAMPP.
ZipArchive is used by the test to inspect XLSX files. Report generation uses zlib.
