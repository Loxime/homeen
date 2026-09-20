<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class ImageStorage
{
    public const MAX_SIZE_BYTES = 8 * 1024 * 1024;

    /**
     * @var array<string, string>
     */
    private const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    public function __construct(
        #[Autowire(
            '%kernel.project_dir%/var/uploads/images'
        )]
        private string $directory,
    ) {
    }

    /**
     * @return array{
     *     storedName:string,
     *     originalName:string,
     *     mimeType:string,
     *     sizeBytes:int,
     *     width:int|null,
     *     height:int|null
     * }
     */
    public function store(
        UploadedFile $file,
    ): array {
        if (!$file->isValid()) {
            throw new \InvalidArgumentException(
                'Invalid uploaded image.'
            );
        }

        $size =
            $file->getSize();

        if (
            $size === false
            || $size <= 0
            || $size > self::MAX_SIZE_BYTES
        ) {
            throw new \InvalidArgumentException(
                'Image must not exceed 8 MB.'
            );
        }

        $mime =
            $file->getMimeType();

        if (
            $mime === null
            || !isset(
                self::EXTENSIONS[$mime]
            )
        ) {
            throw new \InvalidArgumentException(
                'Only JPEG, PNG, WebP and GIF images are allowed.'
            );
        }

        $temporaryPath =
            $file->getPathname();

        $dimensions =
            @getimagesize(
                $temporaryPath
            );

        if ($dimensions === false) {
            throw new \InvalidArgumentException(
                'Uploaded file is not a valid image.'
            );
        }

        $width =
            (int) $dimensions[0];

        $height =
            (int) $dimensions[1];

        $this->ensureDirectory();

        $storedName =
            bin2hex(
                random_bytes(20)
            )
            .'.'
            .self::EXTENSIONS[$mime];

        $originalName =
            mb_substr(
                basename(
                    $file->getClientOriginalName()
                    ?: 'image'
                ),
                0,
                255,
            );

        $file->move(
            $this->directory,
            $storedName,
        );

        return [
            'storedName' =>
                $storedName,

            'originalName' =>
                $originalName,

            'mimeType' =>
                $mime,

            'sizeBytes' =>
                (int) $size,

            'width' =>
                $width,

            'height' =>
                $height,
        ];
    }

    public function path(
        string $storedName,
    ): string {
        if (
            !preg_match(
                '/^[a-f0-9]{40}\.(jpg|png|webp|gif)$/',
                $storedName,
            )
        ) {
            throw new \InvalidArgumentException(
                'Invalid stored image name.'
            );
        }

        return $this->directory
            .DIRECTORY_SEPARATOR
            .$storedName;
    }

    public function delete(
        string $storedName,
    ): void {
        $path =
            $this->path(
                $storedName
            );

        if (
            is_file($path)
            && !unlink($path)
        ) {
            throw new \RuntimeException(
                'Unable to delete stored image.'
            );
        }
    }

    private function ensureDirectory(): void
    {
        if (is_dir($this->directory)) {
            return;
        }

        if (
            !mkdir(
                $this->directory,
                0770,
                true,
            )
            && !is_dir(
                $this->directory
            )
        ) {
            throw new \RuntimeException(
                'Unable to create image storage directory.'
            );
        }
    }
}
