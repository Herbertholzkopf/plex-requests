import pandas as pd
import mysql.connector
from mysql.connector import Error
import json
import os

def load_config(config_file='db_config.json'):
    """
    Lädt die Datenbank-Konfiguration aus einer JSON-Datei
    Beispiel für db_config.json:
    {
        "host": "localhost",
        "user": "your_username",
        "password": "your_password",
        "database": "plex_requests"
    }
    """
    try:
        with open(config_file, 'r') as f:
            config = json.load(f)
            return config
    except FileNotFoundError:
        print(f"Konfigurationsdatei {config_file} nicht gefunden!")
        return None
    except json.JSONDecodeError:
        print(f"Fehler beim Lesen der Konfigurationsdatei. Bitte überprüfen Sie das JSON-Format.")
        return None

def connect_to_database(config):
    """Stellt eine Verbindung zur Datenbank her"""
    if not config:
        return None
        
    try:
        # Verbindung ohne SSL
        connection = mysql.connector.connect(
            host=config['host'],
            user=config['user'],
            password=config['password'],
            database=config['database'],
            ssl_disabled=True,
            auth_plugin='mysql_native_password'
        )
        print("Datenbankverbindung erfolgreich hergestellt!")
        return connection
    except Error as e:
        print(f"Fehler bei der Datenbankverbindung: {e}")
        return None

def import_data(csv_file, connection):
    """Importiert die Daten aus der CSV-Datei in die Datenbank"""
    if not connection:
        print("Keine Datenbankverbindung vorhanden!")
        return

    cursor = None
    try:
        # CSV einlesen
        print(f"Lese CSV-Datei {csv_file}...")
        df = pd.read_csv(csv_file)
        print(f"CSV erfolgreich gelesen. {len(df)} Einträge gefunden.")
        
        cursor = connection.cursor()
        
        # Status-ID für "Requested" abrufen
        cursor.execute("SELECT id FROM status_types WHERE status_name = 'Requested'")
        result = cursor.fetchone()
        if not result:
            print("Status 'Requested' nicht in der Datenbank gefunden!")
            return
        requested_status_id = result[0]
        
        # Zähler für die Statistik
        movies_count = 0
        tv_shows_count = 0
        
        # Daten verarbeiten und einfügen
        for _, row in df.iterrows():
            media_type = row['type']
            title = row['title']
            tmdb_id = row['tmdb_id']
            notes = f"TMDB: {tmdb_id}" if pd.notna(tmdb_id) else None
            
            if media_type.lower() == 'movie':
                # Film einfügen
                query = """
                INSERT INTO movies (title, release_year, status_id, notes)
                VALUES (%s, NULL, %s, %s)
                """
                cursor.execute(query, (title, requested_status_id, notes))
                movies_count += 1
                
            elif media_type.lower() == 'tv':
                # Serie einfügen
                query = """
                INSERT INTO tv_shows (title, release_year, status_id, notes)
                VALUES (%s, NULL, %s, %s)
                """
                cursor.execute(query, (title, requested_status_id, notes))
                tv_shows_count += 1
        
        # Änderungen bestätigen
        connection.commit()
        print(f"\nImport erfolgreich abgeschlossen!")
        print(f"Importierte Filme: {movies_count}")
        print(f"Importierte Serien: {tv_shows_count}")
        print(f"Gesamt: {movies_count + tv_shows_count}")
        
    except Error as e:
        print(f"Fehler beim Import: {e}")
        if connection:
            connection.rollback()
    except Exception as e:
        print(f"Unerwarteter Fehler: {e}")
        if connection:
            connection.rollback()
    finally:
        if cursor:
            cursor.close()

def main():
    connection = None
    try:
        # Konfiguration laden
        config = load_config()
        if not config:
            return
        
        # Datenbankverbindung herstellen
        connection = connect_to_database(config)
        if not connection:
            return
        
        # CSV-Datei importieren
        import_data('petio.requests.csv', connection)
        
    except Exception as e:
        print(f"Hauptprogramm-Fehler: {e}")
    finally:
        if connection:
            try:
                if connection.is_connected():
                    connection.close()
                    print("Datenbankverbindung geschlossen.")
            except Error:
                print("Fehler beim Schließen der Datenbankverbindung.")

if __name__ == "__main__":
    main()