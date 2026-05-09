<?php

namespace App\Service;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class MailerService
{
    public function __construct(
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
        private string $fromEmail,
        private string $fromName,
    ) {}

    public function sendEmailVerification(User $user): void
    {
        $url = $this->urlGenerator->generate(
            'app_verify_email',
            ['token' => $user->getVerificationToken()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $email = (new TemplatedEmail())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to($user->getEmail())
            ->subject('Confirmez votre adresse email')
            ->htmlTemplate('emails/verify_email.html.twig')
            ->context([
                'user' => $user,
                'verificationUrl' => $url,
            ]);

        $this->mailer->send($email);
    }

    public function sendPasswordReset(User $user): void
    {
        $url = $this->urlGenerator->generate(
            'app_reset_password',
            ['token' => $user->getPasswordResetToken()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $email = (new TemplatedEmail())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to($user->getEmail())
            ->subject('Réinitialisation de votre mot de passe')
            ->htmlTemplate('emails/reset_password.html.twig')
            ->context([
                'user' => $user,
                'resetUrl' => $url,
                'expiresAt' => $user->getPasswordResetExpiresAt(),
            ]);

        $this->mailer->send($email);
    }

    public function sendOrderConfirmationToBuyer(Order $order): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to($order->getUser()->getEmail())
            ->subject(sprintf('Confirmation de votre commande #%s', $order->getReference()))
            ->htmlTemplate('emails/order_confirmation_buyer.html.twig')
            ->context(['order' => $order]);

        $this->mailer->send($email);
    }

    /**
     * @param OrderItem[] $items Articles de cet artiste dans la commande
     */
    public function sendOrderNotificationToArtist(User $artist, Order $order, array $items): void
    {
        if (!$artist->getEmail()) {
            return;
        }

        $email = (new TemplatedEmail())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to($artist->getEmail())
            ->subject(sprintf('Nouvelle vente — commande #%s', $order->getReference()))
            ->htmlTemplate('emails/order_notification_artist.html.twig')
            ->context([
                'artist' => $artist,
                'order'  => $order,
                'items'  => $items,
            ]);

        $this->mailer->send($email);
    }

    public static function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * @param Address[]  $recipients
     * @param User[]     $newUsers
     */
    public function sendWeeklySignupsReport(array $recipients, array $newUsers, \DateTimeImmutable $since): void
    {
        if (empty($recipients)) {
            return;
        }

        $email = (new TemplatedEmail())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to(...$recipients)
            ->subject(sprintf('[Placeaupro] Inscriptions de la semaine — %d nouveaux comptes', count($newUsers)))
            ->htmlTemplate('emails/weekly_signups_report.html.twig')
            ->context([
                'newUsers'    => $newUsers,
                'count'       => count($newUsers),
                'since'       => $since,
                'generatedAt' => new \DateTimeImmutable(),
            ]);

        $this->mailer->send($email);
    }
}
