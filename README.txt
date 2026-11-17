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

MyPark stores demo data in a private app folder under PHP's session.save_path
(or the system temporary folder if that setting is empty). PHP must be able to
write there. Keep this location outside the web root.
Accounts, issued compounds and digital receipts survive sign-out in the same
browser. Sign-out clears booking history, wallet activity, maintenance and reviews.
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
Email OTP/reset delivery, SMS, a payment gateway and live support are not connected.
Photo rotation is a preview; saving keeps the original image.
Third-party fonts, chatbot and ticket QR library require Internet access.
