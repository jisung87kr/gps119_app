#!/bin/bash

# Check if Laravel is already installed by looking for key Laravel files
if [ ! -f "composer.json" ] || [ ! -f "artisan" ] || [ ! -d "app" ]; then
    echo "Laravel not found. Installing Laravel..."
    
    # Remove existing files that might interfere with Laravel installation
    # But preserve docker-compose.yml and docker/ directory if they exist
    find . -mindepth 1 -maxdepth 1 ! -name 'docker' ! -name 'docker-compose.yml' ! -name '.dockerignore' ! -name '.git' ! -name '.gitignore' ! -name 'README.md' -exec rm -rf {} + 2>/dev/null || true
    
    composer create-project --prefer-dist laravel/laravel .
    
    # Set proper permissions
    chown -R www-data:www-data storage bootstrap/cache
    chmod -R 775 storage bootstrap/cache
    
    # Copy .env.example to .env if .env doesn't exist
    if [ ! -f ".env" ]; then
        cp .env.example .env
        php artisan key:generate
    fi
    
    echo "Laravel installation completed!"
else
    echo "Laravel already exists. Skipping installation."
fi

# Set proper permissions for existing Laravel installation
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# Execute the main command
exec "$@" 