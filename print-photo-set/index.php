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
    'PHOTOS_PUBLIC_BASE_URL',
    'PHOTOS_PRINT_LOCAL_PATH',
    'PHOTOS_SRCSET_RELATIVE_PATH',
    'PHOTOS_SRCSET_ABSOLUTE_PATH'
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
<title>Print Photo Set - Photos to HTML</title>
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
                    <a class="nav-link" aria-current="page" href="./">Print Photo Set</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../make-srcset/">Make Srcset</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<main class="container container-xl">
    
    <div class="col-8">

        <header class="page-header my-5">
            <h1>Print Photo Set</h1>
            <p class="lead">Fill in the form and submit to generate HTML code for a set of photos.</p>
        </header>

    </div>

    <div class="col-8" id="formheader">
        
        <form action="./index.php" method="post">

            <div class="mb-3">
                <label for="photoset_title" class="form-label">Title of photo set</label>
                <input type="text" class="form-control" id="photoset_title" name="photoset_title" aria-describedby="photoset_title_help" required maxlength="255">
                <div id="photoset_title_help" class="form-text">e.g. Switchback Great Wall Camping, 2017/10/06</div>
            </div>

            <div class="mb-3">
                <label for="photoset_intro" class="form-label">Brief intro of photo set</label>
                <textarea class="form-control" id="photoset_intro" name="photoset_intro" aria-describedby="photoset_intro_help" required></textarea>
                <div id="photoset_intro_help" class="form-text">A description of what’s in the set of photos e.g. 18 photos from a hike &hellip;</div>
            </div>

            <label for="photoset_folder" class="form-label">FTP folder name</label>
                <div class="input-group mb-3 has-validation">
                <span class="input-group-text" id="photoset_folder_tip"><?php echo PHOTOS_PUBLIC_BASE_URL; ?></span>
                <input type="text" class="form-control" id="photoset_folder" name="photoset_folder" aria-describedby="photoset_folder_tip" required pattern="^[\d\w\-]*" title="Only letters or numbers or dashes">
                <div id="photoset_folder_help" class="form-text">The name of the folder where you uploaded the photos e.g. <samp>20171006-SwitchbackGreatWallCamping</samp></div>
            </div>

            <button type="submit" class="btn btn-primary">Generate the HTML code</button> <a href="./" class="btn btn-link ms-4">Start again</a>

        </form>

    </div>

    <hr>

    <div class="col-6">
        <h2>Photos preview and HTML</h2>
        <p class="lead">Preview the photos, and then copy the HTML.</p>
    </div>

    <hr>

    <div class="col-8">
        <h2>Copy the HTML from here</h2>    
        <p>Copy-paste form goes here</p>
    </div>

    <div class="back-to-top-wrapper">
        <div class="back-to-top-link-container border border-secondary rounded p-2 bg-light mb-2">
            <a href="#formheader" class="back-to-top-link btn btn-link text-nowrap" aria-label="Scroll to Form">Scroll to form</a><br>
            <a href="#top" class="back-to-top-link btn btn-link text-nowrap" aria-label="Scroll to Top">Back to top</a><br>
        </div>
    </div>
    
</main>

</body>
</html>