# Cafeteria Management System (Campus Café)

**B.Tech Project · Review 2 · Group GC14**
Dept. of Electronics & Telecommunication Engineering, Pimpri Chinchwad College of Engineering, Pune · A.Y. 2026-27

| Member | PRN |
|---|---|
| Yash Unhale | 123B1E110 |
| Varad Wakde | 123B1E115 |
| Nutan Shinde | 123B5E188 |

**Guide:** Mr. Atul Pawar

## Project description
A web-based platform that digitizes cafeteria operations. Customers register, browse the menu, select items and quantities, **pay first**, and receive an order confirmation and bill. Administrators manage food items, categories, customers and orders from a dashboard. See the project synopsis for the full problem statement, objectives and scope.

## Current status
This archive preserves the Review 2 frontend and adds a Spring Boot + MySQL backend starter with a server-calculated order total and manual UPI payment verification queue.

**Important:** the existing browser UI still runs in mock/localStorage mode and its checkout simulates payment success. The new backend is not yet fully wired into every existing frontend page. Do not describe the browser demo as real payment verification. See `TESTING.md` for exactly what was checked and what remains.

## Technology
- HTML5, CSS3
- Bootstrap 5.3 and Bootstrap Icons (bundled locally in `assets/vendor`, so the demo works offline)
- JavaScript (ES6+), no frameworks
- LocalStorage / mock data (a demo substitute, **not** a database)

## How to run
1. Open the `cafeteria-management` folder in **VS Code**.
2. Install the **Live Server** extension (Ritwick Dey).
3. Right-click `index.html` → **Open with Live Server**.

Any simple static server also works, e.g. `python -m http.server 5500` and then open `http://localhost:5500`.
Opening files directly with `file://` mostly works, but Live Server is recommended.

## Demo logins (frontend demo only, NOT real authentication)
| Role | Email | Password |
|---|---|---|
| Customer | `student@cafeteria.com` | `student123` |
| Admin | `admin@cafeteria.com` | `admin123` |

Admin panel: `admin/admin-login.html` (also linked in the footer). Use **Reset demo data** on the admin dashboard to restore the sample data.

## Current implementation
**Customer:** home page, signup/login with validation, menu with search / category filter / sorting / pagination, food details with quantity selector, working cart (localStorage), checkout with validation, **mock Pay First payment** (UPI / card / wallet UI + simulated verification), order creation with generated order ID, order confirmation with printable bill, order history with details, feedback with star rating, logout.

**Admin:** demo login, dashboard (orders, customers, items, revenue, pending orders, recent orders, popular items, 7-day revenue chart), food item management (add / edit / delete / search / filter), category management, customer list (search, block/activate), order list with search, filter and **status updates** (Pending → Preparing → Ready → Completed / Cancelled).

## Project structure
```
index.html, login.html, signup.html, menu.html, food-details.html,
cart.html, checkout.html, order-success.html, orders.html, feedback.html
admin/   admin-login, admin-dashboard, admin-items, admin-orders, admin-users, admin-categories
css/     style.css (global) · auth.css · menu.css · admin.css
js/      mockData.js   sample data (users, categories, foodItems, orders, feedback, admin)
         api.js        ALL data access, the only file that will change for the backend
         auth.js, menu.js, cart.js, checkout.js, orders.js, feedback.js, admin.js   page logic
         ui.js         shared navbar/footer, toasts, validation helpers, route guards
assets/  images/ (project photos) · vendor/ (Bootstrap, Bootstrap Icons)
```

## Connecting the Java Spring Boot backend (next phase)
Every page calls async functions in `js/api.js` (`loginUser`, `registerUser`, `getFoodItems`, `getCategories`, `createOrder`, `getOrders`, `submitFeedback`, `getAdminDashboard`, `addFoodItem`, `updateFoodItem`, `deleteFoodItem`, …). Each has a `// Future API:` comment with the intended endpoint.

To switch: set `API_BASE_URL`, then replace each function body with `apiRequest(method, path, body)` (a ready fetch helper is already in `api.js`). The UI does not need to change.

| Function | Planned endpoint |
|---|---|
| `loginUser` / `registerUser` | `POST /api/auth/login` · `POST /api/auth/register` |
| `getFoodItems` / `getCategories` | `GET /api/food-items` · `GET /api/categories` |
| `createOrder` | `POST /api/orders` (server recalculates total, verifies payment) |
| `getOrders` / `updateOrderStatus` | `GET /api/orders/my`, `GET /api/admin/orders` · `PATCH /api/admin/orders/{id}/status` |
| `submitFeedback` | `POST /api/feedback` |
| `getAdminDashboard` | `GET /api/admin/dashboard` |
| admin CRUD | `/api/admin/food-items`, `/api/admin/categories`, `/api/admin/users` |

## Future implementation
- Java Spring Boot backend and REST APIs
- MySQL database
- Real authentication (hashed passwords, JWT, roles)
- Real payment gateway integration and payment verification
- Server-side validation and security
- Deployment

Later enhancements from the synopsis: QR-based ordering, mobile app, inventory management, real-time notifications, analytics, recommendations.

## Food photos
Only coffee and cafeteria photos came with the original project, so other items show an emoji tile. To add a real photo, save it in `assets/images/food/` and set that item's `image` field in `js/mockData.js`, for example `image: "assets/images/food/masala-dosa.jpg"`. (If you change seed data on a machine that already opened the demo, click **Reset demo data** on the admin dashboard to reload it.)

## Credits
The initial PHP/MySQL version of this project was taken from the public repository *cafeteria-management* by Sanika Dethe (reference [1] in the synopsis). This frontend was rebuilt with Bootstrap 5 for Review 2.



## PHP/MySQL API (new)
The Review 2 presentation specifies PHP, MySQL, Apache/XAMPP and web standards. The `api/` directory contains a PHP 8.1+ PDO API, and `database/database.sql` contains the MySQL schema and seed menu. The API includes registration/login, menu reads, server-priced order creation, pending UPI claims, admin payment decisions, order-status transition checks, dashboard summary, and feedback submission.

### Setup on Windows with XAMPP
1. Copy the extracted `cafeteria-management` folder into `C:\\xampp\\htdocs\\`.
2. Start **Apache** and **MySQL** in XAMPP.
3. Import `database/database.sql` in phpMyAdmin or MySQL Workbench.
4. Check database credentials in `api/config.php` (default XAMPP MySQL user is often `root` with an empty local password).
5. Create an admin using a terminal with PHP available: `php api/create-admin.php admin@example.com 'Use-A-Long-Unique-Password'`.
6. Configure `CAFETERIA_UPI_ID` in the PHP environment for your merchant UPI ID.
7. API base URL will be `http://localhost/cafeteria-management/api`.
8. `api/API.md` documents endpoints and payloads.

### Payment safety
An API-created order begins as `AWAITING_PAYMENT`. Submitting a transaction reference creates a pending claim; it does not prove payment. The admin must check the actual merchant transaction independently before approving. Only a verified payment becomes `CONFIRMED` and eligible for preparation. No payment gateway credentials are invented.

### Important limitation
The supplied frontend is preserved, but it still uses localStorage mock data and its checkout simulates success. The PHP API has **not yet been fully connected to every existing frontend screen**, so do not present the browser demo as real payment verification. `TESTING.md` documents checks actually performed and remaining work. The archive follows the PPT's PHP/MySQL stack, but it is not yet a fully integrated production-ready application.
