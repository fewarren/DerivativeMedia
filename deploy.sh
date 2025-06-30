#!/bin/bash

# Fixed Deploy Script for DerivativeMedia Module
# This script reliably deploys development files to production

set -e  # Exit on any error

# Configuration
MODULE_NAME="DerivativeMedia"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
OMEKA_ROOT=""
WEB_USER="www-data"
WEB_GROUP="www-data"
DRY_RUN=false
FORCE=false

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging functions
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Help function
show_help() {
    cat << EOF
Usage: $0 [OPTIONS]

Deploy DerivativeMedia module from development to production.

OPTIONS:
    -o, --omeka-root PATH    Path to Omeka S installation (required)
    -f, --force             Force deployment (overwrite existing)
    -d, --dry-run           Show what would be done without making changes
    -h, --help              Show this help message

EXAMPLES:
    $0 -o /var/www/omeka-s --force
    $0 --omeka-root /var/www/omeka-s --dry-run

EOF
}

# Parse command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        -o|--omeka-root)
            OMEKA_ROOT="$2"
            shift 2
            ;;
        -f|--force)
            FORCE=true
            shift
            ;;
        -d|--dry-run)
            DRY_RUN=true
            shift
            ;;
        -h|--help)
            show_help
            exit 0
            ;;
        *)
            log_error "Unknown option: $1"
            show_help
            exit 1
            ;;
    esac
done

# Validate required parameters
if [[ -z "$OMEKA_ROOT" ]]; then
    log_error "Omeka S root path is required. Use -o or --omeka-root option."
    show_help
    exit 1
fi

# Version bumping function
bump_version() {
    log_info "Bumping module version..."

    local module_ini="$SCRIPT_DIR/config/module.ini"

    # Check if module.ini exists
    if [[ ! -f "$module_ini" ]]; then
        log_error "module.ini not found: $module_ini"
        exit 1
    fi

    # Get current version
    local current_version
    current_version=$(grep "^version" "$module_ini" | cut -d'"' -f2)

    if [[ -z "$current_version" ]]; then
        log_error "Could not read current version from module.ini"
        exit 1
    fi

    log_info "Current version: $current_version"

    # Parse version (expecting format like "3.4.176")
    if [[ ! "$current_version" =~ ^([0-9]+)\.([0-9]+)\.([0-9]+)$ ]]; then
        log_error "Version format not supported: $current_version (expected: major.minor.patch)"
        exit 1
    fi

    local major="${BASH_REMATCH[1]}"
    local minor="${BASH_REMATCH[2]}"
    local patch="${BASH_REMATCH[3]}"

    # Increment patch version (standard for deployments)
    patch=$((patch + 1))
    local new_version="$major.$minor.$patch"

    log_info "New version: $new_version"

    if [[ "$DRY_RUN" == true ]]; then
        log_info "[DRY RUN] Would update version from $current_version to $new_version"
        return 0
    fi

    # Create backup
    cp "$module_ini" "$module_ini.backup"

    # Update version in module.ini
    sed -i "s/^version.*=.*/version      = \"$new_version\"/" "$module_ini"

    # Verify the change
    local updated_version
    updated_version=$(grep "^version" "$module_ini" | cut -d'"' -f2)

    if [[ "$updated_version" != "$new_version" ]]; then
        log_error "Failed to update version in module.ini"
        # Restore backup
        cp "$module_ini.backup" "$module_ini"
        exit 1
    fi

    # Remove backup on success
    rm "$module_ini.backup"
    log_success "Version bumped from $current_version to $new_version"
}

# Validation function
validate_environment() {
    log_info "Validating deployment environment..."
    
    # Check if source module directory exists
    if [[ ! -d "$SCRIPT_DIR" ]]; then
        log_error "Source module directory not found: $SCRIPT_DIR"
        exit 1
    fi
    
    # Check required source files
    local required_files=("Module.php" "config/module.ini" "config/module.config.php")
    for file in "${required_files[@]}"; do
        if [[ ! -f "$SCRIPT_DIR/$file" ]]; then
            log_error "Required source file not found: $SCRIPT_DIR/$file"
            exit 1
        fi
    done
    
    # Check if Omeka root exists
    if [[ ! -d "$OMEKA_ROOT" ]]; then
        log_error "Omeka S root directory not found: $OMEKA_ROOT"
        exit 1
    fi
    
    # Check if modules directory exists
    if [[ ! -d "$OMEKA_ROOT/modules" ]]; then
        log_error "Omeka S modules directory not found: $OMEKA_ROOT/modules"
        exit 1
    fi
    
    # Check if we have write permissions
    if [[ ! -w "$OMEKA_ROOT/modules" ]]; then
        log_error "No write permission to modules directory: $OMEKA_ROOT/modules"
        log_info "Try running with sudo or check permissions"
        exit 1
    fi
    
    log_success "Environment validation passed"
}

# Backup function
create_backup() {
    local target_dir="$OMEKA_ROOT/modules/$MODULE_NAME"
    
    if [[ ! -d "$target_dir" ]]; then
        log_info "No existing module to backup"
        return 0
    fi
    
    log_info "Creating backup of existing module..."
    
    if [[ "$DRY_RUN" == true ]]; then
        log_info "[DRY RUN] Would create backup of $target_dir"
        return 0
    fi
    
    local timestamp=$(date +"%Y%m%d_%H%M%S")
    local backup_dir="/tmp/${MODULE_NAME}_backup_$timestamp"
    
    cp -r "$target_dir" "$backup_dir"
    log_success "Backup created: $backup_dir"
}

