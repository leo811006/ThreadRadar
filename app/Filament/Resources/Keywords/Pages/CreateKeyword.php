<?php

namespace App\Filament\Resources\Keywords\Pages;

use App\Filament\Resources\Keywords\KeywordResource;
use App\Models\Keyword;
use App\Services\KeywordService;
use Filament\Resources\Pages\CreateRecord;

class CreateKeyword extends CreateRecord
{
    protected static string $resource = KeywordResource::class;

    protected function handleRecordCreation(array $data): Keyword
    {
        return app(KeywordService::class)->create($data);
    }
}
