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
        $params = [
            'title' => $_POST['title'],
            'release_year' => $_POST['release_year'],
            'status_id' => $_POST['status_id'],
            'available_parts' => $_POST['available_parts'],
            'notes' => $_POST['notes']
        ];

        if (empty($_POST['id'])) {
            // Neue Serie hinzufügen
            $stmt = $pdo->prepare("
                INSERT INTO tv_shows (title, release_year, status_id, available_parts, notes)
                VALUES (:title, :release_year, :status_id, :available_parts, :notes)
            ");
        } else {
            // Existierende Serie aktualisieren
            $params['id'] = $_POST['id'];
            $stmt = $pdo->prepare("
                UPDATE tv_shows
                SET title = :title,
                    release_year = :release_year,
                    status_id = :status_id,
                    available_parts = :available_parts,
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

// Hole alle Serien für die Anzeige
$stmt = $pdo->query("
    SELECT s.*, st.status_name 
    FROM tv_shows s
    JOIN status_types st ON s.status_id = st.id
    ORDER BY s.title
");
$series = $stmt->fetchAll();

// Hole alle Status-Typen für das Formular (inkl. 'Parts Missing')
$stmt = $pdo->query("SELECT * FROM status_types");
$statusTypes = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plex Requests - Serien</title>
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
                <a href="movies.php" class="px-4 py-2 hover:bg-gray-100 rounded-l-lg">Filme</a>
                <a href="series.php" class="px-4 py-2 bg-blue-500 text-white rounded-r-lg">Serien</a>
            </div>
        </div>

        <!-- Series Table -->
        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Jahr</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Vorhanden</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Notizen</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($series as $show): ?>
                    <tr class="hover:bg-gray-50 cursor-pointer" onclick="showEditModal(<?php echo htmlspecialchars(json_encode($show)); ?>)">
                        <td class="px-6 py-4"><?php echo htmlspecialchars($show['title']); ?></td>
                        <td class="px-6 py-4"><?php echo htmlspecialchars($show['release_year']); ?></td>
                        <td class="px-6 py-4">
                            <span class="px-2 py-1 text-sm rounded-full <?php echo getStatusClass($show['status_name']); ?>">
                                <?php echo htmlspecialchars($show['status_name']); ?>
                            </span>
                        </td>
                        <td class="px-6 py-4"><?php echo htmlspecialchars($show['available_parts']); ?></td>
                        <td class="px-6 py-4"><?php echo htmlspecialchars($show['notes']); ?></td>
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
        <span>Serie hinzufügen</span>
    </button>

    <!-- Add/Edit Modal -->
    <div id="seriesModal" class="modal">
        <div class="modal-content bg-white w-full max-w-lg mx-auto mt-20 rounded-lg shadow-lg p-6">
            <h2 id="modalTitle" class="text-2xl font-bold mb-4">Serie hinzufügen</h2>
            <form id="seriesForm" method="post">
                <input type="hidden" id="seriesId" name="id">
                
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="title">
                        Serientitel
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
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="available_parts">
                        Vorhandene Teile
                    </label>
                    <textarea class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                              id="available_parts" name="available_parts" rows="2"></textarea>
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
        function getStatusClass($status) {
            $classes = [
                'Requested' => 'bg-yellow-100 text-yellow-800',
                'Downloading' => 'bg-blue-100 text-blue-800',
                'Added' => 'bg-green-100 text-green-800',
                'Errors' => 'bg-red-100 text-red-800',
                'Not Found' => 'bg-gray-100 text-gray-800',
                'Parts Missing' => 'bg-orange-100 text-orange-800'
            ];
            return $classes[$status] ?? 'bg-gray-100 text-gray-800';
        }
        ?>

        function showAddModal() {
            document.getElementById('modalTitle').textContent = 'Serie hinzufügen';
            document.getElementById('seriesForm').reset();
            document.getElementById('seriesId').value = '';
            document.getElementById('seriesModal').classList.add('active');
        }

        function showEditModal(series) {
            document.getElementById('modalTitle').textContent = 'Serie bearbeiten';
            document.getElementById('seriesId').value = series.id;
            document.getElementById('title').value = series.title;
            document.getElementById('release_year').value = series.release_year;
            document.getElementById('status_id').value = series.status_id;
            document.getElementById('available_parts').value = series.available_parts;
            document.getElementById('notes').value = series.notes;
            document.getElementById('seriesModal').classList.add('active');
        }

        function hideModal() {
            document.getElementById('seriesModal').classList.remove('active');
        }

        // Form submission handling
        document.getElementById('seriesForm').addEventListener('submit', async function(event) {
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
        document.getElementById('seriesModal').addEventListener('click', function(event) {
            if (event.target === this) {
                hideModal();
            }
        });
    </script>
</body>
</html>