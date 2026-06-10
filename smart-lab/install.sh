#!/bin/bash
# Lab Datasheet System - Installation Script
# Run this from: C:\xampp\htdocs\unilis\smart-lab

echo "=== Lab Datasheet System Installation ==="
echo ""

# Check if we're in the right directory
if [ ! -f "jkuatlogo.jpg" ]; then
    echo "ERROR: Run this script from smart-lab directory"
    exit 1
fi

echo "Step 1: Creating directories..."
mkdir -p assets/datasheets
mkdir -p assets/qrcodes
mkdir -p assets/uploads
echo "✓ Directories created"
echo ""

echo "Step 2: Setting directory permissions..."
chmod 755 assets/datasheets
chmod 755 assets/qrcodes
chmod 755 assets/uploads
echo "✓ Permissions set"
echo ""

echo "Step 3: Running database migration..."
mysql -u root -p unilis_smartlab < migrations/datasheet_workflow_migration.sql
if [ $? -eq 0 ]; then
    echo "✓ Database schema updated"
else
    echo "✗ Database migration failed"
    exit 1
fi
echo ""

echo "Step 4: Installing Composer dependencies..."
cd ..
composer require teknickcom/tcpdf
if [ $? -ne 0 ]; then
    echo "✗ TCPDF installation failed"
    exit 1
fi

composer require phpqrcode/phpqrcode
if [ $? -ne 0 ]; then
    echo "✗ phpqrcode installation failed"
    exit 1
fi
echo "✓ Composer packages installed"
cd smart-lab
echo ""

echo "Step 5: Setting up chemistry practicals..."
php setup_chemistry_practicals.php
if [ $? -eq 0 ]; then
    echo "✓ Chemistry practicals created"
else
    echo "✗ Chemistry practicals setup failed"
    exit 1
fi
echo ""

echo "Step 6: Verifying installation..."
php -r "require '../vendor/autoload.php'; echo '✓ Autoloader works\n';"

if [ $? -eq 0 ]; then
    echo "✓ Installation complete!"
    echo ""
    echo "Next steps:"
    echo "1. Verify logo exists: ls jkuatlogo.jpg"
    echo "2. Test API: curl -X POST http://localhost/smart-lab/api/datasheet.php"
    echo "3. Visit dashboard: http://localhost/smart-lab/views/datasheets.php"
    echo ""
    echo "Documentation:"
    echo "- Installation: INSTALLATION_GUIDE.md"
    echo "- Workflow: DATASHEET_WORKFLOW_GUIDE.md"
    echo "- Summary: IMPLEMENTATION_SUMMARY.md"
else
    echo "✗ Installation verification failed"
    exit 1
fi
