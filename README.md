# Zoro's Grill & Gulp - Food Ordering System

## Overview
Zoro's Grill & Gulp is a full-stack web application for a food ordering platform. Customers can browse menus, place orders, register/login, manage subscriptions, and handle password resets via OTP. Admins can view reports, manage orders, track sales, and send daily SMS reports.

**Key Features:**
- User registration/login with mobile/email and referral codes
- Menu browsing and order placement
- Subscription orders with admin approval/rejection
- Forgot password with OTP verification
- Store status management (open/closed)
- Admin dashboard with filtered reports (sales, items sold, custom invoices)
- Daily sales SMS reports via Twilio
- File-based data storage (JSON)

## Tech Stack
- **Frontend**: React.js (built static files)
- **Backend**: PHP (REST API with Composer)
- **Dependencies**: Twilio (SMS), Guzzle (HTTP), phpdotenv, Ratchet (WebSockets?), Symfony polyfills
- **Database**: JSON files (no external DB)
- **Deployment**: Static hosting (e.g., Netlify/Vercel) for frontend + PHP server (Apache/Nginx) for API

## Project Structure
```
Food_Ordering_System/
├── index.html                 # React app entry
├── static/                    # Built React assets (CSS/JS/media)
├── api/                       # PHP Backend
│   ├── api.php               # Main API router & logic
│   ├── router.php            # URI routing
│   ├── report.php            # Sales reports
│   ├── custom_invoices.php   # Custom invoices
│   ├── composer.json/lock    # Dependencies
│   └── data/                 # JSON data files
│       ├── menu.json         # Menu items
│       ├── orders.json       # Orders
│       ├── users.json        # Users
│       ├── subscriptions.json # Subscriptions
│       ├── store_status.json # Store status
│       ├── password_resets.json
│       └── custom_invoices.json
├── data/                     # Duplicate/backup data files (root)
├── myproject/                # Backup of api files
├── build/                    # React build artifacts
└── ... (asset-manifest.json, etc.)
```

**Note**: Root `data/` duplicates `api/data/`. Use `api/data/` for production.

## Quick Setup
1. **Frontend**:
   - Serve `index.html` and `static/` via any static server (e.g., `npx serve .` or Apache/Nginx).
   - Open `http://localhost:3000` (adjust port).

2. **Backend** (API):
   - Ensure PHP 8+ with `curl`, `json` extensions.
   - Navigate to `api/`:
     ```
     cd api
     composer install  # Install deps (Twilio, etc.)
     ```
   - Set `.env` (if needed for Twilio keys via phpdotenv):
     ```
     TWILIO_SID=your_sid
     TWILIO_TOKEN=your_token
     TWILIO_PHONE=your_phone
     ```
   - Serve via PHP dev server:
     ```
     php -S localhost:8000
     ```
   - API base: `http://localhost:8000/api/`

3. **Initialize Data** (if empty):
   - First menu GET/POST will auto-init `menu.json`.

4. **Test**:
   - Menu: `GET /api/menu`
   - Register: `POST /api/register` with JSON `{mobile, name, email?, password}`

## API Endpoints
All endpoints under `/api/`. CORS enabled. JSON input/output.

### Auth
- `POST /api/register` - `{name, mobile, email?, password}` → User + referral code
- `POST /api/login` - `{mobile, password}` or `{email, password}`
- `POST /api/forgot-password/send-otp` - `{mobile/email}`
- `POST /api/forgot-password/reset` - `{mobile/email, otp, newPassword}`

### Core
- `GET /api/menu` - Fetch menu
- `GET/POST /api/store-status` - `{isOpen: bool}`
- `GET /api/orders/store` - All orders
- `POST /api/orders/store` - Place order: `{customerName, phone, address, items: [{name, price, quantity}], totalAmount, paymentMode?, orderId?}`
- `GET /api/orders/subscriptions` - Subscription orders
- `POST /api/orders/subscriptions` - Subscribe: `{customerName, phone, items, totalAmount, subscriptionId?, referralCode?}`
- `PUT /api/orders/subscriptions/{id}/reject` - Reject sub

### Admin
- `POST /api/admin/data` - `{filterType: 'today|week|month', filterValue: date}` → Reports (sales, items sold, invoices, etc.)

### Data Files
Edited directly via API. Backup recommended.

## Features in Detail
- **Referrals**: Auto-generated code on register (e.g., based on name/mobile).
- **Orders**: Track items (JSON array), payment status, delivery.
- **Subscriptions**: Similar to orders, admin approves/rejects.
- **Reports**: Daily sales SMS via Twilio. Items sold map, totals.
- **Security**: Session-based admin? Basic auth in code.

## Development
- Frontend: Edit React source (not in repo; rebuild via `npm run build` if src available).
- Backend: Edit `api/api.php`. Add endpoints there.
- Testing: Use Postman/Insomnia for API.
- SMS: Configure Twilio in env. Used for reports/OTPs.

