<?php
// Initialisiere die Statusvariablen
$message = '';
$messageType = '';

// Prüfe ob ein File hochgeladen wurde
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["csvFile"])) {
    $uploadFile = $_FILES["csvFile"];
    
    // Überprüfe ob es sich um eine CSV-Datei handelt
    $fileType = strtolower(pathinfo($uploadFile["name"], PATHINFO_EXTENSION));
    if ($fileType != "csv") {
        $message = "Es sind nur CSV-Dateien erlaubt.";
        $messageType = "error";
    } else {
        // Setze den Zielpfad (gleiches Verzeichnis wie index.php)
        $targetPath = __DIR__ . '/' . basename($uploadFile["name"]);
        
        // Versuche die Datei hochzuladen
        if (move_uploaded_file($uploadFile["tmp_name"], $targetPath)) {
            $message = "Die Datei " . basename($uploadFile["name"]) . " wurde erfolgreich hochgeladen.";
            $messageType = "success";
        } else {
            $message = "Beim Hochladen der Datei ist ein Fehler aufgetreten.";
            $messageType = "error";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CSV Upload</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .upload-container {
            border: 2px dashed #ccc;
            padding: 20px;
            text-align: center;
            margin: 20px 0;
        }
        .message {
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        .button:hover {
            background-color: #45a049;
        }
    </style>
</head>
<body>
    <h1>CSV Upload</h1>
    
    <?php if ($message): ?>
        <div class="message <?php echo $messageType; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    
    <div class="upload-container">
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data">
            <h2>Wählen Sie eine CSV-Datei aus</h2>
            <input type="file" name="csvFile" accept=".csv" required>
            <br><br>
            <button type="submit" class="button">Hochladen</button>
        </form>
    </div>
</body>
</html>