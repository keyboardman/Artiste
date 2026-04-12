<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Service\ApiClientService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(Request $request, ApiClientService $api): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $api->createUser([
                'email'         => $user->getEmail(),
                'plainPassword' => $form->get('plainPassword')->getData(),
                'firstname'     => $user->getFirstname(),
                'lastname'      => $user->getLastname(),
                'username'      => $user->getFirstname() . ' ' . $user->getLastname(),
                'roles'         => [],
            ]);

            $this->addFlash('success', 'Votre compte a été créé avec succès ! Vous pouvez maintenant vous connecter.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('public/login.html.twig', [
            'registrationForm' => $form,
            'registration_mode' => true,
        ]);
    }
}
