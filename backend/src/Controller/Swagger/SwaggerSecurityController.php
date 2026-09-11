<?php

namespace App\Controller\Swagger;

use App\Form\Swagger\SwaggerLoginForm;
use App\Repository\Core\UserRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class SwaggerSecurityController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $userPasswordHasher
    ) {
    }

    #[Route('/api-login', name: 'app_swagger_login', methods: ['GET', 'POST'])]
    public function index(Request $request, SessionInterface $session): Response
    {
        // Vérifier si l'utilisateur est authentifié
        if ($session->get('user_id')) {
            $this->addFlash('info', 'You are already logged in.');
            return $this->redirectToRoute('app_swagger');
        }

        $form = $this->createForm(SwaggerLoginForm::class, null, [
            'method' => 'POST',
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid())
        {
            // Rechercher l'utilisateur par username
            $user = $this->userRepository->findOneBy([
                'username' => $form->get('username')->getData()
            ]);
            // Vérifiez si l'utilisateur existe et si le mot de passe est valide
            if (!$user || !$user->isVerified() || $user->isDelete() || !$user->isActive() || $user->getFirstLogin() || !in_array('ROLE_ADMIN', $user->getRoles()) || !$this->userPasswordHasher->isPasswordValid($user, $form->get('plainPassword')->getData())) {
                $this->addFlash('error', 'Invalid credentials.');
                return $this->redirectToRoute('app_swagger_login');
            }

            // Stocker l'utilisateur dans la session
            $session->set('user_id', $user->getId());
            $session->set('username', $user->getUsername());

            // Ajouter un message flash de succès
            $this->addFlash('success', 'Login successful. Welcome, ' . $user->getUsername() . '!');

            // L'utilisateur est authentifié, redirigez-le vers la page Swagger
            return $this->redirectToRoute('app_swagger');
        }

        return $this->render('swagger-ui/swagger_login.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/api-logout', name: 'app_swagger_logout', methods: ['GET'])]
    public function logout(SessionInterface $session): Response
    {
        // Supprimer les données de session
        $session->clear();

        $this->addFlash('success', 'You are logged out.');
        return $this->redirectToRoute('app_swagger_login');
    }
}
