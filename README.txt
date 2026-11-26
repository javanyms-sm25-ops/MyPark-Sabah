MyPark Sabah — XAMPP development setup

1. Start Apache and MySQL in the XAMPP Control Panel.
2. For a new installation, import sql/database.sql at http://localhost/phpmyadmin/.
   It creates mypark_sabah and the required tables. It can be imported again
   without replacing records.
3. Put the website files in xampp/htdocs/mypark and open http://localhost/mypark/.
   On this machine the entries in htdocs/mypark already load the original project.

Requires PHP 8.1+ with pdo_mysql. config/database.php defaults to the local
XAMPP server (127.0.0.1:3306, mypark_sabah, root with a blank password).
MYPARK_DB_HOST, MYPARK_DB_PORT, MYPARK_DB_NAME, MYPARK_DB_USER and
MYPARK_DB_PASSWORD can override those settings in the PHP environment.
Reimport sql/database.sql when updating an existing MyPark database. This file
includes the attendance tables. Uploads work with XAMPP's default packet size.
When sharing the project, keep each machine's config/*.local.php files,
repository metadata, and the legacy SQLite archive outside the shared website.

Alternatively run php -S localhost:8000 router.php from this folder, with
the same MariaDB database running, and open http://localhost:8000.

The PHP development server uses router.php for the site's pages, API routes and
static assets.

Demo accounts:
Super Admin: superadmin@park.com / super123
Admin: admin@park.com / admin123
Staff: staff@park.com / staff123
Member: user@park.com / user123

Styles: assets/css/
Scripts: assets/js/
Images: assets/images/
Admin Dashboard: admin/dashboard.php; staff: staff.php; browser data endpoints: api/
Legacy SQLite archive: storage/parking.db. Keep it as a separate archive.
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

PHP sessions keep sign-in, CSRF tokens, pending registration and password reset
requests, and active booking/payment forms. Keep PHP's session.save_path writable
and outside the web root.

Registration sends a one-time verification link over SMTP. Open it within
10 minutes in the browser where you started registration. Password recovery
sends a six-digit OTP.
In config/smtp.local.php, set email to the SMTP sender address and app_password
to its app password. Git ignores this local config file. Localhost links use
the current port and project path automatically. For other hosts set app_url
to the site's base URL, including any project subfolder.
Gmail uses smtp.gmail.com on port 587
with STARTTLS. PHP cURL with SMTP support and a trusted CA bundle is required.

MySQL stores accounts, parking locations, bookings, wallets, receipts, saved
vehicles, OKU verifications, notifications, fines, maintenance, leave and
attendance. Sign-out leaves those records intact. New sessions use a shared
workspace, so Staff, Admin and members can use separate browsers.

An existing browser with pre-MySQL session records imports them on its first
successful request. The app keeps that workspace separate because old accounts
used browser-specific IDs. Keep the original browser session to open its
workspace. The old vehicles.sqlite3 and attendance.sqlite3 files are read
during migration when present, which requires pdo_sqlite for that first request.
Do not use this prototype for real payments or official enforcement.

To try compound payments, create a fine in Staff → Fine Management, sign out,
then sign in as a member in the same workspace. Open Compounds and search for the
fine number or plate. Only members can use wallet credit, pay parking fees or
top up with PayPal Sandbox. Admin and Super Admin can look up compounds and view
the records available through Dashboard.

Open Receipts to find parking bookings, compound payments and wallet top-ups.
Parking cards and the Parking tab show session details and let you open tickets,
check the countdown or switch to an eligible bay type. Drivers can cancel a session
within the first three minutes and receive a notification once it is cancelled.
The receipt date range
includes today and the previous 29 days in Malaysia time. View or print receipts
from the original account. Receipt numbers contain the
transaction date and time in Malaysia, followed by the vehicle plate or receipt
type. Matching numbers get a short sequence suffix.

PayPal Sandbox top-ups add demo credit. Confirming payment deducts the parking
fee from the demo wallet and starts parking immediately. Future reservations are
not supported. Successful parking payments and top-ups appear in Notifications.
SMS and live support are not connected.
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
"Capacity unavailable". Counts come from the database workspace.
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
Saved locations appear in Find parking for members in the same workspace.

OKU Parking Verification (demo)
Use fictional proof and the demo accounts in the same workspace. A member
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

The app stores pending proof privately in the database and removes the proof
when a decision is recorded. The decision, reviewer and timestamp remain stored.
Enable PHP uploads and Fileinfo, and set upload/post limits to accommodate the
2 MB proof.
Keep request-body logging disabled for uploads and verification requests.

Use Dashboard > Set OKU bay allocation to reserve bays within a location's
existing total. New database workspaces start with one OKU bay at Kota Kinabalu
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

MySQL stores vehicles in member_vehicles and selections in
member_vehicle_preferences. Ownership uses the workspace and member ID. Legacy
session vehicles retain their IDs and default. Normalized duplicate legacy plates
merge into the first saved record. Booking-time plate snapshots stay intact.

The booking page starts with the selected saved vehicle. Add vehicle is also
available there. Edit details in the review lets a member change the vehicle and
review again. Both review and confirmation load the member's vehicle from MySQL,
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
includes/vehicles.php handles vehicle preferences and ownership. Legacy imports
are in includes/database-migration.php.
includes/vehicle-selector.php and assets/js/vehicles.js render and save selection.
dashboard.php places the card. profile.php manages saved vehicle details.
includes/parking-booking.php validates bookings and stores the plate snapshot.
parking-details.php and includes/booking-panel.php handle review and confirmation.
api/vehicles.php handles authenticated member vehicle requests with CSRF checks.

Employee Attendance
Open Employee Attendance as Super Admin. Use Admin Location Access
to assign an Admin to their workplaces, then schedule a shift for a Staff account.
An end time earlier than the start time means the shift ends the following day.
The Admin's existing Leave requests page is linked from Employee Attendance.
Staff open My Attendance from their dashboard to check in, check out, see the
last 31 calendar days of shifts, and request a review or time correction.

Attendance uses the attendance_* tables in MariaDB. PHP needs Fileinfo for
photo validation. Use localhost or HTTPS for camera and location permissions.
Staff and Admin can open the shared workspace from separate browsers.

The default workplace radius is 150 m, maximum GPS error 100 m, and late grace
period 10 minutes. Super Admin can change these for new records. Staff can check
in up to 30 minutes before work begins and before the shift ends. Missing
check-ins are flagged after that shift ends. Any approved full-day leave that
overlaps a shift excludes it from missing-attendance counts, including overnight
shifts. Staff can request corrections when outside the normal recording window.
Super Admin summary cards use the current report filters. The first two count
distinct employees, and the other cards count shifts. Check-in counts include
submissions marked for review. Approved time corrections affect those counts
and durations, while the captured timestamps and review history remain visible.

Photos and raw reported coordinates are available to the employee, scoped Admins,
and Super Admin for up to 90 days, then purged on the next attendance request.
Keep the database and its backups private. CSV reports omit photos, raw GPS
coordinates, and review notes. They retain zone, distance, accuracy and timestamps.
Browser GPS and camera capture are reported evidence. They cannot establish true
physical presence or image authenticity. Failed verification triggers human review.
