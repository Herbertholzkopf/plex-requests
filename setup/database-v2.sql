-- Wahl der Datenbank
USE plex_requests;

-- Tabelle für Provider
CREATE TABLE providers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    provider_name VARCHAR(50) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabelle für Collections
CREATE TABLE collections (
    id INT PRIMARY KEY AUTO_INCREMENT,
    collection_name VARCHAR(50) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Füge die Standard-Provider ein
INSERT INTO providers (provider_name) VALUES
    ('Netflix'),
    ('Disney+'),
    ('Prime Video'),
    ('Plex Movies'),
    ('YouTube'),
    ('Torrent'),
    ('UseNet'),
    ('Apple TV+'),
    ('Joyn'),
    ('Weitere');

-- Füge die Standard-Collections ein
INSERT INTO collections (collection_name) VALUES
    ('Normal'),
    ('Weitere'),
    ('Kinder');

-- Füge neue Spalten zur movies Tabelle hinzu
ALTER TABLE movies
ADD COLUMN provider_id INT AFTER status_id,
ADD COLUMN collection_id INT AFTER provider_id,
ADD FOREIGN KEY (provider_id) REFERENCES providers(id),
ADD FOREIGN KEY (collection_id) REFERENCES collections(id);

-- Indexe für die neuen Felder
CREATE INDEX idx_movies_provider ON movies(provider_id);
CREATE INDEX idx_movies_collection ON movies(collection_id);

-- Erweitere die tv_shows Tabelle um die neuen Felder
ALTER TABLE tv_shows
ADD COLUMN provider_id INT AFTER status_id,
ADD COLUMN collection_id INT AFTER provider_id,
ADD FOREIGN KEY (provider_id) REFERENCES providers(id),
ADD FOREIGN KEY (collection_id) REFERENCES collections(id);

-- Optional: Indexe für die neuen Felder
CREATE INDEX idx_tvshows_provider ON tv_shows(provider_id);
CREATE INDEX idx_tvshows_collection ON tv_shows(collection_id);