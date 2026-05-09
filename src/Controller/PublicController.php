<?php

namespace App\Controller;

use App\Entity\Article;
use App\Entity\User;
use App\Form\ArticleUploadType;
use App\Repository\ArticleRepository;
use App\Repository\CategoryRepository;
use App\Repository\UserRepository;
use App\Service\ApiClientService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
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
    public function galerie(ApiClientService $api, CategoryRepository $categoryRepository): Response
    {
        $articles = $api->getArticles(['order[createdAt]' => 'DESC']);

        return $this->render('public/galerie.html.twig', [
            'categories' => array_map(fn ($c) => $c->getName(), $categoryRepository->findAllOrdered()),
            'articles'   => $articles,
        ]);
    }

    #[Route('/stories', name: 'app_stories')]
    public function stories(): Response
    {
        return $this->render('public/stories.html.twig');
    }

    #[Route('/article/{id}', name: 'app_article_show', requirements: ['id' => '\d+'])]
    public function articleShow(int $id, ArticleRepository $articleRepository): Response
    {
        $article = $articleRepository->find($id);

        if (!$article) {
            throw $this->createNotFoundException('Article non trouvé');
        }

        $artistUser = $article->getUser();

        $artist = [
            'image'     => $artistUser?->getAvatar() ?: 'img/artiste.jpg',
            'name'      => $artistUser?->getDisplayName() ?: '',
            'biography' => $artistUser?->getBio() ?: "Cet artiste n'a pas encore renseigné de biographie.",
            'social'    => $artistUser?->getSocial() ?: '',
            'email'     => $artistUser?->getEmail() ?: '',
            'phone'     => $artistUser?->getPhone() ?: '',
        ];

        $related = $articleRepository->createQueryBuilder('a')
            ->andWhere('a.id != :id')
            ->setParameter('id', $id)
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults(4)
            ->getQuery()
            ->getResult();

        return $this->render('public/achat.html.twig', [
            'article'          => $article,
            'images'           => $article->getAllImagePaths(),
            'related_articles' => $related,
            'artist'           => $artist,
        ]);
    }

    #[Route('/profile', name: 'app_profile')]
    public function profile(Request $request, ApiClientService $api, UserRepository $userRepository): Response
    {
        $artistUsername = trim((string) $request->query->get('artist', ''));

        if ($artistUsername !== '') {
            $user = $userRepository->findOneBy(['username' => $artistUsername]);

            if (!$user) {
                throw $this->createNotFoundException('Artiste introuvable');
            }
        } else {
            $user = $this->getUser();
        }

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

    #[Route('/artist/{id}', name: 'app_artist_show', requirements: ['id' => '\d+'])]
    public function artistShow(User $artist, ArticleRepository $articleRepository): Response
    {
        return $this->render('public/artist.html.twig', [
            'artist'   => $artist,
            'articles' => $articleRepository->findBy(['user' => $artist], ['createdAt' => 'DESC']),
        ]);
    }

    #[Route('/search', name: 'app_search', methods: ['GET'])]
    public function search(Request $request, ArticleRepository $articleRepository, UserRepository $userRepository): Response
    {
        $q = trim((string) $request->query->get('q', ''));

        if ($q === '') {
            return $this->render('public/search.html.twig', [
                'q'        => '',
                'articles' => [],
                'artists'  => [],
            ]);
        }

        return $this->render('public/search.html.twig', [
            'q'        => $q,
            'articles' => $articleRepository->findByKeyword($q),
            'artists'  => $userRepository->findByKeyword($q),
        ]);
    }

    #[Route('/mentions-legales', name: 'app_mentions_legales')]
    public function mentionsLegales(): Response
    {
        return $this->render('public/mentions_legales.html.twig');
    }
}
