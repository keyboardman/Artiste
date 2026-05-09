<?php

namespace App\Command;

use App\Repository\UserRepository;
use App\Service\MailerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsCommand(name: 'app:user:reset-link', description: 'Génère un lien de réinitialisation de mot de passe sans envoyer d\'email.')]
class UserResetLinkCommand extends Command
{
    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $em,
        private UrlGeneratorInterface $urlGenerator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'Email de l\'utilisateur');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $user = $this->userRepository->findOneBy(['email' => $input->getArgument('email')]);
        if (!$user) {
            $io->error(sprintf('Aucun utilisateur trouvé avec l\'email "%s".', $input->getArgument('email')));
            return Command::FAILURE;
        }

        $token = MailerService::generateToken();
        $user->setPasswordResetToken($token);
        $user->setPasswordResetExpiresAt(new \DateTimeImmutable('+1 hour'));
        $this->em->flush();

        $url = $this->urlGenerator->generate(
            'app_reset_password',
            ['token' => $token],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $io->success(sprintf('Lien valable 1h pour %s :', $user->getEmail()));
        $io->writeln($url);

        return Command::SUCCESS;
    }
}
