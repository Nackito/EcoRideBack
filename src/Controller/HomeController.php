<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home_index')]

    public function home(): Response

    {

        return $this->json([
            'message' => 'Bienvenue sur votre accueil !',
        ]);
    }
}
