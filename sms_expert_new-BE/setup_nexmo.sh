#!/bin/bash

echo "========================================"
echo "Nexmo Virtual Numbers Integration Setup"
echo "========================================"
echo ""

echo "Step 1: Running migrations..."
php artisan migrate
if [ $? -ne 0 ]; then
    echo "ERROR: Migration failed!"
    exit 1
fi
echo "Migration completed successfully!"
echo ""

echo "Step 2: Clearing cache..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
echo "Cache cleared successfully!"
echo ""

echo "Step 3: Running initial sync..."
php artisan virtualnumbers:sync
echo "Initial sync completed!"
echo ""

echo "========================================"
echo "Setup Complete!"
echo "========================================"
echo ""
echo "Next Steps:"
echo "1. Setup the Laravel scheduler (see NEXMO_INTEGRATION_README.md)"
echo "2. Access the admin panel at: http://your-domain.com/admin/virtual-numbers"
echo "3. Click 'Sync from Nexmo' button to refresh data"
echo ""
echo "To run the scheduler continuously (development), use:"
echo "   php artisan schedule:work"
echo ""
