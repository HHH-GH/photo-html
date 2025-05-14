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
<title>Make Srcset - Photos to HTML</title>
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

<nav class="navbar navbar-dark bg-dark navbar-expand" id="top">
    <div class="container-fluid">
        <a class="navbar-brand" href="../">Photos to HTML</a>
        <div class="collapse navbar-collapse" id="navbarSupportedContent">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link" href="../print-photo-set/">Print Photo Set</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" aria-current="page" href="./">Make Srcset</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<main class="container container-xl">

    <header class="page-header my-5 text-center">
		<h1>Make Srcset</h1>
		<p class="lead">Fill in the form and submit to generate HTML code for featured image and list image srcsets, as long as the images are named according to the rules.</p>

        <ol class="text-left text-muted w-50 mx-auto">
			<li>Delete old photos from ./photos</li>
			<li>Copy the new photos into ./photos, one set at a time</li>
			<li>Fill form, submit</li>
			<li>Copy the generated srcsets</li>
            <li>
                <details>
                    <summary>Image naming rules</summary>
                    <p>Expects six photos, same basename, suffixed with the sizes as below (because that's what I need for my use case).</p>
                    <pre class="text-left">
                    BASENAME_112x112.jpg
                    BASENAME_192x128.jpg
                    BASENAME_320x215.jpg
                    BASENAME_608x344.jpg
                    BASENAME_720x405.jpg
                    BASENAME_1024x576.jpg
                    </pre>
                </details>
		</ol>

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