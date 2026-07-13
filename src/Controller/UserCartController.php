<?php

namespace App\Controller;

use App\Repository\ArticleRepository;
use App\Service\CartService;
use App\Service\OrderService;
use App\Service\SettingsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Toute la logique liée au panier et au passage de commande de l'utilisateur.
 */
#[Route('/cart')]
class UserCartController extends AbstractController
{
    #[Route('', name: 'app_cart')]
    public function cart(CartService $cartService): Response
    {
        return $this->render('public/cart.html.twig', [
            'cart' => $cartService->getCart(),
        ]);
    }

    #[Route('/add/{id}', name: 'app_cart_add', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function cartAdd(int $id, Request $request, CartService $cartService, ArticleRepository $articleRepository): Response
    {
        $article = $articleRepository->find($id);

        if ($article) {
            $cartService->add([
                'id'    => $article->getId(),
                'title' => $article->getTitle(),
                'price' => $article->getPrice(),
                'image' => $article->getImage(),
            ]);
        }

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse($cartService->getCart());
        }

        $referer = $request->headers->get('referer');
        return $this->redirect($referer ?: $this->generateUrl('app_shop'));
    }

    #[Route('/remove/{id}', name: 'app_cart_remove', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function cartRemove(int $id, CartService $cartService, Request $request): Response
    {
        $cartService->remove($id);

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse($cartService->getCart());
        }

        $referer = $request->headers->get('referer');
        return $this->redirect($referer ?: $this->generateUrl('app_shop'));
    }

    #[Route('/checkout', name: 'app_checkout', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function checkout(
        Request $request,
        CartService $cartService,
        OrderService $orderService,
        SettingsService $settings,
    ): Response {
        $cart = $cartService->getCart();

        if (empty($cart['items'])) {
            $this->addFlash('warning', 'Votre panier est vide.');
            return $this->redirectToRoute('app_shop');
        }

        $shippingFee = (float) ($settings->get('shipping_fee', '0') ?? '0');

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('checkout', $request->request->get('_token'))) {
                $this->addFlash('error', 'Token de sécurité invalide.');
                return $this->redirectToRoute('app_checkout');
            }

            $order = $orderService->createFromCart($this->getUser());

            if (!$order) {
                $this->addFlash('error', 'Impossible de créer la commande.');
                return $this->redirectToRoute('app_cart');
            }

            $this->addFlash('success', 'Commande #'.$order->getReference().' confirmée !');
            return $this->redirectToRoute('app_orders');
        }

        return $this->render('public/checkout.html.twig', [
            'cart'         => $cart,
            'shipping_fee' => $shippingFee,
            'grand_total'  => $cart['total'] + $shippingFee,
        ]);
    }
}
