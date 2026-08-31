<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Extrait des géométries spatiales (Point, LineString, Polygon, MultiPolygon...) depuis un
 * fichier géospatial fourni par l'utilisateur, sans exiger de sa part une structure précise.
 *
 * Formats supportés : CSV, Excel (.xlsx/.xls), GeoJSON, KML, GPX, Shapefile (.shp ou .zip).
 *
 * Contrat de sortie unique et normalisé, quel que soit le format d'origine :
 *
 * @phpstan-type NormalizedShape array{
 *     type: string,
 *     latitude: float,
 *     longitude: float,
 *     geometry: array<string, mixed>|null
 * }
 *
 * - `latitude`/`longitude` : point représentatif de la géométrie (le point lui-même pour un
 *   Point, le centroïde — moyenne simple des sommets — pour toute géométrie plus complexe).
 *   Toujours renseigné : les consommateurs existants (qui n'affichent qu'un marqueur) n'ont
 *   rien à changer.
 * - `geometry` : géométrie GeoJSON complète (`{"type": ..., "coordinates": [...]}`), conservée
 *   pour toute forme non ponctuelle. `null` pour un Point simple (latitude/longitude suffisent).
 *
 * Une géométrie complexe (Polygon à 8 sommets, trace GPS à 200 points...) produit **une seule**
 * entrée de la liste retournée — jamais une entrée par sommet constitutif.
 */
class GeoFileParserService
{
    private const MAX_FILE_SIZE_BYTES = 20 * 1024 * 1024; // 20 Mo
    private const MAX_SHAPES = 5000;
    private const MAX_GEOJSON_DEPTH = 12;
    private const MAX_COORDS_PER_GEOMETRY = 50000;

    private const LAT_ALIASES = ['latitude', 'lat'];
    private const LON_ALIASES = ['longitude', 'lon', 'lng'];

    /**
     * @return list<array{type: string, latitude: float, longitude: float, geometry: array<string, mixed>|null}>
     *
     * @throws \InvalidArgumentException Fichier/format/coordonnées invalides (→ 400 côté contrôleur)
     * @throws \RuntimeException         Échec de lecture/parsing du fichier (→ 400 côté contrôleur)
     */
    public function extractCoordinates(UploadedFile $file): array
    {
        if (!$file->isValid()) {
            throw new \InvalidArgumentException('Le fichier uploadé est invalide : ' . $file->getErrorMessage());
        }
        if ($file->getSize() > self::MAX_FILE_SIZE_BYTES) {
            throw new \InvalidArgumentException(sprintf(
                'Le fichier dépasse la taille maximale autorisée (%d Mo).',
                (int) (self::MAX_FILE_SIZE_BYTES / 1024 / 1024)
            ));
        }

        $extension = strtolower($file->getClientOriginalExtension());

        $shapes = match ($extension) {
            'csv' => $this->parseCsv($file),
            'xlsx', 'xls' => $this->parseExcel($file),
            'json', 'geojson' => $this->parseGeoJson($file),
            'kml' => $this->parseKml($file),
            'gpx' => $this->parseGpx($file),
            'shp', 'zip' => $this->parseShapefile($file),
            default => throw new \InvalidArgumentException(
                "Format de fichier non supporté : \"$extension\". Formats supportés : CSV, Excel (.xlsx/.xls), "
                . 'GeoJSON (.json/.geojson), KML, GPX, Shapefile (.shp seul, ou .zip contenant .shp/.shx/.dbf/.prj).'
            ),
        };

        if (count($shapes) > self::MAX_SHAPES) {
            throw new \InvalidArgumentException(sprintf(
                'Trop de géométries dans le fichier (%d trouvées, maximum %d autorisé).',
                count($shapes),
                self::MAX_SHAPES
            ));
        }

        foreach ($shapes as $index => $shape) {
            $lat = $shape['latitude'];
            $lon = $shape['longitude'];
            if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
                throw new \InvalidArgumentException(sprintf(
                    'Coordonnée invalide extraite du fichier (géométrie #%d) : latitude=%s (attendu entre -90 et 90), '
                    . 'longitude=%s (attendu entre -180 et 180).',
                    $index + 1,
                    $lat,
                    $lon
                ));
            }
        }

