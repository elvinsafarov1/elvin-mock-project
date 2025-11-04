<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\JavaServiceClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\StatusCode;

class UserController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private JavaServiceClient $javaServiceClient;
    private TracerInterface $tracer;

    public function __construct(
        EntityManagerInterface $entityManager,
        JavaServiceClient $javaServiceClient
    ) {
        $this->entityManager = $entityManager;
        $this->javaServiceClient = $javaServiceClient;
        $this->tracer = Globals::tracerProvider()->getTracer('php-backend');
    }

    #[Route('/api/users', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $span = $this->tracer->spanBuilder('get_users')
            ->setSpanKind(SpanKind::KIND_SERVER)
            ->startSpan();
        
        $scope = $span->activate();
        
        try {
            $users = $this->entityManager->getRepository(User::class)->findAll();
            
            $span->setAttribute('users.count', count($users));
            
            return $this->json(array_map(function($user) {
                return [
                    'id' => $user->getId(),
                    'name' => $user->getName(),
                    'email' => $user->getEmail(),
                ];
            }, $users));
        } finally {
            $span->end();
            $scope->detach();
        }
    }

    #[Route('/api/users/{id}', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $span = $this->tracer->spanBuilder('get_user')
            ->setSpanKind(SpanKind::KIND_SERVER)
            ->setAttribute('user.id', $id)
            ->startSpan();
        
        $scope = $span->activate();
        
        try {
            $user = $this->entityManager->getRepository(User::class)->find($id);
            
            if (!$user) {
                $span->setStatus(StatusCode::STATUS_ERROR, 'User not found');
                return $this->json(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
            }
            
            // Call Java service for additional data
            $externalData = $this->javaServiceClient->getUserData($id);
            
            return $this->json([
                'id' => $user->getId(),
                'name' => $user->getName(),
                'email' => $user->getEmail(),
                'external_data' => $externalData,
            ]);
        } finally {
            $span->end();
            $scope->detach();
        }
    }

    #[Route('/api/users', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $span = $this->tracer->spanBuilder('create_user')
            ->setSpanKind(SpanKind::KIND_SERVER)
            ->startSpan();
        
        $scope = $span->activate();
        
        try {
            $data = json_decode($request->getContent(), true);
            
            $user = new User();
            $user->setName($data['name'] ?? '');
            $user->setEmail($data['email'] ?? '');
            
            $this->entityManager->persist($user);
            $this->entityManager->flush();
            
            $span->setAttribute('user.id', $user->getId());
            $span->setAttribute('user.name', $user->getName());
            
            return $this->json([
                'id' => $user->getId(),
                'name' => $user->getName(),
                'email' => $user->getEmail(),
            ], Response::HTTP_CREATED);
        } finally {
            $span->end();
            $scope->detach();
        }
    }
}

