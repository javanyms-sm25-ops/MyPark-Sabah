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
Copy config/recaptcha.example.php to config/recaptcha.local.php if the local
file is missing. Set the v3 keys and allowed hostnames there. The local config
is ignored by Git. Enable PHP cURL for login verification.

Records are stored in the current PHP session. Signing out or session expiry clears
accounts created in that session, bookings, top-ups, fines, maintenance and reviews.
Separate browser sessions do not share these records. Do not use this prototype
for real payments or official enforcement. MySQL integration is planned later.

Demo top-ups do not charge money, and bookings do not deduct the wallet balance.
Email OTP/reset delivery, SMS, a payment gateway and live support are not connected.
Photo rotation is a preview; saving keeps the original image.
Third-party fonts, chatbot and ticket QR library require Internet access.
