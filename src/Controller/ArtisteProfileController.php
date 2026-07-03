<?php

namespace App\Controller;

use App\Entity\Article;
use App\Form\ArticleUploadType;
use App\Repository\ArticleRepository;
use App\Service\ApiClientService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Regroupe tout ce que l'artiste connecté peut gérer dans son propre profil :
 * affichage, édition, upload et suppression d'œuvres.
 * Utilise systématiquement la session via $this->getUser().
 */
#[Route('/artiste')]
#[IsGranted('ROLE_USER')]
class ArtisteProfileController extends AbstractController
{
    #[Route('/profile', name: 'app_profile')]
    public function profile(ArticleRepository $articleRepository): Response
    {
        $user = $this->getUser();

        $uploadForm = $this->createForm(ArticleUploadType::class, null, [
            'action' => $this->generateUrl('app_article_upload'),
            'method' => 'POST',
        ]);

        return $this->render('public/profile.html.twig', [
            'user'       => $user,
            'boards'     => $articleRepository->findBy(['user' => $user], ['createdAt' => 'DESC']),
            'uploadForm' => $uploadForm,
        ]);
    }

    #[Route('/profile/edit', name: 'app_profile_edit', methods: ['GET', 'POST'])]
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

    #[Route('/article/upload', name: 'app_article_upload', methods: ['POST'])]
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

    #[Route('/article/delete/{id}', name: 'app_article_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function articleDelete(int $id, ArticleRepository $articleRepository, EntityManagerInterface $em, Request $request): Response
    {
        $article = $articleRepository->find($id);
        $user = $this->getUser();

        if ($article
            && $article->getUser()?->getId() === $user->getId()
            && $this->isCsrfTokenValid('delete_article_' . $id, $request->request->get('_token'))
        ) {
            $em->remove($article);
            $em->flush();
            $this->addFlash('success', 'Œuvre supprimée.');
        }

        return $this->redirectToRoute('app_profile');
    }
}
