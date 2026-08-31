<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;

/**
 * Normalise les fichiers et listes multipart (Swagger / Postman / frontend / Axios).
 *
 * Important : ne pas utiliser InputBag::get() pour les tableaux
 * (Symfony 6.3+ lève BadRequestException sur les valeurs non scalaires).
 */
final class UploadedFilesNormalizer
{
    /**
     * @return list<UploadedFile>
     */
    public static function fromRequest(Request $request, string $field): array
    {
        $base = rtrim($field, '[]');
        $allFiles = $request->files->all();
        $candidates = [];

        foreach ($allFiles as $key => $value) {
            if (rtrim((string) $key, '[]') === $base) {
                $candidates[] = $value;
            }
        }

        $files = [];
        foreach ($candidates as $candidate) {
            self::collectUploadedFiles($candidate, $files);
        }

        // PHP ne remplit pas $_FILES pour un PUT/PATCH multipart. Reconstituer les
        // fichiers dans ce cas permet à l'endpoint de modification de conserver la
        // même API que la création (multipart/form-data).
        if ([] === $files) {
            $files = self::filesFromRawMultipart($request, $base);
        }

        $unique = [];
        $seen = [];
        foreach ($files as $file) {
            $fingerprint = spl_object_id($file) . '|' . ($file->getPathname() ?: '') . '|' . $file->getClientOriginalName();
            if (isset($seen[$fingerprint])) {
                continue;
            }
            $seen[$fingerprint] = true;
            $unique[] = $file;
        }

        return $unique;
    }

    /**
     * @return list<UploadedFile>
     */
    private static function filesFromRawMultipart(Request $request, string $field): array
    {
        $contentType = (string) $request->headers->get('Content-Type', '');
        if (!preg_match('/boundary=(?:"([^"]+)"|([^;\s]+))/', $contentType, $matches)) {
            return [];
        }

        $boundary = $matches[1] ?: $matches[2];
        $files = [];
        foreach (preg_split('/--' . preg_quote($boundary, '/') . '(?:--)?(?:\r\n|$)/', $request->getContent()) ?: [] as $part) {
            [$headers, $content] = array_pad(explode("\r\n\r\n", $part, 2), 2, null);
            if (
                null === $content
                || !preg_match('/name="([^"]+)"/', $headers, $nameMatch)
                || rtrim($nameMatch[1], '[]') !== $field
                || !preg_match('/filename="([^"]*)"/', $headers, $fileNameMatch)
                || '' === $fileNameMatch[1]
            ) {
                continue;
            }

            $path = tempnam(sys_get_temp_dir(), 'asset-maintenance-');
            if (false === $path) {
                throw new \RuntimeException('Impossible de préparer le fichier envoyé.');
            }
            file_put_contents($path, rtrim($content, "\r\n"));
            preg_match('/Content-Type:\s*([^\r\n]+)/i', $headers, $mimeMatch);
            $files[] = new UploadedFile(
                $path,
                $fileNameMatch[1],
                $mimeMatch[1] ?? null,
                UPLOAD_ERR_OK,
                true
            );
        }

        return $files;
    }

    /**
     * @param list<UploadedFile> $files
     */
    private static function collectUploadedFiles(mixed $raw, array &$files): void
    {
        if (null === $raw) {
            return;
        }

        if ($raw instanceof UploadedFile) {
            if (UPLOAD_ERR_NO_FILE === $raw->getError()) {
                return;
            }
            $files[] = $raw;

            return;
        }

        if (!is_array($raw)) {
            return;
        }

        foreach ($raw as $item) {
            self::collectUploadedFiles($item, $files);
        }
    }

    /**
     * @return list<string>
     */
    public static function stringListFromRequest(Request $request, string $field): array
    {
        return array_values(array_filter(
            self::nullableStringListFromRequest($request, $field),
            static fn (?string $v): bool => null !== $v && '' !== $v
        ));
    }

    /**
     * Parse piecesJointesNoms pour tous les clients :
     *
     * Cas 1 : piecesJointesNoms[]=A & piecesJointesNoms[]=B  → ["A","B"]
     * Cas 2 : piecesJointesNoms[]="A,B" (Swagger 1 chaîne)   → ["A","B"]
     * Cas 3 : piecesJointesNoms="A,B"                         → ["A","B"]
     *
     * - trim des espaces
     * - suppression des guillemets entourant une valeur
     * - valeurs vides ignorées
     * - ordre conservé
     *
     * @return list<string> liste indexée 0..n (pas de null ; absence de nom = pas d'entrée → fallback fichier)
     */
    public static function nullableStringListFromRequest(Request $request, string $field): array
    {
        $base = rtrim($field, '[]');
        $all = $request->request->all();
        $raw = null;

        foreach ($all as $key => $value) {
            if (rtrim((string) $key, '[]') === $base) {
                $raw = $value;
                break;
            }
        }

        if (null === $raw) {
            return [];
        }

        return self::parseLabelList($raw);
    }

    /**
     * @param mixed $raw chaîne, tableau, ou valeurs imbriquées
     *
     * @return list<string>
     */
    public static function parseLabelList(mixed $raw): array
    {
        $tokens = [];
        self::collectLabelTokens($raw, $tokens);

        $labels = [];
        foreach ($tokens as $token) {
            $normalized = self::normalizeLabelToken($token);
            if (null === $normalized) {
                continue;
            }
            // Une seule entrée contenant des virgules (Swagger) ou plusieurs noms collés
            if (str_contains($normalized, ',')) {
                foreach (explode(',', $normalized) as $part) {
                    $partNormalized = self::normalizeLabelToken($part);
                    if (null !== $partNormalized) {
                        $labels[] = $partNormalized;
                    }
                }
            } else {
                $labels[] = $normalized;
            }
        }

        return $labels;
    }

    /**
     * @param list<string> $tokens
     */
    private static function collectLabelTokens(mixed $raw, array &$tokens): void
    {
        if (null === $raw) {
            return;
        }

        if (is_array($raw)) {
            foreach ($raw as $item) {
                self::collectLabelTokens($item, $tokens);
            }

            return;
        }

        $tokens[] = (string) $raw;
    }

    private static function normalizeLabelToken(mixed $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $string = trim((string) $value);
        // Retirer guillemets entourant la valeur : "Facture d'achat" ou 'Bon'
        if (
            (str_starts_with($string, '"') && str_ends_with($string, '"'))
            || (str_starts_with($string, "'") && str_ends_with($string, "'"))
        ) {
            $string = trim(substr($string, 1, -1));
        }

        $string = trim($string);
        if ('' === $string) {
            return null;
        }

        // Placeholders JS/Swagger
        if (in_array(strtolower($string), ['null', 'undefined', 'none'], true)) {
            return null;
        }

        return $string;
    }
}
