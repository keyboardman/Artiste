<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\LoginFormType;
use App\Form\RegistrationFormType;
use App\Repository\UserRepository;
use App\Service\ApiClientService;
use App\Service\MailerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        $form = $this->createForm(LoginFormType::class, [
            'email' => $lastUsername,
        ]);

        return $this->render('security/login.html.twig', [
            'loginForm' => $form,
            'error' => $error,
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        ApiClientService $api,
        UserRepository $userRepository,
        EntityManagerInterface $em,
        MailerService $mailerService,
    ): Response {
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

            $created = $userRepository->findOneBy(['email' => $user->getEmail()]);
            if ($created) {
                $created->setVerificationToken(MailerService::generateToken());
                $created->setIsVerified(false);
                $em->flush();

                try {
                    $mailerService->sendEmailVerification($created);
                } catch (\Throwable) {
                    $this->addFlash('warning', 'Compte créé, mais l\'email de confirmation n\'a pas pu être envoyé.');
                }
            }

            $this->addFlash('success', 'Votre compte a été créé ! Un email de confirmation vous a été envoyé.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }

    #[Route('/verify/email/{token}', name: 'app_verify_email', requirements: ['token' => '[a-f0-9]{64}'])]
    public function verifyEmail(string $token, UserRepository $userRepository, EntityManagerInterface $em): Response
    {
        $user = $userRepository->findOneBy(['verificationToken' => $token]);

        if (!$user) {
            $this->addFlash('error', 'Lien de vérification invalide ou déjà utilisé.');
            return $this->redirectToRoute('app_login');
        }

        $user->setIsVerified(true);
        $user->setVerificationToken(null);
        $em->flush();

        $this->addFlash('success', 'Votre adresse email a été confirmée. Vous pouvez maintenant vous connecter.');

        return $this->redirectToRoute('app_login');
    }

    #[Route('/forgot-password', name: 'app_forgot_password', methods: ['GET', 'POST'])]
    public function forgotPassword(
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $em,
        MailerService $mailerService,
    ): Response {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('forgot_password', $request->request->get('_token'))) {
                $this->addFlash('error', 'Token de sécurité invalide.');
                return $this->redirectToRoute('app_forgot_password');
            }

            $email = trim((string) $request->request->get('email', ''));

            if ($email !== '') {
                $user = $userRepository->findOneBy(['email' => $email]);

                if ($user) {
                    $user->setPasswordResetToken(MailerService::generateToken());
                    $user->setPasswordResetExpiresAt(new \DateTimeImmutable('+1 hour'));
                    $em->flush();

                    try {
                        $mailerService->sendPasswordReset($user);
                    } catch (\Throwable) {
                    }
                }
            }

            $this->addFlash('success', 'Si un compte existe pour cette adresse, un email de réinitialisation vient de vous être envoyé.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('public/forgot_password.html.twig');
    }

    #[Route('/reset-password/{token}', name: 'app_reset_password', requirements: ['token' => '[a-f0-9]{64}'], methods: ['GET', 'POST'])]
    public function resetPassword(
        string $token,
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
    ): Response {
        $user = $userRepository->findOneBy(['passwordResetToken' => $token]);

        if (!$user
            || $user->getPasswordResetExpiresAt() === null
            || $user->getPasswordResetExpiresAt() < new \DateTimeImmutable()
        ) {
            $this->addFlash('error', 'Le lien de réinitialisation est invalide ou expiré.');
            return $this->redirectToRoute('app_forgot_password');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('reset_password', $request->request->get('_token'))) {
                $this->addFlash('error', 'Token de sécurité invalide.');
                return $this->redirectToRoute('app_reset_password', ['token' => $token]);
            }

            $password = (string) $request->request->get('password', '');
            $confirm  = (string) $request->request->get('password_confirm', '');

            if (strlen($password) < 6) {
                $this->addFlash('error', 'Le mot de passe doit contenir au moins 6 caractères.');
                return $this->redirectToRoute('app_reset_password', ['token' => $token]);
            }

            if ($password !== $confirm) {
                $this->addFlash('error', 'Les mots de passe ne correspondent pas.');
                return $this->redirectToRoute('app_reset_password', ['token' => $token]);
            }

            $user->setPassword($passwordHasher->hashPassword($user, $password));
            $user->setPasswordResetToken(null);
            $user->setPasswordResetExpiresAt(null);
            $em->flush();

            $this->addFlash('success', 'Votre mot de passe a été réinitialisé. Vous pouvez maintenant vous connecter.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('public/reset_password.html.twig', [
            'token' => $token,
        ]);
    }
}
