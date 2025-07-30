<?php

require __DIR__ . '/vendor/autoload.php';

use Google\Cloud\Vision\V1\ImageAnnotatorClient;

// Carrega o dotenv
(Dotenv\Dotenv::createImmutable(__DIR__))->load();

// Define a variável de ambiente para a chave do Google Cloud
// Certifique-se de que o caminho no .env esteja correto para este contexto
// Ex: GOOGLE_CLOUD_KEY_PATH=src/config/chave_vision.json
putenv('GOOGLE_APPLICATION_CREDENTIALS=' . __DIR__ . '/' . $_ENV['GOOGLE_CLOUD_KEY_PATH']);

echo "Tentando inicializar ImageAnnotatorClient...\n";
try {
    $imageAnnotator = new ImageAnnotatorClient();
    echo "ImageAnnotatorClient inicializado com sucesso!\n";

    // Substitua por um caminho de imagem REAL e VÁLIDO no seu sistema
    // Use uma imagem que você sabe que foi carregada com sucesso antes.
    $imagePath = __DIR__ . '/public/uploads/images/7d70efcfd87a8141.png'; // <-- ATUALIZE ISSO

    if (!file_exists($imagePath)) {
        echo "ERRO: Imagem de teste não encontrada em: " . $imagePath . "\n";
        exit;
    }

    echo "Processando imagem: " . $imagePath . "...\n";
    $imageContent = file_get_contents($imagePath);

    $responseVision = $imageAnnotator->textDetection($imageContent);
    $annotations = $responseVision->getTextAnnotations();

    $extractedText = '';
    if ($annotations) {
        $extractedText = $annotations[0]->getDescription();
        echo "Texto extraído com sucesso:\n";
        echo "---------------------------------\n";
        echo $extractedText . "\n";
        echo "---------------------------------\n";
    } else {
        echo "Nenhum texto detectado na imagem.\n";
    }

    $imageAnnotator->close();
    echo "Teste concluído.\n";

} catch (Exception $e) {
    echo "Ocorreu um ERRO ao se comunicar com a Google Cloud Vision AI:\n";
    echo "Mensagem: " . $e->getMessage() . "\n";
    echo "Código: " . $e->getCode() . "\n";
    // echo "Trace: " . $e->getTraceAsString() . "\n"; // Descomente para mais detalhes se precisar
}