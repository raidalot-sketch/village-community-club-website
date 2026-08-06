<?php
// Deliberately standalone (no bootstrap/database dependency) since a 500
// error may itself be caused by the database being unavailable.
http_response_code(500);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Something Went Wrong</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<section class="section container text-center">
    <h1>Something Went Wrong</h1>
    <p>Sorry, something has gone wrong on our end. Please try again in a few minutes.</p>
    <p><a class="btn btn--primary" href="/">Back to Home</a></p>
</section>
</body>
</html>
