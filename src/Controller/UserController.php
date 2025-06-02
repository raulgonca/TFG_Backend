<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route; // o Attribute\Route si usas PHP 8
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[Route('/api/user', name: 'api_user_')]
final class UserController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private UserRepository $userRepository;
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher
    ) {
        $this->entityManager = $entityManager;
        $this->userRepository = $userRepository;
        $this->passwordHasher = $passwordHasher;
    }

    #[Route('/all', name: 'list', methods: ['GET'])]
    public function getAllUsers(Request $request): JsonResponse
    {
        $page = max(1, (int)$request->query->get('page', 1));
        $limit = max(1, min(100, (int)$request->query->get('limit', 100)));
        $offset = ($page - 1) * $limit;

        $users = $this->userRepository->findBy([], null, $limit, $offset);

        $usersData = [];
        foreach ($users as $user) {
            $usersData[] = [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'username' => $user->getUsername(),
                'roles' => $user->getRoles(),
            ];
        }

        return new JsonResponse($usersData, Response::HTTP_OK);
    }

    #[Route('/get/{id}', name: 'get', methods: ['GET'])]
    public function getUserId(int $id): JsonResponse
    {
        $user = $this->userRepository->find($id);

        if (!$user) {
            return new JsonResponse(['message' => 'Usuario no encontrado'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'username' => $user->getUsername(),
            'roles' => $user->getRoles(),
        ], Response::HTTP_OK);
    }

    #[Route('/update/{id}', name: 'update', methods: ['PUT'])]
    public function updateUser(Request $request, int $id): JsonResponse
    {
        $user = $this->userRepository->find($id);
        if (!$user) {
            return new JsonResponse(['message' => 'Usuario no encontrado'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['email'])) {
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                return new JsonResponse(['message' => 'El formato del email no es válido'], Response::HTTP_BAD_REQUEST);
            }
            $existingUserEmail = $this->userRepository->findOneBy(['email' => $data['email']]);
            if ($existingUserEmail && $existingUserEmail->getId() !== $user->getId()) {
                return new JsonResponse(['message' => 'El email ya está en uso'], Response::HTTP_CONFLICT);
            }
            $user->setEmail($data['email']);
        }

        if (isset($data['username'])) {
            $existingUsername = $this->userRepository->findOneBy(['username' => $data['username']]);
            if ($existingUsername && $existingUsername->getId() !== $user->getId()) {
                return new JsonResponse(['message' => 'El nombre de usuario ya está en uso'], Response::HTTP_CONFLICT);
            }
            $user->setUsername($data['username']);
        }

        if (isset($data['roles'])) {
            $user->setRoles($data['roles']);
        }

        $this->entityManager->flush();

        return new JsonResponse([
            'message' => 'Usuario actualizado correctamente',
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'username' => $user->getUsername(),
            'roles' => $user->getRoles(),
        ], Response::HTTP_OK);
    }

    #[Route('/delete/{id}', name: 'delete', methods: ['DELETE'])]
    public function deleteUser(int $id): JsonResponse
    {
        $user = $this->userRepository->find($id);
        if (!$user) {
            return new JsonResponse(['message' => 'Usuario no encontrado'], Response::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($user);
        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Usuario eliminado correctamente'], Response::HTTP_OK);
    }

    #[Route('/new', name: 'create', methods: ['POST'])]
    public function createUser(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['email'], $data['username'], $data['password'])) {
            return new JsonResponse(['message' => 'Faltan campos obligatorios (email, username, password)'], Response::HTTP_BAD_REQUEST);
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return new JsonResponse(['message' => 'El formato del email no es válido'], Response::HTTP_BAD_REQUEST);
        }

        if ($this->userRepository->findOneBy(['email' => $data['email']])) {
            return new JsonResponse(['message' => 'El email ya está en uso'], Response::HTTP_CONFLICT);
        }

        if ($this->userRepository->findOneBy(['username' => $data['username']])) {
            return new JsonResponse(['message' => 'El nombre de usuario ya está en uso'], Response::HTTP_CONFLICT);
        }

        $user = new User();
        $user->setEmail($data['email']);
        $user->setUsername($data['username']);

        $hashedPassword = $this->passwordHasher->hashPassword($user, $data['password']);
        $user->setPassword($hashedPassword);

        $roles = $data['roles'] ?? ['ROLE_USER'];
        $user->setRoles($roles);

        try {
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            return new JsonResponse([
                'message' => 'Usuario creado con éxito',
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'username' => $user->getUsername(),
                'roles' => $user->getRoles(),
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return new JsonResponse(['message' => 'Error al crear el usuario: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/update-email/{id}', name: 'update_user_email', methods: ['PUT'])]
    public function updateUserEmail(Request $request, int $id): JsonResponse
    {
        $user = $this->userRepository->find($id);
        if (!$user) {
            return new JsonResponse(['error' => 'Usuario no encontrado'], Response::HTTP_NOT_FOUND);
        }
        $data = json_decode($request->getContent(), true);
        if (!isset($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return new JsonResponse(['error' => 'Email no válido'], Response::HTTP_BAD_REQUEST);
        }
        // Comprobar que el email no esté en uso por otro usuario
        $existing = $this->userRepository->findOneBy(['email' => $data['email']]);
        if ($existing && $existing->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'Ya existe un usuario con ese email'], Response::HTTP_CONFLICT);
        }
        $user->setEmail($data['email']);
        $this->entityManager->flush();
        return new JsonResponse(['message' => 'Email actualizado correctamente']);
    }

    #[Route('/update-password/{id}', name: 'update_user_password', methods: ['PUT'])]
    public function updateUserPassword(Request $request, int $id, UserPasswordHasherInterface $passwordHasher): JsonResponse
    {
        $user = $this->userRepository->find($id);
        if (!$user) {
            return new JsonResponse(['error' => 'Usuario no encontrado'], Response::HTTP_NOT_FOUND);
        }
        $data = json_decode($request->getContent(), true);
        if (!isset($data['currentPassword'], $data['newPassword'])) {
            return new JsonResponse(['error' => 'Datos incompletos'], Response::HTTP_BAD_REQUEST);
        }
        // Verifica la contraseña actual
        if (!$passwordHasher->isPasswordValid($user, $data['currentPassword'])) {
            return new JsonResponse(['error' => 'La contraseña actual no es correcta'], Response::HTTP_FORBIDDEN);
        }
        if (strlen($data['newPassword']) < 6) {
            return new JsonResponse(['error' => 'La nueva contraseña debe tener al menos 6 caracteres'], Response::HTTP_BAD_REQUEST);
        }
        $user->setPassword($passwordHasher->hashPassword($user, $data['newPassword']));
        $this->entityManager->flush();
        return new JsonResponse(['message' => 'Contraseña actualizada correctamente']);
    }
}
