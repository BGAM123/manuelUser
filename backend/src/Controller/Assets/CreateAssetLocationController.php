<?php

namespace App\Controller\Assets;

use App\Entity\Asset;
use App\Entity\Location;
use App\Entity\AssetLocation;
use App\Repository\LocationRepository;
use App\Repository\AssetLocationRepository;
use App\Service\ApiResponseFactory;
use App\Service\GeoFileParserService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/assets/{id}/locations', name: 'app_asset_location_create', methods: ['POST'])]
#[OA\Tag(name: 'Assets')]
final class CreateAssetLocationController extends AbstractController
{
    #[OA\Post(
        path: '/assets/{id}/locations',
        summary: 'Enregistrer une localisation pour un bien (terrain ou bâtiment)',
        description: "Crée un ou plusieurs nouveaux enregistrements de localisation géographique pour un bien — typiquement un terrain ou un bâtiment repéré sur une carte. "
            . "Chaque appel **ajoute** à l'historique du bien (rien n'est jamais écrasé) : utilisez `GET /assets/{id}/location` pour la position courante, "
            . "ou `GET /assets/{id}/locations-history` pour l'historique complet.\n\n"
            . "**Vous n'avez pas besoin de connaître la structure interne du fichier envoyé** (FeatureCollection, Placemark, trkpt...) : "
            . "le système détecte automatiquement le format, analyse les géométries qu'il contient (point, ligne, polygone...) et les extrait. "
            . "Une géométrie complexe (ex. un Polygon à 8 sommets pour les limites d'un terrain) est conservée **intacte** dans le champ "
            . "`geometry` de la localisation créée, et reste **une seule entrée** d'historique — jamais une par sommet.\n\n"
            . "**Deux modes d'envoi, selon le `Content-Type` de la requête :**\n"
            . "1. `application/json` — coordonnées d'un point saisies directement (ex. clic sur une carte) : une seule localisation créée.\n"
            . "2. `multipart/form-data` — upload d'un fichier géospatial : une localisation est créée **par géométrie détectée** dans le fichier "
            . "(un point isolé, une ligne complète, un polygone complet...).\n\n"
            . "**Formats de fichier supportés :**\n"
            . "- **CSV** : colonnes détectées par leur nom (`latitude`/`lat`, `longitude`/`lon`/`lng`, insensible à la casse et à l'ordre), "
            . "séparateur `,` ou `;` détecté automatiquement. Sans en-tête reconnu, la première ligne est lue positionnellement.\n"
            . "- **Excel** (`.xlsx`, `.xls`) : mêmes règles de détection de colonnes par nom que le CSV (en-tête requis).\n"
            . "- **GeoJSON** (`.json`, `.geojson`) : toute structure valide — `FeatureCollection`, `Feature`, `GeometryCollection`, ou une "
            . "géométrie `Point`/`MultiPoint`/`LineString`/`MultiLineString`/`Polygon`/`MultiPolygon` directement à la racine.\n"
            . "- **KML** : `Point`, `LineString`, `Polygon` (avec trous), y compris imbriqués dans un `MultiGeometry`.\n"
            . "- **GPX** : `wpt` (points isolés), `trk`/`trkseg`/`trkpt` (une trace = une seule ligne), `rte`/`rtept` (un itinéraire = une seule ligne).\n"
            . "- **Shapefile** : un `.shp` seul, ou — recommandé — un `.zip` contenant `.shp` + `.shx` + `.dbf` + `.prj`. "
            . "Le `.prj` permet de vérifier que le système de coordonnées est bien WGS84 (EPSG:4326), seul système supporté actuellement ; "
            . "sans `.prj`, WGS84 est supposé par défaut.\n\n"
            . "**Limites :** fichier ≤ 20 Mo, 5000 géométries maximum par import."
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        description: 'Identifiant du bien (terrain ou bâtiment)',
        schema: new OA\Schema(type: 'integer'),
        example: 1
    )]
    #[OA\RequestBody(
        required: true,
        content: [
            new OA\JsonContent(
                description: 'Mode 1 : coordonnées directes d\'un point (JSON)',
                required: ['latitude', 'longitude'],
                properties: [
                    new OA\Property(
                        property: 'latitude',
                        type: 'number',
                        format: 'float',
                        minimum: -90,
                        maximum: 90,
                        example: 3.8480,
                        description: 'Latitude du point, en degrés décimaux (WGS84). Doit être comprise entre -90 et 90.'
                    ),
                    new OA\Property(
                        property: 'longitude',
                        type: 'number',
                        format: 'float',
                        minimum: -180,
                        maximum: 180,
                        example: 11.5021,
                        description: 'Longitude du point, en degrés décimaux (WGS84). Doit être comprise entre -180 et 180.'
                    ),
                ],
                example: ['latitude' => 3.8480, 'longitude' => 11.5021]
            ),
            new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    description: 'Mode 2 : upload d\'un fichier géospatial (une localisation créée par géométrie détectée)',
                    required: ['file'],
                    properties: [
                        new OA\Property(
                            property: 'file',
                            type: 'string',
                            format: 'binary',
                            description: 'Fichier géospatial à analyser : CSV, Excel (.xlsx/.xls), GeoJSON, KML, GPX, ou Shapefile (.shp / .zip).'
                        ),
                    ]
                )
            ),
        ]
    )]
    #[OA\Response(
        response: 201,
        description: "Created - Localisation(s) enregistrée(s) avec succès. En mode JSON, `data` est un objet unique (Point). "
            . "En mode fichier, `data` est un tableau contenant une entrée par géométrie extraite du fichier — chacune avec son "
            . "`geometry_type` (`Point`, `LineString`, `Polygon`...) et, pour les géométries non ponctuelles, sa `geometry` complète "
            . "(`latitude`/`longitude` restent le centroïde, pour un affichage immédiat sans traiter `geometry`).",
        content: new OA\JsonContent(
            example: [
                'success' => true,
                'status' => 201,
                'message' => '1 localisation(s) enregistrée(s) avec succès.',
                'data' => [
                    [
                        'id' => 1,
                        'latitude' => 3.8480,
                        'longitude' => 11.5021,
                        'geometry_type' => 'Polygon',
                        'geometry' => [
                            'type' => 'Polygon',
                            'coordinates' => [[[11.5015, 3.8475], [11.5027, 3.8475], [11.5027, 3.8486], [11.5015, 3.8486], [11.5015, 3.8475]]],
                        ],
                    ],
                ],
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Bad Request - Latitude/longitude manquante ou hors plage, fichier vide/illisible, format non supporté, '
            . 'colonnes latitude/longitude non identifiables (CSV/Excel), ou système de coordonnées non-WGS84 (Shapefile)',
        content: new OA\JsonContent(
            example: ['success' => false, 'status' => 400, 'message' => 'Impossible d\'identifier les colonnes latitude/longitude dans le fichier Excel.', 'data' => null]
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'Not Found - Bien introuvable',
        content: new OA\JsonContent(example: ['success' => false, 'status' => 404, 'message' => 'La ressource demandée est introuvable.', 'data' => null])
    )]
    public function __invoke(
        Asset $asset,
        Request $request,
        LocationRepository $locationRepository,
        AssetLocationRepository $assetLocationRepository,
        ValidatorInterface $validator,
        ApiResponseFactory $apiResponse,
        GeoFileParserService $geoFileParser
    ): JsonResponse {
        // Vérifier si c'est un upload de fichier
        $file = $request->files->get('file');

        if ($file) {
            // Traitement du fichier
            try {
                $coordinates = $geoFileParser->extractCoordinates($file);

                if (empty($coordinates)) {
                    return $apiResponse->error('Aucune coordonnée trouvée dans le fichier.', Response::HTTP_BAD_REQUEST);
                }

                // Enregistrer toutes les géométries extraites (une localisation par géométrie
                // détectée : un Polygon à N sommets reste une seule entrée, jamais N entrées)
                $savedLocations = [];
                $totalCoordinates = count($coordinates);
                foreach ($coordinates as $index => $coord) {
                    $location = new Location();
                    $location->setLatitude($coord['latitude']);
                    $location->setLongitude($coord['longitude']);
                    $location->setGeometryType($coord['type']);
                    $location->setGeometry($coord['geometry']);

                    $errors = $validator->validate($location);
                    if (count($errors) > 0) {
                        return $apiResponse->error('La validation a échoué pour une coordonnée.', Response::HTTP_BAD_REQUEST);
                    }

                    $isLastItem = ($index === $totalCoordinates - 1);
                    $locationRepository->save($location, $isLastItem);

                    // Créer la relation asset_location
                    $assetLocation = new AssetLocation();
                    $assetLocation->setAsset($asset);
                    $assetLocation->setLocation($location);
                    $assetLocationRepository->save($assetLocation, $isLastItem);

                    $savedLocations[] = [
                        'id' => $location->getId(),
                        'latitude' => $location->getLatitude(),
                        'longitude' => $location->getLongitude(),
                        'geometry_type' => $location->getGeometryType(),
                        'geometry' => $location->getGeometry(),
                    ];
                }

                return $apiResponse->success($savedLocations, Response::HTTP_CREATED, count($savedLocations) . ' localisation(s) enregistrée(s) avec succès.');

            } catch (\InvalidArgumentException $e) {
                return $apiResponse->error($e->getMessage(), Response::HTTP_BAD_REQUEST);
            } catch (\RuntimeException $e) {
                return $apiResponse->error('Erreur lors du traitement du fichier: ' . $e->getMessage(), Response::HTTP_BAD_REQUEST);
            }
        }

        // Traitement des coordonnées JSON
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $apiResponse->error('Invalid JSON payload.', Response::HTTP_BAD_REQUEST);
        }

        // Validation des coordonnées
        if (!isset($payload['latitude']) || !is_numeric($payload['latitude'])) {
            return $apiResponse->error('La latitude est requise et doit être un nombre.', Response::HTTP_BAD_REQUEST);
        }
        if (!isset($payload['longitude']) || !is_numeric($payload['longitude'])) {
            return $apiResponse->error('La longitude est requise et doit être un nombre.', Response::HTTP_BAD_REQUEST);
        }

        $latitude = (float) $payload['latitude'];
        $longitude = (float) $payload['longitude'];

        if ($latitude < -90 || $latitude > 90) {
            return $apiResponse->error('La latitude doit être entre -90 et 90.', Response::HTTP_BAD_REQUEST);
        }
        if ($longitude < -180 || $longitude > 180) {
            return $apiResponse->error('La longitude doit être entre -180 et 180.', Response::HTTP_BAD_REQUEST);
        }

        // Fermer la localisation précédente si elle existe
        // Plus nécessaire car on garde tout l'historique

        // Créer la nouvelle localisation
        $location = new Location();
        $location->setLatitude($latitude);
        $location->setLongitude($longitude);
        $location->setGeometryType('Point');

        $errors = $validator->validate($location);
        if (count($errors) > 0) {
            return $apiResponse->error('La validation a échoué.', Response::HTTP_BAD_REQUEST);
        }

        $locationRepository->save($location);

        // Créer la relation asset_location
        $assetLocation = new AssetLocation();
        $assetLocation->setAsset($asset);
        $assetLocation->setLocation($location);
        $assetLocationRepository->save($assetLocation, flush: true);

        $data = [
            'id' => $location->getId(),
            'latitude' => $location->getLatitude(),
            'longitude' => $location->getLongitude(),
            'geometry_type' => $location->getGeometryType(),
            'geometry' => $location->getGeometry(),
        ];

        return $apiResponse->success($data, Response::HTTP_CREATED, 'Localisation enregistrée avec succès.');
    }
}
