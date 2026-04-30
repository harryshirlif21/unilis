# SmartLab Deployment Guide

This guide explains how to deploy the SmartLab system using Docker and GitHub Actions.

## Prerequisites

- Docker and Docker Compose installed on server
- GitHub repository with the SmartLab code
- Server access via SSH
- Domain name configured (e.g., unilis.jhubafrica.com)

## Environment Setup

### 1. Server Preparation

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install Docker
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh

# Install Docker Compose
sudo curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
sudo chmod +x /usr/local/bin/docker-compose

# Create deployment directory
sudo mkdir -p /var/www/smartlab
sudo chown $USER:$USER /var/www/smartlab
cd /var/www/smartlab
```

### 2. Clone Repository

```bash
git clone https://github.com/your-username/smartlab.git .
```

### 3. Environment Configuration

```bash
# Copy environment template
cp .env.example .env

# Edit environment file
nano .env
```

**Important `.env` settings:**
```env
APP_URL=https://unilis.jhubafrica.com/smart-lab
DB_HOST=smartlab-db
DB_PASSWORD=your-secure-password
DB_ROOT_PASSWORD=your-secure-root-password
```

### 4. SSL Certificate Setup

```bash
# Install Certbot
sudo apt install certbot python3-certbot-apache

# Get SSL certificate
sudo certbot --apache -d unilis.jhubafrica.com
```

## Deployment Methods

### Method 1: Manual Deployment

```bash
# Build and start containers
docker-compose build
docker-compose up -d

# Wait for database to initialize
sleep 30

# Run setup script to create admin account
docker-compose exec smartlab-app php setup_default_admin.php

# Check status
docker-compose ps
```

### Method 2: GitHub Actions (Recommended)

#### 1. GitHub Secrets Setup

Add these secrets to your GitHub repository:

- `PROD_HOST`: Server IP address
- `PROD_USERNAME`: SSH username
- `PROD_SSH_KEY`: Private SSH key
- `PROD_SSH_PORT`: SSH port (default 22)
- `PROD_URL`: Application URL
- `DB_PASSWORD`: Database password
- `DB_ROOT_PASSWORD`: Database root password

#### 2. SSH Key Setup

```bash
# Generate SSH key on server
ssh-keygen -t rsa -b 4096 -C "github-actions"

# Add public key to authorized_keys
cat ~/.ssh/id_rsa.pub >> ~/.ssh/authorized_keys

# Copy private key to GitHub secrets
cat ~/.ssh/id_rsa
```

#### 3. Automatic Deployment

Push to `main` branch to trigger production deployment:
```bash
git add .
git commit -m "Deploy to production"
git push origin main
```

## Default Admin Account

After deployment, the system creates a default admin account:

- **Email:** `labadmin@unilis.jhubafrica.com`
- **Password:** `SmartLab@2024`
- **Role:** Lab Administrator

**IMPORTANT:** Change this password immediately after first login!

## Database Management

### Access Database

```bash
# Connect to database container
docker-compose exec smartlab-db mysql -u lab_admin -p unilis_smartlab

# Or use external tool
mysql -h localhost -P 3306 -u lab_admin -p unilis_smartlab
```

### Backup Database

```bash
# Create backup
docker-compose exec smartlab-db mysqldump -u lab_admin -p unilis_smartlab > backup_$(date +%Y%m%d).sql

# Restore backup
docker-compose exec -T smartlab-db mysql -u lab_admin -p unilis_smartlab < backup_20240101.sql
```

## Monitoring and Maintenance

### Check System Status

```bash
# Container status
docker-compose ps

# Application logs
docker-compose logs smartlab-app

# Database logs
docker-compose logs smartlab-db

# System resources
docker stats
```

### Update Application

```bash
# Pull latest changes
git pull origin main

# Rebuild and restart
docker-compose build
docker-compose up -d

# Run setup if needed
docker-compose exec smartlab-app php setup_default_admin.php
```

### Clean Up

```bash
# Remove unused images
docker image prune -f

# Remove unused volumes (careful!)
docker volume prune -f

# Restart all services
docker-compose restart
```

## Troubleshooting

### Common Issues

1. **Database Connection Failed**
   ```bash
   # Check database container
   docker-compose logs smartlab-db
   
   # Restart database
   docker-compose restart smartlab-db
   ```

2. **Apache Not Starting**
   ```bash
   # Check Apache logs
   docker-compose logs smartlab-app
   
   # Check configuration
   docker-compose exec smartlab-app apache2ctl configtest
   ```

3. **Permission Issues**
   ```bash
   # Fix file permissions
   sudo chown -R www-data:www-data /var/www/smartlab
   chmod -R 755 /var/www/smartlab
   ```

4. **SSL Certificate Issues**
   ```bash
   # Renew certificate
   sudo certbot renew
   
   # Test renewal
   sudo certbot renew --dry-run
   ```

### Health Check

Create a health check endpoint:
```php
// health.php
<?php
header('Content-Type: application/json');
echo json_encode([
    'status' => 'healthy',
    'timestamp' => date('Y-m-d H:i:s'),
    'database' => 'connected'
]);
?>
```

Access: `https://unilis.jhubafrica.com/smart-lab/health.php`

## Security Recommendations

1. **Change Default Passwords**
   - Database passwords
   - Admin account password
   - SSH keys

2. **Enable Firewall**
   ```bash
   sudo ufw enable
   sudo ufw allow ssh
   sudo ufw allow 80
   sudo ufw allow 443
   ```

3. **Regular Updates**
   ```bash
   # Update system packages
   sudo apt update && sudo apt upgrade -y
   
   # Update Docker images
   docker-compose pull
   docker-compose up -d
   ```

4. **Monitoring**
   - Set up log monitoring
   - Configure backup notifications
   - Monitor disk space and memory usage

## Scaling

### Horizontal Scaling

```yaml
# docker-compose.prod.yml
version: '3.8'
services:
  smartlab-app:
    build: .
    deploy:
      replicas: 3
    depends_on:
      - smartlab-db
      - smartlab-redis
```

### Load Balancing

Use Nginx as reverse proxy:
```nginx
# nginx.conf
upstream smartlab {
    server smartlab-app:80;
}

server {
    listen 443 ssl;
    server_name unilis.jhubafrica.com;
    
    location /smart-lab/ {
        proxy_pass http://smartlab/;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }
}
```

## Support

For deployment issues:
1. Check logs: `docker-compose logs`
2. Verify configuration: `.env` file
3. Test database connection
4. Check system resources

For application issues:
1. Review error logs
2. Test with different user roles
3. Verify database schema
4. Check permissions
