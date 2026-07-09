<?php

namespace App\Filament\Resources\Keywords\Pages;

use App\Filament\Resources\Keywords\KeywordResource;
use App\Filament\Resources\Keywords\Schemas\KeywordForm;
use App\Models\Keyword;
use App\Services\KeywordService;
use Filament\Resources\Pages\CreateRecord;

class CreateKeyword extends CreateRecord
{
    protected static string $resource = KeywordResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['thresholds'] = KeywordForm::flattenThresholdGroups($data['thresholds'] ?? []);

        return $data;
    }

    protected function handleRecordCreation(array $data): Keyword
    {
        return app(KeywordService::class)->create($data);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
