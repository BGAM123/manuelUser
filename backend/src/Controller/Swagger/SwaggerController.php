<?php

namespace App\Controller\Swagger;

use App\Repository\Core\UserRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class SwaggerController extends AbstractController
{
    public function __construct(
        private Security $security,
        private UserRepository $userRepository,
    ) {
    }

    #[Route('/', name: 'app_swagger', methods: ['GET'])]
    public function index(Request $request, SessionInterface $session): Response
    {
        // Vérifier la session
        if ($response = $this->verifySession($session)) {
            return $response;
        }

        return $this->render('swagger-ui/api.html.twig');
    }

    #[Route('/{module}', name: 'app_swagger_module', methods: ['GET'], requirements: ['module' => 'core|profile|reset|security'])]
    public function module(Request $request, string $module, SessionInterface $session): Response
    {
        // Vérifier la session
        if ($response = $this->verifySession($session)) {
            return $response;
        }

        // Charge le bon template en fonction du module.
        $templates = [
            'core' => 'swagger-ui/core.html.twig',
            'profile' => 'swagger-ui/profile.html.twig',
            'reset' => 'swagger-ui/reset.html.twig',
            'security' => 'swagger-ui/security.html.twig',
        ];

        if (!isset($templates[$module])) {
            throw $this->createNotFoundException('Module not found.');
        }

        return $this->render($templates[$module]);
    }

    private function verifySession(SessionInterface $session): ?Response
    {
        // Vérifiez si l'utilisateur est authentifié
        if (!$session->get('user_id')) {
            $this->addFlash('error', 'You must be logged in to access this page.');
            return $this->redirectToRoute('app_swagger_login');
        }

        // Vérifiez si la session est inactive trop longtemps
        $lastActivity = $session->get('last_activity');
        $timeout = 900; // 15 minutes

        if ($lastActivity && (time() - $lastActivity > $timeout)) {
            // Détruire la session si elle est expirée
            $session->clear();
            $this->addFlash('error', 'Your session has expired due to inactivity.');
            return $this->redirectToRoute('app_swagger_login');
        }

        // Rechercher l'utilisateur par username
        $user = $this->userRepository->find($session->get('user_id'));
        if (!$user || !$user->isVerified() || $user->isDelete() || !$user->isActive() || $user->getFirstLogin() || !in_array('ROLE_ADMIN', $user->getRoles())) {
            // Supprimer les données de session si nécessaire
            $session->clear();
            $this->addFlash('error', 'Access denied.');
            return $this->redirectToRoute('app_swagger_login');
        }

        // Mettre à jour l'activité de l'utilisateur
        $session->set('last_activity', time());

        return null; // Continuer le traitement
    }
}
