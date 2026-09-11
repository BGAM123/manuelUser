<?php

namespace App\Service\Core;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Process\Process;

/**
 * Utility service for file upload and conversion.
 */
class FileService
{
    /**
     * Uploads a file with its original name.
     * Adds a numeric suffix when a file with the same name already exists.
     */
    public function uploadFile(string $uploadDir, UploadedFile $file): ?string
    {
        if (!$file || !$file->isValid()) {
            return null;
        }

        $originalName = trim($file->getClientOriginalName());
        $originalName = basename($originalName);

        if ($originalName === '') {
            $extension = $file->guessExtension();
            $fileName = $extension ? ('file.' . $extension) : 'file';
        } else {
            $fileName = $originalName;
        }

        $fileName = $this->buildUniqueFilename($uploadDir, $fileName);
        $file->move($uploadDir, $fileName);

        return $fileName;
    }

    /**
     * Uploads "document" and converts image/word files to PDF before save.
     * - Images: jpg, jpeg, png, bmp, gif, webp, tif, tiff
     * - Word/Text docs: doc, docx, odt, rtf
     * - PDF stays PDF (saved directly)
     */
    public function uploadDocumentAsPdfWhenPossible(string $uploadDir, UploadedFile $file): ?string
    {
        if (!$file || !$file->isValid()) {
            return null;
        }

        $extension = strtolower((string) pathinfo((string) $file->getClientOriginalName(), PATHINFO_EXTENSION));
        $convertibleExtensions = [
            'jpg', 'jpeg', 'png', 'bmp', 'gif', 'webp', 'tif', 'tiff',
            'doc', 'docx', 'odt', 'rtf',
        ];

        // Keep current behavior for non-convertible formats.
        if (!in_array($extension, $convertibleExtensions, true)) {
            return $this->uploadFile($uploadDir, $file);
        }

        $sourceOriginalName = basename((string) $file->getClientOriginalName());
        $sourceOriginalName = $sourceOriginalName !== '' ? $sourceOriginalName : ('document.' . ($extension !== '' ? $extension : 'bin'));
        $sourceInfo = pathinfo($sourceOriginalName);
        $sourceBase = $this->sanitizeFilename($sourceInfo['filename'] ?? 'document');
        $sourceExt = strtolower($sourceInfo['extension'] ?? $extension);
        $sourceName = $sourceBase . ($sourceExt !== '' ? '.' . $sourceExt : '');

        $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'minepia_pdf_' . bin2hex(random_bytes(8));
        if (!@mkdir($tempDir, 0777, true) && !is_dir($tempDir)) {
            throw new \RuntimeException('Impossible de creer le repertoire temporaire pour la conversion PDF.');
        }

        try {
            $file->move($tempDir, $sourceName);
            $sourcePath = $tempDir . DIRECTORY_SEPARATOR . $sourceName;

            $soffice = $this->resolveLibreOfficeBinary();
            if ($soffice === null) {
                throw new \RuntimeException('Conversion PDF indisponible: LibreOffice (soffice) est introuvable sur le serveur.');
            }

            $process = new Process([
                $soffice,
                '--headless',
                '--convert-to',
                'pdf',
                '--outdir',
                $tempDir,
                $sourcePath,
            ]);
            $process->setTimeout(120);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new \RuntimeException('Echec de conversion en PDF: ' . trim($process->getErrorOutput() ?: $process->getOutput()));
            }

            $convertedPdfPath = $tempDir . DIRECTORY_SEPARATOR . $sourceBase . '.pdf';
            if (!file_exists($convertedPdfPath)) {
                $candidates = glob($tempDir . DIRECTORY_SEPARATOR . '*.pdf') ?: [];
                if (empty($candidates)) {
                    throw new \RuntimeException('Conversion terminee mais aucun PDF genere.');
                }
                $convertedPdfPath = $candidates[0];
            }

            $targetPdfName = $this->buildUniqueFilename($uploadDir, $sourceBase . '.pdf');
            $targetPdfPath = rtrim($uploadDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $targetPdfName;

            if (!@rename($convertedPdfPath, $targetPdfPath)) {
                if (!@copy($convertedPdfPath, $targetPdfPath)) {
                    throw new \RuntimeException('Impossible de deplacer le PDF converti vers le repertoire de destination.');
                }
            }

            return $targetPdfName;
        } finally {
            $this->cleanupDirectory($tempDir);
        }
    }

    public function removeFile(string $filePath): bool
    {
        if (file_exists($filePath)) {
            return unlink($filePath);
        }

        return false;
    }

    private function buildUniqueFilename(string $uploadDir, string $fileName): string
    {
        $fileName = basename(trim($fileName));
        if ($fileName === '') {
            $fileName = 'file';
        }

        $targetPath = rtrim($uploadDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $fileName;
        if (!file_exists($targetPath)) {
            return $fileName;
        }

        $fileInfo = pathinfo($fileName);
        $baseName = $fileInfo['filename'] ?? 'file';
        $extension = isset($fileInfo['extension']) && $fileInfo['extension'] !== ''
            ? '.' . $fileInfo['extension']
            : '';

        $index = 1;
        do {
            $candidate = $baseName . '_' . $index . $extension;
            $targetPath = rtrim($uploadDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $candidate;
            $index++;
        } while (file_exists($targetPath));

        return $candidate;
    }

    private function sanitizeFilename(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return 'document';
        }

        $sanitized = preg_replace('/[^A-Za-z0-9 _.-]/', '_', $name);
        $sanitized = trim((string) $sanitized);

        return $sanitized !== '' ? $sanitized : 'document';
    }

    private function resolveLibreOfficeBinary(): ?string
    {
        $candidates = [
            'soffice',
            'soffice.exe',
            'C:\\Program Files\\LibreOffice\\program\\soffice.exe',
            'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.exe',
            '/usr/bin/soffice',
            '/usr/local/bin/soffice',
            '/snap/bin/libreoffice',
        ];

        foreach ($candidates as $candidate) {
            if (str_contains($candidate, DIRECTORY_SEPARATOR) && !file_exists($candidate)) {
                continue;
            }

            $check = new Process([$candidate, '--version']);
            $check->setTimeout(10);
            $check->run();
            if ($check->isSuccessful()) {
                return $candidate;
            }
        }

        return null;
    }

    private function cleanupDirectory(string $dir): void
    {
        if ($dir === '' || !is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        if (is_array($items)) {
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }
                $path = $dir . DIRECTORY_SEPARATOR . $item;
                if (is_dir($path)) {
                    $this->cleanupDirectory($path);
                } else {
                    @unlink($path);
                }
            }
        }

        @rmdir($dir);
    }
}

