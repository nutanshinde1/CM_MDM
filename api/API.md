# PHP API quick reference

Serve this folder as /api using Apache. Example base URL: http://localhost/cafeteria-management/api. PHP 8.1+ with PDO MySQL is required. Requests and responses use JSON. PHP session cookies authenticate users; frontend requests must use credentials (credentials: 'include').

## Environment variables
Set the following (via Apache env, system env, or pi/.env loaded by env-loader.php):
- DB_HOST, DB_NAME, DB_USER, DB_PASSWORD
- CAFETERIA_UPI_ID (optional legacy/manual UPI)
- CORS_ORIGIN
- RAZORPAY_KEY_ID (expose to frontend via /payments/create-order response as keyId)
- RAZORPAY_KEY_SECRET (server-only)
- RAZORPAY_WEBHOOK_SECRET (server-only for webhook verification)

Never commit real secrets. Use .env.example as template.

## Endpoints
| Method | Endpoint | Access | Purpose |
|---|---|---|---|
| POST | /auth/register | Public | Customer registration with password_hash |
| POST | /auth/login | Public | Login and session creation |
| POST | /auth/logout | Session | Destroy session |
| GET | /auth/me | Session | Current user |
| GET | /categories | Public | Active categories |
| GET | /food-items | Public | Available menu |
| GET | /food-items/{id} | Public | Available item |
| POST | /payments/create-order | Customer | Validate cart server-side, create internal order+payment, create Razorpay order (INR, paise). Returns azorpayOrderId, mountPaise, keyId, orderRef. |
| POST | /payments/verify | Customer | Verify Razorpay signature + payment status with Razorpay API. Idempotent; only marks order paid/confirmed after verification. |
| POST | /payments/webhook | Public (signature verified) | Verify webhook signature with raw body; handle payment.captured/payment.failed idempotently. |
| POST | /orders | Customer | Legacy/manual flow (INITIATED/AWAITING_PAYMENT). |
| POST | /payments/{orderRef}/claim | Owner | Submit UPI transaction reference for manual review (legacy). |
| GET | /orders/my | Customer | Order history |
| GET | /orders/{orderRef} | Owner/admin | Order and item snapshots |
| GET | /admin/payments/pending | Admin | Verification queue |
| POST | /admin/payments/{paymentId}/decision | Admin | Approve/reject after checking merchant account |
| GET | /admin/orders | Admin | List orders |
| PATCH | /admin/orders/{orderRef}/status | Admin | Valid preparation transition only; requires paid payment |
| GET | /admin/dashboard | Admin | Database-backed summary |
| POST | /feedback | Customer | Submit rating and feedback |

## Razorpay flow notes
- Amounts are calculated server-side from DB prices; client totals/prices are ignored.
- POST /payments/create-order uses Razorpay API with Basic Auth (keyId:keySecret). Never expose secret to frontend.
- POST /payments/verify validates signature using HMAC-SHA256 of azorpay_order_id|razorpay_payment_id with RAZORPAY_KEY_SECRET and checks payment amount/currency/status.
- Only verified payments set orders.payment_status='PAID' and orders.status='CONFIRMED'. Failed/cancelled payments do not create confirmed orders.
- Webhook requires RAZORPAY_WEBHOOK_SECRET and verifies signature against raw request body.
