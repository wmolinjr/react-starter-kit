# 08 - File Storage (Spatie MediaLibrary)

## Configuração Tenant-Isolated Storage

### 1. Disk Tenant-Specific

```php
// config/filesystems.php

'disks' => [
    'tenant_uploads' => [
        'driver' => 'local',
        'root' => storage_path('app/tenants/' . (tenancy()->initialized ? tenant('id') : 'central')),
        'url' => env('APP_URL') . '/storage/tenants/' . (tenancy()->initialized ? tenant('id') : 'central'),
        'visibility' => 'private',
    ],

    // Para S3
    'tenant_s3' => [
        'driver' => 's3',
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION'),
        'bucket' => env('AWS_BUCKET'),
        'root' => 'tenants/' . (tenancy()->initialized ? tenant('id') : 'central'),
        'visibility' => 'private',
    ],
],
```

### 2. Model com Media

```php
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Project extends Model implements HasMedia
{
    use InteractsWithMedia;

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('attachments')
            ->useDisk('tenant_uploads');

        $this->addMediaCollection('images')
            ->useDisk('tenant_uploads')
            ->registerMediaConversions(function (Media $media) {
                $this->addMediaConversion('thumb')
                    ->width(300)
                    ->height(300);
            });
    }
}
```

### 3. Upload Controller

```php
public function uploadFile(Request $request, Project $project)
{
    $request->validate([
        'file' => 'required|file|max:10240', // 10MB
    ]);

    $project->addMediaFromRequest('file')
        ->toMediaCollection('attachments');

    return back()->with('success', 'Arquivo enviado!');
}

public function downloadFile(Media $media)
{
    // Verificar se media pertence ao tenant atual
    if ($media->model->tenant_id !== current_tenant_id()) {
        abort(404);
    }

    return response()->download($media->getPath(), $media->file_name);
}
```

---

**Versão:** 1.0
