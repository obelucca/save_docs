<?php

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\Document;
use App\Repositories\DocumentRepository;
use Slim\Middleware\BodyParsingMiddleware;
use Google\Cloud\Vision\V1\ImageAnnotatorClient;

(Dotenv\Dotenv::createImmutable(__DIR__ . '/..'))->load();

putenv('GOOGLE_APPLICATION_CREDENTIALS=' . __DIR__ . '/../' . $_ENV['GOOGLE_CLOUD_KEY_PATH']);
$finalKeyPath = __DIR__ . '/../' . $_ENV['GOOGLE_CLOUD_KEY_PATH'];

if (!file_exists($finalKeyPath)) {
    print("ERRO CRÍTICO: Arquivo de chave GCP NÃO ENCONTRADO em: " . $finalKeyPath);
} else {
    print("SUCESSO: Arquivo de chave GCP ENCONTRADO em: " . $finalKeyPath);
};

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

$app->get('/', function (Request $request, Response $response, array $args) {
    $response->getBody()->write("Bem-vindo ao sistema de documentação e resolução de erros!");
    return $response;
});

$app->post('/documents', function (Request $request, Response $response, array $args) use ($documentRepository) {
    $data = $request->getParsedBody();
    $uploadedFiles = $request->getUploadedFiles();

    if (empty($data['title']) || empty($data['responsible']) || empty($data['description'])) {
        $response->getBody()->write(json_encode(['error' => 'Campos "title", "responsible" e "description" são obrigatórios.']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    $imageUrl = null;
    $aiAnalysisText = null;

    if (isset($uploadedFiles['image']) && $uploadedFiles['image']->getError() === UPLOAD_ERR_OK) {
        $image = $uploadedFiles['image'];

        $uploadDirectory = __DIR__ . '/uploads/images/';
        
        if (!is_dir($uploadDirectory)) {
            mkdir($uploadDirectory, 0777, true);
        }

        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $fileMimeType = $image->getClientMediaType();

        if (!in_array($fileMimeType, $allowedMimeTypes)) {
            $response->getBody()->write(json_encode(['error' => 'Tipo de arquivo não permitido. Apenas JPEG, PNG e GIF são aceitos.']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $extension = pathinfo($image->getClientFilename(), PATHINFO_EXTENSION);
        $basename = bin2hex(random_bytes(8));
        $filename = sprintf('%s.%0.8s', $basename, $extension);

        $imagePath = $uploadDirectory . $filename;
        $image->moveTo($imagePath);

        $imageUrl = '/uploads/images/' . $filename;

        try {
            $imageAnnotator = new ImageAnnotatorClient();
            $imageContent = file_get_contents($imagePath);
            $responseVision = $imageAnnotator->textDetection($imageContent);
            $annotations = $responseVision->getTextAnnotations();

            $extractedText = '';
           if ($annotations && count($annotations) > 0) { 
            $extractedText = $annotations[0]->getDescription();
            } else {
            $extractedText = 'Nenhum texto detectado na imagem.';
            }

$imageAnnotator->close();
            $aiAnalysisText = $extractedText;
            $imageAnnotator->close();
        } catch (Exception $e) {
            error_log('Erro ao chamar Google Cloud Vision AI: ' . $e->getMessage());
            $aiAnalysisText = 'Erro ao processar imagem pela IA.';
        }
    }

    $document = new Document(
        null,
        $data['title'],
        $data['responsible'],
        $data['description'],
        $imageUrl
    );
    $document->aiAnalysisText = $aiAnalysisText;

    try {
        $newId = $documentRepository->save($document);

        $response->getBody()->write(json_encode([
            'message' => 'Documento criado com sucesso!',
            'id' => $newId,
            'image_url' => $imageUrl,
            'ai_analysis_text' => $aiAnalysisText
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    } catch (PDOException $e) {
        $response->getBody()->write(json_encode(['error' => 'Erro ao salvar documento: ' . $e->getMessage()]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
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
                'ai_analysis_text' => $document->aiAnalysisText
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

$app->put('/documents/{id}', function (Request $request, Response $response, array $args) use ($documentRepository) {
    $documentId = (int)$args['id'];
    $data = $request->getParsedBody();

    if (empty($data['title']) || empty($data['responsible']) || empty($data['description'])) {
        $response->getBody()->write(json_encode(['error' => 'Campos "title", "responsible" e "description" são obrigatórios para atualização.']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
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
        // aiAnalysisText não é atualizado via PUT, pois é gerado pela IA
    );

    try {
        $success = $documentRepository->putByID($documentId, $documentToUpdate);

        if ($success) {
            $response->getBody()->write(json_encode(['message' => 'Documento atualizado com sucesso!']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
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