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

// Handle POST requests für das Speichern/Aktualisieren
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    try {
        if (empty($_POST['id'])) {
            // Neuen Film hinzufügen
            $stmt = $pdo->prepare("
                INSERT INTO movies (title, release_year, status_id, notes)
                VALUES (:title, :release_year, :status_id, :notes)
            ");
        } else {
            // Existierenden Film aktualisieren
            $stmt = $pdo->prepare("
                UPDATE movies
                SET title = :title,
                    release_year = :release_year,
                    status_id = :status_id,
                    notes = :notes
                WHERE id = :id
            ");
        }

        $stmt->execute([
            'id' => $_POST['id'] ?? null,
            'title' => $_POST['title'],
            'release_year' => $_POST['release_year'],
            'status_id' => $_POST['status_id'],
            'notes' => $_POST['notes']
        ]);

        echo json_encode(['success' => true]);
        exit;
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}

// Hole alle Filme für die Anzeige
$stmt = $pdo->query("
    SELECT m.*, st.status_name 
    FROM movies m
    JOIN status_types st ON m.status_id = st.id
    ORDER BY m.title
");
$movies = $stmt->fetchAll();

// Hole alle Status-Typen für das Formular
$stmt = $pdo->query("SELECT * FROM status_types WHERE status_name != 'Parts Missing'");
$statusTypes = $stmt->fetchAll();
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

        <!-- Add Button -->
        <div class="mb-6">
            <button onclick="showAddModal()" class="bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600">
                Film hinzufügen
            </button>
        </div>

        <!-- Movies Table -->
        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Jahr</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
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
                        <td class="px-6 py-4"><?php echo htmlspecialchars($movie['notes']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

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
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="notes">
                        Notizen
                    </label>
                    <textarea class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                              id="notes" name="notes" rows="3"></textarea>
                </div>

                <div class="flex justify-end">
                    <button type="button" onclick="hideModal()" class="mr-2 px-4 py-2 text-gray-600 hover:text-gray-800">
                        Abbrechen
                    </button>
                    <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                        Speichern
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        <?php
        // PHP-Funktion für Status-Klassen ins JavaScript übertragen
        function getStatusClass($status) {
            $classes = [
                'Requested' => 'bg-yellow-100 text-yellow-800',
                'Downloading' => 'bg-blue-100 text-blue-800',
                'Added' => 'bg-green-100 text-green-800',
                'Errors' => 'bg-red-100 text-red-800',
                'Not Found' => 'bg-gray-100 text-gray-800'
            ];
            return $classes[$status] ?? 'bg-gray-100 text-gray-800';
        }
        ?>

        function showAddModal() {
            document.getElementById('modalTitle').textContent = 'Film hinzufügen';
            document.getElementById('movieForm').reset();
            document.getElementById('movieId').value = '';
            document.getElementById('movieModal').classList.add('active');
        }

        function showEditModal(movie) {
            document.getElementById('modalTitle').textContent = 'Film bearbeiten';
            document.getElementById('movieId').value = movie.id;
            document.getElementById('title').value = movie.title;
            document.getElementById('release_year').value = movie.release_year;
            document.getElementById('status_id').value = movie.status_id;
            document.getElementById('notes').value = movie.notes;
            document.getElementById('movieModal').classList.add('active');
        }

        function hideModal() {
            document.getElementById('movieModal').classList.remove('active');
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