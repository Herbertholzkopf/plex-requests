<?php
// Konfiguration und Datenbankverbindung
function getConfig() {
    $configFile = __DIR__ . '/config.php';
    if (!file_exists($configFile)) {
        die('Configuration file not found');
    }
    return require $configFile;
}

function getDatabaseConnection() {
    $config = getConfig();
    try {
        $pdo = new PDO(
            "mysql:host={$config['server']};dbname={$config['database']};charset=utf8mb4",
            $config['user'],
            $config['password'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        die('Connection failed: ' . $e->getMessage());
    }
}

$pdo = getDatabaseConnection();

// Handle DELETE requests für das Löschen
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    header('Content-Type: application/json');
    
    try {
        $id = $_GET['id'] ?? null;
        if (!$id) {
            throw new Exception('No ID provided');
        }

        $stmt = $pdo->prepare("DELETE FROM movies WHERE id = ?");
        $stmt->execute([$id]);

        echo json_encode(['success' => true]);
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}

// Handle POST requests für das Speichern/Aktualisieren
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    try {
        $params = [
            'title' => $_POST['title'],
            'release_year' => $_POST['release_year'],
            'status_id' => $_POST['status_id'],
            'provider_id' => $_POST['provider_id'] ?: null,
            'collection_id' => $_POST['collection_id'] ?: null,
            'notes' => $_POST['notes']
        ];

        if (empty($_POST['id'])) {
            // Neuen Film hinzufügen
            $stmt = $pdo->prepare("
                INSERT INTO movies (title, release_year, status_id, provider_id, collection_id, notes)
                VALUES (:title, :release_year, :status_id, :provider_id, :collection_id, :notes)
            ");
        } else {
            // Existierenden Film aktualisieren
            $params['id'] = $_POST['id'];
            $stmt = $pdo->prepare("
                UPDATE movies
                SET title = :title,
                    release_year = :release_year,
                    status_id = :status_id,
                    provider_id = :provider_id,
                    collection_id = :collection_id,
                    notes = :notes
                WHERE id = :id
            ");
        }

        $stmt->execute($params);

        echo json_encode(['success' => true]);
        exit;
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}

// Hole alle Status-Typen für das Formular
$stmt = $pdo->query("SELECT * FROM status_types WHERE status_name != 'Parts Missing'");
$statusTypes = $stmt->fetchAll();

// Hole alle Provider
$stmt = $pdo->query("SELECT * FROM providers ORDER BY provider_name");
$providers = $stmt->fetchAll();

// Hole alle Collections
$stmt = $pdo->query("SELECT * FROM collections ORDER BY collection_name");
$collections = $stmt->fetchAll();

// Erstelle die WHERE-Bedingungen basierend auf den Filtern
$where = [];
$params = [];

if (!empty($_GET['search'])) {
    $where[] = "m.title LIKE ?";
    $params[] = '%' . $_GET['search'] . '%';
}

if (!empty($_GET['status_filter'])) {
    $where[] = "m.status_id = ?";
    $params[] = $_GET['status_filter'];
}

if (!empty($_GET['provider_filter'])) {
    $where[] = "m.provider_id = ?";
    $params[] = $_GET['provider_filter'];
}

// Baue die WHERE-Klausel
$whereClause = '';
if (!empty($where)) {
    $whereClause = 'WHERE ' . implode(' AND ', $where);
}

// Hole alle Filme für die Anzeige
$query = "
    SELECT m.*, st.status_name, p.provider_name
    FROM movies m
    JOIN status_types st ON m.status_id = st.id
    LEFT JOIN providers p ON m.provider_id = p.id
    {$whereClause}
    ORDER BY m.title
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$movies = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plex Requests - Filme</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }
        .modal.active {
            display: flex;
        }
    </style>

    <link rel="icon" type="image/png" href="/icons/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="/icons/favicon.svg" />
    <link rel="shortcut icon" href="/icons/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="/icons/apple-touch-icon.png" />
    <link rel="manifest" href="/icons/site.webmanifest" />
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold text-gray-800">Plex Requests</h1>
            <div class="bg-white rounded-lg shadow-sm">
                <a href="movies.php" class="px-4 py-2 bg-blue-500 text-white rounded-l-lg">Filme</a>
                <a href="series.php" class="px-4 py-2 hover:bg-gray-100 rounded-r-lg">Serien</a>
            </div>
        </div>

        <!-- Search and Filter -->
        <div class="bg-white rounded-lg shadow-sm p-4 mb-4">
            <form method="get" class="flex gap-4">
                <div class="flex-1">
                    <input 
                        type="text" 
                        name="search" 
                        placeholder="Nach Filmtitel suchen..." 
                        value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>"
                        class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                </div>
                <div class="w-48">
                    <select 
                        name="status_filter" 
                        class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                        <option value="">Alle Status</option>
                        <?php foreach ($statusTypes as $status): ?>
                            <option value="<?php echo $status['id']; ?>" <?php echo (isset($_GET['status_filter']) && $_GET['status_filter'] == $status['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($status['status_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="w-48">
                    <select 
                        name="provider_filter" 
                        class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                        <option value="">Alle Anbieter</option>
                        <?php foreach ($providers as $provider): ?>
                            <option value="<?php echo $provider['id']; ?>" <?php echo (isset($_GET['provider_filter']) && $_GET['provider_filter'] == $provider['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($provider['provider_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="px-6 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">
                    Suchen
                </button>
                <?php if (!empty($_GET['search']) || !empty($_GET['status_filter']) || !empty($_GET['provider_filter'])): ?>
                    <a href="movies.php" class="px-6 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600">
                        Zurücksetzen
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Movies Table -->
        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Jahr</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Anbieter</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Notizen</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($movies as $movie): ?>
                    <tr class="hover:bg-gray-50 cursor-pointer" onclick="showEditModal(<?php echo htmlspecialchars(json_encode($movie)); ?>)">
                        <td class="px-6 py-4"><?php echo htmlspecialchars($movie['title']); ?></td>
                        <td class="px-6 py-4"><?php echo htmlspecialchars($movie['release_year']); ?></td>
                        <td class="px-6 py-4">
                            <span class="px-2 py-1 text-sm rounded-full <?php echo getStatusClass($movie['status_name']); ?>">
                                <?php echo htmlspecialchars($movie['status_name']); ?>
                            </span>
                        </td>
                        <td class="px-6 py-4"><?php echo htmlspecialchars($movie['provider_name'] ?? '-'); ?></td>
                        <td class="px-6 py-4"><?php echo htmlspecialchars($movie['notes']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Floating Add Button -->
    <button onclick="showAddModal()" class="fixed bottom-8 right-8 bg-green-500 text-white p-4 rounded-full shadow-lg hover:bg-green-600 flex items-center space-x-2">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        <span>Film hinzufügen</span>
    </button>

    <!-- Add/Edit Modal -->
    <div id="movieModal" class="modal">
        <div class="modal-content bg-white w-full max-w-lg mx-auto mt-20 rounded-lg shadow-lg p-6">
            <h2 id="modalTitle" class="text-2xl font-bold mb-4">Film hinzufügen</h2>
            <form id="movieForm" method="post">
                <input type="hidden" id="movieId" name="id">
                
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="title">
                        Filmtitel
                    </label>
                    <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                           id="title" name="title" type="text" required>
                </div>

                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="release_year">
                        Erscheinungsjahr
                    </label>
                    <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                           id="release_year" name="release_year" type="number" min="1900" max="2099" required>
                </div>

                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="status_id">
                        Status
                    </label>
                    <select class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                            id="status_id" name="status_id" required>
                        <?php foreach ($statusTypes as $status): ?>
                        <option value="<?php echo $status['id']; ?>"><?php echo htmlspecialchars($status['status_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="provider_id">
                        Streaming-Anbieter
                    </label>
                    <select class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                            id="provider_id" name="provider_id">
                        <option value="">Bitte wählen...</option>
                        <?php foreach ($providers as $provider): ?>
                        <option value="<?php echo $provider['id']; ?>"><?php echo htmlspecialchars($provider['provider_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="collection_id">
                        Plex-Sammlung
                    </label>
                    <select class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                            id="collection_id" name="collection_id">
                        <option value="">Bitte wählen...</option>
                        <?php foreach ($collections as $collection): ?>
                        <option value="<?php echo $collection['id']; ?>"><?php echo htmlspecialchars($collection['collection_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="notes">
                        Notizen
                    </label>
                    <textarea class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                              id="notes" name="notes" rows="3"></textarea>
                </div>

                <div class="flex justify-between">
                    <button 
                        type="button" 
                        onclick="deleteMovie()" 
                        class="delete-btn invisible px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600">
                        Löschen
                    </button>
                    <div class="flex gap-2">
                        <button type="button" onclick="hideModal()" class="px-4 py-2 text-gray-600 hover:text-gray-800">
                            Abbrechen
                        </button>
                        <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                            Speichern
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        <?php
        function getStatusClass($status) {
            $classes = [
                'Requested' => 'bg-yellow-100 text-yellow-800',
                'Downloading' => 'bg-blue-100 text-blue-800',
                'Added' => 'bg-green-100 text-green-800',
                'Errors' => 'bg-red-100 text-red-800',
                'Not Found' => 'bg-gray-100 text-gray-800',
                'Upgrade' => 'bg-purple-100 text-purple-800'
            ];
            return $classes[$status] ?? 'bg-gray-100 text-gray-800';
        }
        ?>

        function showAddModal() {
            document.getElementById('modalTitle').textContent = 'Film hinzufügen';
            document.getElementById('movieForm').reset();
            document.getElementById('movieId').value = '';
            // Verstecke den Löschen-Button
            document.querySelector('.delete-btn').classList.add('invisible');
            document.getElementById('movieModal').classList.add('active');
        }

        function showEditModal(movie) {
            document.getElementById('modalTitle').textContent = 'Film bearbeiten';
            document.getElementById('movieId').value = movie.id;
            document.getElementById('title').value = movie.title;
            document.getElementById('release_year').value = movie.release_year;
            document.getElementById('status_id').value = movie.status_id;
            document.getElementById('provider_id').value = movie.provider_id;
            document.getElementById('collection_id').value = movie.collection_id;
            document.getElementById('notes').value = movie.notes;
            // Zeige den Löschen-Button
            document.querySelector('.delete-btn').classList.remove('invisible');
            document.getElementById('movieModal').classList.add('active');
        }

        function hideModal() {
            document.getElementById('movieModal').classList.remove('active');
        }

        async function deleteMovie() {
            const movieId = document.getElementById('movieId').value;
            if (!movieId) return;

            if (!confirm('Möchten Sie diesen Film wirklich löschen?')) return;

            try {
                const response = await fetch(`${window.location.href}?id=${movieId}`, {
                    method: 'DELETE'
                });
                
                const result = await response.json();
                if (result.success) {
                    hideModal();
                    window.location.reload();
                } else {
                    alert('Fehler beim Löschen: ' + (result.error || 'Unbekannter Fehler'));
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Fehler beim Löschen');
            }
        }

        // Form submission handling
        document.getElementById('movieForm').addEventListener('submit', async function(event) {
            event.preventDefault();
            const formData = new FormData(event.target);
            
            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                if (result.success) {
                    hideModal();
                    window.location.reload();
                } else {
                    alert('Fehler beim Speichern: ' + (result.error || 'Unbekannter Fehler'));
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Fehler beim Speichern');
            }
        });

        // Close modal when clicking outside
        document.getElementById('movieModal').addEventListener('click', function(event) {
            if (event.target === this) {
                hideModal();
            }
        });
    </script>
</body>
</html>