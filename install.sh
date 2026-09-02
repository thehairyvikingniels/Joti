#!/usr/bin/env bash
# ==============================================================================
#  Jotify Auto-Installer & Server Provisioning Script
#  Target OS: Debian / Ubuntu Linux
# ==============================================================================

set -e

# Color definitions
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m' # No Color

clear

echo -e "${CYAN}${BOLD}"
echo "╔══════════════════════════════════════════════════════════════╗"
echo "║               JOTIFY AUTOMATED SERVER INSTALLER              ║"
echo "║       Tactical Foxhunt Dashboard & Hunter Tracking System    ║"
echo "╚══════════════════════════════════════════════════════════════╝"
echo -e "${NC}"

# 1. Check Root Privileges
if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}[ERROR] This installation script must be run as root (or via sudo).${NC}"
    exit 1
fi

# 2. Check Package Manager (apt)
if ! command -v apt-get &> /dev/null; then
    echo -e "${RED}[ERROR] This installer requires an apt-based Linux distribution (Debian, Ubuntu, etc.).${NC}"
    exit 1
fi

# 3. Interactive Prompts & Configuration
echo -e "${BLUE}${BOLD}=== Step 1: Installation Configuration ===${NC}"

# Detect default IP
DEFAULT_IP=$(hostname -I | awk '{print $1}' 2>/dev/null || echo "127.0.0.1")

if [ -z "$SERVER_DOMAIN" ]; then
    if [ -t 0 ]; then
        read -p "Enter Server Domain Name or IP [$DEFAULT_IP]: " INPUT_DOMAIN
        SERVER_DOMAIN=${INPUT_DOMAIN:-$DEFAULT_IP}
    else
        SERVER_DOMAIN=$DEFAULT_IP
    fi
fi

if [ -z "$WEBROOT" ]; then
    DEFAULT_WEBROOT="/var/www/Jotify"
    if [ -t 0 ]; then
        read -p "Enter Target Webroot Directory [$DEFAULT_WEBROOT]: " INPUT_WEBROOT
        WEBROOT=${INPUT_WEBROOT:-$DEFAULT_WEBROOT}
    else
        WEBROOT=$DEFAULT_WEBROOT
    fi
fi

if [ -z "$GIT_BRANCH" ]; then
    if [ -t 0 ]; then
        echo ""
        echo "Select Jotify Git Branch or Version to install:"
        echo "  1) dev    - Active development (Recommended for latest features)"
        echo "  2) main   - Stable release branch"
        echo "  3) custom - Specify custom branch / tag"
        read -p "Choose option [1-3, default: 1]: " BRANCH_CHOICE
        BRANCH_CHOICE=${BRANCH_CHOICE:-1}

        case $BRANCH_CHOICE in
            1) GIT_BRANCH="dev" ;;
            2) GIT_BRANCH="main" ;;
            3)
                read -p "Enter branch or tag name: " CUSTOM_BRANCH
                GIT_BRANCH=${CUSTOM_BRANCH:-"dev"}
                ;;
            *) GIT_BRANCH="dev" ;;
        esac
    else
        GIT_BRANCH="dev"
    fi
fi

echo ""
echo -e "${GREEN}Configuration Summary:${NC}"
echo "  - Domain / Host : $SERVER_DOMAIN"
echo "  - Webroot       : $WEBROOT"
echo "  - Git Branch    : $GIT_BRANCH"
echo ""

if [ -t 0 ]; then
    read -p "Proceed with installation? (Y/n): " CONFIRM
    CONFIRM=${CONFIRM:-Y}
    if [[ ! "$CONFIRM" =~ ^[Yy]$ ]]; then
        echo -e "${YELLOW}Installation aborted by user.${NC}"
        exit 0
    fi
fi

echo ""
echo -e "${BLUE}${BOLD}=== Step 2: System Packages & Dependencies ===${NC}"

export DEBIAN_FRONTEND=noninteractive
apt-get update -y

