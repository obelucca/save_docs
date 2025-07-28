<?php
require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;


$app = AppFactory::create();
$app->addRoutingMiddleware();
$app->addErrorMiddleware(true, true, true);

// ROTAS API

$app->get('/', function (Request $request, Response $response, array $args) {
    $response->getBody()->write("Bem-vindo ao sistema de documentação e resolução de erros!");
    return $response;
});

$app->post('/documents', function (Request $request, Response $response, array $args) {
    $response->getBody()->write(json_encode(['message' => 'Endpoint POST /documents recebido (em construção).']));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
});


$app->get('/documents/{id}', function (Request $request, Response $response, array $args) {
    $documentId = $args['id'];
    $response->getBody()->write(json_encode(['message' => "Endpoint GET /documents/{$documentId} recebido (em construção)."]));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
});


$app->run();