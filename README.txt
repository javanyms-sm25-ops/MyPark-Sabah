MyPark Sabah — PHP Session Prototype

Requires PHP 8.1+ (tested with PHP 8.5). No MySQL or SQLite connection is used.
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
Accounts, saved vehicles, parking locations, OKU verifications, issued compounds
and digital receipts survive sign-out in the same browser. Sign-out clears
booking history, wallet activity, maintenance and customer support reviews.
The browser's demo session expires after roughly a month of inactivity.
Do not use this prototype for real payments or official enforcement.

To try compound payments, create a fine in Staff → Fine Management, sign out,
then sign in as a member, admin or superadmin in the same browser. Open Compounds
and search for the fine number or plate. Payments use demo wallet credit.

Open Receipts to find parking bookings, compound payments and wallet top-ups.
The date range includes today and the previous 29 days in Malaysia time. Receipt
details and email drafts require the original account in the same browser. The
email action opens a draft in your mail app and you can print or save as PDF.

Demo top-ups do not charge money, and bookings do not deduct the wallet balance.
Parking starts when you confirm the review. Future reservations are not supported.
Email OTP/reset delivery, SMS, a payment gateway and live support are not connected.
Photo rotation is a preview; saving keeps the original image.
Third-party fonts, chatbot and ticket QR library require Internet access.

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
separate browsers. No database migration is needed for this prototype.

Use Operations > Set OKU bay allocation to reserve bays within a location's
existing total. New demo sessions start with one OKU bay at Kota Kinabalu
Waterfront. Locations missing an allocation default to zero. Existing stays
without a bay type count as regular. Verified members can choose the OKU type
at booking or switch an active stay from History. A switch is for remaining
time at the same location and retains the current fee. Booking review,
confirmation, history changes and the map use the same per-type counts.