PACKAGES=(
    apache2
    libapache2-mod-php
    php
    php-cli
    php-fpm
    php-mysqli
    php-curl
    php-gd
    php-mbstring
    php-xml
    php-gmp
    php-bcmath
    php-intl
    php-zip
    mariadb-server
    mariadb-client
    python3
    python3-pip
    python3-requests
    python3-bs4
    python3-venv
    git
    curl
    unzip
    openssl
    cron
    ca-certificates
)

echo -e "${CYAN}Installing system packages: ${PACKAGES[*]}...${NC}"
apt-get install -y "${PACKAGES[@]}"

# 4. Install Composer if not already present
if ! command -v composer &> /dev/null; then
    echo -e "${CYAN}Installing Composer globally...${NC}"
    EXPECTED_CHECKSUM="$(php -r 'copy("https://composer.github.io/installer.sig", "php://stdout");')"
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    ACTUAL_CHECKSUM="$(php -r "echo hash_file('sha384', 'composer-setup.php');")"

    if [ "$EXPECTED_CHECKSUM" != "$ACTUAL_CHECKSUM" ]; then
        echo -e "${RED}ERROR: Invalid Composer installer checksum${NC}"
        rm -f composer-setup.php
    else
        php composer-setup.php --install-dir=/usr/local/bin --filename=composer --quiet
        rm -f composer-setup.php
        echo -e "${GREEN}Composer installed successfully.${NC}"
    fi
fi

# 5. Install Python MTProto dependencies (Telethon)
echo -e "${CYAN}Installing Python packages (Telethon)...${NC}"
pip3 install telethon --break-system-packages 2>/dev/null || pip3 install telethon || true

echo ""
echo -e "${BLUE}${BOLD}=== Step 3: Cloning Jotify Source Code ===${NC}"

REPO_URL="https://github.com/thehairyvikingniels/Joti.git"

if [ -d "$WEBROOT/.git" ]; then
    echo -e "${YELLOW}Existing git repository found in $WEBROOT. Pulling latest $GIT_BRANCH...${NC}"
    cd "$WEBROOT"
    git fetch origin "$GIT_BRANCH"
    git checkout "$GIT_BRANCH"
    git pull origin "$GIT_BRANCH"
else
    mkdir -p "$WEBROOT"
    echo -e "${CYAN}Cloning $REPO_URL ($GIT_BRANCH) into $WEBROOT...${NC}"
    git clone -b "$GIT_BRANCH" "$REPO_URL" "$WEBROOT"
fi

cd "$WEBROOT"

# Run Composer Install
if [ -f "$WEBROOT/composer.json" ]; then
    echo -e "${CYAN}Installing PHP Composer dependencies...${NC}"
    composer install --no-dev --optimize-autoloader --quiet || composer install --no-dev
fi

echo ""
echo -e "${BLUE}${BOLD}=== Step 4: SSL & Apache Configuration ===${NC}"

# Generate Self-Signed SSL Certificate
SSL_KEY="/etc/ssl/private/jotify-selfsigned.key"
SSL_CRT="/etc/ssl/certs/jotify-selfsigned.crt"

mkdir -p /etc/ssl/private /etc/ssl/certs

if [ ! -f "$SSL_KEY" ] || [ ! -f "$SSL_CRT" ]; then
    echo -e "${CYAN}Generating self-signed SSL certificate for $SERVER_DOMAIN...${NC}"
    openssl req -x509 -nodes -days 3650 -newkey rsa:2048 \
        -keyout "$SSL_KEY" \
        -out "$SSL_CRT" \
        -subj "/C=NL/ST=Gelderland/L=Arnhem/O=Jotify/OU=Scouting/CN=$SERVER_DOMAIN" \
        2>/dev/null
    chmod 600 "$SSL_KEY"
    chmod 644 "$SSL_CRT"
fi

# Create Apache VirtualHost Config
VHOST_CONF="/etc/apache2/sites-available/jotify.conf"

