<?php

namespace App\Filament\Resources\NewsPostResource\Pages;

use App\Filament\Resources\NewsPostResource;
use App\Models\NewsPost;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateNewsPost extends CreateRecord
{
    protected static string $resource = NewsPostResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // 1) Slug wajib kebentuk
        $title = (string) ($data['title'] ?? '');
        if (!isset($data['slug']) || !$data['slug']) {
            $data['slug'] = $this->generateUniqueSlug($title);
        }

        // 2) Publish time otomatis kalau status published dan kosong
        $status = (string) ($data['status'] ?? 'draft');
        if ($status === 'published') {
            if (empty($data['published_at'])) {
                $data['published_at'] = now();
            }
        } else {
            $data['published_at'] = null;
        }

        return $data;
    }

    private function generateUniqueSlug(string $title): string
    {
        $base = Str::slug($title);
        $slug = $base ?: 'post';
        $i = 2;

        while (NewsPost::query()->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i;
            $i++;
        }

        return $slug;
    }
}
