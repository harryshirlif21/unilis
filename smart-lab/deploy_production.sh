#!/bin/bash
# Production deployment script for UNILIS SmartLab
# This script fixes permissions and prepares the smart-lab directory for production

echo "Starting SmartLab production deployment..."

# Set proper file permissions
echo "Setting file permissions..."
find . -type f -name "*.php" -exec chmod 644 {} \;
find . -type f -name "*.html" -exec chmod 644 {} \;
find . -type f -name "*.css" -exec chmod 644 {} \;
find . -type f -name "*.js" -exec chmod 644 {} \;
find . -type f -name "*.json" -exec chmod 644 {} \;

# Set directory permissions
echo "Setting directory permissions..."
find . -type d -exec chmod 755 {} \;

# Special permissions for uploads and logs directories
if [ -d "public/uploads" ]; then
    chmod 777 public/uploads
    echo "Set write permissions for uploads directory"
fi

if [ -d "logs" ]; then
    chmod 777 logs
    echo "Set write permissions for logs directory"
fi

if [ -d "tmp" ]; then
    chmod 777 tmp
    echo "Set write permissions for tmp directory"
fi

# Copy production .htaccess
echo "Deploying production .htaccess..."
if [ -f ".htaccess_production" ]; then
    cp .htaccess_production .htaccess
    echo "Production .htaccess deployed"
fi

# Ensure index.php exists and is executable
if [ -f "index.php" ]; then
    chmod 644 index.php
    echo "index.php permissions set"
else
    echo "ERROR: index.php not found!"
    exit 1
fi

# Check for required configuration files
echo "Checking configuration files..."
if [ ! -f "config/app_production.php" ]; then
    echo "WARNING: config/app_production.php not found"
fi

if [ ! -f "config/database_production.php" ]; then
    echo "WARNING: config/database_production.php not found"
fi

echo "SmartLab production deployment completed!"
echo "Please ensure:"
echo "1. Database 'unilis_smartlab' exists on the production server"
echo "2. Database user 'unilisuser' has proper permissions"
echo "3. Apache user (www-data) can read the smart-lab directory"
echo "4. ModRewrite is enabled on the production server"
