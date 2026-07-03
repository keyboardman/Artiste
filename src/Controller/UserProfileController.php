<?php

namespace App\Controller;

use App\Entity\Order;
use App\Repository\OrderRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Espace utilisateur (côté acheteur) : commandes, et plus tard adresses
 * de livraison / facturation.
 */
#[Route('/compte')]
#[IsGranted('ROLE_USER')]
class UserProfileController extends AbstractController
{
    #[Route('/commandes', name: 'app_orders')]
    public function orders(OrderRepository $orderRepository): Response
    {
        return $this->render('public/orders.html.twig', [
            'orders' => $orderRepository->findByUser($this->getUser()),
        ]);
    }

    #[Route('/commandes/{id}', name: 'app_order_show', requirements: ['id' => '\d+'])]
    public function orderShow(Order $order): Response
    {
        if ($order->getUser()?->getId() !== $this->getUser()->getId()) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('public/order_show.html.twig', [
            'order' => $order,
        ]);
    }

    // TODO : adresses de livraison + facturation (entité Address à créer)
}
