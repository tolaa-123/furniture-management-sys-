# FurnitureCraft — Custom Furniture ERP & E-Commerce Platform

A full-stack PHP ERP system for a custom furniture workshop. Manages the complete workflow from customer order to delivery, including production, payroll, inventory, and profit reporting.

## Roles

| Role | Access |
|---|---|
| Customer | Place orders, track status, make payments |
| Employee | View assigned tasks, complete production, log materials |
| Manager | Cost estimation, assign employees, verify payments, payroll, profit report |
| Admin | Full system access, user management, settings, payroll approval |

## Order Workflow

```
Customer places order
  → Manager estimates cost
  → Customer pays deposit (40%)
  → Manager verifies payment
  → Manager assigns employee
  → Employee completes production (logs materials used)
  → Manager approves for delivery
  → Customer pays remaining (60%)
  → Manager verifies → Order completed
```

## Setup (Local)

1. Clone the repo
2. Copy `config/db_config.example.php` → `config/db_config.php` and fill in your DB credentials
3. Import `database/schema.sql` into your MySQL database
4. Point your web server root to the `public/` folder
5. Visit `http://localhost/public/`

## Tech Stack

- PHP 7.4+ (no framework)
- MySQL 5.7+
- Bootstrap 5
- jQuery
- Font Awesome 6

## Deployment

This project requires a PHP + MySQL host. Vercel alone does not support PHP — use a service like:
- **Railway** (free tier, supports PHP + MySQL)
- **Render** (PHP + MySQL)
- **Hostinger / cPanel shared hosting**
- **DigitalOcean App Platform**

See deployment notes below.

## Environment Variables (Production)

Set these in your hosting environment instead of `db_config.php`:

```
DB_HOST=
DB_PORT=3306
DB_USER=
DB_PASS=
DB_NAME=
BASE_URL=https://your-domain.com
```

