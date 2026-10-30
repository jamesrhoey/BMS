<?php

declare(strict_types=1);

use App\Core\Bootstrap;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Session;

require_once dirname(__DIR__) . '/app/Core/Bootstrap.php';

Bootstrap::init(dirname(__DIR__));

$request = new Request();
Response::setBasePath($request->basePath());
Session::start($request);

$router = new Router();
require BASE_PATH . '/routes/web.php';

$router->dispatch($request);

