<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 — System Error</title>
    <?php if (function_exists('asset')): ?>
        <link rel="icon" type="image/png" href="<?= asset('images/pagecom-logo.png') ?>">
    <?php endif; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <?php if (function_exists('asset')): ?>
        <link href="<?= asset('css/app.css') ?>" rel="stylesheet">
    <?php endif; ?>
</head>
<body class="bg-light d-flex align-items-center justify-content-center min-vh-100 p-3">
    <div class="card shadow-sm border-0 p-4 p-md-5 text-center" style="max-width: 540px;">
        <div class="display-5 fw-bold text-danger mb-2">500</div>
        <h1 class="h4 fw-semibold mb-3">Unexpected System Error</h1>
        <p class="text-secondary mb-4">
            We encountered an internal issue while processing your request. No sensitive details were exposed, and the event has been recorded in the server log.
        </p>
        <div>
            <a href="<?= function_exists('url') ? url('/') : './' ?>" class="btn btn-primary">Return Home</a>
        </div>
    </div>
</body>
</html>

