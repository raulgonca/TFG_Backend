<?php

namespace App\Security;

use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Response\JWTAuthenticationSuccessResponse;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use App\Entity\User;

class JWTAuthenticationSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    private $jwtManager;

    public function __construct(JWTTokenManagerInterface $jwtManager)
    {
        $this->jwtManager = $jwtManager;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response
    {
        $user = $token->getUser();

        $payload = [
            'username' => $user instanceof User ? $user->getUsername() : null,
            'email' => $user->getUserIdentifier(),
            'roles' => $user->getRoles(),
        ];

        $jwt = $this->jwtManager->createFromPayload($user, $payload);

        $data = [
            'token' => $jwt,
            'user' => [
                'id' => $user instanceof User ? $user->getId() : null,
                'username' => $user instanceof User ? $user->getUsername() : null,
                'email' => $user->getUserIdentifier(),
                'roles' => $user->getRoles(),
            ]
        ];

        return new JWTAuthenticationSuccessResponse($jwt, $data);
    }
}