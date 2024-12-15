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
    with open(config_file, 'r') as f:
        return json.load(f)

def connect_to_database(config):
    """Stellt eine Verbindung zur Datenbank her"""
    try:
        connection = mysql.connector.connect(**config)
        return connection
    except Error as e:
        print(f"Fehler bei der Datenbankverbindung: {e}")
        return None

def import_data(csv_file, connection):
    """Importiert die Daten aus der CSV-Datei in die Datenbank"""
    try:
        # CSV einlesen
        df = pd.read_csv(csv_file)
        cursor = connection.cursor()
        
        # Status-ID für "Requested" abrufen
        cursor.execute("SELECT id FROM status_types WHERE status_name = 'Requested'")
        requested_status_id = cursor.fetchone()[0]
        
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
                VALUES (%s, %s, %s, %s)
                """
                cursor.execute(query, (title, 9999, requested_status_id, notes))
                
            elif media_type.lower() == 'tv':
                # Serie einfügen
                # Verfügbare Seasons aus den seasons.X Spalten extrahieren
                seasons = []
                for i in range(23):  # 0 bis 22 (basierend auf CSV-Struktur)
                    season_col = f'seasons.{i}'
                    if season_col in row and pd.notna(row[season_col]):
                        seasons.append(str(i))
                
                available_parts = f"Requested Seasons: {', '.join(seasons)}" if seasons else None
                
                query = """
                INSERT INTO tv_shows (title, release_year, status_id, available_parts, notes)
                VALUES (%s, %s, %s, %s, %s)
                """
                cursor.execute(query, (title, 9999, requested_status_id, available_parts, notes))
        
        # Änderungen bestätigen
        connection.commit()
        print("Import erfolgreich abgeschlossen!")
        
    except Error as e:
        print(f"Fehler beim Import: {e}")
        connection.rollback()
    except Exception as e:
        print(f"Unerwarteter Fehler: {e}")
        connection.rollback()
    finally:
        if cursor:
            cursor.close()

def main():
    try:
        # Konfiguration laden
        config = load_config()
        
        # Datenbankverbindung herstellen
        connection = connect_to_database(config)
        if not connection:
            return
        
        # CSV-Datei importieren
        import_data('petio.requests.csv', connection)
        
    finally:
        if connection and connection.is_connected():
            connection.close()
            print("Datenbankverbindung geschlossen.")

if __name__ == "__main__":
    main()