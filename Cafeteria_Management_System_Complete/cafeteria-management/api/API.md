# PHP API quick reference

Serve this folder as `/api` using Apache. Example base URL: `http://localhost/cafeteria-management/api`. PHP 8.1+ with PDO MySQL is required. Requests and responses use JSON. PHP session cookies authenticate users; frontend requests must use credentials (`credentials: 'include'`).

| Method | Endpoint | Access | Purpose |
|---|---|---|---|
| POST | `/auth/register` | Public | Customer registration with `password_hash` |
| POST | `/auth/login` | Public | Login and session creation |
| POST | `/auth/logout` | Session | Destroy session |
| GET | `/auth/me` | Session | Current user |
| GET | `/categories` | Public | Active categories |
| GET | `/food-items` | Public | Available menu |
| GET | `/food-items/{id}` | Public | Available item |
| POST | `/orders` | Customer | Create order with server-priced items, payment `INITIATED` |
| POST | `/payments/{orderRef}/claim` | Owner | Submit UPI transaction reference for manual review |
| GET | `/orders/my` | Customer | Order history |
| GET | `/orders/{orderRef}` | Owner/admin | Order and item snapshots |
| GET | `/admin/payments/pending` | Admin | Verification queue |
| POST | `/admin/payments/{paymentId}/decision` | Admin | Approve/reject after checking merchant account |
| GET | `/admin/orders` | Admin | List orders |
| PATCH | `/admin/orders/{orderRef}/status` | Admin | Valid preparation transition only |
| GET | `/admin/dashboard` | Admin | Database-backed summary |
| POST | `/feedback` | Customer | Submit rating and feedback |

Order payload example:
```json
{"customerName":"Student Name","phone":"9876543210","email":"student@example.com","paymentMethod":"UPI","pickup":"As soon as possible","notes":"","items":[{"id":1,"qty":2}]}
```
The server ignores client-submitted prices and totals. An order starts as `AWAITING_PAYMENT`; claiming a transaction only sets `PAYMENT_REVIEW`. The administrator must independently check the actual merchant account before approving. Screenshots and references are not proof of payment by themselves.