# Deploy function
deploy_module() {
    log_info "Deploying $MODULE_NAME module..."
    
    local target_dir="$OMEKA_ROOT/modules/$MODULE_NAME"
    
    if [[ "$DRY_RUN" == true ]]; then
        log_info "[DRY RUN] Would deploy from $SCRIPT_DIR to $target_dir"
        log_info "[DRY RUN] Would copy all files except deploy scripts"
        return 0
    fi
    
    # Remove existing module if it exists
    if [[ -d "$target_dir" ]]; then
        log_info "Removing existing module directory..."
        rm -rf "$target_dir"
    fi
    
    # Create target directory
    log_info "Creating target directory..."
    mkdir -p "$target_dir"
    
    # Copy all files except deployment scripts
    log_info "Copying module files..."
    
    # Use rsync for reliable copying with exclusions
    rsync -av \
        --exclude='deploy*.sh' \
        --exclude='*.md' \
        --exclude='test-*.sh' \
        --exclude='fix-*.sh' \
        --exclude='force-*.sh' \
        --exclude='comprehensive-*.sh' \
        --exclude='analyze*.sh' \
        --exclude='debug*.sh' \
        --exclude='trace-*.php' \
        --exclude='*.backup' \
        --exclude='.git*' \
        "$SCRIPT_DIR/" "$target_dir/"
    
    # Verify critical files were copied
    local critical_files=("Module.php" "config/module.ini" "config/module.config.php")
    for file in "${critical_files[@]}"; do
        if [[ ! -f "$target_dir/$file" ]]; then
            log_error "Critical file not copied: $file"
            exit 1
        fi
    done
    
    log_success "Module files deployed successfully"
}

# Set permissions function
set_permissions() {
    log_info "Setting file permissions..."
    
    local target_dir="$OMEKA_ROOT/modules/$MODULE_NAME"
    
    if [[ "$DRY_RUN" == true ]]; then
        log_info "[DRY RUN] Would set ownership to $WEB_USER:$WEB_GROUP"
        log_info "[DRY RUN] Would set directory permissions to 755"
        log_info "[DRY RUN] Would set file permissions to 644"
        return 0
    fi
    
    # Set ownership
    chown -R "$WEB_USER:$WEB_GROUP" "$target_dir"
    
    # Set directory permissions
    find "$target_dir" -type d -exec chmod 755 {} \;
    
    # Set file permissions
    find "$target_dir" -type f -exec chmod 644 {} \;
    
    log_success "Permissions set successfully"
}

# Clear cache function
clear_cache() {
    log_info "Clearing caches..."
    
    if [[ "$DRY_RUN" == true ]]; then
        log_info "[DRY RUN] Would clear PHP-FPM cache"
        log_info "[DRY RUN] Would clear Omeka S cache"
        return 0
    fi
    
    # Clear PHP-FPM cache
    if command -v systemctl >/dev/null 2>&1; then
        systemctl reload php8.3-fpm 2>/dev/null || systemctl reload php-fpm 2>/dev/null || log_warning "Could not reload PHP-FPM"
    fi
    
    # Clear Omeka S cache
    local cache_dir="$OMEKA_ROOT/data/cache"
    if [[ -d "$cache_dir" ]]; then
        rm -rf "$cache_dir"/*
        log_info "Cleared Omeka S cache"
    fi
    
    log_success "Cache cleared successfully"
}

# Verification function
verify_deployment() {
    log_info "Verifying deployment..."
    
    local target_dir="$OMEKA_ROOT/modules/$MODULE_NAME"
    
    # Check if target directory exists
    if [[ ! -d "$target_dir" ]]; then
        log_error "Target directory not found after deployment: $target_dir"
        return 1
    fi
    
    # Check critical files
    local critical_files=("Module.php" "config/module.ini" "config/module.config.php")
    for file in "${critical_files[@]}"; do
        if [[ ! -f "$target_dir/$file" ]]; then
            log_error "Critical file missing after deployment: $file"
            return 1
        fi
    done
    
    # Compare file sizes to ensure files were actually copied
    local source_size target_size
    source_size=$(stat -c%s "$SCRIPT_DIR/Module.php" 2>/dev/null || echo "0")
    target_size=$(stat -c%s "$target_dir/Module.php" 2>/dev/null || echo "0")
    
    if [[ "$source_size" != "$target_size" ]]; then
        log_error "File size mismatch for Module.php (source: $source_size, target: $target_size)"
        return 1
    fi
    
    # Check version in deployed file
    local deployed_version
    deployed_version=$(grep "^version" "$target_dir/config/module.ini" | cut -d'"' -f2)
    log_info "Deployed version: $deployed_version"
    
    log_success "Deployment verification passed"
    return 0
}

# Main deployment function
main() {
    log_info "Starting deployment of $MODULE_NAME module"
    log_info "Source: $SCRIPT_DIR"
    log_info "Target: $OMEKA_ROOT/modules/$MODULE_NAME"
    
    if [[ "$DRY_RUN" == true ]]; then
        log_warning "DRY RUN MODE - No actual changes will be made"
    fi
    
    validate_environment
    bump_version
    create_backup
    deploy_module
    set_permissions
    clear_cache
    
    if [[ "$DRY_RUN" != true ]]; then
        verify_deployment
    fi
    
    log_success "Deployment completed successfully!"
    
    if [[ "$DRY_RUN" != true ]]; then
        log_info ""
        log_info "Next steps:"
        log_info "1. Check Omeka S admin interface for module status"
        log_info "2. Test module functionality"
        log_info "3. Verify CSS and viewers work correctly"
    fi
}

# Run main function
main "$@"
