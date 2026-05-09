<?php

namespace App\Controller;

use App\Entity\Article;
use App\Entity\Order;
use App\Form\ArticleUploadType;
use App\Repository\OrderRepository;
use App\Service\ApiClientService;
use App\Service\CartService;
use App\Service\OrderService;
use App\Service\SettingsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/user')]
class UserController extends AbstractController
{
    #[Route('/article/upload', name: 'app_article_upload', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function articleUpload(Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(ArticleUploadType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $file = $form->get('image')->getData();

            $filename = uniqid('article_') . '.' . $file->guessExtension();
            $file->move($this->getParameter('kernel.project_dir') . '/public/uploads/articles', $filename);

            $rawPrice = trim($form->get('price')->getData() ?? '');

            $article = new Article();
            $article->setTitle($form->get('title')->getData());
            $article->setDescription($form->get('description')->getData() ?? '');
            $article->setImage('uploads/articles/' . $filename);
            $article->setPrice($rawPrice !== '' ? str_replace(',', '.', $rawPrice) : '0');
            $article->setCategoryEntity($form->get('category')->getData());
            $article->setUser($this->getUser());

            $em->persist($article);
            $em->flush();

            $this->addFlash('success', 'Votre œuvre a été publiée !');
        } else {
            foreach ($form->getErrors(true) as $error) {
                $this->addFlash('error', $error->getMessage());
            }
        }

        return $this->redirectToRoute('app_profile');
    }

    #[Route('/article/delete/{id}', name: 'app_article_delete', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function articleDelete(int $id, ApiClientService $api, Request $request): Response
    {
        $article = $api->getArticle($id);
        $currentUserId = $this->getUser()->getId();

        if ($article
            && isset($article['user']['id'])
            && $article['user']['id'] === $currentUserId
            && $this->isCsrfTokenValid('delete_article_' . $id, $request->request->get('_token'))
        ) {
            $api->deleteArticle($id);
            $this->addFlash('success', 'Œuvre supprimée.');
        }

        return $this->redirectToRoute('app_profile');
    }

    #[Route('/profile/edit', name: 'app_profile_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function profileEdit(Request $request, ApiClientService $api): Response
    {
        $user = $this->getUser();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('profile_edit', $request->request->get('_token'))) {
                $this->addFlash('error', 'Token de sécurité invalide.');
                return $this->redirectToRoute('app_profile_edit');
            }

            $updateData = [
                'username'  => $request->request->get('username', $user->getUsername()),
                'firstname' => $request->request->get('firstname', $user->getFirstname()),
                'lastname'  => $request->request->get('lastname', $user->getLastname()),
                'bio'       => $request->request->get('bio', $user->getBio()),
                'social'    => $request->request->get('social', $user->getSocial()) ?: null,
                'phone'     => $request->request->get('phone', $user->getPhone()) ?: null,
                'email'     => $user->getEmail(),
                'avatar'    => $user->getAvatar(),
            ];

            $email = trim($request->request->get('email', ''));
            if ($email && $email !== $user->getEmail()) {
                $updateData['email'] = $email;
            }

            $newPassword = $request->request->get('new_password', '');
            if ($newPassword !== '') {
                $updateData['plainPassword'] = $newPassword;
            }

            $avatarFile = $request->files->get('avatar');
            if ($avatarFile && $avatarFile->isValid()) {
                $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (in_array($avatarFile->getMimeType(), $allowed) && $avatarFile->getSize() <= 5 * 1024 * 1024) {
                    $filename = uniqid('avatar_') . '.' . $avatarFile->guessExtension();
                    $avatarFile->move($this->getParameter('kernel.project_dir') . '/public/uploads/avatars', $filename);
                    $updateData['avatar'] = 'uploads/avatars/' . $filename;
                }
            }

            $api->updateUser($user->getId(), $updateData);

            $this->addFlash('success', 'Profil mis à jour avec succès !');
            return $this->redirectToRoute('app_profile');
        }

        return $this->render('public/profile_edit.html.twig', [
            'user' => $user,
        ]);
    }


    #[Route('/cart', name: 'app_cart')]
    public function cart(CartService $cartService): Response
    {
        return $this->render('public/cart.html.twig', [
            'cart' => $cartService->getCart(),
        ]);
    }

    #[Route('/cart/add/{id}', name: 'app_cart_add', methods: ['POST'])]
    public function cartAdd(int $id, Request $request, CartService $cartService, ApiClientService $api): Response
    {
        $article = $api->getArticle($id);

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

    #[Route('/orders', name: 'app_orders')]
    #[IsGranted('ROLE_USER')]
    public function orders(OrderRepository $orderRepository): Response
    {
        return $this->render('public/orders.html.twig', [
            'orders' => $orderRepository->findByUser($this->getUser()),
        ]);
    }

    #[Route('/orders/{id}', name: 'app_order_show', requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function orderShow(Order $order): Response
    {
        if ($order->getUser()?->getId() !== $this->getUser()->getId()) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('public/order_show.html.twig', [
            'order' => $order,
        ]);
    }
}
