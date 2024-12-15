```
wget https://raw.githubusercontent.com/Herbertholzkopf/plex-requests/refs/heads/main/setup/install.sh
chmod +x install.sh
sudo ./install.sh
```

Ändere nach der Installation noch folgende Werte:
```
sudo nano /etc/php/8.1/fpm/php.ini
```
```
Ändere folgende Werte:
max_execution_time = 300
max_input_time = 300
memory_limit = 256M
post_max_size = 32M
upload_max_filesize = 32M
```

Nutze dann den folgenden Befehl, um PHP-FPM neuzustarten:
```
systemctl restart php8.3-fpm
```
Falls es eine andere Version (z.B. neue Version) von PHP-FPM ist, kann unter ``/var/run/php/`` mit dem Befehl ``ls`` gesehen werden, welche Version installiert ist