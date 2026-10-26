# Testing and verification record

## Checks actually performed
- Inspected the supplied ZIP and the existing frontend API/checkout/admin files.
- Confirmed all existing JavaScript files pass `node --check`.
- Verified that the final archive includes the original frontend, PHP API source, SQL schema, setup docs, and test notes.
- Verified ZIP integrity and required files.
- PHP CLI was not available in the execution environment, and no MySQL service was available. PHP syntax checks, database integration, and browser end-to-end tests could not be run here.

## Important integration status
The Review 2 PPT specifies HTML/CSS/JavaScript + PHP + MySQL + Apache/XAMPP. This archive now includes a PHP/PDO API and SQL schema following that stack. However, the existing frontend still uses localStorage mock data and its checkout simulates success; every page has not yet been wired to the PHP API. Do not use the mock checkout as real payment verification.

## Manual test plan
1. Start Apache and MySQL in XAMPP.
2. Import `database/database.sql` in phpMyAdmin or MySQL Workbench.
3. Copy the project folder under `xampp/htdocs/` so `api/index.php` is reachable.
4. Configure database credentials in `api/config.php` or PHP environment variables.
5. Create an administrator from a terminal in the project folder: `php api/create-admin.php admin@example.com 'Use-A-Long-Unique-Password'`.
6. Set `CAFETERIA_UPI_ID` and `CORS_ORIGIN` in the PHP environment as appropriate.
7. Register a customer through `POST /api/auth/register`; login via `POST /api/auth/login`.
8. Check `GET /api/categories` and `GET /api/food-items`.
9. Create an order using `POST /api/orders` with a valid session cookie. Verify the total uses DB prices.
10. Submit a transaction reference to `POST /api/payments/{orderRef}/claim`; verify the order remains `PAYMENT_REVIEW`.
11. Login as admin, inspect `GET /api/admin/payments/pending`, and only approve after checking the merchant transaction outside the app.
12. Verify unpaid orders cannot be advanced and only valid status transitions are accepted.
13. Test duplicate transaction reference, customer ownership, unauthorized admin routes, failed DB connection, feedback, and browser refresh/session behavior.

## Known gaps
- Full frontend-to-PHP integration is not yet completed; frontend currently stays in mock mode.
- Full food-item/category/customer admin CRUD and feedback admin listing are not implemented in the PHP API.
- Payment is manual UPI verification only; no gateway SDK or automatic verification is present.
- Configure same-origin hosting where possible. If the frontend and API use different origins, cookie SameSite/Secure settings and CSRF protection need careful configuration.
- Add rate limiting, session cookie hardening, CSRF protection, full automated tests, and security review before real deployment.
