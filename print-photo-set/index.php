<?php
// Check if the env file exists and if the constants are defined
$envFile = dirname(__DIR__) . '/env.php';
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
<title>Photos to HTML</title>
<!-- Required meta tags -->
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
<meta name="robots" content="noindex, nofollow">

<!-- Bootstrap CSS -->
<link rel="stylesheet" href="../css/bootstrap.min.css">
<!-- Extra styles -->
<link rel="stylesheet" href="../css/styles.css">

</head>
<body>

<nav id="top" class="navbar-dark bg-dark p-2 navbar-expand">    
    <div class="" id="navbarSupportedContent">
        <ul class="navbar-nav">
            <li class="nav-item">
                <span class="navbar-brand">Photos to HTML</span>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="../">Back to index</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="../print-photo-set/">Print Photo Set</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="../make-srcset/">Make Srcset</a>
            </li>
        </ul>
    </div>
</nav>

<main class="container container-xl">

    <header class="page-header my-5 text-center">
		<h1>Print Photo Set</h1>
		<p class="lead">Fill in the form and submit to generate HTML code for a set of photos.</p>
	</header>

    <p>Form goes here</p>

    <div class="back-to-top-wrapper">
        <div class="back-to-top-link-container border border-secondary rounded p-2 bg-light mb-2">
            <a href="#formheader" class="back-to-top-link btn btn-link text-nowrap" aria-label="Scroll to Form">Scroll to form</a><br>
            <a href="#top" class="back-to-top-link btn btn-link text-nowrap" aria-label="Scroll to Top">Back to top</a><br>
        </div>
    </div>

</main>

</body>
</html>