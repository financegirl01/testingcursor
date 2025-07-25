# Inventory Management System

A modern, responsive web-based inventory management system built with PHP, MySQL, and Tailwind CSS. This system provides comprehensive functionality for managing items, inventory tracking, supplier relationships, and generating detailed reports.

## Features

### 🔐 Authentication & Authorization
- User login system with role-based access (Admin, Manager, Staff)
- Session management and security
- Demo accounts included for testing

### 📦 Item Management
- Add, edit, delete inventory items
- Category organization
- SKU tracking
- Pricing management
- Minimum stock level alerts

### 📊 Inventory Tracking
- Real-time stock level monitoring
- Stock in/out operations
- Inventory adjustments
- Location and batch tracking
- Low stock and out-of-stock alerts

### 🚚 Supplier Management
- Comprehensive supplier database
- Contact information management
- Supplier-item relationships
- Performance tracking

### 📈 Reports & Analytics
- Dashboard with key metrics
- Interactive charts and graphs
- Low stock alerts
- Stock movement history
- Category breakdown analysis
- Top items and suppliers reports

### 💻 Modern UI/UX
- Responsive design (mobile-friendly)
- Clean, intuitive interface
- Modal-based forms
- Real-time feedback
- Print-friendly reports

## Technical Stack

- **Backend**: PHP 7.4+
- **Database**: MySQL 5.7+
- **Frontend**: HTML5, CSS3, JavaScript
- **Framework**: Tailwind CSS
- **Charts**: Chart.js
- **Icons**: Font Awesome 6

## Installation

### Prerequisites

- Web server (Apache, Nginx, or similar)
- PHP 7.4 or higher
- MySQL 5.7 or higher
- PDO MySQL extension enabled

### Setup Instructions

1. **Clone/Download the project**
   ```bash
   git clone <repository-url>
   cd inventory-management-system
   ```

2. **Database Setup**
   - Create a new MySQL database named `inventory_management`
   - Import the database schema and sample data:
   ```bash
   mysql -u your_username -p inventory_management < database.sql
   ```

3. **Configuration**
   - Edit `config/database.php` to match your database credentials:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'your_username');
   define('DB_PASS', 'your_password');
   define('DB_NAME', 'inventory_management');
   ```

4. **Web Server Setup**
   - Point your web server document root to the project directory
   - Ensure PHP has read/write permissions to the project folder
   - Make sure URL rewriting is enabled (if using Apache)

5. **Access the Application**
   - Open your web browser and navigate to your web server
   - You should see the login page

## Demo Accounts

The system comes with pre-configured demo accounts:

| Username | Password | Role | Description |
|----------|----------|------|-------------|
| admin | password123 | Admin | Full system access |
| manager1 | password123 | Manager | Management-level access |
| staff1 | password123 | Staff | Basic user access |

## File Structure

```
inventory-management-system/
├── config/
│   └── database.php          # Database configuration
├── database.sql              # Database schema and sample data
├── login.php                 # User authentication
├── dashboard.php             # Main dashboard
├── items.php                 # Item management
├── inventory.php             # Stock tracking
├── suppliers.php             # Supplier management
├── reports.php               # Analytics and reports
├── logout.php                # Session logout
└── README.md                 # This file
```

## Database Schema

The system uses the following main tables:

- **users**: User accounts and authentication
- **categories**: Item categorization
- **items**: Product/item master data
- **suppliers**: Supplier information
- **inventory**: Current stock levels
- **stock_movements**: Historical stock transactions

## Usage Guide

### Getting Started
1. Log in using one of the demo accounts
2. Visit the Dashboard to see system overview
3. Use the navigation to access different modules

### Managing Items
1. Go to Items page
2. Click "Add Item" to create new products
3. Use filters to search and organize items
4. Edit or delete items as needed

### Inventory Operations
1. Go to Inventory page
2. Use "Stock In" to receive new inventory
3. Use "Stock Out" to record sales/usage
4. Use "Adjust Stock" for corrections
5. Monitor low stock alerts

### Supplier Management
1. Go to Suppliers page
2. Add supplier contact information
3. Link suppliers to inventory items
4. Track supplier performance

### Reports & Analytics
1. Visit Reports page for detailed analytics
2. View charts and trends
3. Monitor low stock alerts
4. Export or print reports

## Security Features

- Password hashing using PHP's password_hash()
- SQL injection prevention with prepared statements
- Session management and timeout
- Input validation and sanitization
- CSRF protection considerations

## Browser Compatibility

- Chrome 60+
- Firefox 55+
- Safari 12+
- Edge 79+

## Troubleshooting

### Common Issues

1. **Database Connection Error**
   - Check database credentials in `config/database.php`
   - Ensure MySQL service is running
   - Verify database exists and user has permissions

2. **Login Issues**
   - Use demo accounts provided above
   - Check that sessions are working properly
   - Ensure PHP session configuration is correct

3. **Charts Not Loading**
   - Check internet connection (CDN resources)
   - Ensure JavaScript is enabled
   - Check browser console for errors

4. **Responsive Design Issues**
   - Clear browser cache
   - Ensure Tailwind CSS is loading properly
   - Check viewport meta tag

## Customization

### Adding New Features
- Follow the existing code structure
- Use prepared statements for database queries
- Implement proper input validation
- Maintain responsive design principles

### Styling Changes
- Modify Tailwind CSS classes
- Add custom CSS if needed
- Ensure mobile compatibility

### Database Modifications
- Always backup before schema changes
- Update related PHP code
- Test thoroughly

## Performance Optimization

- Enable PHP OPcache
- Optimize MySQL queries
- Use database indexing
- Implement proper caching strategies
- Compress images and assets

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## License

This project is open source and available under the [MIT License](LICENSE).

## Support

For support and questions:
- Check the troubleshooting section
- Review the code comments
- Test with demo data first

## Version History

- v1.0.0 - Initial release
  - User authentication
  - Item management
  - Inventory tracking
  - Supplier management
  - Reports and analytics
  - Responsive design

---

**Note**: This is a demo system intended for educational and development purposes. For production use, additional security measures and testing should be implemented.