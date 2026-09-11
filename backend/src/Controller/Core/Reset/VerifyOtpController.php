<?php

namespace App\Controller\Core\Reset;

use OpenApi\Attributes as OA;
use App\Service\Core\FunctionService;
use App\Repository\Core\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[OA\Tag(name: "Reset")]
class VerifyOtpController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $userRepository,
        private FunctionService $functionService,
        private JWTTokenManagerInterface $jwtManager,
    ) {
    }

    #[Route('/reset-password/verify-otp', name: 'verify_otp', methods: ['POST'])]
    #[OA\Post(
        path: '/reset-password/verify-otp',
        summary: 'Verify OTP code and generate reset token.',
        tags: ['Reset'],
        description: "Verify the OTP code sent to the user's email and generate a reset token if valid.",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'email', type: 'string', example: 'email@kiama.cm'),
                    new OA\Property(property: 'code_otp', type: 'string', example: '123456')
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
                        new OA\Property(property: 'message', type: 'string', example: 'OTP verified successfully.'),
                        new OA\Property(property: 'token', type: 'string', example: 'abcdef123456...'),
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Error 400: Invalid or expired OTP code.'),
            new OA\Response(response: 404, description: 'Error 404: User not found.'),
        ]
    )]
    public function verifyOtp(Request $request): JsonResponse
    {
        // RÃ©cupÃ©rer les donnÃ©es
        $data = json_decode($request->getContent(), true);

        // VÃ©rification si les champs sont valides
        $this->functionService->entityValide($data, ['email', 'code_otp']);

        // VÃ©rifier si les donnÃ©es des champs sont valide.
        $this->functionService->validate($data);

        // Autoriser uniquement certains champs modifiables
        $data = $this->functionService->allowFields($data, ['email', 'code_otp']);

        // Rechercher l'utilisateur
        $user = $this->userRepository->findOneBy(['email' => $data['email']]);
        if (!$user) {
            return $this->json([
                'code' => 404, 
                'message' => 'User not found.'
            ], 404);
        }

        // VÃ©rifier si un code OTP existe
        if (!$user->getCodeOtp()) {
            return $this->json([
                'code' => 400, 
                'message' => 'No OTP code found. Please request a new one.'
            ], 400);
        }

        // VÃ©rifier si le code OTP a expirÃ©
        $now = new \DateTime();
        if (!$user->getExpirationOtp() || $user->getExpirationOtp() < $now) {
            // Nettoyer le code OTP expirÃ©
            $user->setCodeOtp(null);
            $user->setExpirationOtp(null);
            $this->em->flush();

            return $this->json([
                'code' => 400, 
                'message' => 'OTP code has expired. Please request a new one.'
            ], 400);
        }

        // VÃ©rifier si le code OTP correspond
        if ($user->getCodeOtp() !== $data['code_otp']) {
            return $this->json([
                'code' => 400, 
                'message' => 'Invalid OTP code.'
            ], 400);
        }

        // Le code OTP est valide, gÃ©nÃ©rer un token JWT de rÃ©initialisation
        $token = $this->jwtManager->create($user);
        $user->setResetToken($token);
        $user->setResetTokenExpiresAt((new \DateTime())->modify('+1 hour'));
        
        // Nettoyer le code OTP aprÃ¨s utilisation
        $user->setCodeOtp(null);
        $user->setExpirationOtp(null);
        
        $this->em->flush();

        // Retourner la rÃ©ponse JSON avec le token
        return $this->json([
            'code' => 200, 
            'message' => 'OTP verified successfully.',
            'token' => $token
        ], 200);
    }
}
