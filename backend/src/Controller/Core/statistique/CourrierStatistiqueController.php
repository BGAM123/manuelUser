<?php

namespace App\Controller\Core\statistique;

use App\Service\UserActionLoggerService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/core/statistics/courrier')]
#[OA\Tag(name: "Statistics")]
class CourrierStatistiqueController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private UserActionLoggerService $actionLogger;

    public function __construct(
        EntityManagerInterface $entityManager,
        UserActionLoggerService $actionLogger
    ) {
        $this->entityManager = $entityManager;
        $this->actionLogger = $actionLogger;
    }

    
    /**
     * Statistiques : nombre de classes et nombre de types de courrier par entitÃ©
     */
    #[Route('/comptage-par-entite', name: 'api_statistique_comptage_par_entite', methods: ['GET'])]
    #[OA\Get(
        path: '/core/statistics/courrier/comptage-par-entite',
        summary: 'Obtenir le nombre de classes et de types de courrier pour chaque entité',
        description: 'Retourne pour chaque entité (Courrier, CourrierDepart, Transmission) le nombre de classes distinctes et le nombre de types de courrier distincts enregistrés.',
        tags: ['Statistics'],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: 'date_debut',
                in: 'query',
                required: false,
                description: 'Date de début du filtre (format: YYYY-MM-DD)',
                schema: new OA\Schema(type: 'string', format: 'date')
            ),
            new OA\Parameter(
                name: 'date_fin',
                in: 'query',
                required: false,
                description: 'Date de fin du filtre (format: YYYY-MM-DD)',
                schema: new OA\Schema(type: 'string', format: 'date')
            ),
            new OA\Parameter(
                name: 'service_id',
                in: 'query',
                required: false,
                description: 'Filtrer par ID du service traitant',
                schema: new OA\Schema(type: 'integer')
            ),
        ]
    )]
    #[OA\Response(
        response: 200,
        description: 'Statistiques récupérées avec succès',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(
                    property: 'data',
                    type: 'array',
                    items: new OA\Items(
                        type: 'object',
                        properties: [
                            new OA\Property(property: 'entite', type: 'string', example: 'Courrier', description: 'Nom de l\'entité (Courrier, CourrierDepart, Transmission)'),
                            new OA\Property(property: 'nombre_classes', type: 'integer', example: 3, description: 'Nombre de classes distinctes dans cette entité'),
                            new OA\Property(property: 'nombre_types_courrier', type: 'integer', example: 5, description: 'Nombre de types de courrier distincts dans cette entité'),
                            new OA\Property(property: 'nombre_statuts', type: 'integer', example: 4, description: 'Nombre total de statuts différents dans cette entité (0 pour CourrierDepart)'),
                            new OA\Property(
                                property: 'types_par_classe',
                                type: 'array',
                                description: 'Liste des types de courrier par classe avec leurs statuts',
                                items: new OA\Items(
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'classe', type: 'string', example: 'COURRIER ARRIVE'),
                                        new OA\Property(property: 'nombre_types', type: 'integer', example: 3, description: 'Nombre de types dans cette classe'),
                                        new OA\Property(
                                            property: 'types',
                                            type: 'array',
                                            items: new OA\Items(
                                                type: 'object',
                                                properties: [
                                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                                    new OA\Property(property: 'nom', type: 'string', example: 'Note de service'),
                                                    new OA\Property(property: 'nombre_statuts', type: 'integer', example: 3, description: 'Nombre de statuts différents pour ce type'),
                                                    new OA\Property(
                                                        property: 'statuts',
                                                        type: 'array',
                                                        description: 'Liste des statuts',
                                                        items: new OA\Items(type: 'string', example: 'EN_ATTENTE')
                                                    )
                                                ]
                                            )
                                        )
                                    ]
                                )
                            )
                        ]
                    )
                ),
                new OA\Property(property: 'message', type: 'string', example: 'Statistiques récupérées avec succès')
            ]
        )
    )]
    public function getComptageParEntite(Request $request): JsonResponse
    {
        try {
            // RÃ©cupÃ©ration des paramÃ¨tres de filtre
            $dateDebut = $request->query->get('date_debut');
            $dateFin = $request->query->get('date_fin');
            $serviceId = $request->query->get('service_id');

            $statistiques = [];

            // 1. Statistiques pour l'entitÃ© Courrier (Courrier ArrivÃ©)
            $qbCourrier = $this->entityManager->createQueryBuilder();
            $qbCourrier->select('COUNT(DISTINCT c.classeCourrier) as nombre_classes')
                ->addSelect('COUNT(DISTINCT t.id) as nombre_types')
                ->from('App\Entity\Cour\Courrier', 'c')
                ->leftJoin('c.typeCourrier', 't')
                ->where('c.isDelete = :isDelete')
                ->setParameter('isDelete', false);

            if ($dateDebut) {
                $qbCourrier->andWhere('c.createdAt >= :dateDebut')
                   ->setParameter('dateDebut', new \DateTime($dateDebut));
            }
            if ($dateFin) {
                $qbCourrier->andWhere('c.createdAt <= :dateFin')
                   ->setParameter('dateFin', new \DateTime($dateFin . ' 23:59:59'));
            }
            if ($serviceId) {
                $qbCourrier->andWhere('c.idServiceTraitant = :serviceId')
                   ->setParameter('serviceId', $serviceId);
            }

            $resultCourrier = $qbCourrier->getQuery()->getOneOrNullResult();
            
            // RÃ©cupÃ©rer le dÃ©tail par classe pour Courrier
            $qbCourrierDetail = $this->entityManager->createQueryBuilder();
            $qbCourrierDetail->select('c.classeCourrier as classe, t.id as type_id, t.nom as type_nom, c.statut, COUNT(c.id) as nombre')
                ->from('App\Entity\Cour\Courrier', 'c')
                ->leftJoin('c.typeCourrier', 't')
                ->where('c.isDelete = :isDelete')
                ->setParameter('isDelete', false)
                ->groupBy('c.classeCourrier, t.id, t.nom, c.statut')
                ->orderBy('c.classeCourrier', 'ASC')
                ->addOrderBy('t.nom', 'ASC')
                ->addOrderBy('c.statut', 'ASC');

            if ($dateDebut) {
                $qbCourrierDetail->andWhere('c.createdAt >= :dateDebut')
                   ->setParameter('dateDebut', new \DateTime($dateDebut));
            }
            if ($dateFin) {
                $qbCourrierDetail->andWhere('c.createdAt <= :dateFin')
                   ->setParameter('dateFin', new \DateTime($dateFin . ' 23:59:59'));
            }
            if ($serviceId) {
                $qbCourrierDetail->andWhere('c.idServiceTraitant = :serviceId')
                   ->setParameter('serviceId', $serviceId);
            }

            $detailCourrier = $qbCourrierDetail->getQuery()->getResult();
            
            // Organiser par classe et compter les statuts globaux
            $typesParClasseMap = [];
            $statutsGlobauxCourrier = [];
            foreach ($detailCourrier as $row) {
                $classe = $row['classe'] ?: 'NON_DEFINI';
                $typeId = $row['type_id'];
                $typeNom = $row['type_nom'];
                $statut = $row['statut'] ?: 'NON_DEFINI';
                
                // Collecter tous les statuts uniques pour l'entitÃ©
                if (!in_array($statut, $statutsGlobauxCourrier)) {
                    $statutsGlobauxCourrier[] = $statut;
                }
                
                if (!isset($typesParClasseMap[$classe])) {
                    $typesParClasseMap[$classe] = [
                        'classe' => $classe,
                        'nombre_types' => 0,
                        'types' => []
                    ];
                }
                
                // Utiliser typeId comme clÃ© pour regrouper les statuts par type
                $typeKey = $typeId ?: 'null';
                if (!isset($typesParClasseMap[$classe]['types'][$typeKey])) {
                    $typesParClasseMap[$classe]['types'][$typeKey] = [
                        'id' => $typeId,
                        'nom' => $typeNom ?: 'Non défini',
                        'nombre_statuts' => 0,
                        'statuts' => []
                    ];
                    $typesParClasseMap[$classe]['nombre_types']++;
                }
                
                // Ajouter le statut s'il n'existe pas dÃ©jÃ 
                if (!in_array($statut, $typesParClasseMap[$classe]['types'][$typeKey]['statuts'])) {
                    $typesParClasseMap[$classe]['types'][$typeKey]['statuts'][] = $statut;
                    $typesParClasseMap[$classe]['types'][$typeKey]['nombre_statuts']++;
                }
            }
            
            // Convertir les types en tableau indexÃ©
            foreach ($typesParClasseMap as &$classeData) {
                $classeData['types'] = array_values($classeData['types']);
            }
            
            $typesParClasseCourrier = array_values($typesParClasseMap);

            $statistiques[] = [
                'entite' => 'Courrier',
                'nombre_classes' => (int) ($resultCourrier['nombre_classes'] ?? 0),
                'nombre_types_courrier' => (int) ($resultCourrier['nombre_types'] ?? 0),
                'nombre_statuts' => count($statutsGlobauxCourrier),
                'types_par_classe' => $typesParClasseCourrier
            ];

            // 2. Statistiques pour l'entitÃ© CourrierDepart
            // Note: CourrierDepart a un champ typeCourrier (string) et classeCourrier
            $qbDepart = $this->entityManager->createQueryBuilder();
            $qbDepart->select('COUNT(DISTINCT cd.classeCourrier) as nombre_classes')
                ->addSelect('COUNT(DISTINCT cd.typeCourrier) as nombre_types')
                ->from('App\Entity\Cour\CourrierDepart', 'cd')
                ->where('cd.isDelete = :isDelete')
                ->setParameter('isDelete', false);

            if ($dateDebut) {
                $qbDepart->andWhere('cd.createdAt >= :dateDebut')
                   ->setParameter('dateDebut', new \DateTime($dateDebut));
            }
            if ($dateFin) {
                $qbDepart->andWhere('cd.createdAt <= :dateFin')
                   ->setParameter('dateFin', new \DateTime($dateFin . ' 23:59:59'));
            }

            $resultDepart = $qbDepart->getQuery()->getOneOrNullResult();
            
            // RÃ©cupÃ©rer le dÃ©tail par classe pour CourrierDepart
            // Note: CourrierDepart n'a pas de champ statut
            $qbDepartDetail = $this->entityManager->createQueryBuilder();
            $qbDepartDetail->select('cd.classeCourrier as classe, cd.typeCourrier as type_nom, COUNT(cd.id) as nombre')
                ->from('App\Entity\Cour\CourrierDepart', 'cd')
                ->where('cd.isDelete = :isDelete')
                ->andWhere('cd.typeCourrier IS NOT NULL')
                ->setParameter('isDelete', false)
                ->groupBy('cd.classeCourrier, cd.typeCourrier')
                ->orderBy('cd.classeCourrier', 'ASC')
                ->addOrderBy('cd.typeCourrier', 'ASC');

            if ($dateDebut) {
                $qbDepartDetail->andWhere('cd.createdAt >= :dateDebut')
                   ->setParameter('dateDebut', new \DateTime($dateDebut));
            }
            if ($dateFin) {
                $qbDepartDetail->andWhere('cd.createdAt <= :dateFin')
                   ->setParameter('dateFin', new \DateTime($dateFin . ' 23:59:59'));
            }

            $detailDepart = $qbDepartDetail->getQuery()->getResult();
            
            // Organiser par classe (note: pas d'ID ni de statut pour CourrierDepart car typeCourrier est un string)
            $typesParClasseMapDepart = [];
            foreach ($detailDepart as $row) {
                $classe = $row['classe'] ?: 'NON_DEFINI';
                $typeNom = $row['type_nom'];
                
                if (!isset($typesParClasseMapDepart[$classe])) {
                    $typesParClasseMapDepart[$classe] = [
                        'classe' => $classe,
                        'nombre_types' => 0,
                        'types' => []
                    ];
                }
                
                // Utiliser le nom du type comme clÃ©
                if (!isset($typesParClasseMapDepart[$classe]['types'][$typeNom])) {
                    $typesParClasseMapDepart[$classe]['types'][$typeNom] = [
                        'nom' => $typeNom,
                        'nombre' => 0
                    ];
                    $typesParClasseMapDepart[$classe]['nombre_types']++;
                }
                
                $typesParClasseMapDepart[$classe]['types'][$typeNom]['nombre'] += (int) $row['nombre'];
            }
            
            // Convertir les types en tableau indexÃ©
            foreach ($typesParClasseMapDepart as &$classeData) {
                $classeData['types'] = array_values($classeData['types']);
            }
            
            $typesParClasseDepart = array_values($typesParClasseMapDepart);

            $statistiques[] = [
                'entite' => 'CourrierDepart',
                'nombre_classes' => (int) ($resultDepart['nombre_classes'] ?? 0),
                'nombre_types_courrier' => (int) ($resultDepart['nombre_types'] ?? 0),
                'nombre_statuts' => 0, // CourrierDepart n'a pas de champ statut
                'types_par_classe' => $typesParClasseDepart
            ];

            // 3. Statistiques pour l'entitÃ© Transmission
            // Note: Transmission n'a pas de champ classeCourrier ni typeCourrier propres
            // On va compter via la relation avec Courrier
            $qbTransmission = $this->entityManager->createQueryBuilder();
            $qbTransmission->select('COUNT(DISTINCT c.classeCourrier) as nombre_classes')
                ->addSelect('COUNT(DISTINCT t.id) as nombre_types')
                ->from('App\Entity\Cour\Transmission', 'tr')
                ->leftJoin('tr.idCourrier', 'c')
                ->leftJoin('c.typeCourrier', 't')
                ->where('tr.isDelete = :isDelete')
                ->setParameter('isDelete', false);

            if ($dateDebut) {
                $qbTransmission->andWhere('tr.createdAt >= :dateDebut')
                   ->setParameter('dateDebut', new \DateTime($dateDebut));
            }
            if ($dateFin) {
                $qbTransmission->andWhere('tr.createdAt <= :dateFin')
                   ->setParameter('dateFin', new \DateTime($dateFin . ' 23:59:59'));
            }
            if ($serviceId) {
                $qbTransmission->andWhere('tr.idServiceDestinataire = :serviceId')
                   ->setParameter('serviceId', $serviceId);
            }

            $resultTransmission = $qbTransmission->getQuery()->getOneOrNullResult();
            
            // RÃ©cupÃ©rer le dÃ©tail par classe pour Transmission
            $qbTransmissionDetail = $this->entityManager->createQueryBuilder();
            $qbTransmissionDetail->select('c.classeCourrier as classe, t.id as type_id, t.nom as type_nom, tr.statut, COUNT(tr.id) as nombre')
                ->from('App\Entity\Cour\Transmission', 'tr')
                ->leftJoin('tr.idCourrier', 'c')
                ->leftJoin('c.typeCourrier', 't')
                ->where('tr.isDelete = :isDelete')
                ->setParameter('isDelete', false)
                ->groupBy('c.classeCourrier, t.id, t.nom, tr.statut')
                ->orderBy('c.classeCourrier', 'ASC')
                ->addOrderBy('t.nom', 'ASC')
                ->addOrderBy('tr.statut', 'ASC');

            if ($dateDebut) {
                $qbTransmissionDetail->andWhere('tr.createdAt >= :dateDebut')
                   ->setParameter('dateDebut', new \DateTime($dateDebut));
            }
            if ($dateFin) {
                $qbTransmissionDetail->andWhere('tr.createdAt <= :dateFin')
                   ->setParameter('dateFin', new \DateTime($dateFin . ' 23:59:59'));
            }
            if ($serviceId) {
                $qbTransmissionDetail->andWhere('tr.idServiceDestinataire = :serviceId')
                   ->setParameter('serviceId', $serviceId);
            }

            $detailTransmission = $qbTransmissionDetail->getQuery()->getResult();
            
            // Organiser par classe et compter les statuts globaux
            $typesParClasseMapTransmission = [];
            $statutsGlobauxTransmission = [];
            foreach ($detailTransmission as $row) {
                $classe = $row['classe'] ?: 'NON_DEFINI';
                $typeId = $row['type_id'];
                $typeNom = $row['type_nom'];
                $statut = $row['statut'] ?: 'NON_DEFINI';
                
                // Collecter tous les statuts uniques pour l'entitÃ©
                if (!in_array($statut, $statutsGlobauxTransmission)) {
                    $statutsGlobauxTransmission[] = $statut;
                }
                
                if (!isset($typesParClasseMapTransmission[$classe])) {
                    $typesParClasseMapTransmission[$classe] = [
                        'classe' => $classe,
                        'nombre_types' => 0,
                        'types' => []
                    ];
                }
                
                // Utiliser typeId comme clÃ© pour regrouper les statuts par type
                $typeKey = $typeId ?: 'null';
                if (!isset($typesParClasseMapTransmission[$classe]['types'][$typeKey])) {
                    $typesParClasseMapTransmission[$classe]['types'][$typeKey] = [
                        'id' => $typeId,
                        'nom' => $typeNom ?: 'Non dÃ©fini',
                        'nombre_statuts' => 0,
                        'statuts' => []
                    ];
                    $typesParClasseMapTransmission[$classe]['nombre_types']++;
                }
                
                // Ajouter le statut s'il n'existe pas dÃ©jÃ 
                if (!in_array($statut, $typesParClasseMapTransmission[$classe]['types'][$typeKey]['statuts'])) {
                    $typesParClasseMapTransmission[$classe]['types'][$typeKey]['statuts'][] = $statut;
                    $typesParClasseMapTransmission[$classe]['types'][$typeKey]['nombre_statuts']++;
                }
            }
            
            // Convertir les types en tableau indexÃ©
            foreach ($typesParClasseMapTransmission as &$classeData) {
                $classeData['types'] = array_values($classeData['types']);
            }
            
            $typesParClasseTransmission = array_values($typesParClasseMapTransmission);

            $statistiques[] = [
                'entite' => 'Transmission',
                'nombre_classes' => (int) ($resultTransmission['nombre_classes'] ?? 0),
                'nombre_types_courrier' => (int) ($resultTransmission['nombre_types'] ?? 0),
                'nombre_statuts' => count($statutsGlobauxTransmission),
                'types_par_classe' => $typesParClasseTransmission
            ];

            // Logger l'action
            $this->actionLogger->logAction(
                'CONSULTATION',
                'Consultation du comptage des classes et types de courrier par entité',
                'Statistique',
                null,
                []
            );

            return new JsonResponse([
                'success' => true,
                'data' => $statistiques,
                'message' => 'Statistiques récupérées avec succès'
            ], 200);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur lors de la récupération des statistiques : ' . $e->getMessage()
            ], 500);
        }
    }

}
