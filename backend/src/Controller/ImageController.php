<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ImageRepository;
use App\Service\ImageStorage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/images')]
final readonly class ImageController
{
    public function __construct(
        private ImageRepository $images,
        private ImageStorage $storage,
    ) {
    }

    #[Route(
        '',
        name: 'api_images_list',
        methods: ['GET'],
    )]
    public function list(): JsonResponse
    {
        return new JsonResponse([
            'images' =>
                $this->images->all(),
        ]);
    }

    #[Route(
        '',
        name: 'api_images_upload',
        methods: ['POST'],
    )]
    public function upload(
        Request $request,
    ): JsonResponse {
        $file =
            $request->files->get(
                'image'
            );

        if (!$file instanceof UploadedFile) {
            return new JsonResponse(
                [
                    'error' =>
                        'Image file is required.',

                    'code' =>
                        'IMAGE_REQUIRED',
                ],
                422,
            );
        }

        try {
            $stored =
                $this->storage
                    ->store(
                        $file
                    );

            try {
                $image =
                    $this->images
                        ->create(
                            $stored
                        );
            } catch (\Throwable $exception) {
                $this->storage
                    ->delete(
                        $stored['storedName']
                    );

                throw $exception;
            }

            return new JsonResponse(
                $image,
                201,
            );
        } catch (
            \InvalidArgumentException
            $exception
        ) {
            return new JsonResponse(
                [
                    'error' =>
                        $exception->getMessage(),

                    'code' =>
                        'INVALID_IMAGE',
                ],
                422,
            );
        }
    }

    #[Route(
        '/note/{noteId<\d+>}',
        name: 'api_images_note_list',
        methods: ['GET'],
    )]
    public function noteImages(
        int $noteId,
    ): JsonResponse {
        try {
            return new JsonResponse([
                'images' =>
                    $this->images
                        ->forNote(
                            $noteId
                        ),
            ]);
        } catch (
            \OutOfBoundsException
            $exception
        ) {
            return $this->notFound(
                $exception
            );
        }
    }

    #[Route(
        '/{id<\d+>}/content',
        name: 'api_images_content',
        methods: ['GET'],
    )]
    public function content(
        int $id,
    ): Response {
        try {
            $file =
                $this->images
                    ->file(
                        $id
                    );
        } catch (
            \OutOfBoundsException
            $exception
        ) {
            return $this->notFound(
                $exception
            );
        }

        $path =
            $this->storage
                ->path(
                    $file['storedName']
                );

        if (!is_file($path)) {
            return new JsonResponse(
                [
                    'error' =>
                        'Stored image not found.',

                    'code' =>
                        'IMAGE_FILE_NOT_FOUND',
                ],
                404,
            );
        }

        $response =
            new BinaryFileResponse(
                $path
            );

        $response->headers->set(
            'Content-Type',
            $file['mimeType'],
        );

        $response->headers->set(
            'Cache-Control',
            'private, max-age=3600',
        );

        $response->headers->set(
            'X-Content-Type-Options',
            'nosniff',
        );

        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE,
            $file['originalName'],
            $file['storedName'],
        );

        return $response;
    }

    #[Route(
        '/{id<\d+>}',
        name: 'api_images_delete',
        methods: ['DELETE'],
    )]
    public function delete(
        int $id,
    ): JsonResponse {
        try {
            $storedName =
                $this->images
                    ->delete(
                        $id
                    );

            $this->storage
                ->delete(
                    $storedName
                );

            return new JsonResponse(
                null,
                204,
            );
        } catch (
            \OutOfBoundsException
            $exception
        ) {
            return $this->notFound(
                $exception
            );
        }
    }

    #[Route(
        '/{id<\d+>}/notes/{noteId<\d+>}',
        name: 'api_images_attach_note',
        methods: ['POST'],
    )]
    public function attach(
        int $id,
        int $noteId,
    ): JsonResponse {
        try {
            $this->images
                ->attach(
                    $id,
                    $noteId,
                );

            return new JsonResponse(
                [
                    'attached' => true,
                ],
            );
        } catch (
            \OutOfBoundsException
            $exception
        ) {
            return $this->notFound(
                $exception
            );
        }
    }

    #[Route(
        '/{id<\d+>}/notes/{noteId<\d+>}',
        name: 'api_images_detach_note',
        methods: ['DELETE'],
    )]
    public function detach(
        int $id,
        int $noteId,
    ): JsonResponse {
        try {
            $this->images
                ->detach(
                    $id,
                    $noteId,
                );

            return new JsonResponse(
                null,
                204,
            );
        } catch (
            \OutOfBoundsException
            $exception
        ) {
            return $this->notFound(
                $exception
            );
        }
    }

    private function notFound(
        \OutOfBoundsException $exception,
    ): JsonResponse {
        return new JsonResponse(
            [
                'error' =>
                    $exception->getMessage(),

                'code' =>
                    'IMAGE_NOT_FOUND',
            ],
            404,
        );
    }
}
