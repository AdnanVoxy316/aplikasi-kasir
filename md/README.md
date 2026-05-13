# Kasir Pintar - POS System

A professional point-of-sale (POS) system built with PHP, Bootstrap 5, and MySQL.

## 📁 Project Structure

```
aplikasi-kasir/
├── assets/
│   ├── css/
│   │   └── style.css           # Custom styling
│   ├── js/
│   │   └── script.js            # Client-side interactivity
│   └── img/                     # Product images directory
├── config/
│   └── database.php             # MySQL database connection
├── includes/
│   ├── sidebar.php              # Sidebar navigation component
│   └── header.php               # Header component with clock
├── index.php                    # Main dashboard page
├── database.sql                 # Database schema and sample data
└── README.md                    # This file
```

## 🎨 Features

### Dashboard Layout

- **Sidebar Navigation**: Dark navy blue (#1a2847) with icons for Dashboard, Transaction, Products, Reports, and Settings
- **Header**: Shows "Kasir Pintar" branding, Online status indicator, and real-time clock
- **Stat Cards**: 4 summary cards displaying:
  - Total Sales
  - Total Items
  - Low Stock
  - Customers
- **Responsive Design**: Works on desktop, tablet, and mobile devices

### Technology Stack

- **Frontend**: HTML5, CSS3, Bootstrap 5
- **Backend**: PHP 7.0+
- **Database**: MySQL 5.7+
- **Icons**: Font Awesome 6.4
- **Font**: Google Fonts - Inter

## 🚀 Setup Instructions

### Prerequisites

- XAMPP or similar PHP/MySQL server
- MySQL 5.7+
- PHP 7.0+

### Installation

1. **Extract files** to `C:\xampp\htdocs\aplikasi-kasir`

2. **Create Database**:
   - Open phpMyAdmin: `http://localhost/phpmyadmin`
   - Import `database.sql` file
   - This creates database `aplikasi_kasir` with tables and sample data

3. **Configure Database Connection** (if needed):
   - Edit `config/database.php`
   - Update credentials if using non-default MySQL settings:
     ```php
     define('DB_HOST', 'localhost');
     define('DB_USER', 'root');
     define('DB_PASS', '');
     define('DB_NAME', 'aplikasi_kasir');
     ```

4. **Access Application**:
   - Open browser and go to: `http://localhost/aplikasi-kasir`

## 📂 File Descriptions

### `/assets/css/style.css`

- All custom styling
- Sidebar styling (dark navy, animations, hover effects)
- Header styling (sticky, responsive layout)
- Stat card styling with hover animations
- Responsive design rules

### `/assets/js/script.js`

- Real-time clock functionality (updates every second)
- Sidebar menu active state handling
- Future interactivity for dynamic features

### `/config/database.php`

- MySQL connection setup
- Error handling
- UTF-8 charset configuration

### `/includes/sidebar.php`

- Reusable sidebar navigation component
- Menu items with Font Awesome icons
- Navigation links for future pages

### `/includes/header.php`

- Reusable header component
- Application title
- User status indicator
- Real-time clock display

### `/database.sql`

Database schema includes:

**Products Table**:

- `id`: Auto-increment primary key
- `code`: Unique product code/SKU
- `name`: Product name
- `price`: Product price (DECIMAL)
- `stock`: Stock quantity
- `image`: Image filename reference
- `description`: Product description
- `category`: Product category
- `created_at`: Timestamp
- `updated_at`: Timestamp

**Sample Data**: 8 products with realistic inventory

**Future Tables** (prepared):

- `transactions`: Sales transactions
- `transaction_items`: Items in each transaction

## 🎯 Usage

### Adding New Pages

1. Create new PHP file (e.g., `products.php`)
2. Include the same structure:
   ```php
   <?php
   require_once 'config/database.php';
   ?>
   <!DOCTYPE html>
   <html lang="en">
   <head>
       <!-- Same head structure -->
       <link rel="stylesheet" href="assets/css/style.css">
   </head>
   <body>
       <?php include 'includes/sidebar.php'; ?>
       <div class="main-content">
           <?php include 'includes/header.php'; ?>
           <!-- Page content here -->
       </div>
       <script src="assets/js/script.js"></script>
   </body>
   </html>
   ```

### Styling Guidelines

- **Color Palette**:
  - Primary Dark: `#1a2847` (sidebar)
  - Accent Cyan: `#00d4ff` (active states)
  - Background: `#f8fafc` (light gray)
  - Text: `#1a2847`, `#64748b`, `#94a3b8`

- **Icons**: All icons use Font Awesome 6.4
- **Font**: Inter (Google Fonts)
- **Breakpoints**: Mobile (576px), Tablet (768px), Desktop (1024px+)

## 🔒 Security Notes

- Store database credentials in `config/database.php`
- Use prepared statements for all database queries
- Implement user authentication before going live
- Validate all user inputs
- Use HTTPS in production

## 📝 Next Steps

1. Implement authentication system
2. Create products.php with CRUD operations
3. Implement transaction processing
4. Add reports and analytics
5. Create settings/admin panel
6. Implement order management

## 📄 License

Created for Kasir Pintar POS System

## 📧 Support

For issues or questions, please refer to the code comments or create appropriate ticket channels.
