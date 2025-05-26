# Kenesfood E-commerce Platform
An enterprise-grade e-commerce platform built with CodeIgniter 3, featuring a microservices architecture and containerized deployment.

## 🏗 System Architecture

### Infrastructure Stack
- **Web Server**: Nginx 1.22.0
- **Database Server**: MariaDB 10.4.10
- **PHP Version**: 7.2.9
- **Database Client**: mysqlnd 5.0.12-dev
- **Container Platform**: Docker
- **SSL Support**: Configurable (Currently disabled)

### Database Specifications
- **Server Type**: MariaDB (mariadb.org binary distribution)
- **Protocol Version**: 10
- **Server Charset**: cp1252 West European (latin1)
- **Connection Method**: TCP/IP
- **Administration Tool**: phpMyAdmin 5.2.1

### PHP Extensions
- mysqli
- curl
- mbstring
- Additional required extensions

## 🚀 Core Features

### Product Management System
```php
Modules:
- Product Catalog Management
- Category Hierarchy
- Inventory Management
- Price Management
- Product Images & Media
```

### Order Processing System
```php
Features:
- Real-time Order Tracking
- Multi-status Order Management
- Payment Integration
- Order History
- Invoice Generation
```

### User Management
```php
Capabilities:
- Role-based Access Control
- Member Management
- Admin Dashboard
- User Authentication
- Profile Management
```

### E-commerce Features
```php
Components:
- Shopping Cart System
- Wishlist Management
- Product Reviews
- Promotional System
- Checkout Process
```

## 🛠 Technical Implementation

### Docker Configuration

```yaml
version: '3.8'

services:
  app:
    build:
      context: .
      dockerfile: Dockerfile
    ports:
      - "80:80"
    volumes:
      - .:/var/www/html
    depends_on:
      - db

  db:
    image: mariadb:10.4
    environment:
      MYSQL_ROOT_PASSWORD: ${DB_ROOT_PASSWORD}
      MYSQL_DATABASE: ${DB_DATABASE}
      MYSQL_USER: ${DB_USERNAME}
      MYSQL_PASSWORD: ${DB_PASSWORD}
    volumes:
      - dbdata:/var/lib/mysql
    ports:
      - "3306:3306"

  nginx:
    image: nginx:1.22
    ports:
      - "8080:80"
    volumes:
      - ./nginx.conf:/etc/nginx/conf.d/default.conf
    depends_on:
      - app

volumes:
  dbdata:
```

### Directory Structure

```
kenesfood/
├── application/
│   ├── controllers/
│   │   ├── apis/            # API Controllers
│   │   ├── master/          # Master Data Controllers
│   │   ├── order/           # Order Processing
│   │   └── public/          # Public Access Controllers
│   ├── models/              # Database Models
│   ├── views/               # View Templates
│   └── config/              # Configuration Files
├── docker/
│   ├── nginx/               # Nginx Configuration
│   ├── php/                 # PHP Configuration
│   └── mysql/               # MySQL Configuration
└── docker-compose.yml
```

## 📦 Installation & Deployment

### Prerequisites
- Docker Engine 20.10+
- Docker Compose 2.0+
- Git

### Development Setup
1. Clone the repository:
```bash
git clone https://github.com/Vistapra/Kenesfood.git
cd kenesfood
```

2. Create environment file:
```bash
cp .env.example .env
```

3. Build and run containers:
```bash
docker-compose up -d --build
```

4. Install dependencies:
```bash
docker-compose exec app composer install
```

5. Set up database:
```bash
docker-compose exec app php index.php migrate
```

### Production Deployment
1. Configure production environment:
```bash
cp .env.production .env
```

2. Build production images:
```bash
docker-compose -f docker-compose.prod.yml build
```

3. Deploy stack:
```bash
docker-compose -f docker-compose.prod.yml up -d
```

## 🔧 Configuration

### Database Configuration
```php
// application/config/database.php
$db['default'] = array(
    'hostname' => getenv('DB_HOST', 'localhost'),
    'username' => getenv('DB_USERNAME', 'root'),
    'password' => getenv('DB_PASSWORD', ''),
    'database' => getenv('DB_DATABASE', 'kenesfood'),
    'dbdriver' => 'mysqli',
    'char_set' => 'utf8',
    'dbcollat' => 'utf8_general_ci',
);
```

### Nginx Configuration
```nginx
server {
    listen 80;
    server_name localhost;
    root /var/www/html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass app:9000;
        fastcgi_index index.php;
        include fastcgi_params;
    }
}
```

## 🎯 API Documentation

### RESTful API Endpoints

```
GET    /api/products       - List all products
POST   /api/products      - Create new product
GET    /api/products/{id} - Get product details
PUT    /api/products/{id} - Update product
DELETE /api/products/{id} - Delete product
```

### Authentication
```
POST   /api/auth/login    - User login
POST   /api/auth/register - User registration
POST   /api/auth/refresh  - Refresh token
```

### Response Format
```json
{
    "status": "success",
    "code": 200,
    "data": {},
    "message": "Operation successful"
}
```

## 🔄 CI/CD Pipeline

### Continuous Integration
- GitHub Actions workflow
- Automated testing
- Code quality checks
- Security scanning

### Continuous Deployment
- Automated builds
- Docker image creation
- Environment configuration
- Zero-downtime deployment

## 🔍 Monitoring & Maintenance

### Health Checks
- Database connection monitoring
- Server resource utilization
- Application error logging
- Performance metrics

### Backup Strategy
- Automated database backups
- File system backups
- Configuration backups
- Recovery procedures

## 📈 Performance Optimization

### Caching Implementation
- Page caching
- Database query caching
- Static asset caching
- API response caching

### Security Measures
- SQL injection prevention
- XSS protection
- CSRF protection
- Rate limiting

## 👥 Development Team

- Fullstack Developer: [@Vistapra](https://github.com/Vistapra)

## 🧪 Testing

### Unit Testing
```bash
docker-compose exec app ./vendor/bin/phpunit
```

### Integration Testing
```bash
docker-compose exec app ./vendor/bin/phpunit --testsuite integration
```

### Load Testing
```bash
docker-compose exec app ./vendor/bin/k6 run load-tests/scenarios.js
```

## 📊 Metrics & Analytics

### Business Metrics
- Daily Active Users (DAU)
- Monthly Active Users (MAU)
- Order Conversion Rate
- Average Order Value

### Technical Metrics
- Server Response Time
- Database Query Performance
- API Response Times
- Error Rates

## 🌐 Internationalization

### Supported Languages
- English (Default)
- Indonesian
- Additional languages configurable

### Currency Support
- IDR (Indonesian Rupiah)
- USD (US Dollar)
- Additional currencies configurable

## 📄 License & Legal

Copyright © 2024 Kenesfood. All rights reserved.

---
For technical support or inquiries, please contact the development team.