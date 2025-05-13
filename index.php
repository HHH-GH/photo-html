<?php
// Check if the env file exists and if the constants are defined
$envFile = __DIR__ . '/env.php';
if (!file_exists($envFile)) {
    die('Missing env.php. Copy env.php.sample, update it, and save it as env.php to begin using the scripts.');
}
// Load env.php
require $envFile;

// Check required constants are defined and not empty
$requiredConstants = [
    'BASE_PHOTOS_LOCATION_ABSOLUTE',
    'BASE_PHOTOS_LOCATION_PREVIEW_RELATIVE',
    'BASE_PHOTOS_LOCATION_PREVIEW_ABSOLUTE'
];

foreach ($requiredConstants as $const) {
    if (!defined($const) || trim(constant($const)) === '') {
        die("Missing or empty config constant: $const");
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<title>Make srcset</title>
<!-- Required meta tags -->
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

<!-- Bootstrap CSS -->
<link rel="stylesheet" href="css/bootstrap.min.css">
<!-- Extra styles -->
<link rel="stylesheet" href="css/styles.css">

</head>
<body>

</body>
</html>