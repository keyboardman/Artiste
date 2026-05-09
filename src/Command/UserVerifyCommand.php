<?php

namespace App\Command;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:user:verify', description: 'Marque un utilisateur comme vérifié sans passer par le lien email.')]
class UserVerifyCommand extends Command
{
    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $em,
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

        if ($user->isVerified()) {
            $io->note(sprintf('%s est déjà vérifié.', $user->getEmail()));
            return Command::SUCCESS;
        }

        $user->setIsVerified(true);
        $user->setVerificationToken(null);
        $this->em->flush();

        $io->success(sprintf('%s est maintenant vérifié.', $user->getEmail()));

        return Command::SUCCESS;
    }
}
