<?php

namespace App\Controller;

use App\Entity\Client;
use App\Repository\ClientRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[Route('/api', name: 'api_')]
final class ClientController extends AbstractController
{
    private $entityManager;
    private $clientRepository;

    public function __construct(EntityManagerInterface $entityManager, ClientRepository $clientRepository)
    {
        $this->entityManager = $entityManager;
        $this->clientRepository = $clientRepository;
    }

    #[Route('/clients', name: 'get_clients', methods: ['GET'])]
    public function getClients(): JsonResponse
    {
        $clients = $this->clientRepository->findAll();
        
        $clientsData = [];
        foreach ($clients as $client) {
            $clientsData[] = [
                'id' => $client->getId(),
                'name' => $client->getName(),
                'cif' => $client->getCif(),
                'email' => $client->getEmail(),
                'phone' => $client->getPhone(),
                'web' => $client->getWeb()
            ];
        }
        
        return new JsonResponse($clientsData);
    }

    #[Route('/clients/{id}', name: 'get_client', methods: ['GET'])]
    public function getClient(int $id): JsonResponse
    {
        $client = $this->clientRepository->find($id);
        
        if (!$client) {
            return new JsonResponse(['error' => 'Cliente no encontrado'], Response::HTTP_NOT_FOUND);
        }
        
        return new JsonResponse([
            'id' => $client->getId(),
            'name' => $client->getName(),
            'cif' => $client->getCif(),
            'email' => $client->getEmail(),
            'phone' => $client->getPhone(),
            'web' => $client->getWeb()
        ]);
    }