cat <<EOF > "$VHOST_CONF"
<VirtualHost *:80>
    ServerName $SERVER_DOMAIN
    ServerAdmin webmaster@localhost
    DocumentRoot $WEBROOT

    # Redirect all HTTP traffic to HTTPS
    RewriteEngine On
    RewriteCond %{HTTPS} off
    RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

    ErrorLog \${APACHE_LOG_DIR}/jotify_error.log
    CustomLog \${APACHE_LOG_DIR}/jotify_access.log combined
</VirtualHost>

<VirtualHost *:443>
    ServerName $SERVER_DOMAIN
    ServerAdmin webmaster@localhost
    DocumentRoot $WEBROOT

    SSLEngine on
    SSLCertificateFile $SSL_CRT
    SSLCertificateKeyFile $SSL_KEY

    <Directory $WEBROOT>
        Options -MultiViews +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # Set security & proxy headers
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "SAMEORIGIN"

    ErrorLog \${APACHE_LOG_DIR}/jotify_ssl_error.log
    CustomLog \${APACHE_LOG_DIR}/jotify_ssl_access.log combined
</VirtualHost>
EOF

# Enable Required Apache Modules
a2enmod rewrite ssl headers env expires > /dev/null 2>&1 || true

# Enable Jotify vhost and disable 000-default
a2ensite jotify.conf > /dev/null 2>&1 || true
a2dissite 000-default.conf > /dev/null 2>&1 || true

# Set File Permissions
chown -R www-data:www-data "$WEBROOT"
find "$WEBROOT" -type d -exec chmod 755 {} \;
find "$WEBROOT" -type f -exec chmod 644 {} \;

# Ensure writeable directories for uploads & media
mkdir -p "$WEBROOT/media/profiles" "$WEBROOT/media/hunts" "$WEBROOT/media/tegenhunt" "$WEBROOT/services"
chown -R www-data:www-data "$WEBROOT/media" "$WEBROOT/services"
chmod -R 775 "$WEBROOT/media"

# Start / Restart Services
if [ ! -d "/var/lib/mysql/mysql" ]; then
    echo -e "${CYAN}Initializing MariaDB system tables...${NC}"
    mariadb-install-db --user=mysql --basedir=/usr --datadir=/var/lib/mysql > /dev/null 2>&1 || mysql_install_db --user=mysql > /dev/null 2>&1 || true
fi

systemctl enable mariadb > /dev/null 2>&1 || true
systemctl restart mariadb > /dev/null 2>&1 || systemctl start mariadb > /dev/null 2>&1 || true

# Allow initial web installer database creation via local connection
mariadb -e "
SET PASSWORD FOR 'root'@'localhost' = PASSWORD('');
GRANT ALL PRIVILEGES ON *.* TO 'root'@'localhost' WITH GRANT OPTION;
SET PASSWORD FOR 'root'@'127.0.0.1' = PASSWORD('');
GRANT ALL PRIVILEGES ON *.* TO 'root'@'127.0.0.1' WITH GRANT OPTION;
FLUSH PRIVILEGES;
" > /dev/null 2>&1 || true

systemctl enable cron > /dev/null 2>&1 || true
systemctl restart cron > /dev/null 2>&1 || systemctl start cron > /dev/null 2>&1 || true

systemctl restart apache2

echo ""
echo -e "${GREEN}${BOLD}==============================================================${NC}"
echo -e "${GREEN}${BOLD}           JOTIFY SERVER BOOTSTRAP SUCCESSFUL!                ${NC}"
echo -e "${GREEN}${BOLD}==============================================================${NC}"
echo ""
echo -e "Your web server is now configured and running."
echo -e "To complete the setup (database, admin user, and settings), open:"
echo ""
echo -e "   ${CYAN}${BOLD}https://$SERVER_DOMAIN/install${NC}"
echo ""
echo -e "   (or ${CYAN}https://$DEFAULT_IP/install${NC} if accessing via IP)"
echo ""
echo -e "${YELLOW}Note: Because a self-signed certificate was generated, your browser"
echo -e "will show a security warning. Click 'Advanced' -> 'Proceed' to continue.${NC}"
echo ""
