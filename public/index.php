<?php

// Подключение автозагрузки через composer
require __DIR__ . '/../vendor/autoload.php';

use Slim\Middleware\MethodOverrideMiddleware;
use Slim\Factory\AppFactory;
use DI\Container;
use Hexlet\Code\Connection;

try {
    Connection::get()->connect();
    $DATABASE_URL = getenv('DATABASE_URL');
} catch (\PDOException $e) {
    echo $e->getMessage();
}
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

$app->addErrorMiddleware(true, true, true);
$app->add(MethodOverrideMiddleware::class);

$app->get('/', function ($request, $response) {
    $pdo = Connection::get()->connect();
    try {
        if (!(tableExists($pdo, "urls"))) {
            $pdo->exec("CREATE TABLE urls (
                id          bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
                name        varchar(255),
                created_at  timestamp
            );");
        }
        if (!(tableExists($pdo, "url_checks"))) {
            $pdo->exec("CREATE TABLE url_checks (
                id            bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
                url_id       bigint REFERENCES urls (id),
                status_code varchar(255),
                h1            varchar(255),
                title         varchar(255),
                description   varchar(255),
                created_at    timestamp
            );");
        }
    } catch (\PDOException $e) {
        echo $e->getMessage();
    }
        return $this->get('renderer')->render($response, 'index.phtml');
    }
);
$app->post('/urls', function ($request, $response) {
    $pdo = Connection::get()->connect();
    $url = $request->getParsedBodyParam('url');
    var_dump('hi');
    $v = new Valitron\Validator(array('name' => $url['name']));
    $v->rule('required', 'name')->message('URL не должен быть пустым');
    $v->rule('lengthMax', 'name', 255)->message('Длинна ссылки не должна превышать 255 символов');
    $v->rule('url', 'name')->message('Некорректный URL');
     if (!$v->validate()) {
        $params = [
            'errors' => $v->errors(),
            'url' => $url['name']
        ];
        return $this->get('renderer')->render($response, 'index.phtml', $params);
    }

    $stmt = $pdo->prepare("SELECT * FROM urls WHERE name=:name");
    $stmt->execute(['name' => $url['name']]);
    $urls = $stmt->fetch(\PDO::FETCH_ASSOC);
    var_dump($urls);
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
    return $response->withRedirect("/urls/{$id}", 302);
    }
);
$app->get('/urls/{id}', function ($request, $response, $args) {
    $pdo = Connection::get()->connect();
    $Url = $pdo->query("SELECT * FROM urls WHERE id={$args['id']}")->fetch(\PDO::FETCH_ASSOC);
    var_dump($Url);
    $messages = $this->get('flash')->getMessages();
    $params = [
        'urls' => $Url,
        'flash' => isset($messages) ? $messages : false
    ];
    $messages = $this->get('flash')->getMessages();
    if (isset($messages)) {
        $params['flash'] = $messages;
    }
    return $this->get('renderer')->render($response, 'url.phtml', $params);
});

$app->get('/urls', function ($request, $response) {
    $pdo = Connection::get()->connect();
    $allUrl = $pdo->query("SELECT * FROM urls")->fetchAll(\PDO::FETCH_ASSOC);
    var_dump($allUrl);
    $params = ['urls' => $allUrl];
    return $this->get('renderer')->render($response, 'urls.phtml', $params);
});

$app->post('urls/{url_id}/checks', function ($request, $response) {

    return $this->get('renderer')->render($response, 'url.phtml', $params);
});
$app->run();

function tableExists(\PDO $pdo, string $table)
{
    try {
        $result = $pdo->query("SELECT 1 FROM {$table} LIMIT 1");
    } catch (\PDOException $e) {
        return false;
    }
    return $result !== false;
}