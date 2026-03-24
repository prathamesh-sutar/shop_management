# Surveillance Shop Management System

A complete, production-ready web application built with **PHP + MySQL + Bootstrap 5** for managing a CCTV and surveillance equipment shop.

---

## Features

| Module | Capabilities |
|---|---|
| **Authentication** | Secure login, password hashing, session management, role-based access (Admin / Employee) |
| **Dashboard** | Live stats cards, monthly revenue chart, recent sales, low-stock alerts |
| **Products** | CRUD, category filter, search, image upload, low-stock highlighting |
| **Customers** | CRUD, search, purchase history, service request history |
| **Sales / Invoices** | Multi-product invoices, auto stock deduction, discount & tax, payment methods, printable invoice |
| **Inventory** | Stock tracking, manual adjustment, inventory log, out-of-stock and low-stock views |
| **Service Requests** | CRUD, status workflow (Pending → Assigned → In Progress → Completed), employee assignment |
| **Employees** | CRUD, optional linked login account creation |
| **Reports** | Daily sales, monthly sales, top products, inventory valuation, service request report, Chart.js charts |

---

## Tech Stack

- **Frontend**: HTML5, CSS3, Bootstrap 5.3, Bootstrap Icons, Chart.js 4
- **Backend**: PHP 8+ (procedural with helper functions)
- **Database**: MySQL 5.7+ / MariaDB 10.4+
- **Environment**: XAMPP / Apache

---

## Project Structure

```
surveillance-shop/
│
├── assets/
│   ├── css/style.css          # Custom styles
│   ├── js/main.js             # Sidebar toggle, AJAX helpers, sales helpers
│   └── images/products/       # Uploaded product images (auto-created)
│
├── config/
│   └── database.php           # DB credentials + getDB() singleton
│
├── includes/
│   ├── auth_check.php         # requireLogin(), requireRole(), e(), flash helpers
│   ├── header.php             # <head>, navbar, wrapper open
│   ├── sidebar.php            # Left-nav with active-page highlight
│   └── footer.php             # Scripts, wrapper close
│
├── auth/
│   ├── login.php              # Login form + POST handler
│   └── logout.php             # Session destroy + redirect
│
├── admin/
│   ├── dashboard.php          # Stats overview + charts
│   ├── products.php           # Product CRUD
│   ├── customers.php          # Customer CRUD + history modal
│   ├── sales.php              # Invoice list, new invoice, invoice view/print
│   ├── inventory.php          # Stock levels + manual adjustments
│   ├── services.php           # Service request CRUD
│   ├── employees.php          # Employee CRUD (admin only)
│   └── reports.php            # Filterable reports + charts
│
├── api/
│   ├── product_api.php        # JSON: search/get products
│   ├── sales_api.php          # JSON: sales summary
│   └── inventory_api.php      # JSON: low-stock count/list (used by navbar badge)
│
├── database/
│   └── surveillance_shop.sql  # Full schema + sample data
│
└── index.php                  # Entry point (redirects to login/dashboard)
```

---

## Installation Guide

### 1. Prerequisites
- Install [XAMPP](https://www.apachefriends.org/) (PHP 8.0+, MySQL, Apache)
- Start **Apache** and **MySQL** from the XAMPP Control Panel

### 2. Copy the project
Place this folder inside your XAMPP `htdocs` directory:
```
C:\xampp\htdocs\surveillance-shop\
```
*(on macOS/Linux: `/opt/lampp/htdocs/surveillance-shop/`)*

### 3. Import the database
1. Open your browser and go to `http://localhost/phpmyadmin`
2. Click **New** → create a database named `surveillance_shop`
3. Select the new database, click **Import**
4. Choose `database/surveillance_shop.sql` and click **Go**

### 4. Configure database connection
Open `config/database.php` and update if needed:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');          // your MySQL root password
define('DB_NAME', 'surveillance_shop');
```

### 5. Run the project
Open your browser and navigate to:
```
http://localhost/surveillance-shop/
```

---

## Demo Credentials

| Role | Email | Password |
|---|---|---|
| Admin | admin@surveillance.com | Admin@123 |
| Employee | employee@surveillance.com | Admin@123 |

> **Note**: Passwords are hashed with `password_hash()` / `PASSWORD_DEFAULT`.  
> Change credentials immediately in a production environment.

---

## Security Notes

- All database queries use **prepared statements** (MySQLi)
- Passwords are hashed with `password_hash()` / verified with `password_verify()`
- Output is escaped with `htmlspecialchars()` via the `e()` helper
- Session IDs are regenerated on login to prevent session fixation
- Role-based page protection via `requireLogin()` / `requireRole()`

---

## Customization

- **Low stock threshold**: change `LOW_STOCK_THRESHOLD` in `config/database.php` (default: 5)
- **App name**: change `APP_NAME` in `config/database.php`
- **Product categories**: update the `ENUM` in `database/surveillance_shop.sql` and the `$validCategories` array in `admin/products.php`
