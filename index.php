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
<title>Photos to HTML</title>
<!-- Required meta tags -->
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
<meta name="robots" content="noindex, nofollow">

<!-- Bootstrap CSS -->
<link rel="stylesheet" href="css/bootstrap.min.css">
<!-- Extra styles -->
<link rel="stylesheet" href="css/styles.css">

</head>
<body>


<nav class="navbar navbar-dark bg-dark navbar-expand" id="top">
    <div class="container-fluid">
        <a class="navbar-brand" aria-current="page" href="./">Photos to HTML</a>
        <div class="collapse navbar-collapse" id="navbarSupportedContent">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link" href="./print-photo-set/">Print Photo Set</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="./make-srcset/">Make Srcset</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<main class="container container-xl">

    <header class="page-header my-5 text-center">
		<h1>Photos to HTMl</h1>
		<p class="lead">Turn a folder of photos into a set of <code>figure</code> tags or a <code>srcset</code></p>
	</header>

    <section class="card my-5">
        <div class="card-body">
            
            <h2 class="card-title">Make a set of <code>figure</code> tags</h2>
            <p>You give it a folder location, it loops through all the images in that folder and prints <code>html</code> <code>figure</code> &amp; <code>img</code> tags to copy into your CMS or whatever</p>
            <a href="./print-photo-set/" class="btn btn-primary">Print Photo Set</a>
        </div>
    </section>

    <section class="card my-5">
        <div class="card-body">            
            <h2 class="card-title">Make a <code>srcset</code></h2>
            <p>You put some specially named photos into the <code>photos</code> folder, and if you name them right you get a <code>srcset</code> and standalone <code>img</code> tags to copy into a CMS or whatever.</p>        
            <a href="./make-srcset/" class="btn btn-primary">Make Srcset</a>
        
        </div>
    </section>

    <div class="back-to-top-wrapper">
        <div class="back-to-top-link-container border border-secondary rounded p-2 bg-light mb-2">
            <a href="#top" class="back-to-top-link btn btn-link text-nowrap" aria-label="Scroll to Top">Back to top</a><br>
        </div>
    </div>

</main>

</body>
</html>