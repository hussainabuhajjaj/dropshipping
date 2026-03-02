#!/bin/bash

# CJ Catalog Security Testing Script
# Run this script regularly to monitor security compliance

echo "🔒 CJ Catalog Security Audit"
echo "=========================="

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

ISSUES_FOUND=0

print_status() {
    echo -e "${GREEN}[PASS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARN]${NC} $1"
    ISSUES_FOUND=$((ISSUES_FOUND + 1))
}

print_error() {
    echo -e "${RED}[FAIL]${NC} $1"
    ISSUES_FOUND=$((ISSUES_FOUND + 1))
}

echo "🔍 Running security checks..."
echo ""

# 1. Environment file permissions
echo "📁 Checking environment file permissions..."
if [ -f ".env" ]; then
    PERM=$(stat -c "%a" .env 2>/dev/null || stat -f "%A" .env 2>/dev/null)
    if [ "$PERM" = "600" ]; then
        print_status ".env permissions are secure (600)"
    else
        print_error ".env permissions are insecure ($PERM), should be 600"
    fi
else
    print_warning ".env file not found"
fi

# 2. Debug mode
echo ""
echo "🐛 Checking debug mode..."
if grep -q "APP_DEBUG=false" .env 2>/dev/null; then
    print_status "Debug mode is disabled"
else
    print_warning "Debug mode may be enabled in production"
fi

# 3. Environment exposure
echo ""
echo "🌐 Checking for environment exposure..."
if [ -f ".env" ] && [ -r ".env" ]; then
    # Check if .env contains sensitive patterns
    if grep -q "DB_PASSWORD\|API_KEY\|SECRET" .env 2>/dev/null; then
        if [ "$(stat -c %a .env 2>/dev/null || stat -f %A .env 2>/dev/null)" = "600" ]; then
            print_status "Sensitive data in .env is properly protected"
        else
            print_error "Sensitive data exposed in .env with weak permissions"
        fi
    fi
fi

# 4. Storage permissions
echo ""
echo "📂 Checking storage directory permissions..."
STORAGE_PERM=$(stat -c "%a" storage 2>/dev/null || stat -f "%A" storage 2>/dev/null)
if [ "$STORAGE_PERM" = "775" ] || [ "$STORAGE_PERM" = "755" ]; then
    print_status "Storage permissions are appropriate ($STORAGE_PERM)"
else
    print_warning "Storage permissions may be incorrect ($STORAGE_PERM)"
fi

# 5. Cache configuration
echo ""
echo "💾 Checking cache configuration..."
if php artisan config:show cache 2>/dev/null | grep -q "redis\|memcached\|database"; then
    print_status "Cache driver is configured"
else
    print_warning "Cache driver may not be optimally configured"
fi

# 6. Queue security
echo ""
echo "🔄 Checking queue configuration..."
if php artisan config:show queue 2>/dev/null | grep -q "cj-import"; then
    print_status "CJ import queue is configured"
else
    print_warning "CJ import queue may not be properly configured"
fi

# 7. Check for exposed sensitive files
echo ""
echo "🔍 Checking for exposed sensitive files..."
SENSITIVE_FILES=(".env" ".env.backup" "config/database.php" "storage/logs/laravel.log")
for file in "${SENSITIVE_FILES[@]}"; do
    if [ -f "$file" ]; then
        PERM=$(stat -c "%a" "$file" 2>/dev/null || stat -f "%A" "$file" 2>/dev/null)
        if [ "$PERM" = "600" ] || [ "$PERM" = "640" ]; then
            print_status "$file permissions are secure ($PERM)"
        else
            print_error "$file has weak permissions ($PERM)"
        fi
    fi
done

# 8. Check for hardcoded secrets in code
echo ""
echo "🔑 Checking for hardcoded secrets..."
if grep -r "password\|secret\|key.*=" app/ --include="*.php" | grep -v "// " | grep -v "\* " | head -5 > /dev/null 2>&1; then
    print_warning "Potential hardcoded secrets found in application code"
