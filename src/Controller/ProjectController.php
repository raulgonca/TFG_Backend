<?php

namespace App\Controller;

use App\Entity\Repo;
use App\Repository\RepoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api', name: 'api_')]
final class ProjectController extends AbstractController
{
    private $entityManager;
    private $repoRepository;

    public function __construct(EntityManagerInterface $entityManager, RepoRepository $repoRepository)
    {
        $this->entityManager = $entityManager;
        $this->repoRepository = $repoRepository;
    }

    #[Route('/user/{id}/projects', name: 'user_projects', methods: ['GET'])]
    public function getUserProjects(int $id): JsonResponse
    {
        $repos = $this->repoRepository->findBy(['owner' => $id]);

        $data = [];
        foreach ($repos as $repo) {
            $data[] = [
                'id' => $repo->getId(),
                'projectname' => $repo->getProjectname(),
                'fechaFin' => $repo->getFechaFin()?->format('Y-m-d'),
                // ...otros campos que necesites
            ];
        }
        return new JsonResponse($data);
    }

    #[Route('/user/{id}/collaborations', name: 'user_collaborations', methods: ['GET'])]
    public function getUserCollaborations(int $id): JsonResponse
    {
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
                // ...otros campos que necesites
            ];
        }
        return new JsonResponse($data);
    }
}