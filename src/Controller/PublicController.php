<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\ArticleRepository;
use App\Repository\CategoryRepository;
use App\Repository\UserRepository;
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
    public function shop(Request $request, ArticleRepository $articleRepository): Response
    {
        $perPage = 12;
        $page = max(1, (int) $request->query->get('page', 1));

        $paginator = $articleRepository->paginateLatest($page, $perPage);
        $articles  = iterator_to_array($paginator);
        $total     = count($paginator);
        $pageCount = (int) ceil($total / $perPage);

        return $this->render('public/shop.html.twig', [
            'articles_first_group'  => array_slice($articles, 0, 4),
            'articles_second_group' => array_slice($articles, 4, 4),
            'articles_third_group'  => array_slice($articles, 8, 4),
            'current_page'          => $page,
            'page_count'            => $pageCount,
            'total_articles'        => $total,
        ]);
    }

    #[Route('/galerie', name: 'app_galerie')]
    public function galerie(ArticleRepository $articleRepository, CategoryRepository $categoryRepository): Response
    {
        return $this->render('public/galerie.html.twig', [
            'categories' => array_map(fn ($c) => $c->getName(), $categoryRepository->findAllOrdered()),
            'articles'   => $articleRepository->findBy([], ['createdAt' => 'DESC']),
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
            'id'        => $artistUser?->getId(),
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