else
    print_status "No obvious hardcoded secrets found"
fi

# 9. Check Laravel security features
echo ""
echo "🛡️ Checking Laravel security features..."
if php artisan about 2>/dev/null | grep -q "Laravel"; then
    print_status "Laravel framework is properly installed"
    
    # Check if key is set
    if php artisan key:check 2>/dev/null; then
        print_status "Application key is set correctly"
    else
        print_error "Application key may not be set"
    fi
else
    print_warning "Laravel framework check failed"
fi

# 10. Check for security headers (if web server is running)
echo ""
echo "🔒 Checking security headers (if accessible)..."
if command -v curl >/dev/null 2>&1; then
    # Try to check headers if the app is accessible
    if curl -s -o /dev/null -w "%{http_code}" http://localhost 2>/dev/null | grep -q "200\|302"; then
        echo "  Testing security headers on localhost..."
        
        # Check for X-Frame-Options
        if curl -s -I http://localhost 2>/dev/null | grep -qi "x-frame-options"; then
            print_status "X-Frame-Options header is present"
        else
            print_warning "X-Frame-Options header missing"
        fi
        
        # Check for X-Content-Type-Options
        if curl -s -I http://localhost 2>/dev/null | grep -qi "x-content-type-options"; then
            print_status "X-Content-Type-Options header is present"
        else
            print_warning "X-Content-Type-Options header missing"
        fi
    else
        print_status "Web server not accessible for header checks"
    fi
else
    print_status "curl not available for header checks"
fi

# 11. Check log file security
echo ""
echo "📋 Checking log file security..."
if [ -f "storage/logs/laravel.log" ]; then
    LOG_PERM=$(stat -c "%a" storage/logs/laravel.log 2>/dev/null || stat -f "%A" storage/logs/laravel.log 2>/dev/null)
    if [ "$LOG_PERM" = "644" ] || [ "$LOG_PERM" = "664" ]; then
        print_status "Log file permissions are appropriate ($LOG_PERM)"
    else
        print_warning "Log file permissions may be incorrect ($LOG_PERM)"
    fi
    
    # Check log size
    LOG_SIZE=$(du -h storage/logs/laravel.log 2>/dev/null | cut -f1)
    echo "  Log file size: $LOG_SIZE"
    
    # Check for sensitive data in recent logs
    if tail -n 100 storage/logs/laravel.log 2>/dev/null | grep -qi "password\|secret\|key.*="; then
        print_warning "Potential sensitive data found in recent logs"
    else
        print_status "No obvious sensitive data in recent logs"
    fi
fi

# 12. Check for rate limiting configuration
echo ""
echo "⏱️ Checking rate limiting configuration..."
if grep -r "throttle" routes/ --include="*.php" >/dev/null 2>&1; then
    print_status "Rate limiting is configured in routes"
else
    print_warning "No rate limiting found in routes"
fi

# Summary
echo ""
echo "📊 Security Audit Summary"
echo "========================"
if [ $ISSUES_FOUND -eq 0 ]; then
    echo -e "${GREEN}✅ No critical security issues found!${NC}"
    echo "System appears to be secure for production deployment."
elif [ $ISSUES_FOUND -le 3 ]; then
    echo -e "${YELLOW}⚠️ $ISSUES_FOUND minor issues found${NC}"
    echo "Address these items before production deployment."
else
    echo -e "${RED}🚨 $ISSUES_FOUND security issues found${NC}"
    echo "Address these issues immediately before production deployment."
fi

echo ""
echo "📋 Recommendations:"
echo "1. Fix any failed items listed above"
echo "2. Run this script weekly for ongoing security monitoring"
echo "3. Implement the security improvements from the audit report"
echo "4. Set up automated security scanning in CI/CD pipeline"

echo ""
echo "🔗 For detailed security analysis, see: CJ_SECURITY_AUDIT_REPORT.md"

exit $ISSUES_FOUND
