<?php

// Подключение автозагрузки через composer
require __DIR__ . '/../vendor/autoload.php';

use Slim\Middleware\MethodOverrideMiddleware;
use Slim\Factory\AppFactory;
use DI\Container;
use GuzzleHttp\Client;
use DiDom\Document;

session_start();
$container = new Container();
$container->set('renderer', function () {
    // Параметром передается базовая директория, в которой будут храниться шаблоны
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
});

$app = AppFactory::createFromContainer($container);

$container->set('flash', function () {
    return new \Slim\Flash\Messages();
});

$container->set('connection', function () {
    if (getenv('DATABASE_URL')) {
        $databaseUrl = parse_url(getenv('DATABASE_URL'));
    }
    if (isset($databaseUrl['host'])) {
        $params['host'] = $databaseUrl['host'];
        $params['port'] = isset($databaseUrl['port']) ? $databaseUrl['port'] : 5432;
        $params['database'] = isset($databaseUrl['path']) ?
        ltrim($databaseUrl['path'], '/') : null;
        $params['user'] = isset($databaseUrl['user']) ?
        $databaseUrl['user'] : null;
        $params['password'] = isset($databaseUrl['pass']) ?
        $databaseUrl['pass'] : null;
    } else {
        $params = parse_ini_file('../src/database.ini');
    }
    if ($params === false) {
        throw new \Exception("Error reading database configuration file");
    }
    $conStr = sprintf(
        "pgsql:host=%s;port=%d;dbname=%s;user=%s;password=%s",
        $params['host'],
        $params['port'],
        $params['database'],
        $params['user'],
        $params['password']
    );
    $pdo = new \PDO($conStr);
    $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    return $pdo;
});

$app->addErrorMiddleware(true, true, true);
$app->add(MethodOverrideMiddleware::class);
$router = $app->getRouteCollector()->getRouteParser();

$app->get('/', function ($request, $response) {
        return $this->get('renderer')->render($response, 'index.phtml');
});

$app->post('/urls', function ($request, $response) use ($router) {
    $pdo = $this->get('connection');
    $url = $request->getParsedBodyParam('url');
    $v = new Valitron\Validator(array('name' => $url['name']));
    $v->rule('required', 'name')->message('URL не должен быть пустым');
    $v->rule('lengthMax', 'name', 255)->message('Длинна ссылки не должна превышать 255 символов');
    $v->rule('url', 'name')->message('Некорректный URL');

    if (!$v->validate()) {
        $params = [
            'errors' => $v->errors(),
            'url' => $url['name']
        ];
        return $this->get('renderer')->render($response, 'index.phtml', $params)->withStatus(422);
    }

    $stmt = $pdo->prepare("SELECT * FROM urls WHERE name=:name");
    $stmt->execute(['name' => $url['name']]);
    $urls = $stmt->fetch(\PDO::FETCH_ASSOC);

    if (!$urls) {
        $nowData = new DateTime('now');
        $created_at = $nowData->format('Y-m-d H:i:s');
        $sql = "INSERT INTO urls (name, created_at) VALUES(:name, :created_at)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':name', $url['name']);
        $stmt->bindValue(':created_at', $created_at);
        $stmt->execute();
        $id = $pdo->lastInsertId();
        $this->get('flash')->addMessage('success', 'Страница успешно добавлена');
    } else {
        $id = $urls['id'];
        $this->get('flash')->addMessage('success', 'Страница уже существует');
    }
    return $response->withRedirect($router->urlFor('showUrl', ['id' => $id]), 302);
})->setName('addUrl');

$app->get('/urls/{id}', function ($request, $response, $args) {
    $pdo = $this->get('connection');
    $url = $pdo->query("SELECT * FROM urls WHERE id={$args['id']}")->fetch(\PDO::FETCH_ASSOC);
    $urlCheacks = $pdo->query("SELECT * FROM url_checks
    WHERE url_id={$args['id']} ORDER BY url_id DESC")->fetchAll(\PDO::FETCH_ASSOC);
    $messages = $this->get('flash')->getMessages();
    $params = [
        'urls' => $url,
        'flash' => isset($messages) ? $messages : false,
        'url_checks' => $urlCheacks
    ];
    $messages = $this->get('flash')->getMessages();
    if (isset($messages)) {
        $params['flash'] = $messages;
    }
    return $this->get('renderer')->render($response, 'url.phtml', $params);
})->setName('showUrl');

$app->get('/urls', function ($request, $response) {
    $pdo = $this->get('connection');
    $allUrl = $pdo->query("
    SELECT DISTINCT ON (urls.id) urls.id, urls.name, url_checks.created_at, url_checks.status_code 
    FROM urls LEFT JOIN url_checks
    ON urls.id=url_checks.url_id
    ORDER BY urls.id, url_checks.created_at DESC")->fetchAll(\PDO::FETCH_ASSOC);
    $params = [
        'urls' => $allUrl
    ];
    return $this->get('renderer')->render($response, 'urls.phtml', $params);
})->setName('showUrls');

$app->post('/urls/{url_id}/checks', function ($request, $response, $args) use ($router) {
    $pdo = $this->get('connection');
    $url = $pdo->query("SELECT * FROM urls WHERE id={$args['url_id']}")->fetch(\PDO::FETCH_ASSOC);

    $client = new Client([
        'base_uri' => $url['name'],
        'timeout'  => 2.0,
    ]);

    try {
        $answer = $client->get('/');
        $this->get('flash')->addMessage('success', 'Страница успешно проверена');
    } catch (GuzzleHttp\Exception\ConnectException $e) {
        $this->get('flash')->addMessage('warning', 'Произошла ошибка при проверке, не удалось подключиться');
        return $response->withRedirect($router->urlFor('showUrl', ['id' => $args['url_id']]), 422);
    } catch (GuzzleHttp\Exception\RequestException $e) {
        $answer = $e->getResponse();
        $this->get('flash')->addMessage('warning', 'Проверка была выполнена успешно, но сервер ответил с ошибкой');
    }

    $statusCode = optional($answer)->getStatusCode();
    $html = optional($answer)->getBody()->getContents();
    $document = new Document($html, false);
    $h1 = optional($document->first('h1'))->text();
    $title = optional($document->first('title'))->text();
    $description = optional($document->first('meta[name=description]'))->getAttribute('content');
    $nowData = new DateTime('now');
    $created_at = $nowData->format('Y-m-d H:i:s');
    $sql = "INSERT INTO url_checks (url_id, status_code, h1, title, description, created_at)
    VALUES(:url_id, :status_code, :h1, :title, :description, :created_at)";
    $stmt = $pdo->prepare($sql);
    $urlParam = [
        ':url_id' => $args['url_id'],
        ':status_code' => $statusCode,
        ':h1' => strlen($h1) <= 255 ? $h1 : mb_strimwidth($h1, 0, 255, "..."),
        ':title' => strlen($title) <= 255 ? $title
        : mb_strimwidth($title, 0, 255, "..."),
        ':description' => strlen($description) <= 600 ? $description
        : mb_strimwidth($description, 0, 600, "..."),
        ':created_at' => $created_at,
    ];
    var_dump($urlParam);
    $stmt->execute($urlParam);
    return $response->withRedirect($router->urlFor('showUrl', ['id' => $args['url_id']]), 302);
})->setName('addChecks');

$app->run();
