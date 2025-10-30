# Trendy App

A modern Laravel-based sales and inventory management system built with Livewire and TailwindCSS. This application helps manage products, track sales, handle inventory, and analyze business metrics in real-time.

## Features

- 🛍️ Product Management
  - SKU and barcode tracking
  - Cost and selling price management
  - Units per box configuration
  - Profit calculation
- 📊 Sales Analytics
- 💰 Daily Sales Tracking
<!-- - 🏪 Multi-branch Support -->
- 📦 Stock Management
  - Box and unit-level tracking
  - Available stock monitoring
- 🔐 Role-based Access Control
<!-- - 🪙 Coin Sales Tracking -->
- 📈 Sales Summary Reports
- 📱 Real-time Updates with Livewire

## Tech Stack

- **Framework:** Laravel 12.x
- **PHP Version:** 8.2+
- **Frontend:**
  - Livewire 3.6+
  - TailwindCSS 4.x
  - Vite 6.x
- **Database:** PostgreSQL
- **Additional Tools:**
  - Laravel Excel for reports
  - Laravel Debugbar (development)

## Prerequisites

- PHP >= 8.2
- Composer
- Node.js & npm
- PostgreSQL
- Docker (optional, for containerized deployment)

## Local Installation

1. Clone the repository

```bash
git clone https://github.com/Korkoe27/trendy-app.git
cd trendy-app
```

2. Install PHP dependencies

```bash
composer install
```

3. Install JavaScript dependencies

```bash
npm install
```

4. Set up environment file

```bash
cp .env.example .env
php artisan key:generate
```

5. Configure database in `.env`

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=trendy_app
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

6. Run migrations

```bash
php artisan migrate
```

7. Start the development server

```bash
# Run all services (server, queue, logs, vite)
composer run dev

# Or run separately:
php artisan serve
npm run dev
```

<!-- ## Docker Deployment

The application includes Docker configuration for easy deployment:

1. Build the Docker image
```bash
docker build -t trendy-app:latest .
```

2. Run the container
```bash
docker run --rm -p 10000:10000 \
  -e APP_KEY="your_app_key" \
  -e DB_HOST="your_db_host" \
  -e DB_DATABASE="your_db_name" \
  -e DB_USERNAME="your_db_user" \
  -e DB_PASSWORD="your_db_password" \
  trendy-app:latest
``` -->

## Project Structure

- `app/Livewire/` - Livewire components
  - `Pages/` - Page components
  - `Components/` - Reusable UI components
  - `Category/` - Category management components
- `app/Models/` - Eloquent models
- `app/Http/Controllers/` - HTTP controllers
- `database/migrations/` - Database migrations
- `resources/views/` - Blade views
- `routes/web.php` - Web routes

## Key Models

- `User` - User management with roles
- `Product` - Product information and pricing
  - SKU and barcode
  - Cost and selling prices
  - Units per box
- `Stock` - Inventory management
  - Available boxes
  - Available units
- `DailySales` - Daily sales records
<!-- - `CoinSales` - Coin sales tracking -->
<!-- - `Branches` - Branch management -->
- `Categories` - Product categories

<!-- ## Testing

Run the test suite:

```bash
composer test
``` -->

<!-- ## Deployment

The application is configured for deployment on Render.com using Docker. See `render.yaml` for configuration details. -->

## License

This project is licensed under the MIT License. See the LICENSE file for details.

## Contributors

- [Korkoe27](https://github.com/Korkoe27)
