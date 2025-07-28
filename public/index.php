<?php

require __DIR__ . '/../vendor/autoload.php';


use Slim\Factory\AppFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Middleware\BodyParsingMiddleware;
use App\Models\Document; 
use App\Repositories\DocumentRepository; 

$dbConfig = require __DIR__ . '/../src/config/database.php'; 

$dsn = "{$dbConfig['driver']}:host={$dbConfig['host']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}";

try {
   
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password']);
   
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
   
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    die("Erro de Conexão com o Banco de Dados: " . $e->getMessage());
}


$documentRepository = new DocumentRepository($pdo);

$app = AppFactory::create();
$app->addRoutingMiddleware();
$app->addErrorMiddleware(true, true, true);
$app->add(new BodyParsingMiddleware());

// --- Rotas da API ---

$app->get('/', function (Request $request, Response $response, array $args) {
    $response->getBody()->write("Bem-vindo ao sistema de documentação e resolução de erros!");
    return $response;
});

$app->post('/documents', function (Request $request, Response $response, array $args) use ($documentRepository) {
    $data = $request->getParsedBody(); 

    if (empty($data['title']) || empty($data['responsible']) || empty($data['description'])) {
        $response->getBody()->write(json_encode(['error' => 'Campos "title", "responsible" e "description" são obrigatórios.']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400); // Bad Request
    }

    $document = new Document(
        null, 
        $data['title'],
        $data['responsible'],
        $data['description'],
        $data['image_url'] ?? null 
    );

    try {
        
        $newId = $documentRepository->save($document);

        $response->getBody()->write(json_encode([
            'message' => 'Documento criado com sucesso!',
            'id' => $newId
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201); // Created
    } catch (PDOException $e) {
        $response->getBody()->write(json_encode(['error' => 'Erro ao salvar documento: ' . $e->getMessage()]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500); // Internal Server Error
    }
});


$app->get('/documents/{id}', function (Request $request, Response $response, array $args) use ($documentRepository) {
    $documentId = (int)$args['id']; 

    try {
        
        $document = $documentRepository->findById($documentId);

        if ($document) {
            
            $response->getBody()->write(json_encode([
                'id' => $document->id,
                'title' => $document->title,
                'responsible' => $document->responsible,
                'description' => $document->description,
                'image_url' => $document->image_url,
                
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200); 
        } else {
            
            $response->getBody()->write(json_encode(['error' => 'Documento não encontrado.']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404); 
        }
        } catch (PDOException $e) {
            $response->getBody()->write(json_encode(['error' => 'Erro ao buscar documento: ' . $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500); 
        }
    });
    
    // --- PUT route for updating document ---
    $app->put('/documents/{id}', function (Request $request, Response $response, array $args) use ($documentRepository) {
        $documentId = (int)$args['id']; 
        $data = $request->getParsedBody(); 

    if (empty($data['title']) || empty($data['responsible']) || empty($data['description'])) {
        $response->getBody()->write(json_encode(['error' => 'Campos "title", "responsible" e "description" são obrigatórios para atualização.']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400); // Bad Request
    }

    $existingDocument = $documentRepository->findById($documentId);
    if (!$existingDocument) {
        $response->getBody()->write(json_encode(['error' => 'Documento não encontrado para atualização.']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
    }

    
    $documentToUpdate = new Document(
        $documentId, 
        $data['title'],
        $data['responsible'],
        $data['description'],
        $data['image_url'] ?? null
    );

    try {
        $success = $documentRepository->putByID($documentId, $documentToUpdate);

        if ($success) {
            $response->getBody()->write(json_encode(['message' => 'Documento atualizado com sucesso!']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200); // OK
        } else {
            
            $response->getBody()->write(json_encode(['error' => 'Falha ao atualizar documento. Verifique o ID.']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404); 
        }
    } catch (PDOException $e) {
        $response->getBody()->write(json_encode(['error' => 'Erro ao atualizar documento: ' . $e->getMessage()]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500); 
    }
});


$app->delete('/documents/{id}', function (Request $request, Response $response, array $args) use ($documentRepository) {
    $documentId = (int)$args['id'];

    try {
        $success = $documentRepository->deleteById($documentId);

        if ($success) {
            $response->getBody()->write(json_encode(['message' => 'Documento excluído com sucesso!']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200); 
        } else {
            
            $response->getBody()->write(json_encode(['error' => 'Documento não encontrado para exclusão.']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404); 
        }
    } catch (PDOException $e) {
        $response->getBody()->write(json_encode(['error' => 'Erro ao excluir documento: ' . $e->getMessage()]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
});

$app->run();

