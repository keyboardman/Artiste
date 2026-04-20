<?php

namespace App\Controller;

use App\Entity\Article;
use App\Entity\User;
use App\Form\ArticleType;
use App\Form\UserType;
use App\Service\ApiClientService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    #[Route('/', name: 'app_admin_dashboard')]
    public function dashboard(ApiClientService $api): Response
    {
        $users = $api->getUsers(['order[id]' => 'DESC']);
        $articles = $api->getArticles();

        $stats = [
            'users'        => count($users),
            'articles'     => count($articles),
            'recent_users' => array_slice($users, 0, 5),
        ];

        return $this->render('admin/dashboard.html.twig', [
            'stats' => $stats,
        ]);
    }

    // ===== GESTION DES UTILISATEURS =====

    #[Route('/users', name: 'app_admin_users')]
    public function users(ApiClientService $api): Response
    {
        $users = $api->getUsers();

        return $this->render('admin/users/index.html.twig', [
            'users' => $users,
        ]);
    }

    #[Route('/users/{id}', name: 'app_admin_user_show', requirements: ['id' => '\d+'])]
    public function userShow(User $user): Response
    {
        return $this->render('admin/users/show.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/users/{id}/edit', name: 'app_admin_user_edit', requirements: ['id' => '\d+'])]
    public function userEdit(User $user, Request $request, ApiClientService $api): Response
    {
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $api->updateUser($user->getId(), [
                'email'     => $user->getEmail(),
                'firstname' => $user->getFirstname(),
                'lastname'  => $user->getLastname(),
                'username'  => $user->getUsername(),
                'bio'       => $user->getBio(),
                'avatar'    => $user->getAvatar(),
                'roles'     => $user->getRoles(),
            ]);

            $this->addFlash('success', 'L\'utilisateur a été modifié avec succès.');

            return $this->redirectToRoute('app_admin_users');
        }

        return $this->render('admin/users/edit.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/users/{id}/delete', name: 'app_admin_user_delete', methods: ['POST'])]
    public function userDelete(User $user, Request $request, ApiClientService $api): Response
    {
        if ($this->isCsrfTokenValid('delete'.$user->getId(), $request->request->get('_token'))) {
            $api->deleteUser($user->getId());

            $this->addFlash('success', 'L\'utilisateur a été supprimé avec succès.');
        }

        return $this->redirectToRoute('app_admin_users');
    }

    // ===== GESTION DES ARTICLES =====

    #[Route('/articles', name: 'app_admin_articles')]
    public function articles(ApiClientService $api): Response
    {
        $articles = $api->getArticles();

        return $this->render('admin/articles/index.html.twig', [
            'articles' => $articles,
        ]);
    }

    #[Route('/articles/new', name: 'app_admin_article_new')]
    public function articleNew(Request $request, ApiClientService $api): Response
    {
        $article = new Article();
        $form = $this->createForm(ArticleType::class, $article);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $api->createArticle([
                'title'       => $article->getTitle(),
                'description' => $article->getDescription(),
                'image'       => $article->getImage(),
                'price'       => $article->getPrice(),
                'stock'       => $article->getStock(),
                'category'    => $article->getCategory(),
            ]);

            $this->addFlash('success', 'L\'article a été créé avec succès.');

            return $this->redirectToRoute('app_admin_articles');
        }

        return $this->render('admin/articles/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/articles/{id}/edit', name: 'app_admin_article_edit', requirements: ['id' => '\d+'])]
    public function articleEdit(Article $article, Request $request, ApiClientService $api): Response
    {
        $form = $this->createForm(ArticleType::class, $article);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $api->updateArticle($article->getId(), [
                'title'       => $article->getTitle(),
                'description' => $article->getDescription(),
                'image'       => $article->getImage(),
                'price'       => $article->getPrice(),
                'stock'       => $article->getStock(),
                'category'    => $article->getCategory(),
            ]);

            $this->addFlash('success', 'L\'article a été modifié avec succès.');

            return $this->redirectToRoute('app_admin_articles');
        }

        return $this->render('admin/articles/edit.html.twig', [
            'article' => $article,
            'form'    => $form,
        ]);
    }

    #[Route('/articles/{id}/delete', name: 'app_admin_article_delete', methods: ['POST'])]
    public function articleDelete(Article $article, Request $request, ApiClientService $api): Response
    {
        if ($this->isCsrfTokenValid('delete'.$article->getId(), $request->request->get('_token'))) {
            $api->deleteArticle($article->getId());

            $this->addFlash('success', 'L\'article a été supprimé avec succès.');
        }

        return $this->redirectToRoute('app_admin_articles');
    }

    // ===== GESTION DES COMMANDES =====

    #[Route('/orders', name: 'app_admin_orders')]
    public function orders(): Response
    {
        return $this->render('admin/orders/index.html.twig');
    }

    #[Route('/orders/{id}', name: 'app_admin_order_show', requirements: ['id' => '\d+'])]
    public function orderShow(int $id): Response
    {
        return $this->render('admin/orders/show.html.twig');
    }

    #[Route('/orders/{id}/status', name: 'app_admin_order_status', methods: ['POST'])]
    public function orderStatus(int $id, Request $request): Response
    {
        return $this->redirectToRoute('app_admin_order_show', ['id' => $id]);
    }

    // ===== GESTION DES CATÉGORIES =====

    #[Route('/categories', name: 'app_admin_categories')]
    public function categories(): Response
    {
        return $this->render('admin/categories/index.html.twig');
    }

    // ===== PARAMÈTRES =====

    #[Route('/settings', name: 'app_admin_settings')]
    public function settings(): Response
    {
        return $this->render('admin/settings.html.twig');
    }
}
