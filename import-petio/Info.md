Dies ist ein Skript in Python geschrieben, dass die Requests aus Petio importieren kann.

## Vorraussetzungen
Ändere in der config.py das Passwort für den Benutzer (unter /var/www/plex-requests/import-petio/db_config.json)

Installiere die benötigten Pakete:
```
apt install python3-pip python3-pandas python3-mysql.connector -y
```
Lade das Skript über das Web-Interface http://IP/import-petio hoch

Führe das Skript mit dem Befehl aus:
```
python import_petio.py
```