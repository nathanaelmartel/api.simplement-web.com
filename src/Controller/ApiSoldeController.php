<?php

namespace App\Controller;

use App\Entity\Solde;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class ApiSoldeController extends AbstractController
{
    #[Route('/solde', name: 'api_solde')]
    public function index()
    {
        $datas = [];

        $em = $this->getDoctrine()->getManager();
        $soldes = $em->getRepository(Solde::class)->findBy([
            'annee' => [
                (int) date('Y'),
                (int) date('Y') + 1,
            ],
        ], ['start_at' => 'ASC']);

        foreach ($soldes as $solde) {
            $datas[] = [
                'annee' => $solde->getAnnee(),
                'description' => $solde->getName(),
                'departement' => $solde->getDepartement()->getCode(),
                'start_at' => $solde->getStartAt()->format('Y-m-d H:i:s'),
                'end_at' => $solde->getEndAt()->format('Y-m-d H:i:s'),
            ];
        }

        return new JsonResponse($datas);
    }

    #[Route('/solde/{year}', name: 'api_solde_year')]
    public function year(int $year)
    {
        $datas = [];

        $em = $this->getDoctrine()->getManager();
        $soldes = $em->getRepository(Solde::class)->findBy([
            'annee' => $year,
        ], ['start_at' => 'ASC']);

        foreach ($soldes as $solde) {
            $datas[] = [
                'annee' => $solde->getAnnee(),
                'description' => $solde->getName(),
                'departement' => $solde->getDepartement()->getCode(),
                'start_at' => $solde->getStartAt()->format('Y-m-d H:i:s'),
                'end_at' => $solde->getEndAt()->format('Y-m-d H:i:s'),
            ];
        }

        return new JsonResponse($datas);
    }

    #[Route('/solde/{year}/{departement}', name: 'api_solde_departement')]
    public function departement(int $year, string $departement)
    {
        $datas = [];

        $em = $this->getDoctrine()->getManager();
        $soldes = $em->getRepository(Solde::class)->findBy([
            'annee' => $year,
            'departement' => $departement,
        ], ['start_at' => 'ASC']);

        foreach ($soldes as $solde) {
            $datas[] = [
                'annee' => $solde->getAnnee(),
                'description' => $solde->getName(),
                'departement' => $solde->getDepartement()->getCode(),
                'start_at' => $solde->getStartAt()->format('Y-m-d H:i:s'),
                'end_at' => $solde->getEndAt()->format('Y-m-d H:i:s'),
            ];
        }

        return new JsonResponse($datas);
    }
}
