<?php
// Keine spezielle PHP-Logik benötigt für diese Seite
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plex Requests</title>
    <script src="https://cdn.tailwindcss.com"></script>

    <link rel="icon" type="image/png" href="/icons/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="/icons/favicon.svg" />
    <link rel="shortcut icon" href="/icons/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="/icons/apple-touch-icon.png" />
    <link rel="manifest" href="/icons/site.webmanifest" />
</head>
<body class="bg-gray-100 min-h-screen flex flex-col">
    <div class="container mx-auto px-4 py-8 flex-grow">
        <!-- Header -->
        <div class="text-center mb-16">
            <h1 class="text-4xl font-bold text-gray-800">Plex Requests</h1>
        </div>

        <!-- Main Content -->
        <div class="max-w-2xl mx-auto grid grid-cols-1 md:grid-cols-2 gap-8">
            <!-- Movies Card -->
            <a href="movies.php" class="bg-white rounded-lg shadow-sm hover:shadow-md transition-shadow duration-300 overflow-hidden group">
                <div class="p-8 flex flex-col items-center">
                    <div class="w-24 h-24 bg-blue-500 rounded-full flex items-center justify-center mb-4 group-hover:bg-blue-600 transition-colors duration-300">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 4v16m10-16v16M3 8h18M3 16h18" />
                        </svg>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-800 mb-2">Filme</h2>
                    <p class="text-gray-600 text-center">Durchsuche und verwalte die Film-Anfragen</p>
                </div>
            </a>

            <!-- Series Card -->
            <a href="series.php" class="bg-white rounded-lg shadow-sm hover:shadow-md transition-shadow duration-300 overflow-hidden group">
                <div class="p-8 flex flex-col items-center">
                    <div class="w-24 h-24 bg-blue-500 rounded-full flex items-center justify-center mb-4 group-hover:bg-blue-600 transition-colors duration-300">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                        </svg>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-800 mb-2">Serien</h2>
                    <p class="text-gray-600 text-center">Durchsuche und verwalte die Serien-Anfragen</p>
                </div>
            </a>
        </div>
    </div>

    <!-- Footer -->
    <footer class="text-center py-6 text-gray-600">
        Made with ❤️ by Andreas Koller
    </footer>
</body>
</html>