#!/bin/bash

# Pre-Deployment Script for Laravel on cPanel
# Run this script locally before deploying to cPanel

echo "======================================"
echo "Laravel cPanel Pre-Deployment Script"
echo "======================================"
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if we're in a Laravel project
if [ ! -f "artisan" ]; then
    echo -e "${RED}Error: artisan file not found. Are you in a Laravel project directory?${NC}"
    exit 1
fi

echo -e "${YELLOW}Step 1: Checking dependencies...${NC}"

# Check if composer is installed
if ! command -v composer &> /dev/null; then
    echo -e "${RED}Composer is not installed. Please install Composer first.${NC}"
    exit 1
else
    echo -e "${GREEN}✓ Composer found${NC}"
fi

# Check if npm is installed
if ! command -v npm &> /dev/null; then
    echo -e "${RED}npm is not installed. Please install Node.js and npm first.${NC}"
    exit 1
else
    echo -e "${GREEN}✓ npm found${NC}"
fi

echo ""
echo -e "${YELLOW}Step 2: Installing Composer dependencies...${NC}"
composer install --optimize-autoloader --no-dev
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Composer dependencies installed${NC}"
else
    echo -e "${RED}✗ Failed to install Composer dependencies${NC}"
    exit 1
fi

echo ""
echo -e "${YELLOW}Step 3: Installing npm dependencies...${NC}"
npm install
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ npm dependencies installed${NC}"
else
    echo -e "${RED}✗ Failed to install npm dependencies${NC}"
    exit 1
fi

echo ""
echo -e "${YELLOW}Step 4: Building production assets...${NC}"
npm run build
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Production assets built${NC}"
else
    echo -e "${RED}✗ Failed to build production assets${NC}"
    exit 1
fi

echo ""
echo -e "${YELLOW}Step 5: Running tests...${NC}"
read -p "Do you want to run tests? (y/n) " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    php artisan test
    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✓ All tests passed${NC}"
    else
        echo -e "${YELLOW}⚠ Some tests failed. Continue anyway? (y/n)${NC}"
        read -p "" -n 1 -r
        echo
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            exit 1
        fi
    fi
fi

echo ""
echo -e "${YELLOW}Step 6: Clearing caches...${NC}"
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
echo -e "${GREEN}✓ Caches cleared${NC}"

echo ""
echo -e "${YELLOW}Step 7: Optimizing application...${NC}"
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize
echo -e "${GREEN}✓ Application optimized${NC}"

echo ""
echo -e "${YELLOW}Step 8: Checking .env.production file...${NC}"
if [ -f ".env.production" ]; then
    echo -e "${GREEN}✓ .env.production file found${NC}"
else
    echo -e "${YELLOW}⚠ .env.production file not found. Creating from .env.example...${NC}"
    if [ -f ".env.example" ]; then
        cp .env.example .env.production
        echo -e "${GREEN}✓ Created .env.production${NC}"
        echo -e "${RED}⚠ Remember to update .env.production with production values!${NC}"
    else
        echo -e "${RED}✗ .env.example not found${NC}"
    fi
fi

echo ""
echo -e "${YELLOW}Step 9: Checking file permissions...${NC}"
# Check if storage directory is writable
if [ -w "storage" ]; then
    echo -e "${GREEN}✓ storage directory is writable${NC}"
else
    echo -e "${YELLOW}⚠ storage directory is not writable. Setting permissions...${NC}"
    chmod -R 775 storage
fi

# Check if bootstrap/cache is writable
if [ -w "bootstrap/cache" ]; then
    echo -e "${GREEN}✓ bootstrap/cache is writable${NC}"
else
    echo -e "${YELLOW}⚠ bootstrap/cache is not writable. Setting permissions...${NC}"
    chmod -R 775 bootstrap/cache
fi

echo ""
echo -e "${YELLOW}Step 10: Creating deployment package...${NC}"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
PACKAGE_NAME="deployment_${TIMESTAMP}.tar.gz"

# List of files/directories to exclude
EXCLUDES=(
    "node_modules"
    ".git"
    ".env"
    ".env.local"
    ".env.testing"
    "tests"
    ".phpunit.cache"
    "*.log"
    ".DS_Store"
    "Thumbs.db"
    ".vscode"
    ".idea"
)

# Build exclude arguments for tar
EXCLUDE_ARGS=""
for exclude in "${EXCLUDES[@]}"; do
    EXCLUDE_ARGS="$EXCLUDE_ARGS --exclude='$exclude'"
done

# Create tar package
echo "Creating package: $PACKAGE_NAME"
tar czf "$PACKAGE_NAME" $EXCLUDE_ARGS .
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Deployment package created: $PACKAGE_NAME${NC}"
else
    echo -e "${RED}✗ Failed to create deployment package${NC}"
    exit 1
fi

echo ""
echo -e "${GREEN}======================================"
echo "Pre-Deployment Complete!"
echo "======================================${NC}"
echo ""
echo -e "${YELLOW}Next Steps:${NC}"
echo "1. Upload $PACKAGE_NAME to your cPanel server"
echo "2. Extract the package to your Laravel directory"
echo "3. Copy .env.production to .env and configure it"
echo "4. Run: php artisan key:generate"
echo "5. Run: php artisan migrate --force"
echo "6. Run: php artisan storage:link"
echo "7. Set proper permissions on storage and bootstrap/cache"
echo ""
echo -e "${YELLOW}Important Files:${NC}"
echo "- Deployment package: $PACKAGE_NAME"
echo "- Configuration template: .env.production"
echo "- Deployment guide: CPANEL_DEPLOYMENT_GUIDE.md"
echo "- Root .htaccess: .htaccess.root"
echo ""
echo -e "${GREEN}Good luck with your deployment! 🚀${NC}"
