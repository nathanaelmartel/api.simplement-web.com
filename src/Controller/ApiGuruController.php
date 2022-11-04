<?php

namespace App\Controller;

use App\Service\RestaurantGuru;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ApiGuruController extends AbstractController
{
    #[Route('/guru/{url}/{nb_pages}', name: 'api_guru')]
    public function index(string $url, RestaurantGuru $guru, int $nb_pages = 1): Response
    {
        $url = base64_decode($url);

        $reviews = $guru->getReviews($url, $nb_pages);

        return new JsonResponse($reviews);
    }
}
