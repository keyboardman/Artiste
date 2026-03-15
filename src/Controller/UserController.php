<?php

namespace App\Controller;

use App\Repository\ArticleRepository;
use App\Service\CartService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/user')]
class UserController extends AbstractController
{
    

    #[Route('/profile/edit', name: 'app_profile_edit')]
    #[IsGranted('ROLE_USER')]
    public function profileEdit(Request $request): Response
    {
        $user = $this->getUser();

        // TODO: Créer et gérer le formulaire d'édition
        // $form = $this->createForm(ProfileType::class, $user);
        // $form->handleRequest($request);

        // if ($form->isSubmitted() && $form->isValid()) {
        //     $entityManager->flush();
        //     $this->addFlash('success', 'Profil mis à jour !');
        //     return $this->redirectToRoute('app_profile');
        // }

        return $this->render('public/profile_edit.html.twig', [
            'user' => $user,
            // 'form' => $form,
        ]);
    }

    #[Route('/profile/{id}', name: 'app_profile_public', requirements: ['id' => '\d+'])]
    public function profilePublic(int $id): Response
    {
        // TODO: Récupérer l'utilisateur depuis la base de données
        // $user = $userRepository->find($id);
        // if (!$user) {
        //     throw $this->createNotFoundException('Utilisateur non trouvé');
        // }

        return $this->render('public/profile.html.twig', [
            // 'user' => $user,
            // 'boards' => $user->getBoards(),
        ]);
    }

    #[Route('/cart', name: 'app_cart')]
    public function cart(Request $request): Response
    {
        // TODO: Récupérer le panier depuis la session
        // $cart = $cartService->getCart();

        return $this->render('public/cart.html.twig', [
            // 'cart' => $cart,
        ]);
    }

    #[Route('/cart/add/{id}', name: 'app_cart_add', methods: ['POST'])]
    public function cartAdd(int $id, Request $request, CartService $cartService, ArticleRepository $articleRepository): Response
    {
        $article = $articleRepository->find($id);

        if ($article) {
            $cartService->add($article);
        }

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse($cartService->getCart());
        }

        $referer = $request->headers->get('referer');
        return $this->redirect($referer ?: $this->generateUrl('app_shop'));
    }

    #[Route('/cart/remove/{id}', name: 'app_cart_remove', methods: ['POST'])]
    public function cartRemove(int $id, CartService $cartService, Request $request): Response
    {
        $cartService->remove($id);

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse($cartService->getCart());
        }

        $referer = $request->headers->get('referer');
        return $this->redirect($referer ?: $this->generateUrl('app_shop'));
    }

    #[Route('/checkout', name: 'app_checkout')]
    #[IsGranted('ROLE_USER')]
    public function checkout(): Response
    {
        // TODO: Afficher la page de paiement
        // $cart = $cartService->getCart();

        return $this->render('public/checkout.html.twig', [
            // 'cart' => $cart,
        ]);
    }

    #[Route('/orders', name: 'app_orders')]
    #[IsGranted('ROLE_USER')]
    public function orders(): Response
    {
        $user = $this->getUser();

        // TODO: Récupérer les commandes de l'utilisateur
        // $orders = $orderRepository->findByUser($user);

        return $this->render('public/orders.html.twig', [
            // 'orders' => $orders,
        ]);
    }
}
