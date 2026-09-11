<?php

namespace App\Service\Core;

use App\Service\Core\CrudService;
use App\Exception\EntityFoundException;
use Doctrine\ORM\EntityManagerInterface;
use App\Exception\InvalidFieldException;
use App\Exception\InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Service FunctionService
 * 
 * Service utilitaire fournissant diverses fonctions génériques.
 *
 * Cette classe contient des méthodes utiles pouvant être utilisées dans
 * différentes parties de l'application, comme la génération de mots de passe,
 * le filtrage de données, ou d'autres traitements communs.
 */
class FunctionService
{
    const LOCALE = [
        'en' => 'en',
        'fr' => 'fr'
    ];

    const SEXE = [
        'F' => 'F',
        'M' => 'M'
    ];

    public function __construct(
        private CrudService $crudService,
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * Génère un mot de passe aléatoire de la longueur spécifiée.
     *
     * @param int $length La longueur du mot de passe (par défaut 10 caractères).
     *
     * @return string Le mot de passe généré.
     */
    public function generatePassword(int $length = 10): string
    {
        // Ensemble des caractères possibles pour le mot de passe
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%&';
        // Initialisation de la chaîne qui contiendra le mot de passe généré
        $password = '';
        // Calcul de l'index maximal dans la chaîne de caractères
        $maxIndex = strlen($characters) - 1;
        // Génération du mot de passe caractère par caractère
        for ($i = 0; $i < $length; $i++) {
            // Sélection d'un caractère aléatoire parmi ceux disponibles
            $password .= $characters[random_int(0, $maxIndex)];
        }
        // Retourne le mot de passe généré
        return $password;
    }

    /**
     * Filtre les champs autorisés dans les données.
     *
     * @param array $data          Les données à filtrer.
     * @param array $allowedFields Les champs autorisés.
     *
     * @return array Les données filtrées.
     */
    public function allowFields(array $data, array $allowedFields): array
    {
        // Filtre les données pour ne conserver que les champs figurant dans $allowedFields
        return array_filter($data, function ($key) use ($allowedFields) {
            return in_array($key, $allowedFields, true); // Vérifie si la clé est dans les champs autorisés
        }, ARRAY_FILTER_USE_KEY); // Applique le filtre uniquement sur les clés (et non sur les valeurs)

        if (empty($data)) {
            throw new BadRequestHttpException();
        }
    }

    /**
     * Exclut les champs non modifiables des données.
     *
     * @param array $data           Les données à filtrer.
     * @param array $excludedFields Les champs à exclure.
     *
     * * @throws BadRequestHttpException Si aucun champ reste.
     * 
     * @return array Les données filtrées sans les champs exclus.
     */
    public function excludeFields(array $data, array $excludedFields): array
    {
        // Parcourt les champs à exclure
        foreach ($excludedFields as $field) {
            // Vérifie si le champ existe dans les données, puis le supprime
            if (array_key_exists($field, $data)) {
                unset($data[$field]);
            }
        }

        if (empty($data)) {
            throw new BadRequestHttpException();
        }

        // Retourne les données après exclusion
        return $data;
    }

    /**
     * Vérifie si une entité existe déjà en fonction des critères donnés, en excluant l'entité actuelle.
     *
     * @param string $entityClass Le nom complet de la classe de l'entité (FQCN).
     * @param array $criteria Les critères de recherche sous forme de tableau associatif [champ => valeur].
     * @param int|null $excludeId L'ID de l'entité à exclure de la vérification.
     *
     * @throws EntityFoundException Si une entité correspondante est trouvée.
     */
    public function entityVerify(string $entityClass, array $criteria, ?int $excludeId = null): void
    {
        // Démarre un QueryBuilder
        $qb = $this->em->getRepository($entityClass)->createQueryBuilder('e');

        // Applique les critères de recherche sous forme de conditions 'OR'
        foreach ($criteria as $field => $value) {
            $qb->orWhere("e.$field = :$field")
                ->setParameter($field, $value);
        }

        // Si un ID d'exclusion est fourni, ajoute la condition d'exclusion sur l'ID
        if ($excludeId !== null) {
            $qb->andWhere('e.id != :excludeId')
                ->setParameter('excludeId', $excludeId);
            $qb->andWhere('e.isDelete = :isDelete')
                ->setParameter('isDelete', 0);
        }

        // Exécute la requête et récupère une seule entité (ou null si elle n'existe pas)
        $entity = $qb->getQuery()->getOneOrNullResult();

        // Si une entité est trouvée, on lance l'exception
        if ($entity) {
            throw new EntityFoundException();
        }
    }

    /**
     * Vérifie si les champs requis sont présents et non vides.
     *
     * @param array|null $data Les données à valider
     * @param array|null $requiredFields Les champs requis
     * 
     * @throws BadRequestHttpException Si un champ requis est manquant ou vide
     */
    public function entityValide(?array $data, array $requiredFields): void
    {
        if (is_null($data)) {
            throw new BadRequestHttpException();
        }
        foreach ($requiredFields as $field) {
            if (!array_key_exists($field, $data) || empty($data[$field])) {
                throw new BadRequestHttpException();
            }
        }
    }

    /**
     * Met à jour une propriété booléenne spécifique d'une entité.
     *
     * @param string $entityClass Le nom complet de la classe de l'entité (FQCN).
     * @param int    $id          L'identifiant de l'entité à modifier.
     * @param string $field       Le champ booléen à modifier (exemple : 'isDelete', 'isVerified').
     * @param bool   $value       La valeur à définir pour le champ (true ou false).
     *
     * @return bool Retourne `true` si la mise à jour a réussi.
     *
     * @throws InvalidFieldException Si le champ ou le setter n'existe pas dans l'entité.
     */
    public function updateBooleanField(string $entityClass, int $id, string $field, bool $value): bool
    {
        // Récupère l'entité par son ID
        $entity = $this->crudService->getEntity($entityClass, $id);

        // Vérifie si le champ existe dans l'entité
        if (property_exists($entity, $field)) {
            // Retire le préfixe "is" du champ pour construire le setter
            $setter = 'set' . ucfirst(substr($field, 2)); // Exemple : 'isDelete' devient 'setDelete'
            if (method_exists($entity, $setter)) {
                // Appelle le setter dynamiquement pour modifier le champ
                $entity->$setter($value);
                $this->em->flush();
                return true;
            }
        }

        // Lancer une exception si le champ ou le setter n'existe pas
        throw new InvalidFieldException();
    }

    /**
     * Convertit une date string en DateTimeImmutable.
     */
    public function convertToDateTimeImmutable(?string $dateString): ?\DateTimeImmutable
    {
        return $dateString ? new \DateTimeImmutable($dateString) : null;
    }

    /**
     * Vérifier si les champs de User sont valide.
     *
     * @param array  $data     Les données à utiliser pour remplir l'entité (tableau associatif).
     *
     * @return void
     */
    public function validate(array $data): void
    {
        foreach ($data as $property => $value) {
            // Traitement spécifique : Vérifier si le locale est valide
            if ($property === 'locale') {
                if (!in_array($value, self::LOCALE, true)) {
                    throw new InvalidArgumentException();
                }
            }

            // Traitement spécifique : Vérifier si le sexe est valide
            if ($property === 'sexe') {
                if (!in_array($value, self::SEXE, true)) {
                    throw new InvalidArgumentException();
                }
            }

            // Traitement spécifique : Vérifier si l'email est valide
            if ($property === 'email') {
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    throw new InvalidArgumentException();
                }
            }

            // Traitement spécifique : Vérifier si le téléphone est valide
            if ($property === 'phone' && $value !== null && $value !== '') {
                // Accepte les numéros avec ou sans le signe + au début
                if (!preg_match('/^\+?[0-9]+$/', $value)) {
                    throw new InvalidArgumentException();
                }
                // Exemple de validation de longueur (optionnel, à adapter selon vos besoins)
                // On compte la longueur sans le +
                $phoneLength = strlen(ltrim($value, '+'));
                if ($phoneLength < 4 || $phoneLength > 15) {
                    throw new InvalidArgumentException();
                }
            }

            // Traitement spécifique : Vérifier si le mot de passe est valide
            if ($property === 'password') {
                $validator = Validation::createValidator();

                // Définir les contraintes pour le mot de passe
                $constraints = [
                    new Assert\Length([
                        'min' => 1,
                        'max' => 4096,
                        'minMessage' => 'Password must be at least {{ limit }} characters long.',
                        'maxMessage' => 'Password cannot be longer than {{ limit }} characters.',
                    ]),
                    // new Assert\PasswordStrength([
                    //     'minScore' => 1, // Score minimal (de 0 à 4)
                    //     'message' => 'The password is not strong enough. It should include uppercase, lowercase, numbers, and special characters.',
                    // ]),
                    // new Assert\NotCompromisedPassword([
                    //     'message' => 'This password has been found in a data leak. Please choose a different password.',
                    // ]),
                ];

                // Valider le mot de passe
                $violations = $validator->validate($value, $constraints);

                // Si des violations sont détectées, lever une exception
                if (count($violations) > 0) {
                    throw new InvalidArgumentException();
                }
            }




        }
    }
}
