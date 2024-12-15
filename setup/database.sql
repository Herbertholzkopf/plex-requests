-- Erstelle die Datenbank
CREATE DATABASE plex_requests DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE plex_requests;

-- Tabelle für die Status-Werte (um Konsistenz zu gewährleisten)
CREATE TABLE status_types (
    id INT PRIMARY KEY AUTO_INCREMENT,
    status_name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabelle für Filme
CREATE TABLE movies (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    release_year YEAR,
    status_id INT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (status_id) REFERENCES status_types(id)
);

-- Tabelle für Serien
CREATE TABLE tv_shows (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    release_year YEAR,
    status_id INT,
    available_parts TEXT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (status_id) REFERENCES status_types(id)
);

-- Füge Standard-Status-Typen ein
INSERT INTO status_types (status_name, description) VALUES
    ('Requested', 'Wurde angefragt, aber noch nicht bearbeitet'),
    ('Downloading', 'Wird gerade heruntergeladen'),
    ('Added', 'Wurde erfolgreich hinzugefügt'),
    ('Errors', 'Fehler beim Herunterladen/Hinzufügen'),
    ('Not Found', 'Konnte nicht gefunden werden'),
    ('Parts Missing', 'Nur für Serien: Einige Teile fehlen noch');

-- Indexe für bessere Performance
CREATE INDEX idx_movies_title ON movies(title);
CREATE INDEX idx_movies_status ON movies(status_id);
CREATE INDEX idx_tvshows_title ON tv_shows(title);
CREATE INDEX idx_tvshows_status ON tv_shows(status_id);