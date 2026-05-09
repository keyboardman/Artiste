<?php

namespace App\Controller;

use App\Entity\Article;
use App\Entity\ArticleImage;
use App\Entity\Category;
use App\Entity\Order;
use App\Entity\User;
use App\Enum\OrderStatus;
use App\Form\ArticleType;
use App\Form\CategoryType;
use App\Form\UserType;
use App\Repository\ArticleRepository;
use App\Repository\CategoryRepository;
use App\Repository\OrderItemRepository;
use App\Repository\OrderRepository;
use App\Repository\UserRepository;
use App\Service\ApiClientService;
use App\Service\SettingsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    #[Route('/', name: 'app_admin_dashboard')]
    public function dashboard(
        UserRepository $userRepository,
        ArticleRepository $articleRepository,
        OrderRepository $orderRepository,
        OrderItemRepository $orderItemRepository,
    ): Response {
        $countByStatus = $orderRepository->countByStatus();

        $stats = [
            'users'         => $userRepository->count([]),
            'articles'      => $articleRepository->count([]),
            'orders'        => $orderRepository->countAll(),
            'revenue'       => $orderRepository->getRevenue(),
            'pending'       => $countByStatus[OrderStatus::Pending->value]   ?? 0,
            'paid'          => $countByStatus[OrderStatus::Paid->value]      ?? 0,
            'shipped'       => $countByStatus[OrderStatus::Shipped->value]   ?? 0,
            'delivered'     => $countByStatus[OrderStatus::Delivered->value] ?? 0,
            'cancelled'     => $countByStatus[OrderStatus::Cancelled->value] ?? 0,
            'recent_users'  => $userRepository->findBy([], ['id' => 'DESC'], 5),
            'recent_orders' => $orderRepository->findRecent(5),
            'top_articles'  => $orderItemRepository->findTopArticles(5),
        ];

        return $this->render('admin/dashboard.html.twig', [
            'stats' => $stats,
        ]);
    }

    // ===== UTILISATEURS =====

    #[Route('/users', name: 'app_admin_users')]
    public function users(ApiClientService $api): Response
    {
        return $this->render('admin/users/index.html.twig', [
            'users' => $api->getUsers(),
        ]);
    }

    #[Route('/users/{id}', name: 'app_admin_user_show', requirements: ['id' => '\d+'])]
    public function userShow(User $user): Response
    {
        return $this->render('admin/users/show.html.twig', ['user' => $user]);
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

        return $this->render('admin/users/edit.html.twig', ['user' => $user, 'form' => $form]);
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

    // ===== ARTICLES =====

    #[Route('/articles', name: 'app_admin_articles')]
    public function articles(ArticleRepository $articleRepository): Response
    {
        return $this->render('admin/articles/index.html.twig', [
            'articles' => $articleRepository->findBy([], ['createdAt' => 'DESC']),
        ]);
    }

    #[Route('/articles/new', name: 'app_admin_article_new')]
    public function articleNew(Request $request, EntityManagerInterface $em): Response
    {
        $article = new Article();
        $form = $this->createForm(ArticleType::class, $article);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($article);
            $em->flush();

            $this->addFlash('success', 'L\'article a été créé avec succès.');
            return $this->redirectToRoute('app_admin_articles');
        }

        return $this->render('admin/articles/new.html.twig', ['form' => $form]);
    }

    #[Route('/articles/{id}/edit', name: 'app_admin_article_edit', requirements: ['id' => '\d+'])]
    public function articleEdit(Article $article, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(ArticleType::class, $article);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'L\'article a été modifié avec succès.');
            return $this->redirectToRoute('app_admin_articles');
        }

        return $this->render('admin/articles/edit.html.twig', ['article' => $article, 'form' => $form]);
    }

    #[Route('/articles/{id}/delete', name: 'app_admin_article_delete', methods: ['POST'])]
    public function articleDelete(Article $article, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$article->getId(), $request->request->get('_token'))) {
            $em->remove($article);
            $em->flush();

            $this->addFlash('success', 'L\'article a été supprimé avec succès.');
        }

        return $this->redirectToRoute('app_admin_articles');
    }

    #[Route('/articles/{id}/images/add', name: 'app_admin_article_image_add', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function articleImageAdd(Article $article, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('article_image_add_'.$article->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToRoute('app_admin_article_edit', ['id' => $article->getId()]);
        }

        $files = $request->files->all('images');
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $position = count($article->getImages());
        $added = 0;

        foreach ($files as $file) {
            if (!$file || !$file->isValid()) {
                continue;
            }
            if (!in_array($file->getMimeType(), $allowed, true) || $file->getSize() > 10 * 1024 * 1024) {
                continue;
            }

            $filename = uniqid('article_img_').'.'.$file->guessExtension();
            $file->move($this->getParameter('kernel.project_dir').'/public/uploads/articles', $filename);

            $img = new ArticleImage();
            $img->setArticle($article);
            $img->setPath('uploads/articles/'.$filename);
            $img->setPosition($position++);
            $em->persist($img);
            $added++;
        }

        if ($added > 0) {
            $em->flush();
            $this->addFlash('success', $added.' image(s) ajoutée(s).');
        } else {
            $this->addFlash('warning', 'Aucune image valide.');
        }

        return $this->redirectToRoute('app_admin_article_edit', ['id' => $article->getId()]);
    }

    #[Route('/articles/images/{id}/delete', name: 'app_admin_article_image_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function articleImageDelete(ArticleImage $image, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('article_image_delete_'.$image->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToRoute('app_admin_articles');
        }

        $articleId = $image->getArticle()?->getId();
        $em->remove($image);
        $em->flush();

        $this->addFlash('success', 'Image supprimée.');
        return $articleId
            ? $this->redirectToRoute('app_admin_article_edit', ['id' => $articleId])
            : $this->redirectToRoute('app_admin_articles');
    }

    // ===== COMMANDES =====

    #[Route('/orders', name: 'app_admin_orders')]
    public function orders(OrderRepository $orderRepository): Response
    {
        return $this->render('admin/orders/index.html.twig', [
            'orders' => $orderRepository->findAllOrdered(),
        ]);
    }

    #[Route('/orders/{id}', name: 'app_admin_order_show', requirements: ['id' => '\d+'])]
    public function orderShow(Order $order): Response
    {
        return $this->render('admin/orders/show.html.twig', [
            'order'    => $order,
            'statuses' => OrderStatus::cases(),
        ]);
    }

    #[Route('/orders/{id}/status', name: 'app_admin_order_status', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function orderStatus(Order $order, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('order_status_'.$order->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToRoute('app_admin_order_show', ['id' => $order->getId()]);
        }

        $status = OrderStatus::tryFrom((string) $request->request->get('status', ''));
        if (!$status) {
            $this->addFlash('error', 'Statut inconnu.');
            return $this->redirectToRoute('app_admin_order_show', ['id' => $order->getId()]);
        }

        $order->setStatus($status);
        $em->flush();

        $this->addFlash('success', 'Statut de la commande mis à jour.');
        return $this->redirectToRoute('app_admin_order_show', ['id' => $order->getId()]);
    }

    #[Route('/orders/{id}/delete', name: 'app_admin_order_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function orderDelete(Order $order, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete_order_'.$order->getId(), $request->request->get('_token'))) {
            $em->remove($order);
            $em->flush();
            $this->addFlash('success', 'Commande supprimée.');
        }

        return $this->redirectToRoute('app_admin_orders');
    }

    // ===== CATÉGORIES =====

    #[Route('/categories', name: 'app_admin_categories')]
    public function categories(CategoryRepository $categoryRepository): Response
    {
        return $this->render('admin/categories/index.html.twig', [
            'categories' => $categoryRepository->findAllOrdered(),
        ]);
    }

    #[Route('/categories/new', name: 'app_admin_category_new')]
    public function categoryNew(Request $request, EntityManagerInterface $em): Response
    {
        $category = new Category();
        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($category);
            $em->flush();

            $this->addFlash('success', 'Catégorie créée.');
            return $this->redirectToRoute('app_admin_categories');
        }

        return $this->render('admin/categories/new.html.twig', ['form' => $form]);
    }

    #[Route('/categories/{id}/edit', name: 'app_admin_category_edit', requirements: ['id' => '\d+'])]
    public function categoryEdit(Category $category, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Catégorie mise à jour.');
            return $this->redirectToRoute('app_admin_categories');
        }

        return $this->render('admin/categories/edit.html.twig', ['form' => $form, 'category' => $category]);
    }

    #[Route('/categories/{id}/delete', name: 'app_admin_category_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function categoryDelete(Category $category, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete_category_'.$category->getId(), $request->request->get('_token'))) {
            $em->remove($category);
            $em->flush();
            $this->addFlash('success', 'Catégorie supprimée.');
        }

        return $this->redirectToRoute('app_admin_categories');
    }

    // ===== PARAMÈTRES =====

    #[Route('/settings', name: 'app_admin_settings', methods: ['GET', 'POST'])]
    public function settings(Request $request, SettingsService $settings): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('admin_settings', $request->request->get('_token'))) {
                $this->addFlash('error', 'Token de sécurité invalide.');
                return $this->redirectToRoute('app_admin_settings');
            }

            $settings->setMany([
                'site_name'        => trim((string) $request->request->get('site_name', '')),
                'site_email'       => trim((string) $request->request->get('site_email', '')),
                'site_description' => trim((string) $request->request->get('site_description', '')),
                'maintenance_mode' => $request->request->getBoolean('maintenance_mode') ? '1' : '0',
                'shipping_fee'     => str_replace(',', '.', trim((string) $request->request->get('shipping_fee', '0'))),
            ]);

            $this->addFlash('success', 'Paramètres enregistrés.');
            return $this->redirectToRoute('app_admin_settings');
        }

        return $this->render('admin/settings.html.twig', [
            'settings' => $settings->all(),
        ]);
    }
}
