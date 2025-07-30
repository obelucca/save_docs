<?php 

namespace App\Models;

class Document{
    public ?int $id;
    public string $title;
    public string $responsible;
    public string $description;
    public ?string $image_url;
    public ?string $aiAnalysisText;


    public function __construct(
        ?int $id,
        string $title,
        string $responsible,
        string $description,
        ?string $image_url = null,
        ?string $aiAnalysisText = null
    ){
        $this->id = $id;
        $this->title = $title;
        $this->responsible = $responsible;
        $this->description = $description;
        $this->image_url = $image_url;
        $this->aiAnalysisText = $aiAnalysisText;
    }
}