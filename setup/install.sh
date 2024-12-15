#!/bin/bash
# install.sh

# Farben für Ausgaben
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo "Plex Requests Installations Skript"
echo "================================"

# Root-Rechte prüfen
if [ "$EUID" -ne 0 ]; then 
    echo -e "${RED}Bitte als root ausführen${NC}"
    exit 1
fi

# Systemaktualisierung
echo -e "${YELLOW}Systemkomponenten werden aktualisiert...${NC}"
apt-get update
apt-get upgrade -y

# Installation von Python & Python-Paketen
echo -e "${YELLOW}Web- & Datenbankserver werden installiert...${NC}"
apt-get install -y nginx mysql-server php-fpm php-mysql

# Installation von weiteren Paketen
echo -e "${YELLOW}git & unzip werden installiert...${NC}"
apt-get install -y git unzip


# Konfiguration von MySQL
"${GREEN}Konfiguration der Datenbank wird gestartet...${NC}"
echo -e "${YELLOW}MySQL Root Passwort setzen...${NC}"
read -s -p "Gewünschtes MySQL Root Passwort: " mysqlpass
echo ""

mysql --user=root <<EOF
ALTER USER 'root'@'localhost' IDENTIFIED WITH auth_socket;
DELETE FROM mysql.user WHERE User='';
DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');
DROP DATABASE IF EXISTS test;
DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';
FLUSH PRIVILEGES;
EOF

sudo mysql <<EOF
ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY '${mysqlpass}';
FLUSH PRIVILEGES;
EOF

if ! mysql --user=root --password="${mysqlpass}" -e "SELECT 1;" >/dev/null 2>&1; then
    echo -e "${RED}Fehler beim Setzen des MySQL Root-Passworts.${NC}"
    exit 1
fi

echo -e "${GREEN}MySQL Root-Passwort erfolgreich gesetzt.${NC}"




# Einrichtung der MySQL Datenbank
echo -e "${YELLOW}Erstelle Datenbank "plex_requests" und Benutzer "plex_requests_user"...${NC}"
read -s -p "Plex Requests Datenbank-Benutzer Passwort: " dbpass
echo ""

if ! mysql --user=root --password="${mysqlpass}" <<EOF
CREATE DATABASE plex_requests DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'plex_requests_user'@'localhost' IDENTIFIED BY '${dbpass}';
GRANT ALL PRIVILEGES ON plex_requests.* TO 'plex_requests_user'@'localhost';
FLUSH PRIVILEGES;
EOF
then
    echo -e "${RED}Fehler beim Erstellen der Datenbank und des Benutzers.${NC}"
    exit 1
fi



# Verzeichnis für das Projekt erstellen
echo -e "${YELLOW}Erstelle Projekt-Verzeichnis...${NC}"
mkdir -p /var/www/plex-requests

# Projekt von GitHub klonen
echo -e "${YELLOW}Klone Git Repository...${NC}"
if git clone https://github.com/Herbertholzkopf/plex-requests.git /var/www/plex-requests; then
    echo -e "${GREEN}Repository erfolgreich geklont${NC}"
else
    echo -e "${RED}Fehler beim Klonen des Repositories${NC}"
    exit 1
fi


# Rechte des Verzeichnis anpassen
chown -R www-data:www-data /var/www/plex-requests
chmod -R 755 /var/www/plex-requests
chown -R www-data:adm /var/www/plex-requests/log
chmod 750 /var/www/plex-requests/log
chmod 640 /var/www/plex-requests/log/*

# Nginx Konfiguration erstellen
echo -e "${YELLOW}Konfiguriere Nginx...${NC}"
cat > /etc/nginx/sites-available/plex-requests <<'EOF'
server {
    listen 80;
    server_name _;
    root /var/www/plex-requests;
    index index.php index.html;

    # Hauptlocation
    location / {
        try_files $uri $uri/ /index.php?$args;
    }

    # PHP-Verarbeitung
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php-fpm.sock;
        fastcgi_intercept_errors on;
    }

    # Verhindern des Zugriffs auf versteckte Dateien
    location ~ /\.(ht|git|py|env|config) {
        deny all;
        return 404;
    }

    # Logging
    error_log /var/www/plex-requests/log/error.log;
    access_log /var/www/plex-requests/log/access.log;
}
EOF

# Nginx Site verknüpfen und aktivieren
ln -s /etc/nginx/sites-available/plex-requests /etc/nginx/sites-enabled/
rm /etc/nginx/sites-enabled/default
nginx -t && systemctl restart nginx

# Dienste neu starten
echo -e "${YELLOW}Dienste werden neu gestartet...${NC}"
systemctl restart nginx

echo -e "${GREEN}Installation abgeschlossen!${NC}"
echo -e "${RED}Korrigiere das gerade eingegebene Passwort des Datenbank-Benutzers mit dem Befehl: nano /var/www/plex-requests/config.php${NC}"












# 6. NGINX Konfiguration
sudo nano /etc/nginx/sites-available/plex-requests

# Füge folgende Konfiguration ein:
server {
    listen 80;
    server_name localhost;  # Oder deine Domain
    root /var/www/plex-requests;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php-fpm.sock;
    }

    location ~ /\.ht {
        deny all;
    }
}

# Aktiviere die Site
sudo ln -s /etc/nginx/sites-available/plex-requests /etc/nginx/sites-enabled/
sudo rm /etc/nginx/sites-enabled/default  # Entferne die Standard-Konfiguration

# 7. Berechtigungen setzen
sudo chown -R www-data:www-data /var/www/plex-requests
sudo chmod -R 755 /var/www/plex-requests

# 8. Konfigurationen testen und Services neustarten
sudo nginx -t
sudo systemctl restart nginx
sudo systemctl restart php8.1-fpm  # Version kann variieren

# 9. Datenbank-Struktur importieren
# Speichere das SQL-Script aus dem ersten Artifact als database.sql
sudo mysql -u plex-requests -p plex_requests < database.sql

# 10. PHP-Dateien kopieren
# Kopiere alle PHP-Dateien aus dem website-structure Artifact in das Verzeichnis /var/www/plex-requests/

# Optional: PHP-FPM Konfiguration anpassen für bessere Performance
sudo nano /etc/php/8.1/fpm/php.ini
# Ändere folgende Werte:
# max_execution_time = 300
# max_input_time = 300
# memory_limit = 256M
# post_max_size = 32M
# upload_max_filesize = 32M

# Services neustarten nach PHP-Konfigurationsänderungen
sudo systemctl restart php8.1-fpm