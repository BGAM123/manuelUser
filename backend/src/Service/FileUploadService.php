<?php

namespace App\Service;

use App\Entity\PieceJointe;
use App\Repository\PieceJointeRepository;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

final class FileUploadService
{
    public const KIND_PHOTO = 'photo';
    public const KIND_DOCUMENT = 'document';
    public const KIND_GENERIC = 'generic';

    private const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/plain',
        'text/csv',
    ];

    private const MAX_SIZE_BYTES = 10 * 1024 * 1024; // 10 Mo

    public function __construct(
        private readonly PieceJointeRepository $pieceJointeRepository,
        private readonly SluggerInterface $slugger,
        private readonly string $projectDir,
    ) {
    }

    public function upload(
        UploadedFile $file,
        string $kind = self::KIND_GENERIC,
        ?string $nom = null,
        bool $persist = true,
    ): PieceJointe {
        if (!$file->isValid()) {
            throw new \InvalidArgumentException('Le fichier uploadé est invalide.');
        }

        if ($file->getSize() > self::MAX_SIZE_BYTES) {
            throw new \InvalidArgumentException('Le fichier dépasse la taille maximale autorisée (10 Mo).');
        }

        $mime = $file->getMimeType() ?? '';
        if (!in_array($mime, self::ALLOWED_MIME_TYPES, true)) {
            throw new \InvalidArgumentException('Type de fichier non autorisé.');
        }

        $originalName = $file->getClientOriginalName();
        $safeBase = $this->slugger->slug(pathinfo($originalName, PATHINFO_FILENAME))->lower()->toString();
        $extension = $file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin';
        $uniqueName = sprintf('%s_%s.%s', $safeBase !== '' ? $safeBase : 'file', bin2hex(random_bytes(8)), $extension);

        [$relativeDir, $publicPath] = $this->resolvePaths($kind);
        $uploadDir = $this->projectDir . '/public' . $relativeDir;
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
            throw new \RuntimeException('Impossible de créer le répertoire d\'upload.');
        }

        try {
            $file->move($uploadDir, $uniqueName);
        } catch (FileException $e) {
            throw new \RuntimeException('Échec de l\'enregistrement du fichier : ' . $e->getMessage(), 0, $e);
        }

        $pieceJointe = new PieceJointe();
        $pieceJointe->setNom(null !== $nom && '' !== $nom ? $nom : $originalName);
        $pieceJointe->setChemin($publicPath . '/' . $uniqueName);

        if ($persist) {
            $this->pieceJointeRepository->save($pieceJointe);
        }

        return $pieceJointe;
    }

    public function deleteFile(?string $publicPath): void
    {
        if (null === $publicPath || '' === $publicPath) {
            return;
        }

        $absolutePath = $this->projectDir . '/public' . $publicPath;
        if (is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    }

    /**
     * @return array{0: string, 1: string} [relativeDirFromPublic, publicUrlPrefix]
     */
    private function resolvePaths(string $kind): array
    {
        return match ($kind) {
            self::KIND_PHOTO => ['/uploads/assets/photos', '/uploads/assets/photos'],
            self::KIND_DOCUMENT => ['/uploads/assets/documents', '/uploads/assets/documents'],
            default => ['/uploads', '/uploads'],
        };
    }
}
