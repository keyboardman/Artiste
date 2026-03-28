<?php

namespace App\Controller;

use App\Entity\Article;
use App\Repository\ArticleRepository;
use App\Service\CartService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/user')]
class UserController extends AbstractController
{
    
    #[Route('/profile', name: 'app_profile')]
    public function profile(): Response
    {
        $user = $this->getUser();

        return $this->render('public/profile.html.twig', [
            'user' => $user,
            // 'boards' => $user->getBoards(),
            // 'pins' => $user->getPins(),
            // 'services' => $user->getServices(),
        ]);
    }

    #[Route('/article/upload', name: 'app_article_upload', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function articleUpload(Request $request, EntityManagerInterface $em): Response
    {
        $file = $request->files->get('image');

        if (!$file) {
            $this->addFlash('error', 'Veuillez sélectionner une image.');
            return $this->redirectToRoute('app_profile');
        }

        if ($file->getSize() > 10 * 1024 * 1024) {
            $this->addFlash('error', 'L\'image dépasse 10 Mo.');
            return $this->redirectToRoute('app_profile');
        }

        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file->getMimeType(), $allowed)) {
            $this->addFlash('error', 'Format non supporté. Utilisez JPG, PNG, GIF ou WebP.');
            return $this->redirectToRoute('app_profile');
        }

        $filename = uniqid('article_') . '.' . $file->guessExtension();
        $file->move($this->getParameter('kernel.project_dir') . '/public/uploads/articles', $filename);

        $article = new Article();
        $article->setTitle($request->request->get('title', 'Sans titre'));
        $article->setDescription($request->request->get('description', ''));
        $article->setImage('uploads/articles/' . $filename);
        $rawPrice = trim($request->request->get('price', ''));
        $article->setPrice($rawPrice !== '' ? str_replace(',', '.', $rawPrice) : '0');
        $article->setCategory($request->request->get('category') ?: null);
        $article->setUser($this->getUser());

        $em->persist($article);
        $em->flush();

        $this->addFlash('success', 'Votre œuvre a été publiée !');
        return $this->redirectToRoute('app_profile');
    }

    #[Route('/article/delete/{id}', name: 'app_article_delete', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function articleDelete(int $id, ArticleRepository $articleRepository, EntityManagerInterface $em, Request $request): Response
    {
        $article = $articleRepository->find($id);

        if ($article && $article->getUser() === $this->getUser()
            && $this->isCsrfTokenValid('delete_article_' . $id, $request->request->get('_token'))) {
            $em->remove($article);
            $em->flush();
            $this->addFlash('success', 'Œuvre supprimée.');
        }

        return $this->redirectToRoute('app_profile');
    }

    #[Route('/profile/edit', name: 'app_profile_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function profileEdit(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): Response
    {
        $user = $this->getUser();

        if ($request->isMethod('POST')) {
            $user->setUsername($request->request->get('username', $user->getUsername()));
            $user->setFirstname($request->request->get('firstname', $user->getFirstname()));
            $user->setLastname($request->request->get('lastname', $user->getLastname()));
            $user->setBio($request->request->get('bio', $user->getBio()));

            $email = trim($request->request->get('email', ''));
            if ($email && $email !== $user->getEmail()) {
                $user->setEmail($email);
            }

            $newPassword = $request->request->get('new_password', '');
            if ($newPassword !== '') {
                $user->setPassword($hasher->hashPassword($user, $newPassword));
            }

            $avatarFile = $request->files->get('avatar');
            if ($avatarFile) {
                $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (in_array($avatarFile->getMimeType(), $allowed) && $avatarFile->getSize() <= 5 * 1024 * 1024) {
                    $filename = uniqid('avatar_') . '.' . $avatarFile->guessExtension();
                    $avatarFile->move($this->getParameter('kernel.project_dir') . '/public/uploads/avatars', $filename);
                    $user->setAvatar('uploads/avatars/' . $filename);
                }
            }

            $em->flush();
            $this->addFlash('success', 'Profil mis à jour avec succès !');
            return $this->redirectToRoute('app_profile');
        }

        return $this->render('public/profile_edit.html.twig', [
            'user' => $user,
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