    #[Route('/createclient', name: 'create_client', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function createClient(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['name']) || !isset($data['cif'])) {
            return new JsonResponse(['error' => 'Faltan datos obligatorios'], Response::HTTP_BAD_REQUEST);
        }
        
        $client = new Client();
        $client->setName($data['name']);
        $client->setCif($data['cif']);
        
        if (isset($data['email'])) {
            $client->setEmail($data['email']);
        }
        
        if (isset($data['phone'])) {
            $client->setPhone($data['phone']);
        }
        
        if (isset($data['web'])) {
            $client->setWeb($data['web']);
        }
        
        $this->entityManager->persist($client);
        $this->entityManager->flush();
        
        return new JsonResponse([
            'id' => $client->getId(),
            'message' => 'Cliente creado con éxito'
        ], Response::HTTP_CREATED);
    }

    #[Route('/updateclient/{id}', name: 'update_client', methods: ['PUT'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function updateClient(Request $request, int $id): JsonResponse
    {
        $client = $this->clientRepository->find($id);

        if (!$client) {
            return new JsonResponse(['error' => 'Cliente no encontrado'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        // Comprobar si el nombre ya existe en otro cliente
        if (isset($data['name'])) {
            $existingName = $this->clientRepository->findOneBy(['name' => $data['name']]);
            if ($existingName && $existingName->getId() != $client->getId()) {
                return new JsonResponse(['error' => 'Ya existe un cliente con ese nombre'], Response::HTTP_CONFLICT);
            }
            $client->setName($data['name']);
        }

        // Comprobar si el CIF ya existe en otro cliente
        if (isset($data['cif'])) {
            $existingCif = $this->clientRepository->findOneBy(['cif' => $data['cif']]);
            if ($existingCif && $existingCif->getId() != $client->getId()) {
                return new JsonResponse(['error' => 'Ya existe un cliente con ese CIF'], Response::HTTP_CONFLICT);
            }
            $client->setCif($data['cif']);
        }

        if (isset($data['email'])) {
            $client->setEmail($data['email']);
        }
        
        if (isset($data['phone'])) {
            $client->setPhone($data['phone']);
        }
        
        if (isset($data['web'])) {
            $client->setWeb($data['web']);
        }
        
        $this->entityManager->flush();
        
        return new JsonResponse(['message' => 'Cliente actualizado con éxito']);
    }

    #[Route('/deleteclient/{id}', name: 'delete_client', methods: ['DELETE'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function deleteClient(int $id): JsonResponse
    {
        $client = $this->clientRepository->find($id);
        
        if (!$client) {
            return new JsonResponse(['error' => 'Cliente no encontrado'], Response::HTTP_NOT_FOUND);
        }
        
        $this->entityManager->remove($client);
        $this->entityManager->flush();
        
        return new JsonResponse(['message' => 'Cliente eliminado con éxito']);
    }

    /**
     * Exporta todos los clientes en formato CSV.
     * 
     * Funcionamiento:
     * - Cuando haces una petición GET a /api/clients/export,
     *   este endpoint genera un archivo CSV en tiempo real con todos los clientes de la base de datos.
     * - La primera fila es la cabecera (ID, Nombre, CIF, Email, Teléfono, Web).
     * - Cada fila siguiente es un cliente.
     * - El archivo se descarga automáticamente en el navegador.
     */
    #[Route('/clients/export', name: 'export_clients_csv', methods: ['GET'])]
    public function exportClientsCsv(): StreamedResponse
    {
        $response = new StreamedResponse();
        $response->setCallback(function () {
            $handle = fopen('php://output', 'w+');
            // Cabecera CSV
            fputcsv($handle, ['ID', 'Nombre', 'CIF', 'Email', 'Teléfono', 'Web']);
            foreach ($this->clientRepository->findAll() as $client) {
                fputcsv($handle, [
                    $client->getId(),
                    $client->getName(),
                    $client->getCif(),
                    $client->getEmail(),
                    $client->getPhone(),
                    $client->getWeb()
                ]);
            }
            fclose($handle);
        });
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="clientes.csv"');
        return $response;
    }

    /**
     * Importa clientes desde un archivo CSV.
     * Soporta cabeceras flexibles (con o sin ID, y cualquier orden).
     * Guarda correctamente el campo phone aunque el CSV tenga cabecera 'phone' o 'teléfono'.
     */
    #[Route('/clients/import', name: 'import_clients_csv', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function importClientsCsv(Request $request): JsonResponse
    {
        /** @var UploadedFile $file */
        $file = $request->files->get('file');
        if (!$file) {
            return new JsonResponse(['error' => 'No se ha enviado ningún archivo'], Response::HTTP_BAD_REQUEST);
        }

        $handle = fopen($file->getPathname(), 'r');
        if (!$handle) {
            return new JsonResponse(['error' => 'No se pudo abrir el archivo'], Response::HTTP_BAD_REQUEST);
        }

        $header = fgetcsv($handle);
        // Mapea las cabeceras a su índice (corrige posibles espacios y mayúsculas)
        $map = [];
        foreach ($header as $i => $col) {
            $colNorm = strtolower(trim($col));
            // Soporta 'phone', 'teléfono', 'telefono'
            if (in_array($colNorm, ['phone', 'teléfono', 'telefono'])) {
                $map['phone'] = $i;
            } else {
                $map[$colNorm] = $i;
            }
        }

        $imported = 0;
        $skipped = 0;
        while (($row = fgetcsv($handle)) !== false) {
            $name = isset($map['nombre']) ? $row[$map['nombre']] : null;
            $cif = isset($map['cif']) ? $row[$map['cif']] : null;
            $email = isset($map['email']) ? $row[$map['email']] : null;
            $phone = isset($map['phone']) ? $row[$map['phone']] : null;
            $web = isset($map['web']) ? $row[$map['web']] : null;

            if (!$name || !$cif) {
                $skipped++;
                continue;
            }

            // Evita duplicados por CIF
            $existing = $this->clientRepository->findOneBy(['cif' => $cif]);
            if ($existing) {
                $skipped++;
                continue;
            }

            $client = new Client();
            $client->setName($name);
            $client->setCif($cif);
            $client->setEmail($email ?? '');
            $client->setPhone($phone ?? '');
            $client->setWeb($web ?? '');

            $this->entityManager->persist($client);
            $imported++;
        }
        fclose($handle);
        $this->entityManager->flush();

        return new JsonResponse([
            'message' => "Importación completada",
            'importados' => $imported,
            'omitidos' => $skipped
        ]);
    }
}
