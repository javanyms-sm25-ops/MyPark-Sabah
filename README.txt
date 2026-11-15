MyPark Sabah — PHP Session Prototype

Requires PHP 8.1+ (tested with PHP 8.5). No MySQL or SQLite connection is used.
From this folder run: php -S localhost:8000
Open http://localhost:8000

Demo accounts:
Super Admin: superadmin@park.com / super123
Admin: admin@park.com / admin123
Staff: staff@park.com / staff123
Member: user@park.com / user123

Styles: assets/css/style.css, assets/css/operations.css, assets/staff.css
Scripts: assets/js/
Staff entry point: staff.php; other roles use the existing dashboard/Operations pages.

Records are stored in the current PHP session. Signing out or session expiry clears
accounts created in that session, bookings, top-ups, fines, maintenance and reviews.
Separate browser sessions do not share these records. Do not use this prototype
for real payments or official enforcement. MySQL integration is planned later.

Demo top-ups do not charge money, and bookings do not deduct the wallet balance.
Email OTP/reset delivery, SMS, a payment gateway and live support are not connected.
Photo rotation is a preview; saving keeps the original image.
Third-party fonts, chatbot and ticket QR library require Internet access.
