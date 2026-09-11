<?php

namespace App\Controller\Core\Reset;

use Twig\Environment;
use OpenApi\Attributes as OA;
use App\Service\Core\FunctionService;
use App\Service\Core\SendEmailService;
use App\Repository\Core\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\Core\ResetPasswordService;
use App\Exception\EntityNotFoundException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "Reset")]
class ResetPasswordRequestController extends AbstractController
{
    public function __construct(
        private Environment $twig,
        private SendEmailService $emailService,
        private EntityManagerInterface $em,
        private UserRepository $userRepository,
        private FunctionService $functionService,
        private ResetPasswordService $resetPasswordService,
    ) {
    }

    #[Route('/reset-password-request', name: 'reset_password_request', methods: ['POST'])]
    #[OA\Post(
        path: '/reset-password-request',
        summary: 'Request a password reset OTP code.',
        tags: ['Reset'],
        description: "Request a password reset by sending an OTP code to the user's email address.",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'email', type: 'string', example: 'email@kiama.cm')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successful operation.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'OTP code sent.'),
                        new OA\Property(property: 'code_otp', type: 'string', example: '123456'),
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Error 400: Bad Request.'),
        ]
    )]
    public function requestResetPassword(Request $request): JsonResponse
    {
        // RÃ©cupÃ©rer les donnÃ©es
        $data = json_decode($request->getContent(), true);

        // VÃ©rification si les champs sont valides
        $this->functionService->entityValide($data, ['email']);

        // VÃ©rifier si les donnÃ©es des champs sont valide.
        $this->functionService->validate($data);

        $user = $this->userRepository->findOneBy(['email' => $data['email']]);
        if (!$user) {
            // throw new EntityNotFoundException();
            // Retourner un faux code OTP pour des raisons de sÃ©curitÃ© (Ã©viter l'Ã©numÃ©ration d'emails)
            return $this->json(['code' => 200, 'message' => 'OTP code sent.', 'code_otp' => '000000'], 200);
        }

        // Autoriser uniquement certains champs modifiables
        $data = $this->functionService->allowFields($data, ['email']);

        // GÃ©nÃ©rer un code OTP Ã  6 chiffres
        $codeOtp = sprintf('%06d', random_int(0, 999999));
        
        // Stocker le code OTP et sa date d'expiration (5 minutes)
        $user->setCodeOtp($codeOtp);
        $user->setExpirationOtp((new \DateTime())->modify('+5 minutes'));
        $this->em->flush();

        // Envoi du code OTP par email
        try {
            // Le contenu de l'email en HTML
            $body = $this->twig->render('emails/core/reset_password_otp.html.twig', [
                'fullName' => $user->getFullName(),
                'codeOtp' => $codeOtp,
            ]);
            $this->emailService->sendEmail(
                $this->getParameter('app_noreply_mail'), // L'utilisateur expéditeur (adresse email)
                [$data['email']],  // Les utilisateurs destinataires (tableau d'adresses emails)
                'MINEPIA - Code de réinitialisation de mot de passe', // Le sujet de l'email
                $body, // Le contenu de l'email en HTML
                [],  // (optionnel) Les destinataires en copie (Cc)
                [],  // (optionnel) Les destinataires en copie cachée (Cci)
            );
        } catch (\Exception $e) {
            return $this->json(['code' => 500, 'message' => 'Failed to send email: ' . $e->getMessage()], 500);
        }

        // Retourner la rÃ©ponse JSON avec le code OTP
        return $this->json([
            'code' => 200, 
            'message' => 'OTP code sent.',
            'code_otp' => $codeOtp
        ], 200);
    }
}