        return $shapes;
    }

    // ------------------------------------------------------------------
    // CSV
    // ------------------------------------------------------------------

    /**
     * Détecte automatiquement les colonnes latitude/longitude par leur nom (latitude/lat,
     * longitude/lon/lng, insensible à la casse), quel que soit leur ordre ou la présence de
     * colonnes supplémentaires. Détecte aussi le séparateur (`,` ou `;`). Si aucun en-tête
     * n'est reconnu, tente de lire la première ligne comme une donnée positionnelle
     * (colonne 0 = latitude, colonne 1 = longitude) plutôt que de la perdre silencieusement.
     */
    private function parseCsv(UploadedFile $file): array
    {
        $handle = fopen($file->getPathname(), 'r');
        if (false === $handle) {
            throw new \RuntimeException('Impossible d\'ouvrir le fichier CSV.');
        }

        $firstLine = fgets($handle);
        if (false === $firstLine || '' === trim($firstLine)) {
            fclose($handle);
            throw new \RuntimeException('Le fichier CSV est vide.');
        }
        rewind($handle);

        $delimiter = $this->detectCsvDelimiter($firstLine);

        $firstRow = fgetcsv($handle, 0, $delimiter);
        if (false === $firstRow) {
            fclose($handle);
            throw new \RuntimeException('Le fichier CSV est vide ou illisible.');
        }

        $shapes = [];
        $columns = $this->resolveLatLonColumns($firstRow);

        if (null !== $columns) {
            [$latIdx, $lonIdx] = $columns;
        } else {
            // Pas d'en-tête reconnu : peut-être qu'il n'y a pas d'en-tête du tout.
            $latIdx = 0;
            $lonIdx = 1;
            $lat = $this->parseFloat($firstRow[0] ?? null);
            $lon = $this->parseFloat($firstRow[1] ?? null);
            if (null !== $lat && null !== $lon) {
                $shapes[] = ['type' => 'Point', 'latitude' => $lat, 'longitude' => $lon, 'geometry' => null];
            }
        }

        while (false !== ($row = fgetcsv($handle, 0, $delimiter))) {
            if ($this->isBlankRow($row)) {
                continue;
            }
            $lat = $this->parseFloat($row[$latIdx] ?? null);
            $lon = $this->parseFloat($row[$lonIdx] ?? null);
            if (null !== $lat && null !== $lon) {
                $shapes[] = ['type' => 'Point', 'latitude' => $lat, 'longitude' => $lon, 'geometry' => null];
            }
        }

        fclose($handle);

        if ([] === $shapes) {
            throw new \RuntimeException(
                'Aucune coordonnée exploitable trouvée dans le CSV (colonnes latitude/longitude non identifiées, '
                . 'ou fichier sans donnée).'
            );
        }

        return $shapes;
    }

    private function detectCsvDelimiter(string $sampleLine): string
    {
        $commaCount = substr_count($sampleLine, ',');
        $semicolonCount = substr_count($sampleLine, ';');

        return $semicolonCount > $commaCount ? ';' : ',';
    }

    // ------------------------------------------------------------------
    // Excel
    // ------------------------------------------------------------------

    /**
     * Même logique de détection de colonnes par nom que le CSV. Contrairement au CSV, un
     * en-tête reconnu est requis (les classeurs Excel ont quasi toujours un en-tête) : sinon,
     * une erreur explicite est renvoyée plutôt que de deviner une position.
     */
    private function parseExcel(UploadedFile $file): array
    {
        if (!class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
            throw new \RuntimeException('PhpSpreadsheet n\'est pas installé. Exécutez : composer require phpoffice/phpspreadsheet');
        }

        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getPathname());
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray(null, true, true, false);

        if ([] === $rows) {
            throw new \RuntimeException('Le fichier Excel est vide.');
        }

        $header = array_map(static fn (mixed $v): string => is_scalar($v) ? (string) $v : '', $rows[0]);
        $columns = $this->resolveLatLonColumns($header);
        if (null === $columns) {
            throw new \RuntimeException('Impossible d\'identifier les colonnes latitude/longitude dans le fichier Excel.');
        }
        [$latIdx, $lonIdx] = $columns;

        $shapes = [];
        foreach (array_slice($rows, 1) as $row) {
            if ($this->isBlankRow($row)) {
                continue;
            }
            $lat = $this->parseFloat($row[$latIdx] ?? null);
            $lon = $this->parseFloat($row[$lonIdx] ?? null);
            if (null !== $lat && null !== $lon) {
                $shapes[] = ['type' => 'Point', 'latitude' => $lat, 'longitude' => $lon, 'geometry' => null];
            }
        }

        if ([] === $shapes) {
            throw new \RuntimeException('Aucune coordonnée exploitable trouvée dans les lignes du fichier Excel.');
        }

        return $shapes;
    }

    /**
     * @param list<mixed> $header
     *
     * @return array{0: int, 1: int}|null [indexLatitude, indexLongitude] ou null si non identifiable
     */
    private function resolveLatLonColumns(array $header): ?array
    {
        $latIdx = null;
        $lonIdx = null;
        foreach (array_values($header) as $i => $col) {
            $normalized = strtolower(trim((string) $col));
            if (null === $latIdx && in_array($normalized, self::LAT_ALIASES, true)) {
                $latIdx = $i;
            }
            if (null === $lonIdx && in_array($normalized, self::LON_ALIASES, true)) {
                $lonIdx = $i;
            }
        }

        return (null !== $latIdx && null !== $lonIdx) ? [$latIdx, $lonIdx] : null;
    }

    /**
     * @param list<mixed> $row
     */
    private function isBlankRow(array $row): bool
    {
        foreach ($row as $cell) {
            if (null !== $cell && '' !== trim((string) $cell)) {
                return false;
            }
        }

        return true;
    }

    // ------------------------------------------------------------------
    // GeoJSON
    // ------------------------------------------------------------------

    /**
     * Parcourt récursivement n'importe quelle structure GeoJSON valide : FeatureCollection,
     * Feature, GeometryCollection, ou une Geometry (Point/MultiPoint/LineString/MultiLineString/
     * Polygon/MultiPolygon) directement à la racine. L'utilisateur n'a jamais besoin d'adapter
     * son fichier à une structure précise.
     */
    private function parseGeoJson(UploadedFile $file): array
    {
        $content = file_get_contents($file->getPathname());
        if (false === $content || '' === trim($content)) {
            throw new \RuntimeException('Le fichier GeoJSON est vide.');
        }

        $geoJson = json_decode($content, true);
        if (JSON_ERROR_NONE !== json_last_error()) {
            throw new \RuntimeException('Erreur de parsing JSON : ' . json_last_error_msg());
        }
        if (!is_array($geoJson)) {
            throw new \RuntimeException('Le contenu GeoJSON n\'est pas un objet valide.');
        }

        $shapes = [];
        $this->walkGeoJsonNode($geoJson, $shapes, 0);

        if ([] === $shapes) {
            throw new \RuntimeException(
                'Aucune géométrie exploitable trouvée dans le GeoJSON (attendu : FeatureCollection, Feature, '
                . 'GeometryCollection ou une géométrie Point/LineString/Polygon/MultiPolygon...).'
            );
        }

        return $shapes;
    }

    /**
     * @param array<string, mixed>                                                                $node
     * @param list<array{type: string, latitude: float, longitude: float, geometry: array|null}> &$shapes
     */
    private function walkGeoJsonNode(array $node, array &$shapes, int $depth): void
    {
        if ($depth > self::MAX_GEOJSON_DEPTH) {
            throw new \RuntimeException('Structure GeoJSON trop profondément imbriquée.');
        }
        if (count($shapes) >= self::MAX_SHAPES) {
            return;
        }

        $type = $node['type'] ?? null;
        if (!is_string($type)) {
            return;
        }

        switch ($type) {
            case 'FeatureCollection':
                foreach ((array) ($node['features'] ?? []) as $feature) {
                    if (is_array($feature)) {
                        $this->walkGeoJsonNode($feature, $shapes, $depth + 1);
                    }
                    if (count($shapes) >= self::MAX_SHAPES) {
                        break;
                    }
                }
                break;

            case 'Feature':
                if (isset($node['geometry']) && is_array($node['geometry'])) {
                    $this->walkGeoJsonNode($node['geometry'], $shapes, $depth + 1);
                }
                break;

            case 'GeometryCollection':
                foreach ((array) ($node['geometries'] ?? []) as $geometry) {
                    if (is_array($geometry)) {
                        $this->walkGeoJsonNode($geometry, $shapes, $depth + 1);
                    }
                    if (count($shapes) >= self::MAX_SHAPES) {
                        break;
                    }
                }
                break;

            case 'Point':
                $shape = $this->pointToShape($node['coordinates'] ?? null);
                if (null !== $shape) {
                    $shapes[] = $shape;
                }
                break;

            case 'MultiPoint':
                // Des points indépendants : chacun devient sa propre localisation.
                foreach ((array) ($node['coordinates'] ?? []) as $coord) {
                    $shape = $this->pointToShape($coord);
                    if (null !== $shape) {
                        $shapes[] = $shape;
                    }
                    if (count($shapes) >= self::MAX_SHAPES) {
                        break;
                    }
                }
                break;

            case 'LineString':
            case 'Polygon':
            case 'MultiLineString':
            case 'MultiPolygon':
                $shape = $this->complexGeometryToShape($type, $node['coordinates'] ?? null);
                if (null !== $shape) {
                    $shapes[] = $shape;
                }
                break;

            default:
                // Type inconnu/non spatial : ignoré sans faire échouer tout le fichier.
                break;
        }
    }

    private function pointToShape(mixed $coord): ?array
    {
        if (!is_array($coord) || count($coord) < 2) {
            return null;
        }
        $lon = $this->parseFloat($coord[0] ?? null);
        $lat = $this->parseFloat($coord[1] ?? null);
        if (null === $lat || null === $lon) {
            return null;
        }

        return ['type' => 'Point', 'latitude' => $lat, 'longitude' => $lon, 'geometry' => null];
    }

    private function complexGeometryToShape(string $type, mixed $coordinates): ?array
    {
        if (!is_array($coordinates) || [] === $coordinates) {
            return null;
        }

        $points = [];
        $this->flattenCoordinatesInto($coordinates, $points, 0);
        if ([] === $points) {
            return null;
        }
        if (count($points) > self::MAX_COORDS_PER_GEOMETRY) {
            throw new \RuntimeException(sprintf(
                'Géométrie %s trop volumineuse (%d sommets, maximum %d).',
                $type,
                count($points),
                self::MAX_COORDS_PER_GEOMETRY
            ));
        }

        [$lat, $lon] = $this->computeCentroid($points);

        return [
            'type' => $type,
            'latitude' => $lat,
            'longitude' => $lon,
            'geometry' => ['type' => $type, 'coordinates' => $coordinates],
        ];
    }

    /**
     * Descend récursivement dans un tableau de coordonnées GeoJSON (peu importe la profondeur
     * de nesting — LineString, anneaux de Polygon, polygones d'un MultiPolygon...) jusqu'à
     * trouver des paires [lon, lat] et les accumule dans $points.
     *
     * @param list<array{0: float, 1: float}> &$points
     */
    private function flattenCoordinatesInto(array $node, array &$points, int $depth): void
    {
        if ($depth > 10 || count($points) > self::MAX_COORDS_PER_GEOMETRY) {
            return;
        }

        if ($this->looksLikeCoordinatePair($node)) {
            $lon = $this->parseFloat($node[0]);
            $lat = $this->parseFloat($node[1]);
            if (null !== $lat && null !== $lon) {
                $points[] = [$lon, $lat];
            }

            return;
        }

        foreach ($node as $child) {
            if (is_array($child)) {
                $this->flattenCoordinatesInto($child, $points, $depth + 1);
            }
        }
    }

    private function looksLikeCoordinatePair(array $node): bool
    {
        return count($node) >= 2 && count($node) <= 4
            && is_numeric($node[0] ?? null) && is_numeric($node[1] ?? null);
    }

    /**
     * @param list<array{0: float, 1: float}> $points [lon, lat]
     *
     * @return array{0: float, 1: float} [latitude, longitude]
     */
    private function computeCentroid(array $points): array
    {
        $sumLat = 0.0;
        $sumLon = 0.0;
        $n = count($points);
        foreach ($points as $point) {
            $sumLon += $point[0];
            $sumLat += $point[1];
        }

        return [$sumLat / $n, $sumLon / $n];
    }

    // ------------------------------------------------------------------
    // KML
    // ------------------------------------------------------------------

    /**
     * Recherche Point/LineString/Polygon dans chaque Placemark, y compris imbriqués dans un
     * MultiGeometry, en s'adaptant au namespace XML réellement déclaré par le document
     * (ou son absence).
     */
    private function parseKml(UploadedFile $file): array
    {
        $content = file_get_contents($file->getPathname());
        if (false === $content || '' === trim($content)) {
            throw new \RuntimeException('Le fichier KML est vide.');
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content);
        if (false === $xml) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            throw new \RuntimeException('Fichier KML invalide : ' . ($errors[0]->message ?? 'XML malformé.'));
        }

        $ns = $this->resolveXmlDefaultNamespace($xml);
        $prefix = $this->registerXmlNamespace($xml, $ns);

        $placemarks = $xml->xpath('//' . $prefix . 'Placemark') ?: [];

        $shapes = [];
        foreach ($placemarks as $placemark) {
            foreach ($this->extractKmlPlacemarkGeometries($placemark, $ns, $prefix) as $shape) {
                $shapes[] = $shape;
            }
        }

        if ([] === $shapes) {
            throw new \RuntimeException('Aucune géométrie (Point, LineString, Polygon) trouvée dans le KML.');
        }

        return $shapes;
    }

    /**
     * @return list<array{type: string, latitude: float, longitude: float, geometry: array|null}>
     */
    private function extractKmlPlacemarkGeometries(\SimpleXMLElement $placemark, ?string $ns, string $prefix): array
    {
        $this->registerXmlNamespace($placemark, $ns);
        $shapes = [];

        foreach ($placemark->xpath('.//' . $prefix . 'Point') ?: [] as $point) {
            $coords = $this->parseKmlCoordinatesText((string) ($point->coordinates ?? ''));
            if ([] !== $coords) {
                $shapes[] = ['type' => 'Point', 'latitude' => $coords[0][1], 'longitude' => $coords[0][0], 'geometry' => null];
            }
        }

        foreach ($placemark->xpath('.//' . $prefix . 'LineString') ?: [] as $line) {
            $coords = $this->parseKmlCoordinatesText((string) ($line->coordinates ?? ''));
            $shape = $this->coordsToShape('LineString', $coords);
            if (null !== $shape) {
                $shapes[] = $shape;
            }
        }

        foreach ($placemark->xpath('.//' . $prefix . 'Polygon') ?: [] as $polygon) {
            $ring = $this->parseKmlCoordinatesText((string) ($polygon->outerBoundaryIs->LinearRing->coordinates ?? ''));
            if ([] === $ring) {
                continue;
            }
            $holes = [];
            foreach ($polygon->innerBoundaryIs ?? [] as $inner) {
                $holeRing = $this->parseKmlCoordinatesText((string) ($inner->LinearRing->coordinates ?? ''));
                if ([] !== $holeRing) {
                    $holes[] = $holeRing;
                }
            }
            $rings = array_merge([$ring], $holes);
            $allPoints = array_merge(...$rings);
            [$lat, $lon] = $this->computeCentroid($allPoints);
            $shapes[] = [
                'type' => 'Polygon',
                'latitude' => $lat,
                'longitude' => $lon,
                'geometry' => ['type' => 'Polygon', 'coordinates' => $rings],
            ];
        }

        return $shapes;
    }

    /**
     * @return list<array{0: float, 1: float}> [lon, lat]
     */
    private function parseKmlCoordinatesText(string $raw): array
    {
        $raw = trim($raw);
        if ('' === $raw) {
            return [];
        }

        $tuples = preg_split('/\s+/', $raw) ?: [];
        $points = [];
        foreach ($tuples as $tuple) {
            $parts = explode(',', trim($tuple));
            if (count($parts) >= 2) {
                $lon = $this->parseFloat($parts[0]);
                $lat = $this->parseFloat($parts[1]);
                if (null !== $lat && null !== $lon) {
                    $points[] = [$lon, $lat];
                }
            }
        }

        return $points;
    }

    // ------------------------------------------------------------------
    // GPX
    // ------------------------------------------------------------------

    /**
     * Gère wpt (points indépendants), trk/trkseg/trkpt (une trace = une seule LineString,
     * tous ses trkseg concaténés dans l'ordre) et rte/rtept (un itinéraire = une LineString),
     * avec prise en compte du namespace GPX réellement déclaré.
     */
    private function parseGpx(UploadedFile $file): array
    {
        $content = file_get_contents($file->getPathname());
        if (false === $content || '' === trim($content)) {
            throw new \RuntimeException('Le fichier GPX est vide.');
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content);
        if (false === $xml) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            throw new \RuntimeException('Fichier GPX invalide : ' . ($errors[0]->message ?? 'XML malformé.'));
        }

        $ns = $this->resolveXmlDefaultNamespace($xml);
        $prefix = $this->registerXmlNamespace($xml, $ns);

        $shapes = [];

        foreach ($xml->xpath('//' . $prefix . 'wpt') ?: [] as $wpt) {
            $lat = $this->parseFloat((string) $wpt['lat']);
            $lon = $this->parseFloat((string) $wpt['lon']);
            if (null !== $lat && null !== $lon) {
                $shapes[] = ['type' => 'Point', 'latitude' => $lat, 'longitude' => $lon, 'geometry' => null];
            }
        }

        foreach ($xml->xpath('//' . $prefix . 'trk') ?: [] as $trk) {
            $this->registerXmlNamespace($trk, $ns);
            $points = [];
            foreach ($trk->xpath('.//' . $prefix . 'trkpt') ?: [] as $trkpt) {
                $lat = $this->parseFloat((string) $trkpt['lat']);
                $lon = $this->parseFloat((string) $trkpt['lon']);
                if (null !== $lat && null !== $lon) {
                    $points[] = [$lon, $lat];
                }
            }
            $shape = $this->coordsToShape('LineString', $points);
            if (null !== $shape) {
                $shapes[] = $shape;
            }
        }

        foreach ($xml->xpath('//' . $prefix . 'rte') ?: [] as $rte) {
            $this->registerXmlNamespace($rte, $ns);
            $points = [];
            foreach ($rte->xpath('.//' . $prefix . 'rtept') ?: [] as $rtept) {
                $lat = $this->parseFloat((string) $rtept['lat']);
                $lon = $this->parseFloat((string) $rtept['lon']);
                if (null !== $lat && null !== $lon) {
                    $points[] = [$lon, $lat];
                }
            }
            $shape = $this->coordsToShape('LineString', $points);
            if (null !== $shape) {
                $shapes[] = $shape;
            }
        }

        if ([] === $shapes) {
            throw new \RuntimeException('Aucun point GPX exploitable trouvé (wpt / trk-trkseg-trkpt / rte-rtept).');
        }

        return $shapes;
    }

    // ------------------------------------------------------------------
    // XML helpers (KML/GPX)
    // ------------------------------------------------------------------

    private function resolveXmlDefaultNamespace(\SimpleXMLElement $xml): ?string
    {
        $namespaces = $xml->getNamespaces(true);

        return $namespaces[''] ?? null;
    }

    /**
     * Enregistre le namespace par défaut sous le préfixe "ns" pour les requêtes XPath, et
     * retourne le préfixe à utiliser ("ns:" ou "" si le document n'a pas de namespace).
     */
    private function registerXmlNamespace(\SimpleXMLElement $node, ?string $ns): string
    {
        if (null === $ns) {
            return '';
        }
        $node->registerXPathNamespace('ns', $ns);

        return 'ns:';
    }

    /**
     * @param list<array{0: float, 1: float}> $points [lon, lat]
     */
    private function coordsToShape(string $type, array $points): ?array
    {
        if ([] === $points) {
            return null;
        }
        [$lat, $lon] = $this->computeCentroid($points);

        return [
            'type' => $type,
            'latitude' => $lat,
            'longitude' => $lon,
            'geometry' => ['type' => $type, 'coordinates' => $points],
        ];
    }

    // ------------------------------------------------------------------
    // Shapefile (parseur binaire natif — aucune dépendance Composer)
    // ------------------------------------------------------------------

    /**
     * Aucune bibliothèque Shapefile tierce fiable n'a pu être identifiée avec certitude :
     * le format .shp est en revanche un format binaire simple et stable (spécification ESRI
     * publique), lu ici directement. Accepte soit un .shp isolé (CRS supposé WGS84 si aucune
     * information n'est disponible), soit un .zip contenant .shp/.shx/.dbf/.prj (recommandé :
     * le .prj permet de vérifier que les coordonnées sont bien en WGS84/EPSG:4326).
     */
    private function parseShapefile(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $tmpDir = null;

        try {
            if ('zip' === $extension) {
                [$shpPath, $prjPath] = $this->extractShapefileZip($file->getPathname());
                $tmpDir = dirname($shpPath);
            } else {
                $shpPath = $file->getPathname();
                $prjPath = null;
            }

            $this->assertShapefileIsWgs84OrUnknown($prjPath);

            $shapes = $this->readShpFile($shpPath);

            if ([] === $shapes) {
                throw new \RuntimeException('Aucune géométrie exploitable dans le Shapefile.');
            }

            return $shapes;
        } finally {
            if (null !== $tmpDir && is_dir($tmpDir)) {
                $this->removeDirectoryRecursively($tmpDir);
            }
        }
    }

    /**
     * @return array{0: string, 1: ?string} [cheminDuShp, cheminDuPrjOuNull]
     */
    private function extractShapefileZip(string $zipPath): array
    {
        $zip = new \ZipArchive();
        if (true !== $zip->open($zipPath)) {
            throw new \RuntimeException('Impossible d\'ouvrir l\'archive ZIP du Shapefile.');
        }

        $tmpDir = sys_get_temp_dir() . '/geoimport_' . bin2hex(random_bytes(8));
        if (!mkdir($tmpDir, 0700, true) && !is_dir($tmpDir)) {
            $zip->close();
            throw new \RuntimeException('Impossible de créer un répertoire temporaire pour extraire le ZIP.');
        }

        $shpEntry = null;
        $prjEntry = null;
        for ($i = 0; $i < $zip->numFiles; ++$i) {
            $name = $zip->getNameIndex($i);
            if (false === $name || str_contains($name, '..')) {
                continue; // ignore les entrées suspectes (zip slip)
            }
            $lower = strtolower($name);
            if (str_ends_with($lower, '.shp') && null === $shpEntry) {
                $shpEntry = $name;
            }
            if (str_ends_with($lower, '.prj') && null === $prjEntry) {
                $prjEntry = $name;
            }
        }

        if (null === $shpEntry) {
            $zip->close();
            $this->removeDirectoryRecursively($tmpDir);
            throw new \RuntimeException('L\'archive ZIP ne contient aucun fichier .shp.');
        }

        $zip->extractTo($tmpDir, array_values(array_filter([$shpEntry, $prjEntry])));
        $zip->close();

        $shpPath = $tmpDir . '/' . $shpEntry;
        $prjPath = null !== $prjEntry ? $tmpDir . '/' . $prjEntry : null;

        if (!is_file($shpPath)) {
            $this->removeDirectoryRecursively($tmpDir);
            throw new \RuntimeException('Extraction du .shp depuis le ZIP échouée.');
        }

        return [$shpPath, $prjPath];
    }

    private function assertShapefileIsWgs84OrUnknown(?string $prjPath): void
    {
        if (null === $prjPath || !is_file($prjPath)) {
            return; // Pas de .prj : on suppose WGS84 (limite documentée dans Swagger).
        }

        $wkt = (string) file_get_contents($prjPath);
        $isWgs84 = str_contains($wkt, '4326') || false !== stripos($wkt, 'WGS_1984') || false !== stripos($wkt, 'WGS84');

        if (!$isWgs84) {
            throw new \InvalidArgumentException(
                'Le système de coordonnées du Shapefile (.prj) n\'est pas WGS84 (EPSG:4326), seul système supporté '
                . 'actuellement. Reprojetez le fichier avant import (ex. QGIS : Export > Enregistrer les entités '
                . 'sous > SCR EPSG:4326).'
            );
        }
    }

    /**
     * Lecture binaire séquentielle du format ESRI Shapefile (.shp) : en-tête de 100 octets,
     * puis une suite d'enregistrements (en-tête 8 octets big-endian + contenu little-endian).
     * La longueur déclarée de chaque enregistrement sert à sauter précisément au suivant,
     * quelle que soit la variante exacte du type de forme (Z/M).
     */
    private function readShpFile(string $path): array
    {
        $size = @filesize($path);
        if (false === $size || $size < 100) {
            throw new \RuntimeException('Fichier .shp invalide ou tronqué (en-tête manquant).');
        }

        $handle = fopen($path, 'rb');
        if (false === $handle) {
            throw new \RuntimeException('Impossible d\'ouvrir le fichier .shp.');
        }

        $header = fread($handle, 100);
        $fileCode = false !== $header ? (unpack('N', substr($header, 0, 4))[1] ?? null) : null;
        if (9994 !== $fileCode) {
            fclose($handle);
            throw new \RuntimeException('Le fichier .shp ne commence pas par la signature ESRI attendue (en-tête invalide).');
        }

        $shapes = [];

        while (!feof($handle)) {
            $recordHeader = fread($handle, 8);
            if (false === $recordHeader || strlen($recordHeader) < 8) {
                break;
            }

            $fields = unpack('Nnum/Nlen', $recordHeader);
            $contentBytes = ($fields['len'] ?? 0) * 2;
            if ($contentBytes <= 0 || $contentBytes > 50_000_000) {
                break;
            }

            $content = fread($handle, $contentBytes);
            if (false === $content || strlen($content) < 4) {
                break;
            }

            $shapeType = unpack('V', substr($content, 0, 4))[1] ?? 0;
            foreach ($this->decodeShpRecordContent((int) $shapeType, $content) as $shape) {
                $shapes[] = $shape;
            }

            if (count($shapes) > self::MAX_SHAPES) {
                fclose($handle);
                throw new \RuntimeException(sprintf('Trop d\'entités dans le Shapefile (maximum %d).', self::MAX_SHAPES));
            }
        }

        fclose($handle);

        return $shapes;
    }

    /**
     * @return list<array{type: string, latitude: float, longitude: float, geometry: array|null}>
     */
    private function decodeShpRecordContent(int $shapeType, string $content): array
    {
        return match (true) {
            in_array($shapeType, [1, 11, 21], true) => $this->decodeShpPoint($content),
            in_array($shapeType, [8, 18, 28], true) => $this->decodeShpMultiPoint($content),
            in_array($shapeType, [3, 13, 23], true) => $this->decodeShpPolyOrPolygon($content, false),
            in_array($shapeType, [5, 15, 25], true) => $this->decodeShpPolyOrPolygon($content, true),
            default => [], // 0 = Null Shape, ou type non supporté : ignoré sans faire échouer l'import
        };
    }

    private function decodeShpPoint(string $content): array
    {
        if (strlen($content) < 20) {
            return [];
        }
        $x = unpack('e', substr($content, 4, 8))[1] ?? null;
        $y = unpack('e', substr($content, 12, 8))[1] ?? null;
        if (null === $x || null === $y) {
            return [];
        }

        return [['type' => 'Point', 'latitude' => (float) $y, 'longitude' => (float) $x, 'geometry' => null]];
    }

    private function decodeShpMultiPoint(string $content): array
    {
        if (strlen($content) < 40) {
            return [];
        }
        $numPoints = unpack('V', substr($content, 36, 4))[1] ?? 0;
        $shapes = [];
        $offset = 40;
        for ($i = 0; $i < $numPoints; ++$i) {
            if ($offset + 16 > strlen($content)) {
                break;
            }
            $x = unpack('e', substr($content, $offset, 8))[1] ?? null;
            $y = unpack('e', substr($content, $offset + 8, 8))[1] ?? null;
            if (null !== $x && null !== $y) {
                $shapes[] = ['type' => 'Point', 'latitude' => (float) $y, 'longitude' => (float) $x, 'geometry' => null];
            }
            $offset += 16;
        }

        return $shapes;
    }

    /**
     * Décode un enregistrement PolyLine ou Polygon (et leurs variantes Z/M, dont le début a
     * un layout identique). Chaque "part" du fichier .shp devient un anneau GeoJSON : pour un
     * Polygon, cela correspond exactement à la sémantique GeoJSON (premier anneau = extérieur,
     * suivants = trous) ; pour une PolyLine à plusieurs parts, le résultat est une MultiLineString.
     */
    private function decodeShpPolyOrPolygon(string $content, bool $isPolygon): array
    {
        if (strlen($content) < 44) {
            return [];
        }

        $numParts = unpack('V', substr($content, 36, 4))[1] ?? 0;
        $numPoints = unpack('V', substr($content, 40, 4))[1] ?? 0;
        if ($numParts <= 0 || $numPoints <= 0 || $numParts > 100_000 || $numPoints > self::MAX_COORDS_PER_GEOMETRY) {
            return [];
        }

        $partsOffset = 44;
        $parts = [];
        for ($i = 0; $i < $numParts; ++$i) {
            $pos = $partsOffset + $i * 4;
            if ($pos + 4 > strlen($content)) {
                return [];
            }
            $parts[] = unpack('V', substr($content, $pos, 4))[1] ?? 0;
        }

        $pointsOffset = $partsOffset + $numParts * 4;
        $allPoints = [];
        for ($i = 0; $i < $numPoints; ++$i) {
            $pos = $pointsOffset + $i * 16;
            if ($pos + 16 > strlen($content)) {
                break;
            }
            $x = unpack('e', substr($content, $pos, 8))[1] ?? null;
            $y = unpack('e', substr($content, $pos + 8, 8))[1] ?? null;
            if (null !== $x && null !== $y) {
                $allPoints[] = [(float) $x, (float) $y];
            }
        }

        $rings = [];
        $partCount = count($parts);
        for ($p = 0; $p < $partCount; ++$p) {
            $start = $parts[$p];
            $end = $p + 1 < $partCount ? $parts[$p + 1] : count($allPoints);
            $ring = array_slice($allPoints, $start, max(0, $end - $start));
            if ([] !== $ring) {
                $rings[] = $ring;
            }
        }

        if ([] === $rings) {
            return [];
        }

        $flatPoints = array_merge(...$rings);
        [$lat, $lon] = $this->computeCentroid($flatPoints);

        if ($isPolygon) {
            return [[
                'type' => 'Polygon',
                'latitude' => $lat,
                'longitude' => $lon,
                'geometry' => ['type' => 'Polygon', 'coordinates' => $rings],
            ]];
        }

        if (1 === count($rings)) {
            return [[
                'type' => 'LineString',
                'latitude' => $lat,
                'longitude' => $lon,
                'geometry' => ['type' => 'LineString', 'coordinates' => $rings[0]],
            ]];
        }

        return [[
            'type' => 'MultiLineString',
            'latitude' => $lat,
            'longitude' => $lon,
            'geometry' => ['type' => 'MultiLineString', 'coordinates' => $rings],
        ]];
    }

    private function removeDirectoryRecursively(string $dir): void
    {
        $items = @scandir($dir);
        if (false === $items) {
            return;
        }
        foreach ($items as $item) {
            if ('.' === $item || '..' === $item) {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->removeDirectoryRecursively($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }

    // ------------------------------------------------------------------
    // Commun
    // ------------------------------------------------------------------

    /**
     * Convertit une valeur en float de manière sécurisée.
     */
    private function parseFloat(mixed $value): ?float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        $cleaned = preg_replace('/[^0-9.\-]/', '', (string) $value);
        if ('' === $cleaned || '-' === $cleaned || '.' === $cleaned) {
            return null;
        }

        $float = (float) $cleaned;

        return is_finite($float) ? $float : null;
    }
}
