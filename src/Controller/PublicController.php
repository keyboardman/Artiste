<?php

namespace App\Controller;

use App\Form\ArticleUploadType;
use App\Service\ApiClientService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PublicController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        return $this->render('public/index.html.twig');
    }

    #[Route('/shop', name: 'app_shop')]
    public function shop(ApiClientService $api): Response
    {
        $articles = $api->getArticles();

        return $this->render('public/shop.html.twig', [
            'articles_first_group'  => array_slice($articles, 0, 4),
            'articles_second_group' => array_slice($articles, 4, 4),
            'articles_third_group'  => array_slice($articles, 8, 4),
        ]);
    }

    #[Route('/galerie', name: 'app_galerie')]
    public function galerie(ApiClientService $api): Response
    {
        $articles = $api->getArticles(['order[createdAt]' => 'DESC']);

        return $this->render('public/galerie.html.twig', [
            'categories' => [
                'Illustration',
                'Photographie',
                'Graphisme',
                'Peinture',
                'Digital Painting',
                'Motion Design',
            ],
            'articles' => $articles,
        ]);
    }

    #[Route('/stories', name: 'app_stories')]
    public function stories(): Response
    {
        return $this->render('public/stories.html.twig');
    }

    #[Route('/article/{id}', name: 'app_article_show', requirements: ['id' => '\d+'])]
    public function articleShow(int $id, ApiClientService $api): Response
    {
        $article = $api->getArticle($id);

        if (!$article) {
            throw $this->createNotFoundException('Article non trouvé');
        }

        $allArticles = $api->getArticles();
        $related = array_values(array_slice(
            array_filter($allArticles, fn($a) => $a['id'] !== $id),
            0, 4
        ));

        return $this->render('public/achat.html.twig', [
            'article'          => $article,
            'related_articles' => $related,
            'artist'           => [
                'image'      => 'img/artiste.jpg',
                'biography'  => "Biographie de l'artiste...",
                'social'     => '@artiste',
                'email'      => 'artiste@email.com',
                'phone'      => '+33 1 23 45 67 89',
            ],
        ]);
    }

    #[Route('/profile', name: 'app_profile')]
    public function profile(ApiClientService $api): Response
    {
        $user = $this->getUser();
        $articles = $user ? $api->getArticlesByUser($user->getId()) : [];

        $uploadForm = $this->createForm(ArticleUploadType::class, null, [
            'action' => $this->generateUrl('app_article_upload'),
            'method' => 'POST',
        ]);

        return $this->render('public/profile.html.twig', [
            'user'       => $user,
            'boards'     => $articles,
            'uploadForm' => $uploadForm,
        ]);
    }

    #[Route('/mentions-legales', name: 'app_mentions_legales')]
    public function mentionsLegales(): Response
    {
        return $this->render('public/mentions_legales.html.twig');
    }
}
