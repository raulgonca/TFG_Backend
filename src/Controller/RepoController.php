<?php

namespace App\Controller;

use App\Entity\Repo;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security as SecurityBundleSecurity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api', name: 'api_')]
final class RepoController extends AbstractController
{
    private $entityManager;
    private $security;

    public function __construct(EntityManagerInterface $entityManager, SecurityBundleSecurity $security)
    {
        $this->entityManager = $entityManager;
        $this->security = $security;
    }

    #[Route('/repos', name: 'get_repos', methods: ['GET'])]
    public function getRepos(): JsonResponse
    {
        $user = $this->security->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'No hay usuario autenticado'], Response::HTTP_UNAUTHORIZED);
        }
        $repos = $this->entityManager->getRepository(Repo::class)->findBy(['owner' => $user]);
        
        $reposData = [];
        foreach ($repos as $repo) {
            $client = $repo->getClient();
            $reposData[] = [
                'id' => $repo->getId(),
                'projectname' => $repo->getProjectname(),
                'description' => $repo->getDescription(),
                'fechaInicio' => $repo->getFechaInicio()->format('Y-m-d'),
                'fechaFin' => $repo->getFechaFin() ? $repo->getFechaFin()->format('Y-m-d') : null,
                'fileName' => $repo->getFileName(),
                'client' => $client ? [
                    'id' => $client->getId(),
                    'name' => $client->getName()
                ] : null,
            ];
        }
        
        return new JsonResponse($reposData);
    }

    #[Route('/newrepo', name: 'create_repo', methods: ['POST'])]
    public function createRepo(Request $request): JsonResponse
    {
        // Si el request es multipart/form-data, usa $request->files y $request->request
        $projectname = $request->request->get('projectname');
        $fechaInicio = $request->request->get('fechaInicio');
        $description = $request->request->get('description');
        $fechaFin = $request->request->get('fechaFin');
        $client = $request->request->get('client');
        $fileName = $request->request->get('fileName');
        $ownerId = $request->request->get('owner');

        if (!$projectname || !$fechaInicio) {
            return new JsonResponse(['error' => 'Faltan datos obligatorios'], Response::HTTP_BAD_REQUEST);
        }

        $repo = new Repo();
        $repo->setProjectname($projectname);
        $repo->setDescription($description ?? null);
        $repo->setFechaInicio(new \DateTime($fechaInicio));

        if ($fechaFin) {
            $repo->setFechaFin(new \DateTime($fechaFin));
        }

        // Manejo del archivo
        $uploadedFile = $request->files->get('file');
        if ($uploadedFile) {
            $originalName = $uploadedFile->getClientOriginalName();
            $safeName = uniqid().'-'.$originalName;
            $uploadDir = $this->getParameter('kernel.project_dir') . '/public/FileRepos';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $uploadedFile->move($uploadDir, $safeName);
            $repo->setFileName($safeName);
        } else {
            // Si no hay archivo, asigna un string vacío o un valor por defecto
            $repo->setFileName('');
        }

        if ($client) {
            // Buscar el objeto Client si es un ID
            if (is_numeric($client)) {
                $clientObj = $this->entityManager->getRepository(\App\Entity\Client::class)->find($client);
                if (!$clientObj) {
                    return new JsonResponse(['error' => 'Cliente no encontrado'], Response::HTTP_BAD_REQUEST);
                }
                $repo->setClient($clientObj);
            } else {
                // If $client is not numeric, set to null or handle as needed
                $repo->setClient(null);
            }
        }

        // Establecer el propietario como el usuario actual o el que llega por parámetro
        if ($ownerId) {
            $owner = $this->entityManager->getRepository(User::class)->find($ownerId);
            if (!$owner) {
                return new JsonResponse(['error' => 'Usuario owner no encontrado'], Response::HTTP_BAD_REQUEST);
            }
            $repo->setOwner($owner);
        } else {
            $user = $this->security->getUser();
            if (!$user) {
                return new JsonResponse(['error' => 'No hay usuario autenticado'], Response::HTTP_UNAUTHORIZED);
            }
            $repo->setOwner($user);
        }

        $this->entityManager->persist($repo);
        $this->entityManager->flush();

        return new JsonResponse([
            'id' => $repo->getId(),
            'message' => 'Repositorio creado con éxito'
        ], Response::HTTP_CREATED);
    }

    #[Route('/updaterepo/{id}', name: 'update_repo', methods: ['PATCH'])]
    public function updateRepo(Request $request, int $id): JsonResponse
    {
        $repo = $this->entityManager->getRepository(Repo::class)->find($id);

        if (!$repo) {
            return new JsonResponse(['error' => 'Repositorio no encontrado'], Response::HTTP_NOT_FOUND);
        }

        $user = $this->security->getUser();
        $owner = $repo->getOwner();
        $isOwner = $user && $owner && $user->getId() === $owner->getId();
        if (!$isOwner) {
            return new JsonResponse([
                'error' => 'No tienes permiso para modificar este repositorio',
                'user_id' => $user ? $user->getId() : null,
                'owner_id' => $owner ? $owner->getId() : null
            ], Response::HTTP_FORBIDDEN);
        }

        // Permitir tanto JSON como FormData
        $data = [];
        $contentType = $request->headers->get('Content-Type');
        if ($contentType && 0 === strpos($contentType, 'application/json')) {
            $data = json_decode($request->getContent(), true);
        } else {
            $data = $request->request->all();
        }

        // Solo actualiza los campos que vienen en la petición
        if (array_key_exists('projectname', $data)) {
            $repo->setProjectname($data['projectname']);
        }

        if (array_key_exists('description', $data)) {
            $repo->setDescription($data['description']);
        }

        if (array_key_exists('fechaInicio', $data)) {
            $repo->setFechaInicio(new \DateTime($data['fechaInicio']));
        }

        if (array_key_exists('fechaFin', $data)) {
            $repo->setFechaFin($data['fechaFin'] ? new \DateTime($data['fechaFin']) : null);
        }

        // Manejo de archivo si viene por FormData
        $uploadedFile = $request->files->get('file');
        if ($uploadedFile) {
            $originalName = $uploadedFile->getClientOriginalName();
            $safeName = uniqid().'-'.$originalName;
            $uploadDir = $this->getParameter('kernel.project_dir') . '/public/FileRepos';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $uploadedFile->move($uploadDir, $safeName);
            $repo->setFileName($safeName);
        }

        if (array_key_exists('client', $data)) {
            // Buscar el objeto Client si es un ID
            $client = $data['client'];
            if (is_numeric($client)) {
                $clientObj = $this->entityManager->getRepository(\App\Entity\Client::class)->find($client);
                $repo->setClient($clientObj);
            } else {
                $repo->setClient($client);
            }
        }

        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Repositorio actualizado con éxito']);
    }

    #[Route('/deleterepo/{id}', name: 'delete_repo', methods: ['DELETE'])]
    public function deleteRepo(int $id): JsonResponse
    {
        $repo = $this->entityManager->getRepository(Repo::class)->find($id);

        if (!$repo) {
            return new JsonResponse(['error' => 'Repositorio no encontrado'], Response::HTTP_NOT_FOUND);
        }

        $user = $this->security->getUser();
        $owner = $repo->getOwner();
        $isOwner = $user && $owner && $user->getId() === $owner->getId();
        if (!$isOwner) {
            return new JsonResponse(['error' => 'No tienes permiso para eliminar este repositorio'], Response::HTTP_FORBIDDEN);
        }

        $this->entityManager->remove($repo);
        $this->entityManager->flush();
        
        return new JsonResponse(['message' => 'Repositorio eliminado con éxito']);
    }

    #[Route('/repos/{id}/colaboradores', name: 'add_colaborador', methods: ['POST'])]
    public function addColaborador(Request $request, int $id): JsonResponse
    {
        $repo = $this->entityManager->getRepository(Repo::class)->find($id);

        if (!$repo) {
            return new JsonResponse(['error' => 'Repositorio no encontrado'], Response::HTTP_NOT_FOUND);
        }

        $user = $this->security->getUser();
        $owner = $repo->getOwner();
        $isOwner = $user && $owner && $user->getId() === $owner->getId();
        if (!$isOwner) {
            return new JsonResponse(['error' => 'No tienes permiso para añadir colaboradores a este repositorio'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['userId'])) {
            return new JsonResponse(['error' => 'Falta el ID del usuario colaborador'], Response::HTTP_BAD_REQUEST);
        }
        
        $colaborador = $this->entityManager->getRepository(User::class)->find($data['userId']);
        
        if (!$colaborador) {
            return new JsonResponse(['error' => 'Usuario colaborador no encontrado'], Response::HTTP_NOT_FOUND);
        }
        
        // Verificar que el colaborador no sea el propietario
        if ($colaborador === $repo->getOwner()) {
            return new JsonResponse(['error' => 'El propietario no puede ser añadido como colaborador'], Response::HTTP_BAD_REQUEST);
        }
        
        // Verificar que el colaborador no esté ya añadido
        if ($repo->getColaboradores()->contains($colaborador)) {
            return new JsonResponse(['error' => 'El usuario ya es colaborador de este repositorio'], Response::HTTP_BAD_REQUEST);
        }
        
        $repo->addColaborador($colaborador);
        $this->entityManager->flush();
        
        return new JsonResponse(['message' => 'Colaborador añadido con éxito']);
    }

    #[Route('/repos/{id}/colaboradores/{userId}', name: 'remove_colaborador', methods: ['DELETE'])]
    public function removeColaborador(int $id, int $userId): JsonResponse
    {
        $repo = $this->entityManager->getRepository(Repo::class)->find($id);

        if (!$repo) {
            return new JsonResponse(['error' => 'Repositorio no encontrado'], Response::HTTP_NOT_FOUND);
        }

        $user = $this->security->getUser();
        $owner = $repo->getOwner();
        $isOwner = $user && $owner && $user->getId() === $owner->getId();
        if (!$isOwner) {
            return new JsonResponse(['error' => 'No tienes permiso para eliminar colaboradores de este repositorio'], Response::HTTP_FORBIDDEN);
        }

        $colaborador = $this->entityManager->getRepository(User::class)->find($userId);
        
        if (!$colaborador) {
            return new JsonResponse(['error' => 'Usuario colaborador no encontrado'], Response::HTTP_NOT_FOUND);
        }
        
        if (!$repo->getColaboradores()->contains($colaborador)) {
            return new JsonResponse(['error' => 'El usuario no es colaborador de este repositorio'], Response::HTTP_BAD_REQUEST);
        }
        
        $repo->removeColaborador($colaborador);
        $this->entityManager->flush();
        
        return new JsonResponse(['message' => 'Colaborador eliminado con éxito']);
    }

    #[Route('/repos/{id}/colaboradores', name: 'get_colaboradores', methods: ['GET'])]
    public function getColaboradores(int $id): JsonResponse
    {
        $repo = $this->entityManager->getRepository(Repo::class)->find($id);

        if (!$repo) {
            return new JsonResponse(['error' => 'Repositorio no encontrado'], Response::HTTP_NOT_FOUND);
        }

        $user = $this->security->getUser();
        $owner = $repo->getOwner();
        $isOwner = $user && $owner && $user->getId() === $owner->getId();
        $isColaborador = $repo->getColaboradores()->exists(function($key, $colab) use ($user) {
            return $colab->getId() === $user->getId();
        });
        if (!$isOwner && !$isColaborador) {
            return new JsonResponse(['error' => 'No tienes permiso para ver los colaboradores de este repositorio'], Response::HTTP_FORBIDDEN);
        }

        $colaboradores = [];
        foreach ($repo->getColaboradores() as $colaborador) {
            $colaboradores[] = [
                'id' => $colaborador->getId(),
                'username' => $colaborador->getUsername(),
                'email' => $colaborador->getEmail()
            ];
        }
        
        return new JsonResponse($colaboradores);
    }

    #[Route('/repos/colaboraciones', name: 'get_colaboraciones', methods: ['GET'])]
    public function getColaboraciones(): JsonResponse
    {
        $user = $this->security->getUser();
        
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('r')
           ->from(Repo::class, 'r')
           ->join('r.colaboradores', 'c')
           ->where('c = :user')
           ->setParameter('user', $user);
        
        $repos = $qb->getQuery()->getResult();
        
        $reposData = [];
        foreach ($repos as $repo) {
            $client = $repo->getClient();
            $reposData[] = [
                'id' => $repo->getId(),
                'projectname' => $repo->getProjectname(),
                'description' => $repo->getDescription(),
                'fechaInicio' => $repo->getFechaInicio()->format('Y-m-d'),
                'fechaFin' => $repo->getFechaFin() ? $repo->getFechaFin()->format('Y-m-d') : null,
                'fileName' => $repo->getFileName(),
                'client' => $client ? [
                    'id' => $client->getId(),
                    'name' => $client->getName()
                ] : null,
                'owner' => [
                    'id' => $repo->getOwner()->getId(),
                    'username' => $repo->getOwner()->getUsername()
                ]
            ];
        }
        
        return new JsonResponse($reposData);
    }

    #[Route('/repos/all', name: 'get_repos_all', methods: ['GET'])]
    public function getAllRepos(): JsonResponse
    {
        $repos = $this->entityManager->getRepository(Repo::class)->findAll();

        $reposData = [];
        foreach ($repos as $repo) {
            $client = $repo->getClient();
            $reposData[] = [
                'id' => $repo->getId(),
                'projectname' => $repo->getProjectname(),
                'description' => $repo->getDescription(),
                'fechaInicio' => $repo->getFechaInicio() ? $repo->getFechaInicio()->format('Y-m-d') : null,
                'fechaFin' => $repo->getFechaFin() ? $repo->getFechaFin()->format('Y-m-d') : null,
                'fileName' => $repo->getFileName(),
                'client' => $client ? [
                    'id' => $client->getId(),
                    'name' => $client->getName()
                ] : null,
            ];
        }

        // Devuelve un array plano de proyectos
        return new JsonResponse($reposData, Response::HTTP_OK);
    }

    #[Route('/repos/find/{id}', name: 'find_repo_by_id', methods: ['GET'])]
    public function findRepoById(int $id): JsonResponse
    {
        $repo = $this->entityManager->getRepository(Repo::class)->find($id);

        if (!$repo) {
            return new JsonResponse(['error' => 'Repositorio no encontrado'], Response::HTTP_NOT_FOUND);
        }

        $client = $repo->getClient();
        $owner = $repo->getOwner();

        $repoData = [
            'id' => $repo->getId(),
            'projectname' => $repo->getProjectname(),
            'description' => $repo->getDescription(),
            'fechaInicio' => $repo->getFechaInicio()->format('Y-m-d'),
            'fechaFin' => $repo->getFechaFin() ? $repo->getFechaFin()->format('Y-m-d') : null,
            'fileName' => $repo->getFileName(),
            'owner' => $owner ? [
                'id' => $owner->getId(),
                'username' => $owner->getUsername()
            ] : null,
            'client' => $client ? [
                'id' => $client->getId(),
                'name' => $client->getName()
            ] : null,
            'colaboradores' => array_map(function($colaborador) {
                return [
                    'id' => $colaborador->getId(),
                    'username' => $colaborador->getUsername()
                ];
            }, $repo->getColaboradores()->toArray())
        ];

        return new JsonResponse($repoData);
    }

    #[Route('/repo/{id}/download', name: 'download_repo_file', methods: ['GET'])]
    public function downloadRepoFile(int $id): Response
    {
        $repo = $this->entityManager->getRepository(Repo::class)->find($id);
        if (!$repo || !$repo->getFileName()) {
            throw $this->createNotFoundException('Archivo no encontrado');
        }
        $filePath = $this->getParameter('kernel.project_dir') . '/public/FileRepos/' . $repo->getFileName();
        if (!file_exists($filePath)) {
            throw $this->createNotFoundException('Archivo no encontrado');
        }
        return $this->file($filePath, $repo->getFileName());
    }

    #[Route('/user/{id}/projects', name: 'user_projects', methods: ['GET'])]
    public function getUserProjects(int $id): JsonResponse
    {
        // Devuelve solo los proyectos donde el usuario es propietario
        $repos = $this->entityManager->getRepository(Repo::class)->findBy(['owner' => $id]);
        $data = [];
        foreach ($repos as $repo) {
            $data[] = [
                'id' => $repo->getId(),
                'projectname' => $repo->getProjectname(),
                'fechaFin' => $repo->getFechaFin()?->format('Y-m-d'),
                // Puedes añadir más campos si los necesitas en el frontend
                'description' => $repo->getDescription(),
                'client' => $repo->getClient() ? [
                    'id' => $repo->getClient()->getId(),
                    'name' => $repo->getClient()->getName()
                ] : null,
            ];
        }
        return new JsonResponse($data);
    }

    #[Route('/user/{id}/collaborations', name: 'user_collaborations', methods: ['GET'])]
    public function getUserCollaborations(int $id): JsonResponse
    {
        // Devuelve solo los proyectos donde el usuario es colaborador (no owner)
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('r')
            ->from(Repo::class, 'r')
            ->join('r.colaboradores', 'c')
            ->where('c.id = :userId')
            ->setParameter('userId', $id);
        $repos = $qb->getQuery()->getResult();

        $data = [];
        foreach ($repos as $repo) {
            $data[] = [
                'id' => $repo->getId(),
                'projectname' => $repo->getProjectname(),
                'fechaFin' => $repo->getFechaFin()?->format('Y-m-d'),
                // Puedes añadir más campos si los necesitas en el frontend
                'description' => $repo->getDescription(),
                'client' => $repo->getClient() ? [
                    'id' => $repo->getClient()->getId(),
                    'name' => $repo->getClient()->getName()
                ] : null,
            ];
        }
        return new JsonResponse($data);
    }

    #[Route('/repos/{projectId}/colaboradores', name: 'add_collaborator', methods: ['POST'])]
    public function addCollaborator(Request $request, int $projectId): JsonResponse
    {
        $repo = $this->entityManager->getRepository(Repo::class)->find($projectId);
        if (!$repo) {
            return new JsonResponse(['error' => 'Proyecto no encontrado'], 404);
        }
        $data = json_decode($request->getContent(), true);
        if (!isset($data['userId'])) {
            return new JsonResponse(['error' => 'Falta el userId'], 400);
        }
        $user = $this->entityManager->getRepository(User::class)->find($data['userId']);
        if (!$user) {
            return new JsonResponse(['error' => 'Usuario no encontrado'], 404);
        }
        $repo->addColaborador($user);
        $this->entityManager->flush();
        return new JsonResponse(['message' => 'Colaborador añadido con éxito']);
    }

    #[Route('/repos/{projectId}/colaboradores/{userId}', name: 'remove_collaborator', methods: ['DELETE'])]
    public function removeCollaborator(int $projectId, int $userId): JsonResponse
    {
        $repo = $this->entityManager->getRepository(Repo::class)->find($projectId);
        if (!$repo) {
            return new JsonResponse(['error' => 'Proyecto no encontrado'], 404);
        }
        $user = $this->entityManager->getRepository(User::class)->find($userId);
        if (!$user) {
            return new JsonResponse(['error' => 'Usuario no encontrado'], 404);
        }
        $repo->removeColaborador($user);
        $this->entityManager->flush();
        return new JsonResponse(['message' => 'Colaborador eliminado con éxito']);
    }

    #[Route('/repos/{projectId}/colaboradores', name: 'get_project_collaborators', methods: ['GET'])]
    public function getProjectCollaborators(int $projectId): JsonResponse
    {
        $repo = $this->entityManager->getRepository(Repo::class)->find($projectId);
        if (!$repo) {
            return new JsonResponse(['error' => 'Proyecto no encontrado'], 404);
        }
        $colaboradores = $repo->getColaboradores();
        $data = [];
        foreach ($colaboradores as $user) {
            $data[] = [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),
            ];
        }
        return new JsonResponse($data);
    }


}
