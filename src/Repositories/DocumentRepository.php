<?php
// src/Repositories/DocumentRepository.php

namespace App\Repositories;

use PDO;
use App\Models\Document;
use Exception; // Para capturar exceções gerais se necessário

class DocumentRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function save(Document $document): int
    {
        
        $sql = "INSERT INTO documents (title, responsible, description, image_url, ai_analysis_text) VALUES (:title, :responsible, :description, :image_url, :ai_analysis_text)";
        $stmt = $this->pdo->prepare($sql);

        $stmt->bindValue(':title', $document->title);
        $stmt->bindValue(':responsible', $document->responsible);
        $stmt->bindValue(':description', $document->description);
        $stmt->bindValue(':image_url', $document->image_url);
        $stmt->bindValue(':ai_analysis_text', $document->aiAnalysisText); 
        
        $stmt->execute();

        return (int)$this->pdo->lastInsertId();
    }

    public function findById(int $id): ?Document
    {
        
        $sql = "SELECT id, title, responsible, description, image_url, ai_analysis_text FROM documents WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$data) {
            return null;
        }

        return new Document(
            $data['id'],
            $data['title'],
            $data['responsible'],
            $data['description'],
            $data['image_url'],
            $data['ai_analysis_text'] 
        );
    }

    public function deleteById(int $id): bool
    {
        $sql = "DELETE FROM documents WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        
        return $stmt->execute();
    }

    public function putByID(int $id, Document $document): bool
    {
        
        $sql = "UPDATE documents SET title = :title, responsible = :responsible, description = :description, image_url = :image_url WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':title', $document->title);
        $stmt->bindValue(':responsible', $document->responsible);
        $stmt->bindValue(':description', $document->description);
        $stmt->bindValue(':image_url', $document->image_url);

        return $stmt->execute();
    }
}