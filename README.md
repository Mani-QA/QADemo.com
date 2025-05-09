# E-commerce Testing Platform

A PHP/SQLite-based e-commerce website specifically designed for automated testing purposes. This platform provides a complete e-commerce experience with user authentication, product catalog, shopping cart, checkout process, and admin functionality.

## Features

- **User Authentication**
  - Three user types: Standard, Locked, and Admin
  - Secure login system with CSRF protection
  - Session management

- **Product Catalog**
  - Responsive grid layout
  - Product details with images
  - Stock management
  - Dynamic cart functionality

- **Shopping Cart**
  - Real-time quantity updates
  - Stock validation
  - Dynamic total calculation
  - Persistent cart state

- **Checkout Process**
  - Shipping information validation
  - Payment processing with card validation
  - Order confirmation
  - Stock updates

- **Admin Dashboard**
  - Inventory management
  - Order history
  - Stock level modification

## Requirements

- PHP 7.4 or higher
- SQLite3
- Web server (Apache/Nginx)

## Installation

1. Clone the repository to your web server directory:
   ```bash
   git clone <repository-url>
   cd ecommerce-testing-platform
   ```

2. Create the required directories:
   ```bash
   mkdir database
   mkdir images
   ```

3. Set proper permissions:
   ```bash
   chmod 755 database
   chmod 755 images
   ```

4. Ensure the web server has write permissions to the database directory.

## Default Users

The platform comes with three pre-configured users:

1. Standard User
   - Username: standard_user
   - Password: standard123

2. Locked User
   - Username: locked_user
   - Password: locked123

3. Admin User
   - Username: admin_user
   - Password: admin123

## Testing Features

### User Authentication Testing
- Test login with valid/invalid credentials
- Verify locked user functionality
- Test session management
- Verify CSRF protection

### Product Catalog Testing
- Test responsive design
- Verify product display
- Test image loading
- Verify stock display

### Shopping Cart Testing
- Test add/remove items
- Verify quantity updates
- Test stock validation
- Verify total calculation

### Checkout Testing
- Test form validation
- Verify card number validation (Luhn algorithm)
- Test address validation
- Verify order processing

### Admin Testing
- Test inventory management
- Verify order history
- Test stock updates
- Verify user permissions

## Security Features

- CSRF Protection
- Input Sanitization
- SQL Injection Prevention
- XSS Prevention
- Secure Password Storage

## Contributing

1. Fork the repository
2. Create your feature branch
3. Commit your changes
4. Push to the branch
5. Create a new Pull Request

## License

This project is licensed under the MIT License - see the LICENSE file for details. 