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
Admin: admin/; staff: staff.php; browser data endpoints: api/
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

Accounts, parking locations, OKU verifications, issued compounds
and digital receipts survive sign-out in the same browser. Sign-out clears
booking history, wallet activity, maintenance and customer support reviews.
The browser's demo session expires after roughly a month of inactivity.
Saved vehicles and their default/selected preferences persist in SQLite and
survive sign-out using the same browser-specific demo account identity.
Do not use this prototype for real payments or official enforcement.

To try compound payments, create a fine in Staff → Fine Management, sign out,
then sign in as a member, admin or superadmin in the same browser. Open Compounds
and search for the fine number or plate. Payments use demo wallet credit.

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
Open admin/ as Super Admin. The panel above Reservation Management uses the same
Operations snapshot as the table. Each active stay counts as one occupied bay
at its location. Available bays = max(0, capacity minus occupied bays) for each
bay type. Completed and cancelled stays do not occupy bays. Missing capacity is shown as
"Capacity unavailable". Counts come from this browser's MyPark demo session.
Select a marker or a location in the list to set the table's Location filter.
Completing a stay or using Refresh availability updates the counts and time.

To turn on the Google map, enable Maps JavaScript API and billing in your Google
Cloud project. Restrict a browser API key to Maps JavaScript API and to the sites
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

OKU Parking Verification (demo)
Use fictional proof and the demo accounts in the same browser. A member
submits through Profile > OKU Parking Verification. A Super Admin can review
in Operations > OKU Verification, or grant review access to an Admin there.
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

Use Operations > Set OKU bay allocation to reserve bays within a location's
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
