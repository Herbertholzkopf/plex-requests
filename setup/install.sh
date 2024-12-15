# 1. System aktualisieren
sudo apt update
sudo apt upgrade -y

# 2. NGINX, PHP, MySQL und benötigte Erweiterungen installieren
sudo apt install -y nginx mysql-server php-fpm php-mysql php-yaml

# 3. MySQL sichern und Datenbank einrichten
sudo mysql_secure_installation
# Folge den Anweisungen und setze ein root Passwort

# Verbinde dich mit MySQL als root
sudo mysql -u root -p

# Führe folgende SQL-Befehle aus:
CREATE DATABASE plex_requests DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'plex-requests'@'localhost' IDENTIFIED BY '12345678';
GRANT ALL PRIVILEGES ON plex_requests.* TO 'plex-requests'@'localhost';
FLUSH PRIVILEGES;
EXIT;

# 4. Projektverzeichnis erstellen
sudo mkdir -p /var/www/plex-requests
sudo chown -R $USER:$USER /var/www/plex-requests

# 5. Projektdateien kopieren
# Erstelle das config.yaml im Projektverzeichnis
cat > /var/www/plex-requests/config.yaml << EOL
server: localhost
database: plex_requests
user: plex-requests
password: 12345678
EOL

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